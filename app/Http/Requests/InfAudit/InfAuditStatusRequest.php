<?php

declare(strict_types=1);

namespace App\Http\Requests\InfAudit;

use Illuminate\Foundation\Http\FormRequest;

class InfAuditStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'exists:inf_audit_statuses,name'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
