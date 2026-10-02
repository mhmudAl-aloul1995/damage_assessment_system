<?php

declare(strict_types=1);

namespace App\services;

use App\Models\AuditedBuilding;
use App\Models\AuditedHousingUnit;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\CsoSurvey;
use App\Models\HousingStatus;
use App\Models\HousingUnit;
use App\Models\PublicBuildingSurvey;
use App\Models\RoadFacilitySurvey;
use App\Support\CsoDamageStatusMapper;
use App\Support\Phase\PhaseContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SectorOverviewService
{
    /** @return array{model: class-string<Model>, damage: string, municipality: string, neighborhood: string} */
    private function configuration(string $sector): array
    {
        return match ($sector) {
            'buildings' => ['model' => AuditedBuilding::class, 'damage' => 'building_damage_status', 'municipality' => 'municipalitie', 'neighborhood' => 'neighborhood'],
            'housing-units' => ['model' => $this->housingUnitModelClass(), 'damage' => 'unit_damage_status', 'municipality' => 'municipalitie', 'neighborhood' => 'neighborhood'],
            'public-buildings' => ['model' => PublicBuildingSurvey::class, 'damage' => 'building_damage_status', 'municipality' => 'municipalitie', 'neighborhood' => 'neighborhood'],
            'road-facilities' => ['model' => RoadFacilitySurvey::class, 'damage' => 'road_damage_level', 'municipality' => 'municipalitie', 'neighborhood' => 'neighborhood'],
            'cso-surveys' => ['model' => CsoSurvey::class, 'damage' => 'building_damage_status', 'municipality' => 'municipalitie', 'neighborhood' => 'neighborhood'],
        };
    }

    /** @return list<string> */
    public function damageBuckets(string $sector): array
    {
        return $sector === 'road-facilities'
            ? ['destroyed', 'severe', 'moderate', 'minor', 'no_damage', 'unclassified']
            : ['fully_damaged', 'partially_damaged', 'committee_review', 'no_damage', 'unclassified'];
    }

    /** @param array<string, mixed> $filters */
    private function query(string $sector, array $filters = []): Builder
    {
        $configuration = $this->configuration($sector);
        $query = $configuration['model']::query();

        if ($sector === 'buildings') {
            app(PhaseContext::class)->applyToEloquent($query);
        }

        if ($sector === 'housing-units') {
            $buildingTable = $query->getModel() instanceof AuditedHousingUnit ? 'audited_buildings' : 'buildings';
            app(PhaseContext::class)->applyToParentBuildingPhase($query, $query->getModel()->qualifyColumn('parentglobalid'), $buildingTable);
        }

        foreach (['municipality', 'neighborhood'] as $filter) {
            if (filled($filters[$filter] ?? null)) {
                if ($sector === 'housing-units') {
                    $query->whereHas('building', fn (Builder $building): Builder => $building->where($configuration[$filter], $filters[$filter]));
                } else {
                    $query->where($configuration[$filter], $filters[$filter]);
                }
            }
        }

        if (filled($filters['damage_status'] ?? null)) {
            $column = $configuration['damage'];
            $bucket = $filters['damage_status'];
            $expression = $query->getQuery()->raw("LOWER(TRIM(COALESCE({$column}, '')))");

            if ($bucket === 'unclassified') {
                $knownValues = collect($this->damageBuckets($sector))
                    ->flatMap(fn (string $key): array => $this->damageValues($sector, $key))->all();
                $query->whereNotIn($expression, $knownValues);
            } else {
                $query->whereIn($expression, $this->damageValues($sector, $bucket));
            }
        }

        return $query;
    }

    /** @return list<string> */
    private function damageValues(string $sector, string $bucket): array
    {
        if ($sector === 'road-facilities') {
            return match ($bucket) {
                'no_damage' => ['no_damage', 'no_damaged'],
                'unclassified' => [],
                default => [$bucket],
            };
        }

        if ($sector === 'housing-units') {
            return match ($bucket) {
                CsoDamageStatusMapper::FULLY_DAMAGED => ['fully_damaged2'],
                CsoDamageStatusMapper::PARTIALLY_DAMAGED => ['partially_damaged2'],
                CsoDamageStatusMapper::COMMITTEE_REVIEW => CsoDamageStatusMapper::valuesFor(CsoDamageStatusMapper::COMMITTEE_REVIEW),
                CsoDamageStatusMapper::NO_DAMAGE => ['no_damaged'],
                'unclassified' => [],
                default => CsoDamageStatusMapper::valuesFor($bucket),
            };
        }

        return CsoDamageStatusMapper::valuesFor($bucket);
    }

    private function damageBucket(string $sector, mixed $value): string
    {
        $normalizedValue = strtolower(trim((string) $value));

        foreach ($this->damageBuckets($sector) as $bucket) {
            if (in_array($normalizedValue, $this->damageValues($sector, $bucket), true)) {
                return $bucket;
            }
        }

        return 'unclassified';
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function statistics(string $sector, array $filters): array
    {
        $query = $this->query($sector, $filters);
        $total = (clone $query)->count();
        $completedQuery = $this->completedQuery(clone $query, $sector);
        $completed = (clone $completedQuery)->count();
        $reviewed = $this->reviewedQuery(clone $completedQuery, $sector)->count();
        $approved = $this->reviewedQuery(clone $completedQuery, $sector, true)->count();
        $pending = $completed - $reviewed;
        $damageColumn = $this->configuration($sector)['damage'];
        $damageCounts = array_fill_keys($this->damageBuckets($sector), 0);
        $summary = compact('total', 'completed', 'pending', 'approved');

        foreach ((clone $query)->select($damageColumn)->selectRaw('COUNT(*) as aggregate')->groupBy($damageColumn)->get() as $row) {
            $damageCounts[$this->damageBucket($sector, $row->{$damageColumn})] += (int) $row->aggregate;
        }

        if (in_array($sector, ['buildings', 'housing-units'], true)) {
            $damageSummaryQuery = $sector === 'housing-units' ? clone $query : clone $completedQuery;
            $summary = [...$summary, ...$this->damageSummary($damageSummaryQuery, $damageColumn, $sector)];
        }

        return [
            'summary' => $summary,
            'damage' => $damageCounts,
            'progress' => ['not_completed' => $total - $completed, 'pending' => $pending, 'in_review' => $reviewed - $approved, 'approved' => $approved],
            'neighborhoods' => $this->options($sector, 'neighborhood', array_intersect_key($filters, ['municipality' => true])),
        ];
    }

    /** @return array{fully_damaged: int, partially_damaged: int, committee_review: int, no_damage: int, assessment_blocked: int} */
    private function damageSummary(Builder $query, string $damageColumn, string $sector): array
    {
        $normalizedDamage = $query->getQuery()->raw("LOWER(TRIM(COALESCE({$damageColumn}, '')))");

        if ($sector === 'housing-units') {
            return [
                'fully_damaged' => (clone $query)->where($damageColumn, 'fully_damaged2')->count(),
                'partially_damaged' => (clone $query)->where($damageColumn, 'partially_damaged2')->count(),
                'committee_review' => (clone $query)->whereIn($normalizedDamage, CsoDamageStatusMapper::valuesFor(CsoDamageStatusMapper::COMMITTEE_REVIEW))->count(),
                'no_damage' => (clone $query)->where($damageColumn, 'no_damaged')->count(),
                'assessment_blocked' => $this->housingUnitAssessmentBlockedCount(clone $query),
            ];
        }

        return [
            'fully_damaged' => (clone $query)->whereIn($normalizedDamage, CsoDamageStatusMapper::valuesFor(CsoDamageStatusMapper::FULLY_DAMAGED))->count(),
            'partially_damaged' => (clone $query)->whereIn($normalizedDamage, CsoDamageStatusMapper::valuesFor(CsoDamageStatusMapper::PARTIALLY_DAMAGED))->count(),
            'committee_review' => (clone $query)->whereIn($normalizedDamage, CsoDamageStatusMapper::valuesFor(CsoDamageStatusMapper::COMMITTEE_REVIEW))->count(),
            'no_damage' => (clone $query)->whereIn($normalizedDamage, CsoDamageStatusMapper::valuesFor(CsoDamageStatusMapper::NO_DAMAGE))->count(),
            'assessment_blocked' => (clone $query)
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull($damageColumn)
                    ->orWhereRaw("TRIM(COALESCE({$damageColumn}, '')) = ''"))
                ->count(),
        ];
    }

    private function completedQuery(Builder $query, string $sector): Builder
    {
        if ($sector === 'housing-units') {
            return $query->whereHas('building', fn (Builder $building): Builder => $building->whereRaw("LOWER(TRIM(field_status)) = 'completed'"));
        }

        return $query->whereRaw("LOWER(TRIM(field_status)) = 'completed'");
    }

    private function reviewedQuery(Builder $query, string $sector, bool $approvedOnly = false): Builder
    {
        if (in_array($sector, ['buildings', 'housing-units'], true)) {
            $isBuilding = $sector === 'buildings';
            $statusModel = $isBuilding ? BuildingStatus::class : HousingStatus::class;
            $foreignKey = $isBuilding ? 'building_id' : 'housing_id';
            $relation = $isBuilding ? 'status' : 'assessment_status';
            $statuses = $statusModel::query()->select($foreignKey)
                ->whereIn('id', $statusModel::query()->selectRaw('MAX(id)')->groupBy($foreignKey));

            if ($approvedOnly) {
                $statuses->whereHas($relation, fn (Builder $status): Builder => $status->whereIn('name', ['final_approval', 'undp_final_approve']));
            } else {
                $statuses->whereHas($relation, fn (Builder $status): Builder => $status->whereNotIn('name', ['pending', 'assigned', 'assigned_to_engineer', 'assigned_to_lawyer']));
            }

            return $query->whereIn('objectid', $statuses);
        }

        return $query->whereHas('infAuditStatus.status', function (Builder $status) use ($approvedOnly): void {
            if ($approvedOnly) {
                $status->where('name', 'final_approval');
            } else {
                $status->where('name', '!=', 'assigned');
            }
        });
    }

    /** @param array<string, mixed> $filters
     * @return list<string>
     */
    public function options(string $sector, string $filter, array $filters = []): array
    {
        $column = $this->configuration($sector)[$filter];
        $query = $this->query($sector, $filters);

        if ($sector === 'housing-units') {
            $buildingModelClass = $query->getModel() instanceof AuditedHousingUnit ? AuditedBuilding::class : Building::class;
            $query = $buildingModelClass::query()->whereIn('globalid', $query->select('parentglobalid'));
        }

        return $query->whereNotNull($column)->where($column, '!=', '')
            ->distinct()->orderBy($column)->pluck($column)->all();
    }

    /** @param array<string, mixed> $filters
     * @return array{features: list<array<string, mixed>>, next_cursor: ?int, scanned: int}
     */
    public function mapFeatures(string $sector, array $filters): array
    {
        $configuration = $this->configuration($sector);
        $query = $this->query($sector, $filters)->where('id', '>', $filters['after_id'] ?? 0)->orderBy('id');
        $columns = ['id', 'objectid', $configuration['damage']];

        if ($sector === 'housing-units') {
            $columns[] = 'parentglobalid';
            $relatedBuilding = $query->getModel()->building()->getRelated();
            $buildingColumns = $this->existingColumns($relatedBuilding, ['id', 'globalid', 'municipalitie', 'neighborhood', ...$this->geometryColumns($relatedBuilding)]);
            $query->with('building:'.implode(',', $buildingColumns));
        } else {
            $columns = [...$columns, $configuration['municipality'], $configuration['neighborhood']];
            $columns = [...$columns, ...$this->geometryColumns($query->getModel())];
        }

        $rows = $query->limit(501)->get($this->existingColumns($query->getModel(), $columns));
        $hasMore = $rows->count() > 500;
        $rows = $rows->take(500);
        $features = [];

        foreach ($rows as $row) {
            $source = $sector === 'housing-units' ? $row->building : $row;
            $geometry = $source ? $this->geometry($source) : null;

            if ($geometry === null) {
                continue;
            }

            $features[] = [
                'geometry' => $geometry,
                'attributes' => [
                    'record_id' => (int) $row->id,
                    'objectid' => $row->objectid,
                    'municipality' => $source->{$configuration['municipality']},
                    'neighborhood' => $source->{$configuration['neighborhood']},
                    'damage_status' => $this->damageBucket($sector, $row->{$configuration['damage']}),
                ],
            ];
        }

        return ['features' => $features, 'next_cursor' => $hasMore ? (int) $rows->last()->id : null, 'scanned' => $rows->count()];
    }

    /** @return class-string<Model> */
    private function housingUnitModelClass(): string
    {
        if (Schema::hasTable('audited_housing_units') && AuditedHousingUnit::query()->exists()) {
            return AuditedHousingUnit::class;
        }

        return HousingUnit::class;
    }

    private function housingUnitAssessmentBlockedCount(Builder $query): int
    {
        if (! Schema::hasColumn($query->getModel()->getTable(), 'security_situation_unit')) {
            return 0;
        }

        return $query->whereRaw("LOWER(TRIM(COALESCE(security_situation_unit, ''))) = ?", ['yes'])->count();
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function existingColumns(Model $model, array $columns): array
    {
        $availableColumns = $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());

        return array_values(array_intersect($columns, $availableColumns));
    }

    /** @return list<string> */
    private function geometryColumns(Model $model): array
    {
        $availableColumns = $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());

        return array_values(array_intersect(['location', 'latitude', 'longitude'], $availableColumns));
    }

    /** @return array<string, mixed>|null */
    private function geometry(Model $record): ?array
    {
        $attributes = $record->getAttributes();
        $location = $attributes['location'] ?? null;
        $geometry = is_array($location) ? $location : json_decode((string) $location, true);

        if (is_array($geometry)) {
            $geometry = $geometry['geometry'] ?? $geometry;
            $coordinates = isset($geometry['x'], $geometry['y'])
                ? [$geometry['x'], $geometry['y']]
                : ($geometry['rings'][0][0] ?? $geometry['paths'][0][0] ?? null);
            if (is_array($coordinates) && isset($coordinates[0], $coordinates[1]) && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) {
                $geometry['spatialReference'] ??= ['wkid' => abs((float) $coordinates[0]) > 180 || abs((float) $coordinates[1]) > 90 ? 3857 : 4326];

                return $geometry;
            }
        }

        $latitude = $attributes['latitude'] ?? null;
        $longitude = $attributes['longitude'] ?? null;

        if (is_numeric($latitude) && is_numeric($longitude) && abs((float) $latitude) <= 90 && abs((float) $longitude) <= 180) {
            return ['x' => (float) $longitude, 'y' => (float) $latitude, 'spatialReference' => ['wkid' => 4326]];
        }

        return null;
    }
}
