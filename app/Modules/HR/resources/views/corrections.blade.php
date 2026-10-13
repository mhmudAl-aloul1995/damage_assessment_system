@extends('layouts.app')
@section('title', 'تصحيحات الحضور والموافقات')
@section('pageName', 'تصحيحات الحضور والموافقات')
@section('content')
<div dir="rtl" lang="ar">
    @include('hr::workflow-nav')
    <div class="card mb-6"><form class="card-body d-flex flex-wrap align-items-end gap-4" method="get" action="{{ route('hr.attendance.corrections') }}">
        <div><label for="correction-state" class="form-label">حالة الطلب</label><select id="correction-state" name="state" class="form-select">@foreach(['pending' => 'بانتظار المراجعة', 'approved' => 'معتمد', 'rejected' => 'مرفوض', 'all' => 'جميع الحالات'] as $value => $label)<option value="{{ $value }}" @selected($state === $value)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="correction-filter-date" class="form-label">اليوم (اختياري)</label><input id="correction-filter-date" name="date" type="date" class="form-control" value="{{ $filters['date'] ?? '' }}"></div>
        <div><label for="correction-filter-employee" class="form-label">معرّف الموظف (اختياري)</label><input id="correction-filter-employee" name="employee_id" type="number" min="1" class="form-control" value="{{ $filters['employee_id'] ?? '' }}"></div>
        <button class="btn btn-primary" type="submit">عرض الطلبات</button>
    </form></div>
    @forelse($adjustments as $adjustment)
        <article class="card mb-5"><div class="card-header align-items-center"><h2 class="card-title">#{{ $adjustment->id }} — {{ $adjustment->employee->display_name }} — {{ $adjustment->work_date->toDateString() }}</h2><span class="badge badge-light-primary">{{ $adjustment->decision ? ($adjustment->decision->decision === 'approved' ? 'معتمد' : 'مرفوض') : 'بانتظار المراجعة' }}</span></div><div class="card-body">
            <p><strong>الحالة المطلوبة:</strong> {{ ['present' => 'حاضر', 'absent' => 'غائب', 'excused' => 'بعذر'][$adjustment->requested_status] }} · <strong>مقدم الطلب:</strong> {{ $adjustment->requester->name }}</p>
            <p><strong>الأوقات:</strong> {{ $adjustment->check_in_at ? \Carbon\CarbonImmutable::parse($adjustment->check_in_at, 'UTC')->setTimezone($adjustment->timezone)->format('Y-m-d H:i') : 'غير معلومة' }} — {{ $adjustment->check_out_at ? \Carbon\CarbonImmutable::parse($adjustment->check_out_at, 'UTC')->setTimezone($adjustment->timezone)->format('Y-m-d H:i') : 'غير معلومة' }} ({{ $adjustment->timezone }})</p>
            <p><strong>السبب:</strong> {{ $adjustment->reason }}</p>
            @foreach($adjustment->sessions ?? [] as $session)<p>الوردية #{{ $session['assignment_id'] }}: {{ \Carbon\CarbonImmutable::parse($session['check_in_at'], 'UTC')->setTimezone($adjustment->timezone)->format('Y-m-d H:i') }} — {{ \Carbon\CarbonImmutable::parse($session['check_out_at'], 'UTC')->setTimezone($adjustment->timezone)->format('Y-m-d H:i') }} ({{ $adjustment->timezone }})</p>@endforeach
            @if($adjustment->decision)<div class="alert alert-light mb-0"><strong>المراجع:</strong> {{ $adjustment->decision->reviewer->name }} · {{ $adjustment->decision->note }}</div>
            @else @can('decide', $adjustment)<form method="post" action="{{ route('hr.attendance.corrections.decide', $adjustment) }}" class="d-flex flex-wrap align-items-end gap-4">@csrf
                <div><label for="decision-{{ $adjustment->id }}" class="form-label">القرار</label><select id="decision-{{ $adjustment->id }}" name="decision" class="form-select"><option value="approved">موافقة</option><option value="rejected">رفض</option></select></div>
                <div class="flex-grow-1"><label for="decision-note-{{ $adjustment->id }}" class="form-label">ملاحظة المراجع</label><input id="decision-note-{{ $adjustment->id }}" name="note" class="form-control" minlength="5" maxlength="2000" required></div>
                <button type="submit" class="btn btn-primary">حفظ القرار</button>
            </form>@else<p class="text-muted mb-0">تحتاج المراجعة إلى مستخدم آخر مخوّل؛ الموافقة الذاتية غير مسموحة.</p>@endcan @endif
        </div></article>
    @empty<div class="card"><div class="card-body text-center py-10">لا توجد طلبات مطابقة. يمكنك تقديم طلب من صف اليوم في تقرير الحضور.</div></div>@endforelse
    {{ $adjustments->links() }}
</div>
@endsection
