<?php
namespace App\Modules\HR\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class DecideContractRequest extends FormRequest {
    public function authorize(): bool { return $this->user()?->can('approve',$this->route('revision')->contract)??false; }
    public function rules(): array { return ['decision'=>['required',Rule::in(['approved','rejected'])],'reason'=>['required','string','min:5','max:5000']]; }
    public function messages(): array { return ['decision.in'=>'اختر اعتماداً أو رفضاً.','reason.min'=>'وضح سبب القرار بخمسة أحرف على الأقل.']; }
}
