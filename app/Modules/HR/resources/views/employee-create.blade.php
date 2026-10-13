@extends('layouts.app')
@section('title', 'إضافة موظف')
@section('pageName', 'إضافة موظف')
@section('content')
<div dir="rtl" lang="ar">@include('hr::workflow-nav')
<div class="card"><div class="card-header"><h1 class="card-title">ربط هوية الموظف</h1></div><form method="POST" action="{{ route('hr.employees.store') }}" class="card-body">@csrf
<div class="row g-5">
<label class="col-md-6">الرقم الوظيفي<input name="employee_number" class="form-control" maxlength="50" required value="{{ old('employee_number') }}"></label>
<label class="col-md-6">الاسم<input name="display_name" class="form-control" maxlength="255" required value="{{ old('display_name') }}"></label>
<label class="col-md-6">معرف الحساب الموجود (اختياري)<input type="number" min="1" name="user_id" class="form-control" value="{{ old('user_id') }}"><small>يُترك فارغاً لمن لا يملك حساباً. تحقق من الحساب قبل الربط.</small></label>
<label class="col-md-6">معرف حساب المشرف الأولي (اختياري)<input type="number" min="1" name="supervisor_user_id" class="form-control" value="{{ old('supervisor_user_id') }}"></label>
<label class="col-md-6">بدء العمل<input type="date" name="starts_on" required class="form-control" value="{{ old('starts_on') }}"></label>
<label class="col-md-6">نهاية العمل المعروفة (اختياري)<input type="date" name="ends_on" class="form-control" value="{{ old('ends_on') }}"></label>
</div><button class="btn btn-primary mt-5">إنشاء هوية الموظف</button>
</form></div></div>
@endsection
