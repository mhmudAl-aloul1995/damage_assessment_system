@extends('layouts.app')
@section('title', 'طلب تصحيح الحضور')
@section('pageName', 'طلب تصحيح الحضور')
@section('content')
<div dir="rtl" lang="ar">
    @include('hr::workflow-nav')
    <div class="card"><div class="card-header"><h1 class="card-title">{{ $employee->display_name }} — {{ $row['date'] }}</h1></div><form class="card-body d-flex flex-column gap-5" method="post" action="{{ route('hr.attendance.corrections.store', $employee) }}">@csrf
        <input type="hidden" name="work_date" value="{{ $row['date'] }}"><input type="hidden" name="basis_fingerprint" value="{{ $row['source_fingerprint'] }}"><input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        <div class="alert alert-info">الحالة الحالية: {{ __('hr::attendance.statuses.'.$row['status']) }}. الأوقات تُدخل حسب {{ $row['timezone'] }}. لا يطبق التصحيح إلا بعد مراجعة شخص آخر مخوّل، ويُحفظ السجل الأصلي.</div>
        <div><label for="corrected-status" class="form-label">الحالة المطلوبة</label><select id="corrected-status" name="requested_status" class="form-select"><option value="present">حاضر</option><option value="absent">غائب</option><option value="excused">غياب بعذر</option></select></div>
        @if(count($row['sessions']) > 1)
            @foreach($row['sessions'] as $index => $session)<fieldset class="border rounded p-4"><legend class="fs-6">{{ $session['schedule'] }} — {{ $session['timezone'] }}</legend><input type="hidden" name="sessions[{{ $index }}][assignment_id]" value="{{ $session['assignment_id'] }}"><div class="row g-5"><div class="col-md-6"><label for="session-in-{{ $index }}" class="form-label">حضور الوردية (اختياري)</label><input id="session-in-{{ $index }}" name="sessions[{{ $index }}][check_in]" type="datetime-local" class="form-control" value="{{ $session['check_in'] ? str_replace(' ', 'T', $session['check_in']) : '' }}"></div><div class="col-md-6"><label for="session-out-{{ $index }}" class="form-label">انصراف الوردية (اختياري)</label><input id="session-out-{{ $index }}" name="sessions[{{ $index }}][check_out]" type="datetime-local" class="form-control" value="{{ $session['check_out'] ? str_replace(' ', 'T', $session['check_out']) : '' }}"></div></div></fieldset>@endforeach
        @else
            <div class="row g-5"><div class="col-md-6"><label for="corrected-in" class="form-label">وقت الحضور (اختياري)</label><input id="corrected-in" name="check_in" type="datetime-local" class="form-control" value="{{ old('check_in', $row['check_in'] ? str_replace(' ', 'T', $row['check_in']) : '') }}"></div><div class="col-md-6"><label for="corrected-out" class="form-label">وقت الانصراف (اختياري)</label><input id="corrected-out" name="check_out" type="datetime-local" class="form-control" value="{{ old('check_out', $row['check_out'] ? str_replace(' ', 'T', $row['check_out']) : '') }}"></div></div>
        @endif
        <p class="text-muted">أدخل الوقتين لتصحيح ساعات العمل، أو اتركهما فارغين إذا كان التصحيح للحالة اليومية فقط. عند اختيار الغياب أو العذر يجب ترك الأوقات فارغة.</p>
        <div><label for="correction-reason" class="form-label">سبب التصحيح</label><textarea id="correction-reason" name="reason" class="form-control" minlength="5" maxlength="2000" required>{{ old('reason') }}</textarea></div>
        <button type="submit" class="btn btn-primary align-self-start">تقديم للمراجعة</button>
    </form></div>
</div>
@endsection
