<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ApproveMissingCitizenIdentityNameMatchRequest;
use App\Http\Requests\Reports\BulkApproveMissingCitizenIdentityNameMatchesRequest;
use App\Models\AccessCivilRegistryRecord;
use App\Models\HousingUnit;
use App\Models\MissingCitizenIdentityApproval;
use App\Models\MissingCitizenIdentityReport;
use App\services\ArcgisService;
use App\Support\ArabicNameNormalizer;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

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
                'id' => 'citizen:'.$citizen->id,
                'id_card_no' => (string) $citizen->id_card_no,
                'full_name' => $citizen->full_name ?: '-',
                'source' => __('ui.missing_citizen_identities.source_citizens'),
            ])
            ->values();

        $sgazaRecords = $this->searchSgazaCivilRegistry($search);
        $accessRecords = $this->searchAccessCivilRegistry($search);

        return response()->json([
            'data' => $sgazaRecords
                ->merge($citizens)
                ->merge($accessRecords)
                ->unique('id_card_no')
                ->take(20)
                ->values(),
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
                'source' => __('ui.missing_citizen_identities.source_citizens'),
            ])
            ->values();

        $sgazaCandidates = $this->sgazaNameCandidates($report->normalized_owner_name);

        return response()->json([
            'data' => $sgazaCandidates
                ->merge($candidates)
                ->unique('id_card_no')
                ->take(20)
                ->values(),
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

        $result = $this->approveReportWithCitizen($report, $citizen, $request->user()?->id, $arcgisService);

        if (! $result['success'] && ($result['reason'] ?? null) === 'missing_housing_unit') {
            return response()->json([
                'message' => __('ui.missing_citizen_identities.housing_unit_missing'),
            ], 404);
        }

        return response()->json([
            'message' => ($result['arcgis_success'] ?? false)
                ? __('ui.missing_citizen_identities.approved_success')
                : __('ui.missing_citizen_identities.approved_with_arcgis_error'),
            'arcgis_status' => $result['arcgis_status'] ?? 'failed',
        ]);
    }

    public function bulkApproveNameMatches(
        BulkApproveMissingCitizenIdentityNameMatchesRequest $request,
        ArcgisService $arcgisService
    ): JsonResponse {
        $reports = MissingCitizenIdentityReport::query()
            ->whereIn('id', $request->input('report_ids', []))
            ->whereNull('approved_at')
            ->get();

        $approved = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($reports as $report) {
            $citizen = $this->automaticApprovalCitizen($report);

            if ($citizen === null) {
                $skipped++;

                continue;
            }

            $result = $this->approveReportWithCitizen($report, $citizen, $request->user()?->id, $arcgisService);

            if ($result['success']) {
                $approved++;
            } else {
                $failed++;
            }
        }

        return response()->json([
            'message' => __('ui.missing_citizen_identities.bulk_approved_success', [
                'approved' => $approved,
                'failed' => $failed,
                'skipped' => $skipped,
            ]),
            'approved' => $approved,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);
    }

    private function approvalCitizen(ApproveMissingCitizenIdentityNameMatchRequest $request, MissingCitizenIdentityReport $report): ?object
    {
        if ($request->filled('citizen_id')) {
            $selectedCitizen = (string) $request->input('citizen_id');

            if (str_starts_with($selectedCitizen, 'access:')) {
                $record = AccessCivilRegistryRecord::query()->find((int) Str::after($selectedCitizen, 'access:'));

                if (! $record instanceof AccessCivilRegistryRecord || ! filled($record->id_card_no)) {
                    return null;
                }

                return (object) [
                    'id' => 0,
                    'id_card_no' => $record->id_card_no,
                    'full_name' => $record->full_name,
                ];
            }

            if (str_starts_with($selectedCitizen, 'sgaza:')) {
                $record = $this->sgazaRecordByIdNumber((string) Str::after($selectedCitizen, 'sgaza:'));

                if ($record === null) {
                    return null;
                }

                return (object) [
                    'id' => 0,
                    'id_card_no' => $record->id_card_no,
                    'full_name' => $record->full_name,
                ];
            }

            $selectedCitizen = str_starts_with($selectedCitizen, 'citizen:')
                ? (string) Str::after($selectedCitizen, 'citizen:')
                : $selectedCitizen;

            return DB::table($this->citizensTable())
                ->select(['id', 'id_card_no', 'full_name'])
                ->where('id', (int) $selectedCitizen)
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

    private function automaticApprovalCitizen(MissingCitizenIdentityReport $report): ?object
    {
        if ($report->name_match_status !== 'matched' || ! filled($report->matched_citizen_id_card_no)) {
            return null;
        }

        return (object) [
            'id' => $report->matched_citizen_id,
            'id_card_no' => $report->matched_citizen_id_card_no,
            'full_name' => $report->matched_citizen_full_name,
        ];
    }

    /**
     * @return array{success: bool, arcgis_success?: bool, arcgis_status?: string, reason?: string}
     */
    private function approveReportWithCitizen(
        MissingCitizenIdentityReport $report,
        object $citizen,
        ?int $userId,
        ArcgisService $arcgisService
    ): array {
        $housingUnit = HousingUnit::query()->find($report->housing_unit_id);

        if (! $housingUnit instanceof HousingUnit) {
            return [
                'success' => false,
                'reason' => 'missing_housing_unit',
            ];
        }

        $oldIdNumber = (string) $housingUnit->id_number1;
        $newIdNumber = (string) $citizen->id_card_no;

        DB::transaction(function () use ($housingUnit, $report, $oldIdNumber, $newIdNumber, $userId, $citizen): void {
            $housingUnit->forceFill([
                'id_number1' => $newIdNumber,
            ])->save();

            $report->forceFill([
                'matched_citizen_id' => $citizen->id,
                'matched_citizen_id_card_no' => $citizen->id_card_no,
                'matched_citizen_full_name' => $citizen->full_name,
                'approved_at' => now(),
                'approved_by' => $userId,
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
                'approved_by' => $userId,
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

        return [
            'success' => true,
            'arcgis_success' => (bool) ($arcgisResult['success'] ?? false),
            'arcgis_status' => $arcgisResult['status'] ?? 'failed',
        ];
    }

    private function citizensTable(): string
    {
        if (app()->environment('testing')) {
            return 'citizens';
        }

        return 'phc_dashboard.citizens';
    }

    private function searchAccessCivilRegistry(string $search): Collection
    {
        if (! Schema::hasTable('access_civil_registry_records')) {
            return collect();
        }

        $query = AccessCivilRegistryRecord::query()
            ->select(['id', 'id_card_no', 'full_name', 'mother_name', 'neighborhood', 'birth_date']);

        if (ctype_digit($search)) {
            $query->where('id_card_no', 'like', $search.'%');
        } else {
            $normalizedSearch = ArabicNameNormalizer::normalize($search);

            if ($normalizedSearch === '') {
                return collect();
            }

            $query->where('full_name_normalized', 'like', $normalizedSearch.'%');
        }

        return $query
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(fn (AccessCivilRegistryRecord $record): array => [
                'id' => 'access:'.$record->id,
                'id_card_no' => (string) $record->id_card_no,
                'full_name' => $record->full_name ?: '-',
                'source' => __('ui.missing_citizen_identities.source_access'),
                'details' => collect([
                    $record->mother_name ? __('ui.missing_citizen_identities.mother_name').': '.$record->mother_name : null,
                    $record->neighborhood ? __('ui.missing_citizen_identities.neighborhood').': '.$record->neighborhood : null,
                    $record->birth_date ? __('ui.missing_citizen_identities.birth_date').': '.$record->birth_date->format('Y-m-d') : null,
                ])->filter()->implode(' | '),
            ]);
    }

    private function searchSgazaCivilRegistry(string $search): Collection
    {
        if (! Schema::hasTable('sgaza') || ! Schema::hasColumn('sgaza', 'id_number')) {
            return collect();
        }

        $query = DB::table('sgaza');

        if (ctype_digit($search)) {
            $query->where('id_number', 'like', $search.'%');
        } else {
            $normalizedSearch = ArabicNameNormalizer::normalize($search);

            if ($normalizedSearch === '') {
                return collect();
            }

            $this->applySgazaNameSearch($query, $search, $normalizedSearch);
        }

        return $query
            ->select([
                'id_number',
                'first_name',
                'father_name',
                'grandfather_name',
                'family_name',
                'mother_name',
                'الحي as neighborhood',
                'تاريخ الميلاد as birth_date',
                ...(Schema::hasColumn('sgaza', 'full_name') ? ['full_name'] : []),
            ])
            ->orderBy('id_number')
            ->limit(20)
            ->get()
            ->map(fn ($record): array => $this->sgazaRecordToCandidate($record));
    }

    private function applySgazaNameSearch(QueryBuilder $query, string $search, string $normalizedSearch): void
    {
        $tokens = collect(preg_split('/\s+/u', trim($search)) ?: [])
            ->map(fn (string $token): string => trim($token))
            ->filter()
            ->values();

        $query->where(function (QueryBuilder $query) use ($normalizedSearch, $tokens): void {
            if (Schema::hasColumn('sgaza', 'full_name_normalized')) {
                $query->where('full_name_normalized', 'like', $normalizedSearch.'%');
            }

            if ($tokens->isEmpty()) {
                return;
            }

            $query->orWhere(function (QueryBuilder $query) use ($tokens): void {
                $nameColumns = [
                    'first_name',
                    'father_name',
                    'grandfather_name',
                    'family_name',
                ];

                foreach ($tokens->take(4)->values() as $index => $token) {
                    $query->where($nameColumns[$index], 'like', $token.'%');
                }
            });
        });
    }

    private function sgazaNameCandidates(?string $normalizedOwnerName): Collection
    {
        if (
            ! filled($normalizedOwnerName)
            || ! Schema::hasTable('sgaza')
            || ! Schema::hasColumn('sgaza', 'full_name_normalized')
        ) {
            return collect();
        }

        return DB::table('sgaza')
            ->select([
                'id_number',
                'first_name',
                'father_name',
                'grandfather_name',
                'family_name',
                'mother_name',
                'الحي as neighborhood',
                'تاريخ الميلاد as birth_date',
                ...(Schema::hasColumn('sgaza', 'full_name') ? ['full_name'] : []),
            ])
            ->where('full_name_normalized', $normalizedOwnerName)
            ->orderBy('id_number')
            ->limit(20)
            ->get()
            ->map(fn ($record): array => $this->sgazaRecordToCandidate($record));
    }

    private function sgazaRecordByIdNumber(string $idNumber): ?object
    {
        if (! Schema::hasTable('sgaza') || ! Schema::hasColumn('sgaza', 'id_number')) {
            return null;
        }

        $record = DB::table('sgaza')
            ->where('id_number', $idNumber)
            ->select([
                'id_number',
                'first_name',
                'father_name',
                'grandfather_name',
                'family_name',
                ...(Schema::hasColumn('sgaza', 'full_name') ? ['full_name'] : []),
            ])
            ->first();

        if ($record === null) {
            return null;
        }

        return (object) [
            'id_card_no' => $record->id_number,
            'full_name' => $this->sgazaFullName($record),
        ];
    }

    private function sgazaRecordToCandidate(object $record): array
    {
        return [
            'id' => 'sgaza:'.$record->id_number,
            'id_card_no' => (string) $record->id_number,
            'full_name' => $this->sgazaFullName($record),
            'source' => __('ui.missing_citizen_identities.source_sgaza'),
            'details' => collect([
                filled($record->mother_name ?? null) ? __('ui.missing_citizen_identities.mother_name').': '.$record->mother_name : null,
                filled($record->neighborhood ?? null) ? __('ui.missing_citizen_identities.neighborhood').': '.$record->neighborhood : null,
                filled($record->birth_date ?? null) ? __('ui.missing_citizen_identities.birth_date').': '.date('Y-m-d', strtotime((string) $record->birth_date)) : null,
            ])->filter()->implode(' | '),
        ];
    }

    private function sgazaFullName(object $record): string
    {
        if (filled($record->full_name ?? null)) {
            return (string) $record->full_name;
        }

        $fullName = trim(implode(' ', array_filter([
            trim((string) ($record->first_name ?? '')),
            trim((string) ($record->father_name ?? '')),
            trim((string) ($record->grandfather_name ?? '')),
            trim((string) ($record->family_name ?? '')),
        ])));

        return $fullName !== '' ? $fullName : '-';
    }
}
