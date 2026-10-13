<label class="col-md-6">نوع العقد<select name="contract_type_id" required class="form-select">@foreach($types->where('active',true) as $type)<option value="{{ $type->id }}" @selected(old('contract_type_id',$previous?->contract_type_id)==$type->id)>{{ $type->name }}</option>@endforeach</select></label>
<label class="col-md-6">من<input type="date" name="starts_on" required class="form-control" value="{{ old('starts_on',$previous?->starts_on->toDateString()) }}"></label>
<label class="col-md-6">إلى (للنوع المحدد المدة)<input type="date" name="ends_on" class="form-control" value="{{ old('ends_on',$previous?->ends_on?->toDateString()) }}"></label>
<label class="col-md-6">معرف مستند العمل الحالي (اختياري)<input type="number" min="1" name="document_id" class="form-control" value="{{ old('document_id',$previous?->document_id) }}"></label>
<label>سبب المقترح<textarea name="reason" required minlength="5" maxlength="5000" class="form-control">{{ old('reason') }}</textarea></label>
@can('hr.contracts.sensitive')
@php($terms=$previous?->financial_terms)
<fieldset class="row g-3"><legend class="fs-5">الراتب والمزايا الخاصة</legend>
<label class="col-md-3">الراتب (غير معروف إذا ترك فارغاً)<input name="financial_terms[salary]" class="form-control" inputmode="decimal" value="{{ old('financial_terms.salary',$terms['salary'] ?? '') }}"></label>
<label class="col-md-3">العملة (3 حروف)<input name="financial_terms[currency]" maxlength="3" pattern="[A-Z]{3}" class="form-control" value="{{ old('financial_terms.currency',$terms['currency'] ?? '') }}"></label>
<label class="col-md-3">المنازل العشرية للعملة<input type="number" min="0" max="4" name="financial_terms[currency_scale]" class="form-control" value="{{ old('financial_terms.currency_scale',$terms['currency_scale'] ?? 2) }}"></label>
<label class="col-md-3">أساس الراتب<select name="financial_terms[basis]" class="form-select"><option value="">غير محدد</option>@foreach(['monthly'=>'شهري','daily'=>'يومي','hourly'=>'بالساعة'] as $key=>$label)<option value="{{ $key }}" @selected(old('financial_terms.basis',$terms['basis']??null)==$key)>{{ $label }}</option>@endforeach</select></label>
<label>المزايا وشروطها<textarea name="financial_terms[benefits]" class="form-control" maxlength="5000">{{ old('financial_terms.benefits',$terms['benefits'] ?? '') }}</textarea></label>
</fieldset>
@endcan
