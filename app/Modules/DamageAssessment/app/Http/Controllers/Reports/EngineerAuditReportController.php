<?php

declare(strict_types=1);

namespace App\Modules\DamageAssessment\Http\Controllers\Reports;

use App\Exports\EngineerAuditReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\AreaProductivityReportFilterRequest;
use App\Modules\DamageAssessment\Services\Reports\EngineerAuditReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EngineerAuditReportController extends Controller
{
    public function __construct()
    {
        // $this->middleware('role:Database Officer|Project Officer|undp-Project Manager|Auditing Supervisor|Area Manager|QC/QA Engineer');
    }

    public function index(AreaProductivityReportFilterRequest $request, EngineerAuditReportService $reportService): View
    {
        return ViewFactory::make('damage-assessment::reports.engineer_audit', $reportService->build($request->validated()));
    }

    public function export(AreaProductivityReportFilterRequest $request, EngineerAuditReportService $reportService): BinaryFileResponse
    {
        $report = $reportService->build($request->validated());

        return Excel::download(
            new EngineerAuditReportExport(
                $report['rows'],
                $report['summary'],
                $report['total_label'],
            ),
            $report['active_report_type'] === EngineerAuditReportService::REPORT_TYPE_HOUSING_UNITS
                ? 'engineer-audit-housing-units-report.xlsx'
                : 'engineer-audit-buildings-report.xlsx',
        );
    }
}
