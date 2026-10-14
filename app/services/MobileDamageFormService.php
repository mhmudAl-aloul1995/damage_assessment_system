<?php

namespace App\services;

use App\Http\Requests\InfAudit\InfAuditFieldUpdateRequest;
use App\Models\Assessment;
use App\Models\AssignedAssessmentUser;
use App\Models\Building;
use App\Models\CsoSurvey;
use App\Models\CsoSurveyFilter;
use App\Models\CsoSurveyOrganization;
use App\Models\CsoSurveyUnit;
use App\Models\EditAssessment;
use App\Models\Filter;
use App\Models\HousingUnit;
use App\Models\InfEditAssessment;
use App\Models\PublicBuildingFilter;
use App\Models\PublicBuildingSurvey;
use App\Models\PublicBuildingSurveyUnit;
use App\Models\RoadFacilityFilter;
use App\Models\RoadFacilitySurvey;
use App\Models\RoadFacilitySurveyItem;
use App\Models\User;
use App\Modules\DamageAssessment\Http\Controllers\InfrastructureAudit\InfAuditCsoSurveyController;
use App\Modules\DamageAssessment\Http\Controllers\InfrastructureAudit\InfAuditPublicBuildingController;
use App\Modules\DamageAssessment\Http\Controllers\InfrastructureAudit\InfAuditRoadFacilityController;
use App\Support\Audit\RestrictedLawyerAuditAccess;
use App\Support\Forms\CsoSurveyLayout;
use App\Support\Forms\PublicBuildingSurveyLayout;
use App\Support\Forms\RoadFacilitySurveyLayout;
use App\Support\Navigation\SectorNavigation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class MobileDamageFormService
{
    private const BLOCKED = ['id', 'objectid', 'globalid', 'parentglobalid', 'phase_number', 'field_status', 'assignedto', 'username', 'simserial', 'subscriberid', 'deviceid', 'phonenumber', 'start', 'end', 'today', 'location', 'latitude', 'longitude', 'attachments', 'creator', 'editor', 'creationdate', 'editdate', 'raw_payload', 'all_data', 'arcgis_hash', 'arcgis_synced_at', 'building_committee_status', 'unit_committee_status', 'unit_committee_count'];

    private ?array $cached = null;

    public function __construct(private SectorOverviewService $overview, private MobileDamageDetailService $details, private MobileFormDefinitionService $definitions, private AssessmentEditService $edits) {}

    /** @return array<string, array<string, mixed>> */
    public function definitions(): array
    {
        if ($this->cached !== null) {
            return $this->cached;
        }
        $result = [];
        foreach ($this->configurations() as $key => $config) {
            [$sections, $rules] = in_array($config['sector'], ['buildings', 'housing-units'], true) ? $this->assessmentFields($config) : $this->infrastructureFields($config);
            if ($sections !== []) {
                $result[$key] = [...$this->definitions->define('damage_assessment', $config['title'], true, $rules, $sections), 'sector' => $config['sector'], 'target_kind' => isset($config['repeat']) ? 'child' : 'record'];
            }
        }

        return $this->cached = $result;
    }

    public function allowed(User $user, string $form): bool
    {
        $config = $this->configurations()[$form] ?? null;
        if ($config === null || RestrictedLawyerAuditAccess::isRestrictedLawyer($user)) {
            return false;
        }
        if (! collect(SectorNavigation::forUser($config['sector'], $user))->contains('key', 'overview')) {
            return false;
        }
        if (in_array($config['sector'], ['buildings', 'housing-units'], true)) {
            return ! $user->hasRole('Team Leader') && $this->details->canAudit($user, $config['sector'])
                && $user->hasAnyRole(['Database Officer', 'Auditing Supervisor', 'Audit Reviewer', 'QC/QA Engineer', 'Engineering Auditor', 'Legal Auditor']);
        }
        $roles = ['Database Officer', 'Team Leader -INF', 'Inf - QC/QA Engineer'];
        if ($config['sector'] === 'cso-surveys') {
            $roles[] = 'CSO Officer';
        }

        return $user->hasAnyRole($roles) && collect(SectorNavigation::forUser($config['sector'], $user))->contains('key', 'audit');
    }

    public function target(User $user, string $form, int $id, bool $lock = false): Model
    {
        abort_unless($this->allowed($user, $form), 403);
        $config = $this->configurations()[$form];
        $filters = $this->filters($user);
        if (in_array($config['sector'], ['buildings', 'housing-units'], true)) {
            $visible = $this->overview->inquiryModel($config['sector'], $id, $filters);
            $row = $config['model']::query()->where('globalid', $visible->globalid)->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())->firstOrFail();
            $building = $config['sector'] === 'buildings' ? $row : Building::query()->where('globalid', $row->parentglobalid)->firstOrFail();
            $this->checkPhase($user, $row);
            $this->checkPhase($user, $building);
            if (! $user->hasAnyRole(['Database Officer', 'Auditing Supervisor', 'Audit Reviewer'])) {
                $types = [];
                if ($user->hasAnyRole(['QC/QA Engineer', 'Engineering Auditor'])) {
                    $types[] = 'QC/QA Engineer';
                }
                if ($user->hasRole('Legal Auditor')) {
                    $types[] = 'Legal Auditor';
                }
                abort_unless(AssignedAssessmentUser::query()->where('building_id', $building->objectid)->where('user_id', $user->id)->whereIn('type', $types)->exists(), 403);
            }

            return $row;
        }
        $row = $config['model']::query()->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())->findOrFail($id);
        $parent = isset($config['repeat']) ? $config['parent']::query()->where('globalid', $row->parentglobalid)->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())->firstOrFail() : $row;
        $this->overview->inquiryModel($config['sector'], $parent->id, $filters);
        $this->checkPhase($user, $row);
        $roles = ['Database Officer', 'Team Leader -INF'];
        if ($config['sector'] === 'cso-surveys') {
            $roles[] = 'CSO Officer';
        }
        if (! $user->hasAnyRole($roles)) {
            abort_unless((int) ($parent->infAuditAssignment?->user_id ?? $parent->infAuditStatus?->assigned_to ?? 0) === $user->id, 403);
        }

        return $row;
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function records(User $user, string $form, array $filters): array
    {
        $config = $this->configurations()[$form];
        abort_unless($this->allowed($user, $form), 403);
        if (isset($config['repeat'])) {
            $query = $config['model']::query();
            $allowedPhases = $this->filters($user)['_allowed_phases'] ?? [];
            if ($allowedPhases !== [] && Schema::hasColumn((new $config['model'])->getTable(), 'phase_number')) {
                $query->whereIn('phase_number', $allowedPhases);
            }
            $parentQuery = $config['parent']::query();
            if ($allowedPhases !== []) {
                $parentQuery->whereIn('phase_number', $allowedPhases);
            }
            if (! $user->hasAnyRole(['Database Officer', 'Team Leader -INF', ...($config['sector'] === 'cso-surveys' ? ['CSO Officer'] : [])])) {
                $parentQuery->where(function (Builder $query) use ($user): void {
                    $query->whereHas('infAuditAssignment', fn (Builder $assignment): Builder => $assignment->where('user_id', $user->id))
                        ->orWhereHas('infAuditStatus', fn (Builder $status): Builder => $status->where('assigned_to', $user->id));
                });
            }
            if (filled($filters['search'] ?? null)) {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['search'])).'%';
                $parentQuery->whereRaw($config['name_column']." LIKE ? ESCAPE '!'", [$pattern]);
            }
            $query->whereIn('parentglobalid', $parentQuery->select('globalid'));
            $page = $query->orderByDesc('id')->paginate(20, ['*'], 'page', $filters['page'] ?? 1);
            $parents = $config['parent']::query()->whereIn('globalid', $page->getCollection()->pluck('parentglobalid'))->get(['globalid', $config['name_column']])->keyBy('globalid');

            return ['data' => $page->getCollection()->map(fn (Model $row): array => [
                'id' => $row->id, 'module' => 'damage_assessment', 'title' => $parents->get($row->parentglobalid)?->{$config['name_column']} ?: $config['title'],
                'subtitle' => 'سجل فرعي #'.($row->objectid ?? $row->id), 'status' => null,
            ])->all(), 'total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()];
        }
        $internal = $this->filters($user);
        if (in_array($config['sector'], ['buildings', 'housing-units'], true)) {
            $internal['_mobile_require_source'] = true;
            if (! $user->hasAnyRole(['Database Officer', 'Auditing Supervisor', 'Audit Reviewer'])) {
                $internal['_mobile_audit_user'] = $user->id;
                $internal['_mobile_audit_types'] = [...($user->hasAnyRole(['QC/QA Engineer', 'Engineering Auditor']) ? ['QC/QA Engineer'] : []), ...($user->hasRole('Legal Auditor') ? ['Legal Auditor'] : [])];
            }
        } elseif (! $user->hasAnyRole(['Database Officer', 'Team Leader -INF', ...($config['sector'] === 'cso-surveys' ? ['CSO Officer'] : [])])) {
            $internal['_mobile_audit_user'] = $user->id;
        }
        $result = $this->overview->records($config['sector'], [...$filters, ...$internal]);
        $result['data'] = array_map(fn (array $row): array => ['id' => $row['record_id'], 'module' => 'damage_assessment', 'title' => $row['name'] ?? $row['building_name'] ?? $config['title'], 'subtitle' => trim(($row['municipality'] ?? '').' '.($row['neighborhood'] ?? '')), 'status' => $row['damage_status'] ?? null], $result['data']);

        return $result;
    }

    /** @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    public function answers(string $form, Model $row, array $definition): array
    {
        $config = $this->configurations()[$form];
        $edits = $config['sector'] === 'cso-surveys' ? collect() : $this->editRows($form, $row)->get()->unique('field_name')->keyBy('field_name');
        $answers = [];
        foreach ($definition['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                $value = $config['sector'] === 'cso-surveys'
                    ? app(CsoAuditService::class)->value($row, $field['key'])
                    : ($edits->get($field['key'])?->field_value ?? (isset($config['layout']) ? $config['layout']::value($row, $field['key']) : $row->getRawOriginal($field['key'])));
                if ($field['type'] === 'multiselect') {
                    $value = is_array($value) ? $value : (filled($value) ? preg_split('/[,\s]+/u', (string) $value, -1, PREG_SPLIT_NO_EMPTY) : []);
                }
                $answers[$field['key']] = is_scalar($value) || is_array($value) ? $value : null;
            }
        }

        return $answers;
    }

    public function version(string $form, Model $row): string
    {
        if ($this->configurations()[$form]['sector'] === 'cso-surveys') {
            return hash('sha256', json_encode($row->getAttributes(), JSON_THROW_ON_ERROR));
        }

        return hash('sha256', json_encode([$row->getAttributes(), $this->editRows($form, $row)->get(['id', 'field_name', 'field_value'])->toArray()], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $answers */
    public function save(string $form, Model $row, array $answers, Request $request): void
    {
        $config = $this->configurations()[$form];
        $definition = $this->definitions()[$form];
        $current = $this->answers($form, $row, $definition);
        $fields = collect($definition['sections'])->flatMap(fn (array $section): array => $section['fields'])->keyBy('key');
        foreach ($answers as $field => $value) {
            $previous = $current[$field] ?? null;
            $type = $fields->get($field)['type'] ?? 'text';
            if (in_array($type, ['integer', 'decimal'], true) && is_numeric($previous) && is_numeric($value) && (float) $previous === (float) $value) {
                continue;
            }
            if (is_array($previous) && is_array($value)) {
                sort($previous);
                $comparison = $value;
                sort($comparison);
                if ($previous === $comparison) {
                    continue;
                }
            } elseif (trim((string) $previous) === trim((string) $value)) {
                continue;
            }
            if (in_array($config['sector'], ['buildings', 'housing-units'], true)) {
                $this->edits->save($config['table_type'], (string) $row->globalid, $field, $value, $request);
            } else {
                $formRequest = InfAuditFieldUpdateRequest::createFrom($request, new InfAuditFieldUpdateRequest);
                $formRequest->replace(['table_type' => $config['table_type'], 'auditable_id' => $row->id, 'field_name' => $field, 'field_value' => is_array($value) ? implode(',', $value) : ($value === null ? null : (string) $value)]);
                $formRequest->setContainer(app())->setRedirector(app('redirect'));
                $formRequest->validateResolved();
                $parent = isset($config['repeat']) ? $config['parent']::query()->where('globalid', $row->parentglobalid)->firstOrFail() : $row;
                app($config['controller'])->updateField($formRequest, $parent);
            }
        }
    }

    private function editRows(string $form, Model $row): Builder
    {
        $config = $this->configurations()[$form];
        if (in_array($config['sector'], ['buildings', 'housing-units'], true)) {
            return EditAssessment::query()->where('global_id', $row->globalid)->where('type', $config['table_type'])->orderByDesc('id');
        }

        return InfEditAssessment::query()->where('table_type', $config['table_type'])->where('auditable_id', $row->id)->orderByDesc('id');
    }

    /** @return array<string, mixed> */
    private function filters(User $user): array
    {
        $filters = ['_inquiry' => true];
        $phases = array_values(array_filter(array_map('intval', $user->allowed_phase_numbers ?? []), fn (int $phase): bool => $phase > 0));
        if ($phases !== []) {
            $filters['_allowed_phases'] = $phases;
        }

        return $filters;
    }

    private function checkPhase(User $user, Model $row): void
    {
        $phases = $this->filters($user)['_allowed_phases'] ?? [];
        if ($phases !== [] && Schema::hasColumn($row->getTable(), 'phase_number')) {
            abort_unless(in_array((int) $row->phase_number, $phases, true), 404);
        }
    }

    /** @param array<string, mixed> $config
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, array<mixed>>}
     */
    private function assessmentFields(array $config): array
    {
        $model = new $config['model'];
        $rows = Assessment::query()->orderBy('id')->get(['id', 'name', 'label']);
        $housingStart = $rows->firstWhere('name', 'housing_unit_group')?->id;
        $buildingEnd = $rows->firstWhere('name', 'housing_unit')?->id ?? $housingStart;
        $columns = collect($model->getConnection()->getSchemaBuilder()->getColumns($model->getTable()))->keyBy('name');
        $options = Filter::query()->whereIn('list_name', $rows->pluck('name'))->get(['list_name', 'name', 'label'])->groupBy('list_name');
        $fields = [];
        $rules = [];
        foreach ($rows as $row) {
            if (in_array(strtolower($row->name), self::BLOCKED, true) || ! in_array($row->name, $model->getFillable(), true) || ! $columns->has($row->name)) {
                continue;
            }
            if ($config['sector'] === 'housing-units' ? ($housingStart === null || $row->id < $housingStart) : ($buildingEnd !== null && $row->id >= $buildingEnd)) {
                continue;
            }
            $choices = ($options->get($row->name) ?? collect())->mapWithKeys(fn (Filter $option): array => [(string) $option->name => (string) ($option->label ?: $option->name)])->all();
            $column = $columns->get($row->name);
            $type = $choices !== [] ? 'select' : match (true) {
                str_contains($column['type_name'], 'int') => 'integer',
                in_array($column['type_name'], ['float', 'double', 'decimal', 'numeric', 'real'], true) => 'decimal',
                in_array($column['type_name'], ['json', 'blob'], true) => 'unsupported',
                default => 'text',
            };
            if ($type === 'unsupported') {
                continue;
            }
            $fields[] = $this->definitions->field($row->name, $row->label ?: $row->name, $type, $choices);
            $rules[$row->name] = ['nullable', ...($type === 'integer' ? ['integer'] : ($type === 'decimal' ? ['numeric'] : ['string', 'max:10000']))];
        }
        $sections = array_map(fn (array $fields, int $index): array => ['title' => 'بيانات الاستبيان · '.($index + 1), 'fields' => $fields], array_chunk($fields, 12), array_keys(array_chunk($fields, 12)));

        return [$sections, $rules];
    }

    /** @param array<string, mixed> $config
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, array<mixed>>}
     */
    private function infrastructureFields(array $config): array
    {
        $layout = $config['layout'];
        $sections = isset($config['repeat']) ? $layout::repeatSections($config['repeat']) : $layout::sections();
        $childNames = collect($layout::sections())->filter(fn (array $section): bool => ($section['type'] ?? '') === 'repeat')->flatMap(fn (array $section): array => collect($layout::repeatSections($section['name']))->flatMap(fn (array $child): array => $child['fields'])->pluck('name')->all())->all();
        $options = $config['filter']::query()->get(['list_name', 'name', 'label'])->groupBy('list_name');
        $rules = [];
        $groups = [];
        $seen = [];
        foreach ($sections as $section) {
            $fields = [];
            foreach ($section['fields'] as $field) {
                $key = $field['name'];
                if (in_array(strtolower($key), self::BLOCKED, true) || isset($seen[$key]) || (! isset($config['repeat']) && (in_array($key, $childNames, true) || $section['type'] === 'repeat'))) {
                    continue;
                }
                $type = match ($field['type']) {
                    'text' => 'text', 'integer' => 'integer', 'decimal' => 'decimal', 'date' => 'date',
                    'select_one' => 'select', 'select_multiple' => 'multiselect', default => 'unsupported',
                };
                if ($type === 'unsupported') {
                    continue;
                }
                $choices = ($options->get($field['list_name']) ?? collect())->mapWithKeys(fn (Model $option): array => [(string) $option->name => (string) ($option->label ?: $option->name)])->all();
                if ($choices === []) {
                    $choices = $layout::choices()[$field['list_name']] ?? [];
                }
                if (in_array($type, ['select', 'multiselect'], true) && $choices === []) {
                    continue;
                }
                $seen[$key] = true;
                $label = preg_match('/\p{Arabic}/u', $field['hint'] ?? '') ? $field['hint'] : $field['label'];
                $fields[] = $this->definitions->field($key, $label, $type, $choices);
                $rules[$key] = ['nullable', ...match ($type) {
                    'integer' => ['integer'], 'decimal' => ['numeric'], 'date' => ['date'], 'multiselect' => ['array', 'max:20'],
                    default => ['string', 'max:10000'],
                }];
                if ($type === 'multiselect') {
                    $rules[$key.'.*'] = ['string', 'max:255'];
                }
            }
            if ($fields !== []) {
                $title = preg_match('/\p{Arabic}/u', $section['hint'] ?? '') ? $section['hint'] : $section['label'];
                $groups[] = ['title' => $title, 'fields' => $fields];
            }
        }

        return [$groups, $rules];
    }

    /** @return array<string, array<string, mixed>> */
    private function configurations(): array
    {
        $public = ['sector' => 'public-buildings', 'parent' => PublicBuildingSurvey::class, 'layout' => PublicBuildingSurveyLayout::class, 'controller' => InfAuditPublicBuildingController::class, 'filter' => PublicBuildingFilter::class, 'name_column' => 'building_name'];
        $road = ['sector' => 'road-facilities', 'parent' => RoadFacilitySurvey::class, 'layout' => RoadFacilitySurveyLayout::class, 'controller' => InfAuditRoadFacilityController::class, 'filter' => RoadFacilityFilter::class, 'name_column' => 'str_name'];
        $cso = ['sector' => 'cso-surveys', 'parent' => CsoSurvey::class, 'layout' => CsoSurveyLayout::class, 'controller' => InfAuditCsoSurveyController::class, 'filter' => CsoSurveyFilter::class, 'name_column' => 'organization_name'];

        return [
            'building-audit' => ['sector' => 'buildings', 'model' => Building::class, 'table_type' => 'building_table', 'title' => 'تحديث استبيان تدقيق مبنى'],
            'housing-audit' => ['sector' => 'housing-units', 'model' => HousingUnit::class, 'table_type' => 'housing_table', 'title' => 'تحديث استبيان تدقيق وحدة سكنية'],
            'public-building-audit' => [...$public, 'model' => PublicBuildingSurvey::class, 'table_type' => 'public_building_table', 'title' => 'تدقيق مبنى عام'],
            'public-building-unit-audit' => [...$public, 'model' => PublicBuildingSurveyUnit::class, 'table_type' => 'public_building_unit_table', 'repeat' => 'Unit_Information', 'title' => 'تدقيق وحدة في مبنى عام'],
            'road-audit' => [...$road, 'model' => RoadFacilitySurvey::class, 'table_type' => 'road_facility_table', 'title' => 'تدقيق طريق أو مرفق'],
            'road-item-audit' => [...$road, 'model' => RoadFacilitySurveyItem::class, 'table_type' => 'road_facility_item_table', 'repeat' => 'R2', 'title' => 'تدقيق عنصر في طريق أو مرفق'],
            'cso-audit' => [...$cso, 'model' => CsoSurvey::class, 'table_type' => 'cso_survey_table', 'title' => 'تدقيق استبيان مؤسسة'],
            'cso-organization-audit' => [...$cso, 'model' => CsoSurveyOrganization::class, 'table_type' => 'cso_survey_organization_table', 'repeat' => 'CSO_Organizations', 'title' => 'تدقيق مؤسسة ضمن الاستبيان'],
            'cso-unit-audit' => [...$cso, 'model' => CsoSurveyUnit::class, 'table_type' => 'cso_survey_unit_table', 'repeat' => 'Unit_Information', 'title' => 'تدقيق وحدة ضمن استبيان مؤسسة'],
        ];
    }
}
