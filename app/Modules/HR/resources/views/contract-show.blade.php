@extends('layouts.app')
@section('title', 'تفاصيل عقد العمل')
@section('pageName', 'تفاصيل عقد العمل')
@section('content')
<div dir="rtl" lang="ar">@include('hr::workflow-nav')
<div class="card mb-5"><div class="card-body"><h1>{{ $contract->number }} — {{ $contract->employee->display_name }}</h1>
<p>{{ __('hr::contracts.statuses.'.$row['status']) }} · {{ $contract->primary ? 'أساسي' : 'إضافي / مشروع' }} · {{ $row['starts_on'] ?? 'غير معتمد' }} — {{ $row['ends_on'] ?? 'بدون نهاية محددة' }}</p>
@can('viewProfile',$contract->employee)<a href="{{ route('hr.employees.show',$contract->employee) }}">ملف الموظف والمستندات</a>@endcan
</div></div>
@foreach($contract->revisions as $revision)
<div class="card mb-4"><div class="card-header"><h2 class="card-title">الإصدار #{{ $revision->id }} — {{ __('hr::contracts.kinds.'.$revision->kind) }}</h2></div><div class="card-body">
<p>{{ $revision->type_snapshot['name'] }} · {{ $revision->starts_on->toDateString() }} — {{ $revision->ends_on?->toDateString() ?? 'بدون نهاية' }} · النفاذ: {{ $revision->effective_on->toDateString() }}</p>
<p>المقترح: {{ $revision->author->name }} · القرار: {{ $revision->decision ? __('hr::contracts.decisions.'.$revision->decision->decision).' — '.$revision->decision->reviewer->name : 'بانتظار القرار' }}</p>
@if($sensitive || $revision->created_by === auth()->id())<p>{{ $revision->reason }}</p>@endif
@if($sensitive)<p>الراتب: {{ $revision->financial_terms['salary'] ?? 'غير محدد' }} {{ $revision->financial_terms['currency'] ?? '' }} · {{ $revision->financial_terms['basis'] ?? '' }}</p><p>{{ $revision->financial_terms['benefits'] ?? '' }}</p>@if($revision->decision)<p>سبب القرار: {{ $revision->decision->reason }}</p>@endif @endif
@if($revision->document)@can('viewDocuments',$contract->employee)<a class="btn btn-light" href="{{ route('hr.employees.documents.download',$revision->document) }}">تنزيل {{ $revision->document->original_name }}</a>@else<p>يوجد مستند خاص؛ التنزيل يتطلب صلاحية المستندات.</p>@endcan @else<p class="text-muted">لم يرفق مستند بهذا الإصدار.</p>@endif
@if(!$revision->decision && auth()->user()->can('approve',$contract) && $revision->created_by!==auth()->id())
<form method="POST" action="{{ route('hr.contracts.decide',$revision) }}" class="row g-3 mt-4">@csrf<label class="col-md-4">القرار<select name="decision" class="form-select"><option value="approved">اعتماد</option><option value="rejected">رفض</option></select></label><label class="col-md-8">السبب<textarea name="reason" required minlength="5" maxlength="5000" class="form-control"></textarea></label><div><button class="btn btn-primary">تسجيل قرار نهائي</button></div></form>
@endif
</div></div>
@endforeach
@if(auth()->user()->can('manage',$contract) && !$row['pending'] && $latestApproved?->kind!=='termination')
<div class="card"><div class="card-header"><h2 class="card-title">إصدار مقترح جديد</h2></div><div class="card-body">
<form method="POST" action="{{ route('hr.contracts.propose',$contract) }}" class="row g-4">@csrf
<input type="hidden" name="request_key" value="{{ old('request_key',(string)Illuminate\Support\Str::uuid()) }}">
<label class="col-md-6">المسار<select name="kind" class="form-select">@foreach($latestApproved?['amendment','renewal','termination']:['initial'] as $kind)<option value="{{ $kind }}">{{ __('hr::contracts.kinds.'.$kind) }}</option>@endforeach</select></label>
<label class="col-md-6">نفاذ التعديل (للتعديل فقط)<input type="date" name="effective_on" class="form-control" value="{{ old('effective_on') }}"></label>
@include('hr::contract-fields',['previous'=>$latestApproved])
<p class="text-muted">التجديد يبدأ بعد نهاية الفترة السابقة. التعديل يحفظ بداية الفترة ويحدد تاريخ النفاذ. الإنهاء يحفظ البداية ويحدد آخر يوم سريان، ولا ينهي حساب الموظف تلقائياً.</p>
<div><button class="btn btn-primary">إرسال مقترح</button></div>
</form></div></div>@endif
</div>
@endsection
