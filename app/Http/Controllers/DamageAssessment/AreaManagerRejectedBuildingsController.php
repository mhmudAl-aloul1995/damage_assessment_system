<?php

namespace App\Http\Controllers\DamageAssessment;

use App\Http\Controllers\Controller;
use App\Models\Building;
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

        return View::make('DamageAssessment.areaManagerRejectedBuildings', [
            'regionKey' => $regionKey,
            'regionLabel' => data_get($regionConfig, 'label', $regionKey ?: 'Area Manager'),
            'municipalities' => $municipalities,
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

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->filterColumn('latest_status_label', function ($query, $keyword) {
                $query->where(function ($statusQuery) use ($keyword) {
                    $statusQuery->where('assessment_statuses.label_ar', 'like', '%'.$keyword.'%')
                        ->orWhere('assessment_statuses.label_en', 'like', '%'.$keyword.'%')
                        ->orWhere('assessment_statuses.name', 'like', '%'.$keyword.'%');
                });
            })
            ->editColumn('latest_status_label', function ($row): string {
                $statusName = strtolower((string) $row->latest_status_name);
                $badgeClass = str_contains($statusName, 'reject')
                    ? 'badge badge-light-danger fw-bold'
                    : 'badge badge-light-warning fw-bold';

                return '<span class="'.$badgeClass.'">'.e((string) $row->latest_status_label).'</span>';
            })
            ->editColumn('latest_status_at', function ($row): string {
                return $row->latest_status_at
                    ? \Carbon\Carbon::parse($row->latest_status_at)->format('Y-m-d h:i A')
                    : '-';
            })
            ->addColumn('actions', function ($row): string {
                $assessmentUrl = url('/showAssessmentAudit/'.$row->globalid);

                return '<a href="'.$assessmentUrl.'" class="btn btn-light-primary btn-sm" target="_blank">Open Audit</a>';
            })
            ->rawColumns(['latest_status_label', 'actions'])
            ->toJson();
    }
}
