<?php

namespace App\Modules\Heks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHeksBoqCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasRole('Database Officer') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'section' => ['nullable', 'string', 'max:255'],
            'item_code' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'unit' => ['nullable', 'string', 'max:255'],
            'unit_price_ils' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
