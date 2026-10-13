@extends('layouts.app')
@section('title', 'العقود وتقارير الانتهاء')
@section('pageName', 'العقود وتقارير الانتهاء')
@section('content')
<div dir="rtl" lang="ar">@include('hr::workflow-nav')
<div class="d-flex gap-3 mb-4">@can('hr.contracts.configure')<a class="btn btn-light-primary" href="{{ route('hr.contracts.types') }}">أنواع العقود والتنبيهات</a>@endcan @can('hr.contracts.export')<a class="btn btn-light-success" href="{{ route('hr.contracts.export', request()->query()) }}">تصدير Excel</a>@endcan</div>
<div class="row g-3 mb-4">@foreach($counts as $status=>$count)<div class="col"><div class="card"><div class="card-body">{{ __('hr::contracts.statuses.'.$status) }} <strong>{{ $count }}</strong></div></div></div>@endforeach</div>
<div class="card mb-5"><div class="card-body"><form class="row g-3" method="GET" action="{{ route('hr.contracts.index') }}">
<label class="col-md-3">معرف ملف الموظف<input name="employee_id" type="number" min="1" class="form-control" value="{{ $filters['employee_id'] ?? '' }}"></label>
<label class="col-md-3">نوع العقد<select name="contract_type_id" class="form-select"><option value="">الكل</option>@foreach($types as $type)<option value="{{ $type->id }}" @selected(($filters['contract_type_id']??null)==$type->id)>{{ $type->name }}</option>@endforeach</select></label>
<label class="col-md-3">الحالة<select name="status" class="form-select"><option value="">الكل</option>@foreach(['active','scheduled','expired','terminated','pending','rejected'] as $status)<option value="{{ $status }}" @selected(($filters['status']??null)==$status)>{{ __('hr::contracts.statuses.'.$status) }}</option>@endforeach</select></label>
<label class="col-md-3">الحالة في تاريخ<input type="date" name="date" class="form-control" value="{{ $filters['date'] ?? '' }}"></label>
<label class="col-md-4">تنتهي قبل أو في<input type="date" name="expires_before" class="form-control" value="{{ $filters['expires_before'] ?? '' }}"></label><div><button class="btn btn-primary">تصفية</button></div>
</form></div></div>
<div class="card mb-5"><div class="card-body table-responsive"><table class="table table-row-dashed"><thead><tr><th>الموظف</th><th>العقد</th><th>النوع</th><th>الحالة</th><th>الفترة</th><th>المراجعة</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td>{{ $row['contract']->employee->display_name }}<br>{{ $row['contract']->employee->employee_number }}</td><td><a href="{{ route('hr.contracts.show', $row['contract']) }}">{{ $row['contract']->number }}</a></td><td>{{ $row['revision']?->type_snapshot['name'] ?? '—' }}</td><td>{{ __('hr::contracts.statuses.'.$row['status']) }}</td><td>{{ $row['starts_on'] ?? 'غير معتمد' }} — {{ $row['ends_on'] ?? 'بدون نهاية محددة' }}</td><td>{{ $row['pending'] ? 'مقترح بانتظار القرار' : 'لا يوجد مقترح معلق' }}</td></tr>@empty<tr><td colspan="6">لا توجد عقود في النطاق.</td></tr>@endforelse
</tbody></table>{{ $rows->links() }}</div></div>
@can('hr.contracts.manage')<div class="card"><div class="card-header"><h2 class="card-title">مقترح عقد جديد</h2></div><div class="card-body">
<form method="POST" action="{{ route('hr.contracts.store') }}" class="row g-4">@csrf
<input type="hidden" name="request_key" value="{{ old('request_key', (string) Illuminate\Support\Str::uuid()) }}">
<label class="col-md-6">معرف ملف الموظف<input name="employee_id" type="number" min="1" list="contract-employees" class="form-control" required value="{{ old('employee_id') }}"><datalist id="contract-employees">@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->employee_number }} — {{ $employee->display_name }}</option>@endforeach</datalist></label>
<label class="col-md-6">رقم العقد<input name="number" required maxlength="80" class="form-control" value="{{ old('number') }}"></label>
<label>نوع الارتباط<select name="primary" class="form-select"><option value="1">عقد أساسي</option><option value="0">عقد إضافي / مشروع</option></select></label>
@include('hr::contract-fields', ['previous'=>null])
<div><button class="btn btn-primary">إرسال مقترح للمراجعة</button></div>
</form></div></div>@endcan
</div>
@endsection
