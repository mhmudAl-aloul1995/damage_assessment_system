<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDashboardCardItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $dashboardCard = $this->route('dashboardCard');

        return [
            'key' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('dashboard_card_items', 'key')->where('dashboard_card_id', $dashboardCard?->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'source_bucket' => ['required', 'string', 'max:100'],
            'stat_key' => ['nullable', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:100'],
            'link_group' => ['nullable', 'string', 'max:100'],
            'link_key' => ['nullable', 'string', 'max:100'],
            'calculation_type' => ['required', 'string', Rule::in(['stat_key', 'count_condition'])],
            'source_model' => ['nullable', 'string', 'max:255'],
            'filter_field' => ['nullable', 'string', 'max:100'],
            'filter_operator' => ['nullable', 'string', 'max:20'],
            'filter_value' => ['nullable', 'string', 'max:255'],
            'conditions' => ['nullable', 'array'],
            'conditions.*.field' => ['nullable', 'string', 'max:100'],
            'conditions.*.operator' => ['nullable', 'string', 'max:20'],
            'conditions.*.value' => ['nullable'],
            'conditions.*.value.*' => ['nullable', 'string', 'max:255'],
            'value_suffix' => ['nullable', 'string', 'max:20'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.unique' => 'مفتاح البند مستخدم مسبقاً داخل هذه البطاقة.',
            'title.required' => 'اسم البند مطلوب.',
        ];
    }
}
