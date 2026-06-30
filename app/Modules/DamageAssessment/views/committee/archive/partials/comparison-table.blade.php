@php
    $changedRows = collect($rows)->where('changed', true)->values();
    $unchangedRows = collect($rows)->where('changed', false)->values();
@endphp

<div class="card card-flush border border-gray-200 mb-5">
    <div class="card-header">
        <div class="card-title">
            <div>
                <h3 class="fw-bold m-0">{{ $title }}</h3>
                <div class="text-muted fs-7 mt-1">تظهر فقط الحقول الظاهرة في ملف Excel، مع إبراز الحقول المتغيرة أولًا.</div>
            </div>
        </div>
        <div class="card-toolbar">
            <span class="badge badge-light-warning">{{ $changedRows->count() }} تغيير</span>
        </div>
    </div>
    <div class="card-body">
        @if ($missingCurrent)
            <div class="alert alert-light-warning mb-5">السجل الحالي غير موجود في قاعدة البيانات؛ تظهر المقارنة مع قيم فارغة.</div>
        @endif

        @if ($changedRows->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 mb-0">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th style="width: 22%">الحقل</th>
                            <th style="width: 35%">القيمة السابقة</th>
                            <th style="width: 35%">القيمة الحالية</th>
                            <th style="width: 8%">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($changedRows as $row)
                            <tr class="table-light-warning">
                                <td class="fw-semibold">{{ $row['label'] }}</td>
                                <td class="text-break text-muted">{{ $formatValue($row['old']) }}</td>
                                <td class="text-break fw-semibold">{{ $formatValue($row['current']) }}</td>
                                <td><span class="badge badge-light-warning">تغيّر</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded border border-dashed border-gray-300 bg-light-success p-5">
                <div class="fw-bold">لا توجد تغييرات في الحقول الظاهرة.</div>
                <div class="text-muted fs-7">النسخة المؤرشفة مطابقة للسجل الحالي ضمن الحقول المعروضة من Excel.</div>
            </div>
        @endif

        @if ($unchangedRows->isNotEmpty())
            <details class="mt-5">
                <summary class="fw-semibold text-primary cursor-pointer">عرض {{ $unchangedRows->count() }} حقل غير متغير</summary>
                <div class="table-responsive mt-4">
                    <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 mb-0">
                        <thead>
                            <tr class="fw-bold text-muted bg-light">
                                <th style="width: 22%">الحقل</th>
                                <th>القيمة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($unchangedRows as $row)
                                <tr>
                                    <td class="fw-semibold">{{ $row['label'] }}</td>
                                    <td class="text-break">{{ $formatValue($row['old']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </div>
</div>
