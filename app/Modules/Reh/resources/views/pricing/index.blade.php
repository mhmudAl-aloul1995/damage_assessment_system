@extends('layouts.app')
@section('title','التسعير والميزانية — REH')
@section('pageName','قائمة الأسعار وإصداراتها')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/reh.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/reh-pricing.css') }}?v={{ filemtime(base_path('assets/css/reh-pricing.css')) }}">
<div class="reh-pricing" id="reh-pricing-page" dir="rtl" data-url="{{ route('reh.pricing.index') }}">
<header class="reh-hero"><div><span class="reh-eyebrow">REH / PRICING</span><h2>أسعار واضحة، تكلفة قابلة للتتبع</h2><p>راجع التحليل، اعتمد إصدار الأسعار، ثم احتسب تكلفة الوحدات.</p></div><a class="btn btn-light" href="{{ route('reh.index',['tab'=>'budget']) }}">ميزانية المشروع</a></header>
<nav class="reh-tabs"><a href="{{ route('reh.index') }}">مساحة المشروع</a><a class="active" href="{{ route('reh.pricing.index') }}">مكتبة الأسعار</a><a href="{{ route('reh.index',['tab'=>'budget']) }}">الميزانية والاحتياجات</a></nav>
<div class="reh-price-message" role="status" aria-live="polite" hidden></div>
<div id="reh-price-content">@include('reh::pricing.content')</div>
</div>
@include('reh::pricing.dialog')
@endsection
@section('script')
<script src="{{ asset('assets/js/custom/reh/pricing.js') }}?v={{ filemtime(base_path('assets/js/custom/reh/pricing.js')) }}" defer></script>
@endsection

