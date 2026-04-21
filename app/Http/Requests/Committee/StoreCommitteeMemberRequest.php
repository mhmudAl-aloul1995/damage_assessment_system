<?php

declare(strict_types=1);

namespace App\Http\Requests\Committee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommitteeMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'is_active' => ['nullable', 'boolean'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'signature_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم عضو اللجنة مطلوب.',
            'user_id.exists' => 'المستخدم المختار غير موجود.',
        ];
    }
}
