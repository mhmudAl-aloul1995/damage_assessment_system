<section class="reh-audit-history" data-audit-history-scope aria-label="سجل التعديلات">
<header><div><h4>سجل التعديلات</h4><span>أحدث 100 سجل متاح · حسب صلاحية الاطلاع</span></div><button type="button" class="btn btn-sm btn-light-primary" data-audit-all-history>فتح سجل التعديلات</button></header>
@if(!$auditReady)
<p>سجل التعديلات غير متاح حتى اكتمال تهيئة قاعدة البيانات.</p>
@else
@include('reh::partials.audit-origin-filters')
@foreach($auditHistory->groupBy(fn ($entry) => ($entry->values['origin'] ?? 'manual') === 'sync' ? 'sync:'.$entry->values['sync_run_id'] : 'manual:'.$entry->id) as $entries)
    @php
        $first = $entries->first();
        $isSync = ($first->values['origin'] ?? 'manual') === 'sync';
    @endphp
    @if($isSync)
    <details class="reh-audit-sync-group" data-audit-history-group data-audit-origin="sync" data-audit-run="{{ $first->values['sync_run_id'] }}">
        <summary><span><span class="reh-audit-origin is-sync">مزامنة ArcGIS</span><strong>مزامنة #{{ $first->values['sync_run_id'] }}</strong><small>{{ $first->created_at->format('Y-m-d H:i') }}</small></span><span data-audit-group-count>{{ $entries->count() }} {{ $first->field === '__created' ? 'استبيان جديد' : 'تغيير' }}</span></summary>
        @foreach($entries as $entry)@include('reh::partials.audit-entry')@endforeach
    </details>
    @else
        @foreach($entries as $entry)@include('reh::partials.audit-entry')@endforeach
    @endif
@endforeach
<div class="reh-empty" data-audit-empty-filter @if($auditHistory->isNotEmpty()) hidden @endif>لا توجد تعديلات متاحة ضمن هذا الفلتر.</div>
@endif
</section>
