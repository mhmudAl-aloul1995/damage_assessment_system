<?php

declare(strict_types=1);

namespace App\Http\Requests\InfAudit;

use Illuminate\Foundation\Http\FormRequest;

class InfAuditBulkAssignRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
