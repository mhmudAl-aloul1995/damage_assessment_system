<?php

declare(strict_types=1);

namespace App\Modules\DamageAssessment\Http\Controllers\InfrastructureAudit;

use App\Http\Controllers\Controller;
use App\Http\Requests\InfAudit\InfAuditBulkAssignRequest;
use App\Http\Requests\InfAudit\InfAuditFieldUpdateRequest;
use App\Http\Requests\InfAudit\InfAuditStatusRequest;
use App\Models\CsoAuditLog;
use App\Models\CsoSurvey;
use App\Models\CsoSurveyAuditHistory;
use App\Models\CsoSurveyAuditStatus;
use App\Models\CsoSurveyFilter;
use App\Models\CsoSurveyOrganization;
use App\Models\CsoSurveyUnit;
use App\Models\InfAuditAssignment;
use App\Models\InfAuditStatus;
use App\Models\User;
use App\services\ArcgisService;
use App\services\CsoAuditService;
use App\Support\Forms\CsoSurveyLayout;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Yajra\DataTables\Facades\DataTables;

class InfAuditCsoSurveyController extends Controller
{
    /** @var array<string, \Illuminate\Support\Collection>|null */
    private ?array $auditHistories = null;

    private const TABLE_TYPE = 'cso_survey_table';

    private const ORGANIZATION_TABLE_TYPE = 'cso_survey_organization_table';

    private const UNIT_TABLE_TYPE = 'cso_survey_unit_table';

    private const FINAL_STATUS_NAMES = ['final_approval', 'accepted_final', 'final'];

    public function __construct(private readonly CsoAuditService $audit)
    {
        $this->middleware('role_or_permission:Inf - QC/QA Engineer|Team Leader -INF|Database Officer|Project Officer|CSO Officer|inf-audit.cso.view');
    }

    public function index(): View
    {
        return view('damage-assessment::infrastructure-audit.cso.index', $this->indexData());
    }

    public function data(Request $request): JsonResponse
    {
        $query = CsoSurvey::query()
            ->with(['infAuditAssignment.user', 'infAuditStatus.status', 'infAuditStatus.assignee'])
            ->select('cso_surveys.*');

        $this->joinFieldEngineer($query);
        $this->scopeVisibleToUser($query);
        $this->excludeFinalApproved($query);
        $this->applyFilters($query, $request);

        return DataTables::eloquent($query)
            ->addColumn('selection', fn (CsoSurvey $survey): string => '<input type="checkbox" class="form-check-input inf-audit-row-check" value="'.e((string) $survey->id).'">')
            ->addColumn('audit_status', fn (CsoSurvey $survey): string => $this->statusBadge($survey->infAuditStatus?->status))
            ->addColumn('field_engineer', fn (CsoSurvey $survey): string => e($this->fieldEngineerName($survey)))
            ->addColumn('auditor', fn (CsoSurvey $survey): string => e($survey->infAuditAssignment?->user?->name ?? $survey->infAuditStatus?->assignee?->name ?? '-'))
            ->addColumn('actions', fn (CsoSurvey $survey): string => '<a class="btn btn-sm btn-light-primary" href="'.route('inf-audit.cso.show', $survey).'">فتح التدقيق</a>')
            ->filterColumn('field_engineer', function (Builder $query, string $keyword): void {
                $query->where(function (Builder $builder) use ($keyword): void {
                    $builder->where('cso_surveys.assignedto', 'like', "%{$keyword}%");

                    if (Schema::hasColumn('users', 'username_arcgis')) {
                        $builder->orWhere('field_engineer_users.name', 'like', "%{$keyword}%");

                        if (Schema::hasColumn('users', 'name_en')) {
                            $builder->orWhere('field_engineer_users.name_en', 'like', "%{$keyword}%");
                        }
                    }
                });
            })
            ->rawColumns(['selection', 'audit_status', 'actions'])
            ->toJson();
    }

    public function bulkAssign(InfAuditBulkAssignRequest $request): JsonResponse
    {
        abort_unless(Auth::user()?->hasAnyRole(['Database Officer', 'Team Leader -INF', 'CSO Officer']), 403);

        $data = $request->validated();
        $status = InfAuditStatus::query()->where('name', 'assigned')->firstOrFail();
        $updatedCount = 0;

        DB::transaction(function () use ($data, $status, &$updatedCount): void {
            CsoSurvey::query()
                ->whereIn('id', $data['ids'])
                ->lockForUpdate()
                ->get()
                ->each(function (CsoSurvey $survey) use ($data, $status, &$updatedCount): void {
                    $current = $this->latestAuditStatus($survey);
                    $isSameStatus = (int) ($current?->status_id ?? 0) === (int) $status->id
                        && (int) ($current->assigned_to ?? 0) === (int) $data['assigned_to'];

                    if ($isSameStatus) {
                        return;
                    }

                    CsoSurveyAuditStatus::query()->create([
                        'cso_survey_id' => $survey->id,
                        'objectid' => $survey->objectid,
                        'globalid' => $survey->globalid,
                        'status_id' => $status->id,
                        'assigned_to' => $data['assigned_to'],
                        'updated_by' => Auth::id(),
                        'notes' => $data['notes'] ?? null,
                    ]);

                    InfAuditAssignment::query()->updateOrCreate(
                        [
                            'type' => 'cso_survey',
                            'globalid' => $survey->globalid,
                        ],
                        [
                            'manager_id' => Auth::id(),
                            'user_id' => $data['assigned_to'],
                        ],
                    );

                    CsoSurveyAuditHistory::query()->create([
                        'cso_survey_id' => $survey->id,
                        'objectid' => $survey->objectid,
                        'globalid' => $survey->globalid,
                        'status_id' => $status->id,
                        'assigned_to' => $data['assigned_to'],
                        'user_id' => Auth::id(),
                        'notes' => $data['notes'] ?? null,
                    ]);

                    $updatedCount++;
                });
        });

        return response()->json(['message' => "تم إسناد {$updatedCount} سجل بنجاح."]);
    }

    public function show(CsoSurvey $cso): View
    {
        $cso->load([
            'infAuditAssignment.manager',
            'infAuditAssignment.user',
            'infAuditStatus.status',
            'infAuditStatus.assignee',
            'auditLogs.user',
            'organizations' => fn ($query) => $query->orderBy('objectid'),
            'units' => fn ($query) => $query->orderBy('objectid'),
        ]);

        $this->authorizeRecord($cso);

        $this->auditHistories = $cso->auditLogs->sortByDesc('id')
            ->groupBy(fn (CsoAuditLog $log): string => $log->record_type.'|'.$log->record_id.'|'.$log->field_name)->all();

        return view('damage-assessment::infrastructure-audit.public-buildings.show', [
            ...$this->indexData(),
            'survey' => $cso,
            'sections' => $this->surveySections($cso),
            'childGroups' => $this->childGroups($cso),
            'assignment' => $this->assignment($cso->globalid),
            'editHistories' => $this->editHistories($cso),
            'arcgisAttachments' => $this->arcgisAttachments($cso),
            'currentStatusName' => $this->latestAuditStatus($cso)?->status?->name,
            'statusRoute' => route('inf-audit.cso.status', $cso),
            'fieldRoute' => route('inf-audit.cso.field-update', $cso),
            'backRoute' => route('inf-audit.cso.index'),
            'title' => 'تدقيق منظمات المجتمع المدني',
            'mainSectionTitle' => 'بيانات استبيان CSO',
            'childSectionTitle' => 'المنظمات والوحدات التابعة',
            'usesBaseValues' => true,
        ]);
    }

    public function updateStatus(InfAuditStatusRequest $request, CsoSurvey $cso): JsonResponse
    {
        $data = $request->validated();

        return DB::transaction(function () use ($data, $cso): JsonResponse {
            $cso = CsoSurvey::query()
                ->whereKey($cso->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $status = InfAuditStatus::query()->where('name', $data['status'])->firstOrFail();
            $this->authorizeStatusChange($status->name);

            $current = $this->latestAuditStatus($cso);
            $assignedTo = array_key_exists('assigned_to', $data) ? $data['assigned_to'] : $current?->assigned_to;

            if ($status->name === 'assigned' && ! $assignedTo) {
                return response()->json(['message' => 'يرجى اختيار المدقق.'], 422);
            }

            $isSameStatus = (int) ($current?->status_id ?? 0) === (int) $status->id
                && (int) ($current->assigned_to ?? 0) === (int) ($assignedTo ?? 0);

            if ($isSameStatus) {
                return response()->json(['message' => 'لا يمكن تكرار نفس الحالة الحالية.'], 422);
            }

            $current = CsoSurveyAuditStatus::query()->create([
                'cso_survey_id' => $cso->id,
                'objectid' => $cso->objectid,
                'globalid' => $cso->globalid,
                'status_id' => $status->id,
                'assigned_to' => $assignedTo,
                'updated_by' => Auth::id(),
                'notes' => $data['notes'] ?? null,
            ]);

            if ($status->name === 'assigned' && $assignedTo) {
                InfAuditAssignment::query()->updateOrCreate(
                    [
                        'type' => 'cso_survey',
                        'globalid' => $cso->globalid,
                    ],
                    [
                        'manager_id' => Auth::id(),
                        'user_id' => $assignedTo,
                    ],
                );
            }

            CsoSurveyAuditHistory::query()->create([
                'cso_survey_id' => $cso->id,
                'objectid' => $cso->objectid,
                'globalid' => $cso->globalid,
                'status_id' => $status->id,
                'assigned_to' => $assignedTo,
                'user_id' => Auth::id(),
                'notes' => $data['notes'] ?? null,
            ]);

            $assignment = $this->assignment($cso->globalid);

            return response()->json([
                'message' => 'تم تحديث الحالة بنجاح.',
                'assignment' => [
                    'user_name' => $assignment?->user?->name ?? $current->assignee?->name ?? '-',
                    'manager_name' => $assignment?->manager?->name ?? '-',
                    'updated_at' => $assignment?->updated_at?->format('Y-m-d H:i') ?? '-',
                ],
            ]);
        });
    }

    public function updateField(InfAuditFieldUpdateRequest $request, CsoSurvey $cso): JsonResponse
    {
        $data = $request->validated();

        $this->authorizeFieldEdit($cso);

        $tableType = $data['table_type'];
        $record = $this->editableRecord($cso, $tableType, (int) $data['auditable_id']);
        $field = $this->fieldMeta($data['field_name'], $tableType);
        $this->audit->save(
            $cso, $record, $tableType, $data['field_name'], $data['field_value'] ?? null,
            Auth::id(), $data['notes'] ?? null, $data['expected_value'] ?? null,
            array_key_exists('expected_value', $data),
        );

        return response()->json([
            'message' => 'تم حفظ القيمة في ArcGIS والبيانات الأساسية وتسجيل التعديل.',
            'display_value' => $this->displayValue($record, $field, $tableType),
            'raw_value' => $this->rawValue($record, $data['field_name'], $tableType),
            'history' => $this->historyPayload($this->fieldHistory($record, $data['field_name'], $tableType)),
        ]);
    }

    private function indexData(): array
    {
        return [
            'statuses' => InfAuditStatus::query()->orderBy('order_step')->get(),
            'engineers' => User::role('Inf - QC/QA Engineer')->orderBy('name')->get(['id', 'name']),
            'fieldEngineers' => $this->fieldEngineerOptions(),
            'municipalities' => CsoSurvey::query()->whereNotNull('municipalitie')->distinct()->orderBy('municipalitie')->pluck('municipalitie'),
            'neighborhoods' => CsoSurvey::query()->whereNotNull('neighborhood')->distinct()->orderBy('neighborhood')->pluck('neighborhood'),
        ];
    }

    private function assignment(?string $globalid): ?InfAuditAssignment
    {
        if (! filled($globalid)) {
            return null;
        }

        return InfAuditAssignment::query()
            ->with(['manager', 'user'])
            ->where('type', 'cso_survey')
            ->where('globalid', $globalid)
            ->first();
    }

    private function editHistories(CsoSurvey $survey): array
    {
        return $survey->auditLogs->sortByDesc('id')->values()->all();
    }

    private function surveySections(CsoSurvey $survey): array
    {
        $repeatSectionNames = array_merge(
            CsoSurveyLayout::repeatSectionNames('CSO_Organizations'),
            CsoSurveyLayout::repeatSectionNames('Unit_Information'),
            ['CSO_Organizations', 'Unit_Information'],
        );

        return collect(CsoSurveyLayout::sections())
            ->reject(fn (array $section): bool => in_array($section['name'] ?? '', $repeatSectionNames, true))
            ->map(fn (array $section): array => [
                'title' => $section['label'] ?: $section['name'],
                'rows' => $this->rows($survey, $section['fields'] ?? [], self::TABLE_TYPE),
            ])
            ->filter(fn (array $section): bool => $section['rows'] !== [])
            ->values()
            ->all();
    }

    private function childGroups(CsoSurvey $survey): array
    {
        $organizationSections = CsoSurveyLayout::repeatSections('CSO_Organizations');
        $unitSections = CsoSurveyLayout::repeatSections('Unit_Information');

        $organizations = $survey->organizations
            ->values()
            ->map(fn (CsoSurveyOrganization $organization, int $index): array => [
                'title' => 'CSO Organization '.($index + 1).' - '.($organization->organization_name_en ?: $organization->organization_name_ar ?: $organization->organization_acronym ?: $organization->objectid),
                'sections' => $this->childSections($organization, $organizationSections, self::ORGANIZATION_TABLE_TYPE),
            ])
            ->filter(fn (array $group): bool => $group['sections'] !== []);

        $units = $survey->units
            ->values()
            ->map(fn (CsoSurveyUnit $unit, int $index): array => [
                'title' => 'Unit Information '.($index + 1).' - '.($unit->unit_name ?: $unit->objectid),
                'sections' => $this->childSections($unit, $unitSections, self::UNIT_TABLE_TYPE),
            ])
            ->filter(fn (array $group): bool => $group['sections'] !== []);

        return $organizations
            ->merge($units)
            ->values()
            ->all();
    }

    private function childSections(object $record, array $sections, string $tableType): array
    {
        return collect($sections)
            ->map(fn (array $section): array => [
                'title' => $section['label'] ?: $section['name'],
                'rows' => $this->rows($record, $section['fields'] ?? [], $tableType),
            ])
            ->filter(fn (array $section): bool => $section['rows'] !== [])
            ->values()
            ->all();
    }

    private function rows(object $record, array $fields, string $tableType): array
    {
        return collect($fields)
            ->reject(fn (array $field): bool => in_array($field['type'] ?? null, ['calculate'], true))
            ->map(function (array $field) use ($record, $tableType): array {
                $rawValue = $this->rawValue($record, $field['name'], $tableType);
                $history = $this->fieldHistory($record, $field['name'], $tableType);

                return [
                    'record_id' => $record->id,
                    'table_type' => $tableType,
                    'field_name' => $field['name'],
                    'field_type' => $field['type'] ?? null,
                    'list_name' => $field['list_name'] ?? null,
                    'label' => $field['label'] ?: $field['name'],
                    'value' => $this->formatValue($rawValue, $field),
                    'raw_value' => $rawValue,
                    'editable' => isset($this->audit->editableFields($tableType)[$field['name']]),
                    'show_current_value' => true,
                    'has_answer' => filled($rawValue),
                    'is_edited' => $history !== [],
                    'options' => $this->fieldOptions($field['list_name'] ?? null),
                    'history_id' => 'inf_history_'.md5($tableType.'|'.$record->id.'|'.$field['name']),
                    'history' => $history,
                ];
            })
            ->values()
            ->all();
    }

    private function displayValue(object $record, array $field, string $tableType): string
    {
        return $this->formatValue($this->rawValue($record, $field['name'], $tableType), $field);
    }

    private function rawValue(object $record, string $fieldName, string $tableType): ?string
    {
        $value = $this->audit->value($record, $fieldName);

        if ($value === null || $value === '') {
            return null;
        }

        return is_array($value) ? implode(',', $value) : (string) $value;
    }

    private function fieldOptions(?string $listName): array
    {
        if (! $listName) {
            return [];
        }

        return CsoSurveyFilter::query()
            ->where('list_name', $listName)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['name', 'label'])
            ->map(fn (CsoSurveyFilter $filter): array => [
                'value' => $filter->name,
                'label' => $filter->label ?: $filter->name,
            ])
            ->all();
    }

    private function filterLabel(?string $listName, string $value): string
    {
        if (! $listName || $value === '') {
            return $value;
        }

        return CsoSurveyFilter::query()
            ->where('list_name', $listName)
            ->where('name', $value)
            ->value('label') ?: $value;
    }

    private function fieldHistory(object $record, string $fieldName, string $tableType): array
    {
        $field = $this->fieldMeta($fieldName, $tableType);

        $historyKey = $this->audit->recordType($tableType).'|'.$record->id.'|'.$fieldName;
        $histories = $this->auditHistories !== null
            ? ($this->auditHistories[$historyKey] ?? collect())
            : CsoAuditLog::query()
                ->with('user')
                ->where('record_type', $this->audit->recordType($tableType))
                ->where('record_id', $record->id)
                ->where('field_name', $fieldName)
                ->orderByDesc('id')
                ->get();

        return $histories->each(function (CsoAuditLog $history) use ($field): void {
            $history->display_field_value = $this->formatValue($history->new_value, $field);
            $history->display_old_value = $this->formatValue($history->old_value, $field);
        })
            ->all();
    }

    private function fieldMeta(string $fieldName, string $tableType): array
    {
        $sections = match ($tableType) {
            self::ORGANIZATION_TABLE_TYPE => CsoSurveyLayout::repeatSections('CSO_Organizations'),
            self::UNIT_TABLE_TYPE => CsoSurveyLayout::repeatSections('Unit_Information'),
            default => CsoSurveyLayout::sections(),
        };

        foreach ($sections as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                if (($field['name'] ?? null) === $fieldName) {
                    return $field;
                }
            }
        }

        return [
            'name' => $fieldName,
            'type' => 'text',
            'label' => $fieldName,
            'hint' => null,
            'list_name' => null,
        ];
    }

    private function editableRecord(CsoSurvey $survey, string $tableType, int $id): object
    {
        return match ($tableType) {
            self::TABLE_TYPE => (int) $survey->id === $id ? $survey : abort(404),
            self::ORGANIZATION_TABLE_TYPE => CsoSurveyOrganization::query()
                ->whereKey($id)
                ->where('parentglobalid', $survey->globalid)
                ->firstOrFail(),
            self::UNIT_TABLE_TYPE => CsoSurveyUnit::query()
                ->whereKey($id)
                ->where('parentglobalid', $survey->globalid)
                ->firstOrFail(),
            default => abort(404),
        };
    }

    private function formatValue(mixed $value, array $field): string
    {
        if (! filled($value)) {
            return 'لا يوجد جواب';
        }

        if (is_array($value)) {
            return collect($value)
                ->flatten()
                ->map(fn (mixed $item): string => $this->filterLabel($field['list_name'] ?? null, (string) $item))
                ->implode(', ');
        }

        $stringValue = (string) $value;

        if (($field['type'] ?? null) === 'select_multiple') {
            return collect(preg_split('/[, ]+/', $stringValue) ?: [])
                ->filter()
                ->map(fn (string $item): string => $this->filterLabel($field['list_name'] ?? null, $item))
                ->implode(', ');
        }

        if (($field['type'] ?? null) === 'select_one') {
            return $this->filterLabel($field['list_name'] ?? null, $stringValue);
        }

        return $stringValue;
    }

    private function historyPayload(array $history): array
    {
        return collect($history)
            ->map(fn (CsoAuditLog $item): array => [
                'field_value' => $item->display_field_value ?? $item->new_value ?? '-',
                'old_value' => $item->display_old_value ?? $item->old_value ?? '-',
                'user_name' => $item->user?->name ?? '-',
                'created_at' => $item->created_at?->format('Y-m-d h:i A') ?? '-',
                'notes' => $item->notes,
            ])
            ->all();
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('objectid'), fn (Builder $q) => $q->where('objectid', '=', trim((string) $request->input('objectid'))));

        foreach (['municipalitie', 'neighborhood'] as $field) {
            $query->when($request->filled($field), fn (Builder $q) => $q->where($field, $request->string($field)));
        }

        $query->when($request->filled('auditor'), fn (Builder $q) => $q->whereHas('infAuditAssignment', fn (Builder $s) => $s->where('user_id', $request->integer('auditor'))));
        $query->when($request->filled('field_engineer'), fn (Builder $q) => $q->where('cso_surveys.assignedto', $request->string('field_engineer')));
        $query->when($request->filled('status'), fn (Builder $q) => $this->whereLatestStatus($q, (string) $request->string('status')));
        $query->when($request->filled('from_date'), fn (Builder $q) => $q->whereDate($this->dateColumn(), '>=', $request->date('from_date')?->toDateString()));
        $query->when($request->filled('to_date'), fn (Builder $q) => $q->whereDate($this->dateColumn(), '<=', $request->date('to_date')?->toDateString()));
    }

    private function joinFieldEngineer(Builder $query): void
    {
        if (! Schema::hasColumn('cso_surveys', 'assignedto') || ! Schema::hasColumn('users', 'username_arcgis')) {
            return;
        }

        $query->leftJoin('users as field_engineer_users', 'cso_surveys.assignedto', '=', 'field_engineer_users.username_arcgis')
            ->addSelect([
                'field_engineer_name' => DB::raw('field_engineer_users.name'),
                'field_engineer_name_en' => DB::raw(Schema::hasColumn('users', 'name_en') ? 'field_engineer_users.name_en' : 'NULL'),
            ]);
    }

    private function fieldEngineerName(CsoSurvey $survey): string
    {
        if (! filled($survey->assignedto)) {
            return '-';
        }

        return $survey->field_engineer_name ?: $survey->field_engineer_name_en ?: $survey->assignedto;
    }

    private function fieldEngineerOptions(): array
    {
        if (! Schema::hasColumn('cso_surveys', 'assignedto')) {
            return [];
        }

        $assignedValues = CsoSurvey::query()
            ->whereNotNull('assignedto')
            ->where('assignedto', '<>', '')
            ->distinct()
            ->orderBy('assignedto')
            ->pluck('assignedto');

        $users = Schema::hasColumn('users', 'username_arcgis')
            ? User::query()->whereIn('username_arcgis', $assignedValues)->get(['username_arcgis', 'name', 'name_en'])->keyBy('username_arcgis')
            : collect();

        return $assignedValues
            ->map(fn (string $assignedTo): array => [
                'value' => $assignedTo,
                'label' => $users->get($assignedTo)?->name ?: $users->get($assignedTo)?->name_en ?: $assignedTo,
            ])
            ->values()
            ->all();
    }

    private function excludeFinalApproved(Builder $query): void
    {
        $query->whereNotExists(function ($subQuery): void {
            $subQuery->selectRaw('1')
                ->from('cso_survey_audit_statuses as latest_cso_status')
                ->join('inf_audit_statuses', 'inf_audit_statuses.id', '=', 'latest_cso_status.status_id')
                ->whereColumn('latest_cso_status.cso_survey_id', 'cso_surveys.id')
                ->whereIn('inf_audit_statuses.name', self::FINAL_STATUS_NAMES)
                ->whereRaw('latest_cso_status.id = (select max(csas.id) from cso_survey_audit_statuses as csas where csas.cso_survey_id = cso_surveys.id)');
        });
    }

    private function whereLatestStatus(Builder $query, string $status): void
    {
        $query->whereExists(function ($subQuery) use ($status): void {
            $subQuery->selectRaw('1')
                ->from('cso_survey_audit_statuses as latest_cso_status')
                ->join('inf_audit_statuses', 'inf_audit_statuses.id', '=', 'latest_cso_status.status_id')
                ->whereColumn('latest_cso_status.cso_survey_id', 'cso_surveys.id')
                ->where('inf_audit_statuses.name', $status)
                ->whereRaw('latest_cso_status.id = (select max(csas.id) from cso_survey_audit_statuses as csas where csas.cso_survey_id = cso_surveys.id)');
        });
    }

    private function latestAuditStatus(CsoSurvey $survey): ?CsoSurveyAuditStatus
    {
        return CsoSurveyAuditStatus::query()
            ->with(['status', 'assignee'])
            ->where('cso_survey_id', $survey->id)
            ->latest('id')
            ->first();
    }

    private function arcgisAttachments(CsoSurvey $survey): array
    {
        if (! filled($survey->objectid)) {
            return [];
        }

        $layerUrl = $this->featureServerLayerUrl((string) config('services.arcgis.cso_survey_layer_url'), 0);

        if ($layerUrl === '') {
            return [];
        }

        try {
            $arcgis = app(ArcgisService::class);
            $token = $arcgis->getToken();

            return collect($arcgis->getAttachmentsFromLayerUrl($layerUrl, $survey->objectid, $token))
                ->map(fn (array $attachment): array => [
                    'id' => $attachment['id'] ?? null,
                    'name' => $attachment['name'] ?? 'Attachment',
                    'content_type' => $attachment['contentType'] ?? '',
                    'url' => filled($attachment['id'] ?? null) ? $arcgis->buildUrlFromLayerUrl($layerUrl, $survey->objectid, $attachment['id'], $token) : null,
                ])
                ->filter(fn (array $attachment): bool => filled($attachment['url']))
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function scopeVisibleToUser(Builder $query): void
    {
        if (Auth::user()?->hasRole('Inf - QC/QA Engineer') && ! Auth::user()?->hasAnyRole(['Database Officer', 'Team Leader -INF', 'CSO Officer'])) {
            $query->whereHas('infAuditAssignment', fn (Builder $statusQuery) => $statusQuery->where('user_id', Auth::id()));
        }
    }

    private function authorizeRecord(CsoSurvey $survey): void
    {
        if (Auth::user()?->hasRole('Inf - QC/QA Engineer') && ! Auth::user()?->hasAnyRole(['Database Officer', 'Team Leader -INF', 'CSO Officer'])) {
            abort_unless((int) ($survey->infAuditAssignment?->user_id ?? $survey->infAuditStatus?->assigned_to ?? 0) === Auth::id(), 403);
        }
    }

    private function authorizeFieldEdit(CsoSurvey $survey): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['Database Officer', 'Team Leader -INF', 'CSO Officer']) || (Auth::user()?->hasRole('Inf - QC/QA Engineer') && (int) ($survey->infAuditAssignment?->user_id ?? $survey->infAuditStatus?->assigned_to ?? 0) === Auth::id()), 403);
    }

    private function authorizeStatusChange(string $status): void
    {
        if ($status === 'assigned' || in_array($status, self::FINAL_STATUS_NAMES, true)) {
            abort_unless(Auth::user()?->hasAnyRole(['Database Officer', 'Team Leader -INF', 'CSO Officer']), 403);

            return;
        }

        abort_unless(Auth::user()?->hasAnyRole(['Database Officer', 'Team Leader -INF', 'Inf - QC/QA Engineer', 'CSO Officer']), 403);
    }

    private function statusBadge(?InfAuditStatus $status): string
    {
        return $status
            ? '<span class="'.e($status->badge_class).'">'.e($status->label).'</span>'
            : '<span class="badge badge-light">-</span>';
    }

    private function dateColumn(): string
    {
        return Schema::hasColumn('cso_surveys', 'creationdate') ? 'cso_surveys.creationdate' : 'cso_surveys.created_at';
    }

    private function featureServerLayerUrl(string $url, int $layer): string
    {
        $url = rtrim($url, '/');
        $url = preg_replace('#/query$#i', '', $url) ?: $url;

        if (preg_match('#/featureserver$#i', $url)) {
            return $url.'/'.$layer;
        }

        if (preg_match('#/featureserver/\d+$#i', $url)) {
            return preg_replace('#/featureserver/\d+$#i', '/FeatureServer/'.$layer, $url) ?: $url;
        }

        return $url;
    }
}
