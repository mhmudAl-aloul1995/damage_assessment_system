<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DamageInquiryRequest;
use App\services\SectorOverviewService;
use App\Support\Navigation\SectorNavigation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DamageInquiryController extends Controller
{
    public const SECTORS = ['buildings', 'housing-units', 'public-buildings', 'road-facilities', 'cso-surveys'];

    public function __construct(private SectorOverviewService $overview) {}

    public function sectors(Request $request): JsonResponse
    {
        $sectors = collect(self::SECTORS)->filter(fn (string $sector): bool => collect(SectorNavigation::forUser($sector, $request->user()))->contains('key', 'overview'))
            ->map(fn (string $sector): array => ['key' => $sector, 'title' => __(SectorNavigation::title($sector))])->values();

        return response()->json(['data' => $sectors])->header('Cache-Control', 'private, no-store');
    }

    public function records(DamageInquiryRequest $request, string $sector): JsonResponse
    {
        return response()->json($this->overview->records($sector, $request->inquiryFilters()))->header('Cache-Control', 'private, no-store');
    }

    public function show(DamageInquiryRequest $request, string $sector, int $record): JsonResponse
    {
        return response()->json(['data' => $this->overview->record($sector, $record, $request->inquiryFilters())])->header('Cache-Control', 'private, no-store');
    }

    public function map(DamageInquiryRequest $request, string $sector): JsonResponse
    {
        return response()->json($this->overview->mapFeatures($sector, $request->inquiryFilters()))->header('Cache-Control', 'private, no-store');
    }

    public function stats(DamageInquiryRequest $request, string $sector): JsonResponse
    {
        return response()->json($this->overview->statistics($sector, $request->inquiryFilters()))->header('Cache-Control', 'private, no-store');
    }
}
