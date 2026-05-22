<?php

declare(strict_types=1);

namespace App\Modules\DamageAssessment\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\PublicBuildingSurvey;
use App\Models\RoadFacilitySurvey;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SurveyReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:Database Officer|Project Officer|undp-Project Manager|Auditing Supervisor|Area Manager');
    }

    public function publicBuildings(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDates(
            $request,
            PublicBuildingSurvey::query()->whereNotNull('date_of_damage')->min('date_of_damage'),
            PublicBuildingSurvey::query()->whereNotNull('date_of_damage')->max('date_of_damage')
        );

        $surveys = PublicBuildingSurvey::query()
            ->withCount('units')
            ->whereNotNull('date_of_damage')
            ->whereBetween('date_of_damage', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        $statusCounts = $surveys
            ->groupBy(fn (PublicBuildingSurvey $survey) => $survey->building_damage_status ?: 'not_specified')
            ->map->count()
            ->sortDesc();

        $municipalityRows = $surveys
            ->groupBy(fn (PublicBuildingSurvey $survey) => $survey->municipalitie ?: 'Unknown')
            ->map(function (Collection $items, string $municipality): array {
                return [
                    'name' => $municipality,
                    'total_surveys' => $items->count(),
                    'damaged_surveys' => $items->filter(fn (PublicBuildingSurvey $survey) => filled($survey->building_damage_status))->count(),
                    'total_units' => (int) $items->sum('units_count'),
                ];
            })
            ->sortByDesc('total_surveys')
            ->values();

        return view('damage-assessment::reports.survey_overview', [
            'reportTitle' => __('multilingual.public_buildings_page.report_title'),
            'reportSubtitle' => __('multilingual.public_buildings_page.report_subtitle'),
            'reportRoute' => route('reports.public-buildings'),
            'startDateValue' => $startDate->toDateString(),
            'endDateValue' => $endDate->toDateString(),
            'summaryCards' => [
                ['label' => __('multilingual.public_buildings_page.total_surveys'), 'value' => $surveys->count(), 'class' => 'primary'],
                ['label' => __('multilingual.public_buildings_page.damaged_buildings'), 'value' => $surveys->whereNotNull('building_damage_status')->where('building_damage_status', '!=', '')->count(), 'class' => 'danger'],
                ['label' => __('multilingual.public_buildings_page.total_units'), 'value' => (int) $surveys->sum('units_count'), 'class' => 'success'],
                ['label' => __('multilingual.public_buildings_page.municipalities'), 'value' => $municipalityRows->count(), 'class' => 'info'],
            ],
            'primaryChart' => [
                'selector' => 'public_buildings_status_chart',
                'title' => __('multilingual.public_buildings_page.damage_distribution'),
                'labels' => $statusCounts->keys()->map(fn (string $label) => str($label)->replace('_', ' ')->headline()->toString())->values()->all(),
                'series' => $statusCounts->values()->all(),
                'colors' => ['#f1416c', '#ffc700', '#50cd89', '#7239ea', '#009ef7', '#e4e6ef'],
            ],
            'secondaryChart' => [
                'selector' => 'public_buildings_municipality_chart',
                'title' => __('multilingual.public_buildings_page.surveys_by_municipality'),
                'labels' => $municipalityRows->pluck('name')->all(),
                'series' => $municipalityRows->pluck('total_surveys')->all(),
                'colors' => ['#009ef7'],
            ],
            'curveChart' => [
                'selector' => 'public_buildings_curve_chart',
                'title' => __('multilingual.public_buildings_page.daily_curve'),
                'labels' => $this->buildCurveChartData($surveys, 'date_of_damage', $startDate, $endDate)['labels'],
                'series' => $this->buildCurveChartData($surveys, 'date_of_damage', $startDate, $endDate)['series'],
                'color' => '#7239ea',
            ],
            'tableTitle' => __('multilingual.public_buildings_page.municipality'),
            'tableColumns' => [
                ['label' => __('multilingual.public_buildings_page.total_surveys'), 'key' => 'total_surveys', 'class' => 'primary'],
                ['label' => __('multilingual.public_buildings_page.damaged_buildings'), 'key' => 'damaged_surveys', 'class' => 'danger'],
                ['label' => __('multilingual.public_buildings_page.total_units'), 'key' => 'total_units', 'class' => 'success'],
            ],
            'rows' => $municipalityRows,
            'emptyMessage' => __('multilingual.public_buildings_page.empty_report'),
        ]);
    }

    public function roadFacilities(Request $request): View
    {
        [$startDate, $endDate] = $this->resolveDates(
            $request,
            $this->resolveRoadFacilitiesMinimumDate(),
            $this->resolveRoadFacilitiesMaximumDate()
        );

        $surveys = RoadFacilitySurvey::query()
            ->withCount('items')
            ->get();

        $surveys = $surveys
            ->filter(function (RoadFacilitySurvey $survey) use ($startDate, $endDate): bool {
                $effectiveDate = $this->resolveRoadFacilityEffectiveDate($survey);

                return $effectiveDate !== null
                    && $effectiveDate->betweenIncluded($startDate, $endDate);
            })
            ->values();

        $damageCounts = collect([
            'destroyed' => $surveys->where('road_damage_level', 'destroyed')->count(),

            'severe' => $surveys->where('road_damage_level', 'severe')->count(),

            'moderate' => $surveys->where('road_damage_level', 'moderate')->count(),

            'minor' => $surveys->where('road_damage_level', 'minor')->count(),

            'No_Damage' => $surveys
                ->filter(
                    fn ($survey) => in_array($survey->road_damage_level, ['No_Damage', 'no_damage'])
                )
                ->count(),
        ]);

        $accessCounts = $surveys
            ->groupBy(fn (RoadFacilitySurvey $survey) => $survey->road_access ?: 'not_specified')
            ->map->count()
            ->sortDesc();

        $municipalityRows = $surveys
            ->groupBy(fn (RoadFacilitySurvey $survey) => $survey->municipalitie ?: 'Unknown')
            ->map(function (Collection $items, string $municipality): array {
                return [
                    'name' => $municipality,
                    'total_surveys' => $items->count(),
                    'damaged_roads' => $items->filter(fn (RoadFacilitySurvey $survey) => filled($survey->road_damage_level))->count(),
                    'total_items' => (int) $items->sum('items_count'),
                ];
            })
            ->sortByDesc('total_surveys')
            ->values();

        return view('damage-assessment::reports.survey_overview', [
            'reportTitle' => __('multilingual.road_facilities_page.report_title'),
            'reportSubtitle' => __('multilingual.road_facilities_page.report_subtitle'),
            'reportRoute' => route('reports.road-facilities'),

            'startDateValue' => $startDate->toDateString(),
            'endDateValue' => $endDate->toDateString(),

            'summaryCards' => [
                [
                    'label' => __('multilingual.road_facilities_page.total_surveys'),
                    'value' => $surveys->count(),
                    'class' => 'primary',
                ],

                [
                    'label' => __('multilingual.road_facilities_page.damaged_roads'),
                    'value' => $surveys
                        ->whereNotNull('road_damage_level')
                        ->where('road_damage_level', '!=', '')
                        ->count(),
                    'class' => 'danger',
                ],

                [
                    'label' => __('multilingual.road_facilities_page.total_items'),
                    'value' => (int) $surveys->sum('items_count'),
                    'class' => 'success',
                ],

                [
                    'label' => __('multilingual.road_facilities_page.municipalities'),
                    'value' => $municipalityRows->count(),
                    'class' => 'info',
                ],
            ],

            'primaryChart' => [
                'selector' => 'road_facilities_damage_chart',

                'title' => __('multilingual.road_facilities_page.damage_distribution'),

                'labels' => [
                    __('multilingual.area_productivity_reports.metrics.destroyed'),
                    __('multilingual.area_productivity_reports.metrics.severe'),
                    __('multilingual.area_productivity_reports.metrics.moderate'),
                    __('multilingual.area_productivity_reports.metrics.minor'),
                    __('multilingual.area_productivity_reports.metrics.no_damage'),
                ],

                'series' => [
                    $damageCounts['destroyed'] ?? 0,
                    $damageCounts['severe'] ?? 0,
                    $damageCounts['moderate'] ?? 0,
                    $damageCounts['minor'] ?? 0,
                    $damageCounts['No_Damage'] ?? 0,
                ],

                'colors' => [
                    '#f1416c', // Destroyed
                    '#d9214e', // Severe
                    '#ffc700', // Moderate
                    '#009ef7', // Minor
                    '#50cd89', // No Damage
                ],
            ],

            'secondaryChart' => [
                'selector' => 'road_facilities_access_chart',

                'title' => __('multilingual.road_facilities_page.access_distribution'),

                'labels' => $accessCounts->keys()
                    ->map(
                        fn (string $label) => str($label)
                            ->replace('_', ' ')
                            ->headline()
                            ->toString()
                    )
                    ->values()
                    ->all(),

                'series' => $accessCounts->values()->all(),

                'colors' => ['#50cd89'],
            ],

            'curveChart' => [
                'selector' => 'road_facilities_curve_chart',

                'title' => __('multilingual.road_facilities_page.daily_curve'),

                'labels' => $this->buildRoadFacilitiesCurveChartData(
                    $surveys,
                    $startDate,
                    $endDate
                )['labels'],

                'series' => $this->buildRoadFacilitiesCurveChartData(
                    $surveys,
                    $startDate,
                    $endDate
                )['series'],

                'color' => '#009ef7',
            ],

            'tableTitle' => __('multilingual.road_facilities_page.municipality'),

            'tableColumns' => [
                [
                    'label' => __('multilingual.road_facilities_page.total_surveys'),
                    'key' => 'total_surveys',
                    'class' => 'primary',
                ],

                [
                    'label' => __('multilingual.road_facilities_page.damaged_roads'),
                    'key' => 'damaged_roads',
                    'class' => 'danger',
                ],

                [
                    'label' => __('multilingual.road_facilities_page.total_items'),
                    'key' => 'total_items',
                    'class' => 'success',
                ],
            ],

            'rows' => $municipalityRows,

            'emptyMessage' => __('multilingual.road_facilities_page.empty_report'),
        ]);

    }

    private function resolveDates(Request $request, mixed $minimumDate, mixed $maximumDate): array
    {
        $startDate = filled($request->input('start_date'))
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : ($minimumDate ? Carbon::parse($minimumDate)->startOfDay() : now()->startOfDay());

        $endDate = filled($request->input('end_date'))
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : ($maximumDate ? Carbon::parse($maximumDate)->endOfDay() : $startDate->copy()->endOfDay());

        if ($endDate->lt($startDate)) {
            $endDate = $startDate->copy()->endOfDay();
        }

        return [$startDate, $endDate];
    }

    private function buildCurveChartData(Collection $surveys, string $dateKey, Carbon $startDate, Carbon $endDate): array
    {
        $series = [];
        $labels = [];
        $counts = $surveys
            ->filter(fn ($survey) => filled($survey->{$dateKey}))
            ->groupBy(fn ($survey) => Carbon::parse($survey->{$dateKey})->toDateString())
            ->map->count();

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateString = $date->toDateString();
            $labels[] = $dateString;
            $series[] = (int) ($counts[$dateString] ?? 0);
        }

        return [
            'labels' => $labels,
            'series' => $series,
        ];
    }

    private function resolveRoadFacilitiesMinimumDate(): ?Carbon
    {
        return RoadFacilitySurvey::query()
            ->get()
            ->map(fn (RoadFacilitySurvey $survey) => $this->resolveRoadFacilityEffectiveDate($survey))
            ->filter()
            ->sort()
            ->first();
    }

    private function resolveRoadFacilitiesMaximumDate(): ?Carbon
    {
        return RoadFacilitySurvey::query()
            ->get()
            ->map(fn (RoadFacilitySurvey $survey) => $this->resolveRoadFacilityEffectiveDate($survey))
            ->filter()
            ->sort()
            ->last();
    }

    private function resolveRoadFacilityEffectiveDate(RoadFacilitySurvey $survey): ?Carbon
    {
        if ($survey->submissiondate !== null) {
            return Carbon::parse($survey->submissiondate);
        }

        if ($survey->created_at !== null) {
            return Carbon::parse($survey->created_at);
        }

        return null;
    }

    private function buildRoadFacilitiesCurveChartData(Collection $surveys, Carbon $startDate, Carbon $endDate): array
    {
        $series = [];
        $labels = [];
        $counts = $surveys
            ->map(fn (RoadFacilitySurvey $survey) => $this->resolveRoadFacilityEffectiveDate($survey))
            ->filter()
            ->groupBy(fn (Carbon $date) => $date->toDateString())
            ->map->count();

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateString = $date->toDateString();
            $labels[] = $dateString;
            $series[] = (int) ($counts[$dateString] ?? 0);
        }

        return [
            'labels' => $labels,
            'series' => $series,
        ];
    }
}
