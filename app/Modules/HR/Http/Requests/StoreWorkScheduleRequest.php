<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.attendance.setup') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'timezone'],
            'working_days' => ['required', 'array', 'min:1', 'max:7'],
            'working_days.*' => ['required', 'integer', 'between:1,7', 'distinct'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'different:starts_at'],
            'grace_minutes' => ['required', 'integer', 'between:0,120'],
            'break_minutes' => ['required', 'integer', 'between:0,720'],
            'flexible' => ['sometimes', 'boolean'],
            'latest_start_at' => ['nullable', Rule::requiredIf($this->boolean('flexible')), Rule::prohibitedIf(! $this->boolean('flexible')), 'date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            [$startHour, $startMinute] = array_map('intval', explode(':', $this->input('starts_at')));
            [$endHour, $endMinute] = array_map('intval', explode(':', $this->input('ends_at')));
            $duration = (($endHour * 60 + $endMinute) - ($startHour * 60 + $startMinute) + 1440) % 1440;
            if ((int) $this->input('break_minutes') >= $duration) {
                $validator->errors()->add('break_minutes', 'الاستراحة يجب أن تكون أقصر من الوردية.');
            }
            if ($this->boolean('flexible')) {
                [$latestHour, $latestMinute] = array_map('intval', explode(':', $this->input('latest_start_at')));
                $arrivalWindow = (($latestHour * 60 + $latestMinute) - ($startHour * 60 + $startMinute) + 1440) % 1440;
                if ($arrivalWindow >= $duration) {
                    $validator->errors()->add('latest_start_at', 'آخر وقت للحضور يجب أن يقع داخل نافذة الوردية.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الجدول مطلوب.',
            'timezone.timezone' => 'المنطقة الزمنية غير صالحة.',
            'working_days.required' => 'حدد أيام العمل.',
            'working_days.*.distinct' => 'لا يمكن تكرار يوم العمل.',
            'ends_at.different' => 'يجب أن يختلف وقت الانتهاء عن البداية.',
            'starts_at.date_format' => 'صيغة وقت البداية غير صحيحة.',
            'ends_at.date_format' => 'صيغة وقت النهاية غير صحيحة.',
        ];
    }
}
