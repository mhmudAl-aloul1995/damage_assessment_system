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
                    @php
                        $boqAttachment = $followUp->beneficiary?->attachments
                            ->first(fn ($attachment) => $attachment->attachment_type === 'follow_up_boq' && $attachment->source === "follow-up:{$followUp->id}");
                        $boqImportSummary = $boqAttachment?->raw_data['boq_import_summary'] ?? null;
                        $boqImported = is_array($boqImportSummary) && ($boqImportSummary['imported'] ?? false) && (($boqImportSummary['imported_rows'] ?? 0) > 0);
                        $boqImportFailed = is_array($boqImportSummary) && ($boqImportSummary['imported'] ?? true) === false;
                    @endphp
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
                                @if ($followUp->beneficiary)
                                    <div class="d-flex flex-wrap gap-2">
                                        <a class="btn btn-sm btn-light-primary" href="{{ route('heks.beneficiaries.pricing', $followUp->beneficiary) }}">فتح جدول الكميات</a>
                                        @if ($followUp->boq_url)
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">خيارات الملف</button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="{{ $followUp->boq_url }}" download>تحميل Excel الأصلي</a>
                                                    <a class="dropdown-item" href="{{ $followUp->boq_url }}" target="_blank" rel="noopener">فتح رابط KoBo</a>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @elseif ($followUp->boq_url)
                                    <a class="btn btn-sm btn-light" href="{{ $followUp->boq_url }}" target="_blank" rel="noopener">فتح رابط KoBo</a>
                                @endif
                                @if ($boqImported)
                                    <div class="mt-2">
                                        <span class="badge badge-light-success">تم استيراد البنود</span>
                                        <span class="text-muted small">{{ $boqImportSummary['imported_rows'] }} بند</span>
                                    </div>
                                @elseif ($boqImportFailed)
                                    <div class="mt-2">
                                        <span class="badge badge-light-danger">فشل الاستيراد</span>
                                    </div>
                                @elseif ($boqAttachment)
                                    <div class="mt-2">
                                        <span class="badge badge-light-warning">ملف محفوظ فقط</span>
                                    </div>
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
            <div class="heks-pagination">
                {{ $followUps->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
