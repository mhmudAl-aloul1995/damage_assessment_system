<?php

namespace App\Modules\Reh\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RehReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $permission = match ($this->input('action')) {
            'engineering_approve', 'quantities', 'second_assessment' => 'reh.review.engineering',
            'social_approve' => 'reh.review.social',
            'select', 'exclude', 'reopen' => 'reh.decide',
            default => 'reh.review',
        };

        return ($this->user()?->can('reh.view') && $this->user()?->can($permission))
            && ($this->input('action') !== 'social_approve' || $this->user()?->can('reh.sensitive'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', \Illuminate\Validation\Rule::in(['claim', 'engineering_approve', 'social_approve', 'return', 'second_assessment', 'select', 'exclude', 'reopen', 'quantities'])],
            'version' => ['required', 'integer', 'min:1'],
            'note' => ['required', 'string', 'min:3', 'max:2000'],
            'quantities' => ['required_if:action,quantities', 'array', 'max:75'],
            'quantities.*' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return ['action.required' => 'حدد الإجراء المطلوب.', 'action.in' => 'الإجراء غير متاح.', 'version.required' => 'أعد فتح الملف للحصول على أحدث نسخة.', 'note.required' => 'اكتب ملاحظة توضح سبب الإجراء.', 'note.min' => 'اكتب ملاحظة واضحة من ثلاثة أحرف على الأقل.', 'note.max' => 'الملاحظة لا تتجاوز 2000 حرف.', 'quantities.required_if' => 'أدخل الكميات المطلوب مراجعتها.', 'quantities.*.numeric' => 'الكمية يجب أن تكون رقمًا.', 'quantities.*.min' => 'لا يمكن أن تكون الكمية سالبة.', 'quantities.*.max' => 'الكمية تتجاوز الحد المسموح.'];
    }
}
