<?php

namespace App\Modules\Reh\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RehFieldAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reh.view') && $this->user()?->canAny(['reh.review.engineering', 'reh.review.social']);
    }

    public function rules(): array
    {
        return [
            'field' => ['required', 'string', 'max:150'],
            'value' => ['required', 'string', 'max:10000'],
            'revision' => ['required', 'string', 'size:64'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'field.required' => 'اختر الحقل المطلوب تدقيقه.',
            'value.required' => 'أدخل القيمة الصحيحة؛ لا يمكن حذف إجابة أثناء التدقيق.',
            'value.string' => 'صيغة القيمة غير صحيحة.',
            'value.max' => 'القيمة أطول من الحد المسموح.',
            'revision.required' => 'أعد فتح الملف للحصول على آخر نسخة.',
            'revision.size' => 'أعد فتح الملف للحصول على آخر نسخة.',
            'reason.required' => 'اكتب سبب التصحيح ليُحفظ في سجل التدقيق.',
            'reason.min' => 'اكتب سببًا واضحًا من ثلاثة أحرف على الأقل.',
            'reason.max' => 'السبب لا يتجاوز 2000 حرف.',
        ];
    }
}
