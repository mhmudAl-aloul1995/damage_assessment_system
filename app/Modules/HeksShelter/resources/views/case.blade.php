@extends('layouts.app')
@section('title', 'HEKS — '.$case->name)
@section('pageName', 'ملف المرحلة الثالثة')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/heks-shelter.css') }}?v={{ filemtime(base_path('assets/css/heks-shelter.css')) }}">
<div id="heks-workspace" dir="rtl"><p><a href="{{ route('heks3.index',['tab'=>'cases']) }}">العودة إلى ملفات المرحلة الثالثة</a></p>@include('heks-shelter::detail')</div>
@endsection
@section('script')
<script src="{{ asset('assets/js/custom/heks-shelter.js') }}?v={{ filemtime(base_path('assets/js/custom/heks-shelter.js')) }}" defer></script>
@endsection
