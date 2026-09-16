<?php

namespace App\Services;

/**
 * Turns a list of findings into a 0-100 score and an A-F grade.
 *
 * The weights are deliberately steep: one critical finding (an exposed .env,
 * a leaked private key) should not leave an endpoint sitting on a B.
 */
class SecurityScore
{
    private const PENALTY = [
        'critical' => 45,
        'high' => 22,
        'medium' => 9,
        'low' => 3,
    ];

    private const GRADES = [
        90 => 'A',
        80 => 'B',
        70 => 'C',
        60 => 'D',
        45 => 'E',
    ];

    /**
     * @param  array<int, array{severity: string}>  $findings
     */
    public static function calculate(array $findings): int
    {
        $penalty = collect($findings)->sum(fn (array $finding) => self::PENALTY[$finding['severity']] ?? 0);

        return (int) max(0, min(100, 100 - $penalty));
    }

    public static function grade(int $score): string
    {
        foreach (self::GRADES as $floor => $grade) {
            if ($score >= $floor) {
                return $grade;
            }
        }

        return 'F';
    }

    /**
     * Short verdict shown next to the grade.
     */
    public static function verdict(int $score): string
    {
        return match (true) {
            $score >= 90 => 'Konfigurasi keamanan rapi',
            $score >= 80 => 'Aman, tinggal rapikan sedikit',
            $score >= 70 => 'Ada beberapa hal yang perlu diperbaiki',
            $score >= 60 => 'Cukup banyak celah konfigurasi',
            $score >= 45 => 'Banyak masalah, sebaiknya segera ditangani',
            default => 'Kritis, perbaiki sebelum dipakai publik',
        };
    }
}
