<?php

declare(strict_types=1);

namespace App\Http\Requests\Committee;

use App\Models\TelegramIntegration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTelegramIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('manage telegram integrations');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([TelegramIntegration::TYPE_USER, TelegramIntegration::TYPE_GROUP])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم التكامل مطلوب.',
            'type.required' => 'نوع التكامل مطلوب.',
            'type.in' => 'نوع التكامل غير صالح.',
            'user_id.exists' => 'المستخدم المحدد غير موجود.',
        ];
    }
}
