@extends('layouts.app')
@section('title', 'أجهزة الحضور')
@section('pageName', 'أجهزة الحضور')
@section('content')
<div dir="rtl" lang="ar">
    @include('hr::workflow-nav')
    <div class="alert alert-info">اعتماد الجهاز لا يفعّل التسجيل الإنتاجي قبل التحقق من البوابة الداخلية. يتم التحقق من امتلاك المفتاح؛ حالة شهادة حماية المفتاح تُعرض مستقلة ولا تُفترض حماية عتادية.</div>
    <div class="card"><div class="card-header"><h1 class="card-title">تسجيل واعتماد أجهزة الموظفين</h1></div><div class="card-body table-responsive">
    <table class="table table-row-dashed"><thead><tr><th>الموظف</th><th>الجهاز</th><th>الحالة</th><th>شهادة المفتاح</th><th>تاريخ القرارات</th><th>إجراء</th></tr></thead><tbody>
    @forelse($credentials as $credential)
    <tr><td>{{ $credential->device->employee->display_name }}</td><td>{{ $credential->device->label }}<br><small dir="ltr">{{ $credential->device->device_uuid }}</small><details><summary>بصمة المفتاح العام</summary><code>{{ $credential->key_fingerprint }}</code></details></td>
    <td>{{ ['pending'=>'بانتظار HR', 'approved'=>'معتمد', 'rejected'=>'مرفوض', 'revoked'=>'ملغى'][$credential->status()] }}</td><td>{{ $credential->attestation_status === 'unverified' ? 'غير متحققة' : $credential->attestation_status }}</td>
    <td>@foreach($credential->decisions as $decision)<div>{{ ['approved'=>'اعتماد', 'rejected'=>'رفض', 'revoked'=>'إلغاء'][$decision->decision] }} — {{ $decision->actor->name }} — {{ $decision->created_at->utc() }} (UTC)<p>{{ $decision->reason }}</p></div>@endforeach</td>
    <td>
    @if($credential->device->employee->user_id !== auth()->id())
        @if(($credential->status() === 'pending' && auth()->user()->can('hr.devices.approve')) || ($credential->status() !== 'revoked' && auth()->user()->can('hr.devices.revoke')))
        <form method="POST" action="{{ route('hr.attendance.devices.decide', $credential) }}">@csrf
            <select name="decision" class="form-select" aria-label="قرار الجهاز">
                @if($credential->status() === 'pending' && auth()->user()->can('hr.devices.approve'))<option value="approved">اعتماد</option><option value="rejected">رفض</option>@endif
                @if(auth()->user()->can('hr.devices.revoke'))<option value="revoked">إلغاء اعتماد / جهاز مفقود</option>@endif
            </select><textarea name="reason" class="form-control mt-2" required minlength="5" maxlength="2000" aria-label="سبب القرار"></textarea><button class="btn btn-primary btn-sm mt-2">حفظ القرار</button>
        </form>
        @endif
    @else<span>لا يمكن اعتماد جهازك بنفسك؛ بلّغ عن فقدانه من شاشة الحضور في التطبيق.</span>@endif
    </td></tr>
    @empty<tr><td colspan="6">لا توجد أجهزة مسجلة ضمن صلاحياتك.</td></tr>@endforelse
    </tbody></table>{{ $credentials->links() }}</div></div>
</div>
@endsection
