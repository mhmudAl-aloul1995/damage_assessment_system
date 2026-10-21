@extends('layouts.app')
@section('title', ($exportPage ?? false) ? 'تصدير بيانات تأهيل المساكن — REH' : 'تأهيل المساكن — REH')
@section('pageName', 'المرحلة الثانية · التقييم والتدقيق')
@section('content')
@php($survey = app(\App\Modules\Reh\Services\RehSurvey::class))
@can('reh.export')<script type="application/json" id="reh-export-column-catalog">@json(\App\Modules\Reh\Services\RehExportColumns::catalog(auth()->user()))</script>@endcan
<link rel="stylesheet" href="{{ asset('assets/css/reh.css') }}?v={{ filemtime(base_path('assets/css/reh.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/reh-pricing.css') }}?v={{ filemtime(base_path('assets/css/reh-pricing.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/custom/leaflet/leaflet.bundle.rtl.css') }}">
<div id="reh-workspace" dir="rtl" data-index-url="{{ route($exportPage ? 'reh.exports' : 'reh.index') }}" data-data-url="{{ route($exportPage ? 'reh.exports.data' : 'reh.data') }}" data-workspace-url="{{ route('reh.index') }}" data-export-page="{{ $exportPage ? 'true' : 'false' }}" data-export-page-url="{{ route('reh.exports') }}" data-excel-url="{{ route('reh.export.excel') }}" data-sync-url="{{ route('reh.sync') }}" data-export-url="{{ route('reh.export') }}" data-pdf-url="{{ route('reh.export.pdf') }}" data-initial="{{ json_encode($filters) }}">
    <header class="reh-hero">
        <div><span class="reh-eyebrow">REH / PHASE 02</span><h2>{{ $exportPage ? 'تصدير بيانات المشروع' : 'كل وحدة، خطوة نحو التعافي' }}</h2><p>{{ $exportPage ? 'حدد نطاق البيانات بالفلاتر نفسها، ثم نزّل النتائج بصيغة Excel أو PDF.' : 'مساحة عمل التقييم والتدقيق لمشروع تأهيل المساكن المتضررة' }}</p></div>
        <div class="reh-hero-note"><span class="badge badge-light">المرحلة الثانية</span><span>{{ app(\App\Modules\Reh\Services\RehPricing::class)->statusLabel() }}</span></div>
    </header>
    <nav class="reh-tabs" aria-label="أقسام مشروع REH">
        @can('reh.pricing.view')<a href="{{ route('reh.pricing.index') }}">مكتبة الأسعار</a><a href="{{ route('reh.index', ['tab'=>'budget']) }}" @if($exportPage) data-workspace-tab="budget" @else data-tab="budget" @endif>الميزانية والاحتياجات</a>@endcan
        @foreach(['overview' => 'نظرة عامة', 'units' => 'الوحدات السكنية', 'buildings' => 'المباني والخريطة', 'reports' => 'التقارير'] as $key => $label)
            <a href="{{ route('reh.index', ['tab' => $key]) }}" @if($exportPage) data-workspace-tab="{{ $key }}" @else data-tab="{{ $key }}" @endif>{{ $label }}</a>
        @endforeach
        @can('reh.export')<a href="{{ route('reh.exports') }}" data-open-export-page @if($exportPage) class="active" aria-current="page" @endif>التصدير</a>@endcan
        @can('reh.sync')<a href="{{ route('reh.index', ['tab' => 'sync']) }}" @if($exportPage) data-workspace-tab="sync" @else data-tab="sync" @endif>المزامنة</a>@endcan
    </nav>
    @include('reh::partials.filters')
    <div id="reh-message" role="status" aria-live="polite" hidden></div>
    <div id="reh-loading" class="reh-loading" role="status" hidden><span class="spinner-border spinner-border-sm"></span> جاري تحميل البيانات…</div>
    <section id="reh-results" aria-label="نتائج المشروع" aria-busy="true"><div class="reh-empty">جاري تجهيز مساحة العمل…</div></section>
    <noscript><div class="alert alert-warning">يلزم تفعيل JavaScript لاستخدام البحث والمراجعات في هذه المساحة.</div></noscript>
    <dialog id="reh-detail" class="reh-drawer" aria-label="ملف التقييم"><div class="reh-drawer-toolbar"><span>ملف التقييم</span><button type="button" data-close-detail class="btn btn-sm btn-light" aria-label="إغلاق ملف التقييم">إغلاق ✕</button></div><div id="reh-detail-message" role="alert" hidden></div><div id="reh-detail-body">{!! $initialDetail !!}</div>
    </dialog>
    <dialog id="reh-audit-history-dialog" class="reh-audit-log-modal" aria-labelledby="reh-audit-history-title" aria-describedby="reh-audit-history-caption">
        <header class="reh-audit-log-toolbar"><div><span class="reh-eyebrow">سجل التعديلات</span><h3 id="reh-audit-history-title"></h3></div><button type="button" class="btn btn-sm btn-light" data-close-audit-history aria-label="إغلاق سجل التعديلات">إغلاق ✕</button></header>
        <p id="reh-audit-history-caption" class="reh-audit-log-caption"></p>
        <div data-audit-history-body class="reh-audit-log-body"></div>
    </dialog>
</div>
@include('reh::pricing.dialog')
@endsection
@section('script')
<script src="{{ asset('assets/js/custom/reh/pricing.js') }}?v={{ filemtime(base_path('assets/js/custom/reh/pricing.js')) }}" defer></script>
<script src="{{ asset('assets/plugins/custom/leaflet/leaflet.bundle.js') }}"></script>
<script src="{{ asset('assets/js/custom/reh/workspace.js') }}?v={{ filemtime(base_path('assets/js/custom/reh/workspace.js')) }}" defer></script>
@endsection
