<?php

namespace App\Support\Navigation;

use App\Models\User;

class SectorNavigation
{
    /**
     * @return array<int, array{key: string, title: string, icon: string, url: string, is_active: bool}>
     */
    public static function forUser(string $sector, User $user): array
    {
        $sectorConfiguration = self::sectors()[$sector] ?? null;

        if ($sectorConfiguration === null) {
            return [];
        }

        return collect($sectorConfiguration['tabs'])
            ->filter(fn (array $tab): bool => self::isVisible($tab, $user))
            ->map(fn (array $tab): array => [
                'key' => $tab['key'],
                'title' => $tab['title'],
                'icon' => $tab['icon'],
                'url' => route($tab['route'], $tab['parameters'] ?? []),
                'is_active' => request()->routeIs(...$tab['active_routes']),
            ])
            ->values()
            ->all();
    }

    public static function title(string $sector): ?string
    {
        return self::sectors()[$sector]['title'] ?? null;
    }

    public static function sectorForCurrentRoute(): ?string
    {
        $routeName = request()->route()?->getName();

        if ($routeName === null) {
            return null;
        }

        if (in_array($routeName, ['audit.auditBuilding', 'export.data.index'], true)) {
            $requestedSector = request()->string('sector')->toString();

            return in_array($requestedSector, ['buildings', 'housing-units'], true)
                ? $requestedSector
                : 'buildings';
        }

        foreach (self::routeSectorPrefixes() as $routePrefix => $sector) {
            if (str_starts_with($routeName, $routePrefix)) {
                return $sector;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $tab
     */
    private static function isVisible(array $tab, User $user): bool
    {
        if (($tab['roles'] ?? []) === [] && ($tab['permissions'] ?? []) === []) {
            return true;
        }

        if ($user->hasAnyRole($tab['roles'] ?? [])) {
            return true;
        }

        return collect($tab['permissions'] ?? [])
            ->contains(fn (string $permission): bool => $user->can($permission));
    }

    /**
     * @return array<string, string>
     */
    private static function routeSectorPrefixes(): array
    {
        return [
            'building.' => 'buildings',
            'reports.area-productivity.buildings' => 'buildings',
            'housing.' => 'housing-units',
            'reports.area-productivity.housing-units' => 'housing-units',
            'public-buildings.' => 'public-buildings',
            'inf-audit.public-buildings.' => 'public-buildings',
            'reports.public-buildings' => 'public-buildings',
            'reports.area-productivity.public-buildings' => 'public-buildings',
            'road-facilities.' => 'road-facilities',
            'inf-audit.roads.' => 'road-facilities',
            'reports.road-facilities' => 'road-facilities',
            'reports.area-productivity.road-facilities' => 'road-facilities',
            'cso-surveys.' => 'cso-surveys',
            'inf-audit.cso.' => 'cso-surveys',
            'reports.area-productivity.cso-surveys' => 'cso-surveys',
        ];
    }

    /**
     * @return array<string, array{title: string, tabs: array<int, array<string, mixed>>}>
     */
    private static function sectors(): array
    {
        $damageAuditRoles = ['Database Officer', 'Legal Auditor', 'QC/QA Engineer', 'Auditing Supervisor', 'Project Officer', 'undp-Project Manager'];
        $infrastructureAuditRoles = ['Database Officer', 'Project Officer', 'Team Leader -INF', 'Inf - QC/QA Engineer'];
        $reportRoles = ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Auditing Supervisor', 'Area Manager'];
        $exportRoles = ['Database Officer', 'Project Officer', 'undp-Project Manager', 'QC/QA Engineer', 'Team Leader -INF', 'Area Manager'];

        return [
            'buildings' => [
                'title' => 'menu.damage_assessment.buildings',
                'tabs' => [
                    self::tab('records', 'building.index', ['building.*']),
                    self::tab('audit', 'audit.auditBuilding', ['audit.auditBuilding'], $damageAuditRoles, parameters: ['sector' => 'buildings']),
                    self::tab('reports', 'reports.area-productivity.buildings', ['reports.area-productivity.buildings'], $reportRoles),
                    self::tab('export', 'export.data.index', ['export.data.index'], $exportRoles, parameters: ['sector' => 'buildings']),
                ],
            ],
            'housing-units' => [
                'title' => 'menu.damage_assessment.housing_units',
                'tabs' => [
                    self::tab('records', 'housing.index', ['housing.*']),
                    self::tab('audit', 'audit.auditBuilding', ['audit.auditBuilding'], $damageAuditRoles, parameters: ['sector' => 'housing-units']),
                    self::tab('reports', 'reports.area-productivity.housing-units', ['reports.area-productivity.housing-units'], $reportRoles),
                    self::tab('export', 'export.data.index', ['export.data.index'], $exportRoles, parameters: ['sector' => 'housing-units']),
                ],
            ],
            'public-buildings' => [
                'title' => 'menu.damage_assessment.public_buildings',
                'tabs' => [
                    self::tab('records', 'public-buildings.index', ['public-buildings.index', 'public-buildings.show']),
                    self::tab('audit', 'inf-audit.public-buildings.index', ['inf-audit.public-buildings.*'], $infrastructureAuditRoles),
                    self::tab('reports', 'reports.public-buildings', ['reports.public-buildings'], $reportRoles),
                    self::tab('productivity', 'reports.area-productivity.public-buildings', ['reports.area-productivity.public-buildings'], $reportRoles),
                    self::tab('export', 'public-buildings.export-data', ['public-buildings.export-data'], $exportRoles),
                ],
            ],
            'road-facilities' => [
                'title' => 'menu.damage_assessment.road_facilities',
                'tabs' => [
                    self::tab('records', 'road-facilities.index', ['road-facilities.index', 'road-facilities.show']),
                    self::tab('audit', 'inf-audit.roads.index', ['inf-audit.roads.*'], $infrastructureAuditRoles),
                    self::tab('reports', 'reports.road-facilities', ['reports.road-facilities'], $reportRoles),
                    self::tab('productivity', 'reports.area-productivity.road-facilities', ['reports.area-productivity.road-facilities'], $reportRoles),
                    self::tab('export', 'road-facilities.export-data', ['road-facilities.export-data'], $exportRoles),
                ],
            ],
            'cso-surveys' => [
                'title' => 'menu.damage_assessment.cso_surveys',
                'tabs' => [
                    self::tab('records', 'cso-surveys.index', ['cso-surveys.index', 'cso-surveys.show']),
                    self::tab('audit', 'inf-audit.cso.index', ['inf-audit.cso.*'], [...$infrastructureAuditRoles, 'CSO Officer']),
                    self::tab('reports', 'reports.area-productivity.cso-surveys', ['reports.area-productivity.cso-surveys'], [...$reportRoles, 'CSO Officer'], ['reports.area-productivity.cso-surveys.view']),
                    self::tab('export', 'cso-surveys.export-data', ['cso-surveys.export-data'], [...$exportRoles, 'CSO Officer'], ['cso-surveys.export']),
                ],
            ],
        ];
    }

    /**
     * @param  array<int, string>  $activeRoutes
     * @param  array<int, string>  $roles
     * @param  array<int, string>  $permissions
     * @param  array<string, string>  $parameters
     * @return array<string, mixed>
     */
    private static function tab(
        string $key,
        string $route,
        array $activeRoutes,
        array $roles = [],
        array $permissions = [],
        array $parameters = [],
    ): array {
        return [
            'key' => $key,
            'title' => "menu.sector_navigation.{$key}",
            'icon' => match ($key) {
                'records' => 'ki-document',
                'audit' => 'ki-shield-tick',
                'reports' => 'ki-chart-simple',
                'productivity' => 'ki-graph-up',
                'export' => 'ki-exit-up',
            },
            'route' => $route,
            'active_routes' => $activeRoutes,
            'roles' => $roles,
            'permissions' => $permissions,
            'parameters' => $parameters,
        ];
    }
}
