<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ApproveMissingCitizenIdentityNameMatchRequest;
use App\Models\HousingUnit;
use App\Models\MissingCitizenIdentityApproval;
use App\Models\MissingCitizenIdentityReport;
use App\services\ArcgisService;
use App\Support\ArabicNameNormalizer;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class MissingCitizenIdentityController extends Controller
{
    public function index(): ViewContract
    {
        return View::make('damage-assessment::reports.missing-citizen-identities', [
            'totalMissingCitizenIdentities' => MissingCitizenIdentityReport::query()
                ->whereNull('approved_at')
                ->count(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 25), 1), 100);
        $afterId = max(0, $request->integer('after_id', 0));
        $search = trim($request->string('search')->toString());
        $nameMatchStatus = trim($request->string('name_match_status')->toString());

        $query = MissingCitizenIdentityReport::query()
            ->select([
                'id',
                'owner_name',
                'id_number',
                'name_match_status',
                'matched_citizen_id_card_no',
                'matched_citizen_full_name',
                'matched_citizens_count',
            ])
            ->whereNull('approved_at');

        if ($search !== '') {
            if (ctype_digit($search)) {
                $query->where('id_number', 'like', $search.'%');
            } else {
                $query->where('owner_name', 'like', '%'.$search.'%');
            }
        }

        if (in_array($nameMatchStatus, ['matched', 'ambiguous', 'not_found', 'no_owner_name', 'not_checked'], true)) {
            $query->where('name_match_status', $nameMatchStatus);
        }

        $total = (clone $query)->count();

        $query->when($afterId > 0, fn (Builder $query): Builder => $query->where('id', '>', $afterId));

        /** @var Collection<int, MissingCitizenIdentityReport> $rows */
        $rows = $query
            ->orderBy('id')
            ->limit($perPage + 1)
            ->get();

        return response()->json([
            'data' => $rows
                ->take($perPage)
                ->map(fn (MissingCitizenIdentityReport $report): array => [
                    'id' => $report->id,
                    'owner_name' => $report->owner_name ?: '-',
                    'id_number1' => (string) $report->id_number,
                    'name_match_status' => $report->name_match_status,
                    'matched_citizen_id_card_no' => $report->matched_citizen_id_card_no,
                    'matched_citizen_full_name' => $report->matched_citizen_full_name,
                    'matched_citizens_count' => $report->matched_citizens_count,
                    'can_approve_name_match' => $report->name_match_status === 'matched'
                        && filled($report->matched_citizen_id_card_no),
                    'can_show_name_candidates' => $report->name_match_status === 'ambiguous'
                        && $report->matched_citizens_count > 1,
                    'can_search_citizens' => in_array($report->name_match_status, ['not_found', 'no_owner_name'], true),
                ])
                ->values(),
            'has_more' => $rows->count() > $perPage,
            'next_cursor' => $rows->take($perPage)->last()?->id,
            'per_page' => $perPage,
            'total' => $total,
        ]);
    }

    public function citizenSearch(Request $request, MissingCitizenIdentityReport $report): JsonResponse
    {
        if ($report->approved_at !== null) {
            return response()->json([
                'message' => __('ui.missing_citizen_identities.already_approved'),
            ], 422);
        }

        $search = trim($request->string('q')->toString());

        if (mb_strlen($search) < 2) {
            return response()->json([
                'data' => [],
            ]);
        }

        $query = DB::table($this->citizensTable())
            ->select(['id', 'id_card_no', 'full_name'])
            ->where('status', 'A');

        if (ctype_digit($search)) {
            $query->where('id_card_no', 'like', $search.'%');
        } else {
            $normalizedSearch = ArabicNameNormalizer::normalize($search);

            if ($normalizedSearch === '') {
                return response()->json([
                    'data' => [],
                ]);
            }

            $query->where('full_name_normalized', 'like', $normalizedSearch.'%');
        }

        $citizens = $query
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(fn ($citizen): array => [
                'id' => $citizen->id,
                'id_card_no' => (string) $citizen->id_card_no,
                'full_name' => $citizen->full_name ?: '-',
            ])
            ->values();

        return response()->json([
            'data' => $citizens,
        ]);
    }

    public function nameCandidates(MissingCitizenIdentityReport $report): JsonResponse
    {
        if ($report->approved_at !== null) {
            return response()->json([
                'message' => __('ui.missing_citizen_identities.already_approved'),
            ], 422);
        }

        if (! filled($report->normalized_owner_name)) {
            return response()->json([
                'data' => [],
            ]);
        }

        $candidates = DB::table($this->citizensTable())
            ->select(['id', 'id_card_no', 'full_name'])
            ->where('status', 'A')
            ->where('full_name_normalized', $report->normalized_owner_name)
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(fn ($citizen): array => [
                'id' => $citizen->id,
                'id_card_no' => (string) $citizen->id_card_no,
                'full_name' => $citizen->full_name ?: '-',
            ])
            ->values();

        return response()->json([
            'data' => $candidates,
        ]);
    }

    public function approveNameMatch(
        ApproveMissingCitizenIdentityNameMatchRequest $request,
        MissingCitizenIdentityReport $report,
        ArcgisService $arcgisService
    ): JsonResponse {
        if ($report->approved_at !== null) {
            return response()->json([
                'message' => __('ui.missing_citizen_identities.already_approved'),
            ], 422);
        }

        $citizen = $this->approvalCitizen($request, $report);

        if ($citizen === null) {
            return response()->json([
                'message' => __('ui.missing_citizen_identities.approve_unavailable'),
            ], 422);
        }

        $housingUnit = HousingUnit::query()->find($report->housing_unit_id);

        if (! $housingUnit instanceof HousingUnit) {
            return response()->json([
                'message' => __('ui.missing_citizen_identities.housing_unit_missing'),
            ], 404);
        }

        $oldIdNumber = (string) $housingUnit->id_number1;
        $newIdNumber = (string) $citizen->id_card_no;

        DB::transaction(function () use ($housingUnit, $report, $oldIdNumber, $newIdNumber, $request, $citizen): void {
            $housingUnit->forceFill([
                'id_number1' => $newIdNumber,
            ])->save();

            $report->forceFill([
                'matched_citizen_id' => $citizen->id,
                'matched_citizen_id_card_no' => $citizen->id_card_no,
                'matched_citizen_full_name' => $citizen->full_name,
                'approved_at' => now(),
                'approved_by' => $request->user()?->id,
            ])->save();

            MissingCitizenIdentityApproval::query()->create([
                'missing_citizen_identity_report_id' => $report->id,
                'housing_unit_id' => $housingUnit->id,
                'housing_unit_objectid' => $housingUnit->objectid,
                'old_id_number' => $oldIdNumber,
                'new_id_number' => $newIdNumber,
                'owner_name' => $report->owner_name,
                'citizen_id' => $citizen->id,
                'citizen_full_name' => $citizen->full_name,
                'approved_by' => $request->user()?->id,
                'arcgis_sync_status' => 'pending',
            ]);
        });

        $arcgisResult = $arcgisService->updateHousingUnitIdentity($housingUnit->objectid, $newIdNumber);

        $approval = MissingCitizenIdentityApproval::query()
            ->where('missing_citizen_identity_report_id', $report->id)
            ->latest('id')
            ->first();

        $approval?->forceFill([
            'arcgis_sync_status' => $arcgisResult['status'] ?? 'failed',
            'arcgis_sync_message' => $arcgisResult['message'] ?? null,
            'arcgis_sync_response' => $arcgisResult['response'] ?? null,
        ])->save();

        $report->forceFill([
            'arcgis_sync_status' => $arcgisResult['status'] ?? 'failed',
            'arcgis_sync_message' => $arcgisResult['message'] ?? null,
        ])->save();

        return response()->json([
            'message' => ($arcgisResult['success'] ?? false)
                ? __('ui.missing_citizen_identities.approved_success')
                : __('ui.missing_citizen_identities.approved_with_arcgis_error'),
            'arcgis_status' => $arcgisResult['status'] ?? 'failed',
        ]);
    }

    private function approvalCitizen(ApproveMissingCitizenIdentityNameMatchRequest $request, MissingCitizenIdentityReport $report): ?object
    {
        if ($request->filled('citizen_id')) {
            return DB::table($this->citizensTable())
                ->select(['id', 'id_card_no', 'full_name'])
                ->where('id', $request->integer('citizen_id'))
                ->where('status', 'A')
                ->first();
        }

        if ($report->name_match_status !== 'matched' || ! filled($report->matched_citizen_id_card_no)) {
            return null;
        }

        return (object) [
            'id' => $report->matched_citizen_id,
            'id_card_no' => $report->matched_citizen_id_card_no,
            'full_name' => $report->matched_citizen_full_name,
        ];
    }

    private function citizensTable(): string
    {
        if (app()->environment('testing')) {
            return 'citizens';
        }

        return 'phc_dashboard.citizens';
    }
}
