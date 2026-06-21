<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHousingLegalChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && ! auth()->user()->hasAnyRole(['QC/QA Engineer', 'Engineering Auditor']);
    }

    public function rules(): array
    {
        return [
            'globalid' => ['required_without:globalids', 'string', 'exists:housing_units,globalid'],
            'globalids' => ['required_without:globalid', 'array', 'min:1'],
            'globalids.*' => ['required', 'string', 'distinct', 'exists:housing_units,globalid'],
            'legal_challenge' => ['required', 'string', Rule::in($this->legalChallengeValues())],
        ];
    }

    public function messages(): array
    {
        return [
            'globalid.required' => 'Please select a housing unit.',
            'globalids.required_without' => 'Please select at least one housing unit.',
            'globalids.min' => 'Please select at least one housing unit.',
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
