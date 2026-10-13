@extends('layouts.app')
@section('title', 'مخطط الهيكل التنظيمي')
@section('pageName', 'مخطط الهيكل التنظيمي')
@section('content')
<div dir="rtl" lang="ar">
@include('hr::workflow-nav')
<div class="card mb-5"><div class="card-body">
<h1>مخطط الهيكل التنظيمي</h1>
<p>افتح الفروع لعرض التسلسل. يُعرض الموظفون الذين تسمح صلاحياتك برؤيتهم والتكليفات السارية اليوم.</p>
<form method="GET" action="{{ route('hr.organization.chart') }}" class="row g-4">
<label class="col-md-5">الاسم أو الرقم الوظيفي<input name="q" class="form-control" value="{{ $filters['q'] ?? '' }}"></label>
<label class="col-md-5">القسم<select name="department_id" class="form-select"><option value="">الكل</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(($filters['department_id'] ?? null) == $department->id)>{{ $department->name }}</option>@endforeach</select></label>
<div class="col-md-2 align-self-end"><button class="btn btn-primary">تصفية</button></div>
</form>
</div></div>
<div class="row g-5">
<section class="col-lg-5"><div class="card"><div class="card-body"><h2>تسلسل الأقسام</h2>
@include('hr::organization-tree', ['nodes'=>$departmentTree, 'employees'=>false])
</div></div></section>
<section class="col-lg-7"><div class="card"><div class="card-body"><h2>التسلسل الإداري للموظفين ({{ $employeeCount }})</h2>
<p class="text-muted">عند غياب المسؤول من النطاق المعروض، يبدأ فرع الموظف مستقلاً.</p>
@include('hr::organization-tree', ['nodes'=>$employeeTree, 'employees'=>true])
</div></div></section>
</div>
</div>
@endsection
