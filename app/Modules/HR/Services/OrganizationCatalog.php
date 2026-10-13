<?php

namespace App\Modules\HR\Services;

use App\Models\User;
use App\Modules\HR\Models\AuditLog;
use App\Modules\HR\Models\CatalogModel;
use App\Modules\HR\Models\CostCenter;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\DepartmentScope;
use App\Modules\HR\Models\DepartmentScopeEnding;
use App\Modules\HR\Models\JobTitle;
use App\Modules\HR\Models\OfficeLocation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OrganizationCatalog
{
    public const MODELS = ['departments' => Department::class, 'job-titles' => JobTitle::class, 'offices' => OfficeLocation::class, 'cost-centers' => CostCenter::class];

    public function model(string $kind): string
    {
        abort_unless(isset(self::MODELS[$kind]), 404);

        return self::MODELS[$kind];
    }

    public function save(User $actor, string $kind, array $data, ?int $id = null): CatalogModel
    {
        $this->authorize($actor);
        $class = $this->model($kind);

        return Department::query()->getConnection()->transaction(function () use ($actor, $kind, $data, $id, $class): CatalogModel {
            Department::query()->orderBy('id')->lockForUpdate()->first();
            $record = $id ? $class::query()->lockForUpdate()->findOrFail($id) : new $class;
            if ($kind === 'departments') {
                $parentId = $data['parent_id'] ?? null;
                $visited = [];
                while ($parentId !== null) {
                    if ((int) $parentId === $record->id || isset($visited[$parentId])) {
                        throw ValidationException::withMessages(['parent_id' => 'لا يمكن إنشاء دورة في الهيكل التنظيمي.']);
                    }
                    $visited[$parentId] = true;
                    $parentId = Department::query()->findOrFail($parentId)->parent_id;
                }
            }
            $before = $record->getAttributes();
            $record->fill($data)->save();
            AuditLog::query()->create([
                'actor_id' => $actor->id, 'action' => $id ? 'organization.catalog_updated' : 'organization.catalog_created',
                'subject_type' => $record->getTable(), 'subject_id' => $record->id,
                'metadata' => ['changed_fields' => array_keys(array_diff_assoc($record->getAttributes(), $before))],
                'created_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
            app(EmployeeOrganizationScope::class)->forget();

            return $record;
        });
    }

    public function scope(User $actor, array $data): DepartmentScope
    {
        $this->authorize($actor);

        return Department::query()->getConnection()->transaction(function () use ($actor, $data): DepartmentScope {
            User::query()->lockForUpdate()->findOrFail($data['user_id']);
            Department::query()->findOrFail($data['department_id']);
            $scope = DepartmentScope::query()->create([...$data, 'created_by' => $actor->id]);
            $this->audit($actor, 'organization.scope_granted', $scope->getTable(), $scope->id);
            app(EmployeeOrganizationScope::class)->forget();

            return $scope;
        });
    }

    public function endScope(User $actor, DepartmentScope $scope, array $data): DepartmentScopeEnding
    {
        $this->authorize($actor);

        return Department::query()->getConnection()->transaction(function () use ($actor, $scope, $data): DepartmentScopeEnding {
            User::query()->lockForUpdate()->findOrFail($scope->user_id);
            $scope = DepartmentScope::query()->with('latestEnding')->findOrFail($scope->id);
            $end = $data['ends_on'];
            $currentEnd = $scope->latestEnding?->ends_on->toDateString() ?? $scope->ends_on?->toDateString();
            if ($end < now(config('hr.timezone'))->toDateString() || $end < $scope->starts_on->toDateString() || ($currentEnd !== null && $end >= $currentEnd)) {
                throw ValidationException::withMessages(['ends_on' => 'اختر نهاية اليوم أو نهاية مستقبلية أقصر من النطاق الحالي.']);
            }
            $ending = $scope->endings()->create([...$data, 'created_by' => $actor->id]);
            $this->audit($actor, 'organization.scope_ended', $ending->getTable(), $ending->id);
            app(EmployeeOrganizationScope::class)->forget();

            return $ending;
        });
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->is_active, 403);
        Gate::forUser($actor)->authorize('hr.organization.manage');
    }

    private function audit(User $actor, string $action, string $table, int $id): void
    {
        AuditLog::query()->create(['actor_id' => $actor->id, 'action' => $action, 'subject_type' => $table, 'subject_id' => $id, 'created_at' => now('UTC')->format('Y-m-d H:i:s')]);
    }
}
