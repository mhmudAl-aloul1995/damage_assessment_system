<?php

namespace App\Http\Requests\Audit;

use Illuminate\Foundation\Http\FormRequest;

class StoreBuildingAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'attachment' => ['required', 'file', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'attachment.required' => 'Please select an attachment.',
            'attachment.file' => 'The selected attachment is invalid.',
            'attachment.max' => 'The attachment may not be greater than 20MB.',
        ];
    }
}
