<?php

declare(strict_types=1);

namespace App\Modules\DamageAssessment\Http\Controllers\Reports;

use App\Exports\AreaProductivityExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\AreaProductivityReportFilterRequest;
use App\Modules\DamageAssessment\Services\Reports\AreaProductivityReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AreaProductivityReportController extends Controller
{
    public function __construct(private readonly AreaProductivityReportService $reportService)
    {
        $this->middleware('role:Database Officer|Project Officer|undp-Project Manager|Auditing Supervisor|Area Manager');
    }

    public function housingUnits(AreaProductivityReportFilterRequest $request): View
    {
        return $this->renderReport(AreaProductivityReportService::TYPE_HOUSING_UNITS, $request->validated());
    }

    public function buildings(AreaProductivityReportFilterRequest $request): View
    {
        return $this->renderReport(AreaProductivityReportService::TYPE_BUILDINGS, $request->validated());
    }

    public function housingUnitsData(AreaProductivityReportFilterRequest $request): JsonResponse
    {
        return $this->reportData(AreaProductivityReportService::TYPE_HOUSING_UNITS, $request->validated());
    }

    public function buildingsData(AreaProductivityReportFilterRequest $request): JsonResponse
    {
        return $this->reportData(AreaProductivityReportService::TYPE_BUILDINGS, $request->validated());
    }

    public function publicBuildings(AreaProductivityReportFilterRequest $request): View
    {
        return $this->renderReport(AreaProductivityReportService::TYPE_PUBLIC_BUILDINGS, $request->validated());
    }

    public function roadFacilities(AreaProductivityReportFilterRequest $request): View
    {
        return $this->renderReport(AreaProductivityReportService::TYPE_ROAD_FACILITIES, $request->validated());
    }

    public function exportHousingUnits(AreaProductivityReportFilterRequest $request): BinaryFileResponse
    {
        return $this->exportReport(AreaProductivityReportService::TYPE_HOUSING_UNITS, $request->validated());
    }

    public function exportBuildings(AreaProductivityReportFilterRequest $request): BinaryFileResponse
    {
        return $this->exportReport(AreaProductivityReportService::TYPE_BUILDINGS, $request->validated());
    }

    public function exportPublicBuildings(AreaProductivityReportFilterRequest $request): BinaryFileResponse
    {
        return $this->exportReport(AreaProductivityReportService::TYPE_PUBLIC_BUILDINGS, $request->validated());
    }

    public function exportRoadFacilities(AreaProductivityReportFilterRequest $request): BinaryFileResponse
    {
        return $this->exportReport(AreaProductivityReportService::TYPE_ROAD_FACILITIES, $request->validated());
    }

    private function renderReport(string $type, array $filters): View
    {
        return view('damage-assessment::reports.area_productivity', $this->reportService->build($type, $filters));
    }

    private function reportData(string $type, array $filters): JsonResponse
    {
        $report = $this->reportService->build($type, $filters);

        return response()->json([
            'rows' => $report['rows']->map(fn (object $row): array => [
                'total_count' => (int) ($row->total_count ?? 0),
                'housing_units_count' => (int) ($row->housing_units_count ?? 0),
                'tda_range' => (int) ($row->tda_range ?? 0),
                'pda_range' => (int) ($row->pda_range ?? 0),
                'cra_range' => (int) ($row->cra_range ?? 0),
                'no_damage_count' => (int) ($row->no_damage_count ?? 0),
                'unclassified_count' => (int) ($row->unclassified_count ?? 0),
                'no_eng' => (int) ($row->no_eng ?? 0),
                'neighborhood' => $row->neighborhood ?: __('multilingual.area_productivity_reports.labels.not_available'),
                'municipalitie' => $row->municipalitie ?: __('multilingual.area_productivity_reports.labels.not_available'),
                'governorate' => $row->governorate ?: __('multilingual.area_productivity_reports.labels.not_available'),
                'sector' => __($report['sector_key']),
            ])->values(),
            'summary' => $report['summary'],
            'start_date' => $report['start_date'],
            'end_date' => $report['end_date'],
            'date_range_label' => $report['date_range_label'],
            'title' => __($report['title_key']),
        ]);
    }

    private function exportReport(string $type, array $filters): BinaryFileResponse
    {
        $report = $this->reportService->build($type, $filters);
        $rows = $this->reportService->exportRows($type, $filters);

        return Excel::download(
            new AreaProductivityExport(
                $rows,
                $report['start_date'],
                $report['end_date'],
                __($report['title_key']),
                __($report['sector_key']),
                $type,
                $type === AreaProductivityReportService::TYPE_BUILDINGS,
            ),
            "{$type}_area_productivity.xlsx",
        );
    }
}
