<?php

namespace App\Modules\DamageAssessmentBorrowers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBorrowerSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([
            'Field Engineer',
            'Database Officer',
            'Project Officer',
            'Project Officer - Borrowers',
            'Area Manager',
            'Team Leader',
            'Team Leader -INF',
        ]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'surveyed_at' => ['nullable', 'date'],
            'form_number' => ['nullable', 'string', 'max:100'],
            'borrower_name' => ['required', 'string', 'max:255'],
            'borrower_id_number' => ['nullable', 'string', 'max:50'],
            'family_members_count' => ['nullable', 'integer', 'min:0', 'max:100'],
            'marital_status' => ['nullable', 'string', 'max:50'],
            'spouse_name' => ['nullable', 'string', 'max:255'],
            'spouse_id_number' => ['nullable', 'string', 'max:50'],
            'employment_status' => ['nullable', 'string', 'max:50'],
            'is_borrower_alive' => ['required', 'boolean'],
            'vulnerability_types' => ['nullable', 'array'],
            'vulnerability_types.*' => ['string', 'max:80'],
            'guarantors_count' => ['nullable', 'integer', 'min:0', 'max:20'],
            'guarantors_alive_status' => ['nullable', 'string', 'max:50'],
            'deceased_guarantors' => ['nullable', 'array'],
            'deceased_guarantors.*.name' => ['nullable', 'string', 'max:255'],
            'guarantors_employment_statuses' => ['nullable', 'array'],
            'guarantors_employment_statuses.*' => ['string', 'max:80'],
            'affected_guarantors' => ['nullable', 'array'],
            'affected_guarantors.*.name' => ['nullable', 'string', 'max:255'],
            'affected_guarantors.*.status' => ['nullable', 'string', 'max:80'],
            'displacement_status' => ['nullable', 'string', 'max:80'],
            'displaced_to_governorate' => ['nullable', 'string', 'max:80'],
            'current_residence_address' => ['nullable', 'string'],
            'phone_primary' => ['nullable', 'string', 'max:50'],
            'phone_secondary' => ['nullable', 'string', 'max:50'],
            'loan_unit_address' => ['nullable', 'string'],
            'loan_unit_area' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'loan_unit_floor_type' => ['nullable', 'in:ground,repeated'],
            'parcel_number' => ['nullable', 'string', 'max:100'],
            'plot_number' => ['nullable', 'string', 'max:100'],
            'loan_unit_occupancy_status' => ['nullable', 'string', 'max:80'],
            'resident_households' => ['nullable', 'array'],
            'resident_households.*.head_name' => ['nullable', 'string', 'max:255'],
            'resident_households.*.id_number' => ['nullable', 'string', 'max:50'],
            'resident_households.*.members_count' => ['nullable', 'integer', 'min:0', 'max:100'],
            'resident_households.*.phone' => ['nullable', 'string', 'max:50'],
            'resident_households.*.employment_status' => ['nullable', 'string', 'max:80'],
            'loan_unit_damage_status' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'borrower_name.required' => 'اسم المقترض مطلوب.',
            'is_borrower_alive.required' => 'حالة المقترض على قيد الحياة مطلوبة.',
        ];
    }
}
