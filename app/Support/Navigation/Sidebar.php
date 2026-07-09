<?php

namespace App\Support\Navigation;

use App\Models\User;
use Illuminate\Support\Collection;

class Sidebar
{
    private const TEMPORARY_AUDIT_HOME_USER_NAMES = [
        'ياسمين ماهر مصطفى ابومدللة',
        'غادة محمود عبدالحي الهباش',
        'رانيه سليمان راشد شعت',
    ];

    private const TEMPORARY_AUDIT_HOME_USER_ID_NUMBERS = [
         '800409062',
        '400940623',
        '803275288',
        '800900607',
        '801773987'
    ];

    private const TEMPORARY_AUDIT_HOME_URL = 'damage-assessment/audit';

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function forUser(User $user): Collection
    {
        $sectionsByModule = collect(config('sidebar'))
            ->groupBy(fn (array $section): string => $section['module'] ?? 'damage_assessment');

        return collect(config('modules'))
            ->filter(fn (array $module): bool => $module['enabled'] ?? true)
            ->sortBy('order')
            ->map(function (array $module, string $moduleKey) use ($sectionsByModule, $user): ?array {
                $sections = $sectionsByModule
                    ->get($moduleKey, collect())
                    ->map(fn (array $section): ?array => self::visibleSection($section, $user))
                    ->filter()
                    ->values();

                if ($sections->isEmpty()) {
                    return null;
                }

                $module['key'] = $moduleKey;
                $module['sections'] = $sections;

                return $module;
            })
            ->filter()
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function visibleSection(array $section, User $user): ?array
    {
        if (! $user->hasAnyRole($section['roles'] ?? []) && ! self::hasTemporaryVisibleItem($section, $user)) {
            return null;
        }

        if (isset($section['url'])) {
            $section['items'] = collect();
            $section['visible_item_count'] = 0;
            $section['is_active'] = request()->is(...($section['active_patterns'] ?? [$section['pattern'] ?? '']));
            $section['is_direct'] = true;

            return $section;
        }

        $visibleItems = collect($section['items'] ?? [])
            ->map(fn (array $item): ?array => self::visibleItem($item, $user))
            ->filter()
            ->values();

        if ($visibleItems->isEmpty()) {
            return null;
        }

        $section['items'] = $visibleItems;
        $section['visible_item_count'] = $visibleItems->sum(
            fn (array $item): int => isset($item['children']) ? $item['children']->count() : 1
        );
        $section['is_active'] = request()->is(...($section['active_patterns'] ?? []));

        return $section;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function visibleItem(array $item, User $user): ?array
    {
        if (isset($item['children'])) {
            $children = collect($item['children'])
                ->filter(fn (array $child): bool => $user->hasAnyRole($child['roles'] ?? []))
                ->values();

            if ($children->isEmpty()) {
                return null;
            }

            $item['children'] = $children;

            return $item;
        }

        return $user->hasAnyRole($item['roles'] ?? []) || self::isTemporaryAuditHomeItem($item, $user) ? $item : null;
    }

    private static function hasTemporaryVisibleItem(array $section, User $user): bool
    {
        return collect($section['items'] ?? [])
            ->contains(fn (array $item): bool => self::isTemporaryAuditHomeItem($item, $user));
    }

    private static function isTemporaryAuditHomeItem(array $item, User $user): bool
    {
        return ($item['url'] ?? null) === self::TEMPORARY_AUDIT_HOME_URL
            && (
                in_array(trim($user->name), self::TEMPORARY_AUDIT_HOME_USER_NAMES, true)
                || in_array(trim((string) $user->id_no), self::TEMPORARY_AUDIT_HOME_USER_ID_NUMBERS, true)
            );
    }
}
