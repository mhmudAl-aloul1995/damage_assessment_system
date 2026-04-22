<?php

declare(strict_types=1);

namespace App\Http\Requests\Committee;

use App\Models\TelegramDestination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTelegramDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('manage telegram integrations');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([TelegramDestination::TYPE_USER, TelegramDestination::TYPE_GROUP])],
            'scope_type' => ['required', 'string', 'max:50'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'context_id' => ['nullable', 'integer'],
        ];
    }
}
