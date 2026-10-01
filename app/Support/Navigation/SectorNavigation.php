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
            'audit.index',
            'audit.dashboard',
            'audit.fieldEngineer',
            'audit.auditBuilding',
            'export.data.index',
            'reports.field-engineer.index',
            'reports.daily-achievement',
            'reports.engineer-audit',
            'committee-decisions.index',
            'committee-decisions.higher-committee-reassessments.index',
            'committee-members.index',
            'committee-archive.index',
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
        if (($tab['roles'] ?? []) === [] && ($tab['permissions'] ?? []) === [] && ! isset($tab['visible_when'])) {
            return true;
        }

        if (Sidebar::isItemVisibleToUser($tab, $user)) {
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
            'audit.lawyer-assignments' => 'housing-units',
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
            'area-manager-review.' => 'buildings',
            'committee-decisions.buildings.' => 'buildings',
            'committee-decisions.housing-units.' => 'housing-units',
        ];
    }

    /**
     * @return array<string, array{title: string, tabs: array<int, array<string, mixed>>}>
     */
    private static function sectors(): array
    {
        $damageRecordRoles = ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'];
        $infrastructureRecordRoles = ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader -INF', 'Area Manager', 'Auditing Supervisor', 'QC/QA Engineer'];
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
                    self::tab('records', 'building.index', ['building.*'], $damageRecordRoles),
                    self::damageAuditTab('buildings'),
                    self::decisionsTab('buildings'),
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
                    self::tab('records', 'housing.index', ['housing.*'], $damageRecordRoles),
                    self::damageAuditTab('housing-units'),
                    self::decisionsTab('housing-units'),
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
                    self::tab('records', 'public-buildings.index', ['public-buildings.index', 'public-buildings.show'], $infrastructureRecordRoles),
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
                    self::tab('records', 'road-facilities.index', ['road-facilities.index', 'road-facilities.show'], $infrastructureRecordRoles),
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
                    self::tab('records', 'cso-surveys.index', ['cso-surveys.index', 'cso-surveys.show'], [...$infrastructureRecordRoles, 'CSO Officer']),
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
        return self::dropdownTab('reports', 'ki-chart-simple', $items);
    }

    /**
     * @return array<string, mixed>
     */
    private static function damageAuditTab(string $sector): array
    {
        $auditHomeRoles = ['Database Officer', 'Auditing Supervisor', 'Audit Reviewer', 'Project Officer', 'undp-Project Manager', 'Area Manager', 'Team Leader'];
        $damageAuditRoles = ['Database Officer', 'Legal Auditor', 'QC/QA Engineer', 'Auditing Supervisor', 'Project Officer', 'undp-Project Manager'];
        $auditDashboardRoles = ['Database Officer', 'Auditing Supervisor', 'Project Officer', 'undp-Project Manager'];
        $items = [
            self::item('menu.audit.dashboard', 'audit.dashboard', ['audit.dashboard'], $auditDashboardRoles, ['sector' => $sector]),
            self::item('menu.sector_navigation.audit_items.overview', 'audit.index', ['audit.index'], $auditHomeRoles, ['sector' => $sector], url: 'damage-assessment/audit'),
            self::item('menu.sector_navigation.audit_items.building_audit', 'audit.auditBuilding', ['audit.auditBuilding'], $damageAuditRoles, ['sector' => $sector]),
        ];

        if ($sector === 'buildings') {
            $items[] = self::item('menu.sector_navigation.audit_items.field_engineer_buildings', 'audit.fieldEngineer', ['audit.fieldEngineer'], ['Field Engineer', 'Database Officer'], ['sector' => $sector]);
            $items[] = self::item('menu.sector_navigation.audit_items.area_manager_review', 'area-manager-review.index', ['area-manager-review.*'], ['Database Officer']);
        }

        if ($sector === 'housing-units') {
            $items[] = self::item(
                'menu.sector_navigation.audit_items.legal_units',
                'audit.lawyer-assignments',
                ['audit.lawyer-assignments'],
                ['Database Officer'],
                ['sector' => $sector],
                visibleWhen: 'restricted_lawyer_audit_assignments',
            );
        }

        return self::dropdownTab('audit', 'ki-shield-tick', $items);
    }

    /**
     * @return array<string, mixed>
     */
    private static function decisionsTab(string $sector): array
    {
        $decisionRoles = ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Auditing Supervisor', 'QC/QA Engineer', 'Legal Auditor', 'Area Manager'];
        $committeeRoles = ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Auditing Supervisor', 'QC/QA Engineer', 'Area Manager'];
        $memberRoles = ['Database Officer', 'Project Officer', 'undp-Project Manager', 'Team Leader', 'Team Leader -INF', 'Auditing Supervisor', 'Area Manager'];

        return self::dropdownTab('decisions', 'ki-shield-search', [
            self::item('menu.committee.decisions', 'committee-decisions.index', [
                'committee-decisions.index',
                'committee-decisions.buildings.*',
                'committee-decisions.housing-units.*',
                'committee-decisions.reassessments.*',
            ], $decisionRoles, ['sector' => $sector]),
            self::item('menu.committee.higher_committee_reassessments', 'committee-decisions.higher-committee-reassessments.index', ['committee-decisions.higher-committee-reassessments.*'], $committeeRoles, ['sector' => $sector]),
            self::item('menu.committee.members', 'committee-members.index', ['committee-members.*'], $memberRoles, ['sector' => $sector]),
            self::item('menu.committee.archive', 'committee-archive.index', ['committee-archive.*'], $committeeRoles, ['sector' => $sector]),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private static function dropdownTab(string $key, string $icon, array $items): array
    {
        return [
            'key' => $key,
            'title' => "menu.sector_navigation.{$key}",
            'icon' => $icon,
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
        return self::item(
            "menu.sector_navigation.report_items.{$key}",
            $route,
            $activeRoutes,
            $roles,
            $parameters,
            $permissions,
        );
    }

    /**
     * @param  array<int, string>  $activeRoutes
     * @param  array<int, string>  $roles
     * @param  array<string, string>  $parameters
     * @param  array<int, string>  $permissions
     * @return array<string, mixed>
     */
    private static function item(
        string $title,
        string $route,
        array $activeRoutes,
        array $roles = [],
        array $parameters = [],
        array $permissions = [],
        ?string $visibleWhen = null,
        ?string $url = null,
    ): array {
        return array_filter([
            'title' => $title,
            'route' => $route,
            'active_routes' => $activeRoutes,
            'roles' => $roles,
            'permissions' => $permissions,
            'parameters' => $parameters,
            'visible_when' => $visibleWhen,
            'url' => $url,
        ], fn (mixed $value): bool => $value !== null);
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
