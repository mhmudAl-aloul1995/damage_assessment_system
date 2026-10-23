<?php

namespace App\Modules\HeksShelter\Http\Requests;

use App\Modules\HeksShelter\Services\SurveyDesigner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SurveySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('heks3.settings') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('heks3.settings.save')) {
            $choices = [];
            foreach (preg_split('/\r?\n/', trim((string) $this->input('choices_text', ''))) as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $parts = explode('|', $line, 3);
                $choices[] = ['value' => trim($parts[0]), 'label' => trim($parts[1] ?? $parts[0]), 'exclusive' => trim($parts[2] ?? '') === 'exclusive'];
            }
            $this->merge(['enabled' => $this->boolean('enabled'), 'required' => $this->boolean('required'), 'choices' => $choices]);
        }
    }

    public function rules(): array
    {
        $base = ['revision_id' => ['required', 'integer', 'min:1'], 'edit_version' => ['required', 'integer', 'min:1']];
        if ($this->routeIs('heks3.settings.section')) {
            return [...$base, 'old_section' => ['required', 'string', 'max:255'], 'section' => ['required', 'string', 'max:255'], 'section_order' => ['required', 'integer', 'between:0,1000']];
        }
        if (! $this->routeIs('heks3.settings.save')) {
            return $base;
        }

        return [...$base, 'question_id' => ['nullable', 'integer', 'min:1'], 'key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,47}$/D'],
            'label' => ['required', 'string', 'max:255'], 'type' => ['required', Rule::in(array_keys(SurveyDesigner::TYPES))],
            'section' => ['required', 'string', 'max:255'], 'section_order' => ['required', 'integer', 'between:0,1000'], 'position' => ['required', 'integer', 'between:0,10000'],
            'enabled' => ['required', 'boolean'], 'required' => ['required', 'boolean'], 'parent_key' => ['nullable', 'string', 'regex:/^[a-z][a-z0-9_]{0,47}$/D'],
            'visible_field' => ['nullable', 'string', 'regex:/^[a-z][a-z0-9_]{0,47}$/D'], 'visible_value' => ['nullable', 'required_with:visible_field', 'string', 'max:100'],
            'minimum' => ['nullable', 'numeric', 'between:0,10000000'], 'maximum' => ['nullable', 'numeric', 'between:0,10000000', Rule::when($this->filled('minimum'), 'gte:minimum')],
            'max_length' => ['nullable', 'integer', 'between:1,10000'], 'help' => ['nullable', 'string', 'max:255'], 'error_message' => ['nullable', 'string', 'max:255'],
            'choices' => ['array', 'max:100'], 'choices.*' => ['array:value,label,exclusive'], 'choices.*.value' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,100}$/D', 'distinct'],
            'choices.*.label' => ['required', 'string', 'max:255'], 'choices.*.exclusive' => ['required', 'boolean']];
    }

    public function messages(): array
    {
        return ['required' => 'هذا الحقل مطلوب.', 'regex' => 'استخدم مفتاحاً إنجليزياً ثابتاً دون مسافات أو رموز خاصة.', 'distinct' => 'قيمة الخيار مكررة.', 'max' => 'القيمة أكبر من المسموح.', 'gte' => 'الحد الأعلى يجب ألا يقل عن الحد الأدنى.', 'in' => 'اختر نوعاً مدعوماً.'];
    }
}
