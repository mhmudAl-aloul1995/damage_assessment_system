@extends('layouts.app')
@section('title', 'تقارير الحضور')
@section('pageName', 'تقارير الحضور')
@section('content')
<div dir="rtl" lang="ar">
@include('hr::workflow-nav')
<div class="card mb-5"><div class="card-header"><h1 class="card-title">ملخص الحضور ضمن صلاحياتك</h1></div><div class="card-body">
<form method="GET" class="d-flex flex-wrap gap-3 mb-5"><label>الشهر<input type="month" name="month" class="form-control" value="{{ $filters['month'] ?? now(config('hr.timezone'))->format('Y-m') }}" required></label><label>معرّف الموظف (اختياري)<input type="number" min="1" name="employee_id" class="form-control" value="{{ $filters['employee_id'] ?? '' }}"></label><button class="btn btn-primary">عرض</button></form>
<div class="row g-4">
@foreach(['known_days'=>'أيام بساعات معلومة','unknown_days'=>'أيام بساعات غير معلومة','worked_minutes'=>'مجموع دقائق العمل المعلومة','late_minutes'=>'دقائق التأخير','early_minutes'=>'دقائق الانصراف المبكر','conflicts'=>'أيام ذات تعارض'] as $key=>$label)<div class="col-md-4"><div class="border rounded p-4"><span class="text-muted">{{ $label }}</span><strong class="d-block fs-2">{{ $metrics[$key] }}</strong></div></div>@endforeach
</div><p class="text-muted mt-4">الساعات المجهولة لا تدخل في مجموع ساعات العمل. الأرقام للمراجعة الإدارية، ولا تعتمد وقتاً إضافياً أو خصماً من الراتب.</p>
<div class="d-flex flex-wrap gap-3">@foreach($metrics['statuses'] as $status=>$count)<span>{{ __('hr::attendance.statuses.'.$status) }}: {{ $count }}</span>@endforeach</div>
</div></div>
@can('hr.attendance.export')
<div class="card"><div class="card-header"><h2 class="card-title">تقارير Excel في الخلفية</h2></div><div class="card-body">
<form method="POST" action="{{ route('hr.attendance.reports.queue') }}" class="d-flex flex-wrap gap-3 align-items-end">@csrf
<input type="hidden" name="idempotency_key" value="{{ $requestId }}">
<label>الشهر<input type="month" name="month" class="form-control" required value="{{ $filters['month'] ?? now(config('hr.timezone'))->format('Y-m') }}"></label><label>الموظف (اختياري)<input type="number" min="1" name="employee_id" class="form-control" value="{{ $filters['employee_id'] ?? '' }}"></label><button class="btn btn-success">طلب Excel</button></form>
<p class="text-muted mt-4">التقرير ملف ZIP يضم ملفات Excel مقسمة حسب الموظفين وتوقيت إعداد كل جزء. رابط التنزيل يظهر بعد اكتمال جميع الأجزاء، ويُعاد فحص صلاحياتك عند التنزيل.</p>
<table class="table table-row-dashed"><thead><tr><th>تاريخ الطلب</th><th>المدة</th><th>الموظفون</th><th>الحالة</th><th>الأجزاء</th><th>التنزيل</th></tr></thead><tbody>
@forelse($exports as $export)<tr><td>{{ $export->created_at }}</td><td>{{ $export->filters['month'] ?? $export->filters['date'] }}</td><td>{{ count($export->employee_ids) }}</td><td>{{ ['queued'=>'في الانتظار','processing'=>'قيد الإعداد','ready'=>'جاهز','failed'=>'فشل الإعداد','expired'=>'انتهت الصلاحية'][$export->expires_at->isPast() ? 'expired' : $export->state] }}</td><td>{{ count($export->parts ?? []) }} / {{ (int) ceil(count($export->employee_ids) / 100) }}</td><td>@if($export->state === 'ready' && $export->expires_at->isFuture())<a href="{{ route('hr.attendance.reports.download', $export) }}" class="btn btn-sm btn-light-success">تنزيل ZIP</a>@endif</td></tr>@empty<tr><td colspan="6">لا توجد طلبات تقارير.</td></tr>@endforelse
</tbody></table>{{ $exports->links() }}
</div></div>
@endcan
</div>
@endsection
