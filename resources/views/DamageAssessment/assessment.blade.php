@extends('layouts.app')
@section('title', 'الإستبيان')
@section('pageName', 'الإستبيان')

@section('content')

<div class="card card-flush mb-7">
    <div class="card-header pt-7">
        <div class="card-title">
            <h2>{{ $buildingTitle }}</h2>
        </div>
        <div class="card-toolbar">
            <a href="{{ route('assessment.pdf', $globalid) }}" target="_blank" class="btn btn-sm btn-primary">
                <i class="ki-duotone ki-file-down fs-4 me-1"></i>
                PDF
            </a>
        </div>
    </div>

    <div class="card-body">
        <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold mb-8">
            <li onclick="$('#kt_table_building_assessment').DataTable().ajax.reload()" class="nav-item">
                <a  class="nav-link text-active-primary active" data-bs-toggle="tab" href="#tab_building">
                    المبنى
                </a>
            </li>
            <li onclick="$('#kt_table_housing_assessment').DataTable().ajax.reload()" class="nav-item">
                <a class="nav-link text-active-primary" data-bs-toggle="tab" href="#tab_housing">
                    الوحدة السكنية
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab_building" role="tabpanel">
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <div class="d-flex align-items-center position-relative my-1">
                                <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <input
                                    type="text"
                                    data-kt-buildingAssessment-table-filter="search"
                                    class="form-control form-control-solid w-200px ps-13"
                                    placeholder="بحث" />
                            </div>
                        </div>

                        <div class="card-toolbar">
                            <div class="d-flex justify-content-end" data-kt-Building-table-toolbar="base">
                            <button
                                    type="button"
                                    class="btn btn-sm btn-light-primary me-3"
                                    onclick="$('#kt_table_building_assessment').DataTable().ajax.reload()">
                                    <i class="ki-duotone ki-arrows-circle fs-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    تحديث
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body py-4">
                        <table class="table text-black-600 table-rounded table-striped align-middle table-row-dashed fs-6 gy-5"
                               id="kt_table_building_assessment">
                            <thead>
                                <tr class="text-start text-black-600 fw-bold border-bottom border-gray-200 fs-7 text-uppercase gs-0">
                                    <th class="text-start min-w-250px">السؤال</th>
                                    <th class="text-center min-w-200px">الجواب</th>
                                </tr>
                            </thead>
                            <tbody class=" fw-semibold"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab_housing" role="tabpanel">
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <div class="d-flex align-items-center position-relative my-1">
                                <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <input
                                    type="text"
                                    data-kt-HousingAssessment-table-filter="search"
                                    class="form-control form-control-solid  ps-13"
                                    placeholder="بحث" />
                            </div>
                        </div>

                        <div class="card-toolbar">
                            <div class="d-flex align-items-center fw-bold">
                                <div class="d-flex justify-content-end me-3" data-kt-HousingAssessment-table-toolbar="base">
                                    <select
                                        name="globalid"
                                        data-kt-globalid-table-filter="search"
                                        class="form-select form-select-transparent text-gray-800 fs-base lh-1 fw-bold py-0 ps-3 w-auto"
                                        data-control="select2"
                                        data-allow-clear="true"
                                        data-dropdown-css-class="w-200px"
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
                                    class="btn btn-sm btn-light-primary me-3"
                                    onclick="$('#kt_table_housing_assessment').DataTable().ajax.reload()">
                                    <i class="ki-duotone ki-arrows-circle fs-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    تحديث
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body py-4">
                        <table  class="table text-black-600 table-rounded table-striped align-middle table-row-dashed fs-6 gy-5"
                               id="kt_table_housing_assessment">
                            <thead>
                                <tr class="text-start text-black-600 fw-bold border-bottom border-gray-200 fs-7 text-uppercase gs-0">
                                    <th class="text-start min-w-250px">السؤال</th>
                                    <th class="text-center min-w-200px">الجواب</th>
                                </tr>
                            </thead>
                            <tbody class=" fw-semibold"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="assessmentEditHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="fw-bold">سجل تعديلات الحقل</h3>
                <div class="btn btn-icon btn-sm btn-active-light-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-row-bordered align-middle">
                        <thead>
                            <tr class="fw-bold text-muted">
                                <th>#</th>
                                <th>اسم الحقل</th>
                                <th>القيمة السابقة</th>
                                <th>القيمة الجديدة</th>
                                <th>عدّل بواسطة</th>
                                <th>تاريخ التعديل</th>
                                <th>المصدر</th>
                                <th>رقم طلب الإرجاع</th>
                            </tr>
                        </thead>
                        <tbody id="assessmentEditHistoryBody">
                            <tr>
                                <td colspan="8" class="text-center text-muted">جاري التحميل...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
<script>
    function escapeAssessmentHistoryHtml(value) {
        if (value === null || value === undefined || value === '') {
            return '-';
        }

        return $('<div>').text(value).html();
    }

    $(document).on('click', '.js-assessment-history', function () {
        const button = $(this);
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('assessmentEditHistoryModal'));

        $('#assessmentEditHistoryBody').html('<tr><td colspan="8" class="text-center text-muted">جاري التحميل...</td></tr>');
        modal.show();

        $.ajax({
            url: "{{ route('assessment-edit-histories.index') }}",
            method: "GET",
            data: {
                global_id: button.data('global-id'),
                type: button.data('type'),
                field_name: button.data('field-name')
            },
            success: function (response) {
                if (response && response.status === false) {
                    if (typeof toastr !== 'undefined') {
                        toastr.info(response.message || 'لا يوجد تغيير في القيمة.');
                    }

                    if (callback) {
                        callback(false);
                    }

                    return;
                }
                const rows = response.data || [];

                if (!rows.length) {
                    $('#assessmentEditHistoryBody').html('<tr><td colspan="8" class="text-center text-muted">لا يوجد سجل تعديلات.</td></tr>');
                    return;
                }

                let html = '';
                rows.forEach(function (item, index) {
                    html += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${escapeAssessmentHistoryHtml(item.label || item.field_name)}</td>
                            <td>${escapeAssessmentHistoryHtml(item.old_value)}</td>
                            <td>${escapeAssessmentHistoryHtml(item.new_value)}</td>
                            <td>${escapeAssessmentHistoryHtml(item.edited_by)}</td>
                            <td>${escapeAssessmentHistoryHtml(item.created_at)}</td>
                            <td>${escapeAssessmentHistoryHtml(item.source)}</td>
                            <td>${escapeAssessmentHistoryHtml(item.return_request_id)}</td>
                        </tr>
                    `;
                });

                $('#assessmentEditHistoryBody').html(html);
            },
            error: function () {
                $('#assessmentEditHistoryBody').html('<tr><td colspan="8" class="text-center text-danger">فشل تحميل السجل.</td></tr>');
            }
        });
    });

    function initInlineEditors() {
        $('.inline-edit-select').each(function () {
            if (!$(this).hasClass('select2-hidden-accessible')) {
                $(this).select2({
                    minimumResultsForSearch: 0,
                    width: '100%',
                    dir: 'rtl'
                });
            }
        });
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
            success: function (response) {
                if (typeof toastr !== 'undefined') {
                    toastr.success(response.message || 'تم الحفظ بنجاح');
                }

                if (callback) {
                    callback(true);
                }
            },
            error: function (xhr) {
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

    $(document).on('click', '.inline-save-btn', function () {
        let btn = $(this);
        let wrapper = btn.closest('.d-flex');
        let input = wrapper.find('.inline-edit-input');

        let field = btn.data('field');
        let globalid = btn.data('globalid');
        let type = btn.data('type');
        let value = input.val();

        btn.prop('disabled', true).html('...');

        saveInlineValue(field, globalid, type, value, function () {
            btn.prop('disabled', false).html('حفظ');
        });
    });

    $(document).on('change', '.inline-edit-select', function () {
        let select = $(this);

        let field = select.data('field');
        let globalid = select.data('globalid');
        let type = select.data('type');
        let value = select.val();

        saveInlineValue(field, globalid, type, value);
    });

    var KTBuildingAssessmentList = function () {
        var table = document.getElementById('kt_table_building_assessment');
        var datatable;

        var initEngineerTable = function () {
            datatable = $(table).DataTable({
                serverSide: true,
                ajax: {
                    url: "{{ url('showBuildings') }}",
                    data: function (d) {
                        d.globalid = '{{ $globalid }}';
                    },
                },
                info: false,
                order: [],
                pageLength: 200,
                processing: true,
                columns: [
                    { className: 'text-start', data: 'question', name: 'question', searchable: false, orderable: false },
                    { className: 'text-center', data: 'answer', name: 'answer', searchable: false, orderable: false },
                ],
                createdRow: (row) => {
                    $(row).css('cursor', 'default');
                }
            });

            datatable.on('draw', function () {
                if (typeof KTMenu !== 'undefined') {
                    KTMenu.createInstances();
                }
                initInlineEditors();
            });
        }

        var handleSearchDatatable = () => {
            const filterSearch = document.querySelector('[data-kt-buildingAssessment-table-filter="search"]');

            if (!filterSearch) return;

            filterSearch.addEventListener('keydown', function (e) {
                if (e.which == 13) {
                    e.preventDefault();
                    datatable.search(e.target.value).draw();
                }
            });
        }

        return {
            init: function () {
                if (!table) return;
                initEngineerTable();
                handleSearchDatatable();
            }
        }
    }();

    var KTHousingAssessmentList = function () {
        var table = document.getElementById('kt_table_housing_assessment');
        var datatable;

        var initHousingTable = function () {
            datatable = $(table).DataTable({
                serverSide: true,
                ajax: {
                    url: "{{ url('showHousings') }}",
                    data: function (d) {
                        d.parentglobalid = '{{ $globalid }}';
                        d.globalid = $("[name='globalid']").val();
                    },
                },
                info: false,
                order: [],
                pageLength: 400,
                processing: true,
                columns: [
                    { className: 'text-start', data: 'question', name: 'question', searchable: false, orderable: false },
                    { className: 'text-center', data: 'answer', name: 'answer', searchable: false, orderable: false },
                ],
                createdRow: (row) => {
                    $(row).css('cursor', 'default');
                }
            });

            datatable.on('draw', function () {
                if (typeof KTMenu !== 'undefined') {
                    KTMenu.createInstances();
                }
                initInlineEditors();
            });
        }

        var handleSearchDatatable = () => {
            const filterSearch = document.querySelector('[data-kt-HousingAssessment-table-filter="search"]');

            if (!filterSearch) return;

            filterSearch.addEventListener('keydown', function (e) {
                if (e.which == 13) {
                    e.preventDefault();
                    datatable.search(e.target.value).draw();
                }
            });
        }

        var handleChangeHousingUnit = () => {
            const filterSearch = $('[name="globalid"]');

            filterSearch.on("change", function () {
                datatable.ajax.reload();
            });
        }

        return {
            init: function () {
                if (!table) return;
                initHousingTable();
                handleSearchDatatable();
                handleChangeHousingUnit();
            }
        }
    }();

    KTUtil.onDOMContentLoaded(function () {
        KTBuildingAssessmentList.init();
        KTHousingAssessmentList.init();
        initInlineEditors();

        $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
            initInlineEditors();
        });
    });
</script>
@endsection
