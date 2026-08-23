<?php

namespace App\Services;

use App\Models\Semester;
use App\Models\User;

class EvaluationReferenceService
{
    /**
     * Generate a tamper-proof purely numeric 14-digit reference ID for a student's completed semester evaluation.
     *
     * Format: {YYYY}{SS}{UUUUU}{CCCC}
     * - YYYY: 4-digit Academic Year start (e.g. 2026)
     * - SS: 2-digit Semester code (01 for 1st Sem, 02 for 2nd Sem, 03 for Summer)
     * - UUUUU: 5-digit User ID (padded with zeros)
     * - CCCC: 4-digit HMAC checksum derived from APP_KEY
     */
    public static function generate(int $userId, int $semesterId, ?Semester $semester = null): string
    {
        static $semCache = [];
        if (! $semester) {
            $semester = $semCache[$semesterId] ??= Semester::with('academicYear')->find($semesterId);
        }

        $year = (int) date('Y');
        if ($semester?->academicYear?->name) {
            if (preg_match('/(\d{4})/', $semester->academicYear->name, $matches)) {
                $year = (int) $matches[1];
            }
        }

        $semCode = 1;
        if ($semester?->name) {
            if (stripos($semester->name, '2nd') !== false || stripos($semester->name, 'second') !== false) {
                $semCode = 2;
            } elseif (stripos($semester->name, 'summer') !== false || stripos($semester->name, 'midyear') !== false) {
                $semCode = 3;
            }
        }

        $userPart = sprintf('%05d', $userId % 100000);
        $prefix = sprintf('%04d%02d%s', $year, $semCode, $userPart);

        $key = config('app.key') ?: 'grc_evaluation_secret_salt_2026';
        $rawHash = hash_hmac('sha256', "student_eval_reference:{$userId}:{$semesterId}:{$prefix}", (string) $key);
        $checksum = sprintf('%04d', hexdec(substr($rawHash, 0, 4)) % 10000);

        return $prefix.$checksum;
    }

    /**
     * Format a raw 15-digit numeric reference ID into a readable hyphenated format: YYYY-SS-UUUUU-CCCC
     */
    public static function format(string $rawId): string
    {
        $clean = (string) preg_replace('/\D/', '', $rawId);
        if (strlen($clean) === 15) {
            return substr($clean, 0, 4).'-'.substr($clean, 4, 2).'-'.substr($clean, 6, 5).'-'.substr($clean, 11, 4);
        }

        return $rawId;
    }

    /**
     * Verify if a given reference ID matches the expected deterministic checksum for a student and semester.
     */
    public static function verify(string $rawOrFormattedId, int $userId, int $semesterId): bool
    {
        $clean = (string) preg_replace('/\D/', '', $rawOrFormattedId);

        return $clean === self::generate($userId, $semesterId);
    }
}
