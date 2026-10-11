<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MobileFormDiscardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tokenCan('mobile:submit') ?? false;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return ['submission_uuid' => ['required', 'uuid']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['submission_uuid.uuid' => 'معرّف المسودة غير صالح.', 'submission_uuid.required' => 'معرّف المسودة مطلوب.'];
    }
}
