<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicBuilding;

use App\Http\Controllers\Controller;
use App\Models\PublicBuildingFilter;
use App\Models\PublicBuildingSurvey;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Yajra\DataTables\Facades\DataTables;

class PublicBuildingController extends Controller
{
    // ================= INDEX =================

    public function index(): View
    {
        $summary = [
            'total_surveys' => PublicBuildingSurvey::query()->count(),
            'total_units' => PublicBuildingSurvey::query()->withCount('units')->get()->sum('units_count'),
            'damaged_buildings' => PublicBuildingSurvey::query()
                ->whereNotNull('building_damage_status')
                ->where('building_damage_status', '!=', '')
                ->count(),
        ];

        $databaseFilterGroups = PublicBuildingFilter::query()
            ->orderBy('list_name')
            ->orderBy('label')
            ->get()
            ->groupBy('list_name');

        $filterGroups = $databaseFilterGroups->isNotEmpty()
            ? $databaseFilterGroups
            : $this->fallbackFilterGroups();

        $filterOptions = [
            'municipalities' => PublicBuildingSurvey::query()->distinct()->orderBy('municipalitie')->pluck('municipalitie')->filter()->values(),
            'neighborhoods' => PublicBuildingSurvey::query()->distinct()->orderBy('neighborhood')->pluck('neighborhood')->filter()->values(),
            'researchers' => PublicBuildingSurvey::query()->distinct()->orderBy('assigned_to')->pluck('assigned_to')->filter()->values(),
        ];

        return view('PublicBuilding.index', compact('summary', 'filterOptions', 'filterGroups'));
    }

    // ================= DATATABLE =================

    public function data(): JsonResponse
    {
        return DataTables::eloquent(PublicBuildingSurvey::query()->withCount('units'))
            ->addColumn('actions', fn ($survey) =>
                '<a href="'.route('public-buildings.show', $survey).'" class="btn btn-light btn-sm">View</a>'
            )
            ->addColumn('assigned_to', fn ($survey) =>
                $survey->assigned_to ?? '-'
            )
            ->rawColumns(['actions'])
            ->toJson();
    }

    // ================= SHOW =================

    public function show(PublicBuildingSurvey $publicBuilding): View
    {
        $publicBuilding->load(['units' => fn ($q) => $q->orderBy('objectid')]);

        return view('PublicBuilding.show', [
            'survey' => $publicBuilding,
            'sections' => $this->buildSurveySections($publicBuilding),
            'unitSections' => $this->buildUnitSections($publicBuilding),
        ]);
    }

    // ================= STATIC SECTIONS =================

    private function buildSurveySections(PublicBuildingSurvey $survey): array
    {
        return [
            [
                'title' => 'General Information',
                'rows' => $this->rowsFromMap($survey, [
                    'objectid' => 'Object ID',
                    'globalid' => 'Global ID',
                    'building_name' => 'Building Name',
                    'assigned_to' => 'Researcher',
                    'governorate' => 'Governorate',
                    'municipalitie' => 'Municipality',
                    'neighborhood' => 'Neighborhood',
                    'building_damage_status' => 'Damage Status',
                ]),
            ],
            [
                'title' => 'Building Details',
                'rows' => $this->rowsFromMap($survey, [
                    'benef_type' => 'Beneficiary Type',
                    'building_roof_type' => 'Roof Type',
                    'ground_floor_use' => 'Ground Floor Use',
                    'comments_recommendations' => 'Comments',
                ]),
            ],
        ];
    }

    private function buildUnitSections(PublicBuildingSurvey $survey): array
    {
        return $survey->units
            ->values()
            ->map(function ($unit, int $i): array {
                return [
                    'title' => 'Unit / Floor '.($i + 1),
                    'rows' => $this->rowsFromMap($unit, [
                        'unit_name' => 'Unit Name',
                        'floor_number' => 'Floor Number',
                        'housing_unit_number' => 'Housing Unit Number',
                        'occupied' => 'Occupied',
                        'unit_ownership' => 'Ownership',
                        'damaged_area_m2' => 'Damaged Area',
                        'rentee_resident_full_name' => 'Resident Name',
                        'rentee_mobile_number' => 'Mobile',
                        'general_notes_about_the_unit' => 'Notes',
                    ]),
                ];
            })
            ->filter(fn ($section) => $section['rows'] !== [])
            ->values()
            ->all();
    }

    // ================= HELPERS =================

    private function rowsFromMap(object $record, array $map): array
    {
        return collect($map)
            ->map(function ($label, $field) use ($record) {
                $value = data_get($record, $field);

                if ($value === null || $value === '') {
                    return null;
                }

                return [
                    'question' => $label,
                    'answer' => is_array($value) ? implode(', ', $value) : $value,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function filteredQuery(): Builder
    {
        return PublicBuildingSurvey::query()->withCount('units');
    }

    private function fallbackFilterGroups(): Collection
    {
        return collect([
            'building_damage_status' => collect([
                (object)['name' => 'fully_damaged', 'label' => 'Fully Damaged'],
                (object)['name' => 'partially_damaged', 'label' => 'Partially Damaged'],
            ]),
        ]);
    }
}