<section class="reh-audit-history" aria-label="سجل تصحيح البيانات">
<header><h4>سجل تصحيح البيانات</h4><span>أحدث 100 تعديل · حسب صلاحية الاطلاع</span></header>
@if(!$auditReady)
<p>سجل التعديلات غير متاح حتى اكتمال تهيئة قاعدة البيانات.</p>
@else
@forelse($auditHistory as $entry)
<details class="reh-audit-entry" data-audit-log="{{ $entry->id }}" data-audit-log-field="{{ $entry->field }}"><summary><span><strong>{{ $survey->label($entry->field) }}</strong><small>{{ $entry->user?->name ?? 'مستخدم سابق' }} · {{ $entry->created_at->format('Y-m-d H:i') }} · معرّف الحصر {{ $auditRecord->old_objectid }}</small></span><span class="reh-audit-status is-{{ $entry->status }}">{{ ['superseded' => 'حُسمت بقيمة أحدث', 'pending' => 'بانتظار تأكيد الحفظ', 'completed' => 'حُفظ في المصدرين', 'failed' => 'لم يُحفظ', 'needs_review' => 'يلزم التحقق من النتيجة'][$entry->status] ?? $entry->status }}</span></summary>
<div class="reh-audit-comparison"><div><span>قبل</span><strong>{{ $survey->value($entry->field, $entry->values['before'] ?? null) }}</strong></div><div><span>بعد {{ $entry->status === 'completed' ? '' : '(التعديل المطلوب)' }}</span><strong>{{ $survey->value($entry->field, $entry->values['after'] ?? null) }}</strong></div></div>
<p><strong>سبب التصحيح:</strong> {{ $entry->reason }}</p><p>{{ $entry->message }}</p>
@if(in_array($entry->status, ['pending', 'needs_review'], true) && isset($auditFields[$entry->field]))<button type="button" class="btn btn-light-primary btn-sm" data-audit-verify="{{ route('reh.audit.verify', $entry) }}">التحقق من النتيجة</button>@endif
</details>
@empty<div class="reh-empty">لا توجد تعديلات ميدانية موثقة ضمن صلاحياتك حتى الآن.</div>@endforelse
@endif
</section>
