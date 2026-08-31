<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBuildingDeletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'building_globalid' => ['required', 'string', Rule::exists('buildings', 'globalid')],
            'reason' => ['required', 'string', 'min:10'],
            'notes' => ['nullable', 'string'],
            'signature' => ['required', 'string'],
            'confirmation' => ['accepted'],
        ];
    }
}
