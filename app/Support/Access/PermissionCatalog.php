<?php

namespace App\Support\Access;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionCatalog
{
    private const GROUPS = [
        'user_management' => ['users.', 'roles.', 'permissions.'],
        'cso' => ['cso-surveys.', 'inf-audit.cso.', 'reports.area-productivity.cso-surveys.'],
        'reports' => ['reports.'],
        'audit' => ['audit.'],
        'committee' => ['committee-', 'view committee', 'create committee', 'edit committee', 'sign committee', 'manage committee', 'sync committee'],
        'attendance' => ['attendance.'],
        'exports' => ['exports.'],
        'inf_audit' => ['inf-audit.'],
        'system' => ['system.', 'system-', 'login-logs.', 'user-activity-logs.', 'arcgis.'],
        'team_leader_assignments' => ['team-leader-field-engineers.'],
        'building_survey_return_requests' => ['building-survey-return-requests.'],
        'damage_assessment' => ['damage-assessments.', 'damage-assessment.', 'buildings.', 'housing-units.'],
        'heks' => ['heks.'],
        'borrowers' => ['damage-assessment-borrowers.', 'borrowers.'],
    ];

    public function groups(): Collection
    {
        $permissions = Permission::query()->where('guard_name', 'web')->orderBy('name')->get();

        return collect([...array_keys(self::GROUPS), 'other'])->map(function (string $key) use ($permissions): array {
            $items = $permissions->filter(fn (Permission $permission): bool => $this->groupKey($permission->name) === $key);

            return [
                'key' => $key,
                'label' => in_array($key, ['heks', 'borrowers'], true) ? __('access.'.$key) : __('ui.permission_groups.'.$key),
                'permissions' => $items->values(),
                'rows' => $items->map(fn (Permission $permission): array => $this->describe($permission->name))
                    ->groupBy('resource')->map(fn (Collection $cells, string $resource): array => [
                        'label' => $this->translate('resources', $resource),
                        'cells' => $cells->groupBy('column'),
                    ]),
            ];
        })->filter(fn (array $group): bool => $group['key'] !== 'other' || $group['permissions']->isNotEmpty())->values();
    }

    public function groupKey(string $name): string
    {
        foreach (self::GROUPS as $key => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    return $key;
                }
            }
        }

        return 'other';
    }

    /** @return array{name: string, resource: string, action: string, label: string, column: string, sensitive: bool} */
    public function describe(string $name): array
    {
        $resource = Str::beforeLast($name, '.');
        $action = Str::afterLast($name, '.');

        if (! str_contains($name, '.') && str_contains($name, ' ')) {
            $resource = Str::after($name, ' ');
            $action = Str::before($name, ' ');
        }

        return [
            'name' => $name,
            'resource' => $resource,
            'action' => $action,
            'label' => $name === 'audit.actions' ? __('ui.permissions.audit_actions') : $this->translate('actions', $action),
            'column' => match ($action) {
                'view', 'list' => 'view',
                'create' => 'create',
                'update', 'edit' => 'update',
                'delete' => 'delete',
                'export' => 'export',
                default => 'advanced',
            },
            'sensitive' => preg_match('/^(delete|sign|maintenance|reset|sync|import)|approve/', $action) === 1,
        ];
    }

    public function isSystemRole(string $name): bool
    {
        $names = array_merge(
            ['Database Officer', 'Manager', 'auditing', 'Gis Officer', 'Audit Reviewer'],
            array_keys(config('access.role_profiles', [])),
        );
        $collectRoles = function (array $items) use (&$collectRoles, &$names): void {
            foreach ($items as $item) {
                $names = array_merge($names, $item['roles'] ?? []);
                $collectRoles($item['items'] ?? $item['children'] ?? []);
            }
        };
        $collectRoles(config('sidebar', []));

        return in_array($name, $names, true);
    }

    /** @return array{module: string, read_only: bool, allowed_permissions: array<int, string>}|null */
    public function roleProfile(string $name): ?array
    {
        $profile = config('access.role_profiles.'.$name);

        return is_array($profile) ? $profile : null;
    }

    /** @return array<int, string>|null */
    public function allowedPermissionsForRole(string $name): ?array
    {
        $profile = $this->roleProfile($name);

        return $profile === null ? null : array_values($profile['allowed_permissions'] ?? []);
    }

    /** @param array<int, string> $permissions */
    public function permissionsAreAllowedForRole(string $name, array $permissions): bool
    {
        $allowedPermissions = $this->allowedPermissionsForRole($name);

        return $allowedPermissions === null
            || collect($permissions)->every(fn (string $permission): bool => in_array($permission, $allowedPermissions, true));
    }

    public function revision(Role $role): string
    {
        return hash('sha256', json_encode([$role->name, $role->permissions->pluck('name')->sort()->values()->all()]));
    }

    public function translate(string $section, string $value): string
    {
        $key = 'access.'.$section.'.'.str_replace(['.', '-', ' '], '_', $value);
        $translated = __($key);

        return $translated === $key ? Str::headline($value) : $translated;
    }
}
