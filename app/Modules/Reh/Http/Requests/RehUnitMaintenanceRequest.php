<?php

namespace App\Modules\Reh\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RehUnitMaintenanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Database Officer') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['nullable', 'string', 'in:all,duplicate,available,missing'],
            'code_status' => ['nullable', 'string', 'in:all,available,missing'],
            'creator' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'history_page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return ['kind.in' => 'اختر حالة معرّف الحصر: الكل أو متوفر أو غير متوفر أو المكرر فقط.', 'code_status.in' => 'اختر حالة كود الوحدة: الكل أو متوفر أو غير متوفر.', 'creator.max' => 'اسم منشئ السجل لا يتجاوز 255 حرفًا.', 'per_page.in' => 'اختر عدد الصفوف: 25 أو 50 أو 100.', 'q.max' => 'البحث لا يتجاوز 100 حرف.', 'page.min' => 'رقم الصفحة غير صالح.'];
    }
}
