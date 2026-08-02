<?php

namespace App\Http\Requests\Modules\DamageAssessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditReviewerAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Auditing Supervisor') ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ];
    }
}
