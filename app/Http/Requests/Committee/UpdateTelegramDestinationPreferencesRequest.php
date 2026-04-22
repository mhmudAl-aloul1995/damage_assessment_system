<?php

declare(strict_types=1);

namespace App\Http\Requests\Committee;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTelegramDestinationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('manage telegram integrations');
    }

    public function rules(): array
    {
        return [
            'notify_new_records' => ['nullable', 'boolean'],
            'notify_errors' => ['nullable', 'boolean'],
            'notify_status_changes' => ['nullable', 'boolean'],
            'notify_reports' => ['nullable', 'boolean'],
            'notify_broadcasts' => ['nullable', 'boolean'],
        ];
    }
}
