<?php

namespace App\Modules\Reh\Http\Requests;

class RehBulkDeletionRequest extends RehUnitDeletionRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [...array_diff_key(parent::rules(), ['revision' => true]), 'selection_token' => ['required', 'uuid']];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'selection_token.required' => 'أعد اختيار الوحدات قبل الحذف الجماعي.', 'selection_token.uuid' => 'انتهت صلاحية التحديد. أعد اختياره.'];
    }
}
