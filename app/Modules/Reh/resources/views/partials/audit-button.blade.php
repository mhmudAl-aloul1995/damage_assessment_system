@if(isset($auditFields[$auditFieldName]))
<button type="button" class="reh-audit-trigger" data-audit-field="{{ $auditFieldName }}" @disabled($auditBlocked) aria-label="تصحيح: {{ $survey->label($auditFieldName) }}">تصحيح <span aria-hidden="true">↗</span></button>
@endif
@if(($auditHistory ?? collect())->where('field', $auditFieldName)->isNotEmpty())
<button type="button" class="reh-audit-trigger" data-audit-field-history="{{ $auditFieldName }}">سجل التعديلات ({{ $auditHistory->where('field', $auditFieldName)->count() }})</button>
@endif
