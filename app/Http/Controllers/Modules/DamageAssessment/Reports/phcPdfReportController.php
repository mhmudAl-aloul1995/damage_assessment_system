<?php

namespace App\Http\Controllers\Modules\DamageAssessment\Reports;

use App\Http\Controllers\Controller;
use App\Services\DamageAssessment\Reports\phcPdfReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class phcPdfReportController extends Controller
{
    public function __construct(
        protected phcPdfReportService $reportService
    ) {}

    public function index(Request $request): View
    {
        $data = $this->reportService->build($request);

        return view('modules.damage-assessment.reports.phc', $data);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $data = $this->reportService->build($request);

        $directory = storage_path('app/public/reports');

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $fileName = 'phc-report-'.now()->format('Y-m-d-H-i-s').'-'.Str::random(5).'.pdf';
        $filePath = $directory.DIRECTORY_SEPARATOR.$fileName;

        if (! $this->shouldUseBrowsershot()) {
            $html = view('modules.damage-assessment.reports.indas-mpdf', $data)->render();
            $this->saveWithMpdf($html, $filePath);

            return response()->download($filePath, $fileName);
        }

        $html = view('modules.damage-assessment.reports.indas-pdf', $data)->render();

        $browser = Browsershot::html($html)
            ->format('A4')
            ->landscape()
            ->showBackground()
            ->margins(0, 0, 0, 0)
            ->noSandbox()
            ->setNodeModulePath(base_path('node_modules'))
            ->addChromiumArguments([
                'disable-dev-shm-usage',
                'disable-gpu',
                'font-render-hinting=medium',
            ])
            ->waitUntilNetworkIdle()
            ->timeout(180);

        if (config('services.browsershot.node_binary')) {
            $browser->setNodeBinary(config('services.browsershot.node_binary'));
        }

        if (config('services.browsershot.npm_binary')) {
            $browser->setNpmBinary(config('services.browsershot.npm_binary'));
        }

        if ($chromePath = $this->chromePath()) {
            $browser->setChromePath($chromePath);
        }

        $browser->savePdf($filePath);

        return response()->download($filePath, $fileName);
    }

    private function shouldUseBrowsershot(): bool
    {
        return config('services.damage_assessment_pdf.engine') === 'browsershot'
            && $this->chromePath() !== null;
    }

    private function saveWithMpdf(string $html, string $filePath): void
    {
        $html = (string) preg_replace('/@font-face\s*\{.*?\}/s', '', $html);

        $temporaryDirectory = storage_path('app/mpdf');

        if (! File::exists($temporaryDirectory)) {
            File::makeDirectory($temporaryDirectory, 0755, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => $temporaryDirectory,
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->WriteHTML($html);
        $mpdf->Output($filePath, Destination::FILE);
    }

    private function chromePath(): ?string
    {
        $configuredPath = config('services.browsershot.chrome_path');

        if (is_string($configuredPath) && $configuredPath !== '' && File::exists($configuredPath)) {
            return $configuredPath;
        }

        $candidates = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        ];

        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        $cacheRoots = array_filter([
            $_SERVER['LOCALAPPDATA'] ?? null,
            ($_SERVER['USERPROFILE'] ?? null).'\\.cache\\puppeteer',
        ]);

        foreach ($cacheRoots as $cacheRoot) {
            foreach (glob($cacheRoot.'\\**\\chrome.exe') ?: [] as $candidate) {
                if (File::exists($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }
}
