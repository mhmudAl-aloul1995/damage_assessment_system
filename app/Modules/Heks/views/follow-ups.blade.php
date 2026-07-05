@extends('layouts.app')

@section('title', 'متابعات HEKS')
@section('pageName', 'المتابعات وجداول الكميات')

@section('content')
    @include('heks::partials.nav')

    <style>
        .heks-follow-ups-page .follow-up-kpi { border: 1px solid var(--bs-gray-200); border-radius: .75rem; height: 100%; padding: 1rem; }
        .heks-follow-ups-page .follow-up-table { min-width: 1050px; }
        .heks-follow-ups-page .follow-up-table td { vertical-align: middle; }
        .heks-follow-ups-page .follow-up-file-name { max-width: 210px; }
        .heks-follow-ups-page .follow-up-recommendation { max-width: 230px; }
        .heks-follow-ups-page .follow-up-actions { min-width: 190px; }
        .heks-follow-ups-page .follow-up-boq-preview { background: #f8fbff; border: 1px solid var(--bs-gray-200); border-radius: .75rem; padding: 1rem; }
        .heks-follow-ups-page .follow-up-boq-preview table { margin-bottom: 0; }
        .heks-follow-ups-page .follow-up-boq-preview .boq-preview-description { min-width: 280px; }
    </style>

    <div class="heks-follow-ups-page">
        <div class="row g-4 mb-6">
            @foreach ([
                ['label' => 'إجمالي المتابعات', 'value' => number_format($followUpSummary['total']), 'tone' => 'primary'],
                ['label' => 'لديها BOQ', 'value' => number_format($followUpSummary['with_boq']), 'tone' => 'info'],
                ['label' => 'تم استيراد البنود', 'value' => number_format($followUpSummary['imported_boq']), 'tone' => 'success'],
                ['label' => 'فشل استيراد BOQ', 'value' => number_format($followUpSummary['failed_boq']), 'tone' => 'danger'],
                ['label' => 'إجمالي المنجز ILS', 'value' => number_format((float) $followUpSummary['completed_amount'], 2), 'tone' => 'warning'],
            ] as $card)
                <div class="col-xl col-md-4 col-6">
                    <div class="follow-up-kpi">
                        <div class="text-muted fs-7">{{ $card['label'] }}</div>
                        <div class="fs-2 fw-bold text-{{ $card['tone'] }}">{{ $card['value'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <form method="GET" action="{{ route('heks.follow-ups') }}" class="card card-flush mb-6">
            <div class="card-header">
                <div>
                    <h3 class="card-title">فلاتر المتابعات</h3>
                    <div class="text-muted">اعرض المتابعات حسب المستفيد أو المهندس أو حالة ملف BOQ.</div>
                </div>
                <div class="card-toolbar d-flex gap-2">
                    <a href="{{ route('heks.follow-ups') }}" class="btn btn-sm btn-light">مسح</a>
                    <button class="btn btn-sm btn-primary">تطبيق</button>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-xl-3 col-md-6">
                        <label class="form-label">بحث</label>
                        <input name="q" value="{{ request('q') }}" class="form-control" placeholder="الكود، الاسم، الهوية">
                    </div>
                    <div class="col-xl-2 col-md-6">
                        <label class="form-label">المهندس</label>
                        <select name="engineer" class="form-select">
                            <option value="">كل المهندسين</option>
                            @foreach ($engineers as $engineer)
                                <option value="{{ $engineer }}" @selected(request('engineer') === $engineer)>{{ $engineer }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-6">
                        <label class="form-label">رقم الزيارة</label>
                        <select name="visit_number" class="form-select">
                            <option value="">كل الزيارات</option>
                            @foreach ($visitNumbers as $visitNumber)
                                <option value="{{ $visitNumber }}" @selected(request('visit_number') === $visitNumber)>{{ $visitNumber }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-6">
                        <label class="form-label">حالة BOQ</label>
                        <select name="boq_status" class="form-select">
                            <option value="">كل الحالات</option>
                            <option value="with_boq" @selected(request('boq_status') === 'with_boq')>لديها ملف BOQ</option>
                            <option value="without_boq" @selected(request('boq_status') === 'without_boq')>بدون BOQ</option>
                        </select>
                    </div>
                    <div class="col-xl-1 col-md-6">
                        <label class="form-label">من</label>
                        <input type="date" name="visit_from" value="{{ request('visit_from') }}" class="form-control">
                    </div>
                    <div class="col-xl-1 col-md-6">
                        <label class="form-label">إلى</label>
                        <input type="date" name="visit_to" value="{{ request('visit_to') }}" class="form-control">
                    </div>
                </div>
            </div>
        </form>

        <div class="card card-flush">
            <div class="card-header">
                <div>
                    <h3 class="card-title">المتابعات وجدول الكميات BOQ</h3>
                    <div class="text-muted">الصف يعرض المهم فقط. التفاصيل والتعديل داخل زر التفاصيل.</div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle follow-up-table">
                        <thead>
                        <tr class="fw-bold text-muted">
                            <th>الكود</th>
                            <th>المستفيد</th>
                            <th>الزيارة</th>
                            <th>المهندس</th>
                            <th>الحالة</th>
                            <th>الإنجاز</th>
                            <th>BOQ</th>
                            <th>التوصيات</th>
                            <th class="text-end">إجراءات</th>
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
                                $boqItemsCount = $followUp->boqItems->count();
                                $boqItemsTotal = (float) $followUp->boqItems->sum('total_price_ils');
                                $boqHasFile = $boqAttachment || $followUp->hasBoqLink();
                                $boqStatusLabel = $boqItemsCount > 0 ? 'بنود مستوردة' : ($boqImported ? 'تم استيراد البنود' : ($boqImportFailed ? 'فشل الاستيراد' : ($boqHasFile ? 'ملف محفوظ فقط' : 'لا يوجد BOQ')));
                                $boqStatusClass = $boqItemsCount > 0 ? 'badge-light-success' : ($boqImported ? 'badge-light-success' : ($boqImportFailed ? 'badge-light-danger' : ($boqHasFile ? 'badge-light-warning' : 'badge-light')));
                                $boqPreviewId = "follow-up-boq-preview-{$followUp->id}";
                            @endphp
                            <tr>
                                <td class="fw-bold">{{ $followUp->code }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $followUp->beneficiary?->name ?? '-' }}</div>
                                    <div class="text-muted small">{{ $followUp->beneficiary?->identity_number ?? '-' }}</div>
                                </td>
                                <td>
                                    <span class="badge badge-light-primary">زيارة {{ $followUp->visit_number ?? '-' }}</span>
                                    <div class="text-muted small mt-1">{{ $followUp->visit_date?->format('Y-m-d') ?? '-' }}</div>
                                </td>
                                <td>{{ $followUp->engineer_name ?? '-' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $followUp->workingConditionLabel() }}</div>
                                    @if ($followUp->other_condition)
                                        <div class="text-muted small text-truncate" style="max-width: 160px;">{{ $followUp->other_condition }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-bold">{{ $followUp->completed_amount_ils ? number_format((float) $followUp->completed_amount_ils, 2) : '-' }} ILS</div>
                                    <div class="text-muted small">{{ $followUp->completion_percentage !== null ? number_format((float) $followUp->completion_percentage, 2).'%' : '-' }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $boqStatusClass }}">{{ $boqStatusLabel }}</span>
                                    @if ($followUp->boq_filename)
                                        <div class="text-muted small text-truncate follow-up-file-name mt-1">{{ $followUp->boq_filename }}</div>
                                    @endif
                                    @if ($boqItemsCount > 0)
                                        <div class="fw-bold text-success small mt-1">{{ number_format($boqItemsCount) }} بند · {{ number_format($boqItemsTotal, 2) }} ILS</div>
                                    @elseif ($boqImportFailed)
                                        <div class="text-danger small text-break mt-1">{{ $boqImportSummary['error'] ?? 'تعذر استيراد ملف جدول الكميات.' }}</div>
                                    @elseif ($boqImported)
                                        <div class="text-muted small">{{ $boqImportSummary['imported_rows'] }} بند</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="text-muted small text-truncate follow-up-recommendation">{{ $followUp->engineer_recommendations ?: '-' }}</div>
                                </td>
                                <td class="text-end follow-up-actions">
                                    <div class="d-flex justify-content-end gap-2">
                                        @if ($followUp->boqItems->isNotEmpty())
                                            <button class="btn btn-sm btn-light-primary" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $boqPreviewId }}" aria-expanded="false" aria-controls="{{ $boqPreviewId }}">عرض BOQ</button>
                                        @elseif ($followUp->boq_url)
                                            <form method="POST" action="{{ route('heks.follow-ups.boq.import', $followUp) }}">
                                                @csrf
                                                <button class="btn btn-sm btn-light-primary">ترحيل BOQ</button>
                                            </form>
                                        @elseif ($followUp->beneficiary)
                                            <a class="btn btn-sm btn-light" href="{{ route('heks.beneficiaries.pricing', $followUp->beneficiary) }}">BOQ الأساسي</a>
                                        @endif
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">خيارات الملف</button>
                                            <div class="dropdown-menu">
                                                @if ($followUp->boq_url)
                                                    <form method="POST" action="{{ route('heks.follow-ups.boq.import', $followUp) }}">
                                                        @csrf
                                                        <button class="dropdown-item">ترحيل BOQ من Excel</button>
                                                    </form>
                                                    <a class="dropdown-item" href="{{ $followUp->boq_url }}" download>تحميل Excel الأصلي</a>
                                                    <a class="dropdown-item" href="{{ $followUp->boq_url }}" target="_blank" rel="noopener">فتح رابط KoBo</a>
                                                @endif
                                                <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#followUpModal{{ $followUp->id }}">تفاصيل وتعديل</button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @if ($followUp->boqItems->isNotEmpty())
                                <tr class="collapse" id="{{ $boqPreviewId }}">
                                    <td colspan="9">
                                        <div class="follow-up-boq-preview">
                                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                                <div>
                                                    <div class="fw-bold">جدول كميات الزيارة</div>
                                                    <div class="text-muted small">{{ number_format($boqItemsCount) }} بند · الإجمالي {{ number_format($boqItemsTotal, 2) }} ILS</div>
                                                </div>
                                                <a class="btn btn-sm btn-light" href="{{ route('heks.follow-ups.boq', $followUp) }}">فتح BOQ الزيارة كاملة</a>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-row-dashed align-middle">
                                                    <thead>
                                                    <tr class="fw-bold text-muted">
                                                        <th>القسم</th>
                                                        <th>رقم</th>
                                                        <th class="boq-preview-description">الوصف</th>
                                                        <th>الوحدة</th>
                                                        <th>الكمية</th>
                                                        <th>سعر الوحدة ILS</th>
                                                        <th>الإجمالي ILS</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    @foreach ($followUp->boqItems as $item)
                                                        <tr>
                                                            <td>{{ $item->section ?? '-' }}</td>
                                                            <td class="fw-bold">{{ $item->item_code ?? '-' }}</td>
                                                            <td>{{ $item->description }}</td>
                                                            <td>{{ $item->unit ?? '-' }}</td>
                                                            <td>{{ number_format((float) $item->quantity, 3) }}</td>
                                                            <td>{{ number_format((float) $item->unit_price_ils, 2) }}</td>
                                                            <td class="fw-bold text-success">{{ number_format((float) $item->total_price_ils, 2) }}</td>
                                                        </tr>
                                                    @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="9" class="text-center text-muted">لا توجد متابعات بعد.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @foreach ($followUps as $followUp)
                    @php
                        $boqAttachment = $followUp->beneficiary?->attachments
                            ->first(fn ($attachment) => $attachment->attachment_type === 'follow_up_boq' && $attachment->source === "follow-up:{$followUp->id}");
                        $boqImportSummary = $boqAttachment?->raw_data['boq_import_summary'] ?? null;
                        $boqImported = is_array($boqImportSummary) && ($boqImportSummary['imported'] ?? false) && (($boqImportSummary['imported_rows'] ?? 0) > 0);
                        $boqImportFailed = is_array($boqImportSummary) && ($boqImportSummary['imported'] ?? true) === false;
                        $boqItemsCount = $followUp->boqItems->count();
                        $boqHasFile = $boqAttachment || $followUp->hasBoqLink();
                        $boqStatusLabel = $boqItemsCount > 0 ? 'بنود مستوردة' : ($boqImported ? 'تم استيراد البنود' : ($boqImportFailed ? 'فشل الاستيراد' : ($boqHasFile ? 'ملف محفوظ فقط' : 'لا يوجد BOQ')));
                        $boqStatusClass = $boqItemsCount > 0 ? 'badge-light-success' : ($boqImported ? 'badge-light-success' : ($boqImportFailed ? 'badge-light-danger' : ($boqHasFile ? 'badge-light-warning' : 'badge-light')));
                    @endphp
                    <div class="modal fade" id="followUpModal{{ $followUp->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-scrollable">
                            <form method="POST" action="{{ route('heks.follow-ups.update', $followUp) }}" class="modal-content">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <div>
                                        <h3 class="modal-title">{{ $followUp->code }} - {{ $followUp->beneficiary?->name ?? '-' }}</h3>
                                        <div class="text-muted small">تعديل بيانات المتابعة وملف BOQ المرتبط بها</div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-icon btn-light" data-bs-dismiss="modal" aria-label="Close">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-4">
                                        <div class="col-md-3">
                                            <label class="form-label">رقم الزيارة</label>
                                            <input name="visit_number" class="form-control" value="{{ $followUp->visit_number }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">تاريخ الزيارة</label>
                                            <input type="date" name="visit_date" class="form-control" value="{{ $followUp->visit_date?->format('Y-m-d') }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">المهندس</label>
                                            <input name="engineer_name" class="form-control" value="{{ $followUp->engineer_name }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">نسبة الإنجاز</label>
                                            <input name="completion_percentage" class="form-control" value="{{ $followUp->completion_percentage }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">المنجز ILS</label>
                                            <input name="completed_amount_ils" class="form-control" value="{{ $followUp->completed_amount_ils }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">حالة العمل</label>
                                            <input name="working_condition" class="form-control" value="{{ $followUp->working_condition }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">حالة أخرى</label>
                                            <textarea name="other_condition" class="form-control" rows="2">{{ $followUp->other_condition }}</textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">اسم ملف BOQ</label>
                                            <input name="boq_filename" class="form-control" value="{{ $followUp->boq_filename }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">رابط BOQ من KoBo</label>
                                            <input name="boq_url" class="form-control" value="{{ $followUp->boq_url }}">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">التوصيات</label>
                                            <textarea name="engineer_recommendations" class="form-control" rows="3">{{ $followUp->engineer_recommendations }}</textarea>
                                        </div>
                                        <div class="col-12">
                                            <div class="border rounded p-4">
                                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                                    <span class="badge {{ $boqStatusClass }}">{{ $boqStatusLabel }}</span>
                                                    @if ($boqImported)
                                                        <span class="text-muted small">{{ $boqImportSummary['imported_rows'] }} بند مستورد</span>
                                                    @endif
                                                </div>
                                                @if ($boqImportFailed)
                                                    <div class="text-danger small">{{ $boqImportSummary['error'] ?? 'تعذر استيراد ملف جدول الكميات.' }}</div>
                                                @elseif ($followUp->boq_url)
                                                    <div class="text-muted small text-break">{{ $followUp->boq_url }}</div>
                                                @else
                                                    <div class="text-muted small">لا يوجد ملف BOQ مرتبط بهذه المتابعة.</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                                    <button class="btn btn-primary">حفظ التعديل</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach

                <div class="heks-pagination">
                    {{ $followUps->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
@endsection
