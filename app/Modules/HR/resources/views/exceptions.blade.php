@extends('layouts.app')
@section('title', 'استثناءات الحضور والتسوية')
@section('pageName', 'استثناءات الحضور والتسوية')
@section('content')
<div dir="rtl" lang="ar">
    @include('hr::workflow-nav')
    @can('hr.attendance.reconcile')<div class="card mb-6"><div class="card-header"><h2 class="card-title">تسوية الملخصات مع مصادر الحضور</h2></div><form class="card-body d-flex flex-wrap align-items-end gap-4" method="post" action="{{ route('hr.attendance.reconcile') }}">@csrf
        <div><label for="reconcile-from" class="form-label">من</label><input id="reconcile-from" name="from" type="date" class="form-control" value="{{ now(config('hr.timezone'))->toDateString() }}" required></div>
        <div><label for="reconcile-to" class="form-label">إلى</label><input id="reconcile-to" name="to" type="date" class="form-control" value="{{ now(config('hr.timezone'))->toDateString() }}" max="{{ now(config('hr.timezone'))->toDateString() }}" required></div>
        <div><label for="reconcile-employee" class="form-label">معرّف الموظف (اختياري)</label><input id="reconcile-employee" name="employee_id" type="number" min="1" class="form-control"></div>
        <button class="btn btn-primary" type="submit">بدء التسوية</button><p class="text-muted mb-0 w-100">حد أقصى 31 يوماً في كل مهمة. تُحفظ الملخصات والاستثناءات دون تعديل سجلات الحضور أو الاستيراد الأصلية.</p>
    </form></div>@endcan
    <div class="card"><div class="card-header"><h2 class="card-title">استثناءات تحتاج مراجعة</h2></div><div class="card-body table-responsive"><table class="table table-row-dashed"><thead><tr><th>الموظف</th><th>اليوم</th><th>الاستثناء</th><th>المصدر</th><th>آخر تسوية (UTC)</th><th>الإجراء</th></tr></thead><tbody>@forelse($exceptions as $exception)<tr>
        <td>{{ $exception->summary->employee->display_name }}</td><td>{{ $exception->summary->work_date->toDateString() }}</td><td>{{ __('hr::attendance.statuses.'.$exception->kind) }}</td><td>{{ $exception->summary->payload['source'] }}</td><td>{{ $exception->summary->reconciled_at }}</td><td>@can('requestCorrection', $exception->summary->employee)<a href="{{ route('hr.attendance.corrections.create', ['employee' => $exception->summary->employee_id, 'date' => $exception->summary->work_date->toDateString()]) }}" class="btn btn-sm btn-light-primary">طلب تصحيح</a>@endcan</td>
    </tr>@empty<tr><td colspan="6" class="text-center py-10">لا توجد استثناءات مفتوحة ضمن نطاق صلاحيتك. تعرض هذه الصفحة نتائج آخر تسوية مكتملة.</td></tr>@endforelse</tbody></table>{{ $exceptions->links() }}</div></div>
</div>
@endsection
