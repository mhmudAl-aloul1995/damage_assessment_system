@extends('layouts.app')
@section('title', 'سجل تدقيق الحضور')
@section('pageName', 'سجل تدقيق الحضور')
@section('content')
<div dir="rtl" lang="ar">
    @include('hr::workflow-nav')
    <div class="card"><div class="card-header"><h1 class="card-title">تاريخ إجراءات الحضور</h1></div><div class="card-body table-responsive"><table class="table table-row-dashed"><thead><tr><th>الوقت (UTC)</th><th>المستخدم</th><th>الإجراء</th><th>نوع السجل</th><th>معرّف السجل</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->created_at }}</td><td>{{ $log->actor?->name ?? 'مهمة نظام' }}</td><td>{{ __('hr::attendance.actions.'.$log->action) }}</td><td>{{ $log->subject_type }}</td><td>{{ $log->subject_id }}</td></tr>@empty<tr><td colspan="5">لا توجد إجراءات مسجلة بعد.</td></tr>@endforelse</tbody></table>{{ $logs->links() }}</div></div>
</div>
@endsection
