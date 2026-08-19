<?php

namespace App\Support\Audit;

use App\Models\User;

class RestrictedLawyerAuditAccess
{
    public const ALAA_KATOU = 'آلاء أسعد إسماعيل كتوع';

    public const EYAD_BAKRI = 'المحامي/ اياد هاني وحيد بكري';

    /**
     * @return array<int, array{from: int, to: int|null, lawyer: string}>
     */
    public static function ranges(): array
    {
        return [
            ['from' => 1, 'to' => 1000, 'lawyer' => self::ALAA_KATOU],
            ['from' => 1001, 'to' => null, 'lawyer' => self::EYAD_BAKRI],
        ];
    }

    public static function lawyerForExcelIndex(int $excelIndex): string
    {
        foreach (self::ranges() as $range) {
            if ($excelIndex >= $range['from'] && ($range['to'] === null || $excelIndex <= $range['to'])) {
                return $range['lawyer'];
            }
        }

        return self::EYAD_BAKRI;
    }

    public static function restrictedLawyerNameFor(?User $user): ?string
    {
        if (! $user instanceof User) {
            return null;
        }

        $normalizedName = self::normalizeName($user->name);

        foreach ([self::ALAA_KATOU, self::EYAD_BAKRI] as $lawyerName) {
            if ($normalizedName === self::normalizeName($lawyerName)) {
                return $lawyerName;
            }
        }

        return null;
    }

    public static function isRestrictedLawyer(?User $user): bool
    {
        return false;
    }

    public static function canViewAssignments(?User $user): bool
    {
        return self::restrictedLawyerNameFor($user) !== null
            || ($user?->hasRole('Database Officer') ?? false);
    }

    public static function normalizeGlobalid(mixed $value): string
    {
        return trim(trim((string) $value), "{} \t\n\r\0\x0B");
    }

    private static function normalizeName(string $name): string
    {
        $name = str_replace('المحامي/', '', $name);
        $name = str_replace(['إ', 'أ', 'آ'], 'ا', $name);

        return preg_replace('/\s+/u', ' ', trim($name)) ?: trim($name);
    }
}
