<?php

namespace App\Http\Controllers\Report;

use App\Exports\AreaProductivityExport;
use App\Exports\ProductivityExport;
use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Buildings;
use App\Models\HousingStatus;
use App\Models\HousingUnit;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use View;

class reportController extends Controller
{
    /*
        function __construct()
        {
            $this->middleware('permission:user-list|user-create|user-edit|user-delete', ['only' => ['index', 'show']]);
            $this->middleware('permission:user-create', ['only' => ['create', 'store']]);
            $this->middleware('permission:user-edit', ['only' => ['edit', 'update']]);
            $this->middleware('permission:user-delete', ['only' => ['destroy']]);
        }*/

    public function __construct()
    {
        $this->middleware('role:Database Officer|Project Officer|Auditing Supervisor|Area Manager');
    }

    public function export_productivity(Request $request)
    {
        $data = $request->all();

        $minDate = $data['minDate'];
        $maxDate = $data['maxDate'];
        $year = 2026;
        $month_number = 2; // February

        $assignedto = Building::where('assignedto', '!=', '')->pluck('assignedto')->unique();
        $start = Carbon::createFromDate($year, $month_number, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth(); // Use copy() to avoid modifying the start date

        if (isset($data['minDate'])) {

            $start = $data['minDate'];
        }
        if (isset($data['maxDate'])) {

            $end = $data['maxDate'];
        }
        $period = CarbonPeriod::create($start, 'P1D', $end);

        $stats = Building::whereIn('assignedto', $assignedto)
            ->whereBetween('creationdate', [$start, $end])
            ->selectRaw("
        assignedto, 
        DATE(creationdate) as date, 
        COUNT(CASE WHEN building_damage_status = 'fully_damaged' THEN 1 END) as tda, 
        COUNT(CASE WHEN building_damage_status = 'partially_damaged' THEN 1 END) as pda
    ")
            ->groupBy('assignedto', 'date')
            ->get()
            ->groupBy(['assignedto', 'date'])
            ->map(function ($dates, $engineerId) {
                // Flatten the nested 'date' collections into one list to sum them up
                $totalForEngineer = $dates->flatten(1)->sum(function ($item) {
                    return $item->pda + $item->tda;
                });

                return [
                    'daily_breakdown' => $dates,
                    'engineer_total' => $totalForEngineer,
                ];
            });

        return Excel::download(new ProductivityExport($assignedto, $period, $stats), 'productivity.xlsx');
    }

    public function commulative(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());

        $commualtive = HousingUnit::select(
            'buildings.governorate',
            'buildings.municipalitie',
            'buildings.neighborhood',
            DB::raw('COUNT(DISTINCT CASE WHEN DATE(buildings.creationdate) BETWEEN ? AND ? THEN buildings.assignedto END) as no_eng'),
            DB::raw("COUNT(CASE WHEN housing_units.unit_damage_status = 'fully_damaged2' AND DATE(housing_units.creationdate) BETWEEN ? AND ? THEN 1 END) as tda_range"),
            DB::raw("COUNT(CASE WHEN housing_units.unit_damage_status = 'partially_damaged2' AND DATE(housing_units.creationdate) BETWEEN ? AND ? THEN 1 END) as pda_range"),
            DB::raw("COUNT(CASE WHEN housing_units.unit_damage_status = 'committee_review2' AND DATE(housing_units.creationdate) BETWEEN ? AND ? THEN 1 END) as cra_range")
        )
            ->leftJoin('buildings', 'housing_units.parentglobalid', '=', 'buildings.globalid')
            // ->where('buildings.neighborhood', '!=', '')
            ->where('buildings.field_status', '=', 'COMPLETED')
            // We have 8 date placeholders + 2 where placeholders = 10 total
            ->setBindings([
                $startDate,
                $endDate,
                $startDate,
                $endDate,
                $startDate,
                $endDate,
                $startDate,
                $endDate,
                // '',            // for neighborhood != ?
                'COMPLETED',    // for field_status = ?
            ])
            ->groupBy('buildings.neighborhood')
            ->get();

        return View::make('DamageAssessment.Reports.commulatives', compact('commualtive', 'startDate', 'endDate'));
    }

    public function exportCommulative(Request $request)
    {
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $data = HousingUnit::select(
            'buildings.governorate',
            'buildings.municipalitie',
            'buildings.neighborhood',
            DB::raw('COUNT(DISTINCT CASE WHEN DATE(buildings.creationdate) BETWEEN ? AND ? THEN buildings.assignedto END) as no_eng'),
            DB::raw("COUNT(CASE WHEN housing_units.unit_damage_status = 'fully_damaged2' AND DATE(housing_units.creationdate) BETWEEN ? AND ? THEN 1 END) as tda_range"),
            DB::raw("COUNT(CASE WHEN housing_units.unit_damage_status = 'partially_damaged2' AND DATE(housing_units.creationdate) BETWEEN ? AND ? THEN 1 END) as pda_range"),
            DB::raw("COUNT(CASE WHEN housing_units.unit_damage_status = 'committee_review2' AND DATE(housing_units.creationdate) BETWEEN ? AND ? THEN 1 END) as cra_range")
        )
            ->leftJoin('buildings', 'housing_units.parentglobalid', '=', 'buildings.globalid')
            ->setBindings([$startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate])
            ->groupBy('buildings.neighborhood')
            ->get();

        return Excel::download(new AreaProductivityExport($data, $startDate, $endDate), 'Report.xlsx');
    }

    public function productivity(Request $request)
    {
        $data = $request->all();

        // 1. Get the list of unique engineers from the Buildings table
        $assignedto = Building::whereNotNull('assignedto')
            ->where('assignedto', '!=', '')
            ->pluck('assignedto')
            ->unique();

        $year = date('Y');
        $month_number = date('m');
        $start = Carbon::createFromDate($year, $month_number, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        if (isset($data['minDate'])) {
            $start = Carbon::parse($data['minDate'])->startOfDay();
        }
        if (isset($data['maxDate'])) {
            $end = Carbon::parse($data['maxDate'])->endOfDay();
        }

        $period = CarbonPeriod::create($start, 'P1D', $end);

        // 2. Join housing_units with buildings to access the 'assignedto' column
        $stats = HousingUnit::join('buildings', 'housing_units.parentglobalid', '=', 'buildings.globalid')
            ->whereIn('buildings.assignedto', $assignedto)
            ->whereBetween('housing_units.creationdate', [$start, $end])
            ->selectRaw("
            buildings.assignedto, 
            DATE(housing_units.creationdate) as date, 
            COUNT(CASE WHEN housing_units.unit_damage_status = 'fully_damaged2' THEN 1 END) as tda, 
            COUNT(CASE WHEN housing_units.unit_damage_status = 'partially_damaged2' THEN 1 END) as pda
        ")
            ->groupBy('buildings.assignedto', 'date')
            ->get()
            ->groupBy(['assignedto', 'date'])
            ->map(function ($dates) {
                $totalForEngineer = $dates->flatten(1)->sum(function ($item) {
                    return (int) $item->pda + (int) $item->tda;
                });

                return [
                    'daily_breakdown' => $dates,
                    'engineer_total' => $totalForEngineer,
                ];
            });

        return View::make('DamageAssessment.Reports.productivity', compact('period', 'assignedto', 'stats'));
    }

    public function auditorsDailyAchievement(Request $request)
    {
        $startDate = Carbon::parse($request->input('start_date', now()->toDateString()))->startOfDay();
        $endDate = Carbon::parse($request->input('end_date', $startDate->toDateString()))->endOfDay();

        $auditors = User::role('QC/QA Engineer')
            ->orderBy('name')
            ->get(['id', 'name']);

        $statusCounts = HousingStatus::query()
            ->join('assessment_statuses', 'housing_statuses.status_id', '=', 'assessment_statuses.id')
            ->where('housing_statuses.type', 'QC/QA Engineer')
            ->whereBetween('housing_statuses.updated_at', [$startDate, $endDate])
            ->whereIn('assessment_statuses.name', [
                'accepted_by_engineer',
                'rejected_by_engineer',
                'need_review',
            ])
            ->select(
                'housing_statuses.user_id',
                DB::raw("SUM(CASE WHEN assessment_statuses.name = 'accepted_by_engineer' THEN 1 ELSE 0 END) as accepted_count"),
                DB::raw("SUM(CASE WHEN assessment_statuses.name = 'rejected_by_engineer' THEN 1 ELSE 0 END) as rejected_count"),
                DB::raw("SUM(CASE WHEN assessment_statuses.name = 'need_review' THEN 1 ELSE 0 END) as need_review_count")
            )
            ->groupBy('housing_statuses.user_id')
            ->get()
            ->keyBy('user_id');

        $rows = $auditors->map(function ($auditor) use ($statusCounts) {
            $counts = $statusCounts->get($auditor->id);

            $acceptedCount = (int) ($counts->accepted_count ?? 0);
            $rejectedCount = (int) ($counts->rejected_count ?? 0);
            $needReviewCount = (int) ($counts->need_review_count ?? 0);

            return [
                'name' => $auditor->name,
                'accepted_count' => $acceptedCount,
                'rejected_count' => $rejectedCount,
                'need_review_count' => $needReviewCount,
                'total_count' => $acceptedCount + $rejectedCount + $needReviewCount,
            ];
        });

        $totals = [
            'accepted_count' => $rows->sum('accepted_count'),
            'rejected_count' => $rows->sum('rejected_count'),
            'need_review_count' => $rows->sum('need_review_count'),
            'total_count' => $rows->sum('total_count'),
        ];

        $startDateValue = $startDate->toDateString();
        $endDateValue = $endDate->toDateString();

        return View::make('DamageAssessment.Reports.auditors_daily_achievement', compact(
            'rows',
            'totals',
            'startDateValue',
            'endDateValue'
        ));
    }
}
