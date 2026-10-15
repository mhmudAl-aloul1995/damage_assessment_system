@extends('layouts.app')
@section('title', 'الموارد البشرية HR')
@section('pageName', 'الرئيسية — الموارد البشرية')
@section('content')
<div dir="rtl" lang="ar" class="d-flex flex-column gap-6">
    <div class="card">
        <div class="card-body p-lg-10">
            <span class="badge badge-light-primary mb-4">الموارد البشرية HR</span>
            <h1 class="fs-2 fw-bold mb-3">أهلاً بك في الموارد البشرية</h1>
            <p class="text-muted fs-5 mb-6">ابدأ بمتابعة حضور الموظفين وانصرافهم من مكان واحد.</p>
            <a class="btn btn-primary" href="{{ route('hr.attendance.index') }}">فتح شاشة الحضور</a>
        </div>
    </div>
    <div class="row g-5">
        <div class="col-md-6">
            <div class="card h-100"><div class="card-body">
                <h2 class="fs-4 mb-3">الحضور اليومي</h2>
                <p class="text-muted">راجع حضور اليوم وأوقات الدخول والخروج والتأخير.</p>
                <a class="btn btn-light-primary" href="{{ route('hr.attendance.index', ['date' => now(config('hr.timezone'))->toDateString()]) }}">عرض حضور اليوم</a>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card h-100"><div class="card-body">
                <h2 class="fs-4 mb-3">الحضور الشهري</h2>
                <p class="text-muted">تصفّح سجلات الشهر وابحث عن موظف بالاسم أو الرقم الوظيفي.</p>
                <a class="btn btn-light-primary" href="{{ route('hr.attendance.index', ['month' => now(config('hr.timezone'))->format('Y-m')]) }}">عرض الشهر الحالي</a>
            </div></div>
        </div>
    </div>
    @can('hr.attendance.setup')
        <div class="card"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-4">
            <div><h2 class="fs-5">إعداد الحضور</h2><p class="text-muted mb-0">اربط الموظفين بحساباتهم وحدد جداول العمل والورديات.</p></div>
            <a class="btn btn-light" href="{{ route('hr.attendance.setup') }}">فتح إعدادات الحضور</a>
        </div></div>
    @endcan
</div>
@endsection
