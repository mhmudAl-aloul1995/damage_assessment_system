<?php

namespace App\Http\Controllers\Modules\DamageAssessment\Reports;

use App\Http\Controllers\Controller;
use App\Services\DamageAssessment\Reports\IndasPdfReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IndasPdfReportController extends Controller
{
    public function __construct(
        protected IndasPdfReportService $reportService
    ) {}

    public function index(Request $request): View
    {
        $data = $this->reportService->build($request);

        return view('modules.damage-assessment.reports.indas', $data);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $data = $this->reportService->build($request);

        $html = view('modules.damage-assessment.reports.indas-pdf', $data)->render();

        $directory = storage_path('app/public/reports');

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $fileName = 'indas-report-'.now()->format('Y-m-d-H-i-s').'-'.Str::random(5).'.pdf';
        $filePath = $directory.DIRECTORY_SEPARATOR.$fileName;

        $browser = Browsershot::html($html)
            ->format('A4')
            ->landscape()
            ->showBackground()
            ->margins(0, 0, 0, 0)
            ->noSandbox()
            ->setNodeModulePath(base_path('node_modules'))
            ->addChromiumArguments([
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--font-render-hinting=medium',
            ])
            ->waitUntilNetworkIdle()
            ->timeout(180);

        if (config('services.browsershot.node_binary')) {
            $browser->setNodeBinary(config('services.browsershot.node_binary'));
        }

        if (config('services.browsershot.npm_binary')) {
            $browser->setNpmBinary(config('services.browsershot.npm_binary'));
        }

        if (config('services.browsershot.chrome_path')) {
            $browser->setChromePath(config('services.browsershot.chrome_path'));
        }

        $browser->savePdf($filePath);

        return response()->download($filePath, $fileName);
    }
}
