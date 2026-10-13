<?php
namespace App\Modules\HR\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreContractTypeRequest extends FormRequest {
    public function authorize(): bool { return $this->user()?->is_active && $this->user()?->can('hr.contracts.configure'); }
    public function rules(): array { return ['code'=>['required','string','max:50',Rule::unique('hr_contract_types','code')->ignore($this->route('type'))],'name'=>['required','string','max:255'],'name_en'=>['nullable','string','max:255'],'active'=>['required','boolean'],'requires_end'=>['required','boolean'],'reminder_days'=>['present','array','max:12'],'reminder_days.*'=>['integer','min:0','max:365','distinct']]; }
    protected function prepareForValidation(): void { if (is_string($this->input('reminder_days'))) { $value=trim($this->input('reminder_days')); $this->merge(['reminder_days'=>$value===''?[]:array_map('trim',explode(',',$value))]); } }
    public function messages(): array { return ['code.unique'=>'رمز نوع العقد مستخدم.','reminder_days.*.integer'=>'أدخل أيام التنبيه كأعداد مفصولة بفواصل.','reminder_days.*.max'=>'الحد الأعلى للتنبيه 365 يوماً.']; }
}
