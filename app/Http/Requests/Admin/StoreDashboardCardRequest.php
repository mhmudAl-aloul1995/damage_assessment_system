<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDashboardCardRequest extends FormRequest
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
        return [
            'key' => ['required', 'string', 'max:100', Rule::unique('dashboard_cards', 'key')],
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'source_bucket' => ['required', 'string', 'max:100'],
            'total_stat_key' => ['required', 'string', 'max:100'],
            'icon' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.required' => 'مفتاح البطاقة مطلوب.',
            'key.unique' => 'مفتاح البطاقة مستخدم مسبقاً.',
            'title.required' => 'اسم البطاقة مطلوب.',
        ];
    }
}
