<?php

namespace App\Modules\DamageAssessment\Services\Reports;

use App\Models\Building;
use App\Models\Filter;
use App\Models\HousingUnit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class phcPdfReportService
{
    private const COLORS = [
        'blue' => '#0f4c81',
        'cyan' => '#16a6d9',
        'orange' => '#f58220',
        'green' => '#2fb344',
        'red' => '#d64545',
        'dark' => '#263238',
        'muted' => '#718096',
        'light' => '#eef7fb',
    ];

    /**
     * @var array{0: float, 1: float, 2: float, 3: float}
     */
    private const GAZA_BBOX = [34.18, 31.20, 34.56, 31.60];

    /**
     * @var array<string, array{0: float, 1: float, 2: float, 3: float}>
     */
    private const GOVERNORATE_BBOXES = [
        'North Gaza' => [34.43, 31.47, 34.56, 31.60],
        'Gaza' => [34.35, 31.43, 34.51, 31.55],
        'Middle Area' => [34.28, 31.33, 34.45, 31.46],
        'Khan Younis' => [34.20, 31.25, 34.38, 31.39],
        'Rafah' => [34.18, 31.20, 34.32, 31.32],
    ];

    public function build(Request $request): array
    {
        $filters = $this->filters($request);
        $buildings = $this->buildingRows($filters);
        $housingUnits = $this->housingRows($filters);

        $totals = $this->totals($buildings, $housingUnits);
        $damageDistribution = $this->distribution($housingUnits, 'unit_damage_status', $this->housingDamageLabels());
        $occupancyDistribution = $this->distribution($housingUnits, 'occupied', $this->occupancyLabels());
        $buildingTypeDistribution = $this->distribution($buildings, 'building_type', $this->buildingTypeLabels());
        $buildingUseDistribution = $this->distribution($buildings, 'building_use', $this->buildingUseLabels());
        $governorateOptions = $this->governorates($buildings);
        $governorateMapLabels = $this->mapGovernorateLabels($governorateOptions);
        $governorates = $this->governoratePages($governorateOptions, $governorateMapLabels, $buildings, $housingUnits);
        $neighborhoodPages = $this->neighborhoodPages($governorates);

        return [
            'reportDate' => now()->format('Y-m-d'),
            'generatedAt' => now()->format('Y-m-d H:i'),
            'filters' => $filters,
            'colors' => self::COLORS,
            'totals' => $totals,
            'damageDistribution' => $damageDistribution,
            'occupancyDistribution' => $occupancyDistribution,
            'buildingTypeDistribution' => $buildingTypeDistribution,
            'buildingUseDistribution' => $buildingUseDistribution,
            'governorates' => $governorates,
            'neighborhoodPages' => $neighborhoodPages,
            'summaryRows' => $this->summaryRows($governorates),
            'gazaMap' => $this->arcgisMap($this->buildingCoordinates($buildings, 650), 'قطاع غزة'),
            'totalPages' => 14,
        ];
    }

    /**
     * @return array{start_date: string|null, end_date: string|null, governorate: string|null, municipalitie: string|null}
     */
    private function filters(Request $request): array
    {
        return [
            'start_date' => $request->filled('start_date') ? Carbon::parse($request->input('start_date'))->startOfDay()->toDateTimeString() : null,
            'end_date' => $request->filled('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay()->toDateTimeString() : null,
            'governorate' => $request->filled('governorate') ? (string) $request->input('governorate') : null,
            'municipalitie' => $request->filled('municipalitie') ? (string) $request->input('municipalitie') : null,
        ];
    }

    /**
     * @param  array<string, string|null>  $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if ($filters['start_date'] && $filters['end_date']) {
            $query->whereBetween('creationdate', [$filters['start_date'], $filters['end_date']]);
        }

        if ($filters['governorate']) {
            $query->whereIn('governorate', $this->governorateAliases($filters['governorate']));
        }

        if ($filters['municipalitie']) {
            $query->where('municipalitie', $filters['municipalitie']);
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    private function buildingRows(array $filters): Collection
    {
        return $this->applyFilters(
            Building::query()->select([
                'governorate',
                'municipalitie',
                'neighborhood',
                'building_damage_status',
                'building_type',
                'building_use',
                'latitude',
                'longitude',
            ]),
            $filters
        )->get();
    }

    /**
     * @param  array<string, string|null>  $filters
     */
    private function housingRows(array $filters): Collection
    {
        return $this->applyFilters(
            HousingUnit::query()->select([
                'governorate',
                'municipalitie',
                'neighborhood',
                'unit_damage_status',
                'occupied',
            ]),
            $filters
        )->get();
    }

    private function totals(Collection $buildings, Collection $housingUnits, bool $includeWorkflowTotals = true): array
    {
        $assessedBuildings = $buildings->filter(fn ($row): bool => filled($row->building_damage_status))->count();
        $assessedHousingUnits = $housingUnits->filter(fn ($row): bool => filled($row->unit_damage_status))->count();

        return [
            'buildings' => $buildings->count(),
            'housing_units' => $housingUnits->count(),
            'assessed_buildings' => $assessedBuildings,
            'assessed_housing_units' => $assessedHousingUnits,
            'affected_population' => (int) round($assessedHousingUnits * 5.3),
            'edited_assessments' => $includeWorkflowTotals ? DB::table('edit_assessments')->count() : 0,
            'building_statuses' => $includeWorkflowTotals ? DB::table('building_statuses')->count() : 0,
            'housing_statuses' => $includeWorkflowTotals ? DB::table('housing_statuses')->count() : 0,
            'assessment_statuses' => $includeWorkflowTotals ? DB::table('assessment_statuses')->count() : 0,
            'assessments' => $includeWorkflowTotals ? DB::table('assessments')->count() : 0,
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, array{key: string, label: string, value: int, percent: float, color: string}>
     */
    private function distribution(Collection $rows, string $column, array $labels): array
    {
        $groupedRows = $rows
            ->groupBy(fn ($row): string => (string) ($row->{$column} ?? 'not_available'))
            ->map(fn (Collection $items): int => $items->count());

        $total = max((int) $groupedRows->sum(), 1);
        $palette = [self::COLORS['blue'], self::COLORS['cyan'], self::COLORS['orange'], self::COLORS['green'], self::COLORS['red'], self::COLORS['dark']];

        return $groupedRows
            ->sortDesc()
            ->map(function (int $count, string $key) use ($labels, $total) {
                return [
                    'key' => $key,
                    'label' => $labels[$key] ?? $this->humanize($key),
                    'value' => $count,
                    'percent' => round(($count / $total) * 100, 1),
                    'color' => self::COLORS['blue'],
                ];
            })
            ->values()
            ->map(function (array $item, int $index) use ($palette) {
                $item['color'] = $palette[$index % count($palette)];

                return $item;
            })
            ->all();
    }

    /**
     * @return Collection<int, array{key: string, name: string, english_name: string}>
     */
    private function governorates(Collection $buildings): Collection
    {
        $databaseGovernorates = $this->buildingGovernorates($buildings);

        $filterGovernorates = Filter::query()
            ->where('list_name', 'governorate')
            ->whereNotNull('name')
            ->orderBy('id')
            ->get(['name', 'label'])
            ->map(function (Filter $filter): array {
                $name = trim((string) $filter->name);
                $label = trim((string) $filter->label);

                return [
                    'key' => $name,
                    'name' => $label !== '' ? $label : $this->humanize($name),
                    'english_name' => $name,
                ];
            })
            ->filter(fn (array $governorate): bool => $governorate['english_name'] !== '')
            ->filter(fn (array $governorate): bool => $this->matchesDatabaseGovernorate($governorate['english_name'], $databaseGovernorates))
            ->unique('english_name')
            ->values();

        if ($filterGovernorates->isNotEmpty()) {
            return $filterGovernorates;
        }

        return $databaseGovernorates
            ->map(fn (string $governorate): array => [
                'key' => $governorate,
                'name' => $this->humanize($governorate),
                'english_name' => $governorate,
            ])
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function buildingGovernorates(Collection $buildings): Collection
    {
        return $buildings
            ->pluck('governorate')
            ->filter(fn ($governorate): bool => filled($governorate))
            ->map(fn ($governorate): string => trim((string) $governorate))
            ->unique(fn (string $governorate): string => $this->normalizeGovernorate($governorate))
            ->sort()
            ->values();
    }

    /**
     * @param  Collection<int, string>  $databaseGovernorates
     */
    private function matchesDatabaseGovernorate(string $governorate, Collection $databaseGovernorates): bool
    {
        $aliases = array_map(
            fn (string $alias): string => $this->normalizeGovernorate($alias),
            $this->governorateAliases($governorate)
        );

        return $databaseGovernorates
            ->map(fn (string $databaseGovernorate): string => $this->normalizeGovernorate($databaseGovernorate))
            ->intersect($aliases)
            ->isNotEmpty();
    }

    /**
     * @param  Collection<int, array{key: string, name: string, english_name: string}>  $governorates
     * @return array<string, string>
     */
    private function mapGovernorateLabels(Collection $governorates): array
    {
        return $governorates
            ->mapWithKeys(fn (array $governorate): array => [
                $this->mapGovernorateKey($governorate['english_name']) => $governorate['name'],
            ])
            ->all();
    }

    /**
     * @param  Collection<int, array{key: string, name: string, english_name: string}>  $governorates
     * @param  array<string, string>  $governorateMapLabels
     * @return array<int, array<string, mixed>>
     */
    private function governoratePages(Collection $governorates, array $governorateMapLabels, Collection $buildings, Collection $housingUnits): array
    {
        return $governorates
            ->map(function (array $governorate) use ($buildings, $housingUnits) {
                $englishName = $governorate['english_name'];
                $displayName = $governorate['name'];
                $aliases = $this->governorateAliases($englishName);
                $governorateBuildings = $buildings->whereIn('governorate', $aliases)->values();
                $governorateHousingUnits = $housingUnits->whereIn('governorate', $aliases)->values();

                return [
                    'key' => $englishName,
                    'name' => $displayName,
                    'english_name' => $englishName,
                    'totals' => $this->totals($governorateBuildings, $governorateHousingUnits, false),
                    'damage' => $this->distribution($governorateHousingUnits, 'unit_damage_status', $this->housingDamageLabels()),
                    'occupancy' => $this->distribution($governorateHousingUnits, 'occupied', $this->occupancyLabels()),
                    'building_types' => $this->distribution($governorateBuildings, 'building_type', $this->buildingTypeLabels()),
                    'municipalities' => $this->areaRows($governorateBuildings, $governorateHousingUnits, 'municipalitie', 8),
                    'neighborhoods' => $this->areaRows($governorateBuildings, $governorateHousingUnits, 'neighborhood', 10),
                    'map' => $this->arcgisMap(
                        $this->buildingCoordinates($governorateBuildings, 350),
                        $displayName,
                        $this->mapGovernorateKey($englishName)
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function neighborhoodPages(array $governorates): array
    {
        return collect($governorates)
            ->map(function (array $governorate) {
                return [
                    'governorate' => $governorate['name'],
                    'english_name' => $governorate['english_name'],
                    'rows' => array_slice($governorate['neighborhoods'], 0, 12),
                    'map' => $governorate['map'],
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function areaRows(Collection $buildings, Collection $housingUnits, string $column, int $limit): array
    {
        $buildingRows = $buildings
            ->filter(fn ($row): bool => filled($row->{$column}))
            ->groupBy($column)
            ->map(fn (Collection $items): int => $items->count());

        return $housingUnits
            ->filter(fn ($row): bool => filled($row->{$column}))
            ->groupBy($column)
            ->map(function (Collection $items, string $name) use ($buildingRows) {
                return [
                    'name' => $this->humanize($name),
                    'buildings' => (int) ($buildingRows[$name] ?? 0),
                    'housing_units' => $items->count(),
                    'partially_damaged' => $items->where('unit_damage_status', 'partially_damaged2')->count(),
                    'fully_damaged' => $items->where('unit_damage_status', 'fully_damaged2')->count(),
                    'committee_review' => $items->where('unit_damage_status', 'committee_review2')->count(),
                    'occupied' => $items->where('occupied', 'yes')->count(),
                    'vacant' => $items->where('occupied', 'no')->count(),
                ];
            })
            ->sortByDesc('housing_units')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $governorates
     * @return array<int, array<string, mixed>>
     */
    private function summaryRows(array $governorates): array
    {
        return collect($governorates)
            ->map(fn (array $governorate): array => [
                'name' => $governorate['name'],
                'buildings' => $governorate['totals']['buildings'],
                'housing_units' => $governorate['totals']['housing_units'],
                'assessed_housing_units' => $governorate['totals']['assessed_housing_units'],
                'affected_population' => $governorate['totals']['affected_population'],
            ])
            ->all();
    }

    /**
     * @return Collection<int, object>
     */
    private function buildingCoordinates(Collection $buildings, int $limit): Collection
    {
        return $buildings
            ->filter(fn ($row): bool => filled($row->latitude)
                && filled($row->longitude)
                && (float) $row->latitude >= 31.1
                && (float) $row->latitude <= 31.7
                && (float) $row->longitude >= 34.1
                && (float) $row->longitude <= 34.7)
            ->take($limit)
            ->values();
    }

    /**
     * @return array{title: string, image_url: string, points: array<int, array{x: float, y: float, color: string}>, has_points: bool}
     */
    private function arcgisMap(Collection $coordinates, string $title, ?string $governorateKey = null): array
    {
        $bbox = $this->mapBbox($coordinates, $governorateKey);

        $points = $coordinates
            ->map(function ($point) use ($bbox): array {
                [$minLongitude, $minLatitude, $maxLongitude, $maxLatitude] = $bbox;
                $longitudeSpan = max($maxLongitude - $minLongitude, 0.0001);
                $latitudeSpan = max($maxLatitude - $minLatitude, 0.0001);
                $color = match ($point->building_damage_status) {
                    'fully_damaged' => self::COLORS['orange'],
                    'partially_damaged' => self::COLORS['cyan'],
                    'committee_review' => self::COLORS['green'],
                    default => self::COLORS['blue'],
                };

                return [
                    'x' => round((((float) $point->longitude - $minLongitude) / $longitudeSpan) * 100, 3),
                    'y' => round((1 - (((float) $point->latitude - $minLatitude) / $latitudeSpan)) * 100, 3),
                    'color' => $color,
                ];
            })
            ->filter(fn (array $point): bool => $point['x'] >= 0 && $point['x'] <= 100 && $point['y'] >= 0 && $point['y'] <= 100)
            ->values()
            ->all();

        return [
            'title' => $title,
            'image_url' => $this->arcgisImageUrl($bbox),
            'points' => $points,
            'has_points' => $coordinates->isNotEmpty(),
        ];
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function mapBbox(Collection $coordinates, ?string $governorateKey): array
    {
        if ($coordinates->isEmpty()) {
            return self::GOVERNORATE_BBOXES[$governorateKey ?? ''] ?? self::GAZA_BBOX;
        }

        $minLatitude = (float) $coordinates->min('latitude');
        $maxLatitude = (float) $coordinates->max('latitude');
        $minLongitude = (float) $coordinates->min('longitude');
        $maxLongitude = (float) $coordinates->max('longitude');

        $latitudePadding = max(($maxLatitude - $minLatitude) * 0.18, 0.015);
        $longitudePadding = max(($maxLongitude - $minLongitude) * 0.18, 0.015);

        return [
            max(self::GAZA_BBOX[0], $minLongitude - $longitudePadding),
            max(self::GAZA_BBOX[1], $minLatitude - $latitudePadding),
            min(self::GAZA_BBOX[2], $maxLongitude + $longitudePadding),
            min(self::GAZA_BBOX[3], $maxLatitude + $latitudePadding),
        ];
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     */
    private function arcgisImageUrl(array $bbox): string
    {
        return 'https://services.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/export?'.http_build_query([
            'bbox' => implode(',', $bbox),
            'bboxSR' => 4326,
            'imageSR' => 4326,
            'size' => '960,420',
            'format' => 'png32',
            'transparent' => 'false',
            'f' => 'image',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array<int, string>
     */
    private function governorateAliases(string $governorate): array
    {
        $normalizedGovernorate = $this->normalizeGovernorate($governorate);
        $aliases = [
            $governorate,
            str_replace(' ', '_', $governorate),
            str_replace('_', ' ', $governorate),
        ];

        if (in_array($normalizedGovernorate, ['north', 'north_gaza'], true)) {
            $aliases = array_merge($aliases, ['North Gaza', 'North_Gaza']);
        }

        if (in_array($normalizedGovernorate, ['middle_area', 'middle area'], true)) {
            $aliases = array_merge($aliases, ['Middle Area', 'Middle_Area']);
        }

        if (in_array($normalizedGovernorate, ['khan_younis', 'khan younis'], true)) {
            $aliases = array_merge($aliases, ['Khan Younis', 'Khan_Younis']);
        }

        return array_values(array_unique($aliases));
    }

    private function mapGovernorateKey(string $governorate): string
    {
        return match ($this->normalizeGovernorate($governorate)) {
            'north', 'north_gaza' => 'North Gaza',
            'middle_area' => 'Middle Area',
            'khan_younis' => 'Khan Younis',
            default => str_replace('_', ' ', $governorate),
        };
    }

    private function normalizeGovernorate(string $governorate): string
    {
        return strtolower(str_replace(' ', '_', trim($governorate)));
    }

    /**
     * @return array<string, string>
     */
    private function housingDamageLabels(): array
    {
        return [
            'partially_damaged2' => 'ضرر جزئي',
            'fully_damaged2' => 'ضرر كلي',
            'committee_review2' => 'مراجعة لجنة',
            'not_available' => 'غير محدد',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function occupancyLabels(): array
    {
        return [
            'yes' => 'مشغولة',
            'no' => 'غير مشغولة',
            'not_available' => 'غير محدد',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildingTypeLabels(): array
    {
        return [
            'building' => 'عمارة',
            'house1' => 'منزل',
            'villa' => 'فيلا',
            'tower' => 'برج',
            'canopy' => 'مظلة',
            'building_other' => 'أخرى',
            'not_available' => 'غير محدد',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildingUseLabels(): array
    {
        return [
            'residential' => 'سكني',
            'combined' => 'مختلط',
            'work' => 'عمل',
            'not_available' => 'غير محدد',
        ];
    }

    private function humanize(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return 'غير محدد';
        }

        return str_replace(['_', '.'], [' ', ''], $value);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
