<?php

namespace App\Http\Requests\Modules\DamageAssessmentBorrowers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBorrowerPricingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([
            'Database Officer',
            'Project Officer',
            'Area Manager',
            'Team Leader',
            'Team Leader -INF',
            'Auditing Supervisor',
        ]) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['nullable', 'array'],
            'items.*.catalog_item_id' => ['nullable', 'integer', 'exists:damage_assessment_borrower_boq_catalog_items,id'],
            'items.*.source_column' => ['required', 'string'],
            'items.*.source_key' => ['nullable', 'string', 'max:40'],
            'items.*.item_code' => ['nullable', 'string', 'max:100'],
            'items.*.description' => ['required', 'string'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
