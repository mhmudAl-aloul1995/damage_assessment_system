<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\InfAudit\InfAuditBulkAssignRequest;
use App\Http\Requests\InfAudit\InfAuditChildStoreRequest;
use App\Http\Requests\InfAudit\InfAuditFieldUpdateRequest;
use App\Http\Requests\InfAudit\InfAuditStatusRequest;
use App\Models\InfAuditAssignment;
use App\Models\InfAuditStatus;
use App\Models\InfEditAssessment;
use App\Models\RoadFacilityAuditHistory;
use App\Models\RoadFacilityAuditStatus;
use App\Models\RoadFacilityFilter;
use App\Models\RoadFacilitySurvey;
use App\Models\RoadFacilitySurveyItem;
use App\Models\User;
use App\Support\Forms\RoadFacilitySurveyLayout;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class InfAuditRoadFacilityController extends Controller
{
    private const TABLE_TYPE = 'road_facility_table';

    private const ITEM_TABLE_TYPE = 'road_facility_item_table';

    public function __construct()
    {
        $this->middleware('role:Inf - QC/QA Engineer|Team Leader -INF|Database Officer');
    }

    public function index(): View
    {
        return view('inf_audit.roads.index', $this->indexData());
    }

    public function data(Request $request): JsonResponse
    {
        $query = RoadFacilitySurvey::query()
            ->with(['infAuditAssignment.user', 'infAuditStatus.status', 'infAuditStatus.assignee'])
            ->select('road_facility_surveys.*');

        $this->scopeVisibleToUser($query);
        $this->applyFilters($query, $request);

        return DataTables::eloquent($query)
            ->addColumn('selection', fn (RoadFacilitySurvey $survey): string => '<input type="checkbox" class="form-check-input inf-audit-row-check" value="'.e((string) $survey->id).'">')
            ->addColumn('audit_status', fn (RoadFacilitySurvey $survey): string => $this->statusBadge($survey->infAuditStatus?->status))
            ->addColumn('auditor', fn (RoadFacilitySurvey $survey): string => e($survey->infAuditAssignment?->user?->name ?? $survey->infAuditStatus?->assignee?->name ?? '-'))
            ->addColumn('actions', fn (RoadFacilitySurvey $survey): string => '<a class="btn btn-sm btn-light-primary" href="'.route('inf-audit.roads.show', $survey).'">فتح التدقيق</a>')
            ->rawColumns(['selection', 'audit_status', 'actions'])
            ->toJson();
    }

    public function bulkAssign(InfAuditBulkAssignRequest $request): JsonResponse
    {
        abort_unless(Auth::user()?->hasAnyRole(['Database Officer', 'Team Leader -INF']), 403);

        $data = $request->validated();
        $status = InfAuditStatus::query()->where('name', 'assigned')->firstOrFail();
        $updatedCount = 0;

        DB::transaction(function () use ($data, $status, &$updatedCount): void {
            RoadFacilitySurvey::query()
                ->whereIn('id', $data['ids'])
                ->get()
                ->each(function (RoadFacilitySurvey $survey) use ($data, $status, &$updatedCount): void {
                    $current = RoadFacilityAuditStatus::query()->firstOrNew([
                        'globalid' => $survey->globalid,
                    ]);

                    $isSameStatus = (int) $current->status_id === (int) $status->id
                        && (int) ($current->assigned_to ?? 0) === (int) $data['assigned_to'];

                    $current->fill([
                        'road_facility_survey_id' => $survey->id,
                        'objectid' => $survey->objectid,
                        'globalid' => $survey->globalid,
                        'status_id' => $status->id,
                        'assigned_to' => $data['assigned_to'],
                        'updated_by' => Auth::id(),
                        'notes' => $data['notes'] ?? null,
                    ])->save();

                    InfAuditAssignment::query()->updateOrCreate(
                        [
                            'type' => 'road_facility',
                            'globalid' => $survey->globalid,
                        ],
                        [
                            'manager_id' => Auth::id(),
                            'user_id' => $data['assigned_to'],
                        ],
                    );

                    if (! $isSameStatus) {
                        RoadFacilityAuditHistory::query()->create([
                            'road_facility_survey_id' => $survey->id,
                            'objectid' => $survey->objectid,
                            'globalid' => $survey->globalid,
                            'status_id' => $status->id,
                            'assigned_to' => $data['assigned_to'],
                            'user_id' => Auth::id(),
                            'notes' => $data['notes'] ?? null,
                        ]);
                    }

                    $updatedCount++;
                });
        });

        return response()->json(['message' => "تم إسناد {$updatedCount} سجل بنجاح."]);
    }

    public function show(RoadFacilitySurvey $road): View
    {
        $road->load([
            'items' => fn ($query) => $query->orderBy('objectid'),
            'infAuditAssignment.manager',
            'infAuditAssignment.user',
            'infAuditStatus.status',
            'infAuditStatus.assignee',
        ]);

        $this->authorizeRecord($road);

        return view('inf_audit.roads.show', [
            ...$this->indexData(),
            'survey' => $road,
            'sections' => $this->surveySections($road),
            'childGroups' => $this->itemGroups($road),
            'assignment' => $this->assignment($road->globalid),
            'editHistories' => $this->editHistories($road),
            'statusRoute' => route('inf-audit.roads.status', $road),
            'fieldRoute' => route('inf-audit.roads.field-update', $road),
            'childStoreRoute' => route('inf-audit.roads.children.store', $road),
            'backRoute' => route('inf-audit.roads.index'),
            'title' => 'تدقيق الطرق',
            'mainSectionTitle' => 'بيانات الطريق/المرفق',
            'childSectionTitle' => 'عناصر الطريق المطلوبة',
            'childAddLabel' => 'إضافة عنصر طريق',
        ]);
    }

    public function updateStatus(InfAuditStatusRequest $request, RoadFacilitySurvey $road): JsonResponse
    {
        $data = $request->validated();

        $status = InfAuditStatus::query()->where('name', $data['status'])->firstOrFail();
        $this->authorizeStatusChange($status->name);

        $current = RoadFacilityAuditStatus::query()->firstOrNew([
            'globalid' => $road->globalid,
        ]);

        $assignedTo = array_key_exists('assigned_to', $data) ? $data['assigned_to'] : $current->assigned_to;

        if ($status->name === 'assigned' && ! $assignedTo) {
            return response()->json(['message' => 'يرجى اختيار المدقق.'], 422);
        }

        $isSameStatus = (int) $current->status_id === (int) $status->id
            && (int) ($current->assigned_to ?? 0) === (int) ($assignedTo ?? 0);

        $current->fill([
            'road_facility_survey_id' => $road->id,
            'objectid' => $road->objectid,
            'globalid' => $road->globalid,
            'status_id' => $status->id,
            'assigned_to' => $assignedTo,
            'updated_by' => Auth::id(),
            'notes' => $data['notes'] ?? null,
        ])->save();

        if ($status->name === 'assigned' && $assignedTo) {
            InfAuditAssignment::query()->updateOrCreate(
                [
                    'type' => 'road_facility',
                    'globalid' => $road->globalid,
                ],
                [
                    'manager_id' => Auth::id(),
                    'user_id' => $assignedTo,
                ],
            );
        }

        if (! $isSameStatus) {
            RoadFacilityAuditHistory::query()->create([
                'road_facility_survey_id' => $road->id,
                'objectid' => $road->objectid,
                'globalid' => $road->globalid,
                'status_id' => $status->id,
                'assigned_to' => $assignedTo,
                'user_id' => Auth::id(),
                'notes' => $data['notes'] ?? null,
            ]);
        }

        $assignment = $this->assignment($road->globalid);

        return response()->json([
            'message' => 'تم تحديث الحالة بنجاح.',
            'assignment' => [
                'user_name' => $assignment?->user?->name ?? $current->assignee?->name ?? '-',
                'manager_name' => $assignment?->manager?->name ?? '-',
                'updated_at' => $assignment?->updated_at?->format('Y-m-d H:i') ?? '-',
            ],
        ]);
    }

    public function storeChild(InfAuditChildStoreRequest $request, RoadFacilitySurvey $road): JsonResponse
    {
        $this->authorizeFieldEdit($road);

        $attributes = [
            'globalid' => (string) Str::uuid(),
            'parentglobalid' => $road->globalid,
        ];

        if (Schema::hasColumn('road_facility_survey_items', 'creationdate')) {
            $attributes['creationdate'] = now();
        }

        if (Schema::hasColumn('road_facility_survey_items', 'editdate')) {
            $attributes['editdate'] = now();
        }

        if (Schema::hasColumn('road_facility_survey_items', 'raw_payload')) {
            $attributes['raw_payload'] = [];
        }

        $item = RoadFacilitySurveyItem::query()->create($attributes);

        return response()->json([
            'message' => 'تمت إضافة عنصر طريق جديد للتدقيق.',
            'id' => $item->id,
            'globalid' => $item->globalid,
            'reload' => true,
        ]);
    }

    public function updateField(InfAuditFieldUpdateRequest $request, RoadFacilitySurvey $road): JsonResponse
    {
        $data = $request->validated();

        $this->authorizeFieldEdit($road);

        $record = $this->editableRecord($road, $data['table_type'], (int) $data['auditable_id']);
        $field = $this->fieldMeta($data['field_name'], $data['table_type']);
        $oldValue = $this->displayValue($record, $field, $data['table_type']);

        InfEditAssessment::query()->create([
            'auditable_type' => $data['table_type'] === self::ITEM_TABLE_TYPE ? 'road_facility_item' : 'road_facility',
            'auditable_id' => $record->id,
            'global_id' => $record->globalid ?? null,
            'objectid' => $record->objectid ?? null,
            'table_type' => $data['table_type'],
            'field_name' => $data['field_name'],
            'field_value' => $data['field_value'] ?? null,
            'old_value' => $oldValue,
            'user_id' => Auth::id(),
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'تم حفظ التعديل بنجاح.',
            'display_value' => $this->displayValue($record, $field, $data['table_type']),
            'raw_value' => $this->rawValue($record, $data['field_name'], $data['table_type']),
            'history' => $this->historyPayload($this->fieldHistory($record, $data['field_name'], $data['table_type'])),
        ]);
    }

    private function indexData(): array
    {
        return [
            'statuses' => InfAuditStatus::query()->orderBy('order_step')->get(),
            'engineers' => User::role('Inf - QC/QA Engineer')->orderBy('name')->get(['id', 'name']),
            'municipalities' => RoadFacilitySurvey::query()->whereNotNull('municipalitie')->distinct()->orderBy('municipalitie')->pluck('municipalitie'),
            'neighborhoods' => RoadFacilitySurvey::query()->whereNotNull('neighborhood')->distinct()->orderBy('neighborhood')->pluck('neighborhood'),
        ];
    }

    private function assignment(string $globalid): ?InfAuditAssignment
    {
        return InfAuditAssignment::query()
            ->with(['manager', 'user'])
            ->where('type', 'road_facility')
            ->where('globalid', $globalid)
            ->first();
    }

    private function editHistories(RoadFacilitySurvey $survey): array
    {
        $globalIds = collect([$survey->globalid])
            ->merge($survey->items->pluck('globalid'))
            ->filter()
            ->values()
            ->all();

        return InfEditAssessment::query()
            ->with('user')
            ->whereIn('global_id', $globalIds)
            ->latest()
            ->get()
            ->all();
    }

    private function surveySections(RoadFacilitySurvey $survey): array
    {
        return collect(RoadFacilitySurveyLayout::sections())
            ->reject(fn (array $section): bool => ($section['type'] ?? 'group') === 'repeat')
            ->map(fn (array $section): array => [
                'title' => $section['label'] ?: $section['name'],
                'rows' => $this->rows($survey, $section['fields'] ?? [], self::TABLE_TYPE),
            ])
            ->values()
            ->all();
    }

    private function itemGroups(RoadFacilitySurvey $survey): array
    {
        $itemSections = RoadFacilitySurveyLayout::repeatSections('R2');

        return $survey->items->map(fn (RoadFacilitySurveyItem $item, int $index): array => [
            'title' => 'Road Item '.($index + 1).' - '.($item->item_required ?: $item->objectid),
            'sections' => collect($itemSections)->map(fn (array $section): array => [
                'title' => $section['label'] ?: $section['name'],
                'rows' => $this->rows($item, $section['fields'] ?? [], self::ITEM_TABLE_TYPE),
            ])->values()->all(),
        ])->values()->all();
    }

    private function rows(object $record, array $fields, string $tableType): array
    {
        return collect($fields)
            ->reject(fn (array $field): bool => ($field['type'] ?? null) === 'calculate')
            ->map(fn (array $field): array => [
                'record_id' => $record->id,
                'table_type' => $tableType,
                'field_name' => $field['name'],
                'field_type' => $field['type'] ?? null,
                'list_name' => $field['list_name'] ?? null,
                'label' => $field['label'] ?: $field['name'],
                'value' => $this->displayValue($record, $field, $tableType),
                'raw_value' => $this->rawValue($record, $field['name'], $tableType),
                'options' => $this->fieldOptions($field['list_name'] ?? null),
                'history_id' => 'inf_history_'.md5($tableType.'|'.$record->id.'|'.$field['name']),
                'history' => $this->fieldHistory($record, $field['name'], $tableType),
            ])
            ->values()
            ->all();
    }

    private function displayValue(object $record, array $field, string $tableType): string
    {
        return $this->formatValue($this->rawValue($record, $field['name'], $tableType), $field);
    }

    private function rawValue(object $record, string $fieldName, string $tableType): ?string
    {
        $edit = InfEditAssessment::query()
            ->where('table_type', $tableType)
            ->where('field_name', $fieldName)
            ->where(function ($query) use ($record): void {
                if (filled($record->objectid ?? null)) {
                    $query->where('objectid', $record->objectid);
                }

                if (filled($record->globalid ?? null)) {
                    $query->orWhere('global_id', $record->globalid);
                }
            })
            ->latest()
            ->first();

        $value = $edit?->field_value ?? data_get($record, $fieldName) ?? data_get($record->raw_payload ?? [], $fieldName);

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

        return RoadFacilityFilter::query()
            ->where('list_name', $listName)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['name', 'label'])
            ->map(fn (RoadFacilityFilter $filter): array => [
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

        return RoadFacilityFilter::query()
            ->where('list_name', $listName)
            ->where('name', $value)
            ->value('label') ?: $value;
    }

    private function fieldHistory(object $record, string $fieldName, string $tableType): array
    {
        $field = $this->fieldMeta($fieldName, $tableType);

        return InfEditAssessment::query()
            ->with('user')
            ->where('table_type', $tableType)
            ->where('field_name', $fieldName)
            ->where(function ($query) use ($record): void {
                if (filled($record->objectid ?? null)) {
                    $query->where('objectid', $record->objectid);
                }

                if (filled($record->globalid ?? null)) {
                    $query->orWhere('global_id', $record->globalid);
                }
            })
            ->latest()
            ->get()
            ->each(function (InfEditAssessment $history) use ($field): void {
                $history->display_field_value = $this->formatValue($history->field_value, $field);
                $history->display_old_value = $this->formatValue($history->old_value, $field);
            })
            ->all();
    }

    private function fieldMeta(string $fieldName, string $tableType): array
    {
        $sections = $tableType === self::ITEM_TABLE_TYPE
            ? RoadFacilitySurveyLayout::repeatSections('R2')
            : RoadFacilitySurveyLayout::sections();

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
            ->map(fn (InfEditAssessment $item): array => [
                'field_value' => $item->display_field_value ?? $item->field_value ?? '-',
                'old_value' => $item->display_old_value ?? $item->old_value ?? '-',
                'user_name' => $item->user?->name ?? '-',
                'created_at' => $item->created_at?->format('Y-m-d h:i A') ?? '-',
                'notes' => $item->notes,
            ])
            ->all();
    }

    private function editableRecord(RoadFacilitySurvey $survey, string $tableType, int $id): object
    {
        if ($tableType === self::TABLE_TYPE && $survey->id === $id) {
            return $survey;
        }

        if ($tableType === self::ITEM_TABLE_TYPE) {
            return RoadFacilitySurveyItem::query()
                ->where('id', $id)
                ->where('parentglobalid', $survey->globalid)
                ->firstOrFail();
        }

        abort(404);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('objectid'), fn (Builder $q) => $q->where('objectid', '=', trim((string) $request->input('objectid'))));

        foreach (['municipalitie', 'neighborhood'] as $field) {
            $query->when($request->filled($field), fn (Builder $q) => $q->where($field, $request->string($field)));
        }

        $query->when($request->filled('auditor'), fn (Builder $q) => $q->whereHas('infAuditAssignment', fn (Builder $s) => $s->where('user_id', $request->integer('auditor'))));
        $query->when($request->filled('status'), fn (Builder $q) => $q->whereHas('infAuditStatus.status', fn (Builder $s) => $s->where('name', $request->string('status'))));
        $query->when($request->filled('from_date'), fn (Builder $q) => $q->whereDate($this->dateColumn(), '>=', $request->date('from_date')?->toDateString()));
        $query->when($request->filled('to_date'), fn (Builder $q) => $q->whereDate($this->dateColumn(), '<=', $request->date('to_date')?->toDateString()));
    }

    private function scopeVisibleToUser(Builder $query): void
    {
        if (Auth::user()?->hasRole('Inf - QC/QA Engineer') && ! Auth::user()?->hasAnyRole(['Database Officer', 'Team Leader -INF'])) {
            $query->whereHas('infAuditAssignment', fn (Builder $statusQuery) => $statusQuery->where('user_id', Auth::id()));
        }
    }

    private function authorizeRecord(RoadFacilitySurvey $survey): void
    {
        if (Auth::user()?->hasRole('Inf - QC/QA Engineer') && ! Auth::user()?->hasAnyRole(['Database Officer', 'Team Leader -INF'])) {
            abort_unless((int) ($survey->infAuditAssignment?->user_id ?? $survey->infAuditStatus?->assigned_to ?? 0) === Auth::id(), 403);
        }
    }

    private function authorizeFieldEdit(RoadFacilitySurvey $survey): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['Database Officer', 'Team Leader -INF']) || (Auth::user()?->hasRole('Inf - QC/QA Engineer') && (int) ($survey->infAuditAssignment?->user_id ?? $survey->infAuditStatus?->assigned_to ?? 0) === Auth::id()), 403);
    }

    private function authorizeStatusChange(string $status): void
    {
        if (in_array($status, ['assigned', 'final_approval'], true)) {
            abort_unless(Auth::user()?->hasAnyRole(['Database Officer', 'Team Leader -INF']), 403);

            return;
        }

        abort_unless(Auth::user()?->hasAnyRole(['Database Officer', 'Team Leader -INF', 'Inf - QC/QA Engineer']), 403);
    }

    private function statusBadge(?InfAuditStatus $status): string
    {
        return $status
            ? '<span class="'.e($status->badge_class).'">'.e($status->label).'</span>'
            : '<span class="badge badge-light">-</span>';
    }

    private function dateColumn(): string
    {
        return Schema::hasColumn('road_facility_surveys', 'creationdate') ? 'creationdate' : 'created_at';
    }
}
