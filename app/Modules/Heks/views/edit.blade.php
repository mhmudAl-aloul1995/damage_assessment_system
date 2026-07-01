@extends('layouts.app')

@section('title', 'تفاصيل مستفيد HEKS')
@section('pageName', $beneficiary->code)

@section('content')
    @include('heks::partials.nav')

    <style>
        .heks-case-page .case-hero { border: 1px solid var(--bs-gray-200); border-radius: .85rem; background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), .08), rgba(var(--bs-success-rgb), .04)); }
        .heks-case-page .case-kpi { border: 1px solid var(--bs-gray-200); border-radius: .75rem; padding: 1rem; height: 100%; }
        .heks-case-page .case-tabs .nav-link { border-radius: .65rem; color: var(--bs-gray-700); font-weight: 600; }
        .heks-case-page .case-tabs .nav-link.active { background: var(--bs-primary); color: #fff; }
        .heks-case-page .table-fixed-wide { min-width: 980px; }
        .heks-case-page .text-soft { color: var(--bs-gray-600); }
        .heks-case-page .assessment-list { border: 1px solid var(--bs-gray-200); border-radius: .75rem; max-height: 34rem; overflow-y: auto; overflow-x: hidden; }
        .heks-case-page .assessment-list-header,
        .heks-case-page .assessment-list-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(8rem, .52fr) 5rem; gap: 1rem; align-items: center; }
        .heks-case-page .assessment-list-header { background: var(--bs-body-bg); color: var(--bs-gray-600); font-weight: 700; padding: .85rem 1rem; position: sticky; top: 0; z-index: 1; }
        .heks-case-page .assessment-list-row { border-top: 1px dashed var(--bs-gray-200); padding: 1rem; }
        .heks-case-page .assessment-list-item,
        .heks-case-page .assessment-list-value { min-width: 0; overflow-wrap: anywhere; }
        .heks-case-page .assessment-list-score { justify-self: start; }
        .heks-case-page .survey-section { border: 1px solid #edf1f5; border-radius: 1rem; overflow: hidden; background: #fff; box-shadow: 0 .35rem 1rem rgba(15, 23, 42, .04); }
        .heks-case-page .survey-section-header { cursor: pointer; padding: 1rem 1.25rem; border: 0; background: linear-gradient(135deg, #f8fafc 0%, #eef6ff 45%, #e8f3ff 100%); border-bottom: 1px solid #e4ecf7; transition: all .25s ease; position: relative; }
        .heks-case-page .survey-section-header:hover { background: linear-gradient(135deg, #eef6ff 0%, #dceeff 100%); box-shadow: inset 0 0 0 1px rgba(0, 158, 247, .08); }
        .heks-case-page .survey-section-header:after { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: linear-gradient(180deg, #009ef7, #50cd89); }
        .heks-case-page .survey-collapse-indicator { width: 1.8rem; height: 1.8rem; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: #fff; color: #009ef7; border: 1px solid rgba(0, 158, 247, .18); font-size: .8rem; font-weight: 800; flex: 0 0 auto; }
        .heks-case-page .survey-section-header .survey-collapse-closed,
        .heks-case-page .survey-section-header.collapsed .survey-collapse-open,
        .heks-case-page .survey-section-header .survey-state-badge { display: none; }
        .heks-case-page .survey-section-header.collapsed .survey-collapse-closed,
        .heks-case-page .survey-section-header.collapsed .survey-state-badge { display: inline-flex; }
        .heks-case-page .survey-progress-bar { width: 120px; height: 6px; border-radius: 20px; background: #eef3f7; overflow: hidden; }
        .heks-case-page .survey-progress-fill { height: 100%; border-radius: 20px; background: linear-gradient(90deg, #009ef7, #50cd89); }
        .heks-case-page .survey-item { border-top: 1px dashed var(--bs-gray-300); padding: 1rem 1.25rem; background: #f1fff5; }
        .heks-case-page .survey-item:hover { background: #f8fbff; }
        .heks-case-page .survey-question { font-weight: 800; color: var(--bs-gray-800); line-height: 1.6; overflow-wrap: anywhere; }
        .heks-case-page .survey-answer { font-weight: 700; color: var(--bs-gray-700); line-height: 1.6; overflow-wrap: anywhere; }
        .heks-case-page .survey-edit-box { border: 1px solid #e8eef7; border-radius: .75rem; background: #fff; padding: .75rem; }
        .heks-case-page .survey-history-card { border: 1px solid #edf1f5; border-radius: .65rem; background: #fff; padding: .75rem; }
        .heks-case-page .survey-history-label { color: var(--bs-gray-600); font-size: .75rem; font-weight: 800; }
        @media (max-width: 767.98px) {
            .heks-case-page .assessment-list-header { display: none; }
            .heks-case-page .assessment-list-row { grid-template-columns: 1fr auto; gap: .75rem; }
            .heks-case-page .assessment-list-item { grid-column: 1 / -1; }
        }
    </style>

    @php
        $latestFollowUp = $beneficiary->followUps->first();
        $baseBoqCount = $beneficiary->boqItems->count();
        $followUpBoqCount = $beneficiary->followUps->sum(fn ($followUp) => $followUp->boqItems->count());
    @endphp

    <div class="heks-case-page">
        <div class="case-hero p-5 mb-6">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-5">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span class="badge badge-light-primary">{{ $beneficiary->code }}</span>
                        <span class="badge {{ $beneficiary->is_selected ? 'badge-light-success' : 'badge-light' }}">
                            {{ $beneficiary->is_selected ? 'مختار ضمن 125' : 'تقييم أولي' }}
                        </span>
                        @if ($beneficiary->payment_status)
                            <span class="badge badge-light-warning">
                                {{ match ($beneficiary->payment_status) {
                                    'paid_30' => 'دفعة أولى',
                                    'paid_80' => 'دفعتان',
                                    'paid_100' => 'مدفوع كامل',
                                    default => 'قيد الدفع',
                                } }}
                            </span>
                        @endif
                    </div>
                    <h2 class="fw-bold mb-2">{{ $beneficiary->name ?? '-' }}</h2>
                    <div class="text-muted">
                        {{ $beneficiary->identity_number ?? '-' }} · {{ $beneficiary->phone ?? '-' }} · {{ $beneficiary->field_engineer ?? '-' }}
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <a href="{{ route('heks.beneficiaries.pricing', $beneficiary) }}" class="btn btn-primary">فتح شاشة التسعير</a>
                    <a href="{{ route('heks.follow-ups') }}" class="btn btn-light-primary">المتابعات</a>
                    <a href="{{ route('heks.beneficiaries') }}" class="btn btn-light">رجوع</a>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-6">
            @foreach ([
                ['label' => 'المنحة', 'value' => $beneficiary->grant_amount ? number_format((float) $beneficiary->grant_amount, 2).' ILS' : '-', 'tone' => 'primary'],
                ['label' => 'BOQ الأساسي', 'value' => number_format($baseBoqCount).' بند', 'tone' => 'success'],
                ['label' => 'BOQ الزيارات', 'value' => number_format($followUpBoqCount).' بند', 'tone' => 'info'],
                ['label' => 'آخر متابعة', 'value' => $latestFollowUp?->visit_date?->format('Y-m-d') ?? '-', 'tone' => 'warning'],
                ['label' => 'حالة الضرر', 'value' => $beneficiary->damage_status ?? '-', 'tone' => 'danger'],
            ] as $card)
                <div class="col-xl col-md-4 col-6">
                    <div class="case-kpi">
                        <div class="text-muted small">{{ $card['label'] }}</div>
                        <div class="fs-3 fw-bold text-{{ $card['tone'] }}">{{ $card['value'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
الإستبيان
        <div class="card card-flush">
            <div class="card-body">
                <ul class="nav nav-pills case-tabs gap-2 mb-6" role="tablist">
                    @foreach ([
                        'summary' => 'ملخص',
                        'basic' => 'البيانات الأساسية',
                        'base-boq' => 'BOQ الأساسي',
                        'followups' => 'المتابعات',
                        'assessment' => 'التقييم',
                        'finance' => 'الدفعات والتوزيع',
                        'attachments' => 'المرفقات',
                        'raw' => 'البيانات الخام',
                    ] as $tabId => $tabLabel)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#{{ $tabId }}" type="button" role="tab">{{ $tabLabel }}</button>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="summary" role="tabpanel">
                        <div class="row g-4">
                            @foreach ([
                                'المحافظة' => $beneficiary->governorate ?? '-',
                                'المنطقة' => $beneficiary->area ?? '-',
                                'حالة النزوح' => $beneficiary->displacement_status ?? '-',
                                'حالة الإشغال' => $beneficiary->occupancy_status ?? '-',
                                'دفعة 30%' => $beneficiary->payment_1 ? number_format((float) $beneficiary->payment_1, 2) : '-',
                                'دفعة 50%' => $beneficiary->payment_2 ? number_format((float) $beneficiary->payment_2, 2) : '-',
                                'دفعة 20%' => $beneficiary->payment_3 ? number_format((float) $beneficiary->payment_3, 2) : '-',
                                'إجمالي BOQ الأساسي' => number_format($boqTotal, 2).' ILS',
                            ] as $label => $value)
                                <div class="col-xl-3 col-md-4 col-6">
                                    <div class="case-kpi">
                                        <div class="text-muted small">{{ $label }}</div>
                                        <div class="fw-bold">{{ $value }}</div>
                                    </div>
                                </div>
                            @endforeach
                            <div class="col-md-6">
                                <div class="case-kpi">
                                    <div class="text-muted small mb-2">العنوان</div>
                                    <div class="fw-semibold">{{ $beneficiary->address ?: '-' }}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="case-kpi">
                                    <div class="text-muted small mb-2">التوصيات</div>
                                    <div class="fw-semibold">{{ $beneficiary->recommendations ?: '-' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="basic" role="tabpanel">
                        <form method="POST" action="{{ route('heks.beneficiaries.update', $beneficiary) }}">
                            @csrf
                            @method('PUT')
                            <div class="row g-4">
                                @foreach ([
                                    'name' => 'اسم المستفيد',
                                    'identity_number' => 'رقم الهوية',
                                    'phone' => 'رقم التواصل',
                                    'alternate_phone' => 'رقم بديل',
                                    'field_engineer' => 'المهندس المسؤول',
                                    'visit_date' => 'تاريخ الزيارة',
                                    'governorate' => 'المحافظة',
                                    'area' => 'المنطقة',
                                    'displacement_status' => 'حالة النزوح',
                                    'occupancy_status' => 'حالة الإشغال',
                                    'damage_status' => 'حالة الضرر',
                                    'grant_amount' => 'المنحة',
                                    'payment_1' => 'دفعة 30%',
                                    'payment_2' => 'دفعة 50%',
                                    'payment_3' => 'دفعة 20%',
                                ] as $field => $label)
                                    <div class="col-md-4">
                                        <label class="form-label">{{ $label }}</label>
                                        <input name="{{ $field }}" class="form-control" value="{{ old($field, $field === 'visit_date' ? $beneficiary->{$field}?->format('Y-m-d') : $beneficiary->{$field}) }}">
                                    </div>
                                @endforeach
                                @foreach (['address' => 'العنوان', 'social_notes' => 'ملاحظات اجتماعية', 'engineer_notes' => 'ملاحظات هندسية', 'recommendations' => 'التوصيات'] as $field => $label)
                                    <div class="col-md-6">
                                        <label class="form-label">{{ $label }}</label>
                                        <textarea name="{{ $field }}" class="form-control" rows="3">{{ old($field, $beneficiary->{$field}) }}</textarea>
                                    </div>
                                @endforeach
                                <div class="col-12 text-end">
                                    <button class="btn btn-primary">حفظ البيانات الأساسية</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="base-boq" role="tabpanel">
                        <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 mb-5">
                            <div>
                                <h3 class="fs-4 fw-bold mb-1">جدول الكميات والتسعير BOQ</h3>
                                <div class="text-muted">هذا هو BOQ الأساسي للمستفيد. BOQ الزيارات يظهر في تبويب المتابعات.</div>
                            </div>
                            <div class="d-flex flex-wrap gap-3">
                                <a href="{{ route('heks.beneficiaries.pricing', $beneficiary) }}" class="btn btn-primary">فتح شاشة التسعير</a>
                                <div class="text-end">
                                    <div class="text-muted small">عدد البنود</div>
                                    <div class="fw-bold">{{ number_format($baseBoqCount) }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted small">إجمالي التسعير ILS</div>
                                    <div class="fw-bold text-primary">{{ number_format($boqTotal, 2) }}</div>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('heks.beneficiaries.boq-items.import', $beneficiary) }}" enctype="multipart/form-data" class="row g-3 align-items-end mb-5">
                            @csrf
                            <div class="col-xl-6 col-lg-8">
                                <label class="form-label">استيراد جدول كميات أساسي خاص بالمستفيد</label>
                                <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                            </div>
                            <div class="col-xl-2 col-lg-3">
                                <button class="btn btn-light-primary w-100">استيراد BOQ</button>
                            </div>
                            <div class="col-xl-4">
                                <div class="text-muted small">يتم استيراد البنود التي تحتوي كمية أكبر من صفر فقط، مع احتساب الإجمالي تلقائياً.</div>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('heks.beneficiaries.boq-items.store', $beneficiary) }}" class="row g-3 align-items-end mb-5">
                            @csrf
                            <input type="hidden" name="source" value="manual">
                            <div class="col-xl-2 col-md-4">
                                <label class="form-label">القسم</label>
                                <select id="heks-boq-section" name="section" class="form-select form-select-solid heks-boq-select2" data-control="select2" data-placeholder="اختر القسم" data-tags="true">
                                    <option></option>
                                    @foreach ($boqSections as $section)
                                        <option value="{{ $section }}">{{ $section }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-4 col-md-8">
                                <label class="form-label">البند</label>
                                <select id="heks-boq-item-select" name="description" class="form-select form-select-solid heks-boq-select2" data-control="select2" data-placeholder="اختر أو ابحث عن بند" data-tags="true" required>
                                    <option></option>
                                    @foreach ($boqCatalog as $item)
                                        <option value="{{ $item['description'] }}" data-section="{{ $item['section'] }}" data-code="{{ $item['item_code'] }}" data-unit="{{ $item['unit'] }}" data-price="{{ $item['unit_price_ils'] }}">
                                            {{ $item['item_code'] }} - {{ $item['description'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-1 col-md-3">
                                <label class="form-label">رقم البند</label>
                                <input id="heks-boq-item-code" name="item_code" class="form-control" placeholder="3.1">
                            </div>
                            <div class="col-xl-1 col-md-3">
                                <label class="form-label">الوحدة</label>
                                <select id="heks-boq-unit" name="unit" class="form-select form-select-solid heks-boq-select2" data-control="select2" data-placeholder="الوحدة" data-tags="true">
                                    <option></option>
                                    @foreach ($boqUnits as $unit)
                                        <option value="{{ $unit }}">{{ $unit }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-1 col-md-2">
                                <label class="form-label">الكمية</label>
                                <input name="quantity" type="number" min="0" step="0.001" class="form-control" value="0" required>
                            </div>
                            <div class="col-xl-2 col-md-3">
                                <label class="form-label">تكلفة الوحدة ILS</label>
                                <input id="heks-boq-unit-price" name="unit_price_ils" type="number" min="0" step="0.01" class="form-control" value="0" required>
                            </div>
                            <div class="col-xl-1 col-md-2">
                                <button class="btn btn-primary w-100">إضافة</button>
                            </div>
                            <div class="col-12">
                                <input name="notes" class="form-control" placeholder="ملاحظات البند">
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle table-fixed-wide">
                                <thead>
                                <tr class="fw-bold text-muted">
                                    <th>القسم</th>
                                    <th>رقم</th>
                                    <th class="min-w-300px">الوصف</th>
                                    <th>الوحدة</th>
                                    <th>الكمية</th>
                                    <th>سعر الوحدة ILS</th>
                                    <th>الإجمالي ILS</th>
                                    <th>ملاحظات</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse ($beneficiary->boqItems as $item)
                                    <form id="update-boq-{{ $item->id }}" method="POST" action="{{ route('heks.boq-items.update', $item) }}">
                                        @csrf
                                        @method('PUT')
                                    </form>
                                    <form id="delete-boq-{{ $item->id }}" method="POST" action="{{ route('heks.boq-items.destroy', $item) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                    <tr>
                                        <td><input form="update-boq-{{ $item->id }}" name="section" class="form-control form-control-sm" value="{{ $item->section }}"></td>
                                        <td><input form="update-boq-{{ $item->id }}" name="item_code" class="form-control form-control-sm" value="{{ $item->item_code }}"></td>
                                        <td><textarea form="update-boq-{{ $item->id }}" name="description" class="form-control form-control-sm" rows="2" required>{{ $item->description }}</textarea></td>
                                        <td><input form="update-boq-{{ $item->id }}" name="unit" class="form-control form-control-sm" value="{{ $item->unit }}"></td>
                                        <td><input form="update-boq-{{ $item->id }}" name="quantity" type="number" min="0" step="0.001" class="form-control form-control-sm" value="{{ $item->quantity }}" required></td>
                                        <td><input form="update-boq-{{ $item->id }}" name="unit_price_ils" type="number" min="0" step="0.01" class="form-control form-control-sm" value="{{ $item->unit_price_ils }}" required></td>
                                        <td class="fw-bold">{{ number_format((float) $item->total_price_ils, 2) }}</td>
                                        <td>
                                            <input form="update-boq-{{ $item->id }}" type="hidden" name="source" value="{{ $item->source }}">
                                            <input form="update-boq-{{ $item->id }}" name="notes" class="form-control form-control-sm" value="{{ $item->notes }}">
                                        </td>
                                        <td class="text-nowrap">
                                            <button form="update-boq-{{ $item->id }}" class="btn btn-sm btn-light-primary">حفظ</button>
                                            <button form="delete-boq-{{ $item->id }}" class="btn btn-sm btn-light-danger">حذف</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="text-center text-muted">لا توجد بنود جدول كميات لهذا المستفيد بعد.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="followups" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle table-fixed-wide">
                                <thead>
                                <tr class="fw-bold text-muted">
                                    <th>الزيارة</th>
                                    <th>التاريخ</th>
                                    <th>المهندس</th>
                                    <th>الحالة</th>
                                    <th>الإنجاز</th>
                                    <th>BOQ الزيارة</th>
                                    <th>التوصيات</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse ($beneficiary->followUps as $followUp)
                                    <tr>
                                        <td><span class="badge badge-light-primary">{{ $followUp->visit_number ?? '-' }}</span></td>
                                        <td>{{ $followUp->visit_date?->format('Y-m-d') ?? '-' }}</td>
                                        <td>{{ $followUp->engineer_name ?? '-' }}</td>
                                        <td>{{ $followUp->working_condition ?? '-' }}</td>
                                        <td>
                                            <div>{{ $followUp->completed_amount_ils ? number_format((float) $followUp->completed_amount_ils, 2) : '-' }} ILS</div>
                                            <div class="text-muted small">{{ $followUp->completion_percentage !== null ? number_format((float) $followUp->completion_percentage, 2).'%' : '-' }}</div>
                                        </td>
                                        <td>
                                            @if ($followUp->boqItems->isNotEmpty())
                                                <a class="btn btn-sm btn-light-primary" href="{{ route('heks.follow-ups.boq', $followUp) }}">فتح BOQ الزيارة</a>
                                                <div class="text-muted small mt-1">{{ $followUp->boqItems->count() }} بند</div>
                                            @elseif ($followUp->boq_url)
                                                <a class="btn btn-sm btn-light" href="{{ $followUp->boq_url }}" target="_blank" rel="noopener">فتح رابط KoBo</a>
                                            @else
                                                <span class="text-muted">لا يوجد BOQ</span>
                                            @endif
                                        </td>
                                        <td class="text-muted">{{ $followUp->engineer_recommendations ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted">لا توجد متابعات.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="assessment" role="tabpanel">
                        @php
                            $latestScore = $beneficiary->scores->first(
                                fn ($score) => $score->social_score !== null
                                    || $score->technical_score !== null
                                    || $score->total_score !== null
                                    || filled($score->classification)
                            ) ?? $beneficiary->scores->first();
                            $technicalWeightTotal = $technicalAssessmentRows->sum(fn ($row) => (float) ($row['weight'] ?? 0));
                            $answeredTechnicalRows = $technicalAssessmentRows->filter(fn ($row) => filled($row['value']));
                        @endphp

                        <div class="row g-4 mb-6">
                            @foreach ([
                                ['label' => 'التقييم الاجتماعي', 'value' => $latestScore?->social_score !== null ? number_format((float) $latestScore->social_score, 2) : '-', 'hint' => 'من 30', 'tone' => 'info'],
                                ['label' => 'التقييم الفني', 'value' => $latestScore?->technical_score !== null ? number_format((float) $latestScore->technical_score, 2) : '-', 'hint' => 'من 70', 'tone' => 'primary'],
                                ['label' => 'التقييم النهائي', 'value' => $latestScore?->total_score !== null ? number_format((float) $latestScore->total_score, 2) : '-', 'hint' => 'Social + Technical', 'tone' => 'success'],
                                ['label' => 'التصنيف', 'value' => $latestScore?->classification ?: '-', 'hint' => $latestScore?->source ?: 'Scoring', 'tone' => 'warning'],
                            ] as $scoreCard)
                                <div class="col-xl-3 col-md-6">
                                    <div class="case-kpi">
                                        <div class="d-flex justify-content-between gap-3">
                                            <div class="text-muted small">{{ $scoreCard['label'] }}</div>
                                            <span class="badge badge-light-{{ $scoreCard['tone'] }}">{{ $scoreCard['hint'] }}</span>
                                        </div>
                                        <div class="fs-2 fw-bold text-{{ $scoreCard['tone'] }} mt-2">{{ $scoreCard['value'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="row g-5 mb-7">
                            <div class="col-xl-5">
                                <h3 class="fs-5 fw-bold mb-4">مكونات التقييم</h3>
                                <div class="table-responsive">
                                    <table class="table table-row-dashed align-middle">
                                        <thead><tr class="fw-bold text-muted"><th>المكون</th><th>الوزن</th><th>الحد الأعلى</th></tr></thead>
                                        <tbody>
                                        @foreach ($scoringComponents as $component)
                                            <tr>
                                                <td class="fw-semibold">{{ $component['component'] }}</td>
                                                <td><span class="badge badge-light-primary">{{ $component['weight'] }}</span></td>
                                                <td>{{ $component['max_points'] }} points</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-xl-7">
                                <h3 class="fs-5 fw-bold mb-4">تصنيف الأولوية والتدخل</h3>
                                <div class="table-responsive">
                                    <table class="table table-row-dashed align-middle">
                                        <thead><tr class="fw-bold text-muted"><th>النقاط</th><th>الأولوية</th><th>التدخل</th></tr></thead>
                                        <tbody>
                                        @foreach ($priorityMatrix as $priority)
                                            <tr>
                                                <td><span class="badge badge-light">{{ $priority['score'] }}</span></td>
                                                <td class="fw-bold">{{ $priority['priority'] }}</td>
                                                <td>{{ $priority['intervention'] }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="row g-5 mb-7">
                            <div class="col-xl-6">
                                <div class="d-flex flex-column flex-md-row justify-content-between gap-4 mb-5">
                                    <div>
                                        <h3 class="fs-4 fw-bold mb-1">معايير التقييم الاجتماعي</h3>
                                        <div class="text-muted">Social Vulnerability: كل معيار No = 0 و Yes = 5.</div>
                                    </div>
                                    <div class="case-kpi py-3 min-w-150px">
                                        <div class="text-muted small">عدد المعايير</div>
                                        <div class="fs-4 fw-bold text-info">{{ number_format($socialAssessmentRows->count()) }}</div>
                                    </div>
                                </div>

                                <div class="assessment-list">
                                    <div class="assessment-list-header">
                                        <div>البند</div>
                                        <div>القيمة</div>
                                        <div>النقاط</div>
                                    </div>
                                    @forelse ($socialAssessmentRows as $row)
                                        <div class="assessment-list-row">
                                            <div class="assessment-list-item fw-semibold">{{ $row['question'] }}</div>
                                            <div class="assessment-list-value">
                                                @if (filled($row['value']))
                                                    <span class="fw-semibold">{{ $row['value'] }}</span>
                                                @else
                                                    <span class="text-muted">غير متوفر</span>
                                                @endif
                                            </div>
                                            <div class="assessment-list-score">
                                                @if ($row['points'] !== null)
                                                    <span class="badge {{ (int) $row['points'] > 0 ? 'badge-light-success' : 'badge-light' }}">{{ $row['points'] }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div class="assessment-list-row">
                                            <div class="assessment-list-item text-center text-muted" style="grid-column: 1 / -1;">لم يتم استيراد شيت S-V لمعايير التقييم الاجتماعي بعد.</div>
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="col-xl-6">
                                <div class="d-flex flex-column flex-md-row justify-content-between gap-4 mb-5">
                                    <div>
                                        <h3 class="fs-4 fw-bold mb-1">التقييم الفني للمأوى</h3>
                                        <div class="text-muted">Shelter Technical Weights مع قيمة هذا المستفيد.</div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-3">
                                        <div class="case-kpi py-3 min-w-125px">
                                            <div class="text-muted small">الأوزان</div>
                                            <div class="fs-4 fw-bold text-primary">{{ number_format($technicalWeightTotal, 2) }}</div>
                                        </div>
                                        <div class="case-kpi py-3 min-w-125px">
                                            <div class="text-muted small">قيم موجودة</div>
                                            <div class="fs-4 fw-bold text-success">{{ number_format($answeredTechnicalRows->count()) }} / {{ number_format($technicalAssessmentRows->count()) }}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="assessment-list">
                                    <div class="assessment-list-header">
                                        <div>البند</div>
                                        <div>القيمة</div>
                                        <div>النقاط</div>
                                    </div>
                                    @forelse ($technicalAssessmentRows as $row)
                                        <div class="assessment-list-row">
                                            <div class="assessment-list-item">
                                                <div class="fw-semibold">{{ $row['question'] ?: $row['indicator'] ?: '-' }}</div>
                                            </div>
                                            <div class="assessment-list-value">
                                                @if (filled($row['value']))
                                                    <span class="fw-semibold">{{ $row['value'] }}</span>
                                                @else
                                                    <span class="text-muted">غير متوفر</span>
                                                @endif
                                            </div>
                                            <div class="assessment-list-score">
                                                @if (filled($row['score']))
                                                    <span class="badge badge-light-primary">{{ $row['score'] }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div class="assessment-list-row">
                                            <div class="assessment-list-item text-center text-muted" style="grid-column: 1 / -1;">لم يتم استيراد جدول أوزان التقييم الفني بعد.</div>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="row g-5">
                            <div class="col-xl-6">
                                <h3 class="fs-5 fw-bold mb-4">التقييم والدرجات</h3>
                                <div class="table-responsive">
                                    <table class="table table-row-dashed align-middle">
                                        <thead><tr class="fw-bold text-muted"><th>المصدر</th><th>اجتماعي</th><th>فني</th><th>نهائي</th><th>التصنيف</th><th>المنحة</th><th></th></tr></thead>
                                        <tbody>
                                        @forelse ($beneficiary->scores as $score)
                                            <tr>
                                                <form method="POST" action="{{ route('heks.scores.update', $score) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <td><input name="source" class="form-control form-control-sm" value="{{ $score->source }}" aria-label="Score source"></td>
                                                    <td><input name="social_score" type="number" step="0.01" class="form-control form-control-sm" value="{{ $score->social_score }}" aria-label="Social score"></td>
                                                    <td><input name="technical_score" type="number" step="0.01" class="form-control form-control-sm" value="{{ $score->technical_score }}" aria-label="Technical score"></td>
                                                    <td><input name="total_score" type="number" step="0.01" class="form-control form-control-sm fw-bold" value="{{ $score->total_score }}" aria-label="Total score"></td>
                                                    <td><input name="classification" class="form-control form-control-sm" value="{{ $score->classification }}" aria-label="Classification"></td>
                                                    <td><input name="grant_amount" type="number" step="0.01" class="form-control form-control-sm" value="{{ $score->grant_amount }}" aria-label="Grant amount"></td>
                                                    <td><button class="btn btn-sm btn-light-primary">حفظ</button></td>
                                                </form>
                                            </tr>
                                        @empty
                                            <tr><td colspan="7" class="text-center text-muted">لا توجد درجات.</td></tr>
                                        @endforelse
                                        <tr>
                                            <form method="POST" action="{{ route('heks.beneficiaries.scores.store', $beneficiary) }}">
                                                @csrf
                                                <td><input name="source" class="form-control form-control-sm" value="manual" aria-label="New score source"></td>
                                                <td><input name="social_score" type="number" step="0.01" class="form-control form-control-sm" placeholder="30" aria-label="New social score"></td>
                                                <td><input name="technical_score" type="number" step="0.01" class="form-control form-control-sm" placeholder="70" aria-label="New technical score"></td>
                                                <td><input name="total_score" type="number" step="0.01" class="form-control form-control-sm fw-bold" placeholder="100" aria-label="New total score"></td>
                                                <td><input name="classification" class="form-control form-control-sm" placeholder="High" aria-label="New classification"></td>
                                                <td><input name="grant_amount" type="number" step="0.01" class="form-control form-control-sm" placeholder="ILS" aria-label="New grant amount"></td>
                                                <td><button class="btn btn-sm btn-primary">إضافة</button></td>
                                            </form>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-xl-6">
                                <h3 class="fs-5 fw-bold mb-4">معايير ونتائج التقييم</h3>
                                <div class="table-responsive">
                                    <table class="table table-row-dashed align-middle">
                                        <thead><tr class="fw-bold text-muted"><th>المعيار</th><th>القيمة</th><th>المصدر</th></tr></thead>
                                        <tbody>
                                        @forelse ($beneficiary->labels as $label)
                                            <tr>
                                                <td class="fw-semibold">{{ $label->label_key }}</td>
                                                <td>{{ $label->label_value ?? '-' }}</td>
                                                <td class="text-muted">{{ $label->source ?? '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="3" class="text-center text-muted">لا توجد معايير.</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="finance" role="tabpanel">
                        <div class="row g-5">
                            <div class="col-xl-6">
                                <h3 class="fs-5 fw-bold mb-4">الدفعات</h3>
                                <div class="table-responsive">
                                    <table class="table table-row-dashed align-middle">
                                        <thead><tr class="fw-bold text-muted"><th>المصدر</th><th>30%</th><th>50%</th><th>20%</th></tr></thead>
                                        <tbody>
                                        @forelse ($beneficiary->payments as $payment)
                                            <tr>
                                                <td>{{ $payment->source ?? '-' }}</td>
                                                <td>{{ $payment->payment_1_amount ?? '-' }}</td>
                                                <td>{{ $payment->payment_2_amount ?? '-' }}</td>
                                                <td>{{ $payment->payment_3_amount ?? '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="text-center text-muted">لا توجد دفعات.</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-xl-6">
                                <h3 class="fs-5 fw-bold mb-4">التوزيع</h3>
                                @forelse ($beneficiary->workAssignments as $assignment)
                                    <div class="border rounded p-4 mb-3">
                                        <div class="fw-bold">{{ $assignment->engineer_name ?? '-' }}</div>
                                        <div class="text-muted small">{{ $assignment->source }}</div>
                                        <div>قيمة العقد: {{ $assignment->contract_amount_ils ? number_format((float) $assignment->contract_amount_ils, 2) : '-' }} ILS</div>
                                    </div>
                                @empty
                                    <div class="text-muted">لا يوجد توزيع عمل.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="attachments" role="tabpanel">
                        <div class="row g-4">
                            @forelse ($beneficiary->attachments as $attachment)
                                <div class="col-xl-4 col-md-6">
                                    <div class="border rounded p-4 h-100">
                                        <div class="fw-bold text-truncate">{{ $attachment->filename ?? '-' }}</div>
                                        <div class="text-muted small mb-3">{{ $attachment->attachment_type ?? $attachment->source }}</div>
                                        @if ($attachment->url)
                                            <a class="btn btn-sm btn-light-primary" href="{{ $attachment->url }}" target="_blank" rel="noopener">فتح المرفق</a>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="col-12 text-muted">لا توجد مرفقات مرتبطة.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="tab-pane fade" id="raw" role="tabpanel">
                        <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 mb-6">
                            <div>
                                <h3 class="fs-3 fw-bold mb-2">استبيان KoBo للمستفيد</h3>
                                <div class="text-muted">عرض منظم لإجابات الاستبيان حسب المحاور، مع الاحتفاظ بالمصدر لكل قيمة.</div>
                            </div>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="case-kpi py-3 min-w-125px">
                                    <div class="text-muted small">الأقسام</div>
                                    <div class="fs-4 fw-bold text-primary">{{ number_format(count($surveySections)) }}</div>
                                </div>
                                <div class="case-kpi py-3 min-w-125px">
                                    <div class="text-muted small">الإجابات</div>
                                    <div class="fs-4 fw-bold text-success">{{ number_format(collect($surveySections)->sum(fn ($section) => count($section['items']))) }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="accordion accordion-icon-toggle" id="heksSurveyAccordion">
                            @forelse ($surveySections as $sectionIndex => $section)
                                @php
                                    $sectionId = 'heks_survey_section_'.$sectionIndex;
                                    $itemsCount = count($section['items']);
                                    $answeredCount = collect($section['items'])->filter(fn ($item) => filled($item['value']))->count();
                                    $completionPercent = $itemsCount > 0 ? (int) round(($answeredCount / $itemsCount) * 100) : 0;
                                    $isOpen = $sectionIndex === array_key_first($surveySections);
                                @endphp
                                <div class="survey-section mb-4">
                                    <div class="survey-section-header {{ $isOpen ? '' : 'collapsed' }} d-flex justify-content-between align-items-center flex-wrap gap-3"
                                         data-bs-toggle="collapse"
                                         data-bs-target="#{{ $sectionId }}"
                                         role="button"
                                         aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                                         aria-controls="{{ $sectionId }}">
                                        <div class="d-flex align-items-start gap-3">
                                            <span class="survey-collapse-indicator survey-collapse-open">−</span>
                                            <span class="survey-collapse-indicator survey-collapse-closed">+</span>
                                            <div>
                                                <div class="fw-bold fs-5 text-gray-800">{{ $section['title'] }}</div>
                                                <div class="text-muted mt-1">{{ $section['description'] }}</div>
                                                <div class="survey-progress-bar mt-3">
                                                    <div class="survey-progress-fill" style="width: {{ $completionPercent }}%"></div>
                                                </div>
                                            </diالإستبيان
                                        </div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <span class="badge badge-light-primary">{{ number_format($answeredCount) }} / {{ number_format($itemsCount) }}</span>
                                            <span class="badge badge-light-success">{{ $completionPercent }}% مكتمل</span>
                                            <span class="badge badge-light survey-state-badge">اضغط للعرض</span>
                                        </div>
                                    </div>

                                    <div id="{{ $sectionId }}" class="collapse {{ $isOpen ? 'show' : '' }}" data-bs-parent="#heksSurveyAccordion">
                                        @foreach ($section['items'] as $item)
                                            @php
                                                $historyId = 'heks_survey_history_'.md5($item['source'].'|'.$item['question']);
                                                $historyCount = count($item['history']);
                                            @endphp
                                            <div class="survey-item">
                                                <div class="row g-4 align-items-start">
                                                    <div class="col-lg-5">
                                                        <div class="survey-question">{{ $item['question'] }}</div>
                                                    </div>
                                                    <div class="col-lg-7">
                                                        <div class="survey-answer mb-3">{{ $item['value'] }}</div>
                                                        <form method="POST" action="{{ route('heks.beneficiaries.survey-values.update', $beneficiary) }}" class="survey-edit-box">
                                                            @csrf
                                                            @method('PUT')
                                                            <input type="hidden" name="source" value="{{ $item['source'] }}">
                                                            <input type="hidden" name="field_key" value="{{ $item['question'] }}">
                                                            <div class="d-flex flex-column flex-md-row gap-2">
                                                                <input name="value" class="form-control form-control-sm" value="{{ $item['value'] }}" aria-label="تعديل قيمة الاستبيان">
                                                                <button class="btn btn-sm btn-light-primary flex-shrink-0">حفظ</button>
                                                            </div>
                                                        </form>

                                                        @if ($historyCount > 0)
                                                            <button class="btn btn-sm btn-light mt-3" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $historyId }}" aria-expanded="false" aria-controls="{{ $historyId }}">
                                                                سجل التعديلات ({{ $historyCount }})
                                                            </button>
                                                            <div class="collapse mt-3" id="{{ $historyId }}">
                                                                <div class="d-flex flex-column gap-2">
                                                                    @foreach ($item['history'] as $history)
                                                                        <div class="survey-history-card">
                                                                            <div class="row g-3">
                                                                                <div class="col-md-6">
                                                                                    <div class="survey-history-label">القيمة السابقة</div>
                                                                                    <div>{{ $history['old_value'] ?? '-' }}</div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="survey-history-label">القيمة الجديدة</div>
                                                                                    <div class="fw-bold">{{ $history['new_value'] ?? '-' }}</div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="survey-history-label">المستخدم</div>
                                                                                    <div>{{ $history['user'] ?? '-' }}</div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="survey-history-label">الوقت</div>
                                                                                    <div>{{ $history['created_at'] ?? '-' }}</div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <div class="survey-section">
                                    <div class="survey-item text-muted">لا توجد بيانات استبيان محفوظة لهذه الحالة.</div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery && $.fn.select2) {
                $('.heks-boq-select2').select2({
                    dir: 'rtl',
                    width: '100%',
                    tags: true,
                    allowClear: true
                });
            }

            $('#heks-boq-item-select').on('change', function () {
                const selected = $(this).find(':selected');
                const section = selected.data('section') || '';
                const code = selected.data('code') || '';
                const unit = selected.data('unit') || '';
                const price = selected.data('price') || 0;

                if (section) {
                    const sectionSelect = $('#heks-boq-section');
                    if (!sectionSelect.find(`option[value="${section}"]`).length) {
                        sectionSelect.append(new Option(section, section, true, true));
                    }
                    sectionSelect.val(section).trigger('change');
                }

                if (unit) {
                    const unitSelect = $('#heks-boq-unit');
                    if (!unitSelect.find(`option[value="${unit}"]`).length) {
                        unitSelect.append(new Option(unit, unit, true, true));
                    }
                    unitSelect.val(unit).trigger('change');
                }

                $('#heks-boq-item-code').val(code);
                $('#heks-boq-unit-price').val(price);
            });
        });
    </script>
@endsection
