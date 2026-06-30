@extends('layouts.app')

@section('title', 'متابعات HEKS')
@section('pageName', 'المتابعات وجداول الكميات')

@section('content')
    @include('heks::partials.nav')

    <div class="card card-flush">
        <div class="card-header">
            <h3 class="card-title">المتابعات وجداول الكميات BOQ</h3>
        </div>
        <div class="card-body table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>الكود</th>
                    <th>المستفيد</th>
                    <th>الزيارة</th>
                    <th>المهندس</th>
                    <th>الحالة</th>
                    <th>المنجز ILS</th>
                    <th>%</th>
                    <th>جدول الكميات BOQ</th>
                    <th>التوصيات</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($followUps as $followUp)
                    <tr>
                        <form method="POST" action="{{ route('heks.follow-ups.update', $followUp) }}">
                            @csrf
                            @method('PUT')
                            <td class="fw-bold">{{ $followUp->code }}</td>
                            <td>{{ $followUp->beneficiary?->name ?? '-' }}</td>
                            <td>
                                <input name="visit_number" class="form-control mb-2" value="{{ $followUp->visit_number }}">
                                <input name="visit_date" class="form-control" value="{{ $followUp->visit_date?->format('Y-m-d') }}">
                            </td>
                            <td><input name="engineer_name" class="form-control" value="{{ $followUp->engineer_name }}"></td>
                            <td>
                                <input name="working_condition" class="form-control mb-2" value="{{ $followUp->working_condition }}">
                                <textarea name="other_condition" class="form-control" rows="2">{{ $followUp->other_condition }}</textarea>
                            </td>
                            <td><input name="completed_amount_ils" class="form-control" value="{{ $followUp->completed_amount_ils }}"></td>
                            <td><input name="completion_percentage" class="form-control" value="{{ $followUp->completion_percentage }}"></td>
                            <td class="min-w-200px">
                                <input name="boq_filename" class="form-control mb-2" value="{{ $followUp->boq_filename }}" placeholder="اسم ملف BOQ">
                                <input name="boq_url" class="form-control mb-2" value="{{ $followUp->boq_url }}" placeholder="رابط BOQ">
                                @if ($followUp->boq_url)
                                    <a class="btn btn-sm btn-light" href="{{ $followUp->boq_url }}" target="_blank" rel="noopener">فتح BOQ</a>
                                @endif
                            </td>
                            <td><textarea name="engineer_recommendations" class="form-control" rows="2">{{ $followUp->engineer_recommendations }}</textarea></td>
                            <td><button class="btn btn-sm btn-light-primary">حفظ</button></td>
                        </form>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted">لا توجد متابعات بعد.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $followUps->links() }}
        </div>
    </div>
@endsection
