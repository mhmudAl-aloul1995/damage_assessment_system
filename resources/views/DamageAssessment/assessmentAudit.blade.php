@extends('layouts.app')
@section('title', 'الإستبيان')
@section('pageName', 'الإستبيان')

@php
    $buildingCurrentStatus = $buildingCurrentStatus ?? null;
@endphp

@section('content')
<style>
    .building-status-btn,
    .housing-status-btn {
        transition: all 0.2s ease-in-out;
    }

    .building-status-btn:hover,
    .housing-status-btn:hover {
        transform: translateY(-1px);
    }

    .building-status-btn.is-active,
    .housing-status-btn.is-active {
        transform: translateY(-1px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.12);
    }

    .building-status-btn.btn-light-danger.is-active,
    .housing-status-btn.btn-light-danger.is-active {
        background-color: var(--bs-danger) !important;
        border-color: var(--bs-danger) !important;
        color: #fff !important;
    }

    .building-status-btn.btn-light-success.is-active,
    .housing-status-btn.btn-light-success.is-active {
        background-color: var(--bs-success) !important;
        border-color: var(--bs-success) !important;
        color: #fff !important;
    }

    .building-status-btn.btn-light-warning.is-active,
    .housing-status-btn.btn-light-warning.is-active {
        background-color: var(--bs-warning) !important;
        border-color: var(--bs-warning) !important;
        color: #fff !important;
    }

    .building-status-btn:disabled,
    .housing-status-btn:disabled {
        cursor: not-allowed;
        opacity: 0.8;
    }
</style>

<div class="card card-flush mb-7">
    <div class="card-header pt-7">
        <div class="card-title">
            <h2>الإستبيان</h2>
        </div>
    </div>

    <div class="card-body">
        <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold mb-8">
            <li onclick="reloadBuildingAssessmentTable()" class="nav-item">
                <a class="nav-link text-active-primary active" data-bs-toggle="tab" href="#tab_building">
                    المبنى
                </a>
            </li>

            <li onclick="reloadHousingTabTables()" class="nav-item">
                <a class="nav-link text-active-primary" data-bs-toggle="tab" href="#tab_housing">
                    الوحدة السكنية
                </a>
            </li>
        </ul>

        <div class="tab-content">

            {{-- تبويب المبنى --}}
            <div class="tab-pane fade show active" id="tab_building" role="tabpanel">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card card-flush shadow-sm border-0">
                            <div class="card-header border-0 pt-6 pb-4">
                                <div class="card-title">
                                    <div class="d-flex align-items-center position-relative my-1">
                                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <input
                                            type="text"
                                            data-kt-buildingAssessment-table-filter="search"
                                            class="form-control form-control-solid w-250px ps-13"
                                            placeholder="بحث" />
                                    </div>
                                </div>

                                <div class="card-toolbar">
                                    <div class="d-flex justify-content-end align-items-center gap-2 flex-wrap" data-kt-Building-table-toolbar="base">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-light-danger building-status-btn"
                                            data-status="rejected"
                                            onclick="setBuildingStatus('rejected')">
                                            مرفوض
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-light-success building-status-btn"
                                            data-status="accepted"
                                            onclick="setBuildingStatus('accepted')">
                                            مقبول
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-light-warning building-status-btn"
                                            data-status="need_review"
                                            onclick="setBuildingStatus('need_review')">
                                            بحاجة لمراجعة
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-light-dark"
                                            onclick="openNotesModal('building')">
                                            ملاحظات
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-light-primary ms-3"
                                            onclick="reloadBuildingAssessmentTable()">
                                            <i class="ki-duotone ki-arrows-circle fs-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            تحديث
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body pt-0 pb-4">
                                <div class="table-responsive">
                                    <table class="table align-middle table-row-bordered table-rounded gs-7 gy-4"
                                        id="kt_table_building_assessment">
                                        <thead>
                                            <tr class="fw-bold fs-7 text-gray-800 border-bottom border-gray-300">
                                                <th class="px-6 py-4 min-w-300px">السؤال</th>
                                                <th class="text-center px-6 py-4 min-w-250px">الجواب</th>
                                                <th class="text-center px-6 py-4 min-w-300px">تعديل الإجابة</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-gray-600 fw-semibold"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- تبويب الوحدة السكنية --}}
            <div class="tab-pane fade" id="tab_housing" role="tabpanel">

                {{-- جدول الوحدات --}}
                <div class="card card-flush mb-7 shadow-sm border-0">
                    <div class="card-header pt-6 pb-4 border-0">
                        <div class="card-title">
                            <h3 class="fw-bold mb-0">وحدات المبنى</h3>
                        </div>

                        <div class="card-toolbar">
                            <button
                                type="button"
                                class="btn btn-sm btn-light-primary"
                                onclick="reloadBuildingUnitsTable()">
                                <i class="ki-duotone ki-arrows-circle fs-6">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                تحديث
                            </button>
                        </div>
                    </div>

                    <div class="card-body pt-0 pb-4">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-bordered table-rounded gs-7 gy-4"
                                id="housing_table">
                                <thead>
                                    <tr class="fw-bold fs-7 text-gray-800 border-bottom border-gray-300">
                                        <th class="px-6 py-4">نوع الوحدة</th>
                                        <th class="px-6 py-4">حالة الضرر</th>
                                        <th class="px-6 py-4">رقم الطابق</th>
                                        <th class="px-6 py-4">رقم الوحدة</th>
                                        <th class="px-6 py-4 min-w-280px">اسم المالك</th>
                                        <th class="px-6 py-4">اتجاه الوحدة</th>
                                        <th class="px-6 py-4">التدقيق القانوني</th>
                                        <th class="px-6 py-4">التدقيق الهندسي</th>
                                        <th class="px-6 py-4">الاعتماد النهائي</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- جدول تقييم الوحدة --}}
                <div class="card card-flush shadow-sm border-0">
                    <div class="card-header border-0 pt-6 pb-4">
                        <div class="card-title">
                            <div class="d-flex align-items-center position-relative my-1">
                                <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <input
                                    type="text"
                                    data-kt-HousingAssessment-table-filter="search"
                                    class="form-control form-control-solid w-250px ps-13"
                                    placeholder="بحث" />
                            </div>
                        </div>

                        <div class="card-toolbar">
                            <div class="d-flex align-items-center flex-wrap fw-bold gap-2">
                                <div class="d-flex justify-content-end me-3" data-kt-HousingAssessment-table-toolbar="base">
                                    <select
                                        name="globalid"
                                        data-kt-globalid-table-filter="search"
                                        class="form-select form-select-solid text-gray-800 fs-base fw-bold w-250px"
                                        data-control="select2"
                                        data-allow-clear="true"
                                        data-dropdown-css-class="w-250px"
                                        data-placeholder="إختر الوحدة">
                                        <option value=""></option>
                                        @foreach ($HousingUnit as $value)
                                        <option value="{{ $value->globalid }}">
                                            {{ $value->objectid . '--' . $value->full_name }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-light-danger housing-status-btn"
                                    data-status="rejected"
                                    onclick="setHousingStatus('rejected')">
                                    مرفوض
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-light-success housing-status-btn"
                                    data-status="accepted"
                                    onclick="setHousingStatus('accepted')">
                                    مقبول
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-light-warning housing-status-btn"
                                    data-status="need_review"
                                    onclick="setHousingStatus('need_review')">
                                    بحاجة لمراجعة
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-light-dark"
                                    onclick="openNotesModal('housing')">
                                    ملاحظات
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-light-primary me-3"
                                    onclick="reloadHousingAssessmentTable()">
                                    <i class="ki-duotone ki-arrows-circle fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    تحديث
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body pt-0 pb-4">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-bordered table-rounded gs-7 gy-4"
                                id="kt_table_housing_assessment">
                                <thead>
                                    <tr class="fw-bold fs-7 text-gray-800 border-bottom border-gray-300">
                                        <th class="px-6 py-4 min-w-300px">السؤال</th>
                                        <th class="text-center px-6 py-4 min-w-250px">الجواب</th>
                                        <th class="text-center px-6 py-4 min-w-300px">تعديل الإجابة</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-600 fw-semibold"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

{{-- Modal الملاحظات --}}
<div class="modal fade" id="notesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="fw-bold" id="notesModalTitle">إضافة ملاحظة</h3>

                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>

            <div class="modal-body">
                <textarea
                    id="notesInput"
                    class="form-control form-control-solid"
                    rows="5"
                    placeholder="اكتب الملاحظة هنا..."></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    إلغاء
                </button>

                <button type="button" class="btn btn-primary" onclick="submitStatusWithNotes()">
                    حفظ
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
<script>
    let notesContext = null;   // building | housing
    let pendingStatus = null;  // accepted | rejected | need_review | null

    function initInlineEditors() {
        $('.inline-edit-select').each(function() {
            if (!$(this).hasClass('select2-hidden-accessible')) {
                $(this).select2({
                    minimumResultsForSearch: 0,
                    width: '100%',
                    dir: 'rtl'
                });
            }
        });
    }

    function normalizeStatus(statusName) {
        if (!statusName) return null;

        if (statusName.includes('accepted')) return 'accepted';
        if (statusName.includes('rejected')) return 'rejected';
        if (statusName === 'need_review') return 'need_review';

        return null;
    }

    function setActiveStatusButton(selector, status) {
        $(selector)
            .removeClass('is-active')
            .prop('disabled', false);

        if (!status) return;

        let activeBtn = $(selector + '[data-status="' + status + '"]');

        activeBtn
            .addClass('is-active')
            .prop('disabled', true);
    }

    function openNotesModal(type, status = null) {
        notesContext = type;
        pendingStatus = status;

        $('#notesInput').val('');

        $('#notesModalTitle').text(
            type === 'building' ? 'إضافة ملاحظة للمبنى' : 'إضافة ملاحظة للوحدة'
        );

        const modalEl = document.getElementById('notesModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    function closeNotesModal() {
        const modalEl = document.getElementById('notesModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.hide();
    }

    function saveInlineValue(field, globalid, type, value, callback = null) {
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
            success: function(response) {
                if (typeof toastr !== 'undefined') {
                    toastr.success(response.message || 'تم الحفظ بنجاح');
                }

                if (callback) {
                    callback(true);
                }

                if (type === 'building_table') {
                    reloadBuildingAssessmentTable();
                } else if (type === 'housing_table') {
                    reloadHousingAssessmentTable();
                }
            },
            error: function(xhr) {
                let message = 'حدث خطأ أثناء الحفظ';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                if (typeof toastr !== 'undefined') {
                    toastr.error(message);
                } else {
                    alert(message);
                }

                if (callback) {
                    callback(false);
                }
            }
        });
    }

    function setBuildingStatus(status) {
        let globalid = '{{ $globalid }}';

        if (!globalid) {
            toastr.warning('لا يوجد مبنى محدد');
            return;
        }

        openNotesModal('building', status);
    }

    function setHousingStatus(status) {
        let globalid = $("[name='globalid']").val();

        if (!globalid) {
            toastr.warning('يرجى اختيار الوحدة أولاً');
            return;
        }

        openNotesModal('housing', status);
    }

    function submitStatusWithNotes() {
        let notes = $('#notesInput').val();

        if (notesContext === 'building' && !pendingStatus) {
            toastr.warning('اختر حالة أولاً');
            return;
        }

        if (notesContext === 'housing' && !pendingStatus) {
            toastr.warning('اختر حالة أولاً');
            return;
        }

        if (notesContext === 'building') {
            let globalid = '{{ $globalid }}';

            if (!globalid) {
                toastr.warning('لا يوجد مبنى محدد');
                return;
            }

            $.ajax({
                url: "{{ route('building.assessment.set.status') }}",
                method: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    globalid: globalid,
                    status: pendingStatus,
                    notes: notes
                },
                beforeSend: function() {
                    $('.building-status-btn').prop('disabled', true);
                },
                success: function(response) {
                    toastr.success(response.message || 'تم تحديث حالة المبنى');
                    setActiveStatusButton('.building-status-btn', pendingStatus);
                    reloadBuildingAssessmentTable();
                    reloadBuildingUnitsTable();
                    closeNotesModal();
                },
                error: function(xhr) {
                    let message = 'حدث خطأ أثناء تحديث الحالة';

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }

                    toastr.error(message);
                },
                complete: function() {
                    $('.building-status-btn').not('.is-active').prop('disabled', false);
                }
            });
        }

        if (notesContext === 'housing') {
            let globalid = $("[name='globalid']").val();

            if (!globalid) {
                toastr.warning('يرجى اختيار الوحدة أولاً');
                return;
            }

            $.ajax({
                url: "{{ route('housing.assessment.set.status') }}",
                method: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    globalid: globalid,
                    status: pendingStatus,
                    notes: notes
                },
                beforeSend: function() {
                    $('.housing-status-btn').prop('disabled', true);
                },
                success: function(response) {
                    toastr.success(response.message || 'تم تحديث الحالة بنجاح');
                    setActiveStatusButton('.housing-status-btn', pendingStatus);
                    reloadHousingAssessmentTable();
                    reloadBuildingUnitsTable();
                    closeNotesModal();
                },
                error: function(xhr) {
                    let message = 'حدث خطأ أثناء تحديث الحالة';

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }

                    toastr.error(message);
                },
                complete: function() {
                    $('.housing-status-btn').not('.is-active').prop('disabled', false);
                }
            });
        }
    }

    $(document).on('click', '.inline-save-btn', function() {
        let btn = $(this);
        let wrapper = btn.closest('.d-flex');
        let input = wrapper.find('.inline-edit-input');

        let field = btn.data('field');
        let globalid = btn.data('globalid');
        let type = btn.data('type');
        let value = input.val();

        btn.prop('disabled', true).html('...');

        saveInlineValue(field, globalid, type, value, function() {
            btn.prop('disabled', false).html('حفظ');
        });
    });

    $(document).on('change', '.inline-edit-select', function() {
        let select = $(this);

        let field = select.data('field');
        let globalid = select.data('globalid');
        let type = select.data('type');
        let value = select.val();

        saveInlineValue(field, globalid, type, value);
    });

    function reloadBuildingAssessmentTable() {
        if ($.fn.DataTable.isDataTable('#kt_table_building_assessment')) {
            $('#kt_table_building_assessment').DataTable().ajax.reload(null, false);
        }
    }

    function reloadHousingAssessmentTable() {
        if ($.fn.DataTable.isDataTable('#kt_table_housing_assessment')) {
            $('#kt_table_housing_assessment').DataTable().ajax.reload(null, false);
        }
    }

    function reloadBuildingUnitsTable() {
        if ($.fn.DataTable.isDataTable('#housing_table')) {
            $('#housing_table').DataTable().ajax.reload(null, false);
        }
    }

    function reloadHousingTabTables() {
        reloadBuildingUnitsTable();
        reloadHousingAssessmentTable();
    }

    var KTBuildingAssessmentList = function() {
        var table = document.getElementById('kt_table_building_assessment');
        var datatable;

        var initEngineerTable = function() {
            datatable = $(table).DataTable({
                serverSide: true,
                ajax: {
                    url: "{{ url('showBuildings') }}",
                    data: function(d) {
                        d.globalid = '{{ $globalid }}';
                    },
                },
                info: false,
                order: [],
                pageLength: 200,
                processing: true,
                columns: [{
                        className: 'text-start px-6 py-4 min-w-300px',
                        data: 'question',
                        name: 'question',
                        searchable: false,
                        orderable: false
                    },
                    {
                        className: 'text-center px-6 py-4 min-w-250px',
                        data: 'answer',
                        name: 'answer',
                        searchable: false,
                        orderable: false
                    },
                    {
                        className: 'text-center px-6 py-4 min-w-300px',
                        data: 'editAnswer',
                        name: 'editAnswer',
                        searchable: false,
                        orderable: false
                    },
                ],
                createdRow: function(row, data) {
                    $(row).css('cursor', 'default');
                    var text = $('<div>').html(data.answer).text().trim();

                    if (text !== '' && text !== '-') {
                        $(row).css('background-color', '#d4edda');
                    }
                }
            });

            datatable.on('draw', function() {
                if (typeof KTMenu !== 'undefined') {
                    KTMenu.createInstances();
                }
                initInlineEditors();
            });
        };

        var handleSearchDatatable = function() {
            const filterSearch = document.querySelector('[data-kt-buildingAssessment-table-filter="search"]');

            if (!filterSearch) return;

            filterSearch.addEventListener('keydown', function(e) {
                if (e.which == 13) {
                    e.preventDefault();
                    datatable.search(e.target.value).draw();
                }
            });
        };

        return {
            init: function() {
                if (!table) return;
                initEngineerTable();
                handleSearchDatatable();
            }
        };
    }();

    var KTBuildingUnitsList = function() {
        var table = document.getElementById('housing_table');
        var datatable;

        var initTable = function() {
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
                    data: function(d) {
                        d.globalid = '{{ $globalid }}';
                    }
                },
                columns: [{
                        data: 'housing_unit_type',
                        name: 'housing_unit_type',
                        className: 'text-start px-6 py-4'
                    },
                    {
                        data: 'unit_damage_status',
                        name: 'unit_damage_status',
                        className: 'text-center px-6 py-4'
                    },
                    {
                        data: 'floor_number',
                        name: 'floor_number',
                        className: 'text-center px-6 py-4'
                    },
                    {
                        data: 'housing_unit_number',
                        name: 'housing_unit_number',
                        className: 'text-center px-6 py-4'
                    },
                    {
                        data: 'owner_name',
                        name: 'owner_name',
                        className: 'text-start px-6 py-4 min-w-280px'
                    },
                    {
                        data: 'unit_direction',
                        name: 'unit_direction',
                        className: 'text-center px-6 py-4'
                    },
                    {
                        data: 'legal_audit_status',
                        name: 'legal_audit_status',
                        className: 'text-center px-6 py-4'
                    },
                    {
                        data: 'engineering_audit_status',
                        name: 'engineering_audit_status',
                        className: 'text-center px-6 py-4'
                    },
                    {
                        data: 'final_approval_status',
                        name: 'final_approval_status',
                        className: 'text-center px-6 py-4'
                    }
                ],
                createdRow: function(row) {
                    $(row).css('cursor', 'pointer');
                }
            });

            datatable.on('draw', function() {
                if (typeof KTMenu !== 'undefined') {
                    KTMenu.createInstances();
                }
            });
        };

        return {
            init: function() {
                if (!table) return;
                initTable();
            }
        };
    }();

    var KTHousingAssessmentList = function() {
        var table = document.getElementById('kt_table_housing_assessment');
        var datatable;

        var initHousingTable = function() {
            datatable = $(table).DataTable({
                serverSide: true,
                ajax: {
                    url: "{{ url('showHousings') }}",
                    data: function(d) {
                        d.parentglobalid = '{{ $globalid }}';
                        d.globalid = $("[name='globalid']").val();
                    },
                },
                info: false,
                order: [],
                pageLength: 500,
                processing: true,
                columns: [{
                        className: 'text-start px-6 py-4 min-w-300px',
                        data: 'question',
                        name: 'question',
                        searchable: false,
                        orderable: false
                    },
                    {
                        className: 'text-center px-6 py-4 min-w-250px',
                        data: 'answer',
                        name: 'answer',
                        searchable: false,
                        orderable: false
                    },
                    {
                        className: 'text-center px-6 py-4 min-w-300px',
                        data: 'editAnswer',
                        name: 'editAnswer',
                        searchable: false,
                        orderable: false
                    },
                ],
                createdRow: function(row, data) {
                    $(row).css('cursor', 'default');
                    var text = $('<div>').html(data.answer).text().trim();

                    if (text !== '' && text !== '-') {
                        $(row).css('background-color', '#d4edda');
                    }
                }
            });

            datatable.on('draw', function() {
                if (typeof KTMenu !== 'undefined') {
                    KTMenu.createInstances();
                }
                initInlineEditors();
            });
        };

        var handleSearchDatatable = function() {
            const filterSearch = document.querySelector('[data-kt-HousingAssessment-table-filter="search"]');

            if (!filterSearch) return;

            filterSearch.addEventListener('keydown', function(e) {
                if (e.which == 13) {
                    e.preventDefault();
                    datatable.search(e.target.value).draw();
                }
            });
        };

        var handleChangeHousingUnit = function() {
            const filterSearch = $('[name="globalid"]');

            filterSearch.on("change", function() {
                datatable.ajax.reload();
            });
        };

        return {
            init: function() {
                if (!table) return;
                initHousingTable();
                handleSearchDatatable();
                handleChangeHousingUnit();
            }
        };
    }();

    KTUtil.onDOMContentLoaded(function() {
        KTBuildingAssessmentList.init();
        KTBuildingUnitsList.init();
        KTHousingAssessmentList.init();
        initInlineEditors();

        setActiveStatusButton(
            '.building-status-btn',
            normalizeStatus(@json($buildingCurrentStatus))
        );

        $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function() {
            $.fn.dataTable.tables({
                visible: true,
                api: true
            }).columns.adjust();

            initInlineEditors();
        });

        $('#housing_table tbody').on('click', 'tr', function() {
            let table = $('#housing_table').DataTable();
            let data = table.row(this).data();
alert(4)
            if (!data) return;

            $('#housing_table tbody tr').removeClass('selected');
            $(this).addClass('selected');

            if (!data.globalid) return;

            $('[name="globalid"]').val(data.globalid).trigger('change');

            if (data.current_status) {
                setActiveStatusButton('.housing-status-btn', normalizeStatus(data.current_status));
            }

            reloadHousingAssessmentTable();
        });
    });
</script>
@endsection