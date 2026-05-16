<?php

namespace App\Http\Controllers\Modules\DamageAssessment\Reports;

use App\Http\Controllers\Controller;
use App\Services\DamageAssessment\Reports\IndasPdfReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;

class IndasPdfReportController extends Controller
{
    public function __construct(
        protected IndasPdfReportService $reportService
    ) {}

    public function index(Request $request)
    {
        $data = $this->reportService->build($request);

        return view('modules.damage-assessment.pdf.indas-report', $data);
    }

    public function export(Request $request)
    {
        $data = $this->reportService->build($request);

        $html = view('modules.damage-assessment.pdf.indas-report', $data)->render();

        $directory = storage_path('app/public/reports');

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $fileName = 'indas-report-' . now()->format('Y-m-d-H-i-s') . '-' . Str::random(5) . '.pdf';
        $filePath = $directory . DIRECTORY_SEPARATOR . $fileName;

        $browser = Browsershot::html($html)
            ->format('A4')
            ->landscape()
            ->showBackground()
            ->margins(0, 0, 0, 0)
            ->waitUntilNetworkIdle()
            ->timeout(180);

        if (env('BROWSERSHOT_NODE_BINARY')) {
            $browser->setNodeBinary(env('BROWSERSHOT_NODE_BINARY'));
        }

        if (env('BROWSERSHOT_NPM_BINARY')) {
            $browser->setNpmBinary(env('BROWSERSHOT_NPM_BINARY'));
        }

        if (env('BROWSERSHOT_CHROME_PATH')) {
            $browser->setChromePath(env('BROWSERSHOT_CHROME_PATH'));
        }

        $browser->savePdf($filePath);

        return response()->download($filePath, $fileName);
    }
}