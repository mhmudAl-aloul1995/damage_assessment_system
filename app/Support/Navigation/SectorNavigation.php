<?php

namespace App\Support\Navigation;

use App\Models\User;

class SectorNavigation
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(string $sector, User $user): array
    {
        $sectorConfiguration = self::sectors()[$sector] ?? null;

        if ($sectorConfiguration === null) {
            return [];
        }

        return collect($sectorConfiguration['tabs'])
            ->map(fn (array $tab): ?array => self::visibleTab($tab, $user))
            ->filter()
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

        if (in_array($routeName, [
            'audit.auditBuilding',
            'export.data.index',
            'reports.field-engineer.index',
            'reports.daily-achievement',
            'reports.engineer-audit',
        ], true)) {
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
     * @param  array<string, mixed>  $tab
     * @return array<string, mixed>|null
     */
    private static function visibleTab(array $tab, User $user): ?array
    {
        if (isset($tab['items'])) {
            $items = collect($tab['items'])
                ->filter(fn (array $item): bool => self::isVisible($item, $user))
                ->map(fn (array $item): array => self::navigationItem($item))
                ->values();

            if ($items->isEmpty()) {
                return null;
            }

            return [
                'key' => $tab['key'],
                'title' => $tab['title'],
                'icon' => $tab['icon'],
                'url' => $items->first()['url'],
                'is_active' => $items->contains('is_active', true),
                'items' => $items->all(),
            ];
        }

        if (! self::isVisible($tab, $user)) {
            return null;
        }

        return [
            ...self::navigationItem($tab),
            'key' => $tab['key'],
            'title' => $tab['title'],
            'icon' => $tab['icon'],
            'items' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{title: string, url: string, is_active: bool}
     */
    private static function navigationItem(array $item): array
    {
        return [
            'title' => $item['title'],
            'url' => route($item['route'], $item['parameters'] ?? []),
            'is_active' => request()->routeIs(...$item['active_routes']),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function routeSectorPrefixes(): array
    {
        return [
            'building.' => 'buildings',
            'reports.building-productivity.' => 'buildings',
            'reports.productivity' => 'buildings',
            'reports.area-productivity.buildings' => 'buildings',
            'housing.' => 'housing-units',
            'reports.area-productivity.housing-units' => 'housing-units',
            'reports.hlp-audit' => 'housing-units',
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
        $reportRoles = ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager'];
        $exportRoles = ['Database Officer', 'Project Officer', 'undp-Project Manager', 'QC/QA Engineer', 'Team Leader -INF', 'Area Manager'];
        $damageExportRoles = [...$exportRoles, 'Team Leader'];
        $buildingProductivityRoles = ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader -INF', 'Team Leader', 'Area Manager'];
        $engineerProductivityRoles = ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager', 'Team Leader'];
        $fieldReportRoles = ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager', 'Team Leader -INF', 'Team Leader', 'Auditing Supervisor'];
        $auditReportRoles = ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Area Manager', 'Auditing Supervisor'];

        return [
            'buildings' => [
                'title' => 'menu.damage_assessment.buildings',
                'tabs' => [
                    self::tab('records', 'building.index', ['building.*']),
                    self::tab('audit', 'audit.auditBuilding', ['audit.auditBuilding'], $damageAuditRoles, parameters: ['sector' => 'buildings']),
                    self::reportTab([
                        self::report('area_productivity', 'reports.area-productivity.buildings', ['reports.area-productivity.buildings'], $reportRoles),
                        self::report('building_productivity', 'reports.building-productivity.index', ['reports.building-productivity.*'], $buildingProductivityRoles),
                        self::report('engineer_productivity', 'reports.productivity', ['reports.productivity'], $engineerProductivityRoles),
                        self::report('field_engineer', 'reports.field-engineer.index', ['reports.field-engineer.*'], $fieldReportRoles, ['sector' => 'buildings']),
                        self::report('daily_audit', 'reports.daily-achievement', ['reports.daily-achievement', 'reports.auditors-daily', 'reports.lawyers-daily'], $auditReportRoles, ['sector' => 'buildings']),
                        self::report('engineer_audit', 'reports.engineer-audit', ['reports.engineer-audit'], ['Database Officer', 'Project Officer', 'Area Manager', 'Auditing Supervisor'], ['sector' => 'buildings']),
                    ]),
                    self::tab('export', 'export.data.index', ['export.data.index'], $damageExportRoles, parameters: ['sector' => 'buildings']),
                ],
            ],
            'housing-units' => [
                'title' => 'menu.damage_assessment.housing_units',
                'tabs' => [
                    self::tab('records', 'housing.index', ['housing.*']),
                    self::tab('audit', 'audit.auditBuilding', ['audit.auditBuilding'], $damageAuditRoles, parameters: ['sector' => 'housing-units']),
                    self::reportTab([
                        self::report('area_productivity', 'reports.area-productivity.housing-units', ['reports.area-productivity.housing-units'], $reportRoles),
                        self::report('field_engineer', 'reports.field-engineer.index', ['reports.field-engineer.*'], $fieldReportRoles, ['sector' => 'housing-units']),
                        self::report('daily_audit', 'reports.daily-achievement', ['reports.daily-achievement', 'reports.auditors-daily', 'reports.lawyers-daily'], $auditReportRoles, ['sector' => 'housing-units']),
                        self::report('hlp', 'reports.hlp-audit', ['reports.hlp-audit'], $auditReportRoles),
                        self::report('engineer_audit', 'reports.engineer-audit', ['reports.engineer-audit'], ['Database Officer', 'Project Officer', 'Area Manager', 'Auditing Supervisor'], ['sector' => 'housing-units', 'report_type' => 'housing_units']),
                    ]),
                    self::tab('export', 'export.data.index', ['export.data.index'], $damageExportRoles, parameters: ['sector' => 'housing-units']),
                ],
            ],
            'public-buildings' => [
                'title' => 'menu.damage_assessment.public_buildings',
                'tabs' => [
                    self::tab('records', 'public-buildings.index', ['public-buildings.index', 'public-buildings.show']),
                    self::tab('audit', 'inf-audit.public-buildings.index', ['inf-audit.public-buildings.*'], $infrastructureAuditRoles),
                    self::reportTab([
                        self::report('sector_report', 'reports.public-buildings', ['reports.public-buildings'], $reportRoles),
                        self::report('area_productivity', 'reports.area-productivity.public-buildings', ['reports.area-productivity.public-buildings'], [...$reportRoles, 'Team Leader -INF']),
                    ]),
                    self::tab('export', 'public-buildings.export-data', ['public-buildings.export-data'], $exportRoles),
                ],
            ],
            'road-facilities' => [
                'title' => 'menu.damage_assessment.road_facilities',
                'tabs' => [
                    self::tab('records', 'road-facilities.index', ['road-facilities.index', 'road-facilities.show']),
                    self::tab('audit', 'inf-audit.roads.index', ['inf-audit.roads.*'], $infrastructureAuditRoles),
                    self::reportTab([
                        self::report('sector_report', 'reports.road-facilities', ['reports.road-facilities'], $reportRoles),
                        self::report('area_productivity', 'reports.area-productivity.road-facilities', ['reports.area-productivity.road-facilities'], [...$reportRoles, 'Team Leader -INF']),
                    ]),
                    self::tab('export', 'road-facilities.export-data', ['road-facilities.export-data'], $exportRoles),
                ],
            ],
            'cso-surveys' => [
                'title' => 'menu.damage_assessment.cso_surveys',
                'tabs' => [
                    self::tab('records', 'cso-surveys.index', ['cso-surveys.index', 'cso-surveys.show']),
                    self::tab('audit', 'inf-audit.cso.index', ['inf-audit.cso.*'], [...$infrastructureAuditRoles, 'CSO Officer']),
                    self::reportTab([
                        self::report('area_productivity', 'reports.area-productivity.cso-surveys', ['reports.area-productivity.cso-surveys'], [...$reportRoles, 'Team Leader -INF', 'CSO Officer'], permissions: ['reports.area-productivity.cso-surveys.view']),
                    ]),
                    self::tab('export', 'cso-surveys.export-data', ['cso-surveys.export-data'], [...$exportRoles, 'CSO Officer'], ['cso-surveys.export']),
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private static function reportTab(array $items): array
    {
        return [
            'key' => 'reports',
            'title' => 'menu.sector_navigation.reports',
            'icon' => 'ki-chart-simple',
            'items' => $items,
        ];
    }

    /**
     * @param  array<int, string>  $activeRoutes
     * @param  array<int, string>  $roles
     * @param  array<string, string>  $parameters
     * @param  array<int, string>  $permissions
     * @return array<string, mixed>
     */
    private static function report(
        string $key,
        string $route,
        array $activeRoutes,
        array $roles,
        array $parameters = [],
        array $permissions = [],
    ): array {
        return [
            'title' => "menu.sector_navigation.report_items.{$key}",
            'route' => $route,
            'active_routes' => $activeRoutes,
            'roles' => $roles,
            'permissions' => $permissions,
            'parameters' => $parameters,
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
