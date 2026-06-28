<?php

declare(strict_types=1);

namespace App\Http\Requests\Committee;

use App\Models\CommitteeDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCommitteeDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'decision_type' => ['required', 'string', Rule::in([
                CommitteeDecision::TYPE_FULLY_DAMAGED,
                CommitteeDecision::TYPE_PARTIALLY_DAMAGED,
                CommitteeDecision::TYPE_HIGHER_COMMITTEE,
            ])],
            'decision_text' => ['required', 'string'],
            'action_text' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'decision_date' => ['required', 'date'],
            'committee_members' => ['required', 'array', 'min:1'],
            'committee_members.*' => ['integer', Rule::exists('committee_members', 'id')],
            'status' => ['nullable', 'string', Rule::in([
                'draft',
                'pending_signatures',
                'approved',
                'rejected',
                'completed',
            ])],
        ];
    }

    public function messages(): array
    {
        return [
            'decision_type.required' => 'نوع القرار مطلوب.',
            'decision_text.required' => 'نص القرار مطلوب.',
            'decision_date.required' => 'تاريخ القرار مطلوب.',
        ];
    }
}
