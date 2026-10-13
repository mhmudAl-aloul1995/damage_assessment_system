<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.devices.register') ?? false;
    }

    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'uuid'], 'device_uuid' => ['required', 'uuid'],
            'label' => ['required', 'string', 'max:100'], 'public_key' => ['required', 'string', 'max:4096'],
            'signature' => ['required', 'string', 'max:256'],
            'attestation_chain' => ['sometimes', 'array', 'max:5'], 'attestation_chain.*' => ['required', 'string', 'max:8192'],
        ];
    }

    public function messages(): array
    {
        return ['challenge_id.required' => 'تحدي التسجيل مطلوب.', 'public_key.required' => 'المفتاح العام مطلوب.', 'signature.required' => 'إثبات التوقيع مطلوب.'];
    }
}
