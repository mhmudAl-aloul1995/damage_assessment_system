<?php

declare(strict_types=1);

namespace App\Modules\DamageAssessment\Http\Controllers\Surveys\CsoSurveys;

use App\Http\Controllers\Controller;
use App\Models\CsoSurvey;
use App\Models\CsoSurveyOrganization;
use App\Models\CsoSurveyUnit;
use App\Support\Forms\CsoSurveyLayout;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CsoSurveyController extends Controller
{
    public function index(): View
    {
        return view('damage-assessment::surveys.cso.index', [
            'summary' => [
                'total_surveys' => CsoSurvey::query()->count(),
                'total_organizations' => CsoSurveyOrganization::query()->count(),
                'total_units' => CsoSurveyUnit::query()->count(),
                'damaged_buildings' => CsoSurvey::query()
                    ->whereNotNull('building_damage_status')
                    ->where('building_damage_status', '!=', '')
                    ->count(),
            ],
            'filterOptions' => [
                'municipalities' => CsoSurvey::query()->distinct()->orderBy('municipalitie')->pluck('municipalitie')->filter()->values(),
                'neighborhoods' => CsoSurvey::query()->distinct()->orderBy('neighborhood')->pluck('neighborhood')->filter()->values(),
                'researchers' => CsoSurvey::query()->distinct()->orderBy('assignedto')->pluck('assignedto')->filter()->values(),
                'damageStatuses' => CsoSurvey::query()->distinct()->orderBy('building_damage_status')->pluck('building_damage_status')->filter()->values(),
                'operationalStatuses' => CsoSurvey::query()->distinct()->orderBy('operational_status')->pluck('operational_status')->filter()->values(),
                'min_creationdate' => optional(CsoSurvey::query()->whereNotNull('creationdate')->min('creationdate'))?->format('Y-m-d'),
                'max_creationdate' => optional(CsoSurvey::query()->whereNotNull('creationdate')->max('creationdate'))?->format('Y-m-d'),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        return DataTables::eloquent($this->filteredQuery($request)->withCount(['organizations', 'units']))
            ->addColumn('actions', fn (CsoSurvey $survey): string => '<a href="'.route('cso-surveys.show', $survey).'" class="btn btn-light btn-sm">View</a>')
            ->editColumn('creationdate', fn (CsoSurvey $survey): string => $survey->creationdate?->format('Y-m-d H:i') ?? '-')
            ->editColumn('building_damage_status', fn (CsoSurvey $survey): string => '<span class="badge badge-light-danger">'.e($survey->building_damage_status ?? '-').'</span>')
            ->addColumn('assignedto', fn (CsoSurvey $survey): string => $survey->assignedto ?? '-')
            ->rawColumns(['actions', 'building_damage_status'])
            ->toJson();
    }

    public function show(CsoSurvey $csoSurvey): View
    {
        $csoSurvey->load([
            'organizations' => fn ($query) => $query->orderBy('objectid'),
            'units' => fn ($query) => $query->orderBy('objectid'),
        ]);

        return view('damage-assessment::surveys.cso.show', [
            'survey' => $csoSurvey,
            'sections' => $this->surveySections($csoSurvey),
            'organizationSections' => $this->childSections(
                records: $csoSurvey->organizations,
                sections: CsoSurveyLayout::repeatSections('CSO_Organizations'),
                titlePrefix: 'CSO Organization',
            ),
            'unitSections' => $this->childSections(
                records: $csoSurvey->units,
                sections: CsoSurveyLayout::repeatSections('Unit_Information'),
                titlePrefix: 'Unit Information',
            ),
        ]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = CsoSurvey::query();

        foreach (['municipalitie', 'neighborhood', 'assignedto', 'building_damage_status', 'operational_status'] as $field) {
            $values = $this->requestValues($request, $field);

            if ($values !== []) {
                $query->whereIn($field, $values);
            }
        }

        if ($request->filled('from_date')) {
            $query->whereDate('creationdate', '>=', $request->date('from_date')->toDateString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('creationdate', '<=', $request->date('to_date')->toDateString());
        }

        if ($request->boolean('damaged_only')) {
            $query->whereNotNull('building_damage_status')
                ->where('building_damage_status', '!=', '');
        }

        if ($request->boolean('with_organizations')) {
            $query->has('organizations');
        }

        if ($request->boolean('with_units')) {
            $query->has('units');
        }

        $search = trim((string) $request->input('search.value', $request->input('q', '')));

        if ($search !== '') {
            $query->where(function (Builder $nested) use ($search): void {
                $nested
                    ->where('organization_name', 'like', '%'.$search.'%')
                    ->orWhere('building_name', 'like', '%'.$search.'%')
                    ->orWhere('municipalitie', 'like', '%'.$search.'%')
                    ->orWhere('neighborhood', 'like', '%'.$search.'%')
                    ->orWhere('objectid', 'like', '%'.$search.'%')
                    ->orWhere('building_damage_status', 'like', '%'.$search.'%')
                    ->orWhere('operational_status', 'like', '%'.$search.'%')
                    ->orWhere('assignedto', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }

    private function surveySections(CsoSurvey $survey): array
    {
        $repeatSectionNames = array_merge(
            CsoSurveyLayout::repeatSectionNames('CSO_Organizations'),
            CsoSurveyLayout::repeatSectionNames('Unit_Information'),
            ['CSO_Organizations', 'Unit_Information'],
        );

        return collect(CsoSurveyLayout::sections())
            ->reject(fn (array $section): bool => in_array($section['name'] ?? '', $repeatSectionNames, true))
            ->map(fn (array $section): array => [
                'title' => $section['label'] ?: $section['name'],
                'name' => $section['name'],
                'rows' => $this->rowsFromLayoutFields($survey, $section['fields'] ?? []),
            ])
            ->values()
            ->all();
    }

    private function childSections($records, array $sections, string $titlePrefix): array
    {
        return $records
            ->values()
            ->flatMap(fn (object $record, int $index): array => collect($sections)
                ->map(fn (array $section): array => [
                    'title' => $titlePrefix.' '.($index + 1).' - '.($section['label'] ?: $section['name']),
                    'name' => $section['name'],
                    'rows' => $this->rowsFromLayoutFields($record, $section['fields'] ?? []),
                ])
                ->values()
                ->all())
            ->values()
            ->all();
    }

    private function rowsFromLayoutFields(object $record, array $fields): array
    {
        return collect($fields)
            ->reject(fn (array $field): bool => ($field['type'] ?? null) === 'calculate')
            ->map(function (array $field) use ($record): array {
                $value = CsoSurveyLayout::value($record, $field['name']);
                $answer = CsoSurveyLayout::displayValue($value, $field);

                return [
                    'question' => $field['label'] ?: $field['name'],
                    'answer' => $answer ?? $this->emptyAnswerText($field),
                    'empty' => $answer === null,
                ];
            })
            ->values()
            ->all();
    }

    private function requestValues(Request $request, string $key): array
    {
        $value = $request->input($key);

        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        return collect($value)
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function emptyAnswerText(array $field): string
    {
        return ($field['type'] ?? null) === 'image' ? 'لا يوجد مرفق' : 'لا يوجد جواب';
    }
}
