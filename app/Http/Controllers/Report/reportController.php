<?php

namespace App\Http\Controllers\Report;

use App\Exports\AreaProductivityExport;
use App\Exports\DailyAchievementExport;
use App\Exports\HlpAuditReportExport;
use App\Exports\ProductivityExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\AreaProductivityReportFilterRequest;
use App\Models\Building;
use App\Models\Buildings;
use App\Models\BuildingStatus;
use App\Models\HousingStatus;
use App\Models\HousingUnit;
use App\Models\User;
use App\Services\HlpAuditReportService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
        $this->middleware('role:Database Officer|Project Officer|undp-Project Manager|Auditing Supervisor|Area Manager');
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

    public function dailyAchievement(Request $request)
    {
        return $this->renderDailyAchievementReport($request, $request->input('tab', 'engineers'));
    }

    public function exportDailyAchievement(Request $request): BinaryFileResponse
    {
        $startDate = Carbon::parse($request->input('start_date', now()->toDateString()))->toDateString();
        $endDate = Carbon::parse($request->input('end_date', $startDate))->toDateString();

        return Excel::download(
            new DailyAchievementExport([
                $this->buildDailyAchievementExportSheet(
                    title: 'Engineers',
                    reportTitle: 'Daily Achievement Report For Auditing Engineers',
                    role: 'QC/QA Engineer',
                    statusType: 'QC/QA Engineer',
                    statusNames: ['accepted_by_engineer', 'rejected_by_engineer', 'need_review'],
                    startDate: $startDate,
                    endDate: $endDate,
                ),
                $this->buildDailyAchievementExportSheet(
                    title: 'Lawyers',
                    reportTitle: 'Daily Achievement Report For Auditing Lawyers',
                    role: 'Legal Auditor',
                    statusType: 'Legal Auditor',
                    statusNames: ['assigned_to_lawyer', 'accepted_by_lawyer', 'legal_notes'],
                    startDate: $startDate,
                    endDate: $endDate,
                ),
            ]),
            'daily-achievement-'.$startDate.'-to-'.$endDate.'.xlsx'
        );
    }

    public function auditorsDailyAchievement(Request $request)
    {
        return $this->renderDailyAchievementReport($request, 'engineers');
    }

    public function lawyersDailyAchievement(Request $request)
    {
        return $this->renderDailyAchievementReport($request, 'lawyers');
    }

    public function hlpAudit(AreaProductivityReportFilterRequest $request, HlpAuditReportService $reportService): \Illuminate\Contracts\View\View
    {
        return View::make('DamageAssessment.Reports.hlp_audit', $reportService->build($request->validated()));
    }

    public function exportHlpAudit(AreaProductivityReportFilterRequest $request, HlpAuditReportService $reportService): BinaryFileResponse
    {
        $filters = $request->validated();
        $report = $reportService->build($filters);

        return Excel::download(
            new HlpAuditReportExport(
                $reportService->exportRows($filters),
                $report['start_date'],
                $report['end_date'],
            ),
            'hlp-audit-report-'.$report['start_date'].'-to-'.$report['end_date'].'.xlsx',
        );
    }

    private function renderDailyAchievementReport(Request $request, string $tab)
    {
        $activeTab = $tab === 'lawyers' ? 'lawyers' : 'engineers';

        $reportData = $activeTab === 'lawyers'
            ? $this->getLawyersDailyAchievementData($request)
            : $this->getEngineersDailyAchievementData($request);

        return View::make('DamageAssessment.Reports.daily_achievement', array_merge($reportData, [
            'activeTab' => $activeTab,
        ]));
    }

    private function getEngineersDailyAchievementData(Request $request): array
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
        })->sort($this->sortDailyAchievementRowsByTotal(...))->values();

        $totals = [
            'accepted_count' => $rows->sum('accepted_count'),
            'rejected_count' => $rows->sum('rejected_count'),
            'need_review_count' => $rows->sum('need_review_count'),
            'total_count' => $rows->sum('total_count'),
        ];

        $trackedStatusNames = [
            'accepted_by_engineer',
            'rejected_by_engineer',
            'need_review',
        ];

        return [
            'reportTitle' => 'Daily Achievement Report For Auditing Engineers',
            'reportRoute' => route('reports.daily-achievement'),
            'startDateValue' => $startDate->toDateString(),
            'endDateValue' => $endDate->toDateString(),
            'rows' => $rows,
            'totals' => $totals,
            'chartMetrics' => $this->buildChartMetrics('QC/QA Engineer', $trackedStatusNames, $startDate, $endDate),
            'summaryCards' => [
                ['label' => 'Accepted', 'value' => $totals['accepted_count'], 'class' => 'success'],
                ['label' => 'Rejected', 'value' => $totals['rejected_count'], 'class' => 'danger'],
                ['label' => 'Need Review', 'value' => $totals['need_review_count'], 'class' => 'warning'],
                ['label' => 'Total', 'value' => $totals['total_count'], 'class' => 'primary'],
            ],
            'tableTitle' => 'Auditor Name',
            'tableColumns' => [
                ['label' => 'Accepted Units', 'key' => 'accepted_count', 'class' => 'success'],
                ['label' => 'Rejected Units', 'key' => 'rejected_count', 'class' => 'danger'],
                ['label' => 'Need Review', 'key' => 'need_review_count', 'class' => 'warning'],
                ['label' => 'Total', 'key' => 'total_count', 'class' => 'primary'],
            ],
            'emptyMessage' => 'No auditors found.',
        ];
    }

    private function getLawyersDailyAchievementData(Request $request): array
    {
        $startDate = Carbon::parse($request->input('start_date', now()->toDateString()))->startOfDay();
        $endDate = Carbon::parse($request->input('end_date', $startDate->toDateString()))->endOfDay();

        $lawyers = User::role('Legal Auditor')
            ->orderBy('name')
            ->get(['id', 'name']);

        $statusCounts = HousingStatus::query()
            ->join('assessment_statuses', 'housing_statuses.status_id', '=', 'assessment_statuses.id')
            ->where('housing_statuses.type', 'Legal Auditor')
            ->whereBetween('housing_statuses.updated_at', [$startDate, $endDate])
            ->whereIn('assessment_statuses.name', [
                'assigned_to_lawyer',
                'accepted_by_lawyer',
                'legal_notes',
            ])
            ->select(
                'housing_statuses.user_id',
                DB::raw("SUM(CASE WHEN assessment_statuses.name = 'assigned_to_lawyer' THEN 1 ELSE 0 END) as assigned_count"),
                DB::raw("SUM(CASE WHEN assessment_statuses.name = 'accepted_by_lawyer' THEN 1 ELSE 0 END) as accepted_count"),
                DB::raw("SUM(CASE WHEN assessment_statuses.name = 'legal_notes' THEN 1 ELSE 0 END) as legal_notes_count")
            )
            ->groupBy('housing_statuses.user_id')
            ->get()
            ->keyBy('user_id');

        $rows = $lawyers->map(function ($lawyer) use ($statusCounts) {
            $counts = $statusCounts->get($lawyer->id);

            $assignedCount = (int) ($counts->assigned_count ?? 0);
            $acceptedCount = (int) ($counts->accepted_count ?? 0);
            $legalNotesCount = (int) ($counts->legal_notes_count ?? 0);

            return [
                'name' => $lawyer->name,
                'assigned_count' => $assignedCount,
                'accepted_count' => $acceptedCount,
                'legal_notes_count' => $legalNotesCount,
                'total_count' => $assignedCount + $acceptedCount + $legalNotesCount,
            ];
        })->sort($this->sortDailyAchievementRowsByTotal(...))->values();

        $totals = [
            'assigned_count' => $rows->sum('assigned_count'),
            'accepted_count' => $rows->sum('accepted_count'),
            'legal_notes_count' => $rows->sum('legal_notes_count'),
            'total_count' => $rows->sum('total_count'),
        ];

        $trackedStatusNames = [
            'accepted_by_lawyer',
            'legal_notes',
        ];

        return [
            'reportTitle' => 'Daily Achievement Report For Auditing Lawyers',
            'reportRoute' => route('reports.daily-achievement'),
            'startDateValue' => $startDate->toDateString(),
            'endDateValue' => $endDate->toDateString(),
            'rows' => $rows,
            'totals' => $totals,
            'chartMetrics' => $this->buildChartMetrics('Legal Auditor', $trackedStatusNames, $startDate, $endDate),
            'summaryCards' => [
                ['label' => 'Assigned', 'value' => $totals['assigned_count'], 'class' => 'info'],
                ['label' => 'Accepted', 'value' => $totals['accepted_count'], 'class' => 'success'],
                ['label' => 'Legal Notes', 'value' => $totals['legal_notes_count'], 'class' => 'warning'],
                ['label' => 'Total', 'value' => $totals['total_count'], 'class' => 'primary'],
            ],
            'tableTitle' => 'Lawyer Name',
            'tableColumns' => [
                ['label' => 'Assigned Units', 'key' => 'assigned_count', 'class' => 'info'],
                ['label' => 'Accepted Units', 'key' => 'accepted_count', 'class' => 'success'],
                ['label' => 'Legal Notes', 'key' => 'legal_notes_count', 'class' => 'warning'],
                ['label' => 'Total', 'key' => 'total_count', 'class' => 'primary'],
            ],
            'emptyMessage' => 'No lawyers found.',
        ];
    }

    private function sortDailyAchievementRowsByTotal(array $first, array $second): int
    {
        $totalComparison = $second['total_count'] <=> $first['total_count'];

        if ($totalComparison !== 0) {
            return $totalComparison;
        }

        return strcmp((string) $first['name'], (string) $second['name']);
    }

    private function buildDailyAchievementExportSheet(
        string $title,
        string $reportTitle,
        string $role,
        string $statusType,
        array $statusNames,
        string $startDate,
        string $endDate,
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $dailyCounts = HousingStatus::query()
            ->join('assessment_statuses', 'housing_statuses.status_id', '=', 'assessment_statuses.id')
            ->where('housing_statuses.type', $statusType)
            ->whereBetween('housing_statuses.updated_at', [$start, $end])
            ->whereIn('assessment_statuses.name', $statusNames)
            ->select(
                'housing_statuses.user_id',
                DB::raw('DATE(housing_statuses.updated_at) as achievement_date'),
                DB::raw('COUNT(*) as total_count')
            )
            ->groupBy('housing_statuses.user_id', DB::raw('DATE(housing_statuses.updated_at)'))
            ->get()
            ->groupBy('achievement_date')
            ->map(fn ($rows) => $rows->pluck('total_count', 'user_id')->map(fn ($count): int => (int) $count)->all())
            ->all();

        $totalsByUser = collect($dailyCounts)
            ->flatMap(fn (array $dateCounts) => collect($dateCounts)->map(fn (int $count, int|string $userId): array => [
                'user_id' => (int) $userId,
                'count' => $count,
            ]))
            ->groupBy('user_id')
            ->map(fn ($rows): int => (int) $rows->sum('count'));

        $users = User::role($role)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'total' => (int) ($totalsByUser->get($user->id, 0)),
            ])
            ->sortBy([
                ['total', 'desc'],
                ['name', 'asc'],
            ])
            ->values()
            ->all();

        return [
            'title' => $title,
            'report_title' => $reportTitle,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'users' => $users,
            'daily_counts' => $dailyCounts,
        ];
    }

    private function buildChartMetrics(string $type, array $trackedStatusNames, Carbon $startDate, Carbon $endDate): array
    {
        $auditedBuildingsCount = BuildingStatus::query()
            ->join('assessment_statuses', 'building_statuses.status_id', '=', 'assessment_statuses.id')
            ->where('building_statuses.type', $type)
            ->whereBetween('building_statuses.updated_at', [$startDate, $endDate])
            ->whereIn('assessment_statuses.name', $trackedStatusNames)
            ->distinct('building_statuses.building_id')
            ->count('building_statuses.building_id');

        $auditedHousingUnitsCount = HousingStatus::query()
            ->join('assessment_statuses', 'housing_statuses.status_id', '=', 'assessment_statuses.id')
            ->where('housing_statuses.type', $type)
            ->whereBetween('housing_statuses.updated_at', [$startDate, $endDate])
            ->whereIn('assessment_statuses.name', $trackedStatusNames)
            ->distinct('housing_statuses.housing_id')
            ->count('housing_statuses.housing_id');

        $totalBuildingsCount = Building::query()->count();
        $totalHousingUnitsCount = HousingUnit::query()->count();

        return [
            'buildings' => [
                'label' => 'Audited Buildings',
                'audited_count' => $auditedBuildingsCount,
                'remaining_count' => max($totalBuildingsCount - $auditedBuildingsCount, 0),
                'total_count' => $totalBuildingsCount,
                'percentage' => $totalBuildingsCount > 0 ? round(($auditedBuildingsCount / $totalBuildingsCount) * 100, 1) : 0,
            ],
            'housing_units' => [
                'label' => 'Audited Housing Units',
                'audited_count' => $auditedHousingUnitsCount,
                'remaining_count' => max($totalHousingUnitsCount - $auditedHousingUnitsCount, 0),
                'total_count' => $totalHousingUnitsCount,
                'percentage' => $totalHousingUnitsCount > 0 ? round(($auditedHousingUnitsCount / $totalHousingUnitsCount) * 100, 1) : 0,
            ],
        ];
    }
}
