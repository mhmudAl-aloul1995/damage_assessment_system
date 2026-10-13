<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadDocument', $this->route('employee')) ?? false;
    }

    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:255'], 'category' => ['required', \Illuminate\Validation\Rule::in(['employment', 'qualification', 'identity', 'medical', 'other'])],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:5120'],
            'previous_document_id' => ['nullable', 'integer', 'exists:hr_employee_documents,id'], 'expires_on' => ['nullable', 'date_format:Y-m-d']];
    }

    public function messages(): array
    {
        return ['file.mimes' => 'المستند يجب أن يكون PDF أو صورة JPEG/PNG.', 'file.max' => 'الحد الأقصى 5 ميغابايت.', 'title.required' => 'عنوان المستند مطلوب.'];
    }
}
