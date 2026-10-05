<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkApproveBuildingDeletionRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Gis Officer', 'Database Officer']) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'request_ids' => ['required', 'array', 'min:1'],
            'request_ids.*' => ['integer', 'distinct', 'exists:building_deletion_requests,id'],
        ];
    }
}
