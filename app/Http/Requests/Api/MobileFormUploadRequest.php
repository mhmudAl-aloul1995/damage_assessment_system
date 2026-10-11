<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MobileFormUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tokenCan('mobile:submit') ?? false;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return ['submission_uuid' => ['required', 'uuid'], 'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:15360']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['file.mimes' => 'المرفقات المسموحة صور JPEG وPNG وملفات PDF.', 'file.max' => 'الحد الأقصى للمرفق 15 ميغابايت.', 'file.required' => 'اختر المرفق.'];
    }
}
