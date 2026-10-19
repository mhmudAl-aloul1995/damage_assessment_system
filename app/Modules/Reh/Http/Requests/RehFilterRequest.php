<?php

namespace App\Modules\Reh\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RehFilterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
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
            && (! $this->filled('priority') || $this->user()->can('reh.scores.view'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', \Illuminate\Validation\Rule::in([...array_keys(\App\Modules\Reh\Services\RehScoring::PRIORITIES), 'unclassified'])],
            'survey_status' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Modules\Reh\Services\RehSurvey::FIELD_STATUSES))],
            'status' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Modules\Reh\Services\RehSurvey::WORKFLOW))],
            'governorate' => ['nullable', 'string', 'max:100'],
            'engineer' => ['nullable', 'string', 'max:100'],
            'building_id' => ['nullable', 'integer', 'min:1'],
            'building_old_objectid' => ['nullable', 'string', 'max:100'],
            'building_object_id' => ['nullable', 'integer', 'min:1'],
            'mine' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'tab' => ['nullable', \Illuminate\Validation\Rule::in(['overview', 'units', 'buildings', 'reports', 'sync'])],
        ];
    }

    public function messages(): array
    {
        return ['priority.in' => 'مستوى الأولوية غير صالح.', 'survey_status.in' => 'حالة الاستبيان غير صالحة.', 'q.max' => 'اكتب عبارة بحث لا تتجاوز 100 حرف.', 'status.in' => 'حالة التدقيق غير صالحة.', 'page.integer' => 'رقم الصفحة غير صالح.', 'tab.in' => 'القسم المطلوب غير صالح.', 'building_object_id.integer' => 'رقم OBJECTID للمبنى غير صالح.', 'building_object_id.min' => 'رقم OBJECTID للمبنى يجب أن يكون موجبًا.'];
    }
}
