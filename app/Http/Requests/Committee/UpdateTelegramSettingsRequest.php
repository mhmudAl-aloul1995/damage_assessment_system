<?php

declare(strict_types=1);

namespace App\Http\Requests\Committee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTelegramSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('manage telegram integrations');
    }

    public function rules(): array
    {
        return [
            'bot_token' => ['nullable', 'string'],
            'bot_username' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['nullable', 'boolean'],
            'parse_mode' => ['required', Rule::in(['HTML', 'Markdown'])],
        ];
    }
}
