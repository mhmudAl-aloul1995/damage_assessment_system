<?php

declare(strict_types=1);

namespace App\Http\Requests\Committee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTelegramBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('manage telegram integrations');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'target_type' => ['required', Rule::in(['all', 'scope', 'selected'])],
            'scope_type' => ['nullable', 'string', 'max:50'],
            'destination_ids' => ['nullable', 'array'],
            'destination_ids.*' => ['integer', 'exists:telegram_destinations,id'],
            'context_ids' => ['nullable', 'array'],
            'context_ids.*' => ['integer'],
        ];
    }
}
