<?php

declare(strict_types=1);

namespace App\Http\Requests\DamageAssessment;

use Illuminate\Foundation\Http\FormRequest;

class HudBuildingUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'building_globalid' => ['required', 'string', 'exists:buildings,globalid'],
        ];
    }

    public function messages(): array
    {
        return [
            'building_globalid.required' => 'Please select a building.',
            'building_globalid.exists' => 'The selected building was not found.',
        ];
    }
}
