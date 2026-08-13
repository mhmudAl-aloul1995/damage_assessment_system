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
use Throwable;

class MissingCitizenIdentityController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:Database Officer|Auditing Supervisor|Project Officer');
    }

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
        $perPage = min(max($request->integer('per_page', 100), 100), 500);
        $afterId = max(0, $request->integer('after_id', 0));
        $search = trim($request->string('search')->toString());
        $unitObjectId = trim($request->string('unit_objectid')->toString());
        $issueType = trim($request->string('issue_type')->toString());
        $nameMatchStatus = trim($request->string('name_match_status')->toString());
        $identitySubject = trim($request->string('identity_subject')->toString());

        $query = MissingCitizenIdentityReport::query()
            ->select([
                'missing_citizen_identity_reports.id',
                'missing_citizen_identity_reports.identity_subject',
                'missing_citizen_identity_reports.identity_index',
                'missing_citizen_identity_reports.identity_number_field',
                'missing_citizen_identity_reports.owner_name',
                'missing_citizen_identity_reports.id_number',
                'missing_citizen_identity_reports.issue_type',
                'missing_citizen_identity_reports.name_match_status',
                'missing_citizen_identity_reports.matched_citizen_id_card_no',
                'missing_citizen_identity_reports.matched_citizen_full_name',
                'missing_citizen_identity_reports.matched_citizens_count',
                'housing_units.objectid as housing_unit_objectid',
                'housing_units.unit_owner as housing_unit_owner_name',
                'housing_units.id_number1 as housing_unit_owner_id_number',
                'housing_units.q_9_3_1_first_name',
                'housing_units.q_9_3_2_second_name__father',
                'housing_units.q_9_3_3_third_name__grandfather',
                'housing_units.q_9_3_4_last_name',
            ])
            ->leftJoin('housing_units', 'housing_units.id', '=', 'missing_citizen_identity_reports.housing_unit_id')
            ->whereNull('missing_citizen_identity_reports.approved_at');

        if ($search !== '') {
            if (ctype_digit($search)) {
                $query->where('missing_citizen_identity_reports.id_number', 'like', $search.'%');
            } else {
                $query->where('missing_citizen_identity_reports.owner_name', 'like', '%'.$search.'%');
            }
        }

        if ($unitObjectId !== '') {
            if (ctype_digit($unitObjectId)) {
                $query->where('housing_units.objectid', (int) $unitObjectId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (in_array($issueType, ['missing_civil_registry_identity', 'owner_without_identity'], true)) {
            $query->where('missing_citizen_identity_reports.issue_type', $issueType);
        }

        if (in_array($nameMatchStatus, ['matched', 'ambiguous', 'not_found', 'no_owner_name', 'not_checked'], true)) {
            $query->where('missing_citizen_identity_reports.name_match_status', $nameMatchStatus);
        }

        if (in_array($identitySubject, ['owner', 'spouse'], true)) {
            $query->where('missing_citizen_identity_reports.identity_subject', $identitySubject);
        }

        $total = (clone $query)->count();

        $query->when($afterId > 0, fn (Builder $query): Builder => $query->where('missing_citizen_identity_reports.id', '>', $afterId));

        /** @var Collection<int, MissingCitizenIdentityReport> $rows */
        $rows = $query
            ->orderBy('missing_citizen_identity_reports.id')
            ->limit($perPage + 1)
            ->get();

        return response()->json([
            'data' => $rows
                ->take($perPage)
                ->map(fn (MissingCitizenIdentityReport $report): array => [
                    'id' => $report->id,
                    'identity_subject' => $report->identity_subject,
                    'identity_index' => $report->identity_index,
                    'identity_label' => $this->identityLabel($report),
                    'housing_unit_owner_name' => $this->reportHousingUnitOwnerName($report),
                    'owner_name' => $report->owner_name ?: '-',
                    'housing_unit_objectid' => $report->housing_unit_objectid ? (string) $report->housing_unit_objectid : '-',
                    'housing_unit_owner_id_number' => filled($report->housing_unit_owner_id_number) ? (string) $report->housing_unit_owner_id_number : '-',
                    'id_number1' => filled($report->id_number) ? (string) $report->id_number : '-',
                    'issue_type' => $report->issue_type,
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
        $nameParts = $this->searchNameParts($request);

        if (mb_strlen($search) < 2 && $nameParts->isEmpty()) {
            return response()->json([
                'data' => [],
            ]);
        }

        $query = DB::table($this->citizensTable())
            ->select(['id', 'id_card_no', 'full_name'])
            ->where('status', 'A');

        if (ctype_digit($search)) {
            $query->where('id_card_no', 'like', $search.'%');
        } elseif ($search !== '') {
            $normalizedSearch = ArabicNameNormalizer::normalize($search);

            if ($normalizedSearch === '' && $nameParts->isEmpty()) {
                return response()->json([
                    'data' => [],
                ]);
            }

            $query->where('full_name_normalized', 'like', $normalizedSearch.'%');
        } else {
            $query->whereRaw('1 = 0');
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

        $husbandRegistryRecords = $this->searchHusbandRegistry($report, $search);
        $sgazaRecords = $this->searchSgazaCivilRegistry($search, $nameParts);
        $accessRecords = $this->searchAccessCivilRegistry($search);

        return response()->json([
            'data' => $husbandRegistryRecords
                ->merge($sgazaRecords)
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

        $husbandRegistryCandidates = $this->husbandRegistryNameCandidates($report);
        $sgazaCandidates = $this->sgazaNameCandidates($report->normalized_owner_name);

        return response()->json([
            'data' => $husbandRegistryCandidates
                ->merge($sgazaCandidates)
                ->merge($candidates)
                ->unique('id_card_no')
                ->take(20)
                ->values(),
        ]);
    }

    public function documents(MissingCitizenIdentityReport $report, ArcgisService $arcgisService): JsonResponse
    {
        $housingUnit = HousingUnit::query()->find($report->housing_unit_id);

        if (! $housingUnit instanceof HousingUnit) {
            return response()->json([
                'message' => __('ui.missing_citizen_identities.housing_unit_missing'),
                'data' => [],
            ], 404);
        }

        return response()->json([
            'data' => $this->housingUnitOwnershipDocuments($housingUnit, $arcgisService),
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

            if (str_starts_with($selectedCitizen, 'husband:')) {
                $record = $this->husbandRegistryRecordByIdNumber((string) Str::after($selectedCitizen, 'husband:'));

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

    private function identityNumberField(MissingCitizenIdentityReport $report): string
    {
        $field = (string) ($report->identity_number_field ?: 'id_number1');

        if (! in_array($field, ['id_number1', 'spouse1_id', 'spouse2_id', 'spouse3_id', 'spouse4_id'], true)) {
            return 'id_number1';
        }

        return $field;
    }

    private function identityNameField(MissingCitizenIdentityReport $report): ?string
    {
        $field = (string) $report->identity_name_field;

        if (! in_array($field, ['spouse1', 'spouse2', 'spouse3', 'spouse4'], true)) {
            return null;
        }

        return $field;
    }

    private function identityLabel(MissingCitizenIdentityReport $report): string
    {
        if ($report->identity_subject === 'spouse') {
            return match ((int) $report->identity_index) {
                1 => __('ui.missing_citizen_identities.identity_spouse_1'),
                2 => __('ui.missing_citizen_identities.identity_spouse_2'),
                3 => __('ui.missing_citizen_identities.identity_spouse_3'),
                4 => __('ui.missing_citizen_identities.identity_spouse_4'),
                default => __('ui.missing_citizen_identities.identity_spouse'),
            };
        }

        return __('ui.missing_citizen_identities.identity_owner');
    }

    private function reportHousingUnitOwnerName(MissingCitizenIdentityReport $report): string
    {
        $structuredName = trim(implode(' ', array_filter([
            trim((string) ($report->q_9_3_1_first_name ?? '')),
            trim((string) ($report->q_9_3_2_second_name__father ?? '')),
            trim((string) ($report->q_9_3_3_third_name__grandfather ?? '')),
            trim((string) ($report->q_9_3_4_last_name ?? '')),
        ])));

        $ownerName = $structuredName !== ''
            ? $structuredName
            : trim((string) ($report->housing_unit_owner_name ?? ''));

        return $ownerName !== '' ? $ownerName : '-';
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

        $identityNumberField = $this->identityNumberField($report);
        $identityNameField = $this->identityNameField($report);
        $oldIdNumber = (string) $housingUnit->{$identityNumberField};
        $newIdNumber = (string) $citizen->id_card_no;
        $housingUnitUpdates = [
            $identityNumberField => $newIdNumber,
        ];

        if (
            $identityNameField !== null
            && trim((string) $housingUnit->{$identityNameField}) === ''
            && filled($report->owner_name)
        ) {
            $housingUnitUpdates[$identityNameField] = (string) $report->owner_name;
        }

        DB::transaction(function () use ($housingUnit, $report, $oldIdNumber, $newIdNumber, $userId, $citizen, $housingUnitUpdates): void {
            $housingUnit->forceFill($housingUnitUpdates)->save();

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

        $arcgisResult = $arcgisService->updateHousingUnitFields($housingUnit->objectid, $housingUnitUpdates);

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

    private function husbandRegistryTable(): string
    {
        if (app()->environment('testing')) {
            return 'citizens_to_set_husband_id';
        }

        return 'phc_dashboard.citizens_to_set_husband_id';
    }

    /**
     * @return array<int, array{title: string, url: string, type: string, source: string, content_type?: string, size?: int|null}>
     */
    private function housingUnitOwnershipDocuments(HousingUnit $housingUnit, ArcgisService $arcgisService): array
    {
        $documents = collect();

        if (filled($housingUnit->objectid)) {
            try {
                $token = $arcgisService->getToken();
                $layerId = $arcgisService->getLayerId(HousingUnit::class);

                collect($arcgisService->getAttachments($housingUnit->objectid, $layerId, $token))
                    ->filter(fn (array $attachment): bool => str_contains(
                        mb_strtolower((string) ($attachment['name'] ?? '')),
                        'ownership_image'
                    ))
                    ->each(function (array $attachment) use ($arcgisService, $documents, $housingUnit, $layerId, $token): void {
                        $attachmentId = $attachment['id'] ?? null;

                        if (! filled($attachmentId)) {
                            return;
                        }

                        $url = $arcgisService->buildUrl($housingUnit->objectid, $attachmentId, $layerId, $token);

                        $documents->push([
                            'title' => (string) ($attachment['name'] ?? __('ui.missing_citizen_identities.ownership_document')),
                            'url' => $url,
                            'type' => $this->documentType($url, (string) ($attachment['contentType'] ?? '')),
                            'source' => __('ui.missing_citizen_identities.source_arcgis'),
                            'content_type' => (string) ($attachment['contentType'] ?? ''),
                            'size' => $attachment['size'] ?? null,
                        ]);
                    });
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $documents
            ->filter(fn (array $document): bool => filled($document['url'] ?? null))
            ->unique('url')
            ->values()
            ->all();
    }

    private function collectDocumentsFromValue(Collection $documents, mixed $value, string $source): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->collectDocumentsFromValue($documents, $decoded, $source);

                return;
            }

            collect(preg_split('/[\s,]+/', $value) ?: [])
                ->map(fn (string $url): string => trim($url))
                ->filter()
                ->each(function (string $url) use ($documents, $source): void {
                    $this->pushDocument($documents, $url, null, $source);
                });

            return;
        }

        if (is_object($value)) {
            $this->collectDocumentsFromValue($documents, (array) $value, $source);

            return;
        }

        if (! is_array($value)) {
            return;
        }

        $url = $value['url'] ?? $value['href'] ?? $value['src'] ?? null;

        if (is_string($url)) {
            $title = $value['name'] ?? $value['title'] ?? null;
            $contentType = $value['contentType'] ?? $value['content_type'] ?? null;

            $this->pushDocument(
                $documents,
                $url,
                is_string($title) ? $title : null,
                $source,
                is_string($contentType) ? $contentType : null
            );
        }

        foreach ($value as $item) {
            if (is_array($item) || is_object($item)) {
                $this->collectDocumentsFromValue($documents, $item, $source);
            } elseif (is_string($item)) {
                $this->pushDocument($documents, $item, null, $source);
            }
        }
    }

    private function pushDocument(Collection $documents, string $url, ?string $title, string $source, ?string $contentType = null): void
    {
        $normalizedUrl = $this->normalizeDocumentUrl($url);

        if ($normalizedUrl === null) {
            return;
        }

        $documents->push([
            'title' => filled($title) ? (string) $title : basename(parse_url($normalizedUrl, PHP_URL_PATH) ?: $source),
            'url' => $normalizedUrl,
            'type' => $this->documentType($normalizedUrl, (string) $contentType),
            'source' => $source,
            'content_type' => (string) $contentType,
        ]);
    }

    private function normalizeDocumentUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return url($url);
        }

        if (str_starts_with($url, 'storage/') || str_starts_with($url, 'uploads/')) {
            return url($url);
        }

        return null;
    }

    private function documentType(string $url, string $contentType = ''): string
    {
        if (str_contains($contentType, 'image/')) {
            return 'image';
        }

        if (str_contains($contentType, 'pdf')) {
            return 'pdf';
        }

        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp' => 'image',
            'pdf' => 'pdf',
            default => 'file',
        };
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

    private function searchNameParts(Request $request): Collection
    {
        return collect([
            'first_name' => trim($request->string('first_name')->toString()),
            'father_name' => trim($request->string('father_name')->toString()),
            'grandfather_name' => trim($request->string('grandfather_name')->toString()),
            'family_name' => trim($request->string('family_name')->toString()),
        ])->filter();
    }

    private function searchHusbandRegistry(MissingCitizenIdentityReport $report, string $search): Collection
    {
        if ($report->identity_subject !== 'spouse') {
            return collect();
        }

        $breadwinnerIdCardNo = $this->reportBreadwinnerIdCardNo($report);

        if ($breadwinnerIdCardNo === '') {
            return collect();
        }

        try {
            $query = DB::table($this->husbandRegistryTable())
                ->select(['id_card_no', 'full_name', 'breadwinner_id_card_no'])
                ->where('status', 'A')
                ->whereRaw('TRIM(breadwinner_id_card_no) = ?', [$breadwinnerIdCardNo]);

            if (ctype_digit($search)) {
                $query->where('id_card_no', 'like', $search.'%');
            } elseif ($search !== '') {
                $normalizedSearch = ArabicNameNormalizer::normalize($search);

                if ($normalizedSearch === '') {
                    return collect();
                }

                $query->where('full_name_normalized', 'like', $normalizedSearch.'%');
            } else {
                return collect();
            }

            return $query
                ->orderBy('id_card_no')
                ->limit(20)
                ->get()
                ->map(fn ($record): array => $this->husbandRegistryRecordToCandidate($record));
        } catch (Throwable) {
            return collect();
        }
    }

    private function husbandRegistryNameCandidates(MissingCitizenIdentityReport $report): Collection
    {
        if ($report->identity_subject !== 'spouse' || ! filled($report->normalized_owner_name)) {
            return collect();
        }

        $breadwinnerIdCardNo = $this->reportBreadwinnerIdCardNo($report);

        if ($breadwinnerIdCardNo === '') {
            return collect();
        }

        try {
            return DB::table($this->husbandRegistryTable())
                ->select(['id_card_no', 'full_name', 'breadwinner_id_card_no'])
                ->where('status', 'A')
                ->whereRaw('TRIM(breadwinner_id_card_no) = ?', [$breadwinnerIdCardNo])
                ->where('full_name_normalized', $report->normalized_owner_name)
                ->orderBy('id_card_no')
                ->limit(20)
                ->get()
                ->map(fn ($record): array => $this->husbandRegistryRecordToCandidate($record));
        } catch (Throwable) {
            return collect();
        }
    }

    private function searchSgazaCivilRegistry(string $search, ?Collection $nameParts = null): Collection
    {
        if (! Schema::hasTable('sgaza') || ! Schema::hasColumn('sgaza', 'id_number')) {
            return collect();
        }

        $nameParts ??= collect();
        $query = DB::table('sgaza');

        if ($nameParts->isNotEmpty()) {
            foreach ($nameParts as $column => $value) {
                $query->where($column, 'like', $value.'%');
            }
        } elseif (ctype_digit($search)) {
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

    private function husbandRegistryRecordByIdNumber(string $idNumber): ?object
    {
        try {
            $record = DB::table($this->husbandRegistryTable())
                ->select(['id_card_no', 'full_name'])
                ->where('status', 'A')
                ->where('id_card_no', $idNumber)
                ->first();
        } catch (Throwable) {
            return null;
        }

        if ($record === null) {
            return null;
        }

        return (object) [
            'id_card_no' => $record->id_card_no,
            'full_name' => $record->full_name,
        ];
    }

    private function reportBreadwinnerIdCardNo(MissingCitizenIdentityReport $report): string
    {
        $housingUnit = HousingUnit::query()->find($report->housing_unit_id);

        if (! $housingUnit instanceof HousingUnit) {
            return '';
        }

        return trim((string) $housingUnit->id_number1);
    }

    private function husbandRegistryRecordToCandidate(object $record): array
    {
        return [
            'id' => 'husband:'.$record->id_card_no,
            'id_card_no' => (string) $record->id_card_no,
            'full_name' => $record->full_name ?: '-',
            'source' => __('ui.missing_citizen_identities.source_husband_registry'),
            'details' => filled($record->breadwinner_id_card_no ?? null)
                ? __('ui.missing_citizen_identities.breadwinner_id_card_no').': '.$record->breadwinner_id_card_no
                : '',
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
