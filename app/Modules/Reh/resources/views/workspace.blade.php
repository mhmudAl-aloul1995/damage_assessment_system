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
    <form id="reh-filters" class="reh-filter-bar" role="search">
        <div class="reh-search"><label for="reh-q">بحث في السجلات</label><input id="reh-q" name="q" class="form-control" placeholder="كود الوحدة أو اسم المالك / المبنى" autocomplete="off"></div>
        <div><label for="reh-status">حالة التدقيق</label><select id="reh-status" name="status" class="form-select"><option value="">كل الحالات</option>@foreach($survey::WORKFLOW as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
        <div><label for="reh-governorate">المحافظة</label><select id="reh-governorate" name="governorate" class="form-select"><option value="">كل المحافظات</option>@foreach($survey->dictionary()['choices']['governorate'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
        <div><label for="reh-engineer">المهندس</label><select id="reh-engineer" name="engineer" class="form-select"><option value="">كل المهندسين</option>@foreach($engineers as $value)<option value="{{ $value }}">{{ $survey->value('engineer_name', $value) }}</option>@endforeach</select></div>
        <label class="reh-mine"><input type="checkbox" name="mine" value="1"> ملفاتي</label>
        <button type="reset" class="btn btn-light btn-sm">مسح الفلاتر</button>
        @can('reh.export')<button type="button" data-export class="btn btn-light-primary btn-sm">تصدير CSV</button>@endcan
    </form>
    <div id="reh-message" role="status" aria-live="polite" hidden></div>
    <div id="reh-loading" class="reh-loading" role="status" hidden><span class="spinner-border spinner-border-sm"></span> جاري تحميل البيانات…</div>
    <section id="reh-results" aria-label="نتائج المشروع" aria-busy="true"><div class="reh-empty">جاري تجهيز مساحة العمل…</div></section>
    <noscript><div class="alert alert-warning">يلزم تفعيل JavaScript لاستخدام البحث والمراجعات في هذه المساحة.</div></noscript>
    <dialog id="reh-detail" class="reh-drawer" aria-label="ملف التقييم"><div class="reh-drawer-toolbar"><span>ملف التقييم</span><button type="button" data-close-detail class="btn btn-sm btn-light" aria-label="إغلاق ملف التقييم">إغلاق ✕</button></div><div id="reh-detail-message" role="alert" hidden></div><div id="reh-detail-body">{!! $initialDetail !!}</div></dialog>
</div>
@endsection
@section('script')
<script src="{{ asset('assets/plugins/custom/leaflet/leaflet.bundle.js') }}"></script>
<script src="{{ asset('assets/js/custom/reh/workspace.js') }}?v={{ filemtime(base_path('assets/js/custom/reh/workspace.js')) }}" defer></script>
@endsection
