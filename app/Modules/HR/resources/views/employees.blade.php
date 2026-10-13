@extends('layouts.app')
@section('title', 'إدارة الموظفين')
@section('pageName', 'إدارة الموظفين')
@section('content')
<div dir="rtl" lang="ar">
@include('hr::workflow-nav')
<div class="card"><div class="card-header align-items-center"><h1 class="card-title">الموظفون ضمن صلاحياتك</h1>@if(auth()->user()->can('hr.employees.manage') && auth()->user()->can('hr.employees.view'))<a class="btn btn-primary" href="{{ route('hr.employees.create') }}">إضافة موظف</a>@endif</div><div class="card-body">
<form class="d-flex flex-wrap gap-3 mb-5" method="GET">
<label>الاسم أو الرقم الوظيفي<input class="form-control" name="q" value="{{ $filters['q'] ?? '' }}"></label>
<label>القسم<select class="form-select" name="department_id"><option value="">جميع الأقسام ضمن النطاق</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(($filters['department_id'] ?? '') == $department->id)>{{ $department->name }}</option>@endforeach</select></label>
<label>الأرشفة<select class="form-select" name="archive">@foreach(['active'=>'غير مؤرشف','archived'=>'مؤرشف','all'=>'الجميع'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['archive'] ?? 'active') === $key)>{{ $label }}</option>@endforeach</select></label><button class="btn btn-primary">بحث</button></form>
<div class="table-responsive"><table class="table table-row-dashed"><thead><tr><th>الرقم</th><th>الموظف</th><th>القسم والمسمى الحالي</th><th>المسؤول المباشر</th><th>فترة العمل</th><th>الملف</th></tr></thead><tbody>
@forelse($employees as $employee)<tr><td>{{ $employee->employee_number }}</td><td>{{ $employee->display_name }}<br>{{ $employee->profile?->name_en }}</td><td>{{ $employee->currentOrganizationAssignment?->department->name ?? 'غير محدد' }}<br>{{ $employee->currentOrganizationAssignment?->jobTitle->name ?? '—' }}</td><td>{{ $employee->currentOrganizationAssignment?->manager?->display_name ?? '—' }}</td><td>{{ $employee->starts_on->toDateString() }} — {{ $employee->ends_on?->toDateString() ?? 'مستمرة' }}</td><td><a class="btn btn-sm btn-light-primary" href="{{ route('hr.employees.show', $employee) }}">عرض الملف</a></td></tr>@empty<tr><td colspan="6">لا يوجد موظفون مطابقون ضمن صلاحياتك.</td></tr>@endforelse
</tbody></table></div>{{ $employees->links() }}
</div></div></div>
@endsection
