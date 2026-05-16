<?php

namespace App\Http\Controllers\Modules\DamageAssessment\Reports;

use App\Exports\HlpAuditReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\AreaProductivityReportFilterRequest;
use App\Services\DamageAssessment\Reports\HlpAuditReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HlpAuditReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:Database Officer|Project Officer|undp-Project Manager|Auditing Supervisor|Area Manager');
    }

    public function index(AreaProductivityReportFilterRequest $request, HlpAuditReportService $reportService): View
    {
        return ViewFactory::make('modules.damage-assessment.reports.hlp_audit', $reportService->build($request->validated()));
    }

    public function export(AreaProductivityReportFilterRequest $request, HlpAuditReportService $reportService): BinaryFileResponse
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
}
