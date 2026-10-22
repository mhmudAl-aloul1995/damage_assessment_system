<?php

namespace App\Modules\HeksShelter\Http\Requests;

use App\Modules\HeksShelter\Services\ShelterSurvey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShelterSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('heks3.submit') ?? false;
    }

    public function rules(): array
    {
        $rules = [];
        foreach (app(ShelterSurvey::class)->fields() as $key => $field) {
            $rules[$key] = [$field['required'] ? 'required' : 'nullable'];
            array_push($rules[$key], ...match ($field['type']) {
                'integer' => ['integer', 'min:0', 'max:1000'],
                'decimal' => ['numeric', 'min:0', 'max:10000000'],
                'date' => ['date_format:Y-m-d', 'before_or_equal:today'],
                'multiselect' => ['array', 'max:20'],
                default => ['string', 'max:'.($field['type'] === 'multiline' ? 5000 : 255)],
            });
            if ($field['choices'] !== []) {
                $choiceKey = $field['type'] === 'multiselect' ? $key.'.*' : $key;
                $rules[$choiceKey][] = Rule::in(array_keys($field['choices']));
                if ($field['type'] === 'multiselect') {
                    $rules[$choiceKey][] = 'distinct';
                }
            }
        }
        $rules['identity_number'] = ['required', 'string', 'regex:/^[0-9٠-٩۰-۹]{9}$/u'];
        $rules['spouse_identity'] = ['nullable', 'string', 'regex:/^[0-9٠-٩۰-۹]{9}$/u'];
        $rules['host_identity'] = ['nullable', 'string', 'regex:/^[0-9٠-٩۰-۹]{9}$/u'];
        $rules['phone'] = ['required', 'string', 'max:30'];

        return $rules;
    }

    public function messages(): array
    {
        return ['required' => 'هذا الحقل مطلوب.', 'identity_number.regex' => 'أدخل رقم هوية من تسعة أرقام.', 'in' => 'اختر قيمة من الخيارات المتاحة.', 'date_format' => 'أدخل تاريخاً صالحاً.', 'before_or_equal' => 'لا يمكن أن يكون تاريخ الزيارة مستقبلياً.'];
    }
}
