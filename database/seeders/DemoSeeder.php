<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\User;
use App\Services\SecurityScore;
use Illuminate\Database\Seeder;

/**
 * Demo account with realistic sample tickets and scan history, without
 * hitting the network. Run: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Akun Demo', 'password' => 'demo12345', 'email_verified_at' => now()]
        );

        // Re-running the seeder resets the demo data instead of duplicating it.
        $user->tickets()->delete();

        $check = fn (string $label, bool $passed, string $severity = 'low', string $detail = '') => compact('label', 'passed', 'severity') + ['detail' => $passed ? '' : $detail];

        $allPass = [
            $check('Koneksi memakai HTTPS', true, 'high'),
            $check('Header Strict-Transport-Security', true, 'medium'),
            $check('Header X-Content-Type-Options: nosniff', true),
            $check('Proteksi clickjacking', true),
            $check('Header Content-Security-Policy', true),
            $check('Konfigurasi CORS', true, 'medium'),
            $check('Versi software tidak terekspos', true),
            $check('Cookie memakai HttpOnly dan Secure', true, 'medium'),
            $check('Tidak ada error server (5xx)', true, 'medium'),
            $check('Tidak ada stack trace atau pesan debug', true, 'high'),
            $check('Waktu respons di bawah 2 detik', true),
            $check('Sertifikat TLS berlaku lebih dari 14 hari', true, 'medium'),
        ];

        $fail = function (array $checks, array $failures): array {
            return array_map(function (array $check) use ($failures) {
                if (isset($failures[$check['label']])) {
                    [$severity, $detail] = $failures[$check['label']];

                    return ['label' => $check['label'], 'passed' => false, 'severity' => $severity, 'detail' => $detail];
                }

                return $check;
            }, $checks);
        };

        // 1. Improved over time: first scan had issues, latest one is clean.
        $this->ticketWithHistory($user, 'Payment API production', 'https://pay.example.com/v1/health', 'Dipakai checkout web dan mobile.', [
            [9, $fail($allPass, [
                'Konfigurasi CORS' => ['high', 'Access-Control-Allow-Origin: * digabung Allow-Credentials: true. Batasi origin ke domain yang dipercaya.'],
                'Versi software tidak terekspos' => ['low', 'Header membocorkan versi software (Server: nginx/1.18.0).'],
                'Header Strict-Transport-Security' => ['medium', 'Tanpa HSTS, browser masih bisa diarahkan ke versi http.'],
            ]), 200, 412],
            [2, $fail($allPass, [
                'Versi software tidak terekspos' => ['low', 'Header membocorkan versi software (Server: nginx/1.18.0).'],
            ]), 200, 380],
            [0, $allPass, 200, 356],
        ]);

        // 2. Legacy endpoint with serious problems.
        $legacy = array_values(array_filter($allPass, fn ($c) => $c['label'] !== 'Header Strict-Transport-Security' && $c['label'] !== 'Sertifikat TLS berlaku lebih dari 14 hari'));
        $this->ticketWithHistory($user, 'Legacy partner API', 'http://partner.example.com/api/orders', null, [
            [1, $fail($legacy, [
                'Koneksi memakai HTTPS' => ['high', 'Data dikirim tanpa enkripsi dan bisa disadap atau diubah di tengah jalan.'],
                'Tidak ada stack trace atau pesan debug' => ['high', 'Respons berisi stack trace atau pesan error internal. Matikan mode debug di production.'],
                'Tidak ada error server (5xx)' => ['medium', 'Endpoint mengembalikan status 500.'],
                'Header X-Content-Type-Options: nosniff' => ['low', 'Browser bisa menebak tipe konten.'],
            ]), 500, 1840],
        ]);

        // 3. Certificate about to expire.
        $this->ticketWithHistory($user, 'Internal reporting API', 'https://reports.example.com/v2', 'Sertifikat dikelola manual oleh tim infra.', [
            [0, $fail($allPass, [
                'Sertifikat TLS berlaku lebih dari 14 hari' => ['high', 'Sertifikat habis dalam 5 hari. Perpanjang sekarang.'],
                'Header Content-Security-Policy' => ['low', 'Tidak ada CSP untuk membatasi sumber script dan konten.'],
            ]), 200, 640, 5],
        ]);

        // 4. Not scanned yet, but already being monitored.
        $user->tickets()->create([
            'title' => 'Notifications webhook',
            'api_url' => 'https://hooks.example.com/notify',
            'auto_scan' => true,
        ]);
    }

    /**
     * @param  array<int, array{0:int, 1:array, 2:int, 3:int, 4?:int}>  $runs  [daysAgo, checks, statusCode, ms, certDays?]
     */
    private function ticketWithHistory(User $user, string $title, string $url, ?string $description, array $runs): Ticket
    {
        $ticket = $user->tickets()->create(['title' => $title, 'api_url' => $url, 'description' => $description]);

        foreach ($runs as $run) {
            [$daysAgo, $checks, $statusCode, $ms] = $run;

            $findings = collect($checks)
                ->reject(fn ($c) => $c['passed'])
                ->map(fn ($c) => ['title' => $c['label'], 'severity' => $c['severity'], 'detail' => $c['detail']])
                ->sortByDesc(fn ($f) => ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4][$f['severity']])
                ->values()
                ->all();

            $result = [
                'status_code' => $statusCode,
                'response_time_ms' => $ms,
                'ip' => '203.0.113.10',
                'redirect_to' => null,
                'certificate_days_left' => str_starts_with($url, 'https') ? ($run[4] ?? 74) : null,
                'headers' => ['Content-Type' => 'application/json'],
                'checks' => $checks,
            ];

            $scannedAt = now()->subDays($daysAgo)->subMinutes(random_int(5, 300));
            $severity = $findings[0]['severity'] ?? null;
            $score = SecurityScore::calculate($findings);
            $grade = SecurityScore::grade($score);

            $scan = $ticket->scans()->create([
                'status' => 'completed',
                'severity' => $severity,
                'findings' => $findings,
                'result' => $result,
                'score' => $score,
                'grade' => $grade,
            ]);
            $scan->forceFill(['created_at' => $scannedAt, 'updated_at' => $scannedAt])->save();

            $ticket->forceFill([
                'status' => 'completed',
                'severity' => $severity,
                'findings' => $findings,
                'scan_result' => $result,
                'scanned_at' => $scannedAt,
                'score' => $score,
                'grade' => $grade,
            ])->save();
        }

        return $ticket;
    }
}
