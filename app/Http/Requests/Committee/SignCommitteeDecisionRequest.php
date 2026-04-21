<?php

declare(strict_types=1);

namespace App\Http\Requests\Committee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SignCommitteeDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'committee_member_id' => ['required', 'integer', Rule::exists('committee_members', 'id')],
            'status' => ['required', 'string', Rule::in(['approved', 'rejected', 'pending'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
