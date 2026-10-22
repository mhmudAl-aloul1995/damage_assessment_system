<?php

namespace App\Modules\Reh\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RehUnitDeletionRequest extends FormRequest
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
        return [
            'destination' => ['required', 'string', 'in:database,arcgis,both'],
            'revision' => ['required', 'string', 'size:64'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'confirm' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return ['destination.required' => 'حدد مكان الحذف.', 'destination.in' => 'وجهة الحذف غير صالحة.',
            'revision.required' => 'حدّث الصفحة قبل الحذف.', 'revision.size' => 'حدّث الصفحة قبل الحذف.',
            'reason.required' => 'سبب الحذف إلزامي.', 'reason.min' => 'اكتب سببًا واضحًا من ثلاثة أحرف على الأقل.', 'reason.max' => 'سبب الحذف لا يتجاوز 2000 حرف.',
            'confirm.required' => 'أكد مراجعة الوحدة ووجهة الحذف.', 'confirm.accepted' => 'أكد مراجعة الوحدة ووجهة الحذف.'];
    }
}
