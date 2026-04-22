<?php

declare(strict_types=1);

namespace App\Http\Controllers\Report;

use App\Exports\AreaProductivityExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\AreaProductivityReportFilterRequest;
use App\Services\AreaProductivityReportService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AreaProductivityReportController extends Controller
{
    public function __construct(private readonly AreaProductivityReportService $reportService)
    {
        $this->middleware('role:Database Officer|Project Officer|Auditing Supervisor|Area Manager');
    }

    public function housingUnits(AreaProductivityReportFilterRequest $request): View
    {
        return $this->renderReport(AreaProductivityReportService::TYPE_HOUSING_UNITS, $request->validated());
    }

    public function buildings(AreaProductivityReportFilterRequest $request): View
    {
        return $this->renderReport(AreaProductivityReportService::TYPE_BUILDINGS, $request->validated());
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
        return view('DamageAssessment.Reports.area_productivity', $this->reportService->build($type, $filters));
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
            ),
            "{$type}_area_productivity.xlsx",
        );
    }
}
