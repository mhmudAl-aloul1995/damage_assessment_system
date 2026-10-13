@extends('layouts.app')
@section('title', 'تقرير أمن الحضور')
@section('pageName', 'تقرير أمن الحضور')
@section('content')
<div dir="rtl" lang="ar">
@include('hr::workflow-nav')
<div class="card"><div class="card-header"><h1 class="card-title">طلبات الحضور الأصلية المرفوضة</h1></div><div class="card-body">
<form method="GET" class="d-flex gap-3 mb-5"><label>الشهر<input type="month" name="month" required class="form-control" value="{{ $filters['month'] ?? now(config('hr.timezone'))->format('Y-m') }}"></label><button class="btn btn-primary">عرض</button></form>
<p class="text-muted">يُجمع الرفض المتكرر للهوية والمسار والحالة خلال دقيقة واحدة لتجنب إغراق السجل. الأرقام تمثل السجلات الأمنية المحفوظة. لا يحتوي التقرير كلمات مرور أو رموز جلسة أو بيانات بصمة أو تواقيع.</p>
<table class="table table-row-dashed"><thead><tr><th>الوقت (UTC)</th><th>الحساب</th><th>المسار</th><th>رمز النتيجة</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->created_at }}</td><td>{{ $log->actor?->name ?? 'طلب غير موثق' }}</td><td>{{ $log->metadata['route'] ?? '—' }}</td><td>{{ $log->metadata['status'] ?? '—' }}</td></tr>@empty<tr><td colspan="4">لا توجد طلبات مرفوضة محفوظة ضمن الفترة.</td></tr>@endforelse</tbody></table>{{ $logs->links() }}
</div></div>
</div>
@endsection
