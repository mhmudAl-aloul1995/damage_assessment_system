@extends('layouts.app')

@section('title', 'تقرير إحصائيات الحصر')
@section('pageName', 'تقرير إحصائيات الحصر')

@section('content')
<div class="app-content flex-column-fluid">
    <div class="app-container container-fluid">

        <div class="card mb-5">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h3 class="fw-bold mb-0">
                        تقرير إحصائيات حصر أضرار المباني والوحدات السكنية
                    </h3>
                </div>

                <div class="card-toolbar">
                    <button type="button" id="btn_export" class="btn btn-success">
                        <i class="ki-duotone ki-exit-up fs-2"></i>
                        تصدير Excel
                    </button>
                </div>
            </div>

            <div class="card-body pt-0">

                <form id="filter_form" class="row g-4 mb-6">

                    <div class="col-md-3">
                        <label class="form-label">من تاريخ التعديل</label>
                        <input type="text"
                               name="from_date"
                               id="from_date"
                               class="form-control form-control-solid date-input"
                               placeholder="YYYY-MM-DD">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">إلى تاريخ التعديل</label>
                        <input type="text"
                               name="to_date"
                               id="to_date"
                               class="form-control form-control-solid date-input"
                               placeholder="YYYY-MM-DD">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">البلدية</label>
                        <select name="municipalitie"
                                id="municipalitie"
                                class="form-select form-select-solid"
                                data-control="select2"
                                data-placeholder="الكل">
                            <option value="">الكل</option>
                            @foreach($municipalities as $municipalitie)
                                <option value="{{ $municipalitie }}">{{ $municipalitie }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">الحي</label>
                        <select name="neighborhood"
                                id="neighborhood"
                                class="form-select form-select-solid"
                                data-control="select2"
                                data-placeholder="الكل">
                            <option value="">الكل</option>
                            @foreach($neighborhoods as $neighborhood)
                                <option value="{{ $neighborhood }}">{{ $neighborhood }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">المهندس الميداني</label>
                        <select name="assignedto"
                                id="assignedto"
                                class="form-select form-select-solid"
                                data-control="select2"
                                data-placeholder="الكل">
                            <option value="">الكل</option>
                            @foreach($fieldEngineers as $engineer)
                                <option value="{{ $engineer }}">{{ $engineer }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">حالة الضرر</label>
                        <select name="building_damage_status"
                                id="building_damage_status"
                                class="form-select form-select-solid"
                                data-control="select2"
                                data-placeholder="الكل">
                            <option value="">الكل</option>
                            @foreach($damageStatuses as $status)
                                <option value="{{ $status }}">{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <button type="button" id="btn_filter" class="btn btn-primary">
                            فلترة
                        </button>

                        <button type="button" id="btn_reset" class="btn btn-light">
                            إعادة تعيين
                        </button>
                    </div>

                </form>

                <div class="alert alert-info">
                    التقرير يعتمد على <b>buildings.editdate</b> فقط.
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-row-dashed align-middle text-center" id="damage_statistics_table">
                        <thead>
                            <tr class="fw-bold text-gray-800 bg-light">
                                <th style="width:70px;">م</th>
                                <th>الوصف</th>
                                <th style="width:160px;">العدد</th>
                                <th style="width:180px;">ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" class="py-10 text-muted">جاري تحميل البيانات...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>
</div>
@endsection

@push('script')
<script>
$(document).ready(function () {
    $('.date-input').flatpickr({
        dateFormat: 'Y-m-d',
        allowInput: true
    });

    $('[data-control="select2"]').select2({
        width: '100%'
    });

    function filters() {
        return $('#filter_form').serialize();
    }

    function loadReport() {
        $.ajax({
            url: "{{ route('reports.damage-statistics.data') }}",
            type: "GET",
            data: filters(),
            beforeSend: function () {
                $('#damage_statistics_table tbody').html(`
                    <tr>
                        <td colspan="4" class="py-10 text-muted">
                            جاري تحميل البيانات...
                        </td>
                    </tr>
                `);
            },
            success: function (response) {
                let html = '';

                if (!response.data || response.data.length === 0) {
                    html = `
                        <tr>
                            <td colspan="4" class="py-10 text-muted">
                                لا توجد بيانات
                            </td>
                        </tr>
                    `;
                } else {
                    response.data.forEach(function (row) {
                        if (row.is_section) {
                            html += `
                                <tr class="bg-primary text-white fw-bold">
                                    <td></td>
                                    <td colspan="3" class="text-center">${row.description}</td>
                                </tr>
                            `;
                        } else {
                            html += `
                                <tr>
                                    <td>${row.no ?? ''}</td>
                                    <td class="text-end">${row.description ?? ''}</td>
                                    <td class="fw-bold">${row.count ?? 0}</td>
                                    <td>${row.notes ?? ''}</td>
                                </tr>
                            `;
                        }
                    });
                }

                $('#damage_statistics_table tbody').html(html);
            },
            error: function (xhr) {
                $('#damage_statistics_table tbody').html(`
                    <tr>
                        <td colspan="4" class="py-10 text-danger">
                            حدث خطأ أثناء تحميل التقرير
                        </td>
                    </tr>
                `);

                console.log(xhr.responseText);
            }
        });
    }

    $('#btn_filter').on('click', function () {
        loadReport();
    });

    $('#btn_reset').on('click', function () {
        $('#filter_form')[0].reset();

        $('#municipalitie').val('').trigger('change');
        $('#neighborhood').val('').trigger('change');
        $('#assignedto').val('').trigger('change');
        $('#building_damage_status').val('').trigger('change');

        loadReport();
    });

    $('#btn_export').on('click', function () {
        window.location.href = "{{ route('reports.damage-statistics.export') }}" + '?' + filters();
    });

    loadReport();
});
</script>
@endpush