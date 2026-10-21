<?php

namespace App\Modules\Reh\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RehPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $approval = in_array($this->route()->getName(), ['reh.pricing.approve', 'reh.pricing.estimate.approve'], true);

        return $this->user()?->can('reh.view') && $this->user()?->can($approval ? 'reh.pricing.approve' : 'reh.pricing.manage');
    }

    public function rules(): array
    {
        return match ($this->route()->getName()) {
            'reh.pricing.preview' => ['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']],
            'reh.pricing.store' => ['token' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:160'], 'exchange_rate' => ['required', 'numeric', 'min:0.000001', 'max:100000'], 'effective_date' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'min:3', 'max:2000']],
            'reh.pricing.item.update' => ['revision' => ['required', 'integer', 'min:1'], 'availability' => ['required', Rule::in(['available', 'unavailable'])],
                'reason' => ['required', 'string', 'min:3', 'max:2000'], 'resolve_issue' => ['sometimes', 'boolean'],
                'components' => ['present', 'array', 'max:100'], 'components.*.id' => ['required', 'integer', 'distinct'],
                'components.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:1000000'], 'components.*.rate' => ['nullable', 'numeric', 'min:0', 'max:1000000']],
            'reh.pricing.approve' => ['revision' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:3', 'max:2000']],
            'reh.pricing.book.update' => ['revision' => ['required', 'integer', 'min:1'], 'name' => ['required', 'string', 'max:160'], 'exchange_rate' => ['required', 'numeric', 'min:0.000001', 'max:100000'], 'effective_date' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'min:3', 'max:2000']],
            'reh.pricing.estimate.approve' => ['book_id' => ['required', 'integer', 'exists:reh_price_books,id'], 'fingerprint' => ['required', 'string', 'size:64'], 'reason' => ['required', 'string', 'min:3', 'max:2000']],
            default => ['reason' => ['required', 'string', 'min:3', 'max:2000']],
        };
    }

    public function messages(): array
    {
        return ['required' => 'الحقل :attribute مطلوب.', 'reason.min' => 'اكتب سببًا واضحًا للإجراء.', 'file.mimes' => 'اختر ملف Excel بصيغة xlsx.', 'file.max' => 'حجم الملف لا يتجاوز 5 ميجابايت.',
            'numeric' => 'أدخل قيمة رقمية صحيحة.', 'min' => 'القيمة أقل من الحد المسموح.', 'max' => 'القيمة تتجاوز الحد المسموح.', 'components.*.id.distinct' => 'المكون مكرر.', 'exchange_rate.min' => 'سعر الصرف يجب أن يكون أكبر من صفر.'];
    }
}
