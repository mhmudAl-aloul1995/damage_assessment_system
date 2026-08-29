<?php

declare(strict_types=1);

namespace App\Support\Phase;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PhaseContext
{
    public const REQUEST_KEY = 'phase';

    public const SESSION_KEY = 'selected_phase_number';

    public function selected(): ?int
    {
        if (! request()->hasSession()) {
            return null;
        }

        $phase = request()->session()->get(self::SESSION_KEY);

        return is_int($phase) && $phase > 0 ? $phase : null;
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
            'public_building_surveys',
            'road_facility_surveys',
            'cso_surveys',
            'audited_buildings',
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

        return $defaultOptions
            ->merge($existingPhases)
            ->unique()
            ->sort()
            ->values();
    }

    public function label(?int $phase): string
    {
        return $phase === null ? __('ui.phase.all') : __('ui.phase.item', ['number' => $phase]);
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
}
