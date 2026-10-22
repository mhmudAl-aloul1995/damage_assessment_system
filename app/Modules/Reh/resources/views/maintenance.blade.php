@extends('layouts.app')
@section('title', 'مراجعة معرّفات الوحدات — REH')
@section('pageName', 'أدوات مسؤول قواعد البيانات')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/reh.css') }}?v={{ filemtime(base_path('assets/css/reh.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/reh-maintenance.css') }}?v={{ filemtime(base_path('assets/css/reh-maintenance.css')) }}">
<div id="reh-workspace" class="reh-maintenance" dir="rtl" data-bulk-preview-url="{{ route('reh.maintenance.bulk.preview') }}" data-bulk-delete-url="{{ route('reh.maintenance.bulk.destroy') }}" data-bulk-status-url="{{ route('reh.maintenance.bulk.status', '__operation__') }}" data-bulk-start-url="{{ route('reh.maintenance.bulk.start', '__operation__') }}" data-bulk-filters="{{ json_encode(['kind' => $filters['kind'] ?? 'duplicate', 'q' => $filters['q'] ?? '']) }}" data-total-units="{{ $units->total() }}">
    <header class="reh-hero"><div><span class="reh-eyebrow">REH / DATABASE</span><h2>مراجعة معرّفات الوحدات</h2><p>قارن السجلات المكررة، وراجع الوحدات التي لا تحمل معرّف حصر.</p></div><span class="badge badge-light">مسؤول قواعد البيانات</span></header>
    <nav class="reh-tabs" aria-label="أقسام مشروع REH"><a href="{{ route('reh.index') }}">مساحة المشروع</a><a href="{{ route('reh.index', ['tab' => 'sync']) }}">المزامنة</a><a class="active" aria-current="page" href="{{ route('reh.maintenance.index') }}">مراجعة معرّفات الوحدات</a></nav>
    @if(session('reh_maintenance_message'))<div class="alert alert-success" role="status">{{ session('reh_maintenance_message') }}</div>@endif
    @if(!$ready)<div class="alert alert-warning" role="alert">{{ \App\Modules\Reh\Services\RehFieldAudit::SETUP_MESSAGE }}</div>@endif
    <div id="reh-maintenance-message" class="alert alert-danger" role="alert" hidden></div>
    <section class="reh-card reh-bulk-operation" data-bulk-operation data-initial="{{ json_encode($bulkOperation) }}" @if(!$bulkOperation) hidden @endif aria-label="متابعة الحذف الجماعي">
        <div class="reh-maintenance-heading"><strong>متابعة طلب الحذف الجماعي</strong><span data-bulk-state></span></div>
        <progress data-bulk-progress max="100" value="0" aria-label="تقدم الحذف الجماعي"></progress>
        <p data-bulk-summary aria-live="polite"></p><small data-bulk-message></small>
        <details data-bulk-issues hidden><summary data-bulk-issues-title>وحدات تحتاج المراجعة</summary><ul data-bulk-issues-list></ul></details>
        <div class="reh-maintenance-actions"><a href="#reh-maintenance-history" class="btn btn-sm btn-light" data-open-deletion-history>سجل الوحدات</a><button type="button" class="btn btn-sm btn-light-primary" data-start-bulk hidden>بدء المعالجة</button></div>
    </section>
    @php($kind = $filters['kind'] ?? 'duplicate')
    <nav class="reh-maintenance-stats" aria-label="تصفية حسب حالة معرّف الحصر">
        @foreach(['duplicate' => ['معرّف حصر مكرر', $statistics['groups'].' معرّفًا مشتركًا بين الوحدات'], 'missing' => ['معرّف حصر فارغ', 'يشمل القيم الفارغة وNULL'], 'all' => ['كل الوحدات', 'يشمل السجلات غير الموجودة في المصدر']] as $key => [$label, $caption])
        <a href="{{ route('reh.maintenance.index', ['kind' => $key, 'q' => $filters['q'] ?? null]) }}" class="reh-maintenance-stat {{ $kind === $key ? 'is-active' : '' }}" @if($kind === $key) aria-current="true" @endif><span>{{ $label }}</span><strong>{{ number_format($statistics[$key]) }}</strong><small>{{ $caption }}</small></a>
        @endforeach
    </nav>
    <section class="reh-card" aria-label="الوحدات حسب الفلتر">
        <form method="get" action="{{ route('reh.maintenance.index') }}" class="reh-maintenance-search">
            <input type="hidden" name="kind" value="{{ $kind }}">
            <div><label for="reh-reference-search">البحث بمعرّف حصر الوحدة أو معرّف التأهيل</label><input type="search" id="reh-reference-search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" maxlength="100" placeholder="اكتب المعرّف…"></div>
            <button class="btn btn-primary" type="submit">بحث</button><a class="btn btn-light" href="{{ route('reh.maintenance.index', ['kind' => $kind]) }}">مسح البحث</a>
        </form>
        <div class="reh-maintenance-heading"><strong>{{ number_format($units->total()) }} وحدة مطابقة</strong><span>مرتّبة تصاعديًا حسب معرّف حصر الوحدة · الفارغ في النهاية</span></div>
        <div class="reh-bulk-selection" data-bulk-selection hidden role="region" aria-label="إجراءات الوحدات المحددة"><div><strong data-selection-count></strong><small data-selection-scope></small></div><button type="button" class="btn btn-sm btn-light-primary" data-select-filtered @disabled(!$ready)>تحديد كل نتائج الفلتر ({{ number_format($units->total()) }})</button><button type="button" class="btn btn-sm btn-light" data-clear-selection>إلغاء التحديد</button><button type="button" class="btn btn-sm btn-danger" data-delete-selected @disabled(!$ready)>حذف المحدد</button></div>
        <div class="reh-maintenance-table-wrap"><table class="table reh-table align-middle">
            <thead><tr><th class="reh-selection-cell"><input type="checkbox" data-select-page aria-label="تحديد كل وحدات هذه الصفحة" @disabled(!$ready || $units->isEmpty())></th><th>معرّف الوحدة</th><th>المبنى</th><th>المالك</th><th>الطابق والشقة</th><th>الفريق ومنشئ السجل</th><th>حالة الملف</th><th>الإجراءات</th></tr></thead>
            <tbody>
            @forelse($units as $unit)
                <tr data-maintenance-unit="{{ $unit->id }}">
                    <td class="reh-selection-cell"><input type="checkbox" data-select-unit data-id="{{ $unit->id }}" data-revision="{{ $maintenance->revision($unit) }}" aria-label="تحديد الوحدة بمعرّف التأهيل {{ $unit->object_id }}" @disabled(!$ready)></td>
                    <td><strong class="reh-maintenance-reference">{{ $unit->old_objectid }}</strong><small>معرّف التأهيل: <bdi>{{ $unit->object_id }}</bdi></small>
                        @if(trim((string) $unit->reference_id) === '')<span class="reh-maintenance-badge is-missing">معرّف حصر فارغ</span>
                        @elseif($unit->reference_count > 1)<span class="reh-maintenance-badge is-duplicate">مكرر · {{ $unit->reference_count }} وحدات</span>
                        @else<span class="reh-maintenance-badge">غير مكرر</span>@endif
                    </td>
                    <td>معرّف الحصر: {{ $unit->building?->old_objectid ?? 'غير متوفر' }}<small>{{ $unit->building?->name ?: 'اسم المبنى غير متوفر' }}</small></td>
                    <td>{{ $unit->owner_name ?: 'غير متوفر' }}</td>
                    <td>الطابق {{ $unit->floor_number ?? 'غير متوفر' }}<small>الشقة {{ $unit->apartment_number ?? 'غير متوفر' }}</small></td>
                    <td>{{ $survey->value('engineer_name', $unit->building?->engineer) }}<small>منشئ السجل: <bdi>{{ $unit->creator ?? $unit->source['Creator'] ?? $unit->source['creator'] ?? 'غير متوفر' }}</bdi></small></td>
                    <td>{{ $survey::WORKFLOW[$unit->workflow_status] ?? $unit->workflow_status }}<small>{{ ['pending' => 'بانتظار القرار', 'selected' => 'مقبول مبدئيًا', 'excluded' => 'مستبعد'][$unit->decision] ?? $unit->decision }}</small>@if($unit->source_missing)<span class="reh-maintenance-badge is-missing">غير موجود في المصدر</span>@endif</td>
                    <td><div class="reh-maintenance-actions"><a href="{{ route('reh.units.show', $unit) }}" class="btn btn-sm btn-light">ملف الوحدة</a><button type="button" class="btn btn-sm btn-light-danger" data-delete-unit data-url="{{ route('reh.maintenance.destroy', $unit) }}" data-reference="{{ $unit->old_objectid }}" data-object-id="{{ $unit->object_id }}" data-revision="{{ $maintenance->revision($unit) }}" @disabled(!$ready)>حذف</button></div></td>
                </tr>
            @empty<tr><td colspan="8"><div class="reh-empty"><strong>لا توجد وحدات مطابقة</strong><p>جرّب فلترًا آخر أو امسح البحث.</p></div></td></tr>@endforelse
            </tbody>
        </table></div>
        @if($units->hasPages())<nav class="reh-maintenance-pagination" aria-label="صفحات الوحدات">@if($units->previousPageUrl())<a class="btn btn-sm btn-light" href="{{ $units->previousPageUrl() }}">السابق</a>@endif<span>صفحة {{ $units->currentPage() }} من {{ $units->lastPage() }}</span>@if($units->nextPageUrl())<a class="btn btn-sm btn-light" href="{{ $units->nextPageUrl() }}">التالي</a>@endif</nav>@endif
    </section>
    <details id="reh-maintenance-history" class="reh-card reh-maintenance-history" @if($logs->whereIn('status', ['pending', 'needs_review'])->isNotEmpty()) open @endif><summary>سجل الحذف <span>العمليات المعلّقة وآخر العمليات</span></summary>
        @forelse($logs as $log)<article><div><strong>معرّف الحصر {{ $log->values['reference'] ?? 'غير متوفر' }} · معرّف التأهيل {{ $log->object_id }}</strong><small>{{ $log->user?->name ?? 'مستخدم غير متوفر' }} · {{ $log->created_at->format('Y-m-d H:i') }} · {{ ['database' => 'قاعدة البيانات فقط', 'arcgis' => 'ArcGIS فقط', 'both' => 'ArcGIS وقاعدة البيانات'][$log->field] }}</small></div><span class="reh-maintenance-badge {{ $log->status === 'completed' ? '' : 'is-missing' }}">{{ ['completed' => 'مكتمل', 'failed' => 'لم يتم الحذف', 'pending' => 'جارٍ التنفيذ', 'needs_review' => 'يلزم التحقق', 'partial' => 'حذف جزئي'][$log->status] ?? $log->status }}</span><p>{{ $log->reason }}</p><small>{{ $log->message }}</small>@if(in_array($log->status, ['pending', 'needs_review'], true))<button type="button" class="btn btn-sm btn-light-primary" data-verify-deletion="{{ route('reh.maintenance.verify', $log) }}">التحقق من نتيجة الحذف</button>@endif</article>@empty<p class="text-muted mt-5">لا توجد عمليات حذف مسجلة.</p>@endforelse
    </details>
    <dialog id="reh-unit-delete-dialog" aria-labelledby="reh-unit-delete-title" aria-describedby="reh-unit-delete-caption">
        <form method="post" data-unit-delete-form>@csrf @method('DELETE')
            <header><h3 id="reh-unit-delete-title">حذف الوحدة</h3><button type="button" class="btn btn-sm btn-light" data-close-delete aria-label="إغلاق تأكيد الحذف">إغلاق ✕</button></header>
            <p id="reh-unit-delete-caption"></p><input type="hidden" name="revision">
            <input type="hidden" name="selection_token">
            <p class="reh-delete-impact" data-bulk-caption hidden>يشمل الحذف جميع الوحدات المحددة، وقد يشمل كل النسخ التي تحمل معرّف الحصر نفسه. تستمر المعالجة في الخلفية عند إغلاق الصفحة.</p>
            <div class="alert alert-danger" data-delete-message role="alert" hidden></div>
            <a class="btn btn-sm btn-light-primary" href="{{ route('reh.maintenance.index') }}#reh-maintenance-history" data-refresh-deletion hidden>تحديث الصفحة وعرض سجل الحذف</a>
            <fieldset class="reh-delete-destinations"><legend>مكان الحذف</legend><div><label><input type="radio" name="destination" value="database" required><span>قاعدة البيانات فقط</span></label><label><input type="radio" name="destination" value="arcgis" required><span>ArcGIS فقط</span></label><label><input type="radio" name="destination" value="both" required><span>ArcGIS وقاعدة البيانات</span></label></div></fieldset>
            <p class="reh-delete-impact" data-delete-impact aria-live="polite">حدد مكان الحذف لمعرفة أثره.</p>
            <label for="reh-delete-reason">سبب الحذف <span class="text-danger">*</span></label><textarea class="form-control" id="reh-delete-reason" name="reason" required minlength="3" maxlength="2000" rows="3" placeholder="مثال: سجل مكرر تمت مراجعته ومقارنته بالسجل الصحيح."></textarea>
            <p class="reh-delete-archive">تُحفظ نسخة من بيانات الوحدة وسجل مراجعاتها ونسخ تكلفتها في سجل الحذف قبل إزالة البيانات المحلية.</p>
            <label class="reh-delete-confirm"><input type="checkbox" name="confirm" value="1" required><span data-delete-confirm-label>راجعت معرّف التأهيل ووجهة الحذف وأؤكد حذف هذا السجل.</span></label>
            <footer><button type="button" class="btn btn-light" data-close-delete>إلغاء</button><button type="submit" class="btn btn-danger" data-confirm-delete>تأكيد الحذف</button></footer>
        </form>
    </dialog>
</div>
@endsection
@section('script')<script src="{{ asset('assets/js/custom/reh/maintenance.js') }}?v={{ filemtime(base_path('assets/js/custom/reh/maintenance.js')) }}" defer></script>@endsection
