@php
    $changedRows = collect($rows)->where('changed', true)->values();
    $unchangedRows = collect($rows)->where('changed', false)->values();
@endphp

<div class="card card-flush border border-gray-200 mb-5">
    <div class="card-header"><div class="card-title"><div><h3 class="fw-bold m-0">{{ $title }}</h3><div class="text-muted fs-7 mt-1">تظهر الحقول المتغيرة أولًا لتسهيل المراجعة.</div></div></div><div class="card-toolbar"><span class="badge badge-light-warning">{{ $changedRows->count() }} تغيير</span></div></div>
    <div class="card-body">
        @if ($missingCurrent)<div class="alert alert-light-warning mb-5">السجل الحالي غير موجود في قاعدة البيانات؛ تظهر المقارنة مع قيم فارغة.</div>@endif
        @if ($changedRows->isNotEmpty())
            <div class="table-responsive"><table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 mb-0"><thead><tr class="fw-bold text-muted bg-light"><th style="width: 22%">الحقل</th><th style="width: 35%">القيمة السابقة</th><th style="width: 35%">القيمة الحالية</th><th style="width: 8%">الحالة</th></tr></thead><tbody>@foreach ($changedRows as $row)<tr class="table-light-warning"><td class="fw-semibold">{{ $row['label'] }}</td><td class="text-break text-muted">{{ $formatValue($row['old']) }}</td><td class="text-break fw-semibold">{{ $formatValue($row['current']) }}</td><td><span class="badge badge-light-warning">تغيّر</span></td></tr>@endforeach</tbody></table></div>
        @else
            <div class="rounded border border-dashed border-gray-300 bg-light-success p-5"><div class="fw-bold">لا توجد تغييرات في الحقول المسجلة.</div><div class="text-muted fs-7">النسخة المؤرشفة مطابقة للسجل الحالي ضمن هذه البيانات.</div></div>
        @endif
        @if ($unchangedRows->isNotEmpty())
            <details class="mt-5"><summary class="fw-semibold text-primary cursor-pointer">عرض {{ $unchangedRows->count() }} حقل غير متغير</summary><div class="table-responsive mt-4"><table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 mb-0"><thead><tr class="fw-bold text-muted bg-light"><th style="width: 22%">الحقل</th><th>القيمة</th></tr></thead><tbody>@foreach ($unchangedRows as $row)<tr><td class="fw-semibold">{{ $row['label'] }}</td><td class="text-break">{{ $formatValue($row['old']) }}</td></tr>@endforeach</tbody></table></div></details>
        @endif
        <details class="mt-5"><summary class="fw-semibold text-muted cursor-pointer">عرض السجل الخام للمراجعة الفنية</summary><div class="row g-5 mt-1"><div class="col-lg-6"><div class="border border-gray-200 rounded p-4 h-100"><div class="fw-bold mb-3">السجل السابق</div><pre class="bg-light p-4 rounded text-break mb-0" style="white-space: pre-wrap; max-height: 360px; overflow: auto;">{{ $previousRecord }}</pre></div></div><div class="col-lg-6"><div class="border border-gray-200 rounded p-4 h-100"><div class="fw-bold mb-3">السجل الحالي</div><pre class="bg-light p-4 rounded text-break mb-0" style="white-space: pre-wrap; max-height: 360px; overflow: auto;">{{ $currentRecord }}</pre></div></div></div></details>
    </div>
</div>
