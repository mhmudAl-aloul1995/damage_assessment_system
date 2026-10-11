<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MobilePortalQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:150'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000'], 'record' => ['nullable', 'integer', 'min:1']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['search.string' => 'أدخل نصًا للبحث.', 'search.max' => 'الحد الأقصى للبحث 150 حرفًا.', 'page.min' => 'رقم الصفحة غير صالح.', 'record.integer' => 'رقم السجل غير صالح.'];
    }
}
