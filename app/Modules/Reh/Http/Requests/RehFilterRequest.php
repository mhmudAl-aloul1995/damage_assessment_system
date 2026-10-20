<?php

namespace App\Modules\Reh\Http\Requests;

use App\Modules\Reh\Services\RehAdvancedFilters;
use App\Modules\Reh\Services\RehExportColumns;
use App\Modules\Reh\Services\RehFilterChoices;
use App\Modules\Reh\Services\RehSurveyFilters;
use Illuminate\Foundation\Http\FormRequest;

class RehFilterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('column_set')) {
            if (! is_string($this->input('column_set')) || $this->exists('columns')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['columns' => 'اختيار أعمدة غير صالح.']);
            }
            $this->merge(['columns' => RehExportColumns::decode($this->input('column_set'))]);
        }
        foreach (RehSurveyFilters::DEPENDENCIES as $child => [$parent, $value]) {
            if ($this->filled($child) && ! $this->filled($parent) && ! in_array($this->input($child.'_op'), ['blank', 'not_blank'], true)) {
                $this->merge([$parent => $value]);
            }
        }
        if (! $this->exists('survey_status')) {
            $this->merge(['survey_status' => 'completed']);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return ($this->user()?->can('reh.view') ?? false)
            && (! $this->anyFilled(['priority', ...RehAdvancedFilters::SCORE_FILTERS]) || $this->user()->can('reh.scores.view'))
            && (! $this->anyFilled([...RehAdvancedFilters::SENSITIVE_FILTERS, ...app(RehSurveyFilters::class)->keys(sensitiveOnly: true)]) || $this->user()->can('reh.sensitive'))
            && $this->canExportColumns();
    }

    private function canExportColumns(): bool
    {
        $definitions = RehExportColumns::definitions();
        foreach (is_array($this->input('columns')) ? $this->input('columns') : [] as $key) {
            $definition = is_string($key) ? ($definitions[$key] ?? []) : [];
            if (isset($definition['permission']) && ! $this->user()?->can($definition['permission'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'columns' => ['sometimes', 'required', 'array', 'list', 'min:1', 'max:'.count(RehExportColumns::definitions())],
            'columns.*' => ['required', 'string', 'distinct', \Illuminate\Validation\Rule::in(array_keys(RehExportColumns::definitions()))],
            'q' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', \Illuminate\Validation\Rule::in([...array_keys(\App\Modules\Reh\Services\RehScoring::PRIORITIES), 'unclassified'])],
            'survey_status' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Modules\Reh\Services\RehSurvey::FIELD_STATUSES))],
            'status' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Modules\Reh\Services\RehSurvey::WORKFLOW))],
            'governorate' => ['nullable', 'string', 'max:100'],
            'engineer' => ['nullable', 'string', 'max:100'],
            'neighborhood' => ['nullable', 'string', 'max:100'],
            'visit_from' => ['nullable', 'date_format:Y-m-d'],
            'visit_to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('visit_from') ? ['after_or_equal:visit_from'] : [])],
            'evaluation' => ['nullable', 'in:complete,incomplete'],
            'decision' => ['nullable', 'in:pending,selected,excluded'],
            'edits' => ['nullable', 'in:yes,no'],
            'hosting' => ['nullable', 'in:yes,no,__missing'],
            'owner_disability' => ['nullable', 'in:yes,no,__missing'],
            'family_chronic' => ['nullable', 'in:yes,no,__missing'],
            'building_id' => ['nullable', 'integer', 'min:1'],
            'building_old_objectid' => ['nullable', 'string', 'max:100'],
            'building_object_id' => ['nullable', 'integer', 'min:1'],
            'mine' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'tab' => ['nullable', \Illuminate\Validation\Rule::in(['overview', 'units', 'buildings', 'reports', 'sync'])],
        ];
        foreach (['score' => 100, 'engineering' => 40, 'social' => 60, 'household' => 1000000] as $prefix => $maximum) {
            $rules[$prefix.'_min'] = ['nullable', 'integer', 'min:0', 'max:'.$maximum];
            $rules[$prefix.'_max'] = ['nullable', 'integer', 'min:0', 'max:'.$maximum, ...($this->filled($prefix.'_min') ? ['gte:'.$prefix.'_min'] : [])];
        }

        $choices = [
            'priority' => [...array_keys(\App\Modules\Reh\Services\RehScoring::PRIORITIES), 'unclassified'],
            'survey_status' => array_keys(\App\Modules\Reh\Services\RehSurvey::FIELD_STATUSES),
            'status' => array_keys(\App\Modules\Reh\Services\RehSurvey::WORKFLOW),
            'governorate' => array_keys(app(\App\Modules\Reh\Services\RehSurvey::class)->dictionary()['choices']['governorate']),
            'neighborhood' => array_keys(app(\App\Modules\Reh\Services\RehSurvey::class)->dictionary()['choices']['neighborhood']),
            'evaluation' => ['complete', 'incomplete'], 'decision' => ['pending', 'selected', 'excluded'],
            'edits' => ['yes', 'no'], 'hosting' => ['yes', 'no', '__missing'], 'owner_disability' => ['yes', 'no', '__missing'], 'family_chronic' => ['yes', 'no', '__missing'],
        ];
        foreach ($choices as $key => $options) {
            $rules = [...$rules, ...RehFilterChoices::rules($this->all(), $key, $options, $key === 'survey_status')];
        }
        if (is_array($this->input('engineer'))) {
            $rules['engineer'] = ['nullable', 'array', 'max:100'];
            $rules['engineer.*'] = ['required', 'string', 'distinct', 'max:100'];
        }

        return [...$rules, ...app(RehSurveyFilters::class)->rules($this->all())];
    }

    public function attributes(): array
    {
        return ['columns' => 'أعمدة التصدير', 'columns.*' => 'عمود التصدير', ...RehAdvancedFilters::LABELS, ...app(RehSurveyFilters::class)->labels()];
    }

    public function messages(): array
    {
        return ['*.prohibited' => 'فلتر :attribute لا ينطبق على الإجابة المختارة. عدّل الإجابة المرتبطة به.', '*.required_with' => 'أكمل اختيار :attribute للفلتر.', '*.numeric' => ':attribute يجب أن يكون رقمًا صالحًا.', '*.gte' => 'قيمة «إلى» يجب أن تكون مساوية أو أكبر من قيمة «من».', '*.integer' => ':attribute يجب أن يكون عددًا صحيحًا.', '*.min' => ':attribute أقل من الحد المسموح.', '*.max' => ':attribute يتجاوز الحد المسموح.', '*.in' => 'قيمة :attribute غير صالحة.', '*.date_format' => 'أدخل تاريخًا صالحًا بصيغة سنة-شهر-يوم.', 'visit_to.after_or_equal' => 'نهاية فترة الزيارة يجب أن تكون بعد بدايتها أو في اليوم نفسه.', 'priority.in' => 'مستوى الأولوية غير صالح.', 'survey_status.in' => 'حالة الاستبيان غير صالحة.', 'q.max' => 'اكتب عبارة بحث لا تتجاوز 100 حرف.', 'status.in' => 'حالة التدقيق غير صالحة.', 'page.integer' => 'رقم الصفحة غير صالح.', 'tab.in' => 'القسم المطلوب غير صالح.', 'building_object_id.integer' => 'معرّف تأهيل المبنى غير صالح.', 'building_object_id.min' => 'معرّف تأهيل المبنى يجب أن يكون موجبًا.'];
    }
}
