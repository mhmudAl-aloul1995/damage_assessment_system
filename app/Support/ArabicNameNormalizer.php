<?php

namespace App\Support;

class ArabicNameNormalizer
{
    public static function normalize(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        $normalized = trim($name);
        $normalized = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $normalized) ?? '';
        $normalized = str_replace(['ـ', '’', "'", '`', '´'], '', $normalized);
        $normalized = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $normalized);
        $normalized = str_replace('ة', 'ه', $normalized);
        $normalized = str_replace('ى', 'ي', $normalized);
        $normalized = str_replace('ؤ', 'و', $normalized);
        $normalized = str_replace('ئ', 'ي', $normalized);
        $normalized = preg_replace('/[^\p{Arabic}\p{L}\p{N}]+/u', '', $normalized) ?? '';

        return trim($normalized);
    }
}
