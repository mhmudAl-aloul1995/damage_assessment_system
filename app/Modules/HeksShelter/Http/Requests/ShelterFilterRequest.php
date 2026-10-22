<?php

namespace App\Modules\HeksShelter\Http\Requests;

use App\Modules\HeksShelter\Services\ShelterSurvey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShelterFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('heks3.view') ?? false;
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(array_keys(ShelterSurvey::STATUSES))], 'governorate' => ['nullable', Rule::in(array_keys(app(ShelterSurvey::class)->fields()['governorate']['choices']))], 'engineer' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1'], 'tab' => ['nullable', Rule::in(['overview', 'cases', 'map', 'budget', 'payments', 'visits', 'reports', 'mobile'])]];
    }

    public function messages(): array
    {
        return ['in' => 'اختر قيمة صالحة للفلترة.', 'search.max' => 'نص البحث طويل.', 'integer' => 'اختر رقماً صالحاً.'];
    }
}
