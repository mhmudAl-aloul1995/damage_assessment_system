<?php

namespace App\Http\Requests;

use App\Models\Assessment;
use App\Models\Filter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
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

            $field = (string) $this->input('field');

            if (! $this->fieldBelongsToRequestedType((string) $this->input('type'), $field)) {
                $validator->errors()->add('field', 'الحقل المختار لا يتبع نوع السجلات المحدد.');

                return;
            }

            $filterValues = Filter::query()
                ->where('list_name', $field)
                ->pluck('name')
                ->filter()
                ->values();

            if ($filterValues->isEmpty()) {
                return;
            }

            $value = (string) $this->input('value');

            if ($value === '' || ! $filterValues->contains($value)) {
                $validator->errors()->add('value', 'القيمة المختارة غير موجودة ضمن خيارات هذا الحقل في الاستبيان.');
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

    private function fieldBelongsToRequestedType(string $type, string $field): bool
    {
        if ($field === '' || $field === 'attachments') {
            return false;
        }

        $assessmentRows = Assessment::query()
            ->orderBy('id')
            ->get(['id', 'name']);
        $assessment = $assessmentRows->firstWhere('name', $field);

        if (! $assessment) {
            return false;
        }

        $housingStartId = $assessmentRows->firstWhere('name', 'housing_unit_group')?->id;
        $buildingEndId = $assessmentRows->firstWhere('name', 'housing_unit')?->id
            ?? $housingStartId;

        if ($type === 'housing_table') {
            return $housingStartId !== null
                && $assessment->id >= $housingStartId
                && Schema::hasColumn('housing_units', $field);
        }

        return ($buildingEndId === null || $assessment->id < $buildingEndId)
            && Schema::hasColumn('buildings', $field);
    }
}
