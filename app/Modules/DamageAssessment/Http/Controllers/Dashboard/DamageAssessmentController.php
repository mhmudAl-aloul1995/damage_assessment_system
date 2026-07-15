<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssignedAssessmentUser;
use App\Models\Building;
use App\Models\EditAssessment;
use App\Models\Filter;
use App\Models\HousingUnit;
use App\Models\PublicBuildingSurvey;
use App\Models\RoadFacilitySurvey;
use App\Models\User;
use App\Modules\DamageAssessment\Http\Requests\HudBuildingUnitsRequest;
use App\Services\ArcgisService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rule;
use Illuminate\View\View as ViewResponse;
use Yajra\Datatables\Datatables;

class DamageAssessmentController extends Controller
{
    private const TEMPORARY_HIDDEN_AUDIT_ACTION_USER_NAMES = [
        'ياسمين ماهر مصطفى ابومدللة',
        'غادة محمود عبدالحي الهباش',
        'رانيه سليمان راشد شعت',
    ];

    private const TEMPORARY_HIDDEN_AUDIT_ACTION_USER_ID_NUMBERS = [
        '800409062',
        '400940623',
        '803275288',
        '800900607',
        '801773987',
    ];

    public function index(Request $request, $objectid = null): ViewResponse|RedirectResponse
    {
        if ($request->user()?->hasAnyRole(['Field Engineer', 'field Engineer'])) {
            return redirect()->to(app_route('audit.fieldEngineer'));
        }

        $arcgis = app(ArcgisService::class);
        $token = $arcgis->getToken();

        [$startDate, $endDate, $period] = $this->dashboardDateRange($request);
        $selectedNeighborhood = $request->filled('neighborhood')
            ? (string) $request->string('neighborhood')
            : '';

        $buildingQuery = Building::query();
        $this->applyDashboardMapFilters($buildingQuery, $request, '', 'end');

        $housingUnitQuery = HousingUnit::query();
        $this->applyDashboardHousingFilters($housingUnitQuery, $request);

        $data = [
            'buildings' => $buildingQuery
                ->selectRaw("COALESCE(SUM(field_status = 'Not_Completed'), 0) as not_completed,
                COALESCE(SUM(field_status = 'COMPLETED'), 0) as completed,
                COALESCE(SUM(field_status NOT IN ('COMPLETED', 'Not_Completed')), 0) as pending,
                COALESCE(SUM(building_damage_status = 'fully_damaged'), 0) as fully_damaged,
                COALESCE(SUM(building_damage_status = 'partially_damaged'), 0) as partially_damaged,
                COALESCE(SUM(building_damage_status = 'committee_review'), 0) as committee_review,
                COALESCE(SUM(security_situation = 'Unsafe'), 0) as security_unsafe,
                COALESCE(SUM(uxo_present = 'yes3'), 0) as uxo,
                COALESCE(SUM(bodies_present = 'yes3'), 0) as bodies,
                COALESCE(SUM(building_debris_exist = 'yes'), 0) as debris")
                ->first(),
            'units' => $housingUnitQuery
                ->selectRaw("
                COUNT(*) as total_units,
                COALESCE(SUM(unit_damage_status = 'fully_damaged2'), 0) as fully_damaged,
                COALESCE(SUM(unit_damage_status = 'partially_damaged2'), 0) as partially_damaged,
                COALESCE(SUM(unit_damage_status = 'committee_review2'), 0) as committee_review,
                COALESCE(SUM(has_fire = 'yes'), 0) as has_fire,
                COALESCE(SUM(unit_stripping = 'yes'), 0) as has_strip,
                COALESCE(SUM(is_the_housing_unit_or_living_habitable = 'yes'), 0) as habitable,
                COALESCE(SUM(security_situation_unit = 'Unsafe'), 0) as security_unsafe,
                COALESCE(SUM(unit_stripping = 'yes'), 0) as unit_stripping,
                COALESCE(SUM(unit_support_needed = 'yes'), 0) as unit_support_needed")
                ->first(),
        ];

        $unitStats = [
            'total_units' => $data['units']->total_units,
            'fully_damaged' => $data['units']->fully_damaged,
            'partially_damaged' => $data['units']->partially_damaged,
            'committee_review' => $data['units']->committee_review,
            'has_fire' => $data['units']->has_fire,
            'has_strip' => $data['units']->has_strip,
            'habitable' => $data['units']->habitable,
            'security_unsafe' => $data['units']->security_unsafe,
            'unit_stripping' => $data['units']->unit_stripping,
            'unit_support_needed' => $data['units']->unit_support_needed,
        ];
        $buildingStats = [
            'not_completed' => $data['buildings']->not_completed,
            'completed' => $data['buildings']->completed,
            'pending' => $data['buildings']->pending,
            'fully_damaged' => $data['buildings']->fully_damaged,
            'partially_damaged' => $data['buildings']->partially_damaged,
            'committee_review' => $data['buildings']->committee_review,
            'security_unsafe' => $data['buildings']->security_unsafe,
            'uxo' => $data['buildings']->uxo,
            'bodies' => $data['buildings']->bodies,
            'debris' => $data['buildings']->debris,
        ];

        $publicBuildingStats = [
            'total_surveys' => $this->dashboardPublicBuildingQuery($request)
                ->count(),

            'damaged_buildings' => $this->dashboardPublicBuildingQuery($request)
                ->whereNotNull('building_damage_status')
                ->where('building_damage_status', '!=', '')
                ->count(),

            'total_units' => (int) $this->dashboardPublicBuildingQuery($request)
                ->withCount('units')
                ->get()
                ->sum('units_count'),

            'municipalities' => $this->dashboardPublicBuildingQuery($request)
                ->whereNotNull('municipalitie')
                ->where('municipalitie', '!=', '')
                ->distinct()
                ->count('municipalitie'),

            'neighborhoods' => $this->dashboardPublicBuildingQuery($request)
                ->whereNotNull('neighborhood')
                ->where('neighborhood', '!=', '')
                ->distinct()
                ->count('neighborhood'),

            'assigned_staff' => $this->dashboardPublicBuildingQuery($request)
                ->whereNotNull('assignedto')
                ->where('assignedto', '!=', '')
                ->distinct()
                ->count('assignedto'),

            'occupied_buildings' => $this->dashboardPublicBuildingQuery($request)
                ->where('is_building_occupied', 'yes')
                ->count(),

            'bodies_present' => $this->dashboardPublicBuildingQuery($request)
                ->where('is_bodies', 'yes')
                ->count(),

            'uxo_present' => $this->dashboardPublicBuildingQuery($request)
                ->where('is_uxo', 'yes')
                ->count(),

            'completed_road_length_km' => $this->dashboardCompletedRoadLengthKilometers($request),
        ];

        $roadFacilityStats = [
            'total_surveys' => $this->dashboardRoadFacilityQuery($request)
                ->whereNotNull('road_damage_level')
                ->where('road_damage_level', '!=', '')
                ->count(),

            'damaged_roads' => $this->dashboardRoadFacilityQuery($request)
                ->whereNotNull('road_damage_level')
                ->where('road_damage_level', '!=', '')
                ->count(),

            'total_items' => (int) $this->dashboardRoadFacilityQuery($request)
                ->withCount('items')
                ->get()
                ->sum('items_count'),

            'municipalities' => $this->dashboardRoadFacilityQuery($request)
                ->whereNotNull('municipalitie')
                ->where('municipalitie', '!=', '')
                ->distinct()
                ->count('municipalitie'),

            'neighborhoods' => $this->dashboardRoadFacilityQuery($request)
                ->whereNotNull('neighborhood')
                ->where('neighborhood', '!=', '')
                ->distinct()
                ->count('neighborhood'),

            'potholes_locations' => $this->dashboardRoadFacilityQuery($request)
                ->where('potholes_exist', 'yes')
                ->count(),

            'obstacle_locations' => $this->dashboardRoadFacilityQuery($request)
                ->where('obstacle_exist', 'yes')
                ->count(),

            'buried_bodies_locations' => $this->dashboardRoadFacilityQuery($request)
                ->where('buried_bodies', 'yes')
                ->count(),

            'uxo_locations' => $this->dashboardRoadFacilityQuery($request)
                ->where('uxo_present', 'yes')
                ->count(),
        ];
        $publicBuildingLayerUrl = $this->normalizeFeatureLayerUrl((string) config('services.arcgis.public_building_survey_layer_url'));
        $roadFacilityLayerUrl = $this->normalizeFeatureLayerUrl((string) config('services.arcgis.road_facility_survey_layer_url'));
        $neighborhoods = $this->dashboardNeighborhoods();
        $dashboardFilters = compact('period', 'startDate', 'endDate', 'selectedNeighborhood');

        return View::make(
            'damage-assessment::dashboard.damageAssessment',
            compact(
                'token',
                'unitStats',
                'buildingStats',
                'publicBuildingStats',
                'roadFacilityStats',
                'publicBuildingLayerUrl',
                'roadFacilityLayerUrl',
                'neighborhoods',
                'dashboardFilters',
            )
        );
    }

    public function hud(Request $request): \Illuminate\View\View
    {
        $arcgis = app(ArcgisService::class);
        $token = $arcgis->getToken();
        $buildingLayerUrl = $this->normalizeFeatureLayerUrl((string) config('services.arcgis.buildings_url'));
        $governoratesBoundaryLayerUrl = $this->normalizeFeatureLayerUrl((string) config('services.arcgis.governorates_boundaries_url'));
        $neighborhoodsBoundaryLayerUrl = $this->normalizeFeatureLayerUrl((string) config('services.arcgis.neighborhoods_boundaries_url'));

        $buildingStats = Building::query()
            ->selectRaw("
                COUNT(*) as total_buildings,
                COALESCE(SUM(CASE WHEN field_status = 'COMPLETED' THEN 1 ELSE 0 END), 0) as assessed_buildings,
                COALESCE(SUM(CASE WHEN building_damage_status = 'fully_damaged' THEN 1 ELSE 0 END), 0) as fully_damaged_buildings,
                COALESCE(SUM(CASE WHEN building_damage_status = 'partially_damaged' THEN 1 ELSE 0 END), 0) as partially_damaged_buildings,
                COALESCE(SUM(CASE WHEN building_damage_status = 'committee_review' THEN 1 ELSE 0 END), 0) as committee_review_buildings,
                COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(assessment_obstacle, ''))) = 'yes' OR LOWER(TRIM(COALESCE(security_situation, ''))) = 'unsafe' THEN 1 ELSE 0 END), 0) as obstacle_buildings,
                COALESCE(SUM(CAST(building_debris_qty AS DECIMAL(15, 2))), 0) as rubble_quantity
            ")
            ->first();

        $unitStats = HousingUnit::query()
            ->selectRaw("
                COUNT(*) as total_units,
                COALESCE(SUM(CASE WHEN unit_damage_status = 'fully_damaged2' THEN 1 ELSE 0 END), 0) as fully_damaged,
                COALESCE(SUM(CASE WHEN unit_damage_status = 'partially_damaged2' THEN 1 ELSE 0 END), 0) as partially_damaged,
                COALESCE(SUM(CASE WHEN unit_damage_status = 'committee_review2' THEN 1 ELSE 0 END), 0) as committee_review,
                COALESCE(SUM(CASE WHEN unit_damage_status IS NULL OR unit_damage_status = '' THEN 1 ELSE 0 END), 0) as unclassified,
                COALESCE(SUM(CASE WHEN unit_support_needed = 'yes' THEN 1 ELSE 0 END), 0) as support_needed,
                COALESCE(SUM(CASE WHEN is_the_housing_unit_or_living_habitable = 'yes' THEN 1 ELSE 0 END), 0) as habitable
            ")
            ->first();

        $buildingMunicipalities = Building::query()
            ->selectRaw("
                COALESCE(NULLIF(municipalitie, ''), 'غير محدد') as municipality_name,
                COUNT(*) as assessed_buildings,
                COALESCE(SUM(CASE WHEN building_damage_status = 'fully_damaged' THEN 1 ELSE 0 END), 0) as destroyed_buildings,
                COALESCE(SUM(CASE WHEN building_damage_status = 'partially_damaged' THEN 1 ELSE 0 END), 0) as partially_damaged_buildings,
                COALESCE(SUM(CASE WHEN building_damage_status = 'committee_review' THEN 1 ELSE 0 END), 0) as committee_review_buildings,
                COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(assessment_obstacle, ''))) = 'yes' OR LOWER(TRIM(COALESCE(security_situation, ''))) = 'unsafe' THEN 1 ELSE 0 END), 0) as obstacle_buildings
            ")
            ->groupBy('municipality_name')
            ->get()
            ->keyBy('municipality_name');

        $unitMunicipalities = HousingUnit::query()
            ->selectRaw("
                COALESCE(NULLIF(municipalitie, ''), 'غير محدد') as municipality_name,
                COUNT(*) as units,
                COALESCE(SUM(CASE WHEN unit_damage_status = 'fully_damaged2' THEN 1 ELSE 0 END), 0) as destroyed_units,
                COALESCE(SUM(CASE WHEN unit_damage_status = 'partially_damaged2' THEN 1 ELSE 0 END), 0) as partially_damaged_units,
                COALESCE(SUM(CASE WHEN unit_damage_status = 'committee_review2' THEN 1 ELSE 0 END), 0) as committee_review_units,
                COALESCE(SUM(CASE WHEN unit_damage_status IS NULL OR unit_damage_status = '' THEN 1 ELSE 0 END), 0) as unclassified_units
            ")
            ->groupBy('municipality_name')
            ->get()
            ->keyBy('municipality_name');

        $buildingMunicipalityNeighborhoodStats = Building::query()
            ->selectRaw("
                COALESCE(NULLIF(municipalitie, ''), 'غير محدد') as municipality_name,
                COALESCE(NULLIF(neighborhood, ''), 'غير محدد') as neighborhood_name,
                COUNT(*) as assessed_buildings,
                COALESCE(SUM(CASE WHEN building_damage_status = 'fully_damaged' THEN 1 ELSE 0 END), 0) as destroyed_buildings
            ")
            ->groupBy('municipality_name', 'neighborhood_name')
            ->get()
            ->groupBy('municipality_name')
            ->map(fn (Collection $rows): Collection => $rows->keyBy('neighborhood_name'));

        $unitMunicipalityNeighborhoodStats = HousingUnit::query()
            ->selectRaw("
                COALESCE(NULLIF(municipalitie, ''), 'غير محدد') as municipality_name,
                COALESCE(NULLIF(neighborhood, ''), 'غير محدد') as neighborhood_name,
                COUNT(*) as units,
                COALESCE(SUM(CASE WHEN unit_damage_status = 'fully_damaged2' THEN 1 ELSE 0 END), 0) as destroyed_units
            ")
            ->groupBy('municipality_name', 'neighborhood_name')
            ->get()
            ->groupBy('municipality_name')
            ->map(fn (Collection $rows): Collection => $rows->keyBy('neighborhood_name'));

        $municipalityReports = $buildingMunicipalities
            ->keys()
            ->merge($unitMunicipalities->keys())
            ->merge($buildingMunicipalityNeighborhoodStats->keys())
            ->merge($unitMunicipalityNeighborhoodStats->keys())
            ->unique()
            ->sort()
            ->map(function (string $municipalityName) use ($buildingMunicipalities, $buildingMunicipalityNeighborhoodStats, $unitMunicipalities, $unitMunicipalityNeighborhoodStats): array {
                $buildingRow = $buildingMunicipalities->get($municipalityName);
                $unitRow = $unitMunicipalities->get($municipalityName);
                $buildingNeighborhoodRows = $buildingMunicipalityNeighborhoodStats->get($municipalityName, collect());
                $unitNeighborhoodRows = $unitMunicipalityNeighborhoodStats->get($municipalityName, collect());

                $neighborhoodRows = $buildingNeighborhoodRows
                    ->keys()
                    ->merge($unitNeighborhoodRows->keys())
                    ->unique()
                    ->sort()
                    ->map(function (string $neighborhoodName) use ($buildingNeighborhoodRows, $unitNeighborhoodRows): array {
                        $buildingNeighborhood = $buildingNeighborhoodRows->get($neighborhoodName);
                        $unitNeighborhood = $unitNeighborhoodRows->get($neighborhoodName);

                        return [
                            'name' => $neighborhoodName,
                            'assessed' => (int) ($buildingNeighborhood->assessed_buildings ?? 0),
                            'units' => (int) ($unitNeighborhood->units ?? 0),
                            'destroyed' => (int) ($unitNeighborhood->destroyed_units ?? $buildingNeighborhood->destroyed_buildings ?? 0),
                        ];
                    })
                    ->values();

                return [
                    'name' => $municipalityName,
                    'summary' => [
                        'assessed' => (int) ($buildingRow->assessed_buildings ?? 0),
                        'units' => (int) ($unitRow->units ?? 0),
                        'destroyed' => (int) ($unitRow->destroyed_units ?? $buildingRow->destroyed_buildings ?? 0),
                    ],
                    'chart' => [
                        (int) ($unitRow->destroyed_units ?? $buildingRow->destroyed_buildings ?? 0),
                        (int) ($unitRow->partially_damaged_units ?? $buildingRow->partially_damaged_buildings ?? 0),
                        (int) ($unitRow->committee_review_units ?? $buildingRow->committee_review_buildings ?? 0),
                        (int) ($unitRow->unclassified_units ?? 0),
                    ],
                    'neighborhoods' => $neighborhoodRows,
                ];
            })
            ->values();

        $buildingMunicipalityReports = $buildingMunicipalities
            ->keys()
            ->merge($buildingMunicipalityNeighborhoodStats->keys())
            ->unique()
            ->sort()
            ->map(function (string $municipalityName) use ($buildingMunicipalities, $buildingMunicipalityNeighborhoodStats): array {
                $buildingRow = $buildingMunicipalities->get($municipalityName);
                $buildingNeighborhoodRows = $buildingMunicipalityNeighborhoodStats->get($municipalityName, collect());

                return [
                    'name' => $municipalityName,
                    'summary' => [
                        'assessed' => (int) ($buildingRow->assessed_buildings ?? 0),
                        'destroyed' => (int) ($buildingRow->destroyed_buildings ?? 0),
                        'partial' => (int) ($buildingRow->partially_damaged_buildings ?? 0),
                        'committee' => (int) ($buildingRow->committee_review_buildings ?? 0),
                    ],
                    'chart' => [
                        (int) ($buildingRow->destroyed_buildings ?? 0),
                        (int) ($buildingRow->partially_damaged_buildings ?? 0),
                        (int) ($buildingRow->committee_review_buildings ?? 0),
                    ],
                    'neighborhoods' => $buildingNeighborhoodRows
                        ->keys()
                        ->sort()
                        ->map(function (string $neighborhoodName) use ($buildingNeighborhoodRows): array {
                            $buildingNeighborhood = $buildingNeighborhoodRows->get($neighborhoodName);

                            return [
                                'name' => $neighborhoodName,
                                'assessed' => (int) ($buildingNeighborhood->assessed_buildings ?? 0),
                                'destroyed' => (int) ($buildingNeighborhood->destroyed_buildings ?? 0),
                                'partial' => (int) ($buildingNeighborhood->partially_damaged_buildings ?? 0),
                                'committee' => (int) ($buildingNeighborhood->committee_review_buildings ?? 0),
                            ];
                        })
                        ->values(),
                ];
            })
            ->values();

        $unitMunicipalityReports = $unitMunicipalities
            ->keys()
            ->merge($unitMunicipalityNeighborhoodStats->keys())
            ->unique()
            ->sort()
            ->map(function (string $municipalityName) use ($unitMunicipalities, $unitMunicipalityNeighborhoodStats): array {
                $unitRow = $unitMunicipalities->get($municipalityName);
                $unitNeighborhoodRows = $unitMunicipalityNeighborhoodStats->get($municipalityName, collect());

                return [
                    'name' => $municipalityName,
                    'summary' => [
                        'units' => (int) ($unitRow->units ?? 0),
                        'destroyed' => (int) ($unitRow->destroyed_units ?? 0),
                        'partial' => (int) ($unitRow->partially_damaged_units ?? 0),
                        'committee' => (int) ($unitRow->committee_review_units ?? 0),
                        'unclassified' => (int) ($unitRow->unclassified_units ?? 0),
                    ],
                    'chart' => [
                        (int) ($unitRow->destroyed_units ?? 0),
                        (int) ($unitRow->partially_damaged_units ?? 0),
                        (int) ($unitRow->committee_review_units ?? 0),
                        (int) ($unitRow->unclassified_units ?? 0),
                    ],
                    'neighborhoods' => $unitNeighborhoodRows
                        ->keys()
                        ->sort()
                        ->map(function (string $neighborhoodName) use ($unitNeighborhoodRows): array {
                            $unitNeighborhood = $unitNeighborhoodRows->get($neighborhoodName);

                            return [
                                'name' => $neighborhoodName,
                                'units' => (int) ($unitNeighborhood->units ?? 0),
                                'destroyed' => (int) ($unitNeighborhood->destroyed_units ?? 0),
                                'partial' => (int) ($unitNeighborhood->partially_damaged_units ?? 0),
                                'committee' => (int) ($unitNeighborhood->committee_review_units ?? 0),
                                'unclassified' => (int) ($unitNeighborhood->unclassified_units ?? 0),
                            ];
                        })
                        ->values(),
                ];
            })
            ->values();

        $mapPoints = Building::query()
            ->select(['building_name', 'neighborhood', 'latitude', 'longitude', 'building_damage_status'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->limit(75)
            ->get()
            ->map(fn (Building $building): array => [
                'lat' => (float) $building->latitude,
                'lng' => (float) $building->longitude,
                'title' => $building->building_name ?: ($building->neighborhood ?: 'مبنى بدون اسم'),
                'status' => $building->building_damage_status ?: 'unclassified',
            ]);

        $totalUnits = max((int) ($unitStats->total_units ?? 0), 1);
        $safetyStats = [
            'destroyed' => $this->percentage((int) ($unitStats->fully_damaged ?? 0), $totalUnits),
            'support_needed' => $this->percentage((int) ($unitStats->support_needed ?? 0), $totalUnits),
            'habitable' => $this->percentage((int) ($unitStats->habitable ?? 0), $totalUnits),
        ];

        $hudDashboardData = $this->buildHudDashboardData($request);

        return View::make('damage-assessment::dashboard.hud', [
            'summaryStats' => $hudDashboardData['summaryStats'],
            'buildingDamageChart' => $hudDashboardData['buildingDamageChart'],
            'damageChart' => $hudDashboardData['damageChart'],
            'safetyStats' => $hudDashboardData['safetyStats'],
            'municipalityReports' => $hudDashboardData['municipalityReports'],
            'buildingMunicipalityReports' => $hudDashboardData['buildingMunicipalityReports'],
            'unitMunicipalityReports' => $hudDashboardData['unitMunicipalityReports'],
            'mapPoints' => $mapPoints,
            'buildingLayerUrl' => $buildingLayerUrl,
            'governoratesBoundaryLayerUrl' => $governoratesBoundaryLayerUrl,
            'neighborhoodsBoundaryLayerUrl' => $neighborhoodsBoundaryLayerUrl,
            'token' => $token,
        ]);
    }

    public function hudStats(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->buildHudDashboardData($request));
    }

    public function hudBuildingUnits(HudBuildingUnitsRequest $request): \Illuminate\Http\JsonResponse
    {
        $buildingGlobalId = (string) $request->string('building_globalid');

        $units = HousingUnit::query()
            ->where('parentglobalid', $buildingGlobalId)
            ->orderBy('objectid')
            ->get([
                'objectid',
                'globalid',
                'unit_owner',
                'housing_unit_number',
                'q_9_3_1_first_name',
                'q_9_3_2_second_name__father',
                'q_9_3_4_last_name',
            ])
            ->map(function (HousingUnit $unit): array {
                $ownerName = trim((string) ($unit->unit_owner ?: $unit->full_name));

                if ($ownerName === '') {
                    $unitNumber = trim((string) $unit->housing_unit_number);
                    $ownerName = $unitNumber !== ''
                        ? 'وحدة '.$unitNumber
                        : 'وحدة '.($unit->objectid ?: $unit->globalid);
                }

                return [
                    'id' => (string) $unit->globalid,
                    'text' => $ownerName,
                ];
            })
            ->filter(fn (array $unit): bool => $unit['id'] !== '')
            ->values();

        return response()->json([
            'results' => $units,
        ]);
    }

    private function buildHudDashboardData(Request $request): array
    {
        $buildingStats = $this->hudBuildingQuery($request)
            ->selectRaw("
                COUNT(*) as total_buildings,
                COALESCE(SUM(CASE WHEN field_status = 'COMPLETED' THEN 1 ELSE 0 END), 0) as assessed_buildings,
                COALESCE(SUM(CASE WHEN building_damage_status = 'fully_damaged' THEN 1 ELSE 0 END), 0) as fully_damaged_buildings,
                COALESCE(SUM(CASE WHEN building_damage_status = 'partially_damaged' THEN 1 ELSE 0 END), 0) as partially_damaged_buildings,
                COALESCE(SUM(CASE WHEN building_damage_status = 'committee_review' THEN 1 ELSE 0 END), 0) as committee_review_buildings,
                COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(assessment_obstacle, ''))) = 'yes' OR LOWER(TRIM(COALESCE(security_situation, ''))) = 'unsafe' THEN 1 ELSE 0 END), 0) as obstacle_buildings,
                COALESCE(SUM(CAST(building_debris_qty AS DECIMAL(15, 2))), 0) as rubble_quantity
            ")
            ->first();

        $unitStats = $this->hudHousingUnitQuery($request)
            ->selectRaw("
                COUNT(*) as total_units,
                COALESCE(SUM(CASE WHEN unit_damage_status = 'fully_damaged2' THEN 1 ELSE 0 END), 0) as fully_damaged,
                COALESCE(SUM(CASE WHEN unit_damage_status = 'partially_damaged2' THEN 1 ELSE 0 END), 0) as partially_damaged,
                COALESCE(SUM(CASE WHEN unit_damage_status = 'committee_review2' THEN 1 ELSE 0 END), 0) as committee_review,
                COALESCE(SUM(CASE WHEN unit_damage_status IS NULL OR unit_damage_status = '' THEN 1 ELSE 0 END), 0) as unclassified,
                COALESCE(SUM(CASE WHEN unit_support_needed = 'yes' THEN 1 ELSE 0 END), 0) as support_needed,
                COALESCE(SUM(CASE WHEN is_the_housing_unit_or_living_habitable = 'yes' THEN 1 ELSE 0 END), 0) as habitable
            ")
            ->first();

        $buildingMunicipalities = $this->hudBuildingQuery($request)
            ->selectRaw("
                COALESCE(NULLIF(municipalitie, ''), 'غير محدد') as municipality_name,
                COUNT(*) as assessed_buildings,
                COALESCE(SUM(CASE WHEN building_damage_status = 'fully_damaged' THEN 1 ELSE 0 END), 0) as destroyed_buildings,
                COALESCE(SUM(CASE WHEN building_damage_status = 'partially_damaged' THEN 1 ELSE 0 END), 0) as partially_damaged_buildings,
                COALESCE(SUM(CASE WHEN building_damage_status = 'committee_review' THEN 1 ELSE 0 END), 0) as committee_review_buildings,
                COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(assessment_obstacle, ''))) = 'yes' OR LOWER(TRIM(COALESCE(security_situation, ''))) = 'unsafe' THEN 1 ELSE 0 END), 0) as obstacle_buildings
            ")
            ->groupBy('municipality_name')
            ->get()
            ->keyBy('municipality_name');

        $unitMunicipalities = $this->hudHousingUnitQuery($request)
            ->selectRaw("
                COALESCE(NULLIF(municipalitie, ''), 'غير محدد') as municipality_name,
                COUNT(*) as units,
                COALESCE(SUM(CASE WHEN unit_damage_status = 'fully_damaged2' THEN 1 ELSE 0 END), 0) as destroyed_units,
                COALESCE(SUM(CASE WHEN unit_damage_status = 'partially_damaged2' THEN 1 ELSE 0 END), 0) as partially_damaged_units,
                COALESCE(SUM(CASE WHEN unit_damage_status = 'committee_review2' THEN 1 ELSE 0 END), 0) as committee_review_units,
                COALESCE(SUM(CASE WHEN unit_damage_status IS NULL OR unit_damage_status = '' THEN 1 ELSE 0 END), 0) as unclassified_units
            ")
            ->groupBy('municipality_name')
            ->get()
            ->keyBy('municipality_name');

        $buildingMunicipalityNeighborhoodStats = $this->hudBuildingQuery($request)
            ->selectRaw("
                COALESCE(NULLIF(municipalitie, ''), 'غير محدد') as municipality_name,
                COALESCE(NULLIF(neighborhood, ''), 'غير محدد') as neighborhood_name,
                COUNT(*) as assessed_buildings,
                COALESCE(SUM(CASE WHEN building_damage_status = 'fully_damaged' THEN 1 ELSE 0 END), 0) as destroyed_buildings,
                COALESCE(SUM(CASE WHEN building_damage_status = 'partially_damaged' THEN 1 ELSE 0 END), 0) as partially_damaged_buildings,
                COALESCE(SUM(CASE WHEN building_damage_status = 'committee_review' THEN 1 ELSE 0 END), 0) as committee_review_buildings,
                COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(assessment_obstacle, ''))) = 'yes' OR LOWER(TRIM(COALESCE(security_situation, ''))) = 'unsafe' THEN 1 ELSE 0 END), 0) as obstacle_buildings
            ")
            ->groupBy('municipality_name', 'neighborhood_name')
            ->get()
            ->groupBy('municipality_name')
            ->map(fn (Collection $rows): Collection => $rows->keyBy('neighborhood_name'));

        $unitMunicipalityNeighborhoodStats = $this->hudHousingUnitQuery($request)
            ->selectRaw("
                COALESCE(NULLIF(municipalitie, ''), 'غير محدد') as municipality_name,
                COALESCE(NULLIF(neighborhood, ''), 'غير محدد') as neighborhood_name,
                COUNT(*) as units,
                COALESCE(SUM(CASE WHEN unit_damage_status = 'fully_damaged2' THEN 1 ELSE 0 END), 0) as destroyed_units,
                COALESCE(SUM(CASE WHEN unit_damage_status = 'partially_damaged2' THEN 1 ELSE 0 END), 0) as partially_damaged_units,
                COALESCE(SUM(CASE WHEN unit_damage_status = 'committee_review2' THEN 1 ELSE 0 END), 0) as committee_review_units,
                COALESCE(SUM(CASE WHEN unit_damage_status IS NULL OR unit_damage_status = '' THEN 1 ELSE 0 END), 0) as unclassified_units
            ")
            ->groupBy('municipality_name', 'neighborhood_name')
            ->get()
            ->groupBy('municipality_name')
            ->map(fn (Collection $rows): Collection => $rows->keyBy('neighborhood_name'));

        $municipalityReports = $buildingMunicipalities
            ->keys()
            ->merge($unitMunicipalities->keys())
            ->merge($buildingMunicipalityNeighborhoodStats->keys())
            ->merge($unitMunicipalityNeighborhoodStats->keys())
            ->unique()
            ->sort()
            ->map(function (string $municipalityName) use ($buildingMunicipalities, $buildingMunicipalityNeighborhoodStats, $unitMunicipalities, $unitMunicipalityNeighborhoodStats): array {
                $buildingRow = $buildingMunicipalities->get($municipalityName);
                $unitRow = $unitMunicipalities->get($municipalityName);
                $buildingNeighborhoodRows = $buildingMunicipalityNeighborhoodStats->get($municipalityName, collect());
                $unitNeighborhoodRows = $unitMunicipalityNeighborhoodStats->get($municipalityName, collect());

                $neighborhoodRows = $buildingNeighborhoodRows
                    ->keys()
                    ->merge($unitNeighborhoodRows->keys())
                    ->unique()
                    ->sort()
                    ->map(function (string $neighborhoodName) use ($buildingNeighborhoodRows, $unitNeighborhoodRows): array {
                        $buildingNeighborhood = $buildingNeighborhoodRows->get($neighborhoodName);
                        $unitNeighborhood = $unitNeighborhoodRows->get($neighborhoodName);

                        return [
                            'name' => $neighborhoodName,
                            'assessed' => (int) ($buildingNeighborhood->assessed_buildings ?? 0),
                            'units' => (int) ($unitNeighborhood->units ?? 0),
                            'destroyed' => (int) ($unitNeighborhood->destroyed_units ?? $buildingNeighborhood->destroyed_buildings ?? 0),
                        ];
                    })
                    ->values();

                return [
                    'name' => $municipalityName,
                    'summary' => [
                        'assessed' => (int) ($buildingRow->assessed_buildings ?? 0),
                        'units' => (int) ($unitRow->units ?? 0),
                        'destroyed' => (int) ($unitRow->destroyed_units ?? $buildingRow->destroyed_buildings ?? 0),
                    ],
                    'chart' => [
                        (int) ($unitRow->destroyed_units ?? $buildingRow->destroyed_buildings ?? 0),
                        (int) ($unitRow->partially_damaged_units ?? $buildingRow->partially_damaged_buildings ?? 0),
                        (int) ($unitRow->committee_review_units ?? $buildingRow->committee_review_buildings ?? 0),
                        (int) ($unitRow->unclassified_units ?? 0),
                    ],
                    'neighborhoods' => $neighborhoodRows,
                ];
            })
            ->values();

        $buildingMunicipalityReports = $buildingMunicipalities
            ->keys()
            ->merge($buildingMunicipalityNeighborhoodStats->keys())
            ->unique()
            ->sort()
            ->map(function (string $municipalityName) use ($buildingMunicipalities, $buildingMunicipalityNeighborhoodStats): array {
                $buildingRow = $buildingMunicipalities->get($municipalityName);
                $buildingNeighborhoodRows = $buildingMunicipalityNeighborhoodStats->get($municipalityName, collect());

                return [
                    'name' => $municipalityName,
                    'summary' => [
                        'assessed' => (int) ($buildingRow->assessed_buildings ?? 0),
                        'destroyed' => (int) ($buildingRow->destroyed_buildings ?? 0),
                        'partial' => (int) ($buildingRow->partially_damaged_buildings ?? 0),
                        'committee' => (int) ($buildingRow->committee_review_buildings ?? 0),
                        'obstacle' => (int) ($buildingRow->obstacle_buildings ?? 0),
                    ],
                    'chart' => [
                        (int) ($buildingRow->destroyed_buildings ?? 0),
                        (int) ($buildingRow->partially_damaged_buildings ?? 0),
                        (int) ($buildingRow->committee_review_buildings ?? 0),
                        (int) ($buildingRow->obstacle_buildings ?? 0),
                    ],
                    'neighborhoods' => $buildingNeighborhoodRows
                        ->keys()
                        ->sort()
                        ->map(function (string $neighborhoodName) use ($buildingNeighborhoodRows): array {
                            $buildingNeighborhood = $buildingNeighborhoodRows->get($neighborhoodName);

                            return [
                                'name' => $neighborhoodName,
                                'assessed' => (int) ($buildingNeighborhood->assessed_buildings ?? 0),
                                'destroyed' => (int) ($buildingNeighborhood->destroyed_buildings ?? 0),
                                'partial' => (int) ($buildingNeighborhood->partially_damaged_buildings ?? 0),
                                'committee' => (int) ($buildingNeighborhood->committee_review_buildings ?? 0),
                                'obstacle' => (int) ($buildingNeighborhood->obstacle_buildings ?? 0),
                            ];
                        })
                        ->values(),
                ];
            })
            ->values();

        $unitMunicipalityReports = $unitMunicipalities
            ->keys()
            ->merge($unitMunicipalityNeighborhoodStats->keys())
            ->unique()
            ->sort()
            ->map(function (string $municipalityName) use ($unitMunicipalities, $unitMunicipalityNeighborhoodStats): array {
                $unitRow = $unitMunicipalities->get($municipalityName);
                $unitNeighborhoodRows = $unitMunicipalityNeighborhoodStats->get($municipalityName, collect());

                return [
                    'name' => $municipalityName,
                    'summary' => [
                        'units' => (int) ($unitRow->units ?? 0),
                        'destroyed' => (int) ($unitRow->destroyed_units ?? 0),
                        'partial' => (int) ($unitRow->partially_damaged_units ?? 0),
                        'committee' => (int) ($unitRow->committee_review_units ?? 0),
                        'unclassified' => (int) ($unitRow->unclassified_units ?? 0),
                    ],
                    'chart' => [
                        (int) ($unitRow->destroyed_units ?? 0),
                        (int) ($unitRow->partially_damaged_units ?? 0),
                        (int) ($unitRow->committee_review_units ?? 0),
                        (int) ($unitRow->unclassified_units ?? 0),
                    ],
                    'neighborhoods' => $unitNeighborhoodRows
                        ->keys()
                        ->sort()
                        ->map(function (string $neighborhoodName) use ($unitNeighborhoodRows): array {
                            $unitNeighborhood = $unitNeighborhoodRows->get($neighborhoodName);

                            return [
                                'name' => $neighborhoodName,
                                'units' => (int) ($unitNeighborhood->units ?? 0),
                                'destroyed' => (int) ($unitNeighborhood->destroyed_units ?? 0),
                                'partial' => (int) ($unitNeighborhood->partially_damaged_units ?? 0),
                                'committee' => (int) ($unitNeighborhood->committee_review_units ?? 0),
                                'unclassified' => (int) ($unitNeighborhood->unclassified_units ?? 0),
                            ];
                        })
                        ->values(),
                ];
            })
            ->values();

        $totalUnits = max((int) ($unitStats->total_units ?? 0), 1);
        $damageChart = [
            'labels' => ['وحدات مدمرة كلياً', 'وحدات متضررة جزئياً', 'وحدات مراجعة لجنة', 'وحدات غير مصنفة'],
            'data' => [
                (int) ($unitStats->fully_damaged ?? 0),
                (int) ($unitStats->partially_damaged ?? 0),
                (int) ($unitStats->committee_review ?? 0),
                (int) ($unitStats->unclassified ?? 0),
            ],
        ];

        return [
            'summaryStats' => [
                'total_buildings' => (int) ($buildingStats->total_buildings ?? 0),
                'assessed_buildings' => (int) ($buildingStats->assessed_buildings ?? 0),
                'fully_damaged_units' => (int) ($unitStats->fully_damaged ?? 0),
                'rubble_quantity' => (float) ($buildingStats->rubble_quantity ?? 0),
            ],
            'buildingDamageChart' => [
                'labels' => ['مبانٍ مدمرة كلياً', 'مبانٍ متضررة جزئياً', 'مبانٍ مراجعة لجنة', 'يوجد عائق'],
                'data' => [
                    (int) ($buildingStats->fully_damaged_buildings ?? 0),
                    (int) ($buildingStats->partially_damaged_buildings ?? 0),
                    (int) ($buildingStats->committee_review_buildings ?? 0),
                    (int) ($buildingStats->obstacle_buildings ?? 0),
                ],
            ],
            'damageChart' => $damageChart,
            'safetyStats' => [
                'destroyed' => $this->percentage((int) ($unitStats->fully_damaged ?? 0), $totalUnits),
                'support_needed' => $this->percentage((int) ($unitStats->support_needed ?? 0), $totalUnits),
                'habitable' => $this->percentage((int) ($unitStats->habitable ?? 0), $totalUnits),
            ],
            'municipalityReports' => $municipalityReports,
            'buildingMunicipalityReports' => $buildingMunicipalityReports,
            'unitMunicipalityReports' => $unitMunicipalityReports,
            'assessedUnitsTotal' => array_sum($damageChart['data']),
        ];
    }

    private function hudBuildingQuery(Request $request): Builder
    {
        $query = Building::query();
        $this->applyHudBuildingFilters($query, $request);

        return $query;
    }

    private function hudHousingUnitQuery(Request $request): Builder
    {
        $query = HousingUnit::query();

        if ($this->hasHudBuildingFilters($request)) {
            $query->whereIn('parentglobalid', $this->hudBuildingQuery($request)->select('globalid'));
        }

        return $query;
    }

    private function applyHudBuildingFilters(Builder $query, Request $request): void
    {
        foreach (['assignedto', 'field_status', 'building_damage_status', 'municipalitie', 'neighborhood'] as $field) {
            $values = $this->hudFilterValues($request, $field);

            if (count($values) === 1) {
                $query->where($field, $values[0]);
            } elseif (count($values) > 1) {
                $query->whereIn($field, $values);
            }
        }

        if ($request->boolean('security_priority')) {
            $query->where(function (Builder $securityQuery): void {
                $securityQuery
                    ->whereRaw("LOWER(TRIM(COALESCE(assessment_obstacle, ''))) = ?", ['yes'])
                    ->orWhereRaw("LOWER(TRIM(COALESCE(security_situation, ''))) = ?", ['unsafe']);
            });
        }

        if ($request->boolean('has_dispute')) {
            $query->whereRaw("LOWER(TRIM(COALESCE(has_dispute, ''))) = ?", ['yes']);
        }

        if ($request->filled('building_name')) {
            $query->where('building_name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->string('building_name')).'%');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));

            $query->where(function (Builder $searchQuery) use ($search) {
                if (ctype_digit($search)) {
                    $searchQuery->where('objectid', (int) $search);

                    return;
                }

                $searchQuery->where('globalid', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%');
            });
        }

        [$startDate, $endDate] = $this->dashboardDateRange($request);

        if ($startDate !== null) {
            $query->whereDate('end', '>=', $startDate);
        }

        if ($endDate !== null) {
            $query->whereDate('end', '<=', $endDate);
        }
    }

    private function hasHudBuildingFilters(Request $request): bool
    {
        foreach (['assignedto', 'field_status', 'building_damage_status', 'municipalitie', 'neighborhood', 'building_name', 'search', 'security_priority', 'has_dispute', 'from_date', 'to_date'] as $field) {
            if ($request->filled($field) || count($this->hudFilterValues($request, $field)) > 0) {
                return true;
            }
        }

        return false;
    }

    private function hudFilterValues(Request $request, string $field): array
    {
        $value = $request->input($field, []);

        if ($value === []) {
            $value = $request->input($field.'[]', []);
        }

        return collect(is_array($value) ? $value : [$value])
            ->map(fn ($item): string => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function search(Request $request)
    {
        $term = $request->search;

        $results = Building::where('building_name', 'LIKE', "%{$term}%")
            ->orWhereHas('housing_unit', function ($q) use ($term) {
                $q->where('q_9_3_1_first_name', 'LIKE', "%{$term}%");
                $q->where('q_9_3_2_second_name__father', 'LIKE', "%{$term}%");
                $q->where('q_9_3_3_third_name__grandfather', 'LIKE', "%{$term}%");
                $q->where('q_9_3_4_last_name', 'LIKE', "%{$term}%");
                $q->select('q_9_3_1_first_name', 'q_9_3_2_second_name__father', 'q_9_3_3_third_name__grandfather', 'q_9_3_4_last_name');
            })
            ->with('housing_unit')
            ->limit(10)
            ->select('building_name')
            ->get();

        return response()->json($results);
    }

    public function showBuildings(Request $request)
    {
        return $this->renderAssessmentTable(
            modelClass: Building::class,
            globalid: $request->globalid,
            type: 'building_table'
        );
    }

    public function showHousings(Request $request)
    {
        return $this->renderAssessmentTable(
            modelClass: HousingUnit::class,
            globalid: $request->globalid,
            type: 'housing_table'
        );
    }

    public function publicBuildingsMap(Request $request)
    {
        $query = PublicBuildingSurvey::query()->select([
            'id',
            'objectid',
            'building_name',
            'municipalitie',
            'neighborhood',
            'building_damage_status',
        ]);

        $this->applyDashboardMapFilters($query, $request);

        return DataTables::of($query)
            ->editColumn('building_damage_status', function ($row) {
                return match ($row->building_damage_status) {
                    'fully_damaged' => '<span class="badge badge-light-danger fw-bold">'.e(__('ui.damage_dashboard.fully_damaged')).'</span>',
                    'partially_damaged' => '<span class="badge badge-light-success fw-bold">'.e(__('ui.damage_dashboard.partially_damaged')).'</span>',
                    'committee_review' => '<span class="badge badge-light-warning fw-bold">'.e(__('ui.damage_dashboard.committee_review')).'</span>',
                    default => '<span class="badge badge-light">-</span>',
                };
            })
            ->rawColumns(['building_damage_status'])
            ->make(true);
    }

    public function roadFacilitiesMap(Request $request)
    {
        $query = RoadFacilitySurvey::query()->select([
            'id',
            'objectid',
            'str_name',
            'municipalitie',
            'neighborhood',
            'road_damage_level',
        ]);

        $this->applyDashboardMapFilters($query, $request);

        return DataTables::of($query)
            ->editColumn('road_damage_level', function ($row) {
                return match ($row->road_damage_level) {
                    'destroyed' => '<span class="badge badge-light-danger fw-bold">'.e(__('multilingual.damage_dashboard.destroyed')).'</span>',
                    'severe' => '<span class="badge badge-light-danger fw-bold">'.e(__('multilingual.damage_dashboard.severe')).'</span>',
                    'moderate' => '<span class="badge badge-light-warning fw-bold">'.e(__('multilingual.damage_dashboard.moderate')).'</span>',
                    'minor' => '<span class="badge badge-light-success fw-bold">'.e(__('multilingual.damage_dashboard.minor')).'</span>',
                    'No_Damage' => '<span class="badge badge-light-success fw-bold">'.e(__('multilingual.damage_dashboard.no_damage')).'</span>',
                    default => '<span class="badge badge-light">-</span>',
                };
            })
            ->rawColumns(['road_damage_level'])
            ->make(true);
    }

    public function latestStats(Request $request): \Illuminate\Http\JsonResponse
    {
        $buildingQuery = Building::query();
        $this->applyDashboardMapFilters($buildingQuery, $request, '', 'end');

        $housingUnitQuery = HousingUnit::query();
        $this->applyDashboardHousingFilters($housingUnitQuery, $request);

        $buildings = $buildingQuery
            ->selectRaw("COALESCE(SUM(field_status = 'Not_Completed'), 0) as not_completed,
                COALESCE(SUM(field_status = 'COMPLETED'), 0) as completed,
                COALESCE(SUM(field_status NOT IN ('COMPLETED', 'Not_Completed')), 0) as pending,
                COALESCE(SUM(building_damage_status = 'fully_damaged'), 0) as fully_damaged,
                COALESCE(SUM(building_damage_status = 'partially_damaged'), 0) as partially_damaged,
                COALESCE(SUM(building_damage_status = 'committee_review'), 0) as committee_review,
                COALESCE(SUM(security_situation = 'Unsafe'), 0) as security_unsafe,
                COALESCE(SUM(uxo_present = 'yes3'), 0) as uxo,
                COALESCE(SUM(bodies_present = 'yes3'), 0) as bodies,
                COALESCE(SUM(building_debris_exist = 'yes'), 0) as debris")
            ->first();

        $units = $housingUnitQuery
            ->selectRaw("
                COUNT(*) as total_units,
                COALESCE(SUM(unit_damage_status = 'fully_damaged2'), 0) as fully_damaged,
                COALESCE(SUM(unit_damage_status = 'partially_damaged2'), 0) as partially_damaged,
                COALESCE(SUM(unit_damage_status = 'committee_review2'), 0) as committee_review,
                COALESCE(SUM(has_fire = 'yes'), 0) as has_fire,
                COALESCE(SUM(unit_stripping = 'yes'), 0) as has_strip,
                COALESCE(SUM(is_the_housing_unit_or_living_habitable = 'yes'), 0) as habitable,
                COALESCE(SUM(security_situation_unit = 'Unsafe'), 0) as security_unsafe,
                COALESCE(SUM(unit_stripping = 'yes'), 0) as unit_stripping,
                COALESCE(SUM(unit_support_needed = 'yes'), 0) as unit_support_needed")
            ->first();

        return response()->json([
            'buildingStats' => [
                'not_completed' => (int) $buildings->not_completed,
                'completed' => (int) $buildings->completed,
                'pending' => (int) $buildings->pending,
                'fully_damaged' => (int) $buildings->fully_damaged,
                'partially_damaged' => (int) $buildings->partially_damaged,
                'committee_review' => (int) $buildings->committee_review,
                'security_unsafe' => (int) $buildings->security_unsafe,
                'uxo' => (int) $buildings->uxo,
                'bodies' => (int) $buildings->bodies,
                'debris' => (int) $buildings->debris,
            ],
            'unitStats' => [
                'total_units' => (int) $units->total_units,
                'fully_damaged' => (int) $units->fully_damaged,
                'partially_damaged' => (int) $units->partially_damaged,
                'committee_review' => (int) $units->committee_review,
                'has_fire' => (int) $units->has_fire,
                'has_strip' => (int) $units->has_strip,
                'habitable' => (int) $units->habitable,
                'security_unsafe' => (int) $units->security_unsafe,
                'unit_stripping' => (int) $units->unit_stripping,
                'unit_support_needed' => (int) $units->unit_support_needed,
            ],
        ]);
    }

    public function arcgisOptions(Request $request)
    {
        $validated = $request->validate([
            'field' => [
                'required',
                'string',
                Rule::in([
                    'assignedto',
                    'building_name',
                    'field_status',
                    'building_damage_status',
                    'municipalitie',
                    'neighborhood',
                ]),
            ],
        ]);

        $field = $validated['field'];
        $layerUrl = $this->normalizeFeatureLayerUrl((string) config('services.arcgis.buildings_url'));

        try {
            $http = Http::timeout(60);

            if (app()->environment('local')) {
                $http = $http->withoutVerifying();
            }

            $params = [
                'f' => 'json',
                'where' => '1=1',
                'outFields' => $field,
                'returnDistinctValues' => 'true',
                'returnGeometry' => 'false',
                'orderByFields' => $field,
            ];

            $token = app(ArcgisService::class)->getToken();

            if ($token !== '') {
                $params['token'] = $token;
            }

            $response = $http->get($layerUrl.'/query', $params);

            if (! $response->successful()) {
                return response()->json($this->databaseArcgisOptions($field));
            }

            $payload = $response->json();

            if (isset($payload['error'])) {
                return response()->json($this->databaseArcgisOptions($field));
            }

            $results = collect($payload['features'] ?? [])
                ->map(function (array $feature) use ($field) {
                    $attributes = collect($feature['attributes'] ?? []);

                    return $attributes->first(
                        fn ($value, string $key): bool => strcasecmp($key, $field) === 0
                    );
                })
                ->filter(fn ($value) => filled($value))
                ->unique()
                ->values()
                ->map(fn ($value) => [
                    'id' => (string) $value,
                    'text' => (string) $value,
                ]);

            if ($results->isEmpty()) {
                return response()->json($this->databaseArcgisOptions($field));
            }

            return response()->json($results);
        } catch (\Throwable $throwable) {
            report($throwable);

            return response()->json($this->databaseArcgisOptions($field));
        }
    }

    private function databaseArcgisOptions(string $field): Collection
    {
        return Building::query()
            ->whereNotNull($field)
            ->where($field, '!=', '')
            ->distinct()
            ->orderBy($field)
            ->pluck($field)
            ->filter()
            ->values()
            ->map(fn ($value) => [
                'id' => (string) $value,
                'text' => (string) $value,
            ]);
    }

    private function percentage(int $value, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($value / $total) * 100, 1);
    }

    private function normalizeFeatureLayerUrl(string $url): string
    {
        $normalized = rtrim($url, '/');

        if ($normalized === '') {
            return $normalized;
        }

        if (preg_match('/\/\d+$/', $normalized) === 1) {
            return $normalized;
        }

        return $normalized.'/0';
    }

    private function renderAssessmentTable(string $modelClass, ?string $globalid, string $type)
    {
        $arcgis = app(ArcgisService::class);
        $token = $arcgis->getToken();

        $model = $modelClass::where('globalid', $globalid)->first();
        $record = $model?->toArray() ?? [];
        $record = $this->applyAssessmentRecordFallbacks($model, $record);

        if ($model instanceof Building) {
            $record['submission_date'] = $model->end;
            $record['submition_date'] = $model->end;
        }

        $fillable = (new $modelClass)->getFillable();

        $allEdits = collect();

        $search = request()->input('search.value');

        $edits = collect();
        $allEdits = collect();
        $user = request()->user();
        $building = $model instanceof Building
            ? $model
            : Building::query()->where('globalid', $model?->parentglobalid)->first();
        $canEditAssessment = $this->canEditAssessmentForBuilding($user, $building);
        $isAssessmentReadOnly = ! $canEditAssessment;

        if ($globalid) {
            $edits = EditAssessment::with('user')
                ->where('type', $type)
                ->where('global_id', $globalid)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->groupBy('field_name')
                ->map(fn ($group) => $group->first());

            $allEdits = EditAssessment::with('user')
                ->where('type', $type)
                ->where('global_id', $globalid)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->groupBy('field_name');
        }

        $layerId = $arcgis->getLayerId($modelClass);

        $attachments = collect();

        if ($model && $model->objectid && $token) {
            $attachments = collect(
                $arcgis->getAttachments($model->objectid, $layerId, $token)
            );
        }
        $filtersByList = Filter::query()
            ->get(['list_name', 'name', 'label'])
            ->groupBy('list_name')
            ->map(fn (Collection $filters): Collection => $filters->pluck('label', 'name'));
        $assessmentRows = $this->withSummaryAssessmentRows(
            $this->assessmentRowsForDisplay($type, $fillable, $search),
            $type,
            $record,
            $allEdits,
            $search
        );

        return DataTables::of($assessmentRows)
            ->addColumn('rowClass', function ($row) use ($record, $allEdits) {

                $original = $record[$row->name] ?? null;
                $lastEdit = $allEdits->get($row->name)?->first();
                $edited = $lastEdit?->field_value;

                $value = ($edited !== null && $edited !== '') ? $edited : $original;
                $value = trim(strip_tags((string) $value));

                $sizeOfUnit = (float) ($record['damaged_area_m2'] ?? 0);
                $floorNumber = (float) ($record['floor_number'] ?? 0);
                $criteria = (float) ($row->criteria ?? 0);
                $newCriteria = ($sizeOfUnit * $criteria) / 100;

                if ($value == null || $value == '') {

                    return '';
                }

                if (in_array($row->name, ['dm6', 'dm7', 'dm12'], true) && $floorNumber > 0 && is_numeric($value) && (float) $value > 0) {
                    return 'table-danger';
                }

                if (($row->type == 2) && is_numeric($value) && $value > $criteria) {
                    return 'table-danger';
                }

                if (($row->type > 2 && $row->type < 15) || in_array($row->type, [19, 20, 21, 23])) {

                    $columnNames = Assessment::where('type', $row->type)
                        ->pluck('name')
                        ->toArray();

                    $total = collect($columnNames)->sum(function ($column) use ($record, $allEdits) {

                        $lastEdit = $allEdits->get($column)?->first();

                        if ($lastEdit && $lastEdit->field_value !== null && $lastEdit->field_value !== '') {
                            return (float) $lastEdit->field_value;
                        }

                        return (float) ($record[$column] ?? 0);
                    });

                    if ($total > $newCriteria) {

                        return 'table-danger';
                    }
                }

                if (($row->type == 1) && is_numeric($value) && $newCriteria > 0 && $value > $newCriteria) {
                    if (in_array($row->name, ['mt8', 'mt9'], true) && is_numeric($value) && (float) $value == 1) {
                        return '';
                    }

                    return 'table-danger';
                }

                if (in_array($row->type, [15, 16, 17, 18, 22, 24, 25])) {

                    $columnNames = Assessment::where('type', $row->type)
                        ->pluck('name')
                        ->toArray();

                    $total = collect($columnNames)->sum(function ($column) use ($record, $allEdits) {

                        $lastEdit = $allEdits->get($column)?->first();

                        if ($lastEdit && $lastEdit->field_value !== null && $lastEdit->field_value !== '') {
                            return (float) $lastEdit->field_value;
                        }

                        return (float) ($record[$column] ?? 0);
                    });

                    if ($total > $criteria) {
                        return 'table-danger';
                    }
                }

                return '';
            })
            ->addColumn('question', function ($row) {
                return $row->label.'<br>'.$row->hint;
            })
            ->addColumn('summaryValue', function ($row) use ($record, $allEdits, $filtersByList) {
                if ($row->name === 'attachments') {
                    return null;
                }

                $lastEdit = $allEdits->get($row->name, collect())->first();
                $rawValue = $lastEdit?->field_value;

                if ($rawValue === null || $rawValue === '') {
                    $rawValue = $record[$row->name] ?? null;
                }

                if ($rawValue === null || $rawValue === '') {
                    return null;
                }

                return $this->updateValue(
                    $this->filterLabelForAssessmentValue($filtersByList, $row->name, $rawValue)
                );
            })
            ->addColumn('answer', function ($row) use ($record, $allEdits, $canEditAssessment, $isAssessmentReadOnly, $model, $attachments, $token, $arcgis, $layerId, $type, $globalid, $filtersByList) {
                if ($row->name === 'attachments') {
                    if (! $model || ! $model->objectid || ! $token || $attachments->isEmpty()) {
                        return '<span class="text-muted">'.e(__('ui.damage_common.no_attachments')).'</span>';
                    }

                    $html = '<div class="d-flex flex-wrap gap-2">';

                    foreach ($attachments as $a) {
                        $attachmentId = $a['id'] ?? null;

                        if (! $attachmentId) {
                            continue;
                        }

                        $url = $arcgis->buildUrl(
                            $model->objectid,
                            $attachmentId,
                            $layerId,
                            $token
                        );

                        $html .= '
                    <a href="'.e($url).'" target="_blank">
                        <img src="'.e($url).'"
                             style="width:100px;height:100px;object-fit:cover"
                             class="rounded border">
                    </a>
                ';
                    }

                    return $html.'</div>';
                }

                $fieldEdits = $allEdits->get($row->name, collect());
                $lastEdit = $fieldEdits->first();

                $originalRawValue = $record[$row->name] ?? null;
                $editedRawValue = $lastEdit?->field_value;

                $originalValue = $this->filterLabelForAssessmentValue($filtersByList, $row->name, $originalRawValue);
                $editedValue = $this->filterLabelForAssessmentValue($filtersByList, $row->name, $editedRawValue);
                $originalValue = $this->updateValue($originalValue);
                $editedValue = $this->updateValue($editedValue);
                $editedBy = $lastEdit?->user?->name;
                $editedAt = $lastEdit?->updated_at?->format('Y-m-d h:i A');

                $canViewHistory = auth()->user()->hasAnyRole([
                    'Database Officer',
                    'Project Officer',
                    'undp-Project Manager',
                    'QC/QA Engineer',
                    'Legal Auditor',
                    'Auditing Supervisor',
                ]) || $canEditAssessment;

                if ((is_null($originalValue) || $originalValue === '') && $fieldEdits->isEmpty()) {
                    return '<span class="text-muted">-</span>';
                }

                if ($isAssessmentReadOnly && ! $fieldEdits->isEmpty()) {
                    return e($editedValue ?? '-');
                }

                if ($fieldEdits->isEmpty() || ! $canViewHistory) {
                    return e($originalValue ?? '-');
                }

                $historyHtml = '';
                $collapseId = 'history_'.md5($type.'_'.$globalid.'_'.$row->name);

                foreach ($fieldEdits as $edit) {
                    $historyValue = $this->filterLabelForAssessmentValue($filtersByList, $row->name, $edit->field_value);

                    $historyHtml .= '
                <div class="border rounded p-2 mb-2 bg-light-info">
                    <div>
                        <small class="text-muted">'.e(__('ui.damage_common.value')).':</small>
                        <span class="fw-semibold">'.e($historyValue ?? '-').'</span>
                    </div>
                    <div>
                        <small class="text-muted">'.e(__('ui.damage_common.by')).':</small>
                        '.e($edit->user?->name ?? '-').'
                    </div>
                    <div>
                        <small class="text-muted">'.e(__('ui.damage_common.time')).':</small>
                        '.e(optional($edit->updated_at)->format('Y-m-d h:i A') ?? '-').'
                    </div>
                </div>
            ';
                }

                $historyHtml = '
            <div class="mt-3">
                <button class="btn btn-sm btn-light-primary" type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#'.$collapseId.'"
                        aria-expanded="false">
                    '.e(__('ui.damage_common.view_edit_history', ['count' => $fieldEdits->count()])).'
                </button>

                <div class="collapse mt-2" id="'.$collapseId.'">
                    '.$historyHtml.'
                </div>
            </div>
        ';

                $modalHistoryButton = '
            <div class="mt-2">
                <button class="btn btn-sm btn-light-info js-assessment-history"
                        type="button"
                        data-global-id="'.e($globalid).'"
                        data-type="'.e($type).'"
                        data-field-name="'.e($row->name).'">
                    عرض سجل التعديلات
                </button>
            </div>
        ';

                return '
            <div class="audit-edit-card audit-existing-edit-card">
                <div class="mb-2">
                    <small class="text-muted d-block">'.e(__('ui.damage_common.original')).'</small>
                    <span class="text-gray-700 audit-original-value">'.e($originalValue ?? '-').'</span>
                </div>

                <div class="mb-2">
                    <small class="text-warning d-block fw-bold">'.e(__('ui.damage_common.latest_edit')).'</small>
                    <span class="text-gray-900 fw-bold audit-new-value">'.e($editedValue ?? '-').'</span>
                </div>

                <div class="mb-1">
                    <small class="text-info d-block fw-bold">'.e(__('ui.damage_common.editor_name')).'</small>
                    <span class="text-gray-800">'.e($editedBy ?? '-').'</span>
                </div>

                <div>
                    <small class="text-primary d-block fw-bold">'.e(__('ui.damage_common.edit_time')).'</small>
                    <span class="text-gray-600">'.e($editedAt ?? '-').'</span>
                </div>

                '.$modalHistoryButton.$historyHtml.'
            </div>
        ';
            })
            ->addColumn('editAnswer', function ($row) use ($record, $edits, $globalid, $type, $isAssessmentReadOnly) {
                if ($isAssessmentReadOnly) {
                    return;
                }

                if ($row->name === 'attachments') {
                    return;
                }
                $lastEdit = $edits->get($row->name);
                $editedValue = $lastEdit?->field_value;
                $originalValue = $record[$row->name] ?? '';
                $value = ($editedValue !== null && $editedValue !== '') ? $editedValue : $originalValue;

                $filters = Filter::where('list_name', $row->name)->get();

                if ($filters->count() > 0) {
                    $selectedValues = array_filter(array_map('trim', explode(',', (string) $value)));

                    $html = '<select
                    class="form-select form-select-sm form-select-solid inline-edit-select"
                    data-field="'.e($row->name).'"
                    data-globalid="'.e($globalid).'"
                    data-type="'.e($type).'"
                    data-control="select2"
                    data-close-on-select="true"
                    data-placeholder="'.e(__('ui.damage_common.select_option')).'">';

                    $html .= '<option value=""></option>';

                    foreach ($filters as $option) {
                        $selected = in_array($option->name, $selectedValues) ? 'selected' : '';
                        $html .= '<option value="'.e($option->name).'" '.$selected.'>'.e($option->label).'</option>';
                    }

                    $html .= '</select>';

                    return $html;
                }

                return '
                <div class="d-flex gap-2 align-items-center justify-content-center">
                    <input
                        type="text"
                        class="form-control form-control-sm form-control-solid inline-edit-input"
                        value="'.e($value).'"
                        data-field="'.e($row->name).'"
                        data-globalid="'.e($globalid).'"
                        data-type="'.e($type).'"
                    >
                    <button type="button"
                        class="btn btn-sm btn-light-primary inline-save-btn"
                        data-field="'.e($row->name).'"
                        data-globalid="'.e($globalid).'"
                        data-type="'.e($type).'">
                        '.e(__('ui.buttons.save')).'
                    </button>
                </div>
            ';
            })
            ->rawColumns(['answer', 'question', 'editAnswer', 'rowClass'])
            ->make(true);
    }

    private function applyAssessmentRecordFallbacks(?Model $model, array $record): array
    {
        if (! $model instanceof Building) {
            return $record;
        }

        foreach (['assessment_obstacle_info', 'comments_recommendations'] as $field) {
            if (! $this->isBlankAssessmentValue($record[$field] ?? null)) {
                continue;
            }

            $fallbackValue = $model->getAttribute($field.'_v1');

            if (! $this->isBlankAssessmentValue($fallbackValue)) {
                $record[$field] = $fallbackValue;
            }
        }

        return $record;
    }

    private function isBlankAssessmentValue(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    private function sortAssessmentsByFillableOrder($assessmentRows, array $fillable)
    {
        $order = array_flip($fillable);

        return collect($assessmentRows)
            ->sortBy(fn (Assessment $assessment): int => $order[$assessment->name] ?? PHP_INT_MAX)
            ->values();
    }

    private function assessmentRowsForDisplay(string $type, array $fillable, ?string $search): Collection
    {
        $fillableLookup = array_flip($fillable);
        $rows = $this->scopeAssessmentRowsForDisplay(
            Assessment::query()->orderBy('id')->get(),
            $type
        );
        $currentSection = 'عام';
        $displayRows = collect();

        foreach ($rows as $row) {
            if ($this->isAssessmentSectionRow($row, $fillableLookup)) {
                $currentSection = trim((string) $row->label) !== '' ? (string) $row->label : $currentSection;

                continue;
            }

            if (! $this->isDisplayAssessmentRow($row, $fillableLookup)) {
                continue;
            }

            $row->setAttribute('section', $row->name === 'attachments' ? 'المرفقات' : $currentSection);
            $displayRows->push($row);
        }

        if (! empty($search)) {
            $needle = strtolower($search);

            $displayRows = $displayRows
                ->filter(fn (Assessment $row): bool => str_contains(strtolower((string) $row->name.' '.$row->label.' '.$row->hint.' '.$row->getAttribute('section')), $needle))
                ->values();
        }

        return $displayRows;
    }

    private function scopeAssessmentRowsForDisplay(Collection $rows, string $type): Collection
    {
        if ($type === 'housing_table') {
            $housingStartId = $rows->firstWhere('name', 'housing_unit_group')?->id;

            return $rows
                ->filter(fn (Assessment $row): bool => $row->name === 'attachments'
                    || ($housingStartId !== null && $row->id >= $housingStartId))
                ->values();
        }

        $housingStartId = $rows->firstWhere('name', 'housing_unit')?->id
            ?? $rows->firstWhere('name', 'housing_unit_group')?->id;

        return $rows
            ->filter(fn (Assessment $row): bool => $housingStartId === null || $row->id < $housingStartId)
            ->values();
    }

    private function isDisplayAssessmentRow(Assessment $row, array $fillableLookup): bool
    {
        return $row->name === 'attachments' || isset($fillableLookup[$row->name]);
    }

    private function isAssessmentSectionRow(Assessment $row, array $fillableLookup): bool
    {
        return ! $this->isDisplayAssessmentRow($row, $fillableLookup)
            && trim((string) $row->label) !== '';
    }

    private function canEditAssessmentForBuilding($user, ?Building $building): bool
    {
        if (! $user instanceof User || ! $building instanceof Building) {
            return false;
        }

        if ($user->hasAnyRole(['Database Officer', 'Auditing Supervisor'])) {
            return true;
        }

        if ($this->hasTemporaryStatusAssignmentException($user)) {
            return true;
        }

        $assignmentTypes = [];

        if ($user->hasAnyRole(['QC/QA Engineer', 'Engineering Auditor'])) {
            $assignmentTypes[] = 'QC/QA Engineer';
        }

        if ($user->hasRole('Legal Auditor')) {
            $assignmentTypes[] = 'Legal Auditor';
        }

        if ($assignmentTypes === []) {
            return false;
        }

        return AssignedAssessmentUser::query()
            ->where('building_id', $building->objectid)
            ->where('user_id', $user->id)
            ->whereIn('type', $assignmentTypes)
            ->exists();
    }

    private function hasTemporaryStatusAssignmentException(User $user): bool
    {
        return in_array(trim($user->name), self::TEMPORARY_HIDDEN_AUDIT_ACTION_USER_NAMES, true)
            || in_array(trim((string) $user->id_no), self::TEMPORARY_HIDDEN_AUDIT_ACTION_USER_ID_NUMBERS, true);
    }

    private function withSummaryAssessmentRows($assessmentRows, string $type, array $record, $allEdits, ?string $search)
    {
        $assessmentRows = collect($assessmentRows);
        $existingNames = $assessmentRows->pluck('name')->filter()->flip();

        foreach ($this->summaryFallbackFields($type, $record, $allEdits) as $field => $label) {
            if ($existingNames->has($field)) {
                continue;
            }

            if ($search && ! str_contains(strtolower($field.' '.$label), strtolower($search))) {
                continue;
            }

            $row = new Assessment;
            $row->forceFill([
                'name' => $field,
                'label' => $label,
                'hint' => '',
                'type' => 0,
                'criteria' => 0,
            ]);

            $assessmentRows->push($row);
        }

        return $assessmentRows;
    }

    private function summaryFallbackFields(string $type, array $record, $allEdits): array
    {
        if ($type === 'building_table') {
            return [
                'objectid' => 'رقم المبنى',
                'building_name' => 'اسم المبنى',
                'floor_nos' => 'عدد الطوابق',
                'ground_floor_area__m2' => 'مساحة الطابق الارضي',
                'floor_area_m2' => 'مساحة الطابق المتكرر',
                'building_roof_type' => 'نوع سطح المبنى',
                'concrete_area' => 'مساحة الباطون',
                'scorite_area' => 'مساحة الصاج',
                'comments_recommendations' => 'ملاحظات المهندس',
            ];
        }

        if ($type === 'housing_table') {
            $damageEdit = collect($allEdits->get('unit_damage_status', collect()))->first();
            $damageStatus = strtolower(trim((string) ($damageEdit?->field_value ?? $record['unit_damage_status'] ?? '')));

            $normalizedDamageStatus = str($damageStatus)->replace(['-', ' '], '_')->squish()->toString();
            if (in_array($normalizedDamageStatus, ['totally', 'total', 'totally_damaged', 'total_damage', 'fully_damaged', 'fully_damaged2'], true)
                || str_contains($normalizedDamageStatus, 'totally')
                || str_contains($normalizedDamageStatus, 'fully')) {
                return [
                    'unit_owner' => 'اسم مالك الوحدة',
                    'damaged_area_m2' => 'مساحة الوحدة',
                    'floor_number' => 'رقم الطابق',
                    'housing_unit_number' => 'رقم الوحدة',
                    'external_finishing_of_the_unit' => 'التشطيب الخارجي',
                    'internal_finishing_of_the_unit' => 'التشطيب الداخلي',
                ];
            }

            return [
                'unit_owner' => 'اسم مالك الوحدة',
                'damaged_area_m2' => 'مساحة الوحدة',
                'floor_number' => 'رقم الطابق',
                'housing_unit_number' => 'رقم الوحدة',
                'external_finishing_of_the_unit' => 'التشطيب الخارجي',
                'internal_finishing_of_the_unit' => 'التشطيب الداخلي',
                'reh_kitchen' => 'تأهيل مطبخ',
                'reh_bathroom' => 'تأهيل حمام',
                'is_the_housing_unit_or_living_habitable' => 'هل الوحدة مناسبة للسكن',
            ];
        }

        if ($type === 'building_table') {
            return [
                'floor_nos' => 'عدد الطوابق',
                'ground_floor_area__m2' => 'مساحة الطابق الأرضي',
                'floor_area_m2' => 'مساحة الطابق المتكرر',
                'building_roof_type' => 'نوع سطح المبنى',
                'concrete_area' => 'مساحة الباطون',
                'aspestos_area' => 'مساحة الصاج',
            ];
        }

        if ($type !== 'housing_table') {
            return [];
        }

        $damageEdit = collect($allEdits->get('unit_damage_status', collect()))->first();
        $damageStatus = strtolower(trim((string) ($damageEdit?->field_value ?? $record['unit_damage_status'] ?? '')));

        if (str_contains($damageStatus, 'total')) {
            return [
                'unit_owner' => 'مالك الوحدة',
                'damaged_area_m2' => 'مساحة الوحدة',
                'external_finishing_of_the_unit' => 'تشطيب الوحدة من الخارج',
                'internal_finishing_of_the_unit' => 'تشطيب الوحدة من الداخل',
                'floor_number' => 'رقم الطابق',
                'housing_unit_number' => 'رقم الوحدة',
            ];
        }

        return [
            'unit_owner' => 'مالك الوحدة',
            'damaged_area_m2' => 'مساحة الوحدة',
            'reh_kitchen' => 'تأهيل مطبخ',
            'reh_bathroom' => 'تأهيل حمام',
            'is_the_housing_unit_or_living_habitable' => 'ملائمة للسكن',
            'external_finishing_of_the_unit' => 'تشطيب الوحدة من الخارج',
            'internal_finishing_of_the_unit' => 'تشطيب الوحدة من الداخل',
        ];
    }

    private function filterLabelForAssessmentValue(Collection $filtersByList, string $fieldName, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $filters = $filtersByList->get($fieldName);

        if (! $filters instanceof Collection || $filters->isEmpty()) {
            return $value;
        }

        $values = array_values(array_filter(
            array_map('trim', explode(',', (string) $value)),
            fn (string $item): bool => $item !== ''
        ));

        if ($values === []) {
            return $value;
        }

        return collect($values)
            ->map(fn (string $item): string => (string) ($filters->get($item) ?? $item))
            ->implode(', ');
    }

    private function updateValue($value)
    {

        if ($value == 1 || $value == 2 || $value == 3 || $value == 4) {
            return $value;
        }

        return match ($value) {
            'yes', 'yes1', 'yes2', 'yes3', 'yes4', 'yes5', 'Yes' => __('ui.options.yes'),
            'no', 'no1', 'no2', 'no3', 'no4', 'no5', 'No' => __('ui.options.no'),
            default => $value,
        };
    }

    public function globalSearch(Request $request)
    {
        $search = trim((string) $request->string('search'));

        if (mb_strlen($search) < 2) {
            return response()->json([
                'results' => [],
            ]);
        }

        $buildingResults = Building::query()
            ->select(['globalid', 'objectid', 'building_name', 'owner_name', 'neighborhood'])
            ->where(function ($query) use ($search) {
                $query->where('building_name', 'like', "%{$search}%")
                    ->orWhere('objectid', 'like', "%{$search}%")
                    ->orWhere('owner_name', 'like', "%{$search}%")
                    ->orWhere('neighborhood', 'like', "%{$search}%");
            })
            ->limit(5)
            ->get()
            ->map(function (Building $building): array {
                $subtitleParts = array_values(array_filter([
                    __('ui.search.object_id').': '.$building->objectid,
                    $building->owner_name,
                    $building->neighborhood,
                ]));

                return [
                    'group' => __('ui.search.buildings'),
                    'title' => $building->building_name ?: __('ui.search.unnamed_building'),
                    'subtitle' => implode(' • ', $subtitleParts),
                    'url' => route('assessment.show', $building->globalid),
                    'icon' => 'ki-home',
                ];
            });

        $publicBuildingResults = PublicBuildingSurvey::query()
            ->select(['objectid', 'building_name', 'municipalitie', 'neighborhood'])
            ->where(function ($query) use ($search) {
                $query->where('building_name', 'like', "%{$search}%")
                    ->orWhere('objectid', 'like', "%{$search}%")
                    ->orWhere('municipalitie', 'like', "%{$search}%")
                    ->orWhere('neighborhood', 'like', "%{$search}%");
            })
            ->limit(5)
            ->get()
            ->map(function (PublicBuildingSurvey $survey): array {
                $subtitleParts = array_values(array_filter([
                    __('ui.search.object_id').': '.$survey->objectid,
                    $survey->municipalitie,
                    $survey->neighborhood,
                ]));

                return [
                    'group' => __('ui.search.public_buildings'),
                    'title' => $survey->building_name ?: __('ui.search.unnamed_public_building'),
                    'subtitle' => implode(' • ', $subtitleParts),
                    'url' => route('public-buildings.show', $survey),
                    'icon' => 'ki-office-bag',
                ];
            });

        $roadFacilityResults = RoadFacilitySurvey::query()
            ->select(['objectid', 'str_name', 'municipalitie', 'neighborhood'])
            ->where(function ($query) use ($search) {
                $query->where('str_name', 'like', "%{$search}%")
                    ->orWhere('objectid', 'like', "%{$search}%")
                    ->orWhere('municipalitie', 'like', "%{$search}%")
                    ->orWhere('neighborhood', 'like', "%{$search}%");
            })
            ->limit(5)
            ->get()
            ->map(function (RoadFacilitySurvey $survey): array {
                $subtitleParts = array_values(array_filter([
                    __('ui.search.object_id').': '.$survey->objectid,
                    $survey->municipalitie,
                    $survey->neighborhood,
                ]));

                return [
                    'group' => __('ui.search.road_facilities'),
                    'title' => $survey->str_name ?: __('ui.search.unnamed_road_facility'),
                    'subtitle' => implode(' • ', $subtitleParts),
                    'url' => route('road-facilities.show', $survey),
                    'icon' => 'ki-map',
                ];
            });

        return response()->json([
            'results' => $buildingResults
                ->concat($publicBuildingResults)
                ->concat($roadFacilityResults)
                ->values(),
        ]);
    }

    public function housingUnitsMap(Request $request)
    {
        $fullNameExpression = $this->housingUnitFullNameExpression();

        $query = Building::query()
            ->leftJoin('housing_units', 'housing_units.parentglobalid', '=', 'buildings.globalid')
            ->select([
                'buildings.id',
                'buildings.globalid as building_globalid',
                'buildings.objectid',
                'buildings.building_name as building_name',
                'buildings.neighborhood as neighborhood',
                'housing_units.unit_damage_status',
                DB::raw($fullNameExpression.' as full_name1'),
            ]);

        $this->applyDashboardBuildingUnitTableFilters($query, $request);

        return DataTables::of($query)
            ->filterColumn('full_name1', function ($query, $keyword) use ($fullNameExpression) {
                $query->whereRaw($fullNameExpression.' LIKE ?', ["%{$keyword}%"]);
            })
            ->editColumn('full_name1', function ($row) {
                return $row->full_name1 !== '' ? $row->full_name1 : '-';
            })
            ->editColumn('unit_damage_status', function ($row) {
                return match ($row->unit_damage_status) {
                    'fully_damaged2' => '<span class="badge badge-light-danger fw-bold">'.e(__('ui.damage_dashboard.fully_damaged')).'</span>',
                    'partially_damaged2' => '<span class="badge badge-light-success fw-bold">'.e(__('ui.damage_dashboard.partially_damaged')).'</span>',
                    'committee_review2' => '<span class="badge badge-light-warning fw-bold">'.e(__('ui.damage_dashboard.committee_review')).'</span>',
                    null, '' => '<span class="badge badge-light">'.e(__('multilingual.damage_dashboard.no_units')).'</span>',
                    default => '-',
                };
            })
            ->rawColumns(['unit_damage_status'])
            ->filterColumn('building_name', function ($query, $keyword) {
                $query->where('buildings.building_name', 'like', "%{$keyword}%");
            })
            ->filterColumn('neighborhood', function ($query, $keyword) {
                $query->where('buildings.neighborhood', 'like', "%{$keyword}%");
            })
            ->filterColumn('unit_damage_status', function ($query, $keyword) {
                $query->where('housing_units.unit_damage_status', 'like', "%{$keyword}%");
            })
            ->orderColumn('building_name', function ($query, $order) {
                $query->orderBy('buildings.building_name', $order);
            })
            ->orderColumn('neighborhood', function ($query, $order) {
                $query->orderBy('buildings.neighborhood', $order);
            })
            ->orderColumn('unit_damage_status', function ($query, $order) {
                $query->orderBy('housing_units.unit_damage_status', $order);
            })
            ->orderColumn('full_name1', function ($query, $order) use ($fullNameExpression) {
                $query->orderByRaw($fullNameExpression.' '.$order);
            })
            ->make(true);
    }

    private function housingUnitFullNameExpression(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "TRIM(COALESCE(housing_units.q_9_3_1_first_name, '') || ' ' || COALESCE(housing_units.q_9_3_4_last_name, ''))";
        }

        return "TRIM(CONCAT_WS(' ', housing_units.q_9_3_1_first_name, housing_units.q_9_3_4_last_name))";
    }

    private function dashboardNeighborhoods(): Collection
    {
        return collect()
            ->merge(Building::query()->whereNotNull('neighborhood')->where('neighborhood', '!=', '')->distinct()->pluck('neighborhood'))
            ->merge(PublicBuildingSurvey::query()->whereNotNull('neighborhood')->where('neighborhood', '!=', '')->distinct()->pluck('neighborhood'))
            ->merge(RoadFacilitySurvey::query()->whereNotNull('neighborhood')->where('neighborhood', '!=', '')->distinct()->pluck('neighborhood'))
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    private function dashboardDateRange(Request $request): array
    {
        $requestedPeriod = (string) $request->string('period');
        $period = in_array($requestedPeriod, ['all', 'today', 'week', 'day', 'yesterday'], true)
            ? $requestedPeriod
            : 'all';
        $period = $period === 'yesterday' ? 'day' : $period;

        $today = Carbon::today();
        $startDate = match ($period) {
            'all' => null,
            'week' => $today->copy()->subDays(6)->toDateString(),
            'day' => $today->copy()->subDay()->toDateString(),
            default => $today->toDateString(),
        };
        $endDate = match ($period) {
            'all' => null,
            'day' => $startDate,
            default => $today->toDateString(),
        };

        $fromDateInput = (string) $request->string('from_date');
        $toDateInput = (string) $request->string('to_date');

        if (str_contains($fromDateInput, ' to ') || str_contains($fromDateInput, ' - ')) {
            [$fromDateInput, $rangeEndDate] = preg_split('/\s+(?:to|-)\s+/', $fromDateInput, 2);
            $toDateInput = $toDateInput !== '' ? $toDateInput : $rangeEndDate;
        }

        if ($fromDateInput !== '') {
            $startDate = Carbon::parse($fromDateInput)->toDateString();
        }

        if ($toDateInput !== '') {
            $endDate = Carbon::parse($toDateInput)->toDateString();
        }

        return [$startDate, $endDate, $period];
    }

    private function dashboardPublicBuildingQuery(Request $request): Builder
    {
        $query = PublicBuildingSurvey::query();
        $this->applyDashboardMapFilters($query, $request, '', 'creationdate');

        return $query;
    }

    private function dashboardRoadFacilityQuery(Request $request): Builder
    {
        $query = RoadFacilitySurvey::query();
        $this->applyDashboardMapFilters($query, $request, '', 'creationdate');

        return $query;
    }

    private function dashboardCompletedRoadLengthKilometers(Request $request): float
    {
        $lengthColumn = collect(['shape__length', 'shape_length', 'Shape__Length', 'shape_leng'])
            ->first(fn (string $column): bool => Schema::hasColumn('road_facility_surveys', $column));

        if ($lengthColumn === null) {
            return 0.0;
        }

        $shapeLength = (float) $this->dashboardRoadFacilityQuery($request)
            ->where('field_status', 'COMPLETED')
            ->sum($lengthColumn);

        return $shapeLength * 111;
    }

    private function applyDashboardHousingFilters(Builder $query, Request $request): void
    {
        [$startDate, $endDate] = $this->dashboardDateRange($request);

        if ($startDate !== null) {
            $query->whereDate('building_submit_date', '>=', $startDate);
        }

        if ($endDate !== null) {
            $query->whereDate('building_submit_date', '<=', $endDate);
        }

        if ($request->filled('neighborhood')) {
            $query->whereIn('parentglobalid', Building::query()
                ->select('globalid')
                ->where('neighborhood', (string) $request->string('neighborhood')));
        }
    }

    private function applyDashboardBuildingUnitTableFilters(Builder $query, Request $request): void
    {
        [$startDate, $endDate] = $this->dashboardDateRange($request);

        if ($request->filled('neighborhood')) {
            $query->where('buildings.neighborhood', (string) $request->string('neighborhood'));
        }

        if ($startDate !== null && $endDate !== null) {
            $query->where(function (Builder $dateQuery) use ($startDate, $endDate) {
                $dateQuery
                    ->whereBetween(DB::raw('DATE(housing_units.building_submit_date)'), [$startDate, $endDate]);
            });
        } elseif ($startDate !== null) {
            $query->where(function (Builder $dateQuery) use ($startDate) {
                $dateQuery
                    ->whereDate('housing_units.building_submit_date', '>=', $startDate);
            });
        } elseif ($endDate !== null) {
            $query->where(function (Builder $dateQuery) use ($endDate) {
                $dateQuery
                    ->whereDate('housing_units.building_submit_date', '<=', $endDate);
            });
        }
    }

    private function applyDashboardMapFilters(
        Builder $query,
        Request $request,
        string $tablePrefix = '',
        string $dateColumn = 'creationdate'
    ): void {
        [$startDate, $endDate] = $this->dashboardDateRange($request);

        if ($request->filled('neighborhood')) {
            $query->where($tablePrefix.'neighborhood', (string) $request->string('neighborhood'));
        }

        if ($startDate !== null) {
            $query->whereDate($tablePrefix.$dateColumn, '>=', $startDate);
        }

        if ($endDate !== null) {
            $query->whereDate($tablePrefix.$dateColumn, '<=', $endDate);
        }
    }
}
