<?php

namespace App\Support\Navigation;

use App\Models\User;
use App\Support\Audit\RestrictedLawyerAuditAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
        '400591194',
        '404581993',
        '456901503',
        '400662938',
        '803275288',
        '800900607',
        '801773987',
        '405790619',
        '403697311',
        '803307669',
        '404030421',
        '403746530',
        '406966812',
        '404581993',
        '456901503',
        '400662938',
        '403746530',
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
                $module['is_active'] = request()->is(...($module['active_patterns'] ?? []))
                    || $sections->contains(fn (array $section): bool => $section['is_active']);
                $module['url'] = $sections
                    ->flatMap(fn (array $section) => $section['is_direct'] ?? false ? [$section] : $section['items'])
                    ->flatMap(fn (array $item) => $item['children'] ?? [$item])
                    ->pluck('url')
                    ->first();

                return $module;
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $modules
     * @return array<string, mixed>|null
     */
    public static function currentModule(Collection $modules): ?array
    {
        return $modules->firstWhere('is_active', true) ?? $modules->first();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function isItemVisibleToUser(array $item, User $user): bool
    {
        return self::isItemVisible($item, $user);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function visibleSection(array $section, User $user): ?array
    {
        if (! ($section['sidebar_visible'] ?? true)) {
            return null;
        }

        if (! $user->hasAnyRole($section['roles'] ?? [])
            && ! self::isItemVisible($section, $user)
            && ! self::hasVisibleItem($section, $user)) {
            return null;
        }

        if (isset($section['url'])) {
            if (isset($section['sector'])) {
                $firstVisibleSectorTab = collect(SectorNavigation::forUser($section['sector'], $user))->first();

                if ($firstVisibleSectorTab === null) {
                    return null;
                }

                $section['url'] = Str::after($firstVisibleSectorTab['url'], url('/').'/');
            }

            $section['items'] = collect();
            $section['visible_item_count'] = 0;
            $section['is_active'] = self::isActive($section)
                || (isset($section['sector']) && $section['sector'] === SectorNavigation::sectorForCurrentRoute());
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
        $section['is_active'] = self::isActive($section);

        return $section;
    }

    private static function isActive(array $section): bool
    {
        $activePatterns = $section['active_patterns'] ?? [$section['pattern'] ?? ''];

        if (! request()->is(...$activePatterns)) {
            return false;
        }

        $excludedPatterns = $section['exclude_active_patterns'] ?? [];

        return $excludedPatterns === [] || ! request()->is(...$excludedPatterns);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function visibleItem(array $item, User $user): ?array
    {
        if (isset($item['children'])) {
            $children = collect($item['children'])
                ->filter(fn (array $child): bool => self::isItemVisible($child, $user))
                ->values();

            if ($children->isEmpty()) {
                return null;
            }

            $item['children'] = $children;

            return $item;
        }

        return self::isItemVisible($item, $user) ? $item : null;
    }

    private static function hasVisibleItem(array $section, User $user): bool
    {
        return collect($section['items'] ?? [])
            ->contains(fn (array $item): bool => self::visibleItem($item, $user) !== null);
    }

    private static function isItemVisible(array $item, User $user): bool
    {
        if ($user->hasRole('UNDP')) {
            $permission = self::undpPermissionForUrl($item['url'] ?? null);

            if ($permission !== null) {
                return $user->can($permission);
            }
        }

        return $user->hasAnyRole($item['roles'] ?? [])
            || collect($item['permissions'] ?? [])->contains(fn (string $permission): bool => $user->can($permission))
            || self::isCustomVisibleItem($item, $user)
            || self::isTemporaryAuditHomeItem($item, $user);
    }

    private static function undpPermissionForUrl(?string $url): ?string
    {
        if ($url === null || ! str_starts_with($url, 'damage-assessment/')) {
            return null;
        }

        if (preg_match('/(^|[\/_-])(export|create|edit|import|sync|delete|approve|reject|reset|cancel|process|sign|assign|retry)([\/_-]|$)/i', $url) === 1) {
            return null;
        }

        return match (true) {
            str_contains($url, 'building-deletions') => 'damage-assessment.building-deletion.view',
            str_contains($url, 'building-survey-return-requests') => 'building-survey-return-requests.view',
            str_contains($url, '/attendance/dashboard') => 'attendance.reports.view',
            str_contains($url, '/attendance') => 'attendance.view',
            str_contains($url, '/public-buildings') => 'inf-audit.public-buildings.view',
            str_contains($url, '/road-facilities') => 'inf-audit.roads.view',
            str_contains($url, '/cso-surveys') => 'cso-surveys.view',
            str_contains($url, '/housing') => 'housing-units.view',
            str_contains($url, '/building'), str_contains($url, '/engineer'), str_contains($url, '/damageAssessment') => 'damage-assessments.view',
            default => null,
        };
    }

    private static function isCustomVisibleItem(array $item, User $user): bool
    {
        return match ($item['visible_when'] ?? null) {
            'building_deletion_requests' => ! self::isCsoOfficerOnly($user),
            'restricted_lawyer_audit_assignments' => RestrictedLawyerAuditAccess::canViewAssignments($user),
            default => false,
        };
    }

    private static function isCsoOfficerOnly(User $user): bool
    {
        return $user->hasRole('CSO Officer')
            && ! $user->hasAnyRole([
                'Database Officer',
                'Project Officer',
                'undp-Project Manager',
                'Team Leader',
                'Team Leader -INF',
                'Area Manager',
                'Auditing Supervisor',
                'QC/QA Engineer',
                'Inf - QC/QA Engineer',
                'Field Engineer',
                'Gis Officer',
            ]);
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
