<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MobileFormSubmitRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        abort_if(strlen($this->getContent()) > 2 * 1024 * 1024, 413, 'حجم إجابات الاستبيان أكبر من الحد المسموح.');
    }

    public function authorize(): bool
    {
        return $this->user()?->tokenCan('mobile:submit') ?? false;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'submission_uuid' => ['required', 'uuid'], 'form_version' => ['required', 'string', 'size:64'],
            'record_id' => ['nullable', 'integer', 'min:1'], 'record_version' => ['nullable', 'string', 'size:64'],
            'answers' => ['required', 'array', 'max:500'],
            'attachments' => ['sometimes', 'array', 'max:5'], 'attachments.*' => ['integer', 'min:1', 'distinct'],
            'location' => ['nullable', 'array:latitude,longitude,accuracy', 'required_array_keys:latitude,longitude'],
            'location.latitude' => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.longitude' => ['required_with:location', 'numeric', 'between:-180,180'],
            'location.accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['submission_uuid.uuid' => 'معرّف الإرسال غير صالح.', 'form_version.size' => 'إصدار النموذج غير صالح.', 'answers.required' => 'أدخل إجابات النموذج.', 'attachments.max' => 'الحد الأقصى خمسة مرفقات.', 'location.latitude.between' => 'إحداثيات الموقع غير صالحة.', 'location.longitude.between' => 'إحداثيات الموقع غير صالحة.'];
    }
}
