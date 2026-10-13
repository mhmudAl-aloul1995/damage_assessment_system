@extends('layouts.app')
@section('title', 'الهيكل التنظيمي')
@section('pageName', 'الهيكل التنظيمي')
@section('content')
<div dir="rtl" lang="ar">@include('hr::workflow-nav')
<nav class="d-flex gap-3 mb-5">@foreach(['departments'=>'الأقسام والتسلسل','job-titles'=>'المسميات','offices'=>'مواقع العمل','cost-centers'=>'مراكز التكلفة'] as $key=>$label)<a class="btn btn-light-primary" href="{{ route('hr.organization.index', $key) }}">{{ $label }}</a>@endforeach</nav>
<div class="card mb-5"><div class="card-header"><h1 class="card-title">{{ ['departments'=>'الأقسام والهيكل التنظيمي','job-titles'=>'المسميات الوظيفية','offices'=>'مواقع العمل','cost-centers'=>'مراكز التكلفة'][$kind] }}</h1></div><div class="card-body">
<form method="POST" action="{{ route('hr.organization.store', $kind) }}" class="row g-4">@csrf
<label class="col-md-4">الرمز<input name="code" class="form-control" maxlength="50" required></label><label class="col-md-4">الاسم<input name="name" class="form-control" maxlength="255" required></label><label class="col-md-4">الاسم الإنجليزي<input name="name_en" class="form-control" maxlength="255"></label><input type="hidden" name="active" value="1">
@if($kind === 'departments')<label>القسم الأعلى<select name="parent_id" class="form-select"><option value="">بدون</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select></label>@endif
@if($kind === 'offices')<label class="col-md-6">المنطقة الزمنية<input name="timezone" required class="form-control" value="{{ config('hr.timezone') }}"></label><label class="col-md-6">المدينة<input name="city" class="form-control"></label>@endif
<div><button class="btn btn-primary">إضافة</button></div></form>
</div></div>
@foreach($records as $record)<div class="card mb-4"><form class="card-body row g-4" method="POST" action="{{ route('hr.organization.update', ['kind'=>$kind,'record'=>$record->id]) }}">@csrf @method('PUT')
<label class="col-md-3">الرمز<input name="code" class="form-control" required value="{{ $record->code }}"></label><label class="col-md-3">الاسم<input name="name" class="form-control" required value="{{ $record->name }}"></label><label class="col-md-3">الإنجليزي<input name="name_en" class="form-control" value="{{ $record->name_en }}"></label><label class="col-md-3">الحالة<select name="active" class="form-select"><option value="1" @selected($record->active)>فعال</option><option value="0" @selected(!$record->active)>مؤرشف / غير فعال</option></select></label>
@if($kind === 'departments')<label>القسم الأعلى<select name="parent_id" class="form-select"><option value="">بدون</option>@foreach($departments->where('id','!=',$record->id) as $department)<option value="{{ $department->id }}" @selected($record->parent_id === $department->id)>{{ $department->name }}</option>@endforeach</select></label>@endif
@if($kind === 'offices')<label class="col-md-6">المنطقة الزمنية<input name="timezone" required class="form-control" value="{{ $record->timezone }}"></label><label class="col-md-6">المدينة<input name="city" class="form-control" value="{{ $record->city }}"></label>@endif
<div><button class="btn btn-primary">حفظ</button></div></form></div>@endforeach
{{ $records->links() }}
@if($kind === 'departments')
<div class="card mt-5"><div class="card-header"><h2 class="card-title">نطاق مسؤولي الأقسام</h2></div><div class="card-body">
<form method="POST" action="{{ route('hr.organization.scopes.store') }}" class="row g-4">@csrf<label class="col-md-6">معرف الحساب<input type="number" min="1" name="user_id" required class="form-control"></label><label class="col-md-6">القسم<select name="department_id" required class="form-select">@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select></label>
<label class="col-md-6">تغطية النطاق<select name="include_descendants" class="form-select"><option value="0">هذا القسم فقط</option><option value="1">القسم والأقسام التابعة</option></select></label><label class="col-md-6">من<input type="date" name="starts_on" required class="form-control"></label><label class="col-md-6">إلى (اختياري)<input type="date" name="ends_on" class="form-control"></label><div><button class="btn btn-primary">إضافة نطاق مؤرخ</button></div></form>
<p class="text-muted mt-4">النطاق يحدد الأشخاص؛ يجب أيضاً منح الحساب صلاحية عرض الفريق للحضور أو ملفات الموظفين. لا يمنح الوصول للمستندات أو البيانات الحساسة.</p>
<table class="table table-row-dashed"><thead><tr><th>الحساب</th><th>القسم</th><th>الفترة</th><th>التغطية</th><th>إنهاء</th></tr></thead><tbody>@foreach($scopes as $scope)<tr><td>{{ $scope->user->name }}</td><td>{{ $scope->department->name }}</td><td>{{ $scope->starts_on->toDateString() }} — {{ $scope->latestEnding?->ends_on->toDateString() ?? $scope->ends_on?->toDateString() ?? 'مستمر' }}</td><td>{{ $scope->include_descendants ? 'مع الأقسام التابعة' : 'القسم فقط' }}</td><td><form method="POST" action="{{ route('hr.organization.scopes.end', $scope) }}">@csrf<input type="date" name="ends_on" required class="form-control" aria-label="نهاية نطاق القسم"><input name="reason" required minlength="5" maxlength="2000" class="form-control mt-2" aria-label="سبب إنهاء النطاق"><button class="btn btn-sm btn-light mt-2">تسجيل النهاية</button></form></td></tr>@endforeach</tbody></table>{{ $scopes->links() }}
</div></div>@endif
</div>
@endsection
