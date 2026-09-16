# API Scanner

[![tests](https://github.com/ZainulArkaanAlinsi/api-security-scanner/actions/workflows/tests.yml/badge.svg)](https://github.com/ZainulArkaanAlinsi/api-security-scanner/actions/workflows/tests.yml)
[![license](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Aplikasi web Laravel untuk mengaudit konfigurasi keamanan endpoint API. Tambahkan URL, jalankan scan, dan dapatkan daftar temuan yang diurutkan berdasarkan tingkat risiko beserta cara memperbaikinya.

## Tampilan

| Dashboard | Hasil scan |
|---|---|
| ![Dashboard](docs/screenshots/dashboard.jpg) | ![Detail hasil scan](docs/screenshots/scan-detail.jpg) |

![Halaman login](docs/screenshots/login.jpg)

## Fitur

- **Scan lewat antrean**: request langsung kembali, scan dikerjakan worker di latar belakang, dan halaman ticket memperbarui dirinya sendiri saat hasilnya siap
- **Scanner keamanan**: hingga 12 pemeriksaan per scan
  - HTTPS, masa berlaku sertifikat TLS, HSTS
  - CORS (wildcard, wildcard + credentials)
  - Cookie tanpa `HttpOnly` / `Secure`
  - Stack trace / pesan debug yang bocor, error 5xx
  - `X-Content-Type-Options`, proteksi clickjacking, `Content-Security-Policy`
  - Kebocoran versi software (`Server`, `X-Powered-By`), waktu respons
- **Riwayat scan & perbandingan**: setiap scan disimpan; halaman ticket menampilkan grafik tren, temuan yang sudah diperbaiki, dan yang baru muncul sejak scan sebelumnya
- **Monitoring otomatis**: aktifkan per ticket, lalu `scan:due` men-scan ulang tiap 6 jam dan mengirim email kalau ada temuan high/critical baru
- **Laporan**: versi cetak / simpan PDF, unduhan JSON (termasuk riwayat), dan export CSV daftar ticket
- **Dashboard**: statistik, distribusi risiko, pencarian, filter status & risiko
- **Akun**: register, login (dibatasi 5 percobaan/menit), lupa & reset password, profil, ganti password, hapus akun
- **Keamanan aplikasi**
  - Proteksi SSRF: localhost, IP privat, dan alamat metadata cloud tidak bisa di-scan; koneksi dikunci ke IP yang sudah divalidasi dan redirect tidak diikuti
  - Scan dibatasi ke port 80/443 dan respons dipotong di 5 MB, jadi scanner tidak bisa dipakai sebagai port prober atau dijadikan alat menghabiskan memori server
  - Satu ticket tidak bisa di-scan dua kali bersamaan, dan kegagalan tak terduga tidak meninggalkan ticket berstatus menggantung
  - Setiap user hanya bisa mengakses ticket miliknya (akses ke ticket orang lain → 404)
  - Rate limit pada login, register, reset password, dan scan
- Mode terang/gelap, halaman error kustom (403, 404, 419, 429, 500)

## Kebutuhan

- PHP 8.2+ dengan ekstensi `curl`, `openssl`, `pdo_mysql` (dan `pdo_sqlite` untuk test)
- Composer, Node.js
- MySQL / MariaDB

## Instalasi

```bash
composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate
```

Atur database di `.env`, lalu:

```bash
php artisan migrate
php artisan db:seed --class=DemoSeeder   # opsional: data contoh
php artisan serve
```

Scan berjalan lewat antrean, jadi jalankan worker di terminal terpisah:

```bash
php artisan queue:work
```

Tanpa worker, ticket akan berhenti di status "Sedang scan" dan tidak pernah selesai.

Buka http://127.0.0.1:8000.

### Akun demo

Setelah menjalankan `DemoSeeder`:

| Email | Password |
|---|---|
| `demo@example.com` | `demo12345` |

Akun ini berisi 4 ticket contoh: API yang membaik dari waktu ke waktu, endpoint legacy bermasalah, sertifikat yang hampir kedaluwarsa, dan ticket yang belum di-scan. Jangan jalankan seeder ini di production.

## Konfigurasi

| Variabel `.env` | Default | Keterangan |
|---|---|---|
| `SCANNER_CA_BUNDLE` | kosong | Path file CA untuk verifikasi HTTPS. **Wajib di Laragon/Windows** jika `curl.cainfo` di `php.ini` kosong, misalnya `C:/laragon/etc/ssl/cacert.pem`. Tanpa ini semua scan HTTPS gagal. |
| `SCANNER_ALLOW_PRIVATE` | `false` | Izinkan scan ke IP privat/localhost. Aktifkan hanya di mesin lokal untuk mengetes API sendiri. |
| `SCANNER_TIMEOUT` | `10` | Batas waktu respons target (detik). |
| `MAIL_MAILER` | `log` | Dengan `log`, link reset password ditulis ke `storage/logs/laravel.log`. Isi konfigurasi SMTP agar email benar-benar terkirim. |
| `APP_LOCALE` | `id` | Bahasa tanggal dan waktu. |

## Monitoring otomatis

Aktifkan monitoring lewat tombol di halaman ticket, lalu jalankan scheduler agar scan harian berjalan:

```bash
php artisan schedule:work      # lokal, biarkan jalan di terminal terpisah
```

Di server, tambahkan satu cron job:

```
* * * * * cd /path/ke/project && php artisan schedule:run >> /dev/null 2>&1
```

Jadwalnya: `scan:due --hours=2` setiap 6 jam (00.00, 06.00, 12.00, 18.00 waktu `APP_TIMEZONE`). Bisa juga dijalankan manual:

```bash
php artisan scan:due                 # ticket yang belum di-scan 24 jam terakhir
php artisan scan:due --hours=6       # lebih sering
php artisan scan:due --limit=5       # batasi jumlah per run
```

Email peringatan hanya dikirim untuk temuan **high/critical yang belum ada di scan sebelumnya**, jadi tidak ada email berulang untuk masalah yang sama.

## Checklist sebelum deploy

Aplikasi ini mengaudit keamanan orang lain, jadi konfigurasinya sendiri harus benar:

- [ ] `APP_ENV=production`, `APP_DEBUG=false` (mode debug membocorkan stack trace dan isi env ke pengunjung)
- [ ] `APP_KEY` dibuat ulang dengan `php artisan key:generate`
- [ ] `APP_URL` diisi domain sebenarnya (dipakai link di email reset password dan notifikasi)
- [ ] `SESSION_SECURE_COOKIE=true` saat memakai HTTPS
- [ ] `SCANNER_ALLOW_PRIVATE=false` (nilai default; jangan diaktifkan di server publik)
- [ ] Konfigurasi SMTP diisi, jangan biarkan `MAIL_MAILER=log`
- [ ] Jangan jalankan `DemoSeeder` di production
- [ ] Queue worker dijalankan sebagai service (Supervisor/systemd), bukan manual di terminal

Header keamanan (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, CSP, dan HSTS saat HTTPS) sudah dipasang otomatis oleh `app/Http/Middleware/SecurityHeaders.php`.

## Menjalankan test

```bash
php artisan test
```

Test memakai SQLite in-memory (diatur di `phpunit.xml`), jadi database MySQL tidak tersentuh. Request HTTP dan pemeriksaan sertifikat di-mock, sehingga test tidak butuh koneksi internet.

## Struktur penting

| Lokasi | Isi |
|---|---|
| `app/Services/ApiScanner.php` | Logika scan dan semua pemeriksaan |
| `app/Services/ScanRunner.php` | Menjalankan scan, menyimpan riwayat, memicu notifikasi |
| `app/Console/Commands/ScanDueTickets.php` | Command `scan:due` untuk monitoring otomatis |
| `app/Services/CertificateInspector.php` | Membaca masa berlaku sertifikat TLS |
| `app/Http/Controllers/TicketScanController.php` | Menjalankan scan, laporan JSON, dan laporan cetak |
| `app/Policies/TicketPolicy.php` | Aturan kepemilikan ticket |
| `app/Models/Scan.php` | Riwayat scan per ticket |
| `config/scanner.php` | Konfigurasi scanner |
| `resources/views/partials/theme.blade.php` | Design token dan komponen UI bersama |

## Catatan

- Scan dikerjakan queue worker, jadi endpoint lambat tidak menahan request web. Job diberi batas 60 detik; kalau worker mati di tengah jalan, ticket ditandai gagal, bukan menggantung.
- Scanner hanya mengirim satu request `GET` ke URL yang didaftarkan. Scan hanya endpoint yang kamu miliki atau punya izin untuk diuji.
