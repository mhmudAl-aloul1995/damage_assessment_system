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
            'kind' => ['nullable', 'string', 'in:all,duplicate,missing'],
            'code_status' => ['nullable', 'string', 'in:all,available,missing'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return ['kind.in' => 'اختر نوع المعرف: الكل أو المكرر أو الفارغ.', 'code_status.in' => 'اختر حالة كود الوحدة: الكل أو متوفر أو غير متوفر.', 'q.max' => 'البحث لا يتجاوز 100 حرف.', 'page.min' => 'رقم الصفحة غير صالح.'];
    }
}
