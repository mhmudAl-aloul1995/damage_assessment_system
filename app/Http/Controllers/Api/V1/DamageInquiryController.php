<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DamageInquiryRequest;
use App\Http\Requests\Api\MobileCitizenInquiryRequest;
use App\services\MobileDamageDetailService;
use App\services\SectorOverviewService;
use App\Support\Navigation\SectorNavigation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DamageInquiryController extends Controller
{
    public const SECTORS = ['buildings', 'housing-units', 'public-buildings', 'road-facilities', 'cso-surveys'];

    public function __construct(private SectorOverviewService $overview, private MobileDamageDetailService $details) {}

    public function sectors(Request $request): JsonResponse
    {
        $sectors = collect(self::SECTORS)->filter(fn (string $sector): bool => collect(SectorNavigation::forUser($sector, $request->user()))->contains('key', 'overview'))
            ->map(fn (string $sector): array => ['key' => $sector, 'title' => __(SectorNavigation::title($sector)), 'citizen_inquiry' => $this->details->canAudit($request->user(), $sector)])->values();

        return response()->json(['data' => $sectors])->header('Cache-Control', 'private, no-store');
    }

    public function records(DamageInquiryRequest $request, string $sector): JsonResponse
    {
        return response()->json($this->overview->records($sector, $request->inquiryFilters()))->header('Cache-Control', 'private, no-store');
    }

    public function show(DamageInquiryRequest $request, string $sector, int $record): JsonResponse
    {
        return response()->json(['data' => $this->details->detail($request->user(), $sector, $record, $request->inquiryFilters())])->header('Cache-Control', 'private, no-store');
    }

    public function filters(DamageInquiryRequest $request, string $sector): JsonResponse
    {
        return response()->json(['data' => [
            'municipalities' => $this->overview->options($sector, 'municipality', array_intersect_key($request->inquiryFilters(), ['_allowed_phases' => true])),
            'neighborhoods' => $this->overview->options($sector, 'neighborhood', $request->inquiryFilters()),
            'damage_statuses' => $this->overview->damageBuckets($sector),
            'audit_statuses' => $this->overview->auditBuckets($sector),
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function history(DamageInquiryRequest $request, string $sector, int $record): JsonResponse
    {
        return response()->json($this->details->history($request->user(), $sector, $record, $request->inquiryFilters(), $request->validated('track', 'engineering'), (int) $request->validated('page', 1)))->header('Cache-Control', 'private, no-store');
    }

    public function attachments(DamageInquiryRequest $request, string $sector, int $record): JsonResponse
    {
        return response()->json(['data' => $this->details->attachments($request->user(), $sector, $record, $request->inquiryFilters())])->header('Cache-Control', 'private, no-store');
    }

    public function attachment(DamageInquiryRequest $request, string $sector, int $record, int $attachment): Response
    {
        $file = $this->details->attachment($request->user(), $sector, $record, $attachment, $request->inquiryFilters());

        return response($file['body'], 200, ['Content-Type' => $file['content_type'], 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function citizens(MobileCitizenInquiryRequest $request): JsonResponse
    {
        $rows = [];
        $total = 0;
        $lastPage = 1;
        $filters = [...$request->validated(), '_inquiry' => true, '_citizen_search' => true];
        $phases = collect($request->user()->allowed_phase_numbers ?? [])->map(fn (mixed $phase): int => (int) $phase)->filter(fn (int $phase): bool => $phase > 0)->values()->all();
        if ($phases !== []) {
            $filters['_allowed_phases'] = $phases;
        }
        foreach (['buildings', 'housing-units'] as $sector) {
            if (! $this->details->canAudit($request->user(), $sector) || ! collect(SectorNavigation::forUser($sector, $request->user()))->contains('key', 'overview')) {
                continue;
            }
            $result = $this->overview->records($sector, $filters);
            $rows = [...$rows, ...array_map(fn (array $row): array => [...$row, 'sector' => $sector], $result['data'])];
            $total += $result['total'];
            $lastPage = max($lastPage, $result['last_page']);
        }

        return response()->json(['data' => $rows, 'total' => $total, 'current_page' => (int) ($filters['page'] ?? 1), 'last_page' => $lastPage])->header('Cache-Control', 'private, no-store');
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
