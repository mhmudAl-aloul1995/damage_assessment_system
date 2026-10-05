<?php

namespace App\Modules\Heks\Services;

use App\Models\User;
use Illuminate\Support\Str;

class HeksEngineerUserResolver
{
    public function resolve(?string $engineerName): ?int
    {
        $normalized = $this->normalize($engineerName);

        if ($normalized === null) {
            return null;
        }

        return User::query()
            ->select(['id', 'name', 'name_en', 'username_arcgis', 'email'])
            ->get()
            ->first(fn (User $user): bool => $this->matches($normalized, $user))
            ?->id;
    }

    private function matches(string $engineerName, User $user): bool
    {
        foreach ([$user->name, $user->name_en, $user->username_arcgis, $user->email] as $candidate) {
            $normalizedCandidate = $this->normalize($candidate);

            if ($normalizedCandidate !== null && $normalizedCandidate === $engineerName) {
                return true;
            }
        }

        return false;
    }

    private function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || in_array(Str::lower($value), ['n/a', 'n/a#', 'na', 'none', '-'], true)) {
            return null;
        }

        $value = Str::of($value)
            ->lower()
            ->replace(['م.', 'م ', 'eng.', 'engineer', '____', '___', '__'], ' ')
            ->replaceMatches('/[^\p{Arabic}a-z0-9@._ -]+/u', ' ')
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->toString();

        return $value !== '' ? $value : null;
    }
}
