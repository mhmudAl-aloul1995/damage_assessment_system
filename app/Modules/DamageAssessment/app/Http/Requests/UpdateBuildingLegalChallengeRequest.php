<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBuildingLegalChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && ! auth()->user()->hasAnyRole(['QC/QA Engineer', 'Engineering Auditor']);
    }

    public function rules(): array
    {
        return [
            'building_ids' => ['required', 'array', 'min:1'],
            'building_ids.*' => ['required', 'integer', 'exists:buildings,objectid'],
            'legal_challenge' => ['required', 'string', Rule::in($this->legalChallengeValues())],
        ];
    }

    public function messages(): array
    {
        return [
            'building_ids.required' => 'Please select at least one building.',
            'legal_challenge.required' => 'Please select a legal challenge.',
            'legal_challenge.in' => 'The selected legal challenge is invalid.',
        ];
    }

    /**
     * @return list<string>
     */
    private function legalChallengeValues(): array
    {
        return [
            'missing_legal_documents',
            'broken_ownership_chain',
            'missing_inheritance_documents',
            'government_property_usufruct',
            'unregistered_government_land',
            'camp_land_usufruct',
            'free_housing_with_father',
            'unregistered_real_estate',
            'disputes_with_parties',
            'amicable_partition_deed',
            'utility_bill',
            'other',
        ];
    }
}
