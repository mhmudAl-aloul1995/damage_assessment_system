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
        .heks-case-page .case-boq-table { min-width: 1220px; table-layout: fixed; }
        .heks-case-page .case-boq-table thead th { background: var(--bs-body-bg); position: sticky; top: 0; z-index: 1; }
        .heks-case-page .case-boq-table tbody tr.is-readonly-row { background: rgba(var(--bs-primary-rgb), .025); }
        .heks-case-page .case-boq-table tbody tr.is-editable-row { background: rgba(var(--bs-success-rgb), .035); }
        .heks-case-page .case-boq-table tbody tr.case-boq-section-row { background: #f8fbff; }
        .heks-case-page .case-boq-table-wrap { border: 1px solid var(--bs-gray-200); border-radius: .75rem; max-height: calc(100vh - 18rem); overflow: auto; }
        .heks-case-page .case-boq-section-strip { border-inline-start: 4px solid var(--bs-primary); padding: .75rem 1rem; }
        .heks-case-page .case-boq-col-code { width: 88px; }
        .heks-case-page .case-boq-col-section { width: 150px; }
        .heks-case-page .case-boq-col-item { width: 34%; }
        .heks-case-page .case-boq-col-unit { width: 80px; }
        .heks-case-page .case-boq-col-money { width: 130px; }
        .heks-case-page .case-boq-col-quantity { width: 105px; }
        .heks-case-page .case-boq-col-notes { width: 180px; }
        .heks-case-page .case-boq-col-actions { width: 115px; }
        .heks-case-page .case-boq-description { line-height: 1.55; overflow-wrap: anywhere; }
        .heks-case-page .case-boq-readonly-value { border: 1px solid var(--bs-gray-200); border-radius: .475rem; background: var(--bs-gray-100); min-height: calc(1.5em + 1.1rem + 2px); padding: .55rem .75rem; display: flex; align-items: center; color: var(--bs-gray-700); }
        .heks-case-page .case-boq-number { direction: ltr; text-align: right; font-variant-numeric: tabular-nums; unicode-bidi: plaintext; }
        .heks-case-page .text-soft { color: var(--bs-gray-600); }
        .heks-case-page .assessment-list { border: 1px solid var(--bs-gray-200); border-radius: .75rem; max-height: 34rem; overflow-y: auto; overflow-x: hidden; }
        .heks-case-page .assessment-list-header,
        .heks-case-page .assessment-list-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(8rem, .52fr) 5rem; gap: 1rem; align-items: center; }
        .heks-case-page .assessment-list-header { background: var(--bs-body-bg); color: var(--bs-gray-600); font-weight: 700; padding: .85rem 1rem; position: sticky; top: 0; z-index: 1; }
        .heks-case-page .assessment-list-row { border-top: 1px dashed var(--bs-gray-200); padding: 1rem; }
        .heks-case-page .assessment-list-item,
        .heks-case-page .assessment-list-value { min-width: 0; overflow-wrap: anywhere; }
        .heks-case-page .assessment-list-score { justify-self: start; }
        .heks-case-page .survey-section { border: 1px solid #edf1f5; border-radius: .85rem; overflow: hidden; background: #fff; box-shadow: 0 .25rem .75rem rgba(15, 23, 42, .035); }
        .heks-case-page .survey-section-header { cursor: pointer; padding: 1rem 1.25rem; border: 0; background: #fff; border-bottom: 1px solid #edf1f5; transition: all .25s ease; position: relative; }
        .heks-case-page .survey-section-header:hover { background: #f8fbff; box-shadow: inset 0 0 0 1px rgba(0, 158, 247, .08); }
        .heks-case-page .survey-section-header:after { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: linear-gradient(180deg, #009ef7, #50cd89); }
        .heks-case-page .survey-collapse-indicator { width: 1.8rem; height: 1.8rem; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: #fff; color: #009ef7; border: 1px solid rgba(0, 158, 247, .18); font-size: .8rem; font-weight: 800; flex: 0 0 auto; }
        .heks-case-page .survey-section-header .survey-collapse-closed,
        .heks-case-page .survey-section-header.collapsed .survey-collapse-open,
        .heks-case-page .survey-section-header .survey-state-badge { display: none; }
        .heks-case-page .survey-section-header.collapsed .survey-collapse-closed,
        .heks-case-page .survey-section-header.collapsed .survey-state-badge { display: inline-flex; }
        .heks-case-page .survey-progress-bar { width: 120px; height: 6px; border-radius: 20px; background: #eef3f7; overflow: hidden; }
        .heks-case-page .survey-progress-fill { height: 100%; border-radius: 20px; background: linear-gradient(90deg, #009ef7, #50cd89); }
        .heks-case-page .survey-item { border-top: 1px dashed var(--bs-gray-300); padding: 1rem 1.25rem; background: #fff; }
        .heks-case-page .survey-item:hover { background: #f8fbff; }
        .heks-case-page .survey-question { font-weight: 800; color: var(--bs-gray-800); line-height: 1.6; overflow-wrap: anywhere; }
        .heks-case-page .survey-answer { font-weight: 700; color: var(--bs-gray-700); line-height: 1.6; overflow-wrap: anywhere; }
        .heks-case-page .survey-choices { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .5rem; }
        .heks-case-page .survey-choice { border: 1px solid #e4ebf3; border-radius: 999px; color: var(--bs-gray-600); background: #fff; padding: .35rem .7rem; font-size: .78rem; font-weight: 700; }
        .heks-case-page .survey-choice.selected { border-color: rgba(var(--bs-primary-rgb), .25); color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .08); }
        .heks-case-page .survey-edit-box { border: 1px solid #e8eef7; border-radius: .75rem; background: #fff; padding: .75rem; }
        .heks-case-page .survey-history-card { border: 1px solid #edf1f5; border-radius: .65rem; background: #fff; padding: .75rem; }
        .heks-case-page .survey-history-label { color: var(--bs-gray-600); font-size: .75rem; font-weight: 800; }
        .heks-case-page .kobo-paper { max-width: 980px; margin-inline: auto; border: 1px solid var(--bs-gray-200); border-radius: .95rem; background: linear-gradient(180deg, rgba(var(--bs-primary-rgb), .045), #fff 15rem); padding: 2rem 2.5rem; color: var(--bs-gray-800); direction: rtl; box-shadow: 0 .65rem 1.8rem rgba(15, 23, 42, .07); }
        .heks-case-page .kobo-paper-title { text-align: center; font-size: 1.7rem; font-weight: 800; margin-bottom: 2rem; direction: ltr; color: var(--bs-primary); letter-spacing: 0; }
        .heks-case-page .kobo-pdf-section { break-inside: avoid; margin-bottom: 2rem; border-top: 1px solid var(--bs-gray-200); padding-top: 1.35rem; }
        .heks-case-page .kobo-pdf-section:first-of-type { border-top: 0; padding-top: 0; }
        .heks-case-page .kobo-pdf-section-title { display: flex; align-items: center; gap: .7rem; font-size: 1.1rem; font-weight: 800; margin-bottom: 1rem; color: var(--bs-gray-900); }
        .heks-case-page .kobo-pdf-section-title::before { content: ""; width: .45rem; height: 1.65rem; border-radius: 1rem; background: linear-gradient(180deg, var(--bs-primary), var(--bs-success)); flex: 0 0 auto; }
        .heks-case-page .kobo-pdf-item { margin-bottom: 0; padding: 1rem 0; border-bottom: 1px dashed var(--bs-gray-200); page-break-inside: avoid; }
        .heks-case-page .kobo-pdf-item:last-child { border-bottom: 0; }
        .heks-case-page .kobo-pdf-question { font-size: .96rem; font-weight: 700; line-height: 1.9; margin-bottom: .65rem; color: var(--bs-gray-800); }
        .heks-case-page .kobo-pdf-answer-line { min-height: 2.35rem; border: 1px solid var(--bs-gray-200); border-bottom: 2px solid rgba(var(--bs-primary-rgb), .28); border-radius: .55rem .55rem .35rem .35rem; background: rgba(var(--bs-light-rgb), .75); display: flex; align-items: center; padding: .45rem .75rem; font-size: .95rem; line-height: 1.7; color: var(--bs-gray-800); overflow-wrap: anywhere; }
        .heks-case-page .kobo-pdf-answer-line.is-empty { color: transparent; background: #fff; }
        .heks-case-page .kobo-pdf-choice-list { display: flex; flex-direction: column; gap: .55rem; margin-top: .55rem; }
        .heks-case-page .kobo-pdf-choice { display: flex; align-items: flex-start; gap: .8rem; line-height: 1.8; font-size: .95rem; color: var(--bs-gray-700); border: 1px solid var(--bs-gray-200); border-radius: .6rem; background: #fff; padding: .55rem .7rem; transition: border-color .2s ease, background-color .2s ease, color .2s ease; }
        .heks-case-page .kobo-pdf-choice.is-selected { border-color: rgba(var(--bs-primary-rgb), .3); background: rgba(var(--bs-primary-rgb), .075); color: var(--bs-gray-900); font-weight: 700; }
        .heks-case-page .kobo-pdf-choice-marker { width: 1.35rem; height: 1.35rem; border: 1.5px solid var(--bs-gray-400); flex: 0 0 1.35rem; margin-top: .2rem; display: inline-flex; align-items: center; justify-content: center; background: #fff; }
        .heks-case-page .kobo-pdf-choice-marker.radio { border-radius: 50%; }
        .heks-case-page .kobo-pdf-choice-marker.checkbox { border-radius: .18rem; }
        .heks-case-page .kobo-pdf-choice-marker.selected { border-color: var(--bs-primary); background: var(--bs-primary); }
        .heks-case-page .kobo-pdf-choice-marker.selected::after { content: ""; width: .62rem; height: .62rem; display: block; background: #fff; }
        .heks-case-page .kobo-pdf-choice-marker.radio.selected::after { border-radius: 50%; }
        .heks-case-page .kobo-pdf-choice-marker.checkbox.selected::after { width: .78rem; height: .45rem; border-left: 3px solid #fff; border-bottom: 3px solid #fff; background: transparent; transform: rotate(-45deg); margin-top: -.2rem; }
        @media (max-width: 767.98px) {
            .heks-case-page .kobo-paper { padding: 1.25rem; }
            .heks-case-page .kobo-paper-title { font-size: 1.35rem; }
        }
        .heks-case-page .photo-card { border: 1px solid var(--bs-gray-200); border-radius: .85rem; overflow: hidden; background: #fff; height: 100%; }
        .heks-case-page .photo-card img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; display: block; background: var(--bs-gray-100); }
        .heks-case-page .photo-card-body { padding: 1rem; }
        .heks-case-page .photo-card-title { font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
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
                        {{ $beneficiary->identity_number ?? '-' }} · {{ $beneficiary->phone ?? '-' }} · {{ $beneficiary->responsibleEngineerName() ?? '-' }}
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <a href="{{ route('heks.beneficiaries.pdf', $beneficiary) }}" target="_blank" rel="noopener" class="btn btn-light-success">تصدير PDF</a>
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
                ['label' => 'حالة الضرر', 'value' => $damageStatusDisplay ?? '-', 'tone' => 'danger'],
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
                        'photos' => 'الصور',
                        'attachments' => 'المرفقات',
                        'raw' => 'الإستبيان',
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
                                'المحافظة' => $basicDisplay['governorate'] ?? $beneficiary->governorate ?? '-',
                                'المنطقة' => $basicDisplay['area'] ?? $beneficiary->area ?? '-',
                                'حالة النزوح' => $basicDisplay['displacement_status'] ?? $beneficiary->displacement_status ?? '-',
                                'حالة الإشغال' => $basicDisplay['occupancy_status'] ?? $beneficiary->occupancy_status ?? '-',
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
                                    <div class="fw-semibold">{{ ($basicDisplay['recommendations'] ?? $beneficiary->recommendations) ?: '-' }}</div>
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
                                        <input name="{{ $field }}" class="form-control" value="{{ old($field, $field === 'visit_date' ? $beneficiary->{$field}?->format('Y-m-d') : ($basicDisplay[$field] ?? $beneficiary->{$field})) }}">
                                    </div>
                                @endforeach
                                @foreach (['address' => 'العنوان', 'social_notes' => 'ملاحظات اجتماعية', 'engineer_notes' => 'ملاحظات هندسية', 'recommendations' => 'التوصيات'] as $field => $label)
                                    <div class="col-md-6">
                                        <label class="form-label">{{ $label }}</label>
                                        <textarea name="{{ $field }}" class="form-control" rows="3">{{ old($field, $basicDisplay[$field] ?? $beneficiary->{$field}) }}</textarea>
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

                        @php
                            $lockedBoqSources = ['heks-main', 'heks-boq'];
                            $boqSectionSummaries = $beneficiary->boqItems
                                ->groupBy(fn ($item) => filled($item->section) ? (string) $item->section : 'بدون قسم')
                                ->map(fn ($items) => [
                                    'items_count' => $items->count(),
                                    'editable_count' => $items->reject(fn ($item) => in_array((string) $item->source, $lockedBoqSources, true))->count(),
                                    'total' => (float) $items->sum('total_price_ils'),
                                ]);
                        @endphp

                        <div class="table-responsive case-boq-table-wrap">
                            <table class="table table-row-dashed align-middle case-boq-table">
                                <colgroup>
                                    <col class="case-boq-col-code">
                                    <col class="case-boq-col-section">
                                    <col class="case-boq-col-item">
                                    <col class="case-boq-col-unit">
                                    <col class="case-boq-col-money">
                                    <col class="case-boq-col-quantity">
                                    <col class="case-boq-col-money">
                                    <col class="case-boq-col-notes">
                                    <col class="case-boq-col-actions">
                                </colgroup>
                                <thead>
                                <tr class="fw-bold text-muted">
                                    <th>الكود</th>
                                    <th>القسم</th>
                                    <th>البند</th>
                                    <th>الوحدة</th>
                                    <th>سعر الوحدة ILS</th>
                                    <th>الكمية</th>
                                    <th>الإجمالي ILS</th>
                                    <th>ملاحظات</th>
                                    <th>الإجراء</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse ($beneficiary->boqItems as $index => $item)
                                    @php
                                        $sectionName = filled($item->section) ? (string) $item->section : 'بدون قسم';
                                        $previousItem = $beneficiary->boqItems->get($index - 1);
                                        $previousSection = $previousItem ? (filled($previousItem->section) ? (string) $previousItem->section : 'بدون قسم') : null;
                                        $sectionSummary = $boqSectionSummaries->get($sectionName, ['items_count' => 0, 'editable_count' => 0, 'total' => 0]);
                                        $isLockedBoqItem = in_array((string) $item->source, $lockedBoqSources, true);
                                    @endphp

                                    @if ($index === 0 || $previousSection !== $sectionName)
                                        <tr class="case-boq-section-row">
                                            <td colspan="9">
                                                <div class="case-boq-section-strip d-flex flex-column flex-md-row justify-content-between gap-2">
                                                    <div>
                                                        <div class="fw-bold text-gray-800">{{ $sectionName }}</div>
                                                        <div class="text-muted small">البنود المستوردة من KoBo أو BOQ الأساسي للعرض فقط، والبنود اليدوية قابلة للتعديل.</div>
                                                    </div>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <span class="badge badge-light-primary">{{ number_format($sectionSummary['items_count']) }} بند</span>
                                                        <span class="badge badge-light-success">{{ number_format($sectionSummary['editable_count']) }} قابل للتعديل</span>
                                                        <span class="badge badge-light">{{ number_format((float) $sectionSummary['total'], 2) }} ILS</span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endif

                                    @if (! $isLockedBoqItem)
                                        <form id="update-boq-{{ $item->id }}" method="POST" action="{{ route('heks.boq-items.update', $item) }}">
                                            @csrf
                                            @method('PUT')
                                        </form>
                                        <form id="delete-boq-{{ $item->id }}" method="POST" action="{{ route('heks.boq-items.destroy', $item) }}">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    @endif

                                    <tr class="{{ $isLockedBoqItem ? 'is-readonly-row' : 'is-editable-row' }}">
                                        <td>
                                            @if ($isLockedBoqItem)
                                                <div class="case-boq-readonly-value case-boq-number">{{ $item->item_code ?: '-' }}</div>
                                            @else
                                                <input form="update-boq-{{ $item->id }}" name="item_code" class="form-control form-control-sm form-control-solid case-boq-number" value="{{ $item->item_code }}">
                                            @endif
                                        </td>
                                        <td>
                                            @if ($isLockedBoqItem)
                                                <div class="case-boq-readonly-value">{{ $item->section ?: '-' }}</div>
                                            @else
                                                <input form="update-boq-{{ $item->id }}" name="section" class="form-control form-control-sm form-control-solid" value="{{ $item->section }}">
                                            @endif
                                        </td>
                                        <td>
                                            @if ($isLockedBoqItem)
                                                <div class="fw-semibold case-boq-description">{{ $item->description }}</div>
                                            @else
                                                <textarea form="update-boq-{{ $item->id }}" name="description" class="form-control form-control-sm" rows="2" required>{{ $item->description }}</textarea>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($isLockedBoqItem)
                                                <div class="case-boq-readonly-value case-boq-number">{{ $item->unit ?: '-' }}</div>
                                            @else
                                                <input form="update-boq-{{ $item->id }}" name="unit" class="form-control form-control-sm form-control-solid" value="{{ $item->unit }}">
                                            @endif
                                        </td>
                                        <td>
                                            @if ($isLockedBoqItem)
                                                <div class="case-boq-readonly-value case-boq-number">{{ number_format((float) $item->unit_price_ils, 2) }}</div>
                                            @else
                                                <input form="update-boq-{{ $item->id }}" name="unit_price_ils" type="number" min="0" step="0.01" class="form-control form-control-sm case-boq-number" value="{{ $item->unit_price_ils }}" required>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($isLockedBoqItem)
                                                <div class="case-boq-readonly-value case-boq-number">{{ number_format((float) $item->quantity, 3) }}</div>
                                            @else
                                                <input form="update-boq-{{ $item->id }}" name="quantity" type="number" min="0" step="0.001" class="form-control form-control-sm case-boq-number" value="{{ $item->quantity }}" required>
                                            @endif
                                        </td>
                                        <td class="fw-bold case-boq-number text-success">{{ number_format((float) $item->total_price_ils, 2) }}</td>
                                        <td>
                                            @if ($isLockedBoqItem)
                                                <div class="case-boq-readonly-value">{{ $item->notes ?: '-' }}</div>
                                            @else
                                                <input form="update-boq-{{ $item->id }}" type="hidden" name="source" value="{{ $item->source }}">
                                                <input form="update-boq-{{ $item->id }}" name="notes" class="form-control form-control-sm" value="{{ $item->notes }}" placeholder="ملاحظة">
                                            @endif
                                        </td>
                                        <td>
                                            @if ($isLockedBoqItem)
                                                <span class="badge badge-light-primary w-100 justify-content-center">عرض فقط</span>
                                            @else
                                                <div class="d-flex flex-column gap-2">
                                                    <button form="update-boq-{{ $item->id }}" class="btn btn-sm btn-light-primary">حفظ</button>
                                                    <button form="delete-boq-{{ $item->id }}" class="btn btn-sm btn-light-danger">حذف</button>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="text-center text-muted py-10">لا توجد بنود جدول كميات لهذا المستفيد بعد.</td></tr>
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
                                    @php
                                        $completionPercentage = $followUp->completionPercentageForDisplay();
                                    @endphp
                                    <tr>
                                        <td><span class="badge badge-light-primary">{{ $followUp->visit_number ?? '-' }}</span></td>
                                        <td>{{ $followUp->visit_date?->format('Y-m-d') ?? '-' }}</td>
                                        <td>{{ $followUp->engineerUser?->name ?? $followUp->engineer_name ?? '-' }}</td>
                                        <td>{{ $followUp->workingConditionLabel() }}</td>
                                        <td>
                                            <div>{{ $completionPercentage !== null ? number_format($completionPercentage, 2).'%' : '-' }}</div>
                                            @if ($followUp->completed_amount_ils)
                                                <div class="text-muted small">{{ number_format((float) $followUp->completed_amount_ils, 2) }} ILS</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($followUp->boqItems->isNotEmpty())
                                                <a class="btn btn-sm btn-light-primary" href="{{ route('heks.follow-ups.boq', $followUp) }}">فتح BOQ الزيارة</a>
                                                <div class="text-muted small mt-1">{{ $followUp->boqItems->count() }} بند</div>
                                            @elseif ($followUp->boq_url)
                                                <a class="btn btn-sm btn-light" href="{{ $followUp->boq_url }}" target="_blank" rel="noopener">فتح رابط KoBo</a>
                                            @elseif ($followUp->boq_filename)
                                                <span class="badge badge-light-warning">ملف محفوظ فقط</span>
                                                <div class="text-muted small mt-1">{{ $followUp->boq_filename }}</div>
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
                            $hasCompleteScore = $latestScore?->social_score !== null && $latestScore?->technical_score !== null;
                            $displayTotalScore = $hasCompleteScore && $latestScore?->total_score !== null ? number_format((float) $latestScore->total_score, 2) : '-';
                            $displayClassification = $hasCompleteScore ? ($latestScore?->classification ?: '-') : '-';
                        @endphp

                        <div class="row g-4 mb-6">
                            @foreach ([
                                ['label' => 'التقييم الاجتماعي', 'value' => $latestScore?->social_score !== null ? number_format((float) $latestScore->social_score, 2) : '-', 'hint' => 'من 30', 'tone' => 'info'],
                                ['label' => 'التقييم الفني', 'value' => $latestScore?->technical_score !== null ? number_format((float) $latestScore->technical_score, 2) : '-', 'hint' => 'من 70', 'tone' => 'primary'],
                                ['label' => 'التقييم النهائي', 'value' => $displayTotalScore, 'hint' => 'Social + Technical', 'tone' => 'success'],
                                ['label' => 'التصنيف', 'value' => $displayClassification, 'hint' => $hasCompleteScore ? ($latestScore?->source ?: 'Scoring') : 'غير مكتمل', 'tone' => 'warning'],
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
                                            <div class="fs-4 fw-bold text-success">{{ number_format($answeredTechnicalRows->count()) }} من {{ number_format($technicalAssessmentRows->count()) }}</div>
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
                                        <div class="fw-bold">{{ $assignment->engineerUser?->name ?? $assignment->engineer_name ?? '-' }}</div>
                                        <div class="text-muted small">{{ $assignment->source }}</div>
                                        <div>قيمة العقد: {{ $assignment->contract_amount_ils ? number_format((float) $assignment->contract_amount_ils, 2) : '-' }} ILS</div>
                                    </div>
                                @empty
                                    <div class="text-muted">لا يوجد توزيع عمل.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="photos" role="tabpanel">
                            <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 mb-6">
                                <div>
                                    <h3 class="fs-4 fw-bold mb-1">صور المستفيد والوحدة السكنية</h3>
                                    <div class="text-muted">عرض الصور المستوردة من مرفقات KoBo والزيارات المرتبطة بهذا المستفيد.</div>
                                </div>
                                <div class="case-kpi py-3 min-w-125px">
                                    <div class="text-muted small">عدد الصور</div>
                                    <div class="fs-4 fw-bold text-primary">{{ number_format($imageAttachments->count()) }}</div>
                                </div>
                            </div>

                            <div class="row g-4">
                                @forelse ($imageAttachments as $image)
                                    @php
                                        $imageUrl = route('heks.beneficiaries.attachments.show', [$beneficiary, $image]);
                                    @endphp
                                    <div class="col-xxl-3 col-xl-4 col-md-6">
                                        <div class="photo-card">
                                            @if ($image->url || $image->filename)
                                                <a href="{{ $imageUrl }}" target="_blank" rel="noopener">
                                                    <img src="{{ $imageUrl }}" alt="{{ $image->filename ?? 'HEKS photo' }}" loading="lazy">
                                                </a>
                                            @else
                                                <div class="d-flex align-items-center justify-content-center bg-light text-muted" style="aspect-ratio: 4 / 3;">لا يوجد رابط صورة</div>
                                            @endif
                                            <div class="photo-card-body">
                                                <div class="photo-card-title">{{ $image->filename ?? 'صورة بدون اسم' }}</div>
                                                <div class="text-muted small mt-1">{{ $image->attachment_type ?? $image->source ?? '-' }}</div>
                                                @if ($image->url || $image->filename)
                                                    <a class="btn btn-sm btn-light-primary mt-3" href="{{ $imageUrl }}" target="_blank" rel="noopener">فتح الصورة</a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-12">
                                        <div class="border rounded p-6 text-muted text-center">لا توجد صور مرتبطة بهذا المستفيد.</div>
                                    </div>
                                @endforelse
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

                        <div class="kobo-paper">
                            <div class="kobo-paper-title">{{ $beneficiary->name ?: $beneficiary->code }}</div>
                            @forelse ($surveySections as $sectionIndex => $section)
                                <section class="kobo-pdf-section">
                                    <h4 class="kobo-pdf-section-title">{{ $section['title'] }}</h4>
                                    @foreach ($section['items'] as $item)
                                        @php
                                            $historyId = 'heks_survey_history_'.md5($item['source'].'|'.$item['field_key']);
                                            $historyCount = count($item['history']);
                                            $fieldType = $item['field_type'] ?? null;
                                            $hasChoices = !empty($item['choices']);
                                            $markerType = $fieldType === 'select_multiple' ? 'checkbox' : 'radio';
                                        @endphp
                                        <div class="kobo-pdf-item">
                                            <div class="kobo-pdf-question">{{ $item['question'] }}</div>

                                            @if ($hasChoices && in_array($fieldType, ['select_one', 'select_multiple'], true))
                                                <div class="kobo-pdf-choice-list" aria-label="خيارات السؤال">
                                                    @foreach ($item['choices'] as $choice)
                                                        <div class="kobo-pdf-choice {{ $choice['selected'] ? 'is-selected' : '' }}">
                                                            <span class="kobo-pdf-choice-marker {{ $markerType }} {{ $choice['selected'] ? 'selected' : '' }}"></span>
                                                            <span>{{ $choice['label'] }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="kobo-pdf-answer-line {{ filled($item['value']) ? '' : 'is-empty' }}">{{ filled($item['value']) ? $item['value'] : '-' }}</div>
                                            @endif

                                            @if (!empty($item['warning']))
                                                <div class="badge badge-light-warning mt-2">{{ $item['warning'] }}</div>
                                            @endif

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
                                    @endforeach
                                </section>
                            @empty
                                <div class="text-muted">لا توجد بيانات استبيان محفوظة لهذه الحالة.</div>
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

