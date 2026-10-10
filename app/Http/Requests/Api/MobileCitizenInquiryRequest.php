<?php

namespace App\Http\Requests\Api;

use App\services\MobileDamageDetailService;
use App\Support\Navigation\SectorNavigation;
use Illuminate\Foundation\Http\FormRequest;

class MobileCitizenInquiryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('search'))) {
            $this->merge(['search' => preg_replace('/^\s+|\s+$/u', '', $this->input('search'))]);
        }
    }

    public function authorize(MobileDamageDetailService $details): bool
    {
        return $this->user() !== null && collect(['buildings', 'housing-units'])->contains(function (string $sector) use ($details): bool {
            $tabs = collect(SectorNavigation::forUser($sector, $this->user()))->pluck('key');

            return $tabs->contains('overview') && $details->canAudit($this->user(), $sector);
        });
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $searchRules = ['required', 'string', 'min:2', 'max:150'];
        if (is_string($this->input('search')) && preg_match('/^\d+$/D', $this->input('search'))) {
            $searchRules[] = 'digits:9';
        }

        return ['search' => $searchRules, 'page' => ['nullable', 'integer', 'min:1']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['search.required' => 'أدخل اسم المواطن أو رقم هويته.', 'search.min' => 'أدخل حرفين على الأقل.', 'search.max' => 'نص البحث طويل جدًا.', 'search.string' => 'أدخل نصًا للبحث.', 'search.digits' => 'رقم الهوية يجب أن يتكون من 9 أرقام.', 'page.min' => 'رقم الصفحة غير صالح.'];
    }
}
