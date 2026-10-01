<?php

declare(strict_types=1);

namespace App\Modules\DamageAssessment\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\SectorOverviewRequest;
use App\services\SectorOverviewService;
use App\Support\Navigation\SectorNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class SectorOverviewController extends Controller
{
    public function __construct(private SectorOverviewService $overview) {}

    public function show(SectorOverviewRequest $request, string $sector): View
    {
        return view('damage-assessment::dashboard.sector-overview', [
            'sector' => $sector,
            'sectorTitle' => __(SectorNavigation::title($sector)),
            'filters' => $request->validated(),
            'statistics' => $this->overview->statistics($sector, $request->validated()),
            'municipalities' => $this->overview->options($sector, 'municipality'),
            'damageBuckets' => $this->overview->damageBuckets($sector),
        ]);
    }

    public function stats(SectorOverviewRequest $request, string $sector): JsonResponse
    {
        return response()->json($this->overview->statistics($sector, $request->validated()))->header('Cache-Control', 'private, no-store');
    }

    public function map(SectorOverviewRequest $request, string $sector): JsonResponse
    {
        return response()->json($this->overview->mapFeatures($sector, $request->validated()))->header('Cache-Control', 'private, no-store');
    }
}
