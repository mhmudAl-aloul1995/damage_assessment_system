<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BulkAssessmentInlineEditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:building_table,housing_table'],
            'objectids_text' => ['required', 'string'],
            'field' => ['required', 'string'],
            'value' => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'يرجى اختيار نوع السجلات.',
            'type.in' => 'نوع السجلات غير صحيح.',
            'objectids_text.required' => 'يرجى لصق ObjectID واحد على الأقل.',
            'field.required' => 'يرجى اختيار الحقل المراد تعديله.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->objectIds() === []) {
                $validator->errors()->add('objectids_text', 'يرجى إدخال ObjectID صحيح واحد على الأقل.');
            }
        });
    }

    /**
     * @return list<int>
     */
    public function objectIds(): array
    {
        $parts = preg_split('/[\s,;،]+/u', (string) $this->input('objectids_text'), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return collect($parts)
            ->map(fn (string $value): string => trim($value))
            ->filter(fn (string $value): bool => preg_match('/^\d+$/', $value) === 1)
            ->map(fn (string $value): int => (int) $value)
            ->unique()
            ->values()
            ->all();
    }
}
