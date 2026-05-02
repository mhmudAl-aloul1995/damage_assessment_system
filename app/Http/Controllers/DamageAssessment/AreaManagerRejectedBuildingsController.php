<?php

namespace App\Http\Controllers\DamageAssessment;

use App\Http\Controllers\Controller;
use App\Models\AssessmentStatus;
use App\Models\Building;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Yajra\DataTables\Facades\DataTables;

class AreaManagerRejectedBuildingsController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        abort_unless($user && $user->hasRole('Area Manager|Database Officer'), 403);

        $regionKey = (string) $user->region;
        $regionConfig = config('area_managers.regions.'.$regionKey, []);
        $municipalities = collect(data_get($regionConfig, 'municipalities', []))
            ->filter()
            ->values()
            ->all();
        $filterOptions = $this->filterOptions($municipalities);

        return View::make('DamageAssessment.areaManagerRejectedBuildings', [
            'regionKey' => $regionKey,
            'regionLabel' => data_get($regionConfig, 'label', $regionKey ?: __('multilingual.area_manager_review.default_region')),
            'municipalities' => $municipalities,
            'filterOptions' => $filterOptions,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $user = auth()->user();

        abort_unless($user && $user->hasRole('Area Manager|Database Officer'), 403);

        $municipalities = collect(config('area_managers.regions.'.(string) $user->region.'.municipalities', []))
            ->filter()
            ->values()
            ->all();

        $query = Building::query()
            ->select([
                'buildings.id',
                'buildings.objectid',
                'buildings.globalid',
                'buildings.building_name',
                'buildings.municipalitie',
                'buildings.neighborhood',
                'buildings.assignedto',
                DB::raw('latest_history.created_at as latest_status_at'),
                DB::raw('assessment_statuses.name as latest_status_name'),
                DB::raw("COALESCE(NULLIF(assessment_statuses.label_ar, ''), NULLIF(assessment_statuses.label_en, ''), assessment_statuses.name) as latest_status_label"),
            ])
            ->join('building_status_histories as latest_history', function ($join) {
                $join->on('buildings.objectid', '=', 'latest_history.building_id')
                    ->whereRaw('latest_history.id = (
                        select inner_ranked.id
                        from building_status_histories as inner_ranked
                        where inner_ranked.building_id = buildings.objectid
                        order by inner_ranked.created_at desc, inner_ranked.id desc
                        limit 1
                    )');
            })
            ->join('assessment_statuses', 'assessment_statuses.id', '=', 'latest_history.status_id')
            ->where(function ($statusQuery) {
                $statusQuery->where('assessment_statuses.name', 'need_review')
                    ->orWhere('assessment_statuses.name', 'like', '%reject%');
            });

        if (count($municipalities) > 0) {
            $query->whereIn('buildings.municipalitie', $municipalities);
        } else {
            $query->whereRaw('1 = 0');
        }

        $this->applyFilters($query, $request);

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->filterColumn('latest_status_label', function ($query, $keyword) {
                $query->where(function ($statusQuery) use ($keyword) {
                    $statusQuery->where('assessment_statuses.label_ar', 'like', '%'.$keyword.'%')
                        ->orWhere('assessment_statuses.label_en', 'like', '%'.$keyword.'%')
                        ->orWhere('assessment_statuses.name', 'like', '%'.$keyword.'%');
                });
            })
            ->orderColumn('latest_status_at', 'latest_history.created_at $1')
            ->editColumn('latest_status_label', function ($row): string {
                return AssessmentStatus::badgeHtmlFor(
                    (string) $row->latest_status_name,
                    (string) $row->latest_status_label
                );
            })
            ->editColumn('latest_status_at', function ($row): string {
                return $row->latest_status_at
                    ? \Carbon\Carbon::parse($row->latest_status_at)->format('Y-m-d h:i A')
                    : '-';
            })
            ->addColumn('actions', function ($row): string {
                $assessmentUrl = url('/assessment/'.$row->globalid);

                return '<a href="'.$assessmentUrl.'" class="btn btn-light-primary btn-sm" target="_blank">'.e(__('multilingual.area_manager_review.actions.open_audit')).'</a>';
            })
            ->rawColumns(['latest_status_label', 'actions'])
            ->toJson();
    }

    private function filterOptions(array $municipalities): array
    {
        $buildingQuery = Building::query();

        if (count($municipalities) > 0) {
            $buildingQuery->whereIn('municipalitie', $municipalities);
        } else {
            $buildingQuery->whereRaw('1 = 0');
        }

        return [
            'municipalities' => $municipalities,
            'neighborhoods' => (clone $buildingQuery)
                ->whereNotNull('neighborhood')
                ->where('neighborhood', '!=', '')
                ->distinct()
                ->orderBy('neighborhood')
                ->pluck('neighborhood')
                ->values()
                ->all(),
            'field_engineers' => (clone $buildingQuery)
                ->whereNotNull('assignedto')
                ->where('assignedto', '!=', '')
                ->distinct()
                ->orderBy('assignedto')
                ->pluck('assignedto')
                ->values()
                ->all(),
            'statuses' => DB::table('assessment_statuses')
                ->select(['name', 'label_ar', 'label_en'])
                ->where(function ($statusQuery) {
                    $statusQuery->where('name', 'need_review')
                        ->orWhere('name', 'like', '%reject%');
                })
                ->orderBy('order_step')
                ->get()
                ->map(fn ($status) => [
                    'name' => $status->name,
                    'label' => app()->getLocale() === 'ar'
                        ? ($status->label_ar ?: $status->label_en ?: $status->name)
                        : ($status->label_en ?: $status->label_ar ?: $status->name),
                ])
                ->all(),
        ];
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('objectid')) {
            $query->where('buildings.objectid', 'like', '%'.$request->string('objectid')->trim().'%');
        }

        if ($request->filled('building_name')) {
            $query->where('buildings.building_name', 'like', '%'.$request->string('building_name')->trim().'%');
        }

        if ($request->filled('municipalitie')) {
            $query->where('buildings.municipalitie', $request->string('municipalitie')->trim());
        }

        if ($request->filled('neighborhood')) {
            $query->where('buildings.neighborhood', $request->string('neighborhood')->trim());
        }

        if ($request->filled('assignedto')) {
            $query->where('buildings.assignedto', $request->string('assignedto')->trim());
        }

        if ($request->filled('latest_status')) {
            $query->where('assessment_statuses.name', $request->string('latest_status')->trim());
        }

        if ($request->filled('from_date')) {
            $query->whereDate('latest_history.created_at', '>=', $request->date('from_date')->toDateString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('latest_history.created_at', '<=', $request->date('to_date')->toDateString());
        }
    }
}
