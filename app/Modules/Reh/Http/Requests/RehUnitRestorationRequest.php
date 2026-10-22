<?php

namespace App\Modules\Reh\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RehUnitRestorationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Database Officer') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:2000'], 'confirm' => ['required', 'accepted']];
    }

    public function messages(): array
    {
        return ['reason.required' => 'سبب الاسترجاع إلزامي.', 'reason.min' => 'اكتب سببًا واضحًا للاسترجاع.', 'reason.max' => 'السبب لا يتجاوز 2000 حرف.', 'confirm.required' => 'أكد مراجعة النسخة قبل الاسترجاع.', 'confirm.accepted' => 'أكد مراجعة النسخة قبل الاسترجاع.'];
    }
}
