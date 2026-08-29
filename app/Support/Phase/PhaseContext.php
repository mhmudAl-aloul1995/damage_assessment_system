<?php

declare(strict_types=1);

namespace App\Support\Phase;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PhaseContext
{
    public const REQUEST_KEY = 'phase';

    public const SESSION_KEY = 'selected_phase_number';

    private const ALL_PHASES_SESSION_VALUE = 'all';

    public function selected(): ?int
    {
        if (! request()->hasSession()) {
            return null;
        }

        if (! request()->session()->has(self::SESSION_KEY)) {
            return $this->defaultForUser();
        }

        $phase = request()->session()->get(self::SESSION_KEY);

        if ($phase === self::ALL_PHASES_SESSION_VALUE) {
            return $this->canSelectAll() ? null : $this->defaultForUser();
        }

        if (is_int($phase) && $phase > 0 && $this->isAllowed($phase)) {
            return $phase;
        }

        return $this->defaultForUser();
    }

    public function normalize(mixed $value): ?int
    {
        $normalizedValue = strtolower(trim((string) $value));

        if ($normalizedValue === '' || $normalizedValue === 'all') {
            return null;
        }

        if (str_starts_with($normalizedValue, 'ph')) {
            $normalizedValue = substr($normalizedValue, 2);
        }

        if (! ctype_digit($normalizedValue)) {
            return null;
        }

        $phase = (int) $normalizedValue;

        return $phase > 0 ? $phase : null;
    }

    /**
     * @return Collection<int, int>
     */
    public function options(): Collection
    {
        $defaultOptions = collect([1, 2, 3]);
        $tables = [
            'buildings',
            'housing_units',
            'public_building_surveys',
            'road_facility_surveys',
            'cso_surveys',
            'audited_buildings',
            'audited_housing_units',
        ];

        try {
            $existingPhases = collect($tables)
                ->filter(fn (string $table): bool => Schema::hasTable($table) && Schema::hasColumn($table, 'phase_number'))
                ->flatMap(fn (string $table): Collection => DB::table($table)
                    ->whereNotNull('phase_number')
                    ->distinct()
                    ->pluck('phase_number'))
                ->map(fn (mixed $phase): int => (int) $phase)
                ->filter(fn (int $phase): bool => $phase > 0);
        } catch (Throwable) {
            $existingPhases = collect();
        }

        $options = $defaultOptions
            ->merge($existingPhases)
            ->unique()
            ->sort()
            ->values();

        $allowedPhases = $this->allowedForUser();

        if ($allowedPhases === null) {
            return $options;
        }

        return $options
            ->intersect($allowedPhases)
            ->values();
    }

    public function label(?int $phase): string
    {
        return $phase === null ? __('ui.phase.all') : __('ui.phase.item', ['number' => $phase]);
    }

    public function canSelectAll(): bool
    {
        return $this->allowedForUser() === null;
    }

    public function isAllowed(?int $phase): bool
    {
        if ($phase === null) {
            return $this->canSelectAll();
        }

        $allowedPhases = $this->allowedForUser();

        return $allowedPhases === null || $allowedPhases->contains($phase);
    }

    public function fallbackSelected(): ?int
    {
        return $this->defaultForUser();
    }

    public function allSessionValue(): string
    {
        return self::ALL_PHASES_SESSION_VALUE;
    }

    public function applyToEloquent(EloquentBuilder $query, ?string $qualifiedColumn = null): void
    {
        $phase = $this->selected();

        if ($phase === null) {
            return;
        }

        $model = $query->getModel();

        if ($qualifiedColumn === null && ! Schema::hasColumn($model->getTable(), 'phase_number')) {
            return;
        }

        $query->where($qualifiedColumn ?? $model->qualifyColumn('phase_number'), $phase);
    }

    public function applyToBase(QueryBuilder $query, string $qualifiedColumn): void
    {
        $phase = $this->selected();

        if ($phase !== null) {
            $query->where($qualifiedColumn, $phase);
        }
    }

    public function applyToParentBuildingPhase(
        EloquentBuilder|QueryBuilder $query,
        string $parentGlobalIdColumn,
        string $buildingTable = 'buildings'
    ): void {
        $phase = $this->selected();

        if ($phase === null || ! Schema::hasTable($buildingTable) || ! Schema::hasColumn($buildingTable, 'phase_number')) {
            return;
        }

        $query->whereIn($parentGlobalIdColumn, DB::table($buildingTable)
            ->select('globalid')
            ->where('phase_number', $phase));
    }

    private function defaultForUser(): ?int
    {
        $user = request()->user();
        $allowedPhases = $this->allowedForUser($user);
        $defaultPhase = filter_var($user?->default_phase_number, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
            ],
        ]);

        if ($defaultPhase !== false && ($allowedPhases === null || $allowedPhases->contains($defaultPhase))) {
            return $defaultPhase;
        }

        return $allowedPhases?->first();
    }

    /**
     * @return Collection<int, int>|null
     */
    private function allowedForUser(?Authenticatable $user = null): ?Collection
    {
        $user ??= request()->user();
        $rawPhases = $user?->allowed_phase_numbers;

        if (! is_array($rawPhases) || $rawPhases === []) {
            return null;
        }

        $allowedPhases = collect($rawPhases)
            ->map(fn (mixed $phase): int => (int) $phase)
            ->filter(fn (int $phase): bool => $phase > 0)
            ->unique()
            ->sort()
            ->values();

        return $allowedPhases->isEmpty() ? null : $allowedPhases;
    }
}
