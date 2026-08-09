<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ApproveMissingCitizenIdentityNameMatchRequest;
use App\Models\HousingUnit;
use App\Models\MissingCitizenIdentityApproval;
use App\Models\MissingCitizenIdentityReport;
use App\services\ArcgisService;
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
        $perPage = 25;
        $afterId = max(0, $request->integer('after_id', 0));
        $search = trim($request->string('search')->toString());

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
                ])
                ->values(),
            'has_more' => $rows->count() > $perPage,
            'next_cursor' => $rows->take($perPage)->last()?->id,
            'total' => $total,
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

        if ($report->name_match_status !== 'matched' || ! filled($report->matched_citizen_id_card_no)) {
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
        $newIdNumber = (string) $report->matched_citizen_id_card_no;

        DB::transaction(function () use ($housingUnit, $report, $oldIdNumber, $newIdNumber, $request): void {
            $housingUnit->forceFill([
                'id_number1' => $newIdNumber,
            ])->save();

            $report->forceFill([
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
                'citizen_id' => $report->matched_citizen_id,
                'citizen_full_name' => $report->matched_citizen_full_name,
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
}
