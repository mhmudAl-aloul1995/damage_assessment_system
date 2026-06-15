@extends('layouts.app')

@section('title', 'الإستبيان')
@section('pageName', 'الإستبيان')

@php
    $buildingCurrentStatus = $buildingCurrentStatus ?? null;
    $buildingEngineeringStatus = $buildingEngineeringStatus ?? null;
    $buildingLegalStatus = $buildingLegalStatus ?? null;
    $housingGlobalid = $housingGlobalid ?? null;
    $showHousingTab = filled($housingGlobalid);
    $isAssessmentReadOnly = $isAssessmentReadOnly ?? false;
    $canEditAssessment = $canEditAssessment ?? false;
    $isStatusPreviewOnly = false;
    $statusLabel = fn (?string $status): string => match ($status) {
        'accepted_by_engineer' => 'مقبول هندسياً',
        'rejected_by_engineer' => 'مرفوض هندسياً',
        'need_review' => 'بحاجة لمراجعة',
        'accepted_by_lawyer' => 'مقبول قانونياً',
        'legal_notes' => 'ملاحظات قانونية',
        'final_approval' => 'اعتماد نهائي',
        'undp_final_approve' => 'UNDP Final Approve',
        default => filled($status) ? str($status)->replace('_', ' ')->headline()->toString() : '-',
    };
    $statusBadgeClass = fn (?string $status): string => \App\Models\AssessmentStatus::badgeClassForName($status).' fw-bold px-4 py-3';
@endphp

@section('content')
    <style>
        /* Units table auto-size */
        #housing_table {
            width: auto !important;
            min-width: 100%;
            table-layout: auto !important;
        }

        #housing_table th,
        #housing_table td {
            white-space: normal !important;
            vertical-align: middle !important;
            text-align: center;
            padding: 0.75rem 0.9rem !important;
        }

        /* أعمدة الحالات تكون أضيق */
        #housing_table th:nth-child(9),
        #housing_table td:nth-child(9),
        #housing_table th:nth-child(10),
        #housing_table td:nth-child(10),
        #housing_table th:nth-child(11),
        #housing_table td:nth-child(11) {
            width: 140px !important;
            max-width: 140px !important;
        }

        /* اسم المالك لا يأخذ مساحة كبيرة */
        #housing_table th:nth-child(6),
        #housing_table td:nth-child(6) {
            width: 120px !important;
            max-width: 120px !important;
            word-break: break-word;
        }

        /* نوع الوحدة وحالة الضرر */
        #housing_table th:nth-child(2),
        #housing_table td:nth-child(2),
        #housing_table th:nth-child(3),
        #housing_table td:nth-child(3) {
            width: 130px !important;
            max-width: 130px !important;
        }

        /* الأرقام */
        #housing_table th:nth-child(4),
        #housing_table td:nth-child(4),
        #housing_table th:nth-child(5),
        #housing_table td:nth-child(5) {
            width: 80px !important;
            max-width: 80px !important;
        }

        #housing_table th:nth-child(1),
        #housing_table td:nth-child(1) {
            width: 48px !important;
            max-width: 48px !important;
        }

        .building-status-btn,
        .housing-status-btn {
            transition: all .2s ease-in-out
        }

        .building-status-btn:hover,
        .housing-status-btn:hover {
            transform: translateY(-1px)
        }

        .building-status-btn.is-active,
        .housing-status-btn.is-active {
            transform: translateY(-1px);
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .12)
        }

        .building-status-btn.btn-light-danger.is-active,
        .housing-status-btn.btn-light-danger.is-active {
            background: var(--bs-danger) !important;
            border-color: var(--bs-danger) !important;
            color: #fff !important
        }

        .building-status-btn.btn-light-success.is-active,
        .housing-status-btn.btn-light-success.is-active {
            background: var(--bs-success) !important;
            border-color: var(--bs-success) !important;
            color: #fff !important
        }

        .building-status-btn.btn-light-warning.is-active,
        .housing-status-btn.btn-light-warning.is-active {
            background: var(--bs-warning) !important;
            border-color: var(--bs-warning) !important;
            color: #fff !important
        }

        .building-status-btn.btn-light-primary.is-active,
        .housing-status-btn.btn-light-primary.is-active {
            background: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            color: #fff !important
        }

        .building-status-btn:disabled,
        .housing-status-btn:disabled {
            cursor: not-allowed;
            opacity: .8
        }

        #housing_table tbody tr.selected {
            background-color: rgba(0, 158, 247, .12) !important
        }

        #housing_table tbody tr.multi-selected {
            box-shadow: inset 4px 0 0 var(--bs-warning);
        }

        .container-loader {
            display: none !important
        }

        .audit-sticky-menu {
            position: sticky;
            top: 95px;
            z-index: 10;
            border-radius: 1rem;
            overflow: visible !important;
            height: auto !important;
            max-height: none !important;
        }

        #tab_housing .audit-sticky-menu .card-body {
            overflow: visible !important;
            height: auto !important;
            max-height: none !important;
        }

        #tab_housing .summary-box {
            min-height: 68px;
        }

        .summary-box {
            min-height: 78px;
            border-radius: .85rem;
            padding: .85rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            transition: all .2s ease
        }

        .summary-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .08)
        }

        .summary-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--bs-gray-700);
            margin-bottom: .35rem;
            line-height: 1.4
        }

        .summary-value {
            font-size: 1.2rem;
            font-weight: 800;
            line-height: 1.2;
            word-break: break-word
        }

        .summary-value-long {
            font-size: .95rem;
            font-weight: 700;
            line-height: 1.55;
            white-space: normal
        }

        .audit-toolbar-sticky {
            position: sticky;
            top: 70px;
            z-index: 50;
            background: #fff;
            padding: 10px 0;
            border-bottom: 1px solid #eef1f5
        }

        .audit-filter-btn.is-active {
            background: var(--bs-primary) !important;
            color: #fff !important
        }

        .audit-action-group {
            display: inline-flex;
            flex-direction: column;
            gap: .35rem;
            padding: .5rem .6rem;
            border: 1px solid #eef2f7;
            border-radius: .65rem;
            background: #fff;
            min-width: max-content
        }

        .audit-action-label {
            font-size: .72rem;
            font-weight: 800;
            color: #7e8aa0;
            line-height: 1;
            white-space: nowrap
        }

        .audit-action-controls {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            flex-wrap: wrap
        }

        .assessment-section {
            border: 1px solid #edf1f5;
            border-radius: 1rem;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 .35rem 1rem rgba(15, 23, 42, .04)
        }

        .assessment-section-header {
            cursor: pointer;
            padding: 1rem 1.25rem;
            border: 0;
            background: linear-gradient(135deg, #f8fafc 0%, #eef6ff 45%, #e8f3ff 100%);
            border-bottom: 1px solid #e4ecf7;
            transition: all .25s ease;
            position: relative
        }

        .assessment-section-header:hover {
            background: linear-gradient(135deg, #eef6ff 0%, #dceeff 100%);
            box-shadow: inset 0 0 0 1px rgba(0, 158, 247, .08)
        }

        .assessment-section-header .fw-bold {
            color: #1e2b3b;
            font-size: 1.02rem;
            letter-spacing: .2px
        }

        .assessment-section-header .badge {
            background: #fff;
            color: #009ef7;
            border: 1px solid rgba(0, 158, 247, .18);
            font-weight: 700;
            padding: .45rem .75rem;
            border-radius: .7rem
        }

        .collapse-indicator {
            width: 1.8rem;
            height: 1.8rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #fff;
            color: #009ef7;
            border: 1px solid rgba(0, 158, 247, .18);
            font-size: .8rem;
            font-weight: 800;
            flex: 0 0 auto
        }

        .assessment-section-header .collapse-indicator-closed,
        .assessment-section-header.collapsed .collapse-indicator-open,
        .assessment-section-header .collapse-state-badge {
            display: none
        }

        .assessment-section-header.collapsed .collapse-indicator-closed,
        .assessment-section-header.collapsed .collapse-state-badge {
            display: inline-flex
        }

        .assessment-section-header:after {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, #009ef7, #50cd89)
        }

        .section-progress-bar {
            width: 120px;
            height: 6px;
            border-radius: 20px;
            background: #eef3f7;
            overflow: hidden
        }

        .section-progress-fill {
            height: 100%;
            border-radius: 20px;
            background: linear-gradient(90deg, #009ef7, #50cd89)
        }

        .assessment-item {
            border-top: 1px dashed var(--bs-gray-300);
            padding: 1rem 1.25rem;
            background: #fff
        }

        .assessment-item.has-answer {
            background: #f1fff5
        }

        .assessment-item.is-missing {
            background: #ffffff !important;
            border-right: 4px solid #e4e6ef;
        }

        .assessment-item.is-edited {
            border-right: 4px solid #009ef7
        }

        .assessment-item:hover {
            background: #f8fbff
        }

        .assessment-question {
            font-weight: 800;
            color: var(--bs-gray-800);
            line-height: 1.6
        }

        .assessment-answer {
            font-weight: 700;
            color: var(--bs-gray-700);
            word-break: break-word
        }

        .assessment-edit .select2-container,
        .assessment-edit select,
        .assessment-edit input {
            width: 100% !important
        }

        .assessment-item img {
            max-width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: .75rem;
            margin: 3px
        }

        .assessment-progress {
            min-width: 90px
        }

        .audit-save-indicator {
            position: fixed;
            bottom: 25px;
            left: 25px;
            z-index: 9999;
            display: none;
            padding: .75rem 1rem;
            border-radius: .75rem;
            background: #50cd89;
            color: #fff;
            font-weight: 800;
            box-shadow: 0 .75rem 1.5rem rgba(0, 0, 0, .15)
        }

        @media(max-width:991px) {
            .audit-sticky-menu {
                position: relative;
                top: auto;
                margin-bottom: 1rem
            }

            .summary-box {
                min-height: 70px;
                padding: .75rem
            }

            .summary-title {
                font-size: .72rem
            }

            .summary-value {
                font-size: 1rem
            }
        }

        @media(max-width:768px) {
            .assessment-section-header {
                padding: .9rem 1rem
            }

            .assessment-section-header .fw-bold {
                font-size: .92rem;
                line-height: 1.5
            }

            .assessment-section-header .badge {
                font-size: .72rem;
                padding: .35rem .55rem
            }

            .audit-toolbar-sticky {
                top: 60px
            }


        }

        .audit-edit-card {
            background: #fff8dd;
            border: 1px solid #ffe7a3;
            border-radius: .85rem;
            padding: 1rem;
            text-align: center;
            max-width: 320px;
            margin: auto;
            line-height: 1.8;
        }

        .audit-edit-card div {
            display: block;
        }

        .audit-label {
            color: #a1a5b7;
            font-size: .82rem;
            font-weight: 800;
        }

        .audit-new-value {
            color: #181c32;
            font-weight: 900;
        }

        .audit-original-value {
            color: #7e8299;
            font-weight: 800;
        }

        #tab_housing .card-header {
            display: flex !important;
            align-items: flex-start !important;
            justify-content: space-between !important;
            gap: 1rem;
            flex-wrap: wrap;
        }

        #tab_housing .card-title {
            margin: 0 !important;
            flex: 0 0 auto;
        }

        #tab_housing .card-toolbar {
            position: static !important;
            top: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            flex: 1 1 auto;
        }

        #tab_housing .card-toolbar>.d-flex {
            justify-content: flex-end;
            gap: .5rem;
            flex-wrap: wrap;
        }

        #tab_housing [name="globalid"]+.select2,
        #tab_housing select[name="globalid"] {
            min-width: 250px;
        }

        @media (max-width: 991px) {

            #tab_housing .card-title,
            #tab_housing .card-toolbar {
                width: 100%;
            }

            #tab_housing .card-toolbar>.d-flex {
                justify-content: flex-start;
            }

            #tab_housing [name="globalid"]+.select2,
            #tab_housing select[name="globalid"] {
                width: 100% !important;
                min-width: 100%;
            }
        }

        .assessment-item.table-danger {
            background: #fff5f8 !important;
            border-right: 4px solid #f1416c !important;
        }

        .audit-label {
            color: #a1a5b7;
            font-size: .82rem;
            font-weight: 800;
        }

        .audit-new-value {
            color: #181c32;
            font-weight: 900;
            display: block;
        }

        /* الحل الجذري لمشكلة اختفاء ملخص الوحدة */
        .audit-sticky-menu {
            position: sticky;
            top: 95px;
            z-index: 10;
            border-radius: 1rem;
            height: auto !important;
            /* يسمح بالتمرير الداخلي إذا كان الملخص أطول من الشاشة */
            max-height: calc(100vh - 110px) !important;
            overflow-y: auto !important;
            scrollbar-width: thin;
            /* تحسين شكل شريط التمرير */
        }

        /* إلغاء الـ sticky في الجوال لضمان الظهور */
        @media(max-width:991px) {
            .audit-sticky-menu {
                position: relative !important;
                top: 0 !important;
                max-height: none !important;
                overflow: visible !important;
            }
        }
    </style>

    </style>

    <div class="card card-flush mb-7">
        <div class="card-header pt-7">
            <div class="card-title">
                <h2>الإستبيان</h2>
            </div>
        </div>

        <div class="card-body">
            <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold mb-8">
                <li class="nav-item">
                    <a class="nav-link text-active-primary {{ $showHousingTab ? '' : 'active' }}" data-bs-toggle="tab" href="#tab_building">المبنى</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-active-primary {{ $showHousingTab ? 'active' : '' }}" data-bs-toggle="tab" href="#tab_housing">الوحدة السكنية</a>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade {{ $showHousingTab ? '' : 'show active' }}" id="tab_building" role="tabpanel">
                    <div class="card card-flush shadow-sm border-0">
                        <div class="card-header border-0 pt-6 pb-4">
                            <div class="card-title">
                                <div class="d-flex align-items-center position-relative my-1">
                                    <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5"><span
                                            class="path1"></span><span class="path2"></span></i>
                                    <input type="text" data-kt-buildingAssessment-table-filter="search"
                                        class="form-control form-control-solid w-250px ps-13" placeholder="بحث" />
                                </div>
                            </div>

                            <div class="card-toolbar">
                                <div
                                    class="audit-toolbar-sticky d-flex justify-content-end align-items-center gap-2 flex-wrap">

                                </div>
                            </div>
                        </div>

                        <div class="card-body pt-0 pb-4">
                            <div class="row g-7">
                                <div class="col-12 col-lg-3 col-xl-2">
                                    <div class="audit-sticky-menu bg-white border rounded-3 shadow-sm">
                                        <div class="card-header py-3 px-4">
                                            <div class="card-title m-0">
                                                <h3 class="fw-bold fs-4 mb-0">ملخص المبنى</h3>
                                            </div>
                                        </div>

                                        <div class="card-body p-3">
                                            <div class="row g-3" id="building_summary_items">
                                                <div class="col-12">
                                                    <div class="summary-box bg-light">
                                                        <div class="summary-title">ملخص المبنى</div>
                                                        <div class="summary-value text-muted">--</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-lg-9 col-xl-10">
                                    <div class="audit-toolbar-sticky mb-4">
                                        <div class="d-flex flex-wrap gap-2">
                                            <div class="audit-action-group">
                                                <div class="audit-action-label">فلترة العرض</div>
                                                <div class="audit-action-controls">
                                                    <div class="dropdown">
                                                        <button type="button" class="btn btn-sm btn-light-primary dropdown-toggle"
                                                            data-bs-toggle="dropdown" aria-expanded="false">
                                                            فلتر: <span id="building_filter_label">الكل</span>
                                                        </button>
                                                        <div class="dropdown-menu">
                                                            <button type="button"
                                                                class="dropdown-item audit-filter-btn building-filter-btn is-active"
                                                                data-filter="all" data-filter-label="الكل">الكل</button>
                                                            <button type="button"
                                                                class="dropdown-item audit-filter-btn building-filter-btn"
                                                                data-filter="missing" data-filter-label="الفارغ فقط">الفارغ فقط</button>
                                                            <button type="button"
                                                                class="dropdown-item audit-filter-btn building-filter-btn"
                                                                data-filter="edited" data-filter-label="المعدّل فقط">المعدّل فقط</button>
                                                            <button type="button"
                                                                class="dropdown-item audit-filter-btn building-filter-btn"
                                                                data-filter="answered" data-filter-label="المجاب فقط">المجاب فقط</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            @if($isStatusPreviewOnly)
                                                <span class="{{ $statusBadgeClass($buildingEngineeringStatus) }}">
                                                    آخر حالة هندسية: {{ $statusLabel($buildingEngineeringStatus) }}
                                                </span>
                                                <span class="d-none {{ $statusBadgeClass($buildingEngineeringStatus) }}">
                                                    آخر حالة: {{ $statusLabel($buildingCurrentStatus) }}
                                                </span>
                                                <span class="{{ $statusBadgeClass($buildingLegalStatus) }}">
                                                    آخر حالة قانونية: {{ $statusLabel($buildingLegalStatus) }}
                                                </span>
                                            @else
                                            <div class="audit-action-group">
                                                <div class="audit-action-label">التدقيق القانوني</div>
                                                <div class="audit-action-controls">
                                                    <button type="button" class="btn btn-sm btn-light-success building-status-btn"
                                                        data-status="accepted" data-audit-type="Legal Auditor"
                                                        onclick="setBuildingStatus('accepted', 'Legal Auditor')">مقبول</button>
                                                    <button type="button" class="btn btn-sm btn-light-warning building-status-btn"
                                                        data-status="legal_notes" data-audit-type="Legal Auditor"
                                                        onclick="setBuildingStatus('legal_notes', 'Legal Auditor')">ملاحظات
                                                        قانونية</button>
                                                </div>
                                            </div>

                                            <div class="audit-action-group">
                                                <div class="audit-action-label">التدقيق الهندسي</div>
                                                <div class="audit-action-controls">
                                                    <button type="button" class="btn btn-sm btn-light-danger building-status-btn"
                                                        data-status="rejected" data-audit-type="QC/QA Engineer"
                                                        onclick="setBuildingStatus('rejected', 'QC/QA Engineer')">مرفوض</button>
                                                    <button type="button" class="btn btn-sm btn-light-success building-status-btn"
                                                        data-status="accepted" data-audit-type="QC/QA Engineer"
                                                        onclick="setBuildingStatus('accepted', 'QC/QA Engineer')">مقبول</button>
                                                    <button type="button" class="btn btn-sm btn-light-warning building-status-btn"
                                                        data-status="need_review" data-audit-type="QC/QA Engineer"
                                                        onclick="setBuildingStatus('need_review', 'QC/QA Engineer')">بحاجة
                                                        لمراجعة</button>
                                                </div>
                                            </div>

                                            <div class="audit-action-group">
                                                <div class="audit-action-label">الاعتماد النهائي</div>
                                                <div class="audit-action-controls">
                                                    <button type="button" class="btn btn-sm btn-light-primary building-status-btn"
                                                        data-status="undp_final_approve"
                                                        onclick="setBuildingStatus('undp_final_approve')">
                                                        UNDP Final Approve</button>
                                                </div>
                                            </div>
                                            @endif
                                            @if (auth()->user()->hasAnyRole(['Auditing Supervisor', 'Database Officer']))
                                                <div class="audit-action-group">
                                                    <div class="audit-action-label">اعتماد المشرف</div>
                                                    <div class="audit-action-controls">
                                                        <button type="button" id="btn_show_assessment_final_approve"
                                                            class="btn btn-sm btn-light-warning"
                                                            onclick="finalApproveCurrentBuilding()">
                                                            Final Approve
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="audit-action-group">
                                                <div class="audit-action-label">إجراءات</div>
                                                <div class="audit-action-controls">
                                                    <div class="dropdown">
                                                        <button type="button" class="btn btn-sm btn-light dropdown-toggle"
                                                            data-bs-toggle="dropdown" aria-expanded="false">
                                                            إجراءات
                                                        </button>
                                                        <div class="dropdown-menu">
                                                            @unless($isAssessmentReadOnly || auth()->user()->hasAnyRole(['QC/QA Engineer', 'Engineering Auditor']))
                                                                <button type="button" id="btn_building_legal_challenge"
                                                                    class="dropdown-item"
                                                                    onclick="openLegalChallengeModal('building')">التحديات القانونية</button>
                                                            @endunless
                                                            <button type="button" class="dropdown-item"
                                                                onclick="openNotesModal('building','history')">ملاحظات</button>
                                                            <button type="button" class="dropdown-item"
                                                                onclick="reloadBuildingAssessmentTable()">تحديث</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    <div id="building_assessment_accordion" class="accordion accordion-icon-toggle"></div>
                                </div>
                            </div>

                            <table class="d-none" id="kt_table_building_assessment">
                                <thead>
                                    <tr>
                                        <th>السؤال</th>
                                        <th>الجواب</th>
                                        <th>تعديل الإجابة</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade {{ $showHousingTab ? 'show active' : '' }}" id="tab_housing" role="tabpanel">
                    <div class="card card-flush mb-7 shadow-sm border-0">
                        <div class="card-header pt-6 pb-4 border-0">
                            <div class="card-title">
                                <h3 class="fw-bold mb-0">وحدات المبنى</h3>
                            </div>
                            <div class="card-toolbar">
                                <div class="d-flex flex-wrap gap-2">
                                    <div class="dropdown">
                                        <button type="button" class="btn btn-sm btn-light dropdown-toggle"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                            إجراءات
                                        </button>
                                        <div class="dropdown-menu">
                                            @if(! $isAssessmentReadOnly)
                                            @hasanyrole('Legal Auditor|Database Officer')
                                            <button type="button" id="btn_housing_legal_challenge"
                                                class="dropdown-item"
                                                onclick="openLegalChallengeModal('housing')">التحديات القانونية</button>
                                            @endhasanyrole
                                            @endif
                                            <button type="button" class="dropdown-item"
                                                onclick="reloadBuildingUnitsTable()">تحديث</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-body pt-0 pb-4">
                            <div class="table-responsive">
                                <table class="table align-middle table-row-bordered table-rounded gs-7 gy-4"
                                    id="housing_table">
                                    <thead>
                                        <tr class="fw-bold fs-7 text-black-800 border-bottom border-gray-300">
                                            <th class="px-2 py-3">
                                                <input type="checkbox" class="form-check-input" id="select_all_housing_units">
                                            </th>
                                            <th class="px-2 py-3">نوع الوحدة</th>
                                            <th class="px-2 py-3">حالة الضرر</th>
                                            <th class="px-2 py-3">رقم الطابق</th>
                                            <th class="px-2 py-3">رقم الوحدة</th>
                                            <th class="px-2 py-3">اسم المالك</th>
                                            <th class="px-2 py-3">اتجاه الوحدة</th>
                                            <th class="px-2 py-3">التحديات القانونية</th>
                                            <th class="px-2 py-3">التدقيق القانوني</th>
                                            <th class="px-2 py-3">التدقيق الهندسي</th>
                                            <th class="px-2 py-3">الاعتماد النهائي</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row g-7">
                        <div class="col-12 col-lg-3 col-xl-2">
                            <div class="card card-flush shadow-sm border-0 audit-sticky-menu">
                                <div class="card-header py-3 px-4">
                                    <div class="card-title m-0">
                                        <h3 class="fw-bold fs-4 mb-0">ملخص الوحدة</h3>
                                    </div>
                                </div>

                                <div class="card-body p-3">
                                    <div class="row g-3" id="housing_summary_items">
                                        <div class="col-6 col-lg-12">
                                            <div class="summary-box bg-light-info">
                                                <div class="summary-title">مالك الوحدة</div>
                                                <div id="sidebar_unit_owner" class="summary-value text-info">--</div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-lg-12">
                                            <div class="summary-box bg-light-primary">
                                                <div class="summary-title">مساحة الوحدة</div>
                                                <div id="sidebar_unit_area" class="summary-value text-primary">--</div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-lg-12">
                                            <div class="summary-box bg-light-warning">
                                                <div class="summary-title">تأهيل مطبخ</div>
                                                <div id="sidebar_kitchen" class="summary-value text-warning">--</div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-lg-12">
                                            <div class="summary-box bg-light-info">
                                                <div class="summary-title">تأهيل حمام</div>
                                                <div id="sidebar_bathroom" class="summary-value text-info">--</div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-lg-12">
                                            <div class="summary-box bg-light-success">
                                                <div class="summary-title">ملائمة للسكن</div>
                                                <div id="sidebar_living" class="summary-value text-success">--</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-12">
                                            <div class="summary-box bg-light-warning">
                                                <div class="summary-title">عدد الغرف</div>
                                                <div id="sidebar_rooms" class="summary-value text-warning">--</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-12">
                                            <div class="summary-box bg-light-danger">
                                                <div class="summary-title">هل مأهول؟</div>
                                                <div id="sidebar_occupied" class="summary-value text-danger">--</div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-lg-12">
                                            <div class="summary-box bg-light-primary">
                                                <div class="summary-title">تشطيب الوحدة من الخارج</div>
                                                <div id="sidebar_external_finishing" class="summary-value text-primary">--
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-lg-12">
                                            <div class="summary-box bg-light-info">
                                                <div class="summary-title">تشطيب الوحدة من الداخل</div>
                                                <div id="sidebar_internal_finishing" class="summary-value text-info">--
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-8 col-xl-9">
                            <div class="card card-flush shadow-sm border-0">
                                <div class="card-header border-0 pt-6 pb-4">
                                    <div class="card-title">
                                        <div class="d-flex align-items-center position-relative my-1">
                                            <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5"><span
                                                    class="path1"></span><span class="path2"></span></i>
                                            <input type="text" data-kt-HousingAssessment-table-filter="search"
                                                class="form-control form-control-solid w-250px ps-13" placeholder="بحث" />
                                        </div>
                                    </div>

                                    <div class="card-toolbar">
                                        <div class="audit-toolbar-sticky d-flex align-items-center flex-wrap fw-bold gap-2">
                                            <div class="me-3">
                                                <select name="globalid" class="form-select form-select-solid w-250px"
                                                    data-control="select2" data-placeholder="إختر الوحدة">
                                                    <option value=""></option>
                                                    @foreach ($HousingUnit as $value)
                                                        <option value="{{ $value->globalid }}">
                                                            {{ $value->objectid }} -- {{ $value->full_name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>


                                        </div>
                                    </div>
                                </div>

                                <div class="card-body pt-0 pb-4">
                                    <div class="audit-toolbar-sticky mb-4">
                                        <div class="d-flex flex-wrap gap-2">
                                            <div class="audit-action-group">
                                                <div class="audit-action-label">فلترة العرض</div>
                                                <div class="audit-action-controls">
                                                    <div class="dropdown">
                                                        <button type="button" class="btn btn-sm btn-light-primary dropdown-toggle"
                                                            data-bs-toggle="dropdown" aria-expanded="false">
                                                            فلتر: <span id="housing_filter_label">الكل</span>
                                                        </button>
                                                        <div class="dropdown-menu">
                                                            <button type="button"
                                                                class="dropdown-item audit-filter-btn housing-filter-btn is-active"
                                                                data-filter="all" data-filter-label="الكل">الكل</button>
                                                            <button type="button"
                                                                class="dropdown-item audit-filter-btn housing-filter-btn"
                                                                data-filter="missing" data-filter-label="الفارغ فقط">الفارغ فقط</button>
                                                            <button type="button"
                                                                class="dropdown-item audit-filter-btn housing-filter-btn"
                                                                data-filter="edited" data-filter-label="المعدّل فقط">المعدّل فقط</button>
                                                            <button type="button"
                                                                class="dropdown-item audit-filter-btn housing-filter-btn"
                                                                data-filter="answered" data-filter-label="المجاب فقط">المجاب فقط</button>
                                                            <button type="button"
                                                                class="dropdown-item audit-filter-btn housing-filter-btn"
                                                                data-filter="attachments" data-filter-label="المرفقات">المرفقات</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                           
                                            @if($isStatusPreviewOnly)
                                                <span class="badge badge-light fw-bold px-4 py-3">
                                                    آخر حالة هندسية: <span id="housing_engineering_status_preview">-</span>
                                                </span>
                                                <span class="badge badge-light fw-bold px-4 py-3">
                                                    آخر حالة قانونية: <span id="housing_legal_status_preview">-</span>
                                                </span>
                                                <span class="d-none badge badge-light fw-bold px-4 py-3">
                                                    آخر حالة: <span id="housing_current_status_preview">-</span>
                                                </span>
                                            @else
                                            <div class="audit-action-group">
                                                <div class="audit-action-label">التدقيق القانوني</div>
                                                <div class="audit-action-controls">
                                                    <button type="button" class="btn btn-sm btn-light-success housing-status-btn"
                                                        data-status="accepted" data-audit-type="Legal Auditor"
                                                        onclick="setHousingStatus('accepted', 'Legal Auditor')">مقبول</button>
                                                    <button type="button" class="btn btn-sm btn-light-warning housing-status-btn"
                                                        data-status="legal_notes" data-audit-type="Legal Auditor"
                                                        onclick="setHousingStatus('legal_notes', 'Legal Auditor')">بحاجة
                                                        لمراجعة</button>
                                                </div>
                                            </div>

                                            <div class="audit-action-group">
                                                <div class="audit-action-label">التدقيق الهندسي</div>
                                                <div class="audit-action-controls">
                                                    <button type="button" class="btn btn-sm btn-light-danger housing-status-btn"
                                                        data-status="rejected" data-audit-type="QC/QA Engineer"
                                                        onclick="setHousingStatus('rejected', 'QC/QA Engineer')">مرفوض</button>
                                                    <button type="button" class="btn btn-sm btn-light-success housing-status-btn"
                                                        data-status="accepted" data-audit-type="QC/QA Engineer"
                                                        onclick="setHousingStatus('accepted', 'QC/QA Engineer')">مقبول</button>
                                                    <button type="button" class="btn btn-sm btn-light-warning housing-status-btn"
                                                        data-status="need_review" data-audit-type="QC/QA Engineer"
                                                        onclick="setHousingStatus('need_review', 'QC/QA Engineer')">بحاجة
                                                        لمراجعة</button>
                                                </div>
                                            </div>
                                            @endif

                                            <!--    @hasanyrole('QC/QA Engineer|Engineering Auditor|Database Officer|Auditing Supervisor')
                                                        <button type="button" class="btn btn-sm btn-light-primary housing-status-btn"
                                                            data-status="undp_final_approve"
                                                            onclick="setHousingStatus('undp_final_approve')">
                                                            UNDP Final Approve</button>
                                                        @endhasanyrole -->

                                            <div class="audit-action-group">
                                                <div class="audit-action-label">إجراءات</div>
                                                <div class="audit-action-controls">
                                                    <div class="dropdown">
                                                        <button type="button" class="btn btn-sm btn-light dropdown-toggle"
                                                            data-bs-toggle="dropdown" aria-expanded="false">
                                                            إجراءات
                                                        </button>
                                                        <div class="dropdown-menu">
                                                            <button type="button" class="dropdown-item"
                                                                onclick="openNotesModal('housing','history')">ملاحظات</button>
                                                            <button type="button" class="dropdown-item"
                                                                onclick="reloadHousingAssessmentTable();">تحديث</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="housing_assessment_accordion" class="accordion accordion-icon-toggle"></div>

                                    <table class="d-none" id="kt_table_housing_assessment">
                                        <thead>
                                            <tr>
                                                <th>السؤال</th>
                                                <th>الجواب</th>
                                                <th>تعديل الإجابة</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="audit_save_indicator" class="audit-save-indicator">تم الحفظ بنجاح</div>
        </div>
    </div>

    <div class="modal fade" id="notesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mw-1000px mw-lg-1400px">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="fw-bold" id="notesModalTitle">الملاحظات</h3>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">✖</div>
                </div>

                <div class="modal-body">
                    <div class="mb-5" id="historyWrapper">
                        <h5 class="fw-bold mb-3">سجل الحالات</h5>
                        <div class="table-responsive">
                            <table class="table table-row-bordered align-middle">
                                <thead>
                                    <tr class="fw-bold text-black-800">
                                        <th>الحالة</th>
                                        <th>المستخدم</th>
                                        <th>الملاحظة</th>
                                        <th>التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody id="statusHistoryTable">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">جاري التحميل...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="notesInputWrapper" style="display:none;">
                        <input type="hidden" id="noteId">
                        <textarea id="notesInput" class="form-control form-control-solid" rows="5"
                            placeholder="اكتب الملاحظة هنا..."></textarea>
                        <div class="form-text text-muted mt-2" id="notesLockText" style="display:none;">
                            لا يمكن تعديل الملاحظة لأن الاعتماد النهائي موجود.
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" id="notesSaveBtn" style="display:none;"
                        onclick="submitStatusWithNotes()">حفظ</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="failedUnitsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered mw-1200px">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="fw-bold text-danger">تفاصيل المباني غير المعتمدة</h3>
                    <div class="btn btn-icon btn-sm btn-active-light-primary" data-bs-dismiss="modal">✖</div>
                </div>

                <div class="modal-body" id="failedUnitsContainer">
                    جاري التحميل...
                </div>

                <div class="modal-footer">
                    <button class="btn btn-light" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="legalChallengeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="legalChallengeForm">
                    @csrf
                    <input type="hidden" id="legalChallengeType" name="type">
                    <input type="hidden" id="legalChallengeHousingGlobalId" name="globalid">

                    <div class="modal-header">
                        <h3 class="fw-bold">التحديات القانونية</h3>
                        <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">×</div>
                    </div>

                    <div class="modal-body">
                        <label class="form-label fw-semibold">اختر التحدي القانوني</label>
                        <select name="legal_challenge" id="legal_challenge" class="form-select form-select-solid"
                            data-control="select2" data-placeholder="اختر">
                            <option value=""></option>
                            @foreach($legalChallenges as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary" id="legalChallengeSubmit">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        let isAreaManager = @json(auth()->user()->hasRole('Area Manager'));
        let isAssessmentReadOnly = @json($isAssessmentReadOnly);
        let notesContext = null;
        let pendingStatus = null;
        let pendingAuditType = null;
        let isSubmittingStatus = false;
        let noteEditMode = false;
        let currentNoteRecordId = null;
        let currentApprovalLocked = false;
        let urlHousingGlobalId = @json($housingGlobalid ?? null);
        let buildingCurrentStatus = @json($buildingCurrentStatus);
        let buildingEngineeringStatus = @json($buildingEngineeringStatus);
        let buildingLegalStatus = @json($buildingLegalStatus);
        let buildingFinalStatus = @json($buildingFinalStatus ?? null);
        let buildingObjectId = @json($building?->objectid);
        let buildingLegalChallenge = @json($building?->legal_challenge);
        let currentHousingLegalChallenge = null;
        let initialHousingSelectionDone = false;
        let pendingHousingGlobalId = null;
        let selectedHousingGlobalIds = new Set();
        let inlineSaveLocks = new Set();

        let currentHousingFilter = 'all';
        let currentBuildingFilter = 'all';
        let lastHousingRows = [];
        let lastBuildingRows = [];

        $(function () {
            $('#legal_challenge').select2({
                dropdownParent: $('#legalChallengeModal'),
                width: '100%'
            });
            KTBuildingAssessmentList.init();
            KTBuildingUnitsList.init();
            KTHousingAssessmentList.init();
            setAssessmentActiveStatusButtons('.building-status-btn', buildingEngineeringStatus, buildingLegalStatus, buildingCurrentStatus);
            syncFinalApproveButton();
            selectInitialHousingOption();
        });

        function normalizeSurveyName(name) {
            return String(name || '').trim().toLowerCase();
        }

        function normalizeStatus(statusName) {
            if (!statusName) return null;
            statusName = String(statusName).toLowerCase();
            if (statusName.includes('undp_final_approve')) return 'undp_final_approve';
            if (statusName.includes('accepted')) return 'accepted';
            if (statusName.includes('rejected')) return 'rejected';
            if (statusName.includes('need_review')) return 'need_review';
            if (statusName.includes('legal_notes')) return 'legal_notes';
            return null;
        }

        function normalizeAuditType(statusName) {
            if (!statusName) return null;
            statusName = String(statusName).toLowerCase();
            if (statusName.includes('lawyer') || statusName.includes('legal_notes')) return 'Legal Auditor';
            if (statusName.includes('engineer') || statusName.includes('rejected') || statusName.includes('need_review')) return 'QC/QA Engineer';
            return null;
        }

        function statusPreviewLabel(statusName) {
            const labels = {
                accepted_by_engineer: 'مقبول هندسياً',
                rejected_by_engineer: 'مرفوض هندسياً',
                need_review: 'بحاجة لمراجعة',
                accepted_by_lawyer: 'مقبول قانونياً',
                legal_notes: 'ملاحظات قانونية',
                final_approval: 'اعتماد نهائي',
                undp_final_approve: 'UNDP Final Approve'
            };

            return labels[statusName] || (statusName ? String(statusName).replaceAll('_', ' ') : '-');
        }

        function updateHousingStatusPreview(statusName) {
            $('#housing_current_status_preview').text(statusPreviewLabel(statusName));
        }

        function updateHousingAuditStatusPreviews(engineeringStatusName, legalStatusName) {
            $('#housing_engineering_status_preview').text(statusPreviewLabel(engineeringStatusName));
            $('#housing_legal_status_preview').text(statusPreviewLabel(legalStatusName));
            updateHousingStatusPreview(engineeringStatusName || legalStatusName);
        }

        const BUILDING_SUMMARY_FIELDS = [
            'objectid',
            'owner_name',
            'floor_nos',
            'ground_floor_area__m2',
            'floor_area_m2',
            'building_roof_type',
            'concrete_area',
            'aspestos_area',
            'comments_recommendations'
        ];

        const BUILDING_SUMMARY_LABELS = {
            objectid: 'رقم المبنى',
            owner_name: 'اسم المالك',
            floor_nos: 'عدد الطوابق',
            ground_floor_area__m2: 'مساحة الطابق الأرضي',
            floor_area_m2: 'مساحة الطابق المتكرر',
            building_roof_type: 'نوع سطح المبنى',
            concrete_area: 'مساحة الباطون',
            aspestos_area: 'مساحة الصاج',
            comments_recommendations: 'التوصيات'
        };

        function isAnswered(row) {
            let text = $('<div>').html(row.answer || '').text().trim();
            return text !== '' && text !== '-';
        }

        function isEdited(row) {
            let answerHtml = String(row.answer || '').toLowerCase();
            return answerHtml.includes('آخر تعديل') || answerHtml.includes('last edit') || answerHtml.includes('modified');
        }

        function showAuditSaveIndicator() {
            $('#audit_save_indicator').stop(true, true).fadeIn(150).delay(1200).fadeOut(250);
        }

        function initInlineEditors() {
            $('.inline-edit-select').each(function () {
                let el = $(this);
                if (el.hasClass('select2-hidden-accessible')) {
                    el.select2('destroy');
                }

                if (el.find('option').length <= 1) {
                    return;
                }

                el.select2({
                    width: '100%',
                    dir: 'rtl',
                    minimumResultsForSearch: 0,
                    dropdownAutoWidth: true
                });
            });
        }

        function sectionHtml(section, items, index, prefix, opened = false) {
            let answered = items.filter(row => isAnswered(row)).length;
            let percent = items.length ? Math.round((answered / items.length) * 100) : 0;
            let sectionId = prefix + '_section_' + index;

            let html = `
                                                                                                                                                                                <div class="assessment-section mb-4">
                                                                                                                                                                                    <div class="assessment-section-header ${opened ? '' : 'collapsed'} d-flex justify-content-between align-items-center flex-wrap gap-3"
                                                                                                                                                                                         data-bs-toggle="collapse"
                                                                                                                                                                                         data-bs-target="#${sectionId}"
                                                                                                                                                                                         aria-expanded="${opened ? 'true' : 'false'}">
                                                                                                                                                                                        <div>
                                                                                                                                                                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                                                                                                                                                <span class="collapse-indicator collapse-indicator-open">▲</span>
                                                                                                                                                                                                <span class="collapse-indicator collapse-indicator-closed">▼</span>
                                                                                                                                                                                                <div class="fw-bold fs-5 text-gray-800">${section}</div>
                                                                                                                                                                                                <span class="badge badge-light-warning collapse-state-badge">مغلق</span>
                                                                                                                                                                                            </div>
                                                                                                                                                                                            <div class="section-progress-bar mt-2">
                                                                                                                                                                                                <div class="section-progress-fill" style="width:${percent}%"></div>
                                                                                                                                                                                            </div>
                                                                                                                                                                                        </div>

                                                                                                                                                                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                                                                                                                                            <span class="badge badge-light-primary">${items.length} سؤال</span>
                                                                                                                                                                                            <span class="badge badge-light-success assessment-progress">${percent}% مكتمل</span>
                                                                                                                                                                                        </div>
                                                                                                                                                                                    </div>

                                                                                                                                                                                    <div id="${sectionId}" class="collapse ${opened ? 'show' : ''}">
                                                                                                                                                                            `;

            items.forEach(function (row) {
                let hasAnswer = isAnswered(row);
                let edited = isEdited(row);

                html += `
                                                                                                        <div class="assessment-item ${hasAnswer ? 'has-answer' : 'is-missing'} ${edited ? 'is-edited' : ''} ${row.rowClass || ''}">                                                                                <div class="row g-4 align-items-start">
                                                                                                                                                                                            <div class="col-xl-5 col-lg-12">
                                                                                                                                                                                                <div class="assessment-question">${row.question || '-'}</div>
                                                                                                                                                                                            </div>

                                                                                                                                                                                            <div class="col-xl-3 col-lg-6">
                                                                                                                                                                                                <div class="text-muted fs-8 mb-1">الجواب</div>
                                                                                                                                                                                                <div class="assessment-answer">${row.answer || '-'}</div>
                                                                                                                                                                                            </div>

                                                                                                                                                                                          ${!isAreaManager && !isAssessmentReadOnly ? `
                                                                                <div class="col-xl-4 col-lg-6">
                                                                                    <div class="text-muted fs-8 mb-1">تعديل الإجابة</div>
                                                                                    <div class="assessment-edit">${row.editAnswer || '-'}</div>
                                                                                </div>
                                                                            ` : ''}
                                                                                                                                                                                        </div>
                                                                                                                                                                                    </div>
                                                                                                                                                                                `;
            });

            html += `</div></div>`;
            return html;
        }

        function isAuditAttachmentRow(row) {
            let name = normalizeSurveyName(row.name);
            let section = String(row.section || '').trim().toLowerCase();
            return name === 'attachments' || section === 'المرفقات' || section === 'attachments';
        }

        function keepAttachmentRowsVisible(rows, filteredRows) {
            let pinnedAttachmentRows = [];
            let pinnedRows = new Set();

            rows.forEach(function (row) {
                if (isAuditAttachmentRow(row) && !pinnedRows.has(row)) {
                    pinnedAttachmentRows.push(row);
                    pinnedRows.add(row);
                }
            });

            return pinnedAttachmentRows.concat(filteredRows.filter(row => !pinnedRows.has(row)));
        }

        function applyAuditFilter(rows, filter) {
            if (filter === 'missing') return keepAttachmentRowsVisible(rows, rows.filter(row => !isAnswered(row)));
            if (filter === 'edited') return keepAttachmentRowsVisible(rows, rows.filter(row => isEdited(row)));
            if (filter === 'answered') return keepAttachmentRowsVisible(rows, rows.filter(row => isAnswered(row)));
            if (filter === 'attachments') {
                return rows.filter(row => isAuditAttachmentRow(row));
            }
            return keepAttachmentRowsVisible(rows, rows);
        }

        function renderAccordion(target, rows, filter, prefix) {
            rows = applyAuditFilter(rows, filter);

            let html = '';
            orderedSectionGroups(rows).forEach(function (group, index) {
                html += sectionHtml(group.section, group.rows, index, prefix, group.section !== 'عام');
            });

            $(target).html(html || `
                                                                                                                                                                                <div class="alert alert-light-warning">
                                                                                                                                                                                    لا توجد نتائج مطابقة للفلتر الحالي.
                                                                                                                                                                                </div>
                                                                                                                                                                            `);

            setTimeout(function () {
                initInlineEditors();
                if (typeof KTMenu !== 'undefined') KTMenu.createInstances();
            }, 100);
        }

        function orderedSectionGroups(rows) {
            let groups = [];
            let groupsBySection = {};

            rows.forEach(function (row) {
                let section = row.section || 'عام';

                if (!groupsBySection[section]) {
                    groupsBySection[section] = {
                        section: section,
                        rows: []
                    };
                    groups.push(groupsBySection[section]);
                }

                groupsBySection[section].rows.push(row);
            });

            return groups;
        }

        function sectionSortWeight(section, prefix) {
            const buildingOrder = ['1. ملخص المبنى', '2. مرفقات المبنى', '3. ملاحظات المبنى'];
            const housingOrder = ['1. ملخص الوحدة', '2. مرفقات الوحدة', '3. ملاحظات الوحدة'];
            const preferredOrder = prefix === 'housing' ? housingOrder : buildingOrder;
            const index = preferredOrder.indexOf(section);

            return index >= 0 ? index : 100;
        }

        $(document).on('click', '.housing-filter-btn', function () {
            $('.housing-filter-btn').removeClass('is-active');
            $(this).addClass('is-active');
            currentHousingFilter = $(this).data('filter');
            $('#housing_filter_label').text($(this).data('filter-label') || $(this).text().trim());
            renderAccordion('#housing_assessment_accordion', lastHousingRows, currentHousingFilter, 'housing');
        });

        $(document).on('click', '.building-filter-btn', function () {
            $('.building-filter-btn').removeClass('is-active');
            $(this).addClass('is-active');
            currentBuildingFilter = $(this).data('filter');
            $('#building_filter_label').text($(this).data('filter-label') || $(this).text().trim());
            renderAccordion('#building_assessment_accordion', lastBuildingRows, currentBuildingFilter, 'building');
        });

        const BUILDING_SURVEY_MAP = {
            attachments: ['Introduction', 0],
            objectid: ['Introduction', 1],
            weather: ['Introduction', 2],
            security_situation: ['Introduction', 3],
            security_info: ['Introduction', 4],
            assessment_obstacle: ['Introduction', 4.5],
            obstacle_type: ['Introduction', 5],
            assessment_obstacle_info: ['Introduction', 5.5],
            building_name: ['Introduction', 6],
            comments_recommendations: ['6. Engineer Comments', 7],

            building_damage_status: ['Current Damage Status', 100],
            owner_name_1: ['Current Damage Status', 101],
            owner_mobile_1: ['Current Damage Status', 102],
            floor_nos_1: ['Current Damage Status', 103],
            building_address: ['Current Damage Status', 104],
            building_type: ['1. Introduction', 101],
            building_type_other: ['1. Introduction', 102],
            building_use: ['1. Introduction', 103],
            building_name: ['Introduction', 104],
            date_of_damage: ['1. Introduction', 105],
            building_material: ['1. Introduction', 106],
            other_material: ['1. Introduction', 107],
            building_age: ['1. Introduction', 108],
            land_area: ['1. Introduction', 109],
            floor_nos: ['1. Introduction', 110],
            ground_floor_area__m2: ['1. Introduction', 111],
            floor_area_m2: ['1. Introduction', 112],
            units_nos: ['1. Introduction', 112],
            damaged_units_nos: ['1. Introduction', 113],
            occupied_units_nos: ['1. Introduction', 114],
            vacant_units_nos: ['1. Introduction', 115],
            is_damaged_before: ['1. Introduction', 116],
            if_damaged: ['1. Introduction', 117],
            building_debris_exist: ['1. Introduction', 118],
            building_debris_qty: ['1. Introduction', 119],
            building_debris_blocking: ['1. Introduction', 120],
            uxo_present: ['1. Introduction', 121],
            bodies_present: ['1. Introduction', 122],
            estimated_number_of_bodies: ['1. Introduction', 123],
            building_status_visit: ['1. Introduction', 124],
            building_roof_type: ['1. Introduction', 180],
            clay_tile_area: ['1. Introduction', 181],
            concrete_area: ['1. Introduction', 182],
            aspestos_area: ['1. Introduction', 183],
            scorite_area: ['1. Introduction', 184],
            other_roof: ['1. Introduction', 185],
            other_roof_area: ['1. Introduction', 186],

            building_ownership: ['2.1 Introduction', 200],
            owner_status: ['2.1 Introduction', 201],
            building_responsible: ['2.1 Introduction', 202],
            building_authorization: ['2.1 Introduction', 203],
            land_fully_owned: ['2.1 Introduction', 204],
            owner_name: ['2.1 Introduction', 205],
            owner_id: ['2.1 Introduction', 206],
            owner_mobile: ['2.1 Introduction', 207],
            board1_name: ['2.1 Introduction', 208],
            board1_id: ['2.1 Introduction', 209],
            board1_number: ['2.1 Introduction', 210],
            board2_name: ['2.1 Introduction', 211],
            board2_id: ['2.1 Introduction', 212],
            board2_number: ['2.1 Introduction', 213],
            has_authorization_if_not_owner: ['2.1 Introduction', 214],
            authorization_details: ['2.1 Introduction', 215],
            is_rented: ['2.1 Introduction', 216],
            tenant_names: ['2.1 Introduction', 217],
            agreement_type: ['2.1 Introduction', 218],
            agreement_duration: ['2.1 Introduction', 219],

            has_documents: ['2.2 documents', 400],
            doc_types_available: ['2.2 documents', 401],
            doc_types_other: ['2.2 documents', 402],
            no_documents_reason: ['2.2 documents', 403],
            need_renew_docs: ['2.2 documents', 404],
            doc_challenges: ['2.2 documents', 405],
            doc_challenges_other: ['2.2 documents', 406],
            id_number_photo: ['3. Building Attachments', 407],
            land_ownership_photo: ['3. Building Attachments', 408],
            municipal_permit_photo: ['3. Building Attachments', 409],
            other_documents_photo: ['3. Building Attachments', 410],

            has_elevator: ['4. Building Services', 400],
            elevator_number: ['4. Building Services', 401],
            elevator_status: ['4. Building Services', 402],
            elevator_box: ['4. Building Services', 403],
            elevator_motor: ['4. Building Services', 404],
            has_solar: ['4. Building Services', 405],
            solar_damage_status: ['4. Building Services', 406],
            has_well: ['4. Building Services', 407],
            well_damage_status: ['4. Building Services', 408],
            has_fence: ['4. Building Services', 409],
            fence_damage_status: ['4. Building Services', 410],
            fence_length: ['4. Building Services', 411],
            has_electric_room: ['4. Building Services', 412],
            electric_room_damage_status: ['4. Building Services', 413],
            has_sewage: ['4. Building Services', 414],
            sewage_damage_status: ['4. Building Services', 415],
            service_ownership: ['4. Building Services', 416],
            service_ownership_name: ['4. Building Services', 417],
            has_other_service: ['4. Building Services', 418],
            other_service_details: ['4. Building Services', 419],
            building_services_notes: ['4. Building Services', 420],

            staircase_status: ['5. Building Accessories', 500],
            staircase_widt: ['5. Building Accessories', 501],
            has_parking: ['5. Building Accessories', 502],
            parking_status: ['5. Building Accessories', 503],
            garage_area: ['5. Building Accessories', 504],
            garage_type: ['5. Building Accessories', 505],
            has_canopy: ['5. Building Accessories', 506],
            canopy_status: ['5. Building Accessories', 507],
            carport_length: ['5. Building Accessories', 508],
            carport_width: ['5. Building Accessories', 509],
            carport_height: ['5. Building Accessories', 510],
            has_basement: ['5. Building Accessories', 511],
            basement_status: ['5. Building Accessories', 512],
            basement_area: ['5. Building Accessories', 513],
            has_mezzanine: ['5. Building Accessories', 514],
            mezzanine_status: ['5. Building Accessories', 515],
            roof_terrace_area: ['5. Building Accessories', 516],
            // comments_recommendations: ['6. Engineer Comments', 600],
            building_image: ['6. Engineer Comments', 601],
            building_image2: ['6. Engineer Comments', 602],
        };

        const HOUSING_SURVEY_MAP = {
            attachments: ['7. Unit Introduction', 700],

            housing_unit_group: ['7. Unit Introduction', 701],
            housing_unit_type: ['7. Unit Introduction', 702],
            unit_damage_status: ['7. Unit Introduction', 703],
            final_comments: ['Photos & Final Comments', 704],
            page8: ['8. Unit Information', 800],
            floor_number: ['8. Unit Information', 801],
            housing_unit_number: ['8. Unit Information', 802],
            unit_direction: ['8. Unit Information', 803],
            damaged_area_m2: ['8. Unit Information', 804],
            unit_roof_type: ['8. Unit Information', 805],
            unit_clay_tile_area: ['8. Unit Information', 806],
            unit_concrete_area: ['8. Unit Information', 807],
            unit_aspestos_area: ['8. Unit Information', 808],
            unit_scorite_area: ['8. Unit Information', 809],
            unit_other_roof: ['8. Unit Information', 810],
            unit_other_roof_area: ['8. Unit Information', 811],
            infra_type2: ['8. Unit Information', 812],
            house_unit_ownership: ['8. Unit Information', 813],
            other_ownership: ['8. Unit Information', 814],
            occupied: ['8. Unit Information', 815],
            number_of_rooms: ['8. Unit Information', 816],
            number_of_bathrooms: ['8. Unit Information', 817],

            page9: ['9. Household and Unit Information', 900],
            identity_type1: ['9. Household and Unit Information', 901],
            id_number1: ['9. Household and Unit Information', 902],
            passport1: ['9. Household and Unit Information', 903],
            other_id1: ['9. Household and Unit Information', 904],
            unit_owner: ['7. Unit Introduction', 905],
            q_9_3_1_first_name: ['9. Household and Unit Information', 906],
            q_9_3_2_second_name__father: ['9. Household and Unit Information', 907],
            q_9_3_3_third_name__grandfather: ['9. Household and Unit Information', 908],
            q_9_3_4_last_name: ['9. Household and Unit Information', 909],
            sex: ['9. Household and Unit Information', 910],
            mobile_number: ['7. Unit Introduction', 911],
            additional_mobile: ['7. Unit Introduction', 912],
            owner_job: ['9. Household and Unit Information', 913],
            other_job: ['9. Household and Unit Information', 914],
            age: ['9. Household and Unit Information', 915],
            marital_status: ['9. Household and Unit Information', 916],
            ownership_image: ['9. Household and Unit Information', 917],

            page10: ['10. Spouses and Disability Information', 1000],
            no_spouses: ['10. Spouses and Disability Information', 1001],
            spouse1: ['10. Spouses and Disability Information', 1002],
            spouse1_id: ['10. Spouses and Disability Information', 1003],
            spouse2: ['10. Spouses and Disability Information', 1004],
            spouse2_id: ['10. Spouses and Disability Information', 1005],
            spouse3: ['10. Spouses and Disability Information', 1006],
            spouse3_id: ['10. Spouses and Disability Information', 1007],
            spouse4: ['10. Spouses and Disability Information', 1008],
            spouse4_id: ['10. Spouses and Disability Information', 1009],
            are_there_people_with_disability: ['10. Spouses and Disability Information', 1010],
            number_of_people_with_disability: ['10. Spouses and Disability Information', 1011],
            handicapped_type: ['10. Spouses and Disability Information', 1012],
            other_handicapped: ['10. Spouses and Disability Information', 1013],
            is_refugee: ['10. Spouses and Disability Information', 1014],
            unrwa_registration_number: ['10. Spouses and Disability Information', 1015],

            page11: ['11. Family Size', 1100],
            number_of_nuclear_families: ['11. Family Size', 1101],
            mchildren_001: ['11. Family Size', 1102],
            myoung: ['11. Family Size', 1103],
            melderly: ['11. Family Size', 1104],
            fchildren: ['11. Family Size', 1105],
            fyoung_001: ['11. Family Size', 1106],
            felderly: ['11. Family Size', 1107],
            pregnant: ['11. Family Size', 1108],
            lactating: ['11. Family Size', 1109],

            page12: ['12. Current Residence and Refugee Status', 1200],
            the_unit_resident: ['12. Current Residence and Refugee Status', 1201],
            current_address: ['12. Current Residence and Refugee Status', 1202],
            current_residence: ['12. Current Residence and Refugee Status', 1203],
            current_residence_other: ['12. Current Residence and Refugee Status', 1204],
            shelter_name: ['12. Current Residence and Refugee Status', 1205],
            shelter_type: ['12. Current Residence and Refugee Status', 1206],
            shelter_type_other: ['12. Current Residence and Refugee Status', 1207],
            governorate: ['12. Current Residence and Refugee Status', 1208],
            locality: ['12. Current Residence and Refugee Status', 1209],
            neighborhood: ['12. Current Residence and Refugee Status', 1210],
            street: ['12. Current Residence and Refugee Status', 1211],
            closest_facility2: ['12. Current Residence and Refugee Status', 1212],

            page13: ['13. Household and Rentee', 1300],
            identity_type2: ['13. Household and Rentee', 1301],
            rentee_id_passport_number: ['13. Household and Rentee', 1302],
            rentee_resident_full_name: ['13. Household and Rentee', 1303],
            q_13_3_1_first_name: ['13. Household and Rentee', 1304],
            q_13_3_2_second_name__father: ['13. Household and Rentee', 1305],
            q_13_3_3_third_name__grandfather: ['13. Household and Rentee', 1306],
            q_13_3_4_last_name__family: ['13. Household and Rentee', 1307],
            rentee_mobile_number: ['13. Household and Rentee', 1308],
            work_type: ['13. Household and Rentee', 1309],
            other_work: ['13. Household and Rentee', 1310],

            page14: ['14. Unit Finishing and Internal Damaged', 1400],
            external_finishing_of_the_unit: ['14. Unit Finishing and Internal Damaged', 1401],
            other_external_finishing: ['14. Unit Finishing and Internal Damaged', 1402],
            is_finished: ['14. Unit Finishing and Internal Damaged', 1403],
            internal_finishing_of_the_unit: ['14. Unit Finishing and Internal Damaged', 1404],
            finishing_extent: ['14. Unit Finishing and Internal Damaged', 1405],
            finishing_partial_types: ['14. Unit Finishing and Internal Damaged', 1406],
            has_fire: ['14. Unit Finishing and Internal Damaged', 1407],
            fire_extent: ['14. Unit Finishing and Internal Damaged', 1408],
            fire_severity: ['14. Unit Finishing and Internal Damaged', 1409],
            fire_locations: ['14. Unit Finishing and Internal Damaged', 1410],
            fire_rooms_count: ['14. Unit Finishing and Internal Damaged', 1411],
            fire_area: ['14. Unit Finishing and Internal Damaged', 1412],
            furniture_ownership: ['12. Current Residence and Refugee Status', 1413],
            percentage_of_damaged_furniture: ['12. Current Residence and Refugee Status', 1414],
            unit_stripping: ['14. Unit Finishing and Internal Damaged', 1415],
            unit_stripping_details: ['14. Unit Finishing and Internal Damaged', 1416],
            stripping_area: ['14. Unit Finishing and Internal Damaged', 1417],
            stripping_locations: ['14. Unit Finishing and Internal Damaged', 1418],
            rubble_removal_is_needed: ['14. Unit Finishing and Internal Damaged', 1419],
            activation_of_uxo_ha_d_material_clearance: ['14. Unit Finishing and Internal Damaged', 1420],
            unit_support_needed: ['14. Unit Finishing and Internal Damaged', 1421],
            is_the_housing_unit_or_living_habitable: ['14. Unit Finishing and Internal Damaged', 1422],

            mhpss: ['15. Mental Health and Psychosocial Support (MHPSS)', 1500],
            mhpss_experinced: ['15. Mental Health and Psychosocial Support (MHPSS)', 1501],
            other_mhpss_exp: ['15. Mental Health and Psychosocial Support (MHPSS)', 1502],
            mhpss_support: ['15. Mental Health and Psychosocial Support (MHPSS)', 1503],
            other_mhpss_support: ['15. Mental Health and Psychosocial Support (MHPSS)', 1504],
            community_participation: ['15. Mental Health and Psychosocial Support (MHPSS)', 1505],

            ce: ['16. Community Needs and Preferences Survey', 1600],
            ce1: ['16. Community Needs and Preferences Survey', 1601],
            prefab_moving: ['16. Community Needs and Preferences Survey', 1602],
            prefab_moving_maybe: ['16. Community Needs and Preferences Survey', 1603],
            prefab_types: ['16. Community Needs and Preferences Survey', 1604],
            other_prefab_types: ['16. Community Needs and Preferences Survey', 1605],
            prefab_pref: ['16. Community Needs and Preferences Survey', 1606],
            ce2: ['16. Community Needs and Preferences Survey', 1607],
            reh_kitchen: ['16. Community Needs and Preferences Survey', 1608],
            reh_bathroom: ['16. Community Needs and Preferences Survey', 1609],
            reh_type: ['16. Community Needs and Preferences Survey', 1610],
            ce3: ['16. Community Needs and Preferences Survey', 1611],
            additional_comments: ['16. Community Needs and Preferences Survey', 1612],

            techncial_boq: ['17. Techncial-BOQ', 1700],
            tech_boq: ['Techncial-BOQ', 1701],
            // final_comments: ['3. ملاحظات الوحدة', 301],

        };

        const BOQ_GROUPS = [
            ['dm', 'Demolishing Works', 1710],
            ['bl', 'Blocks Works', 1730],
            ['co', 'Concrete Works', 1740],
            ['al', 'Aluminum Works', 1810],
            ['wd', 'Wood Works', 1830],
            ['mt', 'Metal Works', 1850],
            ['cm', 'Combined', 1870],
            ['pm', 'Plumping Works', 1890],
            ['el', 'Electrical Works', 1930],
            ['pv', 'PV System Works', 1970],
            ['item', 'Miscellaneous Works', 1990],
            ['quant', 'Miscellaneous Works', 1995],
        ];

        function getDynamicBoqMap(name) {
            name = normalizeSurveyName(name);
            let pMatch = name.match(/^p(\d+)/);
            if (pMatch) {
                let p = Number(pMatch[1]);
                if (p === 11) return ['Demolishing Works', 1710];
                if (p === 12) return ['Blocks Works', 1730];
                if (p === 13) return ['Concrete Works', 1740];
                if (p === 14) return ['Internal Finishings Works', 1750];
                if (p === 15) return ['Aluminum Works', 1810];
                if (p === 16) return ['Wood Works', 1830];
                if (p === 17) return ['Metal Works', 1850];
                if (p === 18) return ['Combined', 1870];
                if (p === 19) return ['Plumping Works', 1890];
                if (p === 20) return ['Electrical Works', 1930];
                if (p === 21) return ['PV System Works', 1970];
                if (p === 22) return ['Miscellaneous Works', 1990];
            }

            let fnMatch = name.match(/^fn(\d+)/);
            if (fnMatch) {
                let fn = Number(fnMatch[1]);

                if (fn >= 1 && fn <= 3) return ['Painting Works', 1750 + fn];
                if ([5, 6, 7, 8, 10].includes(fn)) return ['Tiling Works', 1760 + fn];
                if (fn === 4 || (fn >= 11 && fn <= 15)) return ['Marble Works', 1770 + fn];
                if (fn >= 16 && fn <= 26) return ['Plastering Works (Gypsum / Plaster)', 1780 + fn];
                if (fn >= 27 && fn <= 31) return ['External Finishings Works', 1790 + fn];
            }

            for (let i = 0; i < BOQ_GROUPS.length; i++) {
                let [prefix, section, base] = BOQ_GROUPS[i];
                if (name.startsWith(prefix)) {
                    let number = Number((name.match(/\d+/) || [0])[0]);
                    return [section, base + number];
                }
            }

            return null;
        }

        function getHousingMap(row) {
            let name = normalizeSurveyName(row.name);

            if (HOUSING_SURVEY_MAP[name]) return HOUSING_SURVEY_MAP[name];

            let boq = getDynamicBoqMap(name);
            if (boq) return boq;

            return null;
        }

        function getBuildingMap(row) {
            let name = normalizeSurveyName(row.name);
            return BUILDING_SURVEY_MAP[name] || null;
        }

        function setActiveStatusButton(selector, status, auditType = null) {
            if (!status) {
                refreshStatusButtonAvailability(selector);
                return;
            }

            if (auditType) {
                $(selector + '[data-audit-type="' + auditType + '"]').removeClass('is-active');
            } else {
                $(selector + '[data-status="' + status + '"]').removeClass('is-active');
            }

            markStatusButtonActive(selector, status, auditType);
            refreshStatusButtonAvailability(selector);
        }

        function setAssessmentActiveStatusButtons(selector, engineeringStatus = null, legalStatus = null, currentStatus = null) {
            $(selector).removeClass('is-active');

            markStatusButtonActive(selector, normalizeStatus(engineeringStatus), 'QC/QA Engineer');
            markStatusButtonActive(selector, normalizeStatus(legalStatus), 'Legal Auditor');

            if (normalizeStatus(currentStatus) === 'undp_final_approve') {
                markStatusButtonActive(selector, 'undp_final_approve');
            }

            refreshStatusButtonAvailability(selector);
        }

        function markStatusButtonActive(selector, status, auditType = null) {
            if (!status) return;

            let activeSelector = selector + '[data-status="' + status + '"]';
            if (auditType) {
                activeSelector += '[data-audit-type="' + auditType + '"]';
            }

            $(activeSelector).addClass('is-active');
        }

        function refreshStatusButtonAvailability(selector) {
            $(selector).each(function () {
                let button = $(this);
                button.prop('disabled', button.hasClass('is-active'));
            });
        }

        function getFirstHousingOptionValue() {
            return $('[name="globalid"]').find('option').eq(1).val() || null;
        }

        function selectInitialHousingOption() {
            let select = $('[name="globalid"]');
            if (!select.length) return null;

            let valueToSelect = null;

            if (urlHousingGlobalId && select.find('option[value="' + urlHousingGlobalId + '"]').length) {
                valueToSelect = urlHousingGlobalId;
            } else {
                valueToSelect = getFirstHousingOptionValue();
            }

            if (valueToSelect) {
                pendingHousingGlobalId = valueToSelect;
                select.val(valueToSelect).trigger('change');
            }

            return valueToSelect;
        }

        function highlightHousingRowByGlobalId(datatable, globalid) {
            let targetRow = null;
            $('#housing_table tbody tr').removeClass('selected');

            $('#housing_table tbody tr').each(function () {
                let rowData = datatable.row(this).data();
                if (rowData && rowData.globalid == globalid) {
                    targetRow = $(this);
                    return false;
                }
            });

            if (targetRow && targetRow.length) {
                targetRow.addClass('selected');
                return targetRow;
            }

            return null;
        }

        function getSelectedHousingGlobalIds() {
            return Array.from(selectedHousingGlobalIds);
        }

        function syncHousingSelectionCheckboxes(datatable) {
            let visibleIds = [];

            $('#housing_table tbody tr').each(function () {
                let row = datatable.row(this).data();
                if (!row || !row.globalid) return;

                visibleIds.push(row.globalid);
                let isSelected = selectedHousingGlobalIds.has(row.globalid);
                $(this).toggleClass('multi-selected', isSelected);
                $(this).find('.housing-unit-select').prop('checked', isSelected);
            });

            $('#select_all_housing_units').prop(
                'checked',
                visibleIds.length > 0 && visibleIds.every((globalid) => selectedHousingGlobalIds.has(globalid))
            );
        }

        function autoSelectAndClickHousingRow(datatable) {
            let selectedId = pendingHousingGlobalId || $('[name="globalid"]').val();

            if (!selectedId) {
                selectedId = getFirstHousingOptionValue();
                if (selectedId) {
                    $('[name="globalid"]').val(selectedId).trigger('change');
                    pendingHousingGlobalId = selectedId;
                }
            }

            let targetRow = highlightHousingRowByGlobalId(datatable, selectedId);

            if (targetRow && targetRow.length) {
                targetRow.trigger('click');
                initialHousingSelectionDone = true;
                pendingHousingGlobalId = null;
                return;
            }

            let firstRow = $('#housing_table tbody tr:first');
            if (firstRow.length) {
                firstRow.addClass('selected');
                firstRow.trigger('click');
                initialHousingSelectionDone = true;
                pendingHousingGlobalId = null;
            }
        }

        function openNotesModal(type, mode = 'history', status = null, auditType = null) {
            notesContext = type;
            pendingStatus = status;
            pendingAuditType = auditType;
            noteEditMode = false;
            currentNoteRecordId = null;
            currentApprovalLocked = false;

            $('#noteId').val('');
            $('#notesInput').val('').prop('readonly', false);
            $('#notesSaveBtn').hide().text('حفظ').prop('disabled', false).attr('onclick', 'submitStatusWithNotes()');

            $('#notesLockText').hide();
            $('#historyWrapper').hide();
            $('#notesInputWrapper').hide();

            let globalid = getSelectedGlobalId(type);

            if (!globalid) {
                toastr.warning(type === 'building' ? 'لا يوجد مبنى محدد' : 'يرجى اختيار الوحدة أولاً');
                return;
            }

            if (mode === 'history') {
                $('#notesModalTitle').text(type === 'building' ? 'سجل حالات المبنى' : 'سجل حالات الوحدة');
                $('#historyWrapper').show();
                renderHistoryLoading();
                loadStatusHistory(type, globalid);
            } else if (mode === 'note') {
                $('#notesModalTitle').text(type === 'building' ? 'إضافة ملاحظة للمبنى' : 'إضافة ملاحظة للوحدة');
                $('#notesInputWrapper').show();
                $('#notesSaveBtn').show().text('حفظ').prop('disabled', false).attr('onclick', 'submitStatusWithNotes()');
            }

            bootstrap.Modal.getOrCreateInstance(document.getElementById('notesModal')).show();
        }

        function getSelectedGlobalId(type) {
            if (type === 'building') return @json($buildingGlobalid);
            return $("[name='globalid']").val() || null;
        }

        function renderHistoryLoading() {
            $('#statusHistoryTable').html(`<tr><td colspan="5" class="text-center text-muted">جاري التحميل...</td></tr>`);
        }

        function renderHistoryEmpty() {
            $('#statusHistoryTable').html(`<tr><td colspan="5" class="text-center text-muted">لا يوجد سجل حالات</td></tr>`);
        }

        function renderHistoryError() {
            $('#statusHistoryTable').html(`<tr><td colspan="5" class="text-center text-danger">فشل تحميل السجل</td></tr>`);
        }

        function escapeHtml(text) {
            if (text === null || text === undefined || text === '') return '-';
            return $('<div>').text(text).html();
        }

        function escapeAttribute(text) {
            if (text === null || text === undefined) return '';
            return $('<div>').text(text).html();
        }

        function renderStatusBadge(item) {
            let label = item.status_label ?? item.status_name ?? '-';
            let badgeClass = item.status_badge_class ?? 'badge badge-light fw-bold';
            badgeClass = String(badgeClass).replace(/[^a-zA-Z0-9 _-]/g, '').trim();

            return `<span class="${badgeClass}">${escapeHtml(label)}</span>`;
        }

        function renderHistoryNoteCell(item) {
            let canEdit = !isAssessmentReadOnly && !!item.can_edit && !!item.note_id;
            let title = canEdit
                ? 'انقر مرتين للتعديل'
                : (item.has_final_approve ? 'لا يمكن التعديل بعد الاعتماد النهائي' : '');

            return `
                <td class="history-note-cell${canEdit ? ' editable-history-note' : ''}"
                    data-note-id="${escapeAttribute(item.note_id ?? '')}"
                    data-can-edit="${canEdit ? '1' : '0'}"
                    data-locked="${item.has_final_approve ? '1' : '0'}"
                    title="${escapeAttribute(title)}">
                    <span class="history-note-text">${escapeHtml(item.notes ?? '-')}</span>
                </td>
            `;
        }

        function loadStatusHistory(type, globalid) {
            let url = type === 'building' ? "{{ route('building.status.history') }}" : "{{ route('housing.status.history') }}";

            $.ajax({
                url: url,
                method: "GET",
                data: { globalid: globalid },
                success: function (response) {
                    let history = [];
                    if (response && response.status !== undefined && Array.isArray(response.history)) history = response.history;
                    else if (Array.isArray(response)) history = response;

                    if (!history.length) {
                        renderHistoryEmpty();
                        return;
                    }

                    let rows = '';
                    history.forEach(function (item) {
                        rows += `
                                                                                                                                                                                            <tr>
                                                                                                                                                                                                <td>${renderStatusBadge(item)}</td>
                                                                                                                                                                                                <td>${escapeHtml(item.user_name ?? '-')}</td>
                                                                                                                                                                                                ${renderHistoryNoteCell(item)}
                                                                                                                                                                                                <td>${escapeHtml(item.created_at ?? '-')}</td>
                                                                                                                                                                                            </tr>
                                                                                                                                                                                        `;
                    });

                    $('#statusHistoryTable').html(rows);
                },
                error: function () {
                    renderHistoryError();
                }
            });
        }

        $(document).on('dblclick', '#statusHistoryTable .history-note-cell', function () {
            beginInlineHistoryNoteEdit($(this));
        });

        function beginInlineHistoryNoteEdit(cell) {
            if (cell.data('editing')) return;

            if (String(cell.data('can-edit')) !== '1') {
                if (String(cell.data('locked')) === '1') {
                    toastr.warning('لا يمكن تعديل الملاحظة لأن الاعتماد النهائي موجود');
                }
                return;
            }

            let noteId = cell.data('note-id');
            if (!noteId) return;

            let originalNote = cell.find('.history-note-text').text();
            if (originalNote === '-') originalNote = '';

            cell.data('editing', true);
            cell.data('original-note', originalNote);
            cell.html(`
                <textarea class="form-control form-control-sm history-note-editor" rows="3">${escapeHtml(originalNote)}</textarea>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-primary btn-save-history-note">حفظ</button>
                    <button type="button" class="btn btn-sm btn-light btn-cancel-history-note">إلغاء</button>
                </div>
            `);
            cell.find('.history-note-editor').focus();
        }

        $(document).on('click', '.btn-cancel-history-note', function () {
            let cell = $(this).closest('.history-note-cell');
            cancelInlineHistoryNoteEdit(cell);
        });

        function cancelInlineHistoryNoteEdit(cell) {
            let originalNote = cell.data('original-note') ?? '';
            cell.data('editing', false);
            cell.html(`<span class="history-note-text">${escapeHtml(originalNote || '-')}</span>`);
        }

        $(document).on('click', '.btn-save-history-note', function () {
            let cell = $(this).closest('.history-note-cell');
            saveInlineHistoryNoteEdit(cell);
        });

        function saveInlineHistoryNoteEdit(cell) {
            let noteId = cell.data('note-id');
            let notes = cell.find('.history-note-editor').val() ?? '';

            $.ajax({
                url: "{{ route('assessment.notes.update') }}",
                method: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    id: noteId,
                    notes: notes,
                    type: notesContext
                },
                beforeSend: function () {
                    cell.find('textarea, button').prop('disabled', true);
                },
                success: function (response) {
                    cell.data('editing', false);
                    cell.html(`<span class="history-note-text">${escapeHtml(notes || '-')}</span>`);

                    if (response.user_name) {
                        cell.closest('tr').find('td').eq(1).text(response.user_name);
                    }

                    toastr.success(response.message || 'تم تحديث الملاحظة بنجاح');

                    if (notesContext === 'building') {
                        reloadBuildingAssessmentTable();
                        reloadBuildingUnitsTable();
                    } else if (notesContext === 'housing') {
                        reloadHousingAssessmentTable();
                        reloadBuildingUnitsTable();
                    }
                },
                error: function (xhr) {
                    cell.find('textarea, button').prop('disabled', false);
                    toastr.error(xhr.responseJSON?.message || 'حدث خطأ أثناء تحديث الملاحظة');
                }
            });
        }

        function closeNotesModal() {
            let modalEl = document.getElementById('notesModal');
            let modal = bootstrap.Modal.getInstance(modalEl);

            if (modal) {
                modal.hide();
            }

            $('#notesInput').val('');
            pendingStatus = null;
            pendingAuditType = null;
            notesContext = null;
        }

        function reloadBuildingAssessmentTable() {
            if ($.fn.DataTable.isDataTable('#kt_table_building_assessment')) {
                $('#kt_table_building_assessment')
                    .DataTable()
                    .ajax.reload(null, false);
            }
        }
        function reloadHousingAssessmentTable() {
            if ($.fn.DataTable.isDataTable('#kt_table_housing_assessment')) {
                $('#kt_table_housing_assessment')
                    .DataTable()
                    .ajax.reload(null, false);
            }
        }
        function reloadBuildingUnitsTable() {
            if ($.fn.DataTable.isDataTable('#housing_table')) {
                $('#housing_table')
                    .DataTable()
                    .ajax.reload(null, false);
            }
        }
        function syncHousingRowAfterNumberUpdate(globalid) {
            if (!globalid) return;

            let unitsTable = $('#housing_table').DataTable();

            unitsTable.ajax.reload(function () {
                $('#housing_table tbody tr').removeClass('selected');
                $('#housing_table tbody tr').each(function () {
                    let row = unitsTable.row(this).data();
                    if (row && row.globalid == globalid) {
                        $(this).addClass('selected');
                        return false;
                    }
                });

                $('#kt_table_housing_assessment').DataTable().ajax.reload(null, false);
            }, false);
        }


        function saveInlineValue(field, globalid, type, value, callback = null) {
            globalid = resolveInlineGlobalId(globalid, type);

            if (!globalid) {
                toastr.warning(type === 'building_table' ? 'لا يوجد مبنى محدد' : 'يرجى اختيار الوحدة أولاً');
                if (callback) callback(false);
                return;
            }

            let lockKey = [type, globalid, field].join('|');

            if (inlineSaveLocks.has(lockKey)) {
                if (callback) callback(false);
                return;
            }

            inlineSaveLocks.add(lockKey);

            $.ajax({
                url: "{{ route('assessment.inline.update') }}",
                method: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    field: field,
                    globalid: globalid,
                    type: type,
                    value: value
                },
                success: function (response) {
                    toastr.success(response.message || 'تم الحفظ بنجاح');
                    showAuditSaveIndicator();

                    updateAnswerCardAfterSave(field, globalid, type, value, response);

                    if (
                        type === 'housing_table' &&
                        [
                            'damaged_area_m2',
                            'unit_owner',
                            'reh_kitchen',
                            'reh_bathroom',
                            'is_the_housing_unit_or_living_habitable',
                            'external_finishing_of_the_unit',
                            'internal_finishing_of_the_unit',
                            'floor_number',
                            'housing_unit_number',
                            'unit_damage_status'
                        ].includes(field)
                    ) {
                        loadHousingSidebarSummary();
                    }

                    reloadHousingAssessmentTable();
                    reloadBuildingUnitsTable();
                    if (callback) callback(true);
                },
                error: function (xhr) {
                    toastr.error(xhr.responseJSON?.message || 'حدث خطأ أثناء الحفظ');
                    if (callback) callback(false);
                },
                complete: function () {
                    inlineSaveLocks.delete(lockKey);
                }
            });
        }

        function resolveInlineGlobalId(globalid, type) {
            if (globalid !== undefined && globalid !== null && String(globalid).trim() !== '') {
                return globalid;
            }

            if (type === 'building_table') {
                return @json($buildingGlobalid);
            }

            if (type === 'housing_table') {
                return $("[name='globalid']").val() || null;
            }

            return null;
        }

        function setBuildingStatus(status, auditType = null) {
            let globalid = '{{ $buildingGlobalid }}';

            if (!globalid) {
                toastr.warning('لا يوجد مبنى محدد');
                return;
            }

            openNotesModal('building', 'note', status, auditType);
        }

        function setHousingStatus(status, auditType = null) {
            let globalid = $("[name='globalid']").val();

            if (!globalid) {
                toastr.warning('يرجى اختيار الوحدة أولاً');
                return;
            }

            openNotesModal('housing', 'note', status, auditType);
        }

        function openLegalChallengeModal(type, housingGlobalid = null, housingLegalChallenge = null) {
            let isBuilding = type === 'building';
            let globalid = isBuilding ? @json($buildingGlobalid) : (housingGlobalid || $("[name='globalid']").val());
            let selectedHousingIds = isBuilding ? [] : getSelectedHousingGlobalIds();

            if (!isBuilding && !housingGlobalid && selectedHousingIds.length === 0) {
                toastr.warning('يرجى اختيار وحدة واحدة على الأقل');
                return;
            }

            if (!globalid && selectedHousingIds.length === 0) {
                toastr.warning(isBuilding ? 'لا يوجد مبنى محدد' : 'يرجى اختيار الوحدة أولاً');
                return;
            }

            if (!isBuilding && housingGlobalid) {
                $('[name="globalid"]').val(globalid).trigger('change');
                currentHousingLegalChallenge = housingLegalChallenge ?? currentHousingLegalChallenge;
            }

            $('#legalChallengeType').val(type);
            $('#legalChallengeHousingGlobalId').val(isBuilding ? '' : globalid);
            $('#legal_challenge').val(isBuilding ? buildingLegalChallenge : (selectedHousingIds.length === 1 ? (housingLegalChallenge ?? currentHousingLegalChallenge) : null)).trigger('change');
            $('#legalChallengeModal').modal('show');
        }

        $('#legalChallengeForm').on('submit', function (e) {
            e.preventDefault();

            let type = $('#legalChallengeType').val();
            let isBuilding = type === 'building';
            let selectedHousingIds = getSelectedHousingGlobalIds();
            let housingGlobalid = $('#legalChallengeHousingGlobalId').val();
            if (!isBuilding && selectedHousingIds.length === 0 && housingGlobalid) {
                selectedHousingIds = [housingGlobalid];
            }
            let submitButton = $('#legalChallengeSubmit');
            let data = isBuilding
                ? {
                    _token: "{{ csrf_token() }}",
                    building_ids: [buildingObjectId],
                    legal_challenge: $('#legal_challenge').val()
                }
                : {
                    _token: "{{ csrf_token() }}",
                    globalids: selectedHousingIds,
                    legal_challenge: $('#legal_challenge').val()
                };

            if (!isBuilding && selectedHousingIds.length === 0) {
                toastr.warning('يرجى اختيار وحدة واحدة على الأقل');
                return;
            }

            submitButton.prop('disabled', true);

            $.ajax({
                url: isBuilding
                    ? "{{ route('audit.building.legalChallenge') }}"
                    : "{{ route('housing.assessment.legalChallenge') }}",
                method: 'POST',
                data: data,
                success: function (response) {
                    toastr.success(response.message || 'تم الحفظ بنجاح');
                    $('#legalChallengeModal').modal('hide');

                    if (isBuilding) {
                        buildingLegalChallenge = response.legal_challenge;
                        reloadBuildingAssessmentTable();
                    } else {
                        currentHousingLegalChallenge = response.legal_challenge;
                        reloadHousingAssessmentTable();
                        reloadBuildingUnitsTable();
                    }
                },
                error: function (xhr) {
                    toastr.error(xhr.responseJSON?.message || 'حدث خطأ أثناء الحفظ');
                },
                complete: function () {
                    submitButton.prop('disabled', false);
                }
            });
        });


        function submitStatusWithNotes() {
            if (isSubmittingStatus) return;

            let notes = $('#notesInput').val();
            notes = notes ? notes.trim() : '';

            if (!pendingStatus) {
                toastr.warning('اختر حالة أولاً');
                return;
            }

            if (!notesContext) {
                toastr.warning('نوع الحالة غير معروف');
                return;
            }
            if ((!notes || notes.trim() === '') && pendingStatus != 'accepted') {
                toastr.warning('يرجى إدخال الملاحظة');
                $('#notesInput').focus();
                return;
            }

            isSubmittingStatus = true;
            $('#notesSaveBtn').prop('disabled', true);

            let isBuilding = notesContext === 'building';
            let globalid = isBuilding ? '{{ $buildingGlobalid }}' : $("[name='globalid']").val();

            if (!globalid) {
                toastr.warning(isBuilding ? 'لا يوجد مبنى محدد' : 'يرجى اختيار الوحدة أولاً');
                isSubmittingStatus = false;
                $('#notesSaveBtn').prop('disabled', false);
                return;
            }

            $.ajax({
                url: isBuilding
                    ? "{{ route('building.assessment.set.status') }}"
                    : "{{ route('housing.assessment.set.status') }}",
                method: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    globalid: globalid,
                    status: pendingStatus,
                    audit_type: pendingAuditType,
                    notes: notes
                },
                beforeSend: function () {
                    isBuilding
                        ? $('.building-status-btn').prop('disabled', true)
                        : $('.housing-status-btn').prop('disabled', true);
                },
                success: function (response) {
                    toastr.success(response.message || 'تم تحديث الحالة بنجاح');

                    if (isBuilding) {
                        setActiveStatusButton('.building-status-btn', pendingStatus, pendingAuditType);
                        reloadBuildingAssessmentTable();
                    } else {
                        setActiveStatusButton('.housing-status-btn', pendingStatus, pendingAuditType);
                        reloadHousingAssessmentTable();
                    }

                    reloadBuildingUnitsTable();
                    closeNotesModal();
                },
                error: function (xhr) {
                    toastr.error(xhr.responseJSON?.message || 'حدث خطأ أثناء تحديث الحالة');
                },
                complete: function () {
                    isSubmittingStatus = false;
                    $('#notesSaveBtn').prop('disabled', false);

                    if (isBuilding) {
                        refreshStatusButtonAvailability('.building-status-btn');
                    } else {
                        refreshStatusButtonAvailability('.housing-status-btn');
                    }
                }
            });
        }
        $(document).on('click', '.inline-save-btn', function () {
            let btn = $(this);
            let wrapper = btn.closest('.d-flex');
            let input = wrapper.find('.inline-edit-input');

            let field = btn.data('field');
            let type = btn.data('type');
            let globalid = resolveInlineGlobalId(btn.data('globalid'), type);
            let value = input.val();

            btn.prop('disabled', true).html('...');

            saveInlineValue(field, globalid, type, value, function () {
                btn.prop('disabled', false).html('حفظ');
            });
        });

        $(document).on('change', '.inline-edit-select', function () {
            let select = $(this);
            let type = select.data('type');
            saveInlineValue(select.data('field'), resolveInlineGlobalId(select.data('globalid'), type), type, select.val());
        });

        /*         function reloadBuildingAssessmentTable() {
                    if ($.fn.DataTable.isDataTable('#kt_table_building_assessment')) {
                        reloadTableWithoutScroll('#kt_table_building_assessment');
                    }
                }

                function reloadHousingAssessmentTable() {
                    if ($.fn.DataTable.isDataTable('#kt_table_housing_assessment')) {
                        reloadTableWithoutScroll('#kt_table_housing_assessment');
                    }
                }

                function reloadBuildingUnitsTable() {
                    if ($.fn.DataTable.isDataTable('#housing_table')) {
                        reloadTableWithoutScroll('#housing_table');
                    }
                }

                function reloadHousingTabTables() {
                    reloadBuildingUnitsTable();
                    reloadHousingAssessmentTable();
                }
         */
        var KTBuildingAssessmentList = function () {
            var table = document.getElementById('kt_table_building_assessment');
            var datatable;

            var initBuildingTable = function () {
                datatable = $(table).DataTable({
                    serverSide: true,
                    processing: true,
                    info: false,
                    order: [],
                    pageLength: 500,
                    ajax: {
                        url: "{{ url('damage-assessment/showBuildings') }}",
                        data: function (d) { d.globalid = '{{ $buildingGlobalid }}'; },
                        dataSrc: function (json) {
                            let rows = json.data || [];

                            rows.forEach(function (row) {
                                row.section = row.section || 'عام';
                            });

                            lastBuildingRows = rows;
                            renderBuildingSummaryItems(rows);
                            renderAccordion('#building_assessment_accordion', rows, currentBuildingFilter, 'building');

                            return rows;
                        }
                    },
                    columns: [
                        { data: 'question', searchable: false, orderable: false },
                        { data: 'answer', searchable: false, orderable: false },
                        { data: 'editAnswer', searchable: false, orderable: false }
                    ]
                });
            };

            var handleSearchDatatable = function () {
                const filterSearch = document.querySelector('[data-kt-buildingAssessment-table-filter="search"]');
                if (!filterSearch) return;

                filterSearch.addEventListener('keyup', function () {
                    let keyword = this.value.toLowerCase();
                    let rows = lastBuildingRows;

                    if (keyword !== '') {
                        rows = rows.filter(function (row) {
                            let q = $('<div>').html(row.question || '').text().toLowerCase();
                            let a = $('<div>').html(row.answer || '').text().toLowerCase();
                            let n = String(row.name || '').toLowerCase();
                            return q.includes(keyword) || a.includes(keyword) || n.includes(keyword);
                        });
                    }

                    rows = keepAttachmentRowsVisible(lastBuildingRows, rows);

                    renderAccordion('#building_assessment_accordion', rows, currentBuildingFilter, 'building');
                });
            };

            return {
                init: function () {
                    if (!table) return;
                    initBuildingTable();
                    handleSearchDatatable();
                }
            };
        }();

        var KTBuildingUnitsList = function () {
            var table = document.getElementById('housing_table');
            var datatable;

            var initTable = function () {
                datatable = $(table).DataTable({
                    processing: true,
                    serverSide: true,
                    searching: false,
                    lengthChange: false,
                    paging: false,
                    info: false,
                    order: [],
                    pageLength: 25,
                    ajax: {
                        url: "{{ route('housing.units.by.building') }}",
                        data: function (d) { d.globalid = '{{ $buildingGlobalid }}'; }
                    },
                    autoWidth: true,
                    scrollX: false,
                    responsive: false,
                    columns: [
                        {
                            data: 'globalid',
                            name: 'select_unit',
                            searchable: false,
                            orderable: false,
                            className: 'text-center px-2 py-3',
                            render: function (data, type) {
                                if (type !== 'display') {
                                    return data;
                                }

                                return '<input type="checkbox" class="form-check-input housing-unit-select" value="' + $('<div>').text(data || '').html() + '">';
                            }
                        },
                        { data: 'housing_unit_type', name: 'housing_unit_type', className: 'text-start px-2 py-3' },
                        { data: 'unit_damage_status', name: 'unit_damage_status', className: 'text-center px-2 py-3' },
                        { data: 'floor_number', name: 'floor_number', className: 'text-center px-2 py-3' },
                        { data: 'housing_unit_number', name: 'housing_unit_number', className: 'text-center px-2 py-3' },
                        { data: 'owner_name', name: 'owner_name', className: 'text-start px-2 py-3 min-w-280px' },
                        { data: 'unit_direction', name: 'unit_direction', className: 'text-center px-2 py-3' },
                        {
                            data: 'legal_challenge_label',
                            name: 'legal_challenge_label',
                            className: 'text-center px-2 py-3',
                            render: function (data, type, row) {
                                if (type !== 'display') {
                                    return data;
                                }

                                return $('<div>').text(data || '-').html();
                            }
                        },
                        { data: 'legal_audit_status', name: 'legal_audit_status', className: 'text-center px-2 py-3' },
                        { data: 'engineering_audit_status', name: 'engineering_audit_status', className: 'text-center px-2 py-3' },
                        { data: 'final_approval_status', name: 'final_approval_status', className: 'text-center px-2 py-3' }
                    ],
                    createdRow: function (row) {
                        $(row).css('cursor', 'pointer');
                    }
                });

                datatable.on('draw', function () {
                    if (typeof KTMenu !== 'undefined') KTMenu.createInstances();
                    syncHousingSelectionCheckboxes(datatable);

                    if (!initialHousingSelectionDone || pendingHousingGlobalId) {
                        setTimeout(function () {
                            autoSelectAndClickHousingRow(datatable);
                        }, 150);
                    }
                });

                $('#select_all_housing_units').on('change', function () {
                    let checked = $(this).is(':checked');

                    $('#housing_table tbody tr').each(function () {
                        let row = datatable.row(this).data();
                        if (!row || !row.globalid) return;

                        if (checked) {
                            selectedHousingGlobalIds.add(row.globalid);
                        } else {
                            selectedHousingGlobalIds.delete(row.globalid);
                        }
                    });

                    syncHousingSelectionCheckboxes(datatable);
                });

                $('#housing_table tbody').on('change', '.housing-unit-select', function (event) {
                    event.stopPropagation();

                    let row = datatable.row($(this).closest('tr')).data();
                    if (!row || !row.globalid) return;

                    if ($(this).is(':checked')) {
                        selectedHousingGlobalIds.add(row.globalid);
                    } else {
                        selectedHousingGlobalIds.delete(row.globalid);
                    }

                    syncHousingSelectionCheckboxes(datatable);
                });

                $('#housing_table tbody').on('click', 'tr', function (event) {
                    if ($(event.target).is('input, label')) return;

                    let row = datatable.row(this).data();
                    if (!row || !row.globalid) return;

                    $('#housing_table tbody tr').removeClass('selected');
                    $(this).addClass('selected');

                    $('[name="globalid"]').val(row.globalid).trigger('change');
                    currentHousingLegalChallenge = row.legal_challenge || null;
                    updateHousingAuditStatusPreviews(row.current_engineering_status, row.current_legal_status);
                    setAssessmentActiveStatusButtons('.housing-status-btn', row.current_engineering_status, row.current_legal_status, row.current_status);
                });
            };

            return {
                init: function () {
                    if (!table) return;
                    initTable();
                }
            };
        }();

        var KTHousingAssessmentList = function () {
            var table = document.getElementById('kt_table_housing_assessment');
            var datatable;

            var initHousingTable = function () {
                datatable = $(table).DataTable({
                    serverSide: true,
                    processing: true,
                    info: false,
                    order: [],
                    pageLength: 500,
                    ajax: {
                        url: "{{ url('damage-assessment/showHousings') }}",
                        data: function (d) {
                            d.parentglobalid = '{{ $buildingGlobalid }}';
                            d.globalid = $("[name='globalid']").val();
                        },
                        dataSrc: function (json) {

                            let rows = json.data || [];
                            console.log(rows.map(r => ({
                                name: r.name,
                                rowClass: r.rowClass
                            })));
                            rows.forEach(function (row) {
                                row.section = row.section || 'عام';
                                row.rowClass = row.rowClass || '';
                            });

                            lastHousingRows = rows;
                            renderAccordion('#housing_assessment_accordion', rows, currentHousingFilter, 'housing');

                            return rows;
                        }
                    },
                    columns: [
                        { data: 'question', name: 'question', searchable: false, orderable: false },
                        { data: 'answer', name: 'answer', searchable: false, orderable: false },
                        { data: 'editAnswer', name: 'editAnswer', searchable: false, orderable: false }
                    ]
                });

                datatable.on('draw', function () {
                    setTimeout(function () { initInlineEditors(); }, 100);
                    if (typeof KTMenu !== 'undefined') KTMenu.createInstances();
                });
            };

                var handleSearchDatatable = function () {
                     const filterSearch = document.querySelector('[data-kt-HousingAssessment-table-filter="search"]');
                     if (!filterSearch) return;

                     filterSearch.addEventListener('keyup', function () {
                         let keyword = this.value.toLowerCase();
                         let rows = lastHousingRows;

                         if (keyword !== '') {
                             rows = rows.filter(function (row) {
                                 let q = $('<div>').html(row.question || '').text().toLowerCase();
                                 let a = $('<div>').html(row.answer || '').text().toLowerCase();
                                 let n = String(row.name || '').toLowerCase();
                                 return q.includes(keyword) || a.includes(keyword) || n.includes(keyword);
                             });
                         }

                         rows = keepAttachmentRowsVisible(lastHousingRows, rows);

                         renderAccordion('#housing_assessment_accordion', rows, currentHousingFilter, 'housing');
                     });
                 };

             
         /*    var handleSearchDatatable = function () {
                const filterSearch = document.querySelector('[data-kt-HousingAssessment-table-filter="search"]');
                if (!filterSearch) return;

                filterSearch.addEventListener('keydown', function (e) {

                    if (e.key === 'Enter') {
                        e.preventDefault();
                        datatable.search(this.value).draw();
                    }
                });
            };
          */   var handleChangeHousingUnit = function () {
                const filterSelect = $('[name="globalid"]');
                loadHousingSidebarSummary();

                filterSelect.on("change", function () {
                    datatable.ajax.reload(null, false);
                    loadHousingSidebarSummary();
                });
            };

            return {
                init: function () {
                    if (!table) return;
                    initHousingTable();
                    handleSearchDatatable();
                    handleChangeHousingUnit();
                }
            };
        }();

        function loadHousingSidebarSummary() {
            let globalid = $('[name="globalid"]').val();

            if (!globalid) {
                renderHousingSummaryItems([]);
                return;
            }

            $.get("{{ route('housing.summary') }}", { globalid: globalid }, function (res) {
                renderHousingSummaryItems(res.summary_items || []);
            }).fail(function () {
                renderHousingSummaryItems([]);
            });
        }

        function renderHousingSummaryItems(items) {
            const colors = ['info', 'primary', 'warning', 'success', 'danger'];
            let html = '';

            (items || []).forEach(function (item, index) {
                let color = colors[index % colors.length];
                html += `
                                        <div class="col-6 col-lg-12">
                                            <div class="summary-box bg-light-${color}">
                                                <div class="summary-title">${escapeHtml(item.label || '')}</div>
                                                <div class="summary-value text-${color}">${escapeHtml(item.value || '--')}</div>
                                            </div>
                                        </div>`;
            });

            $('#housing_summary_items').html(html || `
                                    <div class="col-12">
                                        <div class="summary-box bg-light">
                                            <div class="summary-title">ملخص الوحدة</div>
                                            <div class="summary-value text-muted">لا توجد قيم مدخلة</div>
                                        </div>
                                    </div>`);
        }

        function renderBuildingSummaryItems(rows) {
            const colors = ['info', 'primary', 'warning', 'success', 'danger'];
            let html = '';

            BUILDING_SUMMARY_FIELDS.forEach(function (field, index) {
                let row = (rows || []).find(function (item) {
                    return normalizeSurveyName(item.name) === field;
                });

                let value = row ? cleanAuditText(row.summaryValue || $('<div>').html(row.answer || '').text()) : '-';
                if (!value) value = '-';

                let color = colors[index % colors.length];
                const isLongValue = field === 'comments_recommendations';

                html += `
                                        <div class="${isLongValue ? 'col-12' : 'col-6 col-lg-12'}">
                                            <div class="summary-box bg-light-${color}">
                                                <div class="summary-title">${escapeHtml(BUILDING_SUMMARY_LABELS[field] || field)}</div>
                                                <div class="summary-value ${isLongValue ? 'summary-value-long' : ''} text-${color}">${escapeHtml(value)}</div>
                                            </div>
                                        </div>`;
            });

            $('#building_summary_items').html(html || `
                                    <div class="col-12">
                                        <div class="summary-box bg-light">
                                            <div class="summary-title">ملخص المبنى</div>
                                            <div class="summary-value text-muted">لا توجد قيم مدخلة</div>
                                        </div>
                                    </div>`);
        }

        function cleanAuditText(text) {
            text = String(text || '')
                .replace(/\s+/g, ' ')
                .replace(/الأصل/g, '')
                .replace(/آخر تعديل/g, '')
                .replace(/اسم المعدّل/g, '')
                .replace(/وقت التعديل/g, '')
                .replace(/عرض سجل التعديلات/g, '')
                .trim();

            // يمنع WindyWindy أو Totally DamagedTotally Damaged
            let half = text.substring(0, text.length / 2);
            if (text.length % 2 === 0 && half === text.substring(text.length / 2)) {
                text = half;
            }

            return text || '-';
        }

        function getCurrentEditValue(input, value) {
            if (input.is('select')) {
                let selected = input.find('option:selected');
                return cleanAuditText(selected.text() || value);
            }

            return cleanAuditText(value);
        }

        function syncFinalApproveButton() {
            let btn = $('#btn_show_assessment_final_approve');

            if (!btn.length) return;

            let isApproved = String(buildingFinalStatus || '').toLowerCase() === 'final_approval';

            btn
                .toggleClass('btn-light-warning', !isApproved)
                .toggleClass('btn-light-success is-active', isApproved)
                .prop('disabled', isApproved)
                .text(isApproved ? 'Final Approved' : 'Final Approve');
        }


        function finalApproveCurrentBuilding() {
            let btn = $('#btn_show_assessment_final_approve');

            if (!buildingObjectId || btn.prop('disabled')) {
                return;
            }

            let submitApproval = function () {
                $.ajax({
                    url: "{{ route('audit.building.finalApprove') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        building_ids: [buildingObjectId]
                    },
                    beforeSend: function () {
                        btn.prop('disabled', true).attr('data-kt-indicator', 'on');
                    },
                    success: function (response) {

                        // إذا فشل الاعتماد، اعرض السبب ولا تعتبره approved
                        if (response.blocked_buildings && response.blocked_buildings.length > 0) {
                            let b = response.blocked_buildings[0];

                            let html = `
                                                <div class="alert alert-warning fw-bold mb-5">
                                                    ${b.reason ?? 'لا يمكن الاعتماد النهائي'}
                                                </div>

                                                <div class="mb-5">
                                                    <div><strong>Building ID:</strong> ${b.building_id ?? '-'}</div>
                                                    <div><strong>اسم المبنى:</strong> ${b.building_name ?? '-'}</div>
                                                    <div><strong>GlobalID:</strong> ${b.building_globalid ?? '-'}</div>
                                                    <div><strong>Status:</strong> ${b.engineer_status ?? '-'}</div>
                                                </div>
                                            `;

                            if (b.failed_units && b.failed_units.length > 0) {
                                html += `
                                                    <div class="table-responsive">
                                                        <table class="table table-row-bordered table-striped align-middle">
                                                            <thead>
                                                                <tr>
                                                                    <th>ObjectID</th>
                                                                    <th>GlobalID</th>
                                                                    <th>اسم المالك</th>
                                                                    <th>Status</th>
                                                                    <th>Reason</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                `;

                                b.failed_units.forEach(function (u) {
                                    html += `
                                                        <tr>
                                                            <td>${u.objectid ?? '-'}</td>
                                                            <td>${u.globalid ?? '-'}</td>
                                                            <td>${u.owner_name ?? '-'}</td>
                                                            <td>${u.engineer_status ?? '-'}</td>
                                                            <td class="text-danger fw-bold">${u.reason ?? '-'}</td>
                                                        </tr>
                                                    `;
                                });

                                html += `
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                `;
                            }

                            $('#failedUnitsContainer').html(html);
                            $('#failedUnitsModal').modal('show');

                            toastr.warning(response.message || 'لم يتم اعتماد المبنى. راجع الأسباب.');
                            btn.prop('disabled', false);
                            return;
                        }

                        // لا تحدّث الواجهة إلا إذا تم اعتماد هذا المبنى فعلاً
                        let approvedIds = response.approved_ids || [];

                        if (
                            response.approved_count > 0 &&
                            (
                                approvedIds.length === 0 ||
                                approvedIds.includes(parseInt(buildingObjectId)) ||
                                approvedIds.includes(String(buildingObjectId))
                            )
                        ) {
                            buildingFinalStatus = 'final_approval';
                            syncFinalApproveButton();
                            reloadBuildingUnitsTable();

                            toastr.success(response.message || 'تم الاعتماد النهائي بنجاح');
                        } else {
                            toastr.warning(response.message || 'لم يتم اعتماد أي مبنى.');
                            btn.prop('disabled', false);
                        }
                    },
                    error: function (xhr) {
                        let message = xhr.responseJSON?.message || 'Final approval failed';
                        toastr.error(message);
                        btn.prop('disabled', false);
                    },
                    complete: function () {
                        btn.removeAttr('data-kt-indicator');
                    }
                });
            };

            if (typeof Swal === 'undefined') {
                submitApproval();
                return;
            }

            Swal.fire({
                title: 'Final Approve',
                text: 'Do you want to final approve this building?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Approve',
                cancelButtonText: 'Cancel',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-warning',
                    cancelButton: 'btn btn-light'
                }
            }).then(function (result) {
                if (result.isConfirmed) {
                    submitApproval();
                }
            });
        }

        function editHistoryCollapseId(type, globalid, field) {
            return 'inline_history_' + btoa(unescape(encodeURIComponent(type + '_' + globalid + '_' + field)))
                .replace(/[^a-zA-Z0-9]/g, '');
        }

        function renderEditHistoryItems(history) {
            history = Array.isArray(history) ? history : [];

            if (!history.length) {
                return '<div class="text-muted small">لا يوجد سجل تعديلات لهذا الحقل.</div>';
            }

            return history.map(function (item) {
                return `
                                                                                                                                                        <div class="border rounded p-2 mb-2 bg-light-info text-start">
                                                                                                                                                            <div><span class="audit-label">القيمة</span>: <span class="fw-semibold">${escapeHtml(cleanAuditText(item.value))}</span></div>
                                                                                                                                                            <div><span class="audit-label">المستخدم</span>: ${escapeHtml(item.user_name || '-')}</div>
                                                                                                                                                            <div><span class="audit-label">الوقت</span>: ${escapeHtml(item.updated_at || '-')}</div>
                                                                                                                                                        </div>
                                                                                                                                                    `;
            }).join('');
        }

        function renderEditCard(originalText, displayValue, userName, updatedAt, type, globalid, field, history) {
            let collapseId = editHistoryCollapseId(type, globalid, field);
            let historyCount = Array.isArray(history) ? history.length : 0;

            return `
                                                                                                                                                    <div class="audit-edit-card">
                                                                                                                                                        <div class="audit-label">الأصل</div>
                                                                                                                                                        <div class="audit-original-value">${escapeHtml(originalText)}</div>

                                                                                                                                                        <div class="audit-label text-warning mt-3">آخر تعديل</div>
                                                                                                                                                        <div class="audit-new-value">${escapeHtml(displayValue)}</div>

                                                                                                                                                        <div class="audit-label text-primary mt-3">اسم المعدّل</div>
                                                                                                                                                        <div>${escapeHtml(userName)}</div>

                                                                                                                                                        <div class="audit-label text-primary mt-3">وقت التعديل</div>
                                                                                                                                                        <div>${escapeHtml(updatedAt)}</div>

                                                                                                                                                        <button type="button"
                                                                                                                                                                class="btn btn-sm btn-light-primary mt-4"
                                                                                                                                                                data-bs-toggle="collapse"
                                                                                                                                                                data-bs-target="#${collapseId}">
                                                                                                                                                            عرض سجل التعديلات (${historyCount})
                                                                                                                                                        </button>

                                                                                                                                                        <div class="collapse mt-3" id="${collapseId}">
                                                                                                                                                            ${renderEditHistoryItems(history)}
                                                                                                                                                        </div>
                                                                                                                                                    </div>
                                                                                                                                                `;
        }

        function updateAnswerCardAfterSave(field, globalid, type, value, response) {
            let input = $('[data-field="' + field + '"][data-globalid="' + globalid + '"][data-type="' + type + '"]');
            let item = input.closest('.assessment-item');

            if (!item.length) return;

            let answerBox = item.find('.assessment-answer');

            let originalText = answerBox.find('.audit-original-value').first().text().trim();

            if (!originalText) {
                originalText = answerBox.clone()
                    .find('.audit-edit-card, .btn, button')
                    .remove()
                    .end()
                    .text();

                originalText = cleanAuditText(originalText);
            }

            let displayValue = getCurrentEditValue(input, value);

            let userName = response.user_name || response.editor_name || response.updated_by || '{{ auth()->user()->name ?? "المستخدم الحالي" }}';
            let updatedAt = response.updated_at || response.time || new Date().toLocaleString('ar');
            let history = response.history || [{
                value: displayValue,
                user_name: userName,
                updated_at: updatedAt
            }];

            answerBox.html(renderEditCard(originalText, displayValue, userName, updatedAt, type, globalid, field, history));

            item.removeClass('is-missing').addClass('has-answer is-edited');
        }
        function showEditHistory(type, globalid, field) {
            let collapseId = editHistoryCollapseId(type, globalid, field);
            let target = $('#' + collapseId);

            if (!target.length || target.data('loaded')) {
                return;
            }

            target.html('<div class="text-muted small">جاري التحميل...</div>');

            $.get("{{ route('assessment.inline.history') }}", {
                type: type,
                globalid: globalid,
                field: field
            }, function (response) {
                target.html(renderEditHistoryItems(response.history || []));
                target.data('loaded', true);
            }).fail(function () {
                target.html('<div class="text-danger small">فشل تحميل سجل التعديلات.</div>');
            });
        }

        document.body.setAttribute('data-kt-app-sidebar-minimize', 'on');
    </script>
@endsection
