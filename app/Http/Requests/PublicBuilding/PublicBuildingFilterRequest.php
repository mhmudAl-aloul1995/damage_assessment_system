<?php

declare(strict_types=1);

namespace App\Http\Requests\PublicBuilding;

use Illuminate\Foundation\Http\FormRequest;

class PublicBuildingFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'municipalitie' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'assigned_to' => ['nullable', 'string', 'max:255'],
            'filters' => ['nullable', 'array'],
            'filters.*' => ['nullable', 'string', 'max:255'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'damaged_only' => ['nullable', 'boolean'],
            'with_units' => ['nullable', 'boolean'],
            'has_municipality' => ['nullable', 'boolean'],
            'has_neighborhood' => ['nullable', 'boolean'],
            'has_assigned_to' => ['nullable', 'boolean'],
            'occupied_only' => ['nullable', 'boolean'],
            'bodies_only' => ['nullable', 'boolean'],
            'uxo_only' => ['nullable', 'boolean'],
            'search' => ['nullable'],
            'search.value' => ['nullable', 'string', 'max:255'],
            'draw' => ['nullable'],
            'start' => ['nullable'],
            'length' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_date.after_or_equal' => 'The end date must be after or equal to the start date.',
        ];
    }
}
