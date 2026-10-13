<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArchiveEmployeeProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageProfile', $this->route('employee')) ?? false;
    }

    public function rules(): array
    {
        return ['archive' => ['required', 'boolean']];
    }

    public function messages(): array
    {
        return ['archive.required' => 'حدد الأرشفة أو الاستعادة.'];
    }
}
