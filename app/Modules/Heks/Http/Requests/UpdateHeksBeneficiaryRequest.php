<?php

namespace App\Modules\Heks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHeksBeneficiaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'identity_number' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'alternate_phone' => ['nullable', 'string', 'max:255'],
            'field_engineer' => ['nullable', 'string', 'max:255'],
            'visit_date' => ['nullable', 'date'],
            'governorate' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'displacement_status' => ['nullable', 'string', 'max:255'],
            'occupancy_status' => ['nullable', 'string', 'max:255'],
            'damage_status' => ['nullable', 'string', 'max:255'],
            'grant_amount' => ['nullable', 'numeric'],
            'payment_1' => ['nullable', 'numeric'],
            'payment_2' => ['nullable', 'numeric'],
            'payment_3' => ['nullable', 'numeric'],
            'is_selected' => ['nullable', 'boolean'],
            'selection_status' => ['nullable', 'string', 'max:255'],
            'payment_status' => ['nullable', 'string', 'max:255'],
            'social_notes' => ['nullable', 'string'],
            'engineer_notes' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
        ];
    }
}
