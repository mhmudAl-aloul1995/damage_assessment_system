<?php

namespace App\Http\Requests\Committee;

use App\Models\CommitteeDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExceptionalCommitteeDecisionMemberUpdateRequest extends FormRequest
{
    private const ALLOWED_ID_NUMBERS = ['801933490', '800846960'];

    public function authorize(): bool
    {
        return auth()->check()
            && in_array(trim((string) auth()->user()->id_no), self::ALLOWED_ID_NUMBERS, true)
            && $this->route('committeeDecision')?->isCompleted();
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
        ];
    }

    public function messages(): array
    {
        return [
            'decision_type.required' => 'نوع القرار مطلوب.',
            'decision_text.required' => 'نص القرار مطلوب.',
            'decision_date.required' => 'تاريخ القرار مطلوب.',
            'committee_members.required' => 'يجب اختيار عضو لجنة واحد على الأقل.',
            'committee_members.min' => 'يجب اختيار عضو لجنة واحد على الأقل.',
        ];
    }
}
