<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Reports;

use App\Exports\AreaProductivityExport;
use App\Exports\ProductivityExport;
use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\HousingUnit;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:Database Officer|Project Officer|undp-Project Manager|Auditing Supervisor|Area Manager');
    }

    public function export_productivity(Request $request): BinaryFileResponse
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

    public function commulative(Request $request): ViewContract
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
            ->where('buildings.field_status', '=', 'COMPLETED')
            ->setBindings([
                $startDate,
                $endDate,
                $startDate,
                $endDate,
                $startDate,
                $endDate,
                $startDate,
                $endDate,
                'COMPLETED',
            ])
            ->groupBy('buildings.neighborhood')
            ->get();

        return View::make('modules.damage-assessment.reports.commulatives', compact('commualtive', 'startDate', 'endDate'));
    }

    public function exportCommulative(Request $request): BinaryFileResponse
    {
        set_time_limit(300);
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

    public function productivity(Request $request): ViewContract
    {
        $data = $request->all();

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

        return View::make('modules.damage-assessment.reports.productivity', compact('period', 'assignedto', 'stats'));
    }
}
