@extends('layouts.app')

@section('title', 'استبيان مقترضي بنك التنمية الإسلامي')
@section('pageName', 'استبيان المقترضين')

@section('content')
    @php
        $isFormPage = $isFormPage ?? false;
        $riskColors = [
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'primary',
            'low' => 'success',
        ];
        $globalExchangeRate = $globalExchangeRate ?? 3.2;
        $canManagePricing = $canManagePricing ?? false;
    @endphp

    <style>
        .damage-assessment-borrowers-page .borrower-stat-card .card-body {
            min-height: 104px;
        }

        .damage-assessment-borrowers-page .borrower-command-center {
            background: linear-gradient(135deg, #1b4d89 0%, #1f6f8b 100%);
            border-radius: 1rem;
            box-shadow: 0 1rem 2.5rem rgba(31, 79, 137, 0.18);
            color: #fff;
            padding: 1.5rem;
        }

        .damage-assessment-borrowers-page .borrower-command-center .text-muted,
        .damage-assessment-borrowers-page .borrower-command-center .text-white-75 {
            color: rgba(255, 255, 255, 0.78) !important;
        }

        .damage-assessment-borrowers-page .borrower-quick-filter {
            border: 1px solid var(--bs-gray-200);
            border-radius: 0.75rem;
            color: var(--bs-gray-700);
            font-weight: 600;
            padding: 0.6rem 0.8rem;
            text-align: start;
            transition: 0.15s ease;
            width: 100%;
        }

        .damage-assessment-borrowers-page .borrower-quick-filter:hover,
        .damage-assessment-borrowers-page .borrower-quick-filter.is-active {
            background: var(--bs-primary-light);
            border-color: var(--bs-primary);
            color: var(--bs-primary);
        }

        .damage-assessment-borrowers-page .borrower-filter-bar {
            background: var(--bs-body-bg);
            border: 1px solid var(--bs-gray-200);
            border-radius: 0.9rem;
            box-shadow: 0 0.3rem 1rem rgba(15, 23, 42, 0.04);
            padding: 0.6rem;
        }

        .damage-assessment-borrowers-page .borrower-worklist-toolbar {
            flex: 0 0 auto;
            width: auto;
        }

        .damage-assessment-borrowers-page .borrower-worklist-toolbar .borrower-filter-bar {
            flex-wrap: nowrap !important;
        }

        .damage-assessment-borrowers-page .borrower-filter-bar .form-control {
            min-width: 15rem;
        }

        .damage-assessment-borrowers-page .borrower-filter-bar .select2-container {
            min-width: 12rem;
        }

        .damage-assessment-borrowers-page .borrower-filter-bar .select2-selection {
            min-height: 38px;
        }

        .damage-assessment-borrowers-page .borrower-filter-bar .select2-selection__rendered {
            line-height: 38px;
        }

        .damage-assessment-borrowers-page .borrower-repeat-row {
            overflow: hidden;
        }

        .damage-assessment-borrowers-page .borrowers-mobile-list {
            display: none;
        }

        .damage-assessment-borrowers-page .borrower-pricing-cell {
            background: var(--bs-gray-100);
            border: 1px solid var(--bs-gray-200);
            border-radius: 0.65rem;
            box-sizing: border-box;
            min-height: 10.9rem;
            min-width: 13rem;
            padding: 0.75rem;
            width: 13rem;
        }

        .damage-assessment-borrowers-page .borrower-pricing-amounts {
            display: grid;
            gap: 0.35rem;
        }

        .damage-assessment-borrowers-page .borrower-pricing-amounts > span {
            align-items: center;
            display: flex;
            justify-content: space-between;
            gap: 1rem;
        }

        .damage-assessment-borrowers-page .borrower-pricing-action {
            width: 100%;
        }

        .damage-assessment-borrowers-page .borrower-loan-summary {
            background: var(--bs-gray-100);
            border: 1px solid var(--bs-gray-200);
            border-radius: 0.65rem;
            box-sizing: border-box;
            min-height: 10.9rem;
            min-width: 13rem;
            padding: 0.75rem;
            width: 13rem;
        }

        .damage-assessment-borrowers-page .borrower-loan-summary-grid {
            display: grid;
            gap: 0.45rem 0.75rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 0.65rem;
        }

        .damage-assessment-borrowers-page .borrower-loan-summary-grid span {
            color: var(--bs-gray-600);
            display: block;
            font-size: 0.75rem;
        }

        .damage-assessment-borrowers-page .borrower-loan-summary-grid strong {
            color: var(--bs-gray-800);
            display: block;
            font-size: 0.8rem;
            margin-top: 0.1rem;
        }

        .damage-assessment-borrowers-page .borrowers-import-dropzone {
            border: 1px dashed var(--bs-primary);
            border-radius: 0.95rem;
            background: var(--bs-primary-light);
            cursor: pointer;
            transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
        }

        .damage-assessment-borrowers-page .borrowers-import-dropzone:hover,
        .damage-assessment-borrowers-page .borrowers-import-dropzone.is-active {
            border-color: var(--bs-primary);
            background: #f1faff;
            box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.08);
        }

        .damage-assessment-borrowers-page .borrowers-import-file-name {
            max-width: 100%;
            word-break: break-word;
        }

        .damage-assessment-borrowers-page .borrowers-import-preview {
            background: var(--bs-gray-100);
            border: 1px solid var(--bs-gray-200);
            border-radius: 0.85rem;
            display: none;
            padding: 1rem;
        }

        .damage-assessment-borrowers-page .borrowers-import-preview.is-visible {
            display: block;
        }

        .damage-assessment-borrowers-page .borrower-create-layout {
            align-items: flex-start;
        }

        .damage-assessment-borrowers-page .borrower-create-card,
        .damage-assessment-borrowers-page .borrower-analysis-card {
            border: 1px solid var(--bs-gray-200);
            box-shadow: 0 0.85rem 2.4rem rgba(15, 23, 42, 0.06);
        }

        .damage-assessment-borrowers-page .borrower-create-card > .card-header {
            background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), 0.08), rgba(var(--bs-info-rgb), 0.04));
            border-bottom: 1px solid var(--bs-gray-200);
            padding: 1.5rem 1.75rem;
        }

        .damage-assessment-borrowers-page .borrower-create-hero {
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            width: 100%;
        }

        .damage-assessment-borrowers-page .borrower-create-hero-note {
            max-width: 18rem;
        }

        .damage-assessment-borrowers-page .borrower-form-progress {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-top: 1.25rem;
            width: 100%;
        }

        .damage-assessment-borrowers-page .borrower-form-progress-item {
            align-items: center;
            background: var(--bs-body-bg);
            border: 1px solid var(--bs-gray-200);
            border-radius: 0.95rem;
            color: var(--bs-gray-700);
            display: flex;
            font-weight: 600;
            gap: 0.6rem;
            padding: 0.8rem 0.9rem;
            text-align: start;
            transition: 0.15s ease;
            width: 100%;
        }

        .damage-assessment-borrowers-page button.borrower-form-progress-item:hover,
        .damage-assessment-borrowers-page button.borrower-form-progress-item:focus-visible {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.08);
            outline: 0;
        }

        .damage-assessment-borrowers-page .borrower-form-progress-item span {
            align-items: center;
            background: var(--bs-primary-light);
            border-radius: 999px;
            color: var(--bs-primary);
            display: inline-flex;
            flex: 0 0 auto;
            font-size: 0.8rem;
            height: 1.85rem;
            justify-content: center;
            width: 1.85rem;
        }

        .damage-assessment-borrowers-page .borrower-analysis-column {
            position: sticky;
            top: 6rem;
        }

        .damage-assessment-borrowers-page .borrower-survey-form .form-control,
        .damage-assessment-borrowers-page .borrower-survey-form .form-select {
            background-color: var(--bs-gray-100);
            border-color: var(--bs-gray-100);
            border-radius: 0.85rem;
            color: var(--bs-gray-700);
            min-height: 46px;
            transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .damage-assessment-borrowers-page .borrower-survey-form textarea.form-control {
            min-height: 86px;
            resize: vertical;
        }

        .damage-assessment-borrowers-page .borrower-survey-form .form-control:focus,
        .damage-assessment-borrowers-page .borrower-survey-form .form-select:focus {
            background-color: var(--bs-body-bg);
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.08);
            color: var(--bs-gray-800);
        }

        .damage-assessment-borrowers-page .borrower-survey-form .form-label {
            color: var(--bs-gray-700);
            font-weight: 600;
            margin-bottom: 0.45rem;
        }

        .damage-assessment-borrowers-page .borrower-survey-form .form-check-inline {
            align-items: center;
            background: var(--bs-gray-100);
            border: 1px solid var(--bs-gray-200);
            border-radius: 0.85rem;
            display: inline-flex;
            flex-direction: row-reverse;
            gap: 0.45rem;
            margin-inline-end: 0;
            padding: 0.75rem 0.9rem;
        }

        .damage-assessment-borrowers-page .borrower-survey-form .form-check-inline .form-check-input {
            flex: 0 0 auto;
            float: none;
            margin: 0;
        }

        .damage-assessment-borrowers-page .borrower-survey-form .form-check-inline .form-check-label {
            color: var(--bs-gray-700);
            line-height: 1.4;
        }

        .damage-assessment-borrowers-page .borrower-survey-form .borrower-repeat-row {
            background: var(--bs-gray-100);
            border: 1px dashed var(--bs-gray-300);
            border-radius: 0.85rem;
            padding: 0.65rem;
        }

        .damage-assessment-borrowers-page .borrower-form-section-title {
            align-items: center;
            background: var(--bs-light);
            border-radius: 0.95rem;
            color: var(--bs-gray-800);
            display: flex;
            gap: 0.75rem;
            padding: 0.95rem 1.1rem;
        }

        .damage-assessment-borrowers-page .borrower-form-section-title::before {
            background: var(--bs-primary);
            border-radius: 999px;
            content: "";
            height: 1.5rem;
            width: 0.35rem;
        }

        @media (max-width: 1199.98px) {
            .damage-assessment-borrowers-page .borrower-analysis-column {
                position: static;
            }

            .damage-assessment-borrowers-page .borrower-worklist-toolbar .borrower-filter-bar {
                flex-wrap: wrap !important;
            }
        }

        @media (max-width: 767.98px) {
            .damage-assessment-borrowers-page {
                margin-inline: -0.75rem;
            }

            .damage-assessment-borrowers-page .row {
                --bs-gutter-x: 1rem;
                --bs-gutter-y: 1rem;
            }

            .damage-assessment-borrowers-page .card-header {
                flex-direction: column;
                align-items: stretch !important;
                gap: 0.75rem;
                padding: 1rem 1rem 0;
            }

            .damage-assessment-borrowers-page .card-title {
                margin: 0;
            }

            .damage-assessment-borrowers-page .borrower-create-card > .card-header {
                padding: 1rem;
            }

            .damage-assessment-borrowers-page .borrower-create-hero {
                flex-direction: column;
            }

            .damage-assessment-borrowers-page .borrower-create-hero-note {
                max-width: none;
            }

            .damage-assessment-borrowers-page .borrower-form-progress {
                grid-template-columns: 1fr;
            }

            .damage-assessment-borrowers-page .card-title h3,
            .damage-assessment-borrowers-page h4 {
                font-size: 1.05rem;
                line-height: 1.6;
            }

            .damage-assessment-borrowers-page .card-body {
                padding: 1rem;
            }

            .damage-assessment-borrowers-page .borrower-stat-card .card-body {
                min-height: 86px;
                padding: 0.875rem;
            }

            .damage-assessment-borrowers-page .borrower-stat-card .fs-2hx {
                font-size: 1.75rem !important;
            }

            .damage-assessment-borrowers-page .form-label {
                margin-bottom: 0.35rem;
                font-size: 0.925rem;
            }

            .damage-assessment-borrowers-page .form-control,
            .damage-assessment-borrowers-page .form-select,
            .damage-assessment-borrowers-page .btn {
                min-height: 44px;
            }

            .damage-assessment-borrowers-page .form-check-inline {
                display: flex;
                width: 100%;
                margin-inline-end: 0;
            }

            .damage-assessment-borrowers-page .card-toolbar,
            .damage-assessment-borrowers-page .card-toolbar .form-control {
                width: 100% !important;
            }

            .damage-assessment-borrowers-page .borrower-repeat-row.d-flex,
            .damage-assessment-borrowers-page .borrower-repeat-row .d-flex {
                flex-direction: column;
            }

            .damage-assessment-borrowers-page #analysisPanel .d-flex {
                flex-direction: column;
                align-items: flex-start !important;
            }

            .damage-assessment-borrowers-page [data-add-row],
            .damage-assessment-borrowers-page #borrowerSubmitBtn {
                width: 100%;
            }

            .damage-assessment-borrowers-page .table-responsive {
                display: none;
            }

            .damage-assessment-borrowers-page .borrowers-mobile-list {
                display: grid;
                gap: 0.75rem;
            }

            .damage-assessment-borrowers-page .borrower-mobile-card {
                border: 1px solid #e4e6ef;
                border-radius: 0.65rem;
                padding: 0.9rem;
                background: #fff;
            }

            .damage-assessment-borrowers-page .borrower-mobile-meta {
                display: grid;
                gap: 0.45rem;
                margin-top: 0.75rem;
            }

            .damage-assessment-borrowers-page .borrower-mobile-meta-item {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 0.75rem;
                color: #5e6278;
                font-size: 0.9rem;
            }

            .damage-assessment-borrowers-page .borrower-mobile-meta-item span:last-child {
                color: #181c32;
                font-weight: 600;
                text-align: end;
            }

            .damage-assessment-borrowers-page .borrower-pricing-cell {
                min-height: 0;
                min-width: 0;
                width: 100%;
            }

            .damage-assessment-borrowers-page .borrower-loan-summary {
                min-height: 0;
                min-width: 0;
                width: 100%;
            }
        }
    </style>

    <div class="damage-assessment-borrowers-page">
    @if (! $isFormPage)
    <section class="borrower-command-center mb-6">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-4">
            <div>
                <div class="text-white-75 fw-semibold mb-2">إدارة القروض والتقييمات الميدانية</div>
                <h2 class="fw-bold mb-2 text-white">مساحة عمل المقترضين</h2>
                <p class="mb-0 text-white-75">ابحث عن الحالة، حدّد أولويتها، ثم انتقل للتقييم أو التسعير من مكان واحد.</p>
            </div>
            <div class="d-flex flex-wrap gap-3">
                <button type="button" class="btn btn-light-primary" data-bs-toggle="modal" data-bs-target="#borrowersImportModal">
                    استيراد من Excel
                </button>
                <a href="{{ route('damage-assessment-borrowers.create') }}" class="btn btn-light">
                    تعبئة استبيان جديد
                </a>
            </div>
        </div>
    </section>

    <div class="row g-5 mb-6" id="borrowerStats">
        <div class="col-md-2 col-6">
            <div class="card card-flush h-100 borrower-stat-card">
                <div class="card-body">
                    <div class="text-gray-500 fw-semibold">الإجمالي</div>
                    <div class="fs-2hx fw-bold" data-stat="total">{{ $stats['total'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card card-flush h-100 borrower-stat-card">
                <div class="card-body">
                    <div class="text-gray-500 fw-semibold">حرج</div>
                    <div class="fs-2hx fw-bold text-danger" data-stat="critical">{{ $stats['critical'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card card-flush h-100 borrower-stat-card">
                <div class="card-body">
                    <div class="text-gray-500 fw-semibold">مرتفع</div>
                    <div class="fs-2hx fw-bold text-warning" data-stat="high">{{ $stats['high'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card card-flush h-100 borrower-stat-card">
                <div class="card-body">
                    <div class="text-gray-500 fw-semibold">نازحون</div>
                    <div class="fs-2hx fw-bold text-info" data-stat="displaced">{{ $stats['displaced'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card card-flush h-100 borrower-stat-card">
                <div class="card-body">
                    <div class="text-gray-500 fw-semibold">ضرر كلي</div>
                    <div class="fs-2hx fw-bold text-danger" data-stat="destroyed">{{ $stats['destroyed'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card card-flush h-100 borrower-stat-card">
                <div class="card-body">
                    <div class="text-gray-500 fw-semibold">ضرر جزئي</div>
                    <div class="fs-2hx fw-bold text-primary" data-stat="partial_damage">{{ $stats['partial_damage'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card card-flush h-100 borrower-stat-card">
                <div class="card-body">
                    <div class="text-gray-500 fw-semibold">كفلاء غير فعالين</div>
                    <div class="fs-2hx fw-bold text-warning" data-stat="inactive_guarantors">{{ $stats['inactive_guarantors'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-6" aria-label="فلاتر الأولوية السريعة">
        <div class="col-xl col-lg-4 col-sm-6">
            <button type="button" class="borrower-quick-filter is-active" data-risk-filter="">
                كل الحالات <span class="text-muted d-block fs-8 fw-normal">عرض قائمة العمل الكاملة</span>
            </button>
        </div>
        <div class="col-xl col-lg-4 col-sm-6">
            <button type="button" class="borrower-quick-filter" data-risk-filter="critical">
                أولوية حرجة <span class="text-muted d-block fs-8 fw-normal">تحتاج متابعة فورية</span>
            </button>
        </div>
        <div class="col-xl col-lg-4 col-sm-6">
            <button type="button" class="borrower-quick-filter" data-risk-filter="high">
                أولوية مرتفعة <span class="text-muted d-block fs-8 fw-normal">تحتاج مراجعة قريبة</span>
            </button>
        </div>
        <div class="col-xl col-lg-4 col-sm-6">
            <button type="button" class="borrower-quick-filter" data-risk-filter="medium">
                قيد المراجعة <span class="text-muted d-block fs-8 fw-normal">حالات بمتابعة متوسطة</span>
            </button>
        </div>
        <div class="col-xl col-lg-4 col-sm-6">
            <button type="button" class="borrower-quick-filter" data-damage-filter="destroyed">
                ضرر كلي <span class="text-muted d-block fs-8 fw-normal">وحدات مهدمة كليًا</span>
            </button>
        </div>
        <div class="col-xl col-lg-4 col-sm-6">
            <button type="button" class="borrower-quick-filter" data-damage-filter="partial">
                ضرر جزئي <span class="text-muted d-block fs-8 fw-normal">أضرار طفيفة أو بليغة</span>
            </button>
        </div>
    </div>
    @endif

    @if (! $isFormPage && session('success'))
        <div class="alert alert-success d-flex align-items-center gap-3 mb-6">
            <i class="ki-duotone ki-check-circle fs-2x text-success">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="d-flex flex-wrap justify-content-end gap-3 mb-6">
        @if ($isFormPage)
            <a href="{{ route('damage-assessment-borrowers.index') }}" class="btn btn-light-primary">
                العودة إلى الاستبيانات
            </a>
        @else
            @if ($canManagePricing)
                <button type="button" class="btn btn-light-success" data-bs-toggle="modal" data-bs-target="#globalExchangeRateModal">
                    سعر الصرف الموحد: {{ number_format((float) $globalExchangeRate, 4) }}
                </button>
            @endif
        @endif
    </div>

    <div class="row g-6 borrower-create-layout">
        @if ($isFormPage)
        <div class="col-12 col-xxl-8 col-xl-9">
            <div class="card card-flush borrower-create-card">
                <div class="card-header align-items-stretch">
                    <div class="card-title flex-column align-items-stretch w-100">
                        <div class="borrower-create-hero">
                            <div>
                                <span class="badge badge-light-primary mb-3">استبيان ميداني</span>
                                <h3 class="fw-bold mb-0">تعبئة استبيان المقترض</h3>
                                <div class="text-muted fs-6 mt-2">رتّب البيانات حسب الأقسام التالية، ثم احفظ الاستبيان لعرض درجة الخطورة مباشرة.</div>
                            </div>
                            <div class="borrower-create-hero-note alert alert-light mb-0 py-3 px-4">
                                <div class="fw-semibold text-gray-800">مناسب للجوال</div>
                                <div class="text-muted fs-7">الحقول ستظهر كسطر واحد واضح على الشاشات الصغيرة لتسهيل التعبئة الميدانية.</div>
                            </div>
                        </div>
                        <div class="borrower-form-progress" aria-label="أقسام الاستبيان">
                            <button type="button" class="borrower-form-progress-item" data-scroll-to-section="borrowerBasics"><span>1</span> بيانات المقترض</button>
                            <button type="button" class="borrower-form-progress-item" data-scroll-to-section="guarantors"><span>2</span> الكفلاء</button>
                            <button type="button" class="borrower-form-progress-item" data-scroll-to-section="displacement"><span>3</span> النزوح والسكن</button>
                            <button type="button" class="borrower-form-progress-item" data-scroll-to-section="housingUnit"><span>4</span> الوحدة السكنية</button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-5 p-lg-8">
                    <form id="borrowerSurveyForm" class="row g-5 borrower-survey-form" data-offline-sync="true">
                        @csrf

                        <div class="col-12" id="borrowerBasics"><h4 class="fw-bold borrower-form-section-title mb-0">بيانات المقترض الأساسية</h4></div>

                        <div class="col-md-6">
                            <label class="form-label required">اسم المقترض رباعي</label>
                            <input class="form-control" name="borrower_name" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">رقم الهوية</label>
                            <input class="form-control" name="borrower_id_number">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">رقم الاستمارة</label>
                            <input class="form-control" name="form_number">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">تاريخ ووقت الزيارة</label>
                            <input type="datetime-local" class="form-control" name="surveyed_at">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">عدد أفراد الأسرة</label>
                            <input type="number" min="0" class="form-control" name="family_members_count">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">الحالة الاجتماعية</label>
                            <select class="form-select" name="marital_status">
                                <option value="">اختر</option>
                                <option value="married">متزوج/ة</option>
                                <option value="single">أعزب/عزباء</option>
                                <option value="widowed">أرمل/ة</option>
                                <option value="divorced">مطلق/ة</option>
                                <option value="abandoned">مهجور/ة</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">هل المقترض على قيد الحياة؟</label>
                            <select class="form-select" name="is_borrower_alive" required>
                                <option value="1">نعم</option>
                                <option value="0">لا</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">اسم الزوج/ة</label>
                            <input class="form-control" name="spouse_name">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">هوية الزوج/ة</label>
                            <input class="form-control" name="spouse_id_number">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">الوضع الوظيفي للمقترض</label>
                            <select class="form-select" name="employment_status">
                                <option value="">اختر</option>
                                <option value="working">على رأس عمله</option>
                                <option value="retired">متقاعد</option>
                                <option value="not_working">لا يعمل</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">مؤشرات الهشاشة داخل الأسرة</label>
                            <div class="d-flex flex-wrap gap-4">
                                @foreach([
                                    'martyrs' => 'يوجد شهداء',
                                    'injured' => 'يوجد مصابين',
                                    'disabled' => 'يوجد أشخاص ذوي إعاقة',
                                    'elderly' => 'يوجد كبار سن',
                                    'none' => 'ليس مما سبق',
                                ] as $value => $label)
                                    <label class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="vulnerability_types[]" value="{{ $value }}">
                                        <span class="form-check-label">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="separator my-3"></div>
                        <div class="col-12" id="guarantors"><h4 class="fw-bold borrower-form-section-title mb-0">الكفلاء</h4></div>

                        <div class="col-md-3">
                            <label class="form-label">عدد الكفلاء</label>
                            <input type="number" min="0" class="form-control" name="guarantors_count">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">هل جميع الكفلاء على قيد الحياة؟</label>
                            <select class="form-select" name="guarantors_alive_status">
                                <option value="">اختر</option>
                                <option value="yes">نعم</option>
                                <option value="no">لا</option>
                                <option value="none">لا يوجد</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الوضع الوظيفي للكفلاء</label>
                            <div class="d-flex flex-wrap gap-4">
                                @foreach([
                                    'all_working' => 'جميعهم على رأس عملهم',
                                    'retired' => 'يوجد كفيل متقاعد',
                                    'lost_job' => 'يوجد كفيل فقد عمله',
                                ] as $value => $label)
                                    <label class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="guarantors_employment_statuses[]" value="{{ $value }}">
                                        <span class="form-check-label">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">أسماء الكفلاء المتوفين</label>
                            <div id="deceasedGuarantorsRepeater" class="d-grid gap-2"></div>
                            <button type="button" class="btn btn-sm btn-light-primary mt-2" data-add-row="deceasedGuarantorsRepeater">إضافة كفيل متوفى</button>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">كفلاء متقاعدين/فاقدين عمل</label>
                            <div id="affectedGuarantorsRepeater" class="d-grid gap-2"></div>
                            <button type="button" class="btn btn-sm btn-light-primary mt-2" data-add-row="affectedGuarantorsRepeater">إضافة كفيل متأثر</button>
                        </div>

                        <div class="separator my-3"></div>
                        <div class="col-12" id="displacement"><h4 class="fw-bold borrower-form-section-title mb-0">النزوح والسكن الحالي</h4></div>

                        <div class="col-md-4">
                            <label class="form-label">حالة النزوح</label>
                            <select class="form-select" name="displacement_status">
                                <option value="">اختر</option>
                                <option value="displaced">نازح</option>
                                <option value="returned">عائد إلى منزله</option>
                                <option value="resident">مقيم</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">المحافظة النازح إليها</label>
                            <select class="form-select" name="displaced_to_governorate">
                                <option value="">اختر</option>
                                <option value="north">محافظة الشمال</option>
                                <option value="gaza">محافظة غزة</option>
                                <option value="middle">محافظة الوسطى</option>
                                <option value="khan_younis">محافظة خانيونس</option>
                                <option value="rafah">محافظة رفح</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">رقم التواصل 1</label>
                            <input class="form-control" name="phone_primary">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">رقم التواصل 2</label>
                            <input class="form-control" name="phone_secondary">
                        </div>
                        <div class="col-12">
                            <label class="form-label">عنوان السكن الحالي</label>
                            <textarea class="form-control" name="current_residence_address" rows="2"></textarea>
                        </div>

                        <div class="separator my-3"></div>
                        <div class="col-12" id="housingUnit"><h4 class="fw-bold borrower-form-section-title mb-0">بيانات الوحدة السكنية المستهدفة بالقرض</h4></div>

                        <div class="col-md-6">
                            <label class="form-label">عنوان الوحدة</label>
                            <textarea class="form-control" name="loan_unit_address" rows="2"></textarea>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">المساحة م2</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="loan_unit_area">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">رقم القطعة</label>
                            <input class="form-control" name="parcel_number">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">رقم القسيمة</label>
                            <input class="form-control" name="plot_number">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">وضع الأشخاص داخل الشقة</label>
                            <select class="form-select" name="loan_unit_occupancy_status">
                                <option value="">اختر</option>
                                <option value="owner_borrower">المقترض نفسه</option>
                                <option value="tenants">مستأجرين</option>
                                <option value="displaced_hosted">نازحين أو مستضافين</option>
                                <option value="buyers">مشترين</option>
                                <option value="heirs">وارثين</option>
                                <option value="none_due_damage">لا يوجد بسبب الضرر</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الوضع الإنشائي للوحدة</label>
                            <select class="form-select" name="loan_unit_damage_status">
                                <option value="">اختر</option>
                                <option value="destroyed">هدم كلي</option>
                                <option value="severe_uninhabitable">متضرر بليغ غير صالح للسكن</option>
                                <option value="severe_habitable">متضرر بليغ صالح للسكن</option>
                                <option value="minor">أضرار طفيفة</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">الأسر التي تعيش داخل الوحدة</label>
                            <div id="householdsRepeater" class="d-grid gap-3"></div>
                            <button type="button" class="btn btn-sm btn-light-primary mt-2" data-add-row="householdsRepeater">إضافة أسرة</button>
                        </div>

                        <div class="col-12">
                            <label class="form-label">ملاحظات إضافية</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>

                        <div class="col-12 d-grid d-sm-flex justify-content-sm-end">
                            <button type="submit" class="btn btn-primary btn-lg" id="borrowerSubmitBtn">
                                <span class="indicator-label">حفظ الاستبيان وتحليل الحالة</span>
                                <span class="indicator-progress">جاري الحفظ...
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        <div class="{{ $isFormPage ? 'col-12 col-xxl-4 col-xl-9 borrower-analysis-column' : 'col-12' }}">
            @if ($isFormPage)
            <div class="card card-flush mb-6 borrower-analysis-card">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="fw-bold mb-0">نتيجة التحليل</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div id="analysisPanel" class="alert alert-light-primary mb-0">
                        احفظ الاستبيان لعرض درجة الخطورة وأسبابها.
                    </div>
                </div>
            </div>
            @else

            <div class="card card-flush">
                <div class="card-header align-items-center gap-3">
                    <div class="card-title">
                        <div>
                            <h3 class="fw-bold mb-1">قائمة العمل</h3>
                            <div class="text-muted fs-7">رتّب الأولويات ثم افتح الحالة لاتخاذ الإجراء التالي.</div>
                        </div>
                    </div>
                    <div class="card-toolbar borrower-worklist-toolbar">
                        <div class="borrower-filter-bar d-flex flex-wrap align-items-center gap-2">
                            <input type="search" class="form-control form-control-sm w-200px" id="borrowerSearch" placeholder="بحث بالاسم أو الهوية أو الجوال">
                            <select class="form-select form-select-solid form-select-sm borrower-filter-select" id="borrowerRiskFilter" aria-label="تصفية حسب مستوى الخطورة" data-control="select2" data-hide-search="true" data-placeholder="كل مستويات الخطورة">
                                <option value="">كل مستويات الخطورة</option>
                                <option value="critical">حرج</option>
                                <option value="high">مرتفع</option>
                                <option value="medium">متوسط</option>
                                <option value="low">منخفض</option>
                            </select>
                            <select class="form-select form-select-solid form-select-sm borrower-filter-select" id="borrowerDamageFilter" aria-label="تصفية حسب نوع الضرر" data-control="select2" data-hide-search="true" data-placeholder="كل أنواع الضرر">
                                <option value="">كل أنواع الضرر</option>
                                <option value="destroyed">ضرر كلي</option>
                                <option value="partial">ضرر جزئي</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="borrowersMobileList" class="borrowers-mobile-list mb-2"></div>
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th>المقترض</th>
                                    <th>النزوح</th>
                                    <th>الوحدة</th>
                                    <th>القرض</th>
                                    <th>BOQ</th>
                                    <th>صور</th>
                                    <th>الخطورة</th>
                                </tr>
                            </thead>
                            <tbody id="borrowersTableBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted">جاري التحميل...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
    </div>

    @if (! $isFormPage)
        <div class="modal fade" id="borrowersImportModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form id="borrowersImportForm" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h2 class="fw-bold mb-0">استيراد بيانات المستفيدين</h2>
                            <button type="button" class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal" aria-label="إغلاق">
                                <i class="ki-duotone ki-cross fs-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </button>
                        </div>
                        <div class="modal-body">
                            <label class="required fw-semibold fs-6 mb-2 d-block" for="borrowersFile">ملف Excel</label>
                            <label class="dropzone borrowers-import-dropzone d-flex align-items-center p-8 mb-5" for="borrowersFile" id="borrowersImportDropzone">
                                <input type="file" name="borrowers_file" id="borrowersFile" class="d-none" accept=".xlsx" required>
                                <span class="symbol symbol-50px me-5">
                                    <span class="symbol-label bg-light-success">
                                        <i class="ki-duotone ki-file-up fs-2x text-success">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </span>
                                </span>
                                <span class="d-flex flex-column text-start">
                                    <span class="fs-5 fw-bold text-gray-900">اسحب ملف Excel هنا أو اضغط للاختيار</span>
                                    <span class="fs-7 text-gray-600 mt-1 borrowers-import-file-name" id="borrowersImportFileName">صيغة XLSX فقط، حتى 20 ميغابايت.</span>
                                </span>
                            </label>
                            <label class="fw-semibold fs-6 mb-2 d-block" for="boqFile">ملف أسعار BOQ (اختياري)</label>
                            <input type="file" name="boq_file" id="boqFile" class="form-control form-control-solid mb-3" accept=".xlsx">
                            <div class="form-text mb-5">ارفع ملف BOQ-Analysis Price عند الحاجة لحساب إجمالي البنود تلقائيًا.</div>
                            <div class="form-text">سيتم تجاوز الصفوف المكررة أو غير المكتملة تلقائيًا، ثم تحديث الإحصائيات بعد الاستيراد.</div>
                            <div class="borrowers-import-preview mt-5" id="borrowersImportPreview" aria-live="polite"></div>
                            <div class="invalid-feedback d-block mt-3" id="borrowersImportError" style="display: none;"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                            <button type="button" class="btn btn-light-primary" id="borrowersPreviewBtn">
                                <span class="indicator-label">معاينة الملف</span>
                                <span class="indicator-progress">جارٍ تحليل الملف...
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                </span>
                            </button>
                            <button type="submit" class="btn btn-primary" id="borrowersImportSubmitBtn">
                                <span class="indicator-label">تأكيد الاستيراد</span>
                                <span class="indicator-progress">جاري الاستيراد...
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if ($canManagePricing)
            <div class="modal fade" id="globalExchangeRateModal" tabindex="-1" aria-labelledby="globalExchangeRateModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('damage-assessment-borrowers.exchange-rate.update') }}">
                            @csrf
                            @method('PUT')
                            <div class="modal-header">
                                <h2 class="fw-bold mb-0" id="globalExchangeRateModalLabel">سعر الصرف الموحد</h2>
                                <button type="button" class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal" aria-label="إغلاق">
                                    <i class="ki-duotone ki-cross fs-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </button>
                            </div>
                            <div class="modal-body">
                                <label class="form-label fw-semibold" for="globalExchangeRateInput">الدولار / شيكل</label>
                                <input type="text" inputmode="decimal" name="exchange_rate" id="globalExchangeRateInput" value="{{ old('exchange_rate', $globalExchangeRate) }}" class="form-control form-control-lg" dir="ltr" required>
                                <div class="form-text mt-3">سيعاد احتساب إجماليات الشيكل وأسعار الوحدة بالشيكل لكل استبيانات المقترضين. قيم الدولار لا تتغير.</div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                                <button type="submit" class="btn btn-success">تطبيق السعر الموحد</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endif
@endsection

@section('script')
    <script>
        const borrowerRoutes = {
            data: @json(route('damage-assessment-borrowers.data')),
            store: @json(route('damage-assessment-borrowers.store')),
            import: @json(route('damage-assessment-borrowers.import')),
            previewImport: @json(route('damage-assessment-borrowers.import.preview')),
        };

        const riskClasses = {
            critical: 'danger',
            high: 'warning',
            medium: 'primary',
            low: 'success',
        };
        const borrowersOfflineRowsKey = 'phc.damageAssessmentBorrowers.rows';
        const borrowersPendingRowsKey = 'phc.damageAssessmentBorrowers.pendingRows';
        const canManageBorrowerPricing = @json($canManagePricing);

        function csrfToken() {
            return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        }

        function repeaterRow(type) {
            if (type === 'householdsRepeater') {
                return `<div class="border rounded p-3 borrower-repeat-row">
                    <div class="row g-3">
                        <div class="col-md-3"><input class="form-control" data-field="head_name" placeholder="اسم رب الأسرة"></div>
                        <div class="col-md-2"><input class="form-control" data-field="id_number" placeholder="رقم الهوية"></div>
                        <div class="col-md-2"><input type="number" min="0" class="form-control" data-field="members_count" placeholder="عدد الأفراد"></div>
                        <div class="col-md-2"><input class="form-control" data-field="phone" placeholder="رقم التواصل"></div>
                        <div class="col-md-2">
                            <select class="form-select" data-field="employment_status">
                                <option value="">وظيفي</option>
                                <option value="working">على رأس عمله</option>
                                <option value="retired">متقاعد</option>
                                <option value="not_working">لا يعمل</option>
                            </select>
                        </div>
                        <div class="col-md-1"><button type="button" class="btn btn-sm btn-light-danger w-100" data-remove-row>حذف</button></div>
                    </div>
                </div>`;
            }

            if (type === 'affectedGuarantorsRepeater') {
                return `<div class="d-flex gap-2 borrower-repeat-row">
                    <input class="form-control" data-field="name" placeholder="اسم الكفيل">
                    <select class="form-select" data-field="status">
                        <option value="retired">متقاعد</option>
                        <option value="lost_job">فقد عمله</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-light-danger" data-remove-row>حذف</button>
                </div>`;
            }

            return `<div class="d-flex gap-2 borrower-repeat-row">
                <input class="form-control" data-field="name" placeholder="اسم الكفيل المتوفى">
                <button type="button" class="btn btn-sm btn-light-danger" data-remove-row>حذف</button>
            </div>`;
        }

        document.querySelectorAll('[data-add-row]').forEach((button) => {
            button.addEventListener('click', () => {
                const target = document.getElementById(button.dataset.addRow);
                target.insertAdjacentHTML('beforeend', repeaterRow(button.dataset.addRow));
            });
        });

        document.addEventListener('click', (event) => {
            if (event.target.matches('[data-remove-row]')) {
                event.target.closest('.borrower-repeat-row').remove();
            }
        });

        function collectRepeater(id) {
            return Array.from(document.querySelectorAll(`#${id} .borrower-repeat-row`)).map((row) => {
                const item = {};
                row.querySelectorAll('[data-field]').forEach((input) => {
                    item[input.dataset.field] = input.value;
                });
                return item;
            }).filter((item) => Object.values(item).some((value) => value !== ''));
        }

        function formPayload(form) {
            const data = new FormData(form);
            const payload = {};

            data.forEach((value, key) => {
                if (key.endsWith('[]')) {
                    const cleanKey = key.slice(0, -2);
                    payload[cleanKey] = payload[cleanKey] || [];
                    payload[cleanKey].push(value);
                    return;
                }

                payload[key] = value;
            });

            payload.deceased_guarantors = collectRepeater('deceasedGuarantorsRepeater');
            payload.affected_guarantors = collectRepeater('affectedGuarantorsRepeater');
            payload.resident_households = collectRepeater('householdsRepeater');

            return payload;
        }

        function renderStats(stats) {
            Object.entries(stats || {}).forEach(([key, value]) => {
                const target = document.querySelector(`[data-stat="${key}"]`);
                if (target) target.textContent = value;
            });
        }

        function renderAnalysis(analysis) {
            const color = riskClasses[analysis.risk_level] || 'secondary';
            const reasons = (analysis.risk_reasons || []).map((reason) => `<li>${reason}</li>`).join('');
            document.getElementById('analysisPanel').className = `alert alert-light-${color} mb-0`;
            document.getElementById('analysisPanel').innerHTML = `
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="fw-bold fs-4">درجة الخطورة: ${analysis.risk_score}/100</div>
                    <span class="badge badge-light-${color} fs-6">${analysis.risk_level}</span>
                </div>
                <ul class="mb-0">${reasons || '<li>لا توجد مؤشرات خطورة رئيسية.</li>'}</ul>
            `;
        }

        function cachedRows() {
            try {
                return JSON.parse(localStorage.getItem(borrowersOfflineRowsKey) || '[]');
            } catch (error) {
                return [];
            }
        }

        function pendingRows() {
            try {
                return JSON.parse(localStorage.getItem(borrowersPendingRowsKey) || '[]');
            } catch (error) {
                return [];
            }
        }

        function rememberRows(rows) {
            localStorage.setItem(borrowersOfflineRowsKey, JSON.stringify(rows || []));
        }

        function rememberPendingRows(rows) {
            localStorage.setItem(borrowersPendingRowsKey, JSON.stringify(rows || []));
        }

        function pendingRowFromPayload(payload) {
            return {
                id: `offline-${Date.now()}`,
                borrower_name: payload.borrower_name,
                borrower_id_number: payload.borrower_id_number,
                displacement_label: payload.displacement_status || '-',
                damage_label: payload.loan_unit_damage_status || '-',
                risk_level: 'medium',
                risk_label: @json(app()->getLocale() === 'ar' ? 'بانتظار المزامنة' : 'Pending sync'),
                risk_score: '-',
            };
        }

        function formatMoney(value) {
            return Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function pricingSummary(row) {
            const usd = Number(row.boq_total_usd || 0);
            const ils = Number(row.boq_total_ils || 0);
            const isPriced = usd > 0 || ils > 0;
            const badgeColor = isPriced ? 'success' : 'secondary';
            const badgeText = isPriced ? 'مسعّر' : 'غير مسعّر';
            const actionText = isPriced ? 'تعديل التسعير' : 'إضافة تسعير';

            return `
                <div class="borrower-pricing-cell">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                        <span class="badge badge-light-${badgeColor}">${badgeText}</span>
                        ${row.exchange_rate ? `<span class="text-muted small">صرف ${Number(row.exchange_rate).toFixed(4)}</span>` : ''}
                    </div>
                    <div class="borrower-pricing-amounts mb-3">
                        <span><span class="text-muted">USD</span><strong>${formatMoney(usd)} $</strong></span>
                        <span><span class="text-muted">ILS</span><strong class="text-success">${formatMoney(ils)} ₪</strong></span>
                    </div>
                    ${canManageBorrowerPricing && row.pricing_url ? `<a href="${row.pricing_url}" class="btn btn-sm btn-light-primary borrower-pricing-action">${actionText}</a>` : ''}
                </div>
            `;
        }

        function loanSummary(row) {
            if (!row.loan_number) {
                return '<span class="text-muted">غير مرتبط</span>';
            }

            const status = row.loan_status === 'active' ? 'نشط' : 'مغلق';
            const color = row.loan_status === 'active' ? 'success' : 'secondary';

            const amount = (value) => value === null ? 'غير متوفر' : formatMoney(value);

            return `<div class="borrower-loan-summary">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <span class="badge badge-light-${color}">${status}</span>
                    <strong>${row.loan_number}</strong>
                </div>
                <div class="borrower-loan-summary-grid">
                    <div><span>مبلغ القرض</span><strong>${amount(row.loan_total_amount)}</strong></div>
                    <div><span>محفظة القرض</span><strong>${amount(row.loan_portfolio_amount)}</strong></div>
                    <div><span>الصافي</span><strong>${amount(row.loan_net_amount)}</strong></div>
                    <div><span>الرصيد الحالي</span><strong class="text-success">${amount(row.loan_balance)}</strong></div>
                </div>
            </div>`;
        }

        function renderRows(rows) {
            const body = document.getElementById('borrowersTableBody');
            const mobileList = document.getElementById('borrowersMobileList');
            const allRows = [...pendingRows(), ...(rows || [])];

            if (!allRows.length) {
                body.innerHTML = '<tr><td colspan="7" class="text-center text-muted">لا توجد بيانات بعد</td></tr>';
                mobileList.innerHTML = '<div class="text-center text-muted py-4">لا توجد بيانات بعد</div>';
                return;
            }

            body.innerHTML = allRows.map((row) => {
                const color = riskClasses[row.risk_level] || 'secondary';
                return `<tr>
                    <td>
                        <div class="fw-bold">${row.borrower_name}</div>
                        <div class="text-muted small">${row.borrower_id_number || '-'}</div>
                        <div class="d-flex gap-2 mt-2">
                            ${row.show_url ? `<a href="${row.show_url}" class="btn btn-sm btn-light">عرض</a>` : ''}
                        </div>
                    </td>
                    <td>${row.displacement_label || '-'}</td>
                    <td>${row.damage_label || '-'}</td>
                    <td>${loanSummary(row)}</td>
                    <td>${pricingSummary(row)}</td>
                    <td>${row.attachments_count || 0}</td>
                    <td><span class="badge badge-light-${color}">${row.risk_label} (${row.risk_score})</span></td>
                </tr>`;
            }).join('');

            mobileList.innerHTML = allRows.map((row) => {
                const color = riskClasses[row.risk_level] || 'secondary';

                return `<article class="borrower-mobile-card">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <div class="fw-bold">${row.borrower_name}</div>
                            <div class="text-muted small">${row.borrower_id_number || '-'}</div>
                            <div class="d-flex gap-2 mt-2">
                                ${row.show_url ? `<a href="${row.show_url}" class="btn btn-sm btn-light">عرض</a>` : ''}
                            </div>
                        </div>
                        <span class="badge badge-light-${color}">${row.risk_label} (${row.risk_score})</span>
                    </div>
                    <div class="borrower-mobile-meta">
                        <div class="borrower-mobile-meta-item">
                            <span>النزوح</span>
                            <span>${row.displacement_label || '-'}</span>
                        </div>
                        <div class="borrower-mobile-meta-item">
                            <span>الوحدة</span>
                            <span>${row.damage_label || '-'}</span>
                        </div>
                        <div class="borrower-mobile-meta-item boq">
                            <span>BOQ</span>
                            <span>${formatMoney(row.boq_total_usd)} $ / ${formatMoney(row.boq_total_ils)} ₪</span>
                        </div>
                        <div class="borrower-mobile-meta-item">
                            <span>القرض</span>
                            <span>${row.loan_number ? `${row.loan_number} — ${row.loan_balance === null ? 'غير متوفر' : formatMoney(row.loan_balance)}` : 'غير مرتبط'}</span>
                        </div>
                        <div class="borrower-mobile-meta-item">
                            <span>صور</span>
                            <span>${row.attachments_count || 0}</span>
                        </div>
                    </div>
                    <div class="mt-3">${pricingSummary(row)}</div>
                </article>`;
            }).join('');
        }

        async function loadBorrowers() {
            const q = document.getElementById('borrowerSearch').value;
            const riskLevel = document.getElementById('borrowerRiskFilter')?.value;
            const damageStatus = document.getElementById('borrowerDamageFilter')?.value;
            const url = new URL(borrowerRoutes.data, window.location.origin);
            if (q) url.searchParams.set('q', q);
            if (riskLevel) url.searchParams.set('risk_level', riskLevel);
            if (damageStatus) url.searchParams.set('damage_status', damageStatus);

            try {
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const result = await response.json();
                rememberRows(result.data || []);
                renderStats(result.stats);
                renderRows(result.data || []);
            } catch (error) {
                renderRows(cachedRows());

                if (typeof toastr !== 'undefined') {
                    toastr.info(@json(app()->getLocale() === 'ar' ? 'وضع عدم الاتصال: يتم عرض سجلات المقترضين المحفوظة.' : 'Offline mode: showing saved borrower rows.'));
                }
            }
        }

        const borrowerSurveyForm = document.getElementById('borrowerSurveyForm');
        const borrowerSearch = document.getElementById('borrowerSearch');
        const borrowerRiskFilter = document.getElementById('borrowerRiskFilter');
        const borrowerDamageFilter = document.getElementById('borrowerDamageFilter');
        const borrowersImportForm = document.getElementById('borrowersImportForm');
        const borrowersFile = document.getElementById('borrowersFile');
        const borrowersImportDropzone = document.getElementById('borrowersImportDropzone');
        const borrowersImportFileName = document.getElementById('borrowersImportFileName');
        const borrowersImportPreview = document.getElementById('borrowersImportPreview');
        const borrowersPreviewBtn = document.getElementById('borrowersPreviewBtn');

        if (window.jQuery && $.fn.select2) {
            $('.borrower-filter-select').each(function () {
                const select = $(this);

                if (!select.hasClass('select2-hidden-accessible')) {
                    select.select2({
                        allowClear: false,
                        minimumResultsForSearch: Infinity,
                        width: '100%',
                    });
                }
            });
        }

        borrowerSurveyForm?.addEventListener('submit', async (event) => {
            event.preventDefault();

            const form = event.currentTarget;
            const button = document.getElementById('borrowerSubmitBtn');
            const payload = formPayload(form);
            button.setAttribute('data-kt-indicator', 'on');
            button.disabled = true;

            try {
                if (!navigator.onLine && window.phcOfflineSync) {
                    await window.phcOfflineSync.queue({
                        url: borrowerRoutes.store,
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                        },
                        body: JSON.stringify(payload),
                    });

                    rememberPendingRows([pendingRowFromPayload(payload), ...pendingRows()]);
                    form.reset();
                    document.getElementById('deceasedGuarantorsRepeater').innerHTML = '';
                    document.getElementById('affectedGuarantorsRepeater').innerHTML = '';
                    document.getElementById('householdsRepeater').innerHTML = '';
                    if (borrowerSearch) {
                        renderRows(cachedRows());
                    }

                    if (typeof toastr !== 'undefined') {
                        toastr.success('تم حفظ الاستبيان أوفلاين. سيتم إرساله تلقائيًا عند رجوع الإنترنت.');
                    }

                    return;
                }

                const response = await fetch(borrowerRoutes.store, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify(payload),
                });
                const result = await response.json();

                if (!response.ok || !result.status) {
                    const errors = result.errors ? Object.values(result.errors).flat().join('<br>') : (result.message || 'فشل حفظ الاستبيان');
                    if (typeof toastr !== 'undefined') toastr.error(errors);
                    return;
                }

                form.reset();
                document.getElementById('deceasedGuarantorsRepeater').innerHTML = '';
                document.getElementById('affectedGuarantorsRepeater').innerHTML = '';
                document.getElementById('householdsRepeater').innerHTML = '';
                renderAnalysis(result.analysis);
                renderStats(result.stats);
                if (borrowerSearch) {
                    await loadBorrowers();
                }
                if (typeof toastr !== 'undefined') toastr.success(result.message);
            } finally {
                button.removeAttribute('data-kt-indicator');
                button.disabled = false;
            }
        });

        borrowerSearch?.addEventListener('input', () => {
            clearTimeout(window.borrowerSearchTimer);
            window.borrowerSearchTimer = setTimeout(loadBorrowers, 300);
        });

        borrowerRiskFilter?.addEventListener('change', loadBorrowers);
        borrowerDamageFilter?.addEventListener('change', loadBorrowers);

        document.querySelectorAll('[data-risk-filter]').forEach((button) => {
            button.addEventListener('click', () => {
                borrowerRiskFilter.value = button.dataset.riskFilter;
                document.querySelectorAll('[data-risk-filter]').forEach((filter) => filter.classList.remove('is-active'));
                button.classList.add('is-active');
                loadBorrowers();
            });
        });

        document.querySelectorAll('[data-damage-filter]').forEach((button) => {
            button.addEventListener('click', () => {
                borrowerDamageFilter.value = button.dataset.damageFilter;
                document.querySelectorAll('[data-damage-filter]').forEach((filter) => filter.classList.remove('is-active'));
                button.classList.add('is-active');
                loadBorrowers();
            });
        });

        document.querySelectorAll('[data-scroll-to-section]').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById(button.dataset.scrollToSection)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        borrowersFile?.addEventListener('change', () => {
            borrowersImportFileName.textContent = borrowersFile.files?.[0]?.name || 'صيغة XLSX فقط، حتى 20 ميغابايت.';
            borrowersImportPreview?.classList.remove('is-visible');
            if (borrowersImportPreview) borrowersImportPreview.innerHTML = '';
        });

        function renderImportPreview(preview) {
            const sheets = preview.sheets || [];
            if (!sheets.length) {
                borrowersImportPreview.innerHTML = '<div class="text-danger fw-semibold">لم يتم العثور على أوراق قروض قابلة للاستيراد.</div>';
                borrowersImportPreview.classList.add('is-visible');
                return;
            }

            borrowersImportPreview.innerHTML = `
                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <div class="fw-bold">تمت قراءة الملف بنجاح</div>
                        <div class="text-muted fs-7">اختر ورقة واحدة للاستيراد. لن يُنفذ أي تغيير قبل الضغط على تأكيد الاستيراد.</div>
                    </div>
                    <span class="badge badge-light-success">معاينة فقط</span>
                </div>
                <div class="row g-3">
                    ${sheets.map((sheet, index) => `
                        <div class="col-12">
                            <label class="form-check form-check-custom form-check-solid border rounded p-3 w-100">
                                <input class="form-check-input" type="radio" name="sheet_name" value="${sheet.name}" ${index === 0 ? 'checked' : ''}>
                                <span class="form-check-label ms-3 flex-grow-1">
                                    <span class="fw-bold d-block">${sheet.name} — ${sheet.status === 'active' ? 'قروض نشطة' : 'قروض مغلقة'}</span>
                                    <span class="text-muted fs-7">${sheet.ready} سجل جاهز من أصل ${sheet.total}${sheet.skipped ? `، ${sheet.skipped} بحاجة مراجعة` : ''}</span>
                                </span>
                            </label>
                        </div>
                    `).join('')}
                </div>
            `;
            borrowersImportPreview.classList.add('is-visible');
        }

        borrowersPreviewBtn?.addEventListener('click', async () => {
            if (!borrowersFile?.files?.length) {
                if (typeof toastr !== 'undefined') toastr.error('اختر ملف Excel أولًا.');
                return;
            }

            const error = document.getElementById('borrowersImportError');
            error.style.display = 'none';
            borrowersPreviewBtn.setAttribute('data-kt-indicator', 'on');
            borrowersPreviewBtn.disabled = true;

            try {
                const previewData = new FormData();
                previewData.append('borrowers_file', borrowersFile.files[0]);
                previewData.append('_token', csrfToken());
                const response = await fetch(borrowerRoutes.previewImport, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: previewData,
                });
                const result = await response.json().catch(() => ({
                    status: false,
                    message: 'انتهت جلسة العمل أو تعذرت قراءة استجابة الخادم. حدّث الصفحة ثم أعد المحاولة.',
                }));

                if (!response.ok || !result.status) {
                    error.textContent = result.message || 'تعذرت معاينة الملف.';
                    error.style.display = 'block';
                    return;
                }

                renderImportPreview(result.preview);
            } finally {
                borrowersPreviewBtn.removeAttribute('data-kt-indicator');
                borrowersPreviewBtn.disabled = false;
            }
        });

        ['dragenter', 'dragover'].forEach((eventName) => {
            borrowersImportDropzone?.addEventListener(eventName, () => borrowersImportDropzone.classList.add('is-active'));
        });

        ['dragleave', 'drop'].forEach((eventName) => {
            borrowersImportDropzone?.addEventListener(eventName, () => borrowersImportDropzone.classList.remove('is-active'));
        });

        borrowersImportForm?.addEventListener('submit', async (event) => {
            event.preventDefault();

            const form = event.currentTarget;
            const button = document.getElementById('borrowersImportSubmitBtn');
            const error = document.getElementById('borrowersImportError');
            error.style.display = 'none';
            error.textContent = '';
            button.setAttribute('data-kt-indicator', 'on');
            button.disabled = true;

            try {
                const response = await fetch(borrowerRoutes.import, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                    },
                    body: new FormData(form),
                });
                const result = await response.json();

                if (! response.ok || ! result.status) {
                    const message = result.errors?.borrowers_file?.[0] || result.message || 'فشل استيراد الملف.';
                    error.textContent = message;
                    error.style.display = 'block';
                    if (typeof toastr !== 'undefined') toastr.error(message);
                    return;
                }

                form.reset();
                borrowersImportFileName.textContent = 'صيغة XLSX فقط، حتى 20 ميغابايت.';
                borrowersImportPreview.classList.remove('is-visible');
                borrowersImportPreview.innerHTML = '';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('borrowersImportModal')).hide();
                renderStats(result.stats);
                await loadBorrowers();

                if (typeof toastr !== 'undefined') {
                    toastr.success(result.message);
                }
            } finally {
                button.removeAttribute('data-kt-indicator');
                button.disabled = false;
            }
        });

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', async (event) => {
                if (event.data?.type !== 'PHC_OFFLINE_SYNC_COMPLETE') {
                    return;
                }

                rememberPendingRows([]);
                if (borrowerSearch) {
                    await loadBorrowers();
                }

                if (typeof toastr !== 'undefined') {
                    toastr.success(@json(app()->getLocale() === 'ar' ? 'تمت مزامنة استبيانات المقترضين المحفوظة بنجاح.' : 'Offline borrower surveys synced successfully.'));
                }
            });
        }

        window.addEventListener('online', () => {
            window.phcOfflineSync?.registerSync?.();
        });

        if (borrowerSearch) {
            loadBorrowers();
        }
    </script>
@endsection
