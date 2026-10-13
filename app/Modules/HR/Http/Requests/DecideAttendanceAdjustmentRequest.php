<?php

namespace App\Modules\HR\Http\Requests;

use App\Modules\HR\Models\AttendanceAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideAttendanceAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $adjustment = $this->route('adjustment');

        return $adjustment instanceof AttendanceAdjustment && ($this->user()?->can('decide', $adjustment) ?? false);
    }

    public function rules(): array
    {
        return ['decision' => ['required', Rule::in(['approved', 'rejected'])], 'note' => ['required', 'string', 'min:5', 'max:2000']];
    }

    public function messages(): array
    {
        return ['decision.in' => 'اختر موافقة أو رفضاً.', 'note.required' => 'ملاحظة المراجع مطلوبة.'];
    }
}
