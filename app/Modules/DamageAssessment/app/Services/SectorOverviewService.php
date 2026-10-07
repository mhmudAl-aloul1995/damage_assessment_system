<?php

declare(strict_types=1);

namespace App\services;

use App\Models\AuditedBuilding;
use App\Models\AuditedHousingUnit;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\CsoSurvey;
use App\Models\CsoSurveyAuditStatus;
use App\Models\HousingStatus;
use App\Models\HousingUnit;
use App\Models\PublicBuildingAuditStatus;
use App\Models\PublicBuildingSurvey;
use App\Models\RoadFacilityAuditStatus;
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

        if (filled($filters['field_completion'] ?? null)) {
            $query = $this->fieldQuery($query, $sector, $filters['field_completion'] === 'completed');
        }

        if (filled($filters['audit_status'] ?? null)) {
            $query = $this->auditFilter($query, $sector, $filters['audit_status']);
        }
        if (($filters['metric'] ?? null) === 'completed') {
            $query = $this->completedQuery($query, $sector);
        } elseif (in_array($filters['metric'] ?? null, ['action_required', 'approved'], true)) {
            $query = $this->auditFilter($query, $sector, $filters['metric']);
        }
        if (isset($filters['west'], $filters['south'], $filters['east'], $filters['north'])) {
            $query = $this->withinExtent($query, $sector, $filters);
        }

        return $query;
    }

    private function auditFilter(Builder $query, string $sector, string $bucket): Builder
    {
        $query = $this->completedQuery($query, $sector);
        $table = $query->getModel()->getTable();
        $columns = array_map(fn (string $column): string => $query->getModel()->qualifyColumn($column),
            $query->getModel()->getConnection()->getSchemaBuilder()->getColumnListing($table));
        $query = $query->getModel()->newQueryWithoutScopes()
            ->fromSub($this->withLatestAudit($query->select($columns))->toBase(), $table)->select($table.'.*');
        if ($bucket === 'pending') {
            return $query->where(fn (Builder $query): Builder => $query->whereNull('overview_audit_name')->orWhere('overview_audit_name', 'pending'));
        }
        if ($bucket === 'unclassified') {
            $knownNames = collect($this->auditBuckets($sector))->flatMap(fn (string $key): array => $this->auditNames($sector, $key))->all();

            return $query->whereNotNull('overview_audit_name')->whereNotIn('overview_audit_name', $knownNames);
        }

        return $query->whereIn('overview_audit_name', $this->auditNames($sector, $bucket));
    }

    /** @return list<string> */
    public function auditBuckets(string $sector): array
    {
        return in_array($sector, ['buildings', 'housing-units'], true)
            ? ['pending', 'assigned_engineer', 'accepted_engineer', 'assigned_lawyer', 'accepted_lawyer', 'needs_action', 'rejected', 'team_approved', 'undp_approved', 'unclassified']
            : ['pending', 'assigned', 'accepted', 'needs_action', 'rejected', 'approved', 'unclassified'];
    }

    /** @return list<string> */
    private function auditNames(string $sector, string $bucket): array
    {
        return match ($bucket) {
            'pending' => ['pending'],
            'assigned_engineer' => ['assigned', 'assigned_to_engineer'],
            'assigned_lawyer' => ['assigned_to_lawyer'],
            'accepted_engineer' => ['accepted_by_engineer'],
            'accepted_lawyer' => ['accepted_by_lawyer'],
            'assigned' => ['assigned'], 'accepted' => ['accepted'],
            'needs_action' => ['need_review'],
            'rejected' => ['rejected', 'rejected_by_engineer', 'final_reject'],
            'team_approved' => ['final_approval'], 'undp_approved' => ['undp_final_approve'],
            'approved' => in_array($sector, ['buildings', 'housing-units'], true) ? ['final_approval', 'undp_final_approve'] : ['final_approval'],
            'action_required' => ['need_review', 'rejected', 'rejected_by_engineer', 'final_reject'],
            default => [],
        };
    }

    private function auditBucket(string $sector, ?string $name): string
    {
        if ($name === null || $name === 'pending') {
            return 'pending';
        }

        foreach ($this->auditBuckets($sector) as $bucket) {
            if (in_array($name, $this->auditNames($sector, $bucket), true)) {
                return $bucket;
            }
        }

        return 'unclassified';
    }

    private function withLatestAudit(Builder $query): Builder
    {
        [$statusClass, $foreignKey, $localKey, $lookupTable] = match (true) {
            $query->getModel() instanceof AuditedBuilding => [BuildingStatus::class, 'building_id', 'objectid', 'assessment_statuses'],
            $query->getModel() instanceof HousingUnit, $query->getModel() instanceof AuditedHousingUnit => [HousingStatus::class, 'housing_id', 'objectid', 'assessment_statuses'],
            $query->getModel() instanceof PublicBuildingSurvey => [PublicBuildingAuditStatus::class, 'public_building_survey_id', 'id', 'inf_audit_statuses'],
            $query->getModel() instanceof RoadFacilitySurvey => [RoadFacilityAuditStatus::class, 'globalid', 'globalid', 'inf_audit_statuses'],
            default => [CsoSurveyAuditStatus::class, 'cso_survey_id', 'id', 'inf_audit_statuses'],
        };
        $statusTable = (new $statusClass)->getTable();
        $latest = $statusClass::query()->leftJoin($lookupTable, $lookupTable.'.id', '=', $statusTable.'.status_id')
            ->selectRaw("COALESCE(LOWER(TRIM({$lookupTable}.name)), '__unknown__')")
            ->whereColumn($statusTable.'.'.$foreignKey, $query->getModel()->qualifyColumn($localKey))
            ->orderByDesc($statusTable.'.id')->limit(1);

        if ($query->getQuery()->columns === null) {
            $query->select($query->getModel()->getTable().'.*');
        }

        return $query->addSelect(['overview_audit_name' => $latest]);
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
        $auditQuery = $this->withLatestAudit((clone $completedQuery)->select($query->getModel()->getTable().'.id'));
        $audit = array_fill_keys($this->auditBuckets($sector), 0);
        $auditGroups = $query->getQuery()->newQuery()->fromSub($auditQuery->toBase(), 'overview_audits')
            ->select('overview_audit_name')->selectRaw('COUNT(*) as aggregate')->groupBy('overview_audit_name')->get();
        foreach ($auditGroups as $group) {
            $audit[$this->auditBucket($sector, $group->overview_audit_name)] += (int) $group->aggregate;
        }
        $approved = ($audit['approved'] ?? 0) + ($audit['team_approved'] ?? 0) + ($audit['undp_approved'] ?? 0);
        $pending = $audit['pending'];
        $actionRequired = $audit['needs_action'] + $audit['rejected'];
        $damageColumn = $this->configuration($sector)['damage'];
        $damageCounts = array_fill_keys($this->damageBuckets($sector), 0);
        $summary = [...compact('total', 'completed', 'pending', 'approved'), 'action_required' => $actionRequired];

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
            'fieldwork' => ['completed' => $completed, 'not_completed' => $total - $completed],
            'audit' => $audit,
            'audit_tracks' => $this->auditTracks($completedQuery, $sector),
            'chart' => $this->sectorChart(clone $query, $sector),
            'progress' => ['not_completed' => $total - $completed, 'pending' => $pending, 'in_review' => ($audit['accepted_engineer'] ?? 0) + ($audit['accepted_lawyer'] ?? 0) + ($audit['accepted'] ?? 0), 'approved' => $approved],
            'neighborhoods' => $this->options($sector, 'neighborhood', array_intersect_key($filters, ['municipality' => true])),
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    /** @return array{engineering: array<string, int>, legal: array<string, int>} */
    private function auditTracks(Builder $completedQuery, string $sector): array
    {
        if (! in_array($sector, ['buildings', 'housing-units'], true)) {
            return ['engineering' => [], 'legal' => []];
        }

        return [
            'engineering' => $this->trackStatusCounts($completedQuery, 'QC/QA Engineer', [
                'pending', 'assigned_to_engineer', 'accepted_by_engineer', 'need_review', 'rejected_by_engineer',
            ]),
            'legal' => $this->trackStatusCounts($completedQuery, 'Legal Auditor', [
                'pending', 'assigned_to_lawyer', 'accepted_by_lawyer', 'legal_notes',
            ]),
        ];
    }

    /**
     * @param  list<string>  $statuses
     * @return array<string, int>
     */
    private function trackStatusCounts(Builder $completedQuery, string $type, array $statuses): array
    {
        $model = $completedQuery->getModel();
        $statusClass = $model instanceof AuditedBuilding ? BuildingStatus::class : HousingStatus::class;
        $foreignKey = $model instanceof AuditedBuilding ? 'building_id' : 'housing_id';
        $localKey = 'objectid';
        $statusTable = (new $statusClass)->getTable();
        $latest = $statusClass::query()->leftJoin('assessment_statuses', 'assessment_statuses.id', '=', $statusTable.'.status_id')
            ->selectRaw('LOWER(TRIM(assessment_statuses.name))')
            ->whereColumn($statusTable.'.'.$foreignKey, $model->qualifyColumn($localKey))
            ->where($statusTable.'.type', $type)
            ->orderByDesc($statusTable.'.id')->limit(1);
        $table = $model->getTable();
        $trackQuery = (clone $completedQuery)->select($table.'.id')->addSelect(['overview_track_status' => $latest]);
        $groups = $completedQuery->getQuery()->newQuery()->fromSub($trackQuery->toBase(), 'overview_track_statuses')
            ->select('overview_track_status')->selectRaw('COUNT(*) as aggregate')->groupBy('overview_track_status')->get();
        $counts = array_fill_keys($statuses, 0);

        foreach ($groups as $group) {
            $status = filled($group->overview_track_status) ? (string) $group->overview_track_status : 'pending';
            if (array_key_exists($status, $counts)) {
                $counts[$status] += (int) $group->aggregate;
            }
        }

        return $counts;
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
        return $this->fieldQuery($query, $sector, true);
    }

    private function fieldQuery(Builder $query, string $sector, bool $completed): Builder
    {
        if ($sector === 'housing-units') {
            $condition = fn (Builder $building): Builder => $building->whereRaw("LOWER(TRIM(COALESCE(field_status, ''))) = 'completed'");

            return $completed ? $query->whereHas('building', $condition) : $query->whereDoesntHave('building', $condition);
        }

        return $query->whereRaw("LOWER(TRIM(COALESCE(field_status, ''))) ".($completed ? '=' : '!=')." 'completed'");
    }

    /** @return array{title: string, note: string, rows: list<array<string, mixed>>} */
    private function sectorChart(Builder $query, string $sector): array
    {
        $column = match ($sector) {
            'public-buildings' => 'building_use', 'road-facilities' => 'road_type',
            'cso-surveys' => 'operational_status', 'housing-units' => 'security_situation_unit',
            default => 'neighborhood',
        };
        if (! in_array($column, $this->existingColumns($query->getModel(), [$column]), true)) {
            return ['title' => __('sector-overview.charts.'.$sector), 'note' => __('sector-overview.chart_unavailable'), 'rows' => []];
        }
        $choiceClass = match ($sector) {
            'public-buildings' => \App\Models\PublicBuildingFilter::class,
            'road-facilities' => \App\Models\RoadFacilityFilter::class,
            'cso-surveys' => \App\Models\CsoSurveyFilter::class,
            default => null,
        };
        $choices = $choiceClass ? $choiceClass::query()->where('list_name', $column)->pluck('label', 'name')->all() : [];
        $fallback = match ($sector) {
            'public-buildings' => \App\Support\Forms\PublicBuildingSurveyLayout::choices(),
            'road-facilities' => \App\Support\Forms\RoadFacilitySurveyLayout::choices(),
            'cso-surveys' => \App\Support\Forms\CsoSurveyLayout::choices(),
            default => [],
        };
        $choices = array_replace($fallback[$column] ?? [], $choices);
        $counts = [];
        foreach ((clone $query)->select($column)->selectRaw('COUNT(*) as aggregate')->groupBy($column)->get() as $row) {
            $raw = (string) $row->getRawOriginal($column);
            $values = $sector === 'road-facilities' ? json_decode($raw, true) : null;
            $values = is_array($values) ? array_values(array_unique(array_filter($values, 'is_string'))) : [trim($raw)];
            foreach ($values ?: [''] as $value) {
                $counts[$value] = ($counts[$value] ?? 0) + (int) $row->aggregate;
            }
        }
        arsort($counts);
        $rows = [];
        foreach ($counts as $value => $count) {
            $label = $value === '' ? __('sector-overview.not_recorded') : ($choices[$value] ?? $value);
            if ($sector === 'housing-units' && in_array(strtolower((string) $value), ['yes', 'no'], true)) {
                $label = __('sector-overview.obstacle_'.strtolower((string) $value));
            }
            $rows[] = ['label' => $label, 'count' => $count, 'filter' => $sector === 'buildings' && $value !== '' ? ['neighborhood' => $value] : null];
        }

        return ['title' => __('sector-overview.charts.'.$sector), 'note' => __('sector-overview.chart_notes.'.$sector), 'rows' => $rows];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function records(string $sector, array $filters): array
    {
        $query = $this->geographicQuery($this->query($sector, $filters), $sector);
        $page = $this->withLatestAudit($query)->orderBy('id')->paginate(20, ['*'], 'page', $filters['page'] ?? 1);

        return ['data' => $page->getCollection()->map(fn (Model $row): array => $this->recordAttributes($row, $sector))->all(),
            'total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()];
    }

    private function geographicQuery(Builder $query, string $sector): Builder
    {
        $configuration = $this->configuration($sector);
        $columns = ['id', 'objectid', $configuration['damage'], 'field_status'];
        if ($sector === 'housing-units') {
            $columns[] = 'parentglobalid';
            $building = $query->getModel()->building()->getRelated();
            $buildingColumns = $this->existingColumns($building, ['id', 'globalid', 'field_status', 'municipalitie', 'neighborhood', ...$this->geometryColumns($building)]);
            $query->with('building:'.implode(',', $buildingColumns));
        } else {
            $columns = [...$columns, 'globalid', $configuration['municipality'], $configuration['neighborhood'], ...$this->geometryColumns($query->getModel())];
        }

        return $query->select($this->existingColumns($query->getModel(), $columns));
    }

    /** @return array<string, mixed> */
    private function recordAttributes(Model $row, string $sector): array
    {
        $source = $sector === 'housing-units' ? $row->building : $row;
        $completed = strtolower(trim((string) ($source?->getRawOriginal('field_status') ?? ''))) === 'completed';

        return [
            'record_id' => (int) $row->id, 'objectid' => $row->objectid,
            'parentglobalid' => $row->getRawOriginal('parentglobalid'),
            'municipality' => $source?->getRawOriginal('municipalitie'), 'neighborhood' => $source?->getRawOriginal('neighborhood'),
            'damage_status' => $this->damageBucket($sector, $row->getRawOriginal($this->configuration($sector)['damage'])),
            'field_completed' => $completed,
            'audit_status' => $completed ? $this->auditBucket($sector, $row->getRawOriginal('overview_audit_name')) : 'not_completed',
        ];
    }

    /** @param array<string, mixed> $bounds */
    private function withinExtent(Builder $query, string $sector, array $bounds): Builder
    {
        $ids = [];
        $this->geographicQuery(clone $query, $sector)->chunkById(500, function ($rows) use (&$ids, $sector, $bounds): void {
            foreach ($rows as $row) {
                $source = $sector === 'housing-units' ? $row->building : $row;
                $geometry = $source ? $this->geometry($source) : null;
                if ($geometry !== null && $this->intersectsExtent($geometry, $bounds)) {
                    $ids[] = (int) $row->id;
                }
            }
        });

        return $query->whereIntegerInRaw($query->getModel()->qualifyColumn('id'), $ids);
    }

    /** @param array<string, mixed> $geometry
     * @param  array<string, mixed>  $bounds
     */
    private function intersectsExtent(array $geometry, array $bounds): bool
    {
        $wkid = (int) ($geometry['spatialReference']['latestWkid'] ?? $geometry['spatialReference']['wkid'] ?? 4326);
        if (! in_array($wkid, [4326, 3857, 102100, 102113], true)) {
            return false;
        }
        $coordinates = isset($geometry['x'], $geometry['y']) ? [[$geometry['x'], $geometry['y']]]
            : array_merge(...($geometry['rings'] ?? $geometry['paths'] ?? [[]]));
        $points = [];
        foreach ($coordinates as $coordinate) {
            if (! isset($coordinate[0], $coordinate[1]) || ! is_numeric($coordinate[0]) || ! is_numeric($coordinate[1])) {
                continue;
            }
            [$x, $y] = [(float) $coordinate[0], (float) $coordinate[1]];
            if ($wkid !== 4326) {
                $x = rad2deg($x / 6378137);
                $y = rad2deg(atan(sinh(max(-20037508.35, min(20037508.35, $y)) / 6378137)));
            }
            $points[] = [$x, $y];
        }
        if ($points === []) {
            return false;
        }
        $longitudes = array_column($points, 0);
        $latitudes = array_column($points, 1);

        return max($longitudes) >= (float) $bounds['west'] && min($longitudes) <= (float) $bounds['east']
            && max($latitudes) >= (float) $bounds['south'] && min($latitudes) <= (float) $bounds['north'];
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
        $query = $this->query($sector, $filters)->where('id', '>', $filters['after_id'] ?? 0)->orderBy('id');
        $rows = $this->withLatestAudit($this->geographicQuery($query, $sector))->limit(501)->get();
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
                'attributes' => $this->recordAttributes($row, $sector),
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
