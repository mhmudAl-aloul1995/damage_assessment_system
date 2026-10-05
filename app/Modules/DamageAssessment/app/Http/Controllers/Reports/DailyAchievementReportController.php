<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Reports;

use App\Exports\DailyAchievementExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\DailyAchievementUnitsRequest;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\HousingStatus;
use App\Models\HousingUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DailyAchievementReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:Database Officer|Project Officer|undp-Project Manager|Auditing Supervisor|Area Manager');
    }

    public function dailyAchievement(Request $request): ViewContract
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
                    role: null,
                    statusType: null,
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

    public function auditorsDailyAchievement(Request $request): ViewContract
    {
        return $this->renderDailyAchievementReport($request, 'engineers');
    }

    public function lawyersDailyAchievement(Request $request): ViewContract
    {
        return $this->renderDailyAchievementReport($request, 'lawyers');
    }

    public function dailyAchievementUnits(DailyAchievementUnitsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();
        $statusName = (string) $validated['status'];
        $userId = (int) $validated['user_id'];
        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $search = trim((string) $request->input('search.value', ''));

        $baseQuery = HousingStatus::query()
            ->join('assessment_statuses', 'housing_statuses.status_id', '=', 'assessment_statuses.id')
            ->join('housing_units', 'housing_statuses.housing_id', '=', 'housing_units.objectid')
            ->leftJoin('buildings', 'housing_units.parentglobalid', '=', 'buildings.globalid')
            ->where('housing_statuses.user_id', $userId)
            ->where('assessment_statuses.name', $statusName)
            ->whereBetween('housing_statuses.created_at', [$startDate, $endDate]);

        $recordsTotal = (clone $baseQuery)->count();

        if ($search !== '') {
            $baseQuery->where(function ($query) use ($search): void {
                $query
                    ->where('housing_units.objectid', 'like', "%{$search}%")
                    ->orWhere('housing_units.globalid', 'like', "%{$search}%")
                    ->orWhere('housing_units.parentglobalid', 'like', "%{$search}%")
                    ->orWhere('housing_units.housing_unit_number', 'like', "%{$search}%")
                    ->orWhere('housing_units.floor_number', 'like', "%{$search}%")
                    ->orWhere('housing_units.q_9_3_1_first_name', 'like', "%{$search}%")
                    ->orWhere('housing_units.q_9_3_4_last_name', 'like', "%{$search}%")
                    ->orWhere('buildings.building_name', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $baseQuery)->count();

        $units = $baseQuery
            ->select([
                'housing_units.objectid',
                'housing_units.globalid',
                'housing_units.parentglobalid',
                'housing_units.floor_number',
                'housing_units.housing_unit_number',
                'housing_units.q_9_3_1_first_name',
                'housing_units.q_9_3_2_second_name__father',
                'housing_units.q_9_3_4_last_name',
                'buildings.building_name',
                'buildings.globalid as building_globalid',
                'housing_statuses.created_at as status_created_at',
                'housing_statuses.notes',
            ])
            ->orderBy('housing_statuses.created_at')
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(function ($unit): array {
                $residentName = collect([
                    $unit->q_9_3_1_first_name,
                    $unit->q_9_3_2_second_name__father,
                    $unit->q_9_3_4_last_name,
                ])->filter()->implode(' ');

                return [
                    'objectid' => $unit->objectid,
                    'globalid' => $unit->globalid,
                    'building_name' => $unit->building_name ?? '-',
                    'parentglobalid' => $unit->parentglobalid,
                    'floor_number' => $unit->floor_number ?? '-',
                    'housing_unit_number' => $unit->housing_unit_number ?? '-',
                    'resident_name' => $residentName !== '' ? $residentName : '-',
                    'status_created_at' => $unit->status_created_at
                        ? Carbon::parse($unit->status_created_at)->format('Y-m-d H:i')
                        : '-',
                    'notes' => $unit->notes ?? '-',
                    'assessment_url' => url('damage-assessment/showAssessmentAudit/'.rawurlencode((string) $unit->building_globalid).'/'.rawurlencode((string) $unit->globalid)),
                ];
            });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $units,
        ]);
    }

    private function renderDailyAchievementReport(Request $request, string $tab): ViewContract
    {
        $activeTab = $tab === 'lawyers' ? 'lawyers' : 'engineers';

        $reportData = $activeTab === 'lawyers'
            ? $this->getLawyersDailyAchievementData($request)
            : $this->getEngineersDailyAchievementData($request);

        return View::make('damage-assessment::reports.daily_achievement', array_merge($reportData, [
            'activeTab' => $activeTab,
        ]));
    }

    private function getEngineersDailyAchievementData(Request $request): array
    {
        $startDate = Carbon::parse($request->input('start_date', now()->toDateString()))->startOfDay();
        $endDate = Carbon::parse($request->input('end_date', $startDate->toDateString()))->endOfDay();

        $statusCounts = $this->housingStatusAchievementQuery(null, [
            'accepted_by_engineer',
            'rejected_by_engineer',
            'need_review',
        ], $startDate, $endDate)
            ->select(
                'housing_statuses.user_id',
                DB::raw("SUM(CASE WHEN assessment_statuses.name = 'accepted_by_engineer' THEN 1 ELSE 0 END) as accepted_count"),
                DB::raw("SUM(CASE WHEN assessment_statuses.name = 'rejected_by_engineer' THEN 1 ELSE 0 END) as rejected_count"),
                DB::raw("SUM(CASE WHEN assessment_statuses.name = 'need_review' THEN 1 ELSE 0 END) as need_review_count")
            )
            ->groupBy('housing_statuses.user_id')
            ->get()
            ->keyBy('user_id');

        $auditors = User::query()
            ->whereIn('id', $statusCounts->keys()->filter())
            ->orderBy('name')
            ->get(['id', 'name']);

        $rows = $auditors->map(function ($auditor) use ($statusCounts) {
            $counts = $statusCounts->get($auditor->id);

            $acceptedCount = (int) ($counts->accepted_count ?? 0);
            $rejectedCount = (int) ($counts->rejected_count ?? 0);
            $needReviewCount = (int) ($counts->need_review_count ?? 0);

            return [
                'user_id' => $auditor->id,
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
            'chartMetrics' => $this->buildChartMetrics(null, $trackedStatusNames, $startDate, $endDate),
            'summaryCards' => [
                ['label' => 'Accepted', 'value' => $totals['accepted_count'], 'class' => 'success'],
                ['label' => 'Rejected', 'value' => $totals['rejected_count'], 'class' => 'danger'],
                ['label' => 'Need Review', 'value' => $totals['need_review_count'], 'class' => 'warning'],
                ['label' => 'Total', 'value' => $totals['total_count'], 'class' => 'primary'],
            ],
            'tableTitle' => 'Auditor Name',
            'tableColumns' => [
                ['label' => 'Accepted Units', 'key' => 'accepted_count', 'class' => 'success', 'status' => 'accepted_by_engineer'],
                ['label' => 'Rejected Units', 'key' => 'rejected_count', 'class' => 'danger', 'status' => 'rejected_by_engineer'],
                ['label' => 'Need Review', 'key' => 'need_review_count', 'class' => 'warning', 'status' => 'need_review'],
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

        $statusCounts = $this->housingStatusAchievementQuery('Legal Auditor', [
            'assigned_to_lawyer',
            'accepted_by_lawyer',
            'legal_notes',
        ], $startDate, $endDate)
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
                'user_id' => $lawyer->id,
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
                ['label' => 'Assigned Units', 'key' => 'assigned_count', 'class' => 'info', 'status' => 'assigned_to_lawyer'],
                ['label' => 'Accepted Units', 'key' => 'accepted_count', 'class' => 'success', 'status' => 'accepted_by_lawyer'],
                ['label' => 'Legal Notes', 'key' => 'legal_notes_count', 'class' => 'warning', 'status' => 'legal_notes'],
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
        ?string $role,
        ?string $statusType,
        array $statusNames,
        string $startDate,
        string $endDate,
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $dailyCounts = $this->housingStatusAchievementQuery($statusType, $statusNames, $start, $end)
            ->select(
                'housing_statuses.user_id',
                DB::raw('DATE(housing_statuses.created_at) as achievement_date'),
                DB::raw('COUNT(*) as total_count')
            )
            ->groupBy('housing_statuses.user_id', DB::raw('DATE(housing_statuses.created_at)'))
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

        $usersQuery = User::query()
            ->when($role !== null, fn ($query) => $query->role($role))
            ->when($role === null, fn ($query) => $query->whereIn('id', $totalsByUser->keys()->filter()))
            ->orderBy('name');

        $users = $usersQuery->get(['id', 'name'])
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

    private function buildChartMetrics(?string $type, array $trackedStatusNames, Carbon $startDate, Carbon $endDate): array
    {
        $auditedBuildingsCount = BuildingStatus::query()
            ->join('assessment_statuses', 'building_statuses.status_id', '=', 'assessment_statuses.id')
            ->join('buildings', 'building_statuses.building_id', '=', 'buildings.objectid')
            ->when($type !== null, fn ($query) => $query->where('building_statuses.type', $type))
            ->whereBetween('building_statuses.created_at', [$startDate, $endDate])
            ->whereIn('assessment_statuses.name', $trackedStatusNames)
            ->distinct('building_statuses.building_id')
            ->count('building_statuses.building_id');

        $auditedHousingUnitsCount = $this->housingStatusAchievementQuery($type, $trackedStatusNames, $startDate, $endDate)
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

    private function housingStatusAchievementQuery(?string $type, array $statusNames, Carbon $startDate, Carbon $endDate): Builder
    {
        return HousingStatus::query()
            ->join('assessment_statuses', 'housing_statuses.status_id', '=', 'assessment_statuses.id')
            ->join('housing_units', 'housing_statuses.housing_id', '=', 'housing_units.objectid')
            ->when($type !== null, fn ($query) => $query->where('housing_statuses.type', $type))
            ->whereBetween('housing_statuses.created_at', [$startDate, $endDate])
            ->whereIn('assessment_statuses.name', $statusNames)
            ->select('housing_statuses.*');
    }
}
