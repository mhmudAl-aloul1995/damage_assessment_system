<?php

namespace App\Http\Requests\Committee;

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
            'committee_members' => ['required', 'array', 'min:1'],
            'committee_members.*' => ['integer', Rule::exists('committee_members', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'committee_members.required' => 'يجب اختيار عضو لجنة واحد على الأقل.',
            'committee_members.min' => 'يجب اختيار عضو لجنة واحد على الأقل.',
        ];
    }
}
