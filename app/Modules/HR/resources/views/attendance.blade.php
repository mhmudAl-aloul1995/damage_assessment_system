@extends('layouts.app')
@section('title', 'الموارد البشرية — الحضور والانصراف')
@section('pageName', 'الحضور والانصراف')
@section('content')
<div dir="rtl" lang="ar" class="d-flex flex-column gap-6">
    @include('hr::workflow-nav')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><h1 class="fs-2 fw-bold">متابعة الحضور والانصراف</h1><p class="text-muted mb-0">{{ $dates[0] }} — {{ $dates[1] }} · {{ $employees->total() }} موظف ضمن نطاق الصلاحية</p></div>
        @can('hr.attendance.setup')<a class="btn btn-light-primary" href="{{ route('hr.attendance.setup') }}">إعداد الحضور والورديات</a>@endcan
    </div>
    <div class="alert alert-warning mb-0" role="status">تسجيل الحضور الآمن عبر الهاتف غير مفعّل حتى اعتماد الأجهزة والتحقق من بوابة الشبكة الداخلية وHTTPS.</div>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="card"><div class="card-body">
        <div class="row g-5">
            <form class="col-lg-6 d-flex flex-wrap align-items-end gap-3" method="get" action="{{ route('hr.attendance.index') }}">
                <div><label for="report-date" class="form-label">التقرير اليومي</label><input id="report-date" class="form-control" type="date" name="date" value="{{ $filters['date'] ?? $dates[0] }}" required></div>
                <div><label for="daily-employee" class="form-label">معرّف الموظف (اختياري)</label><input id="daily-employee" class="form-control" type="number" min="1" name="employee_id" value="{{ $filters['employee_id'] ?? '' }}"></div>
                <button class="btn btn-primary" type="submit">عرض اليوم</button>
            </form>
            <form class="col-lg-6 d-flex flex-wrap align-items-end gap-3" method="get" action="{{ route('hr.attendance.index') }}">
                <div><label for="report-month" class="form-label">التقرير الشهري</label><input id="report-month" class="form-control" type="month" name="month" value="{{ $filters['month'] ?? substr($dates[0], 0, 7) }}" required></div>
                <div><label for="monthly-employee" class="form-label">معرّف الموظف (اختياري)</label><input id="monthly-employee" class="form-control" type="number" min="1" name="employee_id" value="{{ $filters['employee_id'] ?? '' }}"></div>
                <button class="btn btn-primary" type="submit">عرض الشهر</button>
            </form>
        </div>
    </div></div>
    <div><h2 class="fs-5">ملخص جميع الموظفين ضمن نطاق العرض</h2><div class="d-flex flex-wrap gap-3">
        @forelse($totals as $status => $count)
            <div class="card border"><div class="card-body py-4 px-6"><div class="text-muted">{{ __('hr::attendance.statuses.'.$status) }}</div><strong class="fs-2">{{ $count }}</strong></div></div>
        @empty<p class="text-muted">لا توجد بيانات ضمن نطاق العرض.</p>@endforelse
    </div></div>
    <div class="card"><div class="card-header align-items-center"><h2 class="card-title">سجل الحضور</h2>
        @can('hr.attendance.export')<a class="btn btn-sm btn-light-success" href="{{ route('hr.attendance.export', array_filter($filters, fn ($value, $key) => $key !== 'page', ARRAY_FILTER_USE_BOTH)) }}">تصدير Excel للنطاق المحدد</a>@endcan
    </div><div class="card-body">
        <p class="text-muted">السجل القديم يثبت الحالة اليومية فقط. الرمز — يعني أن الوقت غير معلوم. الغياب المحتمل يحتاج مراجعة الإجازات والاستثناءات، والوقت الزائد لا يعني اعتماد عمل إضافي.</p>
        <div class="table-responsive"><table class="table table-row-dashed align-middle gy-4">
            <thead><tr class="fw-bold text-muted"><th>الموظف</th><th>اليوم / الوردية</th><th>أول حضور</th><th>آخر انصراف</th><th>دقائق العمل</th><th>تأخير</th><th>انصراف مبكر</th><th>الحالة / المصدر</th></tr></thead>
            <tbody>@forelse($rows as $row)<tr>
                <td><strong>{{ $row['name'] }}</strong><div class="text-muted">{{ $row['employee_number'] }} · #{{ $row['employee_id'] }}</div>@if(auth()->user()->can('requestCorrection', $employees->firstWhere('id', $row['employee_id'])))<a class="btn btn-sm btn-light mt-2" href="{{ route('hr.attendance.corrections.create', ['employee' => $row['employee_id'], 'date' => $row['date']]) }}">طلب تصحيح</a>@endif</td>
                <td>{{ $row['date'] }}<div class="text-muted">{{ $row['schedule'] ?? '—' }} · {{ $row['timezone'] }}</div>@if(count($row['sessions']) > 1)<details class="mt-2"><summary>تفاصيل الورديات</summary>@foreach($row['sessions'] as $session)<div class="py-2">{{ $session['schedule'] }} · {{ $session['check_in'] ?? '—' }} / {{ $session['check_out'] ?? '—' }} · {{ $session['worked_minutes'] ?? '—' }} دقيقة</div>@endforeach</details>@endif</td>
                <td dir="ltr">{{ $row['check_in'] ?? '—' }}</td><td dir="ltr">{{ $row['check_out'] ?? '—' }}</td>
                <td>{{ $row['worked_minutes'] ?? '—' }}</td><td>{{ $row['late_minutes'] ?? '—' }}</td><td>{{ $row['early_minutes'] ?? '—' }}</td>
                <td><span class="badge {{ in_array($row['status'], ['conflict', 'missing_checkout', 'missing_checkin', 'missing_session', 'absence_candidate']) ? 'badge-light-warning' : 'badge-light-primary' }}">{{ __('hr::attendance.statuses.'.$row['status']) }}</span><div class="text-muted mt-1">{{ $row['source'] }}</div></td>
            </tr>@empty<tr><td colspan="8" class="text-center py-10">لا توجد هويات حضور مطابقة. تُنشأ هوية الحضور من الإعدادات؛ تبقى سجلات الاستيراد متاحة في نظام الحضور الحالي.</td></tr>@endforelse</tbody>
        </table></div>
        {{ $employees->links() }}
    </div></div>
</div>
@endsection
