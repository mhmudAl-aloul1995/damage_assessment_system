@if(!$auditReady)
<div class="alert alert-warning" role="status">{{ \App\Modules\Reh\Services\RehFieldAudit::SETUP_MESSAGE }}</div>
@elseif($auditFields)
@php
    $editorFields = [];
    foreach ($auditFields as $name => $definition) {
        $raw = $auditRecord->source[$name] ?? null;
        $editorFields[$name] = ['label' => $definition['label'], 'type' => $definition['input_type'], 'choices' => $definition['choices'], 'value' => $definition['input_type'] === 'date' && is_numeric($raw) ? \Carbon\CarbonImmutable::createFromTimestampMs((int) $raw)->utc()->format('Y-m-d') : $raw, 'display' => $survey->value($name, $raw), 'group' => $definition['group']['label'] ?? 'بيانات عامة'];
    }
@endphp
<script type="application/json" data-audit-fields>@json($editorFields)</script>
<div class="reh-audit-intro"><span class="reh-audit-icon" aria-hidden="true">✓</span><div><strong>تدقيق البيانات الميدانية</strong><p>{{ $auditBlocked ?: 'افتح البيانات الميدانية أو كميات الأعمال، واضغط «تصحيح» بجانب الإجابة. لا يلزم إسناد الملف.' }}</p><small>الحفظ في قاعدة البيانات وArcGIS · سبب إلزامي · سجل قبل / بعد</small></div></div>
<section class="reh-audit-editor" data-audit-editor hidden aria-labelledby="reh-audit-title">
    <form data-audit-form action="{{ route('reh.audit.save', [$auditKind, $auditRecord->id]) }}">
        @csrf<input type="hidden" name="revision" value="{{ $auditRevision }}"><input type="hidden" name="field">
        <header><div><span class="reh-eyebrow" data-audit-group></span><h3 id="reh-audit-title" data-audit-title></h3><small>{{ $auditKind === 'unit' ? 'وحدة' : 'مبنى' }} · old_objectid {{ $auditRecord->old_objectid }}</small></div><button type="button" data-audit-cancel class="btn btn-light btn-sm">إلغاء</button></header>
        <div class="reh-audit-comparison"><div><span>القيمة الحالية</span><strong data-audit-before></strong></div><div><label for="reh-audit-value">القيمة الصحيحة <span aria-hidden="true">*</span></label><div data-audit-input></div></div></div>
        <label for="reh-audit-reason">لماذا تم التصحيح؟ <span aria-hidden="true">*</span></label><textarea id="reh-audit-reason" class="form-control" name="reason" rows="2" required minlength="3" maxlength="2000" placeholder="مثال: مطابقة العدد مع استمارة الزيارة الميدانية."></textarea>
        <p class="reh-audit-impact">بعد الحفظ يُعاد حساب التقييم، وتعود مراجعة البيانات المتأثرة إلى الانتظار. تعديل المبنى يعيد تدقيق وحداته. لا تكتب تفاصيل صحية في سبب التعديل.</p>
        <div class="reh-audit-feedback" data-audit-feedback role="alert" hidden></div>
        <footer><span>سيُسجّل اسمك ووقت التعديل والقيمتان.</span><button type="submit" class="btn btn-primary">حفظ في قاعدة البيانات وArcGIS</button></footer>
    </form>
</section>
@endif
