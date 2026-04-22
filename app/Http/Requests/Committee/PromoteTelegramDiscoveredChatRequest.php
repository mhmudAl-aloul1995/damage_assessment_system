<?php

declare(strict_types=1);

namespace App\Http\Requests\Committee;

use Illuminate\Foundation\Http\FormRequest;

class PromoteTelegramDiscoveredChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('manage telegram integrations');
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'scope_type' => ['required', 'string', 'max:50'],
            'context_id' => ['nullable', 'integer'],
        ];
    }
}
