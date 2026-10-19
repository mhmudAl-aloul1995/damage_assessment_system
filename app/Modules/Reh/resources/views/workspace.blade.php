@extends('layouts.app')
@section('title', 'تأهيل المساكن — REH')
@section('pageName', 'المرحلة الثانية · التقييم والتدقيق')
@section('content')
@php($survey = app(\App\Modules\Reh\Services\RehSurvey::class))
<link rel="stylesheet" href="{{ asset('assets/css/reh.css') }}?v={{ filemtime(base_path('assets/css/reh.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/custom/leaflet/leaflet.bundle.rtl.css') }}">
<div id="reh-workspace" dir="rtl" data-index-url="{{ route('reh.index') }}" data-data-url="{{ route('reh.data') }}" data-sync-url="{{ route('reh.sync') }}" data-export-url="{{ route('reh.export') }}" data-initial="{{ json_encode($filters) }}">
    <header class="reh-hero">
        <div><span class="reh-eyebrow">REH / PHASE 02</span><h2>كل وحدة، خطوة نحو التعافي</h2><p>مساحة عمل التقييم والتدقيق لمشروع تأهيل المساكن المتضررة</p></div>
        <div class="reh-hero-note"><span class="badge badge-light">المرحلة الثانية</span><span>التسعير بانتظار قائمة الأسعار</span></div>
    </header>
    <nav class="reh-tabs" aria-label="أقسام مشروع REH">
        @foreach(['overview' => 'نظرة عامة', 'units' => 'الوحدات السكنية', 'buildings' => 'المباني والخريطة', 'reports' => 'التقارير'] as $key => $label)
            <a href="{{ route('reh.index', ['tab' => $key]) }}" data-tab="{{ $key }}">{{ $label }}</a>
        @endforeach
        @can('reh.sync')<a href="{{ route('reh.index', ['tab' => 'sync']) }}" data-tab="sync">المزامنة</a>@endcan
    </nav>
    <form id="reh-filters" class="reh-filter-bar" role="search" aria-label="تصفية سجلات المشروع">
        <div class="reh-filter-heading"><div><strong>تصفية النتائج</strong><span class="reh-filter-summary" data-filter-summary>مكتمل ميدانيًا</span></div><button type="reset" class="reh-reset-filters">إعادة الضبط</button></div>
        <div class="reh-filter-grid">
            <div class="reh-search"><label for="reh-q">البحث عن وحدة أو مبنى</label><input id="reh-q" name="q" class="form-control" placeholder="اكتب OBJECTID أو الاسم أو كود الوحدة…" autocomplete="off"></div>
            <div><label for="reh-survey-status">حالة الاستبيان</label><select id="reh-survey-status" name="survey_status" class="form-select" data-dropdown-parent="#reh-filters">@foreach($survey::FIELD_STATUSES as $value => $label)<option value="{{ $value }}" @selected(($filters['survey_status'] ?? 'completed') === $value)>{{ $label }}</option>@endforeach</select></div>
            @can('reh.scores.view')<div><label for="reh-priority">مستوى الأولوية</label><select id="reh-priority" name="priority" class="form-select" data-dropdown-parent="#reh-filters"><option value="">كل مستويات الأولوية</option>@foreach(\App\Modules\Reh\Services\RehScoring::PRIORITIES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach<option value="unclassified">غير مصنفة · بانتظار الاستكمال</option></select></div>@endcan
            <div><label for="reh-status">حالة التدقيق</label><select id="reh-status" name="status" class="form-select" data-dropdown-parent="#reh-filters"><option value="">كل حالات التدقيق</option>@foreach($survey::WORKFLOW as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
            <div><label for="reh-governorate">المحافظة</label><select id="reh-governorate" name="governorate" class="form-select" data-dropdown-parent="#reh-filters"><option value="">كل المحافظات</option>@foreach($survey->dictionary()['choices']['governorate'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
            <div><label for="reh-engineer">المهندس</label><select id="reh-engineer" name="engineer" class="form-select" data-dropdown-parent="#reh-filters"><option value="">كل المهندسين</option>@foreach($engineers as $value)<option value="{{ $value }}">{{ $survey->value('engineer_name', $value) }}</option>@endforeach</select></div>
            <div class="reh-filter-actions"><label class="reh-mine"><input type="checkbox" name="mine" value="1"> الملفات المسندة لي</label>@can('reh.export')<button type="button" data-export class="btn btn-light-primary btn-sm">تصدير النتائج CSV</button>@endcan</div>
        </div>
    </form>
    <div id="reh-message" role="status" aria-live="polite" hidden></div>
    <div id="reh-loading" class="reh-loading" role="status" hidden><span class="spinner-border spinner-border-sm"></span> جاري تحميل البيانات…</div>
    <section id="reh-results" aria-label="نتائج المشروع" aria-busy="true"><div class="reh-empty">جاري تجهيز مساحة العمل…</div></section>
    <noscript><div class="alert alert-warning">يلزم تفعيل JavaScript لاستخدام البحث والمراجعات في هذه المساحة.</div></noscript>
    <dialog id="reh-detail" class="reh-drawer" aria-label="ملف التقييم"><div class="reh-drawer-toolbar"><span>ملف التقييم</span><button type="button" data-close-detail class="btn btn-sm btn-light" aria-label="إغلاق ملف التقييم">إغلاق ✕</button></div><div id="reh-detail-message" role="alert" hidden></div><div id="reh-detail-body">{!! $initialDetail !!}</div>
        <dialog id="reh-score-source-dialog" class="reh-source-dialog" aria-labelledby="reh-source-title">
            <header class="reh-source-header"><div><small>البيانات الميدانية المرتبطة بالمؤشر</small><h3 id="reh-source-title">بيانات المؤشر</h3></div><button type="button" class="btn btn-sm btn-light" data-close-score-source aria-label="إغلاق البيانات الميدانية">✕</button></header>
            <div id="reh-score-source-body" class="reh-source-body"></div>
            <footer class="reh-source-footer"><span>عرض فقط · البيانات حسب آخر استقبال ومراجعة محفوظة.</span><button type="button" class="btn btn-light-primary" data-close-score-source>العودة للتقييم</button></footer>
        </dialog>
    </dialog>
</div>
@endsection
@section('script')
<script src="{{ asset('assets/plugins/custom/leaflet/leaflet.bundle.js') }}"></script>
<script src="{{ asset('assets/js/custom/reh/workspace.js') }}?v={{ filemtime(base_path('assets/js/custom/reh/workspace.js')) }}" defer></script>
@endsection
