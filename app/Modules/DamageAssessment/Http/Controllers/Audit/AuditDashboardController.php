<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\HousingStatus;
use App\Models\HousingUnit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class AuditDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $startDate = Carbon::parse($request->input('start_date', now()->subDays(29)->toDateString()))->startOfDay();
        $endDate = Carbon::parse($request->input('end_date', now()->toDateString()))->endOfDay();

        $engineerStatuses = [
            'assigned_to_engineer' => 'Assigned',
            'accepted_by_engineer' => 'Accepted',
            'rejected_by_engineer' => 'Rejected',
            'need_review' => 'Need Review',
        ];

        $lawyerStatuses = [
            'assigned_to_lawyer' => 'Assigned',
            'accepted_by_lawyer' => 'Accepted',
            'legal_notes' => 'Legal Notes',
        ];

        $totalBuildingsCount = Building::query()
            ->where('field_status', 'COMPLETED')
            ->whereDate('end', '>=', $startDate->toDateString())
            ->whereDate('end', '<=', $endDate->toDateString())
            ->count();
        $totalHousingUnitsCount = HousingUnit::query()
            ->whereDate('editdate', '>=', $startDate->toDateString())
            ->whereDate('editdate', '<=', $endDate->toDateString())
            ->count();
        $dailyHousingAchievementStartDate = $startDate->copy();

        $engineerMetrics = $this->buildAuditDashboardMetrics(
            type: 'QC/QA Engineer',
            buildingStatuses: $engineerStatuses,
            housingStatuses: $engineerStatuses,
            trackedAuditedStatuses: ['accepted_by_engineer', 'rejected_by_engineer', 'need_review'],
            totalBuildingsCount: $totalBuildingsCount,
            totalHousingUnitsCount: $totalHousingUnitsCount,
            startDate: $startDate,
            endDate: $endDate,
            dailyHousingAchievementStartDate: $dailyHousingAchievementStartDate,
        );

        $lawyerMetrics = $this->buildAuditDashboardMetrics(
            type: 'Legal Auditor',
            buildingStatuses: $lawyerStatuses,
            housingStatuses: $lawyerStatuses,
            trackedAuditedStatuses: ['accepted_by_lawyer', 'legal_notes'],
            totalBuildingsCount: $totalBuildingsCount,
            totalHousingUnitsCount: $totalHousingUnitsCount,
            startDate: $startDate,
            endDate: $endDate,
            dailyHousingAchievementStartDate: $dailyHousingAchievementStartDate,
        );

        $summaryMetrics = [
            'total_buildings_count' => $totalBuildingsCount,
            'total_housing_units_count' => $totalHousingUnitsCount,
            'engineer' => $engineerMetrics['summary'],
            'lawyer' => $lawyerMetrics['summary'],
        ];

        $chartData = [
            'engineer' => $engineerMetrics['charts'],
            'lawyer' => $lawyerMetrics['charts'],
        ];

        $startDateValue = $startDate->toDateString();
        $endDateValue = $endDate->toDateString();

        return View::make('damage-assessment::audit.auditDashboard', compact(
            'summaryMetrics',
            'chartData',
            'startDateValue',
            'endDateValue'
        ));
    }

    /**
     * @param  array<string, string>  $buildingStatuses
     * @param  array<string, string>  $housingStatuses
     * @param  array<int, string>  $trackedAuditedStatuses
     * @return array{summary: array<string, float|int>, charts: array<string, array<int, int|string>|string>}
     */
    private function buildAuditDashboardMetrics(
        string $type,
        array $buildingStatuses,
        array $housingStatuses,
        array $trackedAuditedStatuses,
        int $totalBuildingsCount,
        int $totalHousingUnitsCount,
        Carbon $startDate,
        Carbon $endDate,
        Carbon $dailyHousingAchievementStartDate,
    ): array {
        $buildingStatusRaw = BuildingStatus::query()
            ->join('buildings', 'building_statuses.building_id', '=', 'buildings.objectid')
            ->join('assessment_statuses', 'building_statuses.status_id', '=', 'assessment_statuses.id')
            ->where('buildings.field_status', 'COMPLETED')
            ->where('building_statuses.type', $type)
            ->whereBetween('building_statuses.updated_at', [$startDate, $endDate])
            ->whereIn('assessment_statuses.name', array_keys($buildingStatuses))
            ->selectRaw('assessment_statuses.name as status_name, COUNT(*) as total')
            ->groupBy('assessment_statuses.name')
            ->pluck('total', 'status_name');

        $housingStatusRaw = HousingStatus::query()
            ->join('housing_units', 'housing_statuses.housing_id', '=', 'housing_units.objectid')
            ->join('buildings', 'housing_units.parentglobalid', '=', 'buildings.globalid')
            ->join('assessment_statuses', 'housing_statuses.status_id', '=', 'assessment_statuses.id')
            ->where('buildings.field_status', 'COMPLETED')
            ->where('housing_statuses.type', $type)
            ->whereBetween('housing_statuses.updated_at', [$startDate, $endDate])
            ->whereIn('assessment_statuses.name', array_keys($housingStatuses))
            ->selectRaw('assessment_statuses.name as status_name, COUNT(*) as total')
            ->groupBy('assessment_statuses.name')
            ->pluck('total', 'status_name');

        $auditedBuildingsCount = BuildingStatus::query()
            ->join('buildings', 'building_statuses.building_id', '=', 'buildings.objectid')
            ->join('assessment_statuses', 'building_statuses.status_id', '=', 'assessment_statuses.id')
            ->where('buildings.field_status', 'COMPLETED')
            ->where('building_statuses.type', $type)
            ->whereBetween('building_statuses.updated_at', [$startDate, $endDate])
            ->whereIn('assessment_statuses.name', $trackedAuditedStatuses)
            ->distinct('building_statuses.building_id')
            ->count('building_statuses.building_id');

        $auditedHousingUnitsCount = HousingStatus::query()
            ->join('housing_units', 'housing_statuses.housing_id', '=', 'housing_units.objectid')
            ->join('buildings', 'housing_units.parentglobalid', '=', 'buildings.globalid')
            ->join('assessment_statuses', 'housing_statuses.status_id', '=', 'assessment_statuses.id')
            ->where('buildings.field_status', 'COMPLETED')
            ->where('housing_statuses.type', $type)
            ->whereBetween('housing_statuses.updated_at', [$startDate, $endDate])
            ->whereIn('assessment_statuses.name', $trackedAuditedStatuses)
            ->distinct('housing_statuses.housing_id')
            ->count('housing_statuses.housing_id');

        $dailyHousingAchievementRaw = HousingStatus::query()
            ->join('housing_units', 'housing_statuses.housing_id', '=', 'housing_units.objectid')
            ->join('buildings', 'housing_units.parentglobalid', '=', 'buildings.globalid')
            ->join('assessment_statuses', 'housing_statuses.status_id', '=', 'assessment_statuses.id')
            ->where('buildings.field_status', 'COMPLETED')
            ->where('housing_statuses.type', $type)
            ->whereBetween('housing_statuses.updated_at', [$dailyHousingAchievementStartDate, $endDate])
            ->whereIn('assessment_statuses.name', $trackedAuditedStatuses)
            ->selectRaw('DATE(housing_statuses.updated_at) as achievement_date, COUNT(DISTINCT housing_statuses.housing_id) as total')
            ->groupBy('achievement_date')
            ->pluck('total', 'achievement_date');

        $dailyHousingAchievementDates = [];
        $dailyHousingAchievementSeries = [];
        $cumulativeDailyHousingAchievementTotal = 0;

        if ($dailyHousingAchievementStartDate->lte($endDate)) {
            $cursorDate = $dailyHousingAchievementStartDate->copy();

            while ($cursorDate->lte($endDate)) {
                $dateKey = $cursorDate->toDateString();
                $dailyAchievementTotal = (int) ($dailyHousingAchievementRaw[$dateKey] ?? 0);

                if ($dailyAchievementTotal > 0) {
                    $cumulativeDailyHousingAchievementTotal += $dailyAchievementTotal;
                    $dailyHousingAchievementDates[] = $dateKey;
                    $dailyHousingAchievementSeries[] = $cumulativeDailyHousingAchievementTotal;
                }

                $cursorDate->addDay();
            }
        }

        return [
            'summary' => [
                'audited_buildings_count' => $auditedBuildingsCount,
                'audited_housing_units_count' => $auditedHousingUnitsCount,
                'audited_buildings_percentage' => $totalBuildingsCount > 0 ? round(($auditedBuildingsCount / $totalBuildingsCount) * 100, 1) : 0,
                'audited_housing_units_percentage' => $totalHousingUnitsCount > 0 ? round(($auditedHousingUnitsCount / $totalHousingUnitsCount) * 100, 1) : 0,
            ],
            'charts' => [
                'building_status_labels' => array_values($buildingStatuses),
                'building_status_series' => collect(array_keys($buildingStatuses))
                    ->map(fn ($statusName) => (int) ($buildingStatusRaw[$statusName] ?? 0))
                    ->values()
                    ->all(),
                'housing_status_labels' => array_values($housingStatuses),
                'housing_status_series' => collect(array_keys($housingStatuses))
                    ->map(fn ($statusName) => (int) ($housingStatusRaw[$statusName] ?? 0))
                    ->values()
                    ->all(),
                'comparison_categories' => ['Buildings', 'Housing Units'],
                'comparison_audited_series' => [$auditedBuildingsCount, $auditedHousingUnitsCount],
                'comparison_total_series' => [$totalBuildingsCount, $totalHousingUnitsCount],
                'daily_housing_achievement_start_date' => $dailyHousingAchievementStartDate->toDateString(),
                'daily_housing_achievement_labels' => $dailyHousingAchievementDates,
                'daily_housing_achievement_series' => $dailyHousingAchievementSeries,
            ],
        ];
    }
}
