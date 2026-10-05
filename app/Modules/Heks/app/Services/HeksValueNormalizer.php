<?php

namespace App\Modules\Heks\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

class HeksValueNormalizer
{
    public function digits(mixed $value): string
    {
        return strtr((string) $value, [
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
        ]);
    }

    public function code(mixed $value): string
    {
        $value = trim($this->digits($value));

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        if (preg_match('/^(.+)\.0$/', $value, $matches) === 1) {
            return $matches[1];
        }

        return $value;
    }

    public function money(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = $this->digits($value);
        $normalized = str_replace([',', ' ', "\u{00A0}", '%'], '', $normalized);
        $normalized = preg_replace('/[^\d.\-]/', '', $normalized) ?? '';

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    public function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = $this->digits($value);

        try {
            if (is_numeric($value) && (float) $value > 20000) {
                return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
            }

            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function boolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = Str::of($this->digits($value))->trim()->lower()->toString();

        return match ($normalized) {
            '1', 'true', 'yes', 'y', 'نعم', 'yes_selected' => true,
            '0', 'false', 'no', 'n', 'لا', 'not_selected' => false,
            default => null,
        };
    }

    public function uuid(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return Str::startsWith($value, 'uuid:') ? $value : Str::after($value, 'uuid:');
    }
}
