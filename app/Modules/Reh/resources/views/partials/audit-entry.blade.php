@php
    $syncEntry = ($entry->values['origin'] ?? 'manual') === 'sync';
    $createdEntry = $syncEntry && $entry->field === '__created';
@endphp
<details class="reh-audit-entry" data-audit-log="{{ $entry->id }}" data-audit-log-field="{{ $entry->field }}" data-audit-origin="{{ $syncEntry ? 'sync' : 'manual' }}" @if($syncEntry) data-audit-run="{{ $entry->values['sync_run_id'] }}" @else data-audit-history-group @endif>
    <summary><span><strong>{{ $createdEntry ? 'استبيان جديد' : $survey->label($entry->field) }}</strong><small>{{ $syncEntry ? 'منفّذ المزامنة: ' : '' }}{{ $entry->user?->name ?? ($syncEntry ? 'مزامنة النظام' : 'مستخدم سابق') }} · {{ $entry->created_at->format('Y-m-d H:i') }} · معرّف الحصر {{ $entry->values['reference_after'] ?? $auditRecord->old_objectid }}</small></span><span class="reh-audit-status is-{{ $entry->status }}">{{ $syncEntry ? 'استُقبل من ArcGIS' : (['superseded' => 'حُسمت بقيمة أحدث', 'pending' => 'بانتظار تأكيد الحفظ', 'completed' => 'حُفظ في المصدرين', 'failed' => 'لم يُحفظ', 'needs_review' => 'يلزم التحقق من النتيجة'][$entry->status] ?? $entry->status) }}</span></summary>
    @if(!$syncEntry)<span class="reh-audit-origin is-manual">تصحيح يدوي</span>@endif
    @if(!$createdEntry)<div class="reh-audit-comparison"><div><span>قبل</span><strong>{{ $survey->value($entry->field, $entry->values['before'] ?? null) }}</strong></div><div><span>بعد {{ $entry->status === 'completed' ? '' : '(التعديل المطلوب)' }}</span><strong>{{ $survey->value($entry->field, $entry->values['after'] ?? null) }}</strong></div></div>@endif
    @if($syncEntry)
        <p><strong>آخر معدّل في ArcGIS:</strong> <bdi>{{ $entry->values['remote_editor'] ?: 'غير متوفر' }}</bdi></p>
        <small>معرّف التأهيل وقت المزامنة: <bdi>{{ $entry->object_id }}</bdi></small>
    @else
        <p><strong>سبب التصحيح:</strong> {{ $entry->reason }}</p>
    @endif
    <p>{{ $entry->message }}</p>
    @if(!$syncEntry && in_array($entry->status, ['pending', 'needs_review'], true) && isset($auditFields[$entry->field]))<button type="button" class="btn btn-light-primary btn-sm" data-audit-verify="{{ route('reh.audit.verify', $entry) }}">التحقق من النتيجة</button>@endif
</details>
