<?php

namespace App\Modules\HeksShelter\Services;

use App\Models\User;
use App\Modules\HeksShelter\Models\SurveyQuestion;
use App\Modules\HeksShelter\Models\SurveyRevision;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class SurveyDesigner
{
    private array $questionCache = [];

    public const PROTECTED = ['code', 'name', 'identity_number', 'phone', 'governorate', 'visit_date', 'eligibility', 'safety_risks', 'safe_environment', 'safe_entry', 'structurally_safe', 'ownership_proof', 'uxo', 'legal_dispute', 'owner_permission_obstacle', 'can_manage_cash', 'family_total', 'unit_area', 'damage', 'address', 'recommendations'];

    public const TYPES = ['text' => 'نص', 'phone' => 'هاتف', 'multiline' => 'نص طويل', 'integer' => 'عدد صحيح', 'decimal' => 'رقم عشري', 'date' => 'تاريخ', 'boolean' => 'نعم / لا', 'select' => 'اختيار واحد', 'multiselect' => 'اختيار متعدد', 'repeat' => 'مجموعة متكررة'];

    public function initialize(): void
    {
        if (SurveyRevision::query()->exists()) {
            return;
        }
        $revision = SurveyRevision::query()->create(['title' => 'HEKS — استبيان المرحلة الثالثة لإصلاح المأوى', 'status' => 'published', 'published_at' => now()]);
        foreach (require dirname(__DIR__).'/resources/survey.php' as $sectionIndex => $section) {
            foreach ($section['fields'] as $position => $field) {
                $question = $revision->questions()->create(['key' => $field['key'], 'label' => $field['label'], 'type' => $field['type'], 'section' => $section['title'], 'section_order' => $sectionIndex, 'position' => $position,
                    'required' => $field['required'], 'enabled' => true, 'visible_field' => $field['visible']['field'] ?? null, 'visible_value' => $field['visible']['equals'] ?? null]);
                foreach ($field['choices'] as $value => $label) {
                    $question->choices()->create(['value' => $value, 'label' => $label, 'position' => $question->choices()->count(), 'exclusive' => $value === 'none']);
                }
            }
        }
    }

    public function published(): SurveyRevision
    {
        return SurveyRevision::query()->where('status', 'published')->latest('id')->firstOrFail();
    }

    public function questionsFor(?int $revision = null): Collection
    {
        $revision ??= $this->published()->id;

        return $this->questionCache[$revision] ??= SurveyQuestion::query()->where('revision_id', $revision)->with('choices')->orderBy('section_order')->orderBy('position')->orderBy('id')->get();
    }

    public function sections(?int $revision = null): array
    {
        $questions = $this->questionsFor($revision);

        return $questions->whereNull('parent_key')->where('enabled', true)->groupBy('section')->map(fn ($rows, string $title): array => ['title' => $title, 'fields' => $rows->map(fn ($question): array => $this->field($question, $questions))->values()->all()])->values()->all();
    }

    private function field(SurveyQuestion $question, Collection $questions): array
    {
        return ['key' => $question->key, 'label' => $question->label, 'type' => $question->type, 'required' => $question->required,
            'choices' => $question->choices->pluck('label', 'value')->all(), 'visible' => $question->visible_field ? ['field' => $question->visible_field, 'equals' => $question->visible_value] : null,
            'minimum' => $question->minimum, 'maximum' => $question->maximum, 'max_length' => $question->max_length, 'help' => $question->help, 'error_message' => $question->error_message,
            'exclusive' => $question->choices->where('exclusive', true)->pluck('value')->all(),
            'children' => $questions->where('parent_key', $question->key)->where('enabled', true)->map(fn ($child): array => $this->field($child, $questions))->values()->all()];
    }

    public function draft(User $user): SurveyRevision
    {
        return (new SurveyRevision)->getConnection()->transaction(function () use ($user): SurveyRevision {
            $published = SurveyRevision::query()->whereKey($this->published()->id)->lockForUpdate()->firstOrFail();
            $draft = SurveyRevision::query()->where('status', 'draft')->first();
            if ($draft) {
                return $draft;
            }
            $draft = SurveyRevision::query()->create(['title' => $published->title, 'user_id' => $user->id]);
            foreach ($published->questions()->with('choices')->get() as $question) {
                $new = $draft->questions()->create($question->except(['id', 'revision_id', 'created_at', 'updated_at', 'choices']));
                foreach ($question->choices as $choice) {
                    $new->choices()->create($choice->except(['id', 'question_id', 'created_at', 'updated_at']));
                }
            }

            return $draft;
        });
    }

    public function save(User $user, array $input): void
    {
        (new SurveyRevision)->getConnection()->transaction(function () use ($user, $input): void {
            $draft = SurveyRevision::query()->whereKey($input['revision_id'])->where('status', 'draft')->lockForUpdate()->firstOrFail();
            abort_unless($draft->edit_version === (int) $input['edit_version'], 409, 'عدّل مستخدم آخر الإعدادات. حدّث الصفحة.');
            $question = isset($input['question_id']) ? $draft->questions()->findOrFail($input['question_id']) : new SurveyQuestion(['revision_id' => $draft->id]);
            $original = SurveyQuestion::query()->whereHas('revision', fn ($query) => $query->where('status', 'published'))->where('key', $question->key ?? $input['key'])->where('parent_key', $question->exists ? $question->parent_key : ($input['parent_key'] ?? null))->first();
            if ($original && ($original->key !== $input['key'] || $original->type !== $input['type'] || ($original->parent_key ?? '') !== ($input['parent_key'] ?? ''))) {
                throw ValidationException::withMessages(['key' => 'مفتاح السؤال المنشور ونوعه ومجموعته ثابتة لحماية البيانات.']);
            }
            if (in_array($input['key'], self::PROTECTED, true) && ((! ($input['enabled'] ?? false)) || ($input['visible_field'] ?? null) !== ($original?->visible_field) || ($input['visible_value'] ?? null) !== ($original?->visible_value))) {
                throw ValidationException::withMessages(['enabled' => 'هذا سؤال أساسي لا يمكن تعطيله أو تغيير شرط ظهوره.']);
            }
            if (in_array($input['key'], self::PROTECTED, true) && $original?->required && ! $input['required']) {
                throw ValidationException::withMessages(['required' => 'هذا سؤال أساسي مطلوب لاستلام الملف.']);
            }
            if ($draft->questions()->where('key', $input['key'])->where('parent_key', $input['parent_key'] ?? null)->when($question->exists, fn ($query) => $query->whereKeyNot($question->id))->exists()) {
                throw ValidationException::withMessages(['key' => 'مفتاح السؤال مستخدم.']);
            }
            if (! empty($input['parent_key'])) {
                $draft->questions()->where('key', $input['parent_key'])->where('type', 'repeat')->whereNull('parent_key')->firstOrFail();
                if (in_array($input['type'], ['repeat', 'multiselect'], true)) {
                    throw ValidationException::withMessages(['type' => 'حقول المجموعة تدعم القيم المفردة فقط.']);
                }
            }
            if (! empty($input['visible_field'])) {
                $parent = $draft->questions()->whereNull('parent_key')->where('key', $input['visible_field'])->where('enabled', true)->first();
                if (! $parent || $parent->key === $input['key'] || $parent->visible_field || ! in_array($parent->type, ['text', 'phone', 'select'], true) || ! empty($input['parent_key'])) {
                    throw ValidationException::withMessages(['visible_field' => 'اختر سؤالاً مستقلاً نصياً أو اختياراً واحداً لشرط الظهور.']);
                }
            }
            $question->fill(collect($input)->only(['key', 'label', 'type', 'section', 'section_order', 'position', 'required', 'enabled', 'parent_key', 'visible_field', 'visible_value', 'minimum', 'maximum', 'max_length', 'help', 'error_message'])->all())->save();
            $choices = $input['choices'] ?? [];
            if ($original && in_array($question->type, ['select', 'multiselect'], true)) {
                $publishedChoices = \App\Modules\HeksShelter\Models\SurveyChoice::query()->whereHas('question', fn ($query) => $query->where('key', $question->key)->where('parent_key', $question->parent_key)->whereHas('revision', fn ($revision) => $revision->where('status', 'published')))->get();
                foreach ($publishedChoices as $choice) {
                    if (! in_array($choice->value, array_column($choices, 'value'), true)) {
                        throw ValidationException::withMessages(['choices' => 'لا يمكن حذف قيمة خيار منشور. يمكن تعديل تسميته أو إخفاء السؤال.']);
                    }
                }
            }
            $question->choices()->delete();
            foreach ($choices as $index => $choice) {
                $question->choices()->create([...$choice, 'position' => $index]);
            }
            $draft->update(['edit_version' => $draft->edit_version + 1, 'user_id' => $user->id]);
            unset($this->questionCache[$draft->id]);
        });
    }

    public function publish(User $user, int $id, int $version): void
    {
        Cache::lock('heks-survey-publish', 120)->block(10, function () use ($user, $id, $version): void {
            $draft = SurveyRevision::query()->whereKey($id)->where('status', 'draft')->firstOrFail();
            abort_unless($draft->edit_version === $version, 409, 'تغيرت المسودة. راجعها قبل النشر.');
            foreach ($draft->questions()->where('enabled', true)->get() as $question) {
                if ($question->visible_field && ! $draft->questions()->whereNull('parent_key')->where('enabled', true)->where('key', $question->visible_field)->exists()) {
                    throw ValidationException::withMessages(['publish' => 'سؤال شرط الظهور مفقود أو مخفي: '.$question->label]);
                }
                if (in_array($question->type, ['select', 'multiselect'], true) && ! $question->choices()->exists()) {
                    throw ValidationException::withMessages(['publish' => 'السؤال '.$question->label.' يحتاج خيارات.']);
                }
                if ($question->type === 'repeat' && ! $draft->questions()->where('parent_key', $question->key)->where('enabled', true)->exists()) {
                    throw ValidationException::withMessages(['publish' => 'أضف حقول المجموعة المتكررة قبل النشر.']);
                }
            }
            app(SurveyAnswerStorage::class)->ensureColumns($draft);
            $draft->getConnection()->transaction(function () use ($draft, $version, $user): void {
                $locked = SurveyRevision::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->status === 'draft' && $locked->edit_version === $version, 409, 'تغيرت المسودة أثناء التجهيز. أعد المعاينة.');
                $locked->update(['status' => 'published', 'published_at' => now(), 'user_id' => $user->id]);
            });
        });
    }

    public function updateSection(User $user, array $input): void
    {
        (new SurveyRevision)->getConnection()->transaction(function () use ($user, $input): void {
            $draft = SurveyRevision::query()->whereKey($input['revision_id'])->where('status', 'draft')->lockForUpdate()->firstOrFail();
            abort_unless($draft->edit_version === (int) $input['edit_version'], 409, 'تغيرت المسودة. حدّث الصفحة.');
            $draft->questions()->where('section', $input['old_section'])->update(['section' => $input['section'], 'section_order' => $input['section_order']]);
            $draft->update(['edit_version' => $draft->edit_version + 1, 'user_id' => $user->id]);
            unset($this->questionCache[$draft->id]);
        });
    }

    public function filterCatalog(): array
    {
        return SurveyQuestion::query()->whereHas('revision', fn ($query) => $query->where('status', 'published'))->orderByDesc('revision_id')->get()->unique(fn ($row) => ($row->parent_key ? $row->parent_key.'.' : '').$row->key)->mapWithKeys(fn ($row) => [($row->parent_key ? $row->parent_key.'.' : '').$row->key => $row])->all();
    }

    public function applyFilters(\Illuminate\Database\Eloquent\Builder $query, array $filters): void
    {
        $catalog = $this->filterCatalog();
        foreach ($filters as $index => $filter) {
            $question = $catalog[$filter['field']] ?? null;
            if (! $question) {
                throw ValidationException::withMessages(['survey_filters.'.$index.'.field' => 'سؤال الفلترة غير موجود أو غير منشور.']);
            }
            $operator = $filter['operator'];
            $value = $filter['value'] ?? null;
            if (! in_array($operator, ['empty', 'filled'], true) && ($value === null || $value === '')) {
                throw ValidationException::withMessages(['survey_filters.'.$index.'.value' => 'أدخل قيمة الفلتر.']);
            }
            if (in_array($question->type, ['integer', 'decimal', 'repeat', 'boolean'], true) && ! in_array($operator, ['empty', 'filled'], true) && ! is_numeric($value)) {
                throw ValidationException::withMessages(['survey_filters.'.$index.'.value' => 'أدخل قيمة رقمية.']);
            }
            if ($question->type === 'date' && ! in_array($operator, ['empty', 'filled'], true) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value ?? '')) {
                throw ValidationException::withMessages(['survey_filters.'.$index.'.value' => 'أدخل تاريخاً بصيغة سنة-شهر-يوم.']);
            }
            $sqlOperator = ['eq' => '=', 'ne' => '!=', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='][$operator] ?? '=';
            if ($question->type === 'multiselect') {
                if (! in_array($operator, ['eq', 'ne', 'empty', 'filled'], true)) {
                    throw ValidationException::withMessages(['survey_filters' => 'الاختيار المتعدد يدعم يساوي، لا يساوي، فارغ، غير فارغ.']);
                }
                $method = in_array($operator, ['ne', 'empty'], true) ? 'whereNotExists' : 'whereExists';
                $query->$method(function ($sub) use ($question, $operator, $value): void {
                    $sub->selectRaw('1')->from('heks_survey_selections')->whereColumn('case_id', 'heks_shelter_cases.id')->where('field_key', $question->key);
                    if (in_array($operator, ['eq', 'ne'], true)) {
                        $sub->where('value', $value);
                    }
                });

                continue;
            }
            $apply = function ($builder, string $column) use ($operator, $value, $sqlOperator): void {
                if ($operator === 'empty') {
                    $builder->whereNull($column);
                } elseif ($operator === 'filled') {
                    $builder->whereNotNull($column);
                } elseif ($operator === 'contains') {
                    $builder->whereRaw($column." LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%']);
                } else {
                    $builder->where($column, $sqlOperator, $value);
                }
            };
            if ($question->parent_key) {
                $query->whereExists(function ($sub) use ($question, $apply): void {
                    $sub->selectRaw('1')->from('heks_rows_'.$question->parent_key)->whereColumn('case_id', 'heks_shelter_cases.id');
                    $apply($sub, SurveyAnswerStorage::column($question->key));
                });
            } else {
                $apply($query, SurveyAnswerStorage::column($question->key));
            }
        }
    }
}
