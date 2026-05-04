@extends('layouts.app')

@section('title', 'تدقيق المباني العامة')
@section('pageName', 'تدقيق المباني العامة')

@section('content')
    <div class="card card-flush">
        <div class="card-header pt-6">
            <div class="card-title">
                <h2 class="fw-bold mb-0">تدقيق المباني العامة</h2>
            </div>
            <div class="card-toolbar d-flex gap-2 flex-wrap">
                @role('Database Officer|Team Leader -INF')
                    <select id="bulk_assign_engineer" class="form-select form-select-solid w-250px" data-placeholder="اختر المدقق">
                        <option value=""></option>
                        @foreach ($engineers as $engineer)
                            <option value="{{ $engineer->id }}">{{ $engineer->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" id="bulk_assign_btn" class="btn btn-light-info">إسناد المحدد</button>
                @endrole
                <button class="btn btn-light-primary" onclick="$('#inf_public_buildings_table').DataTable().ajax.reload(null, false)">تحديث</button>
            </div>
        </div>

        <div class="card-body">
            <div class="row g-3 mb-6">
                <div class="col-md-2">
                    <input id="filter_objectid" type="text" class="form-control form-control-solid audit-filter" placeholder="ObjectID">
                </div>
                <div class="col-md-2">
                    <select id="filter_municipalitie" class="form-select form-select-solid audit-filter audit-select" data-placeholder="البلدية">
                        <option value="">كل البلديات</option>
                        @foreach ($municipalities as $municipality)
                            <option value="{{ $municipality }}">{{ $municipality }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="filter_neighborhood" class="form-select form-select-solid audit-filter audit-select" data-placeholder="الحي">
                        <option value="">كل الأحياء</option>
                        @foreach ($neighborhoods as $neighborhood)
                            <option value="{{ $neighborhood }}">{{ $neighborhood }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="filter_status" class="form-select form-select-solid audit-filter audit-select" data-placeholder="الحالة">
                        <option value="">كل الحالات</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->name }}">{{ $status->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="filter_auditor" class="form-select form-select-solid audit-filter audit-select" data-placeholder="المدقق">
                        <option value="">كل المدققين</option>
                        @foreach ($engineers as $engineer)
                            <option value="{{ $engineer->id }}">{{ $engineer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input id="filter_from_date" type="date" class="form-control form-control-solid audit-filter">
                </div>
                <div class="col-md-2">
                    <input id="filter_to_date" type="date" class="form-control form-control-solid audit-filter">
                </div>
            </div>

            <div class="table-responsive">
                <table id="inf_public_buildings_table" class="table table-row-bordered align-middle gy-4">
                    <thead>
                        <tr class="fw-bold text-gray-800">
                            <th class="w-40px">
                                <input type="checkbox" id="inf_audit_select_all" class="form-check-input">
                            </th>
                            <th>ObjectID</th>
                            <th>اسم المبنى</th>
                            <th>البلدية</th>
                            <th>الحي</th>
                            <th>الحالة</th>
                            <th>المدقق</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $(function () {
            $('.audit-select').select2({ width: '100%', allowClear: true });

            const table = $('#inf_public_buildings_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: @json(route('inf-audit.public-buildings.data')),
                    data: function (d) {
                        d.objectid = $('#filter_objectid').val();
                        d.municipalitie = $('#filter_municipalitie').val();
                        d.neighborhood = $('#filter_neighborhood').val();
                        d.status = $('#filter_status').val();
                        d.auditor = $('#filter_auditor').val();
                        d.from_date = $('#filter_from_date').val();
                        d.to_date = $('#filter_to_date').val();
                    }
                },
                columns: [
                    { data: 'selection', name: 'selection', orderable: false, searchable: false },
                    { data: 'objectid', name: 'objectid' },
                    { data: 'building_name', name: 'building_name', defaultContent: '-' },
                    { data: 'municipalitie', name: 'municipalitie', defaultContent: '-' },
                    { data: 'neighborhood', name: 'neighborhood', defaultContent: '-' },
                    { data: 'audit_status', name: 'audit_status', orderable: false, searchable: false },
                    { data: 'auditor', name: 'auditor', orderable: false, searchable: false },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false },
                ],
                order: [[0, 'desc']]
            });

            $('.audit-filter').on('change', function () {
                table.ajax.reload();
            });

            $('#filter_objectid').on('input', function () {
                table.ajax.reload();
            });

            $('#bulk_assign_engineer').select2({ width: '250px', allowClear: true });

            $('#inf_audit_select_all').on('change', function () {
                $('.inf-audit-row-check').prop('checked', $(this).is(':checked'));
            });

            $('#inf_public_buildings_table').on('draw.dt', function () {
                $('#inf_audit_select_all').prop('checked', false);
            });

            $('#bulk_assign_btn').on('click', function () {
                const ids = $('.inf-audit-row-check:checked').map(function () {
                    return $(this).val();
                }).get();

                if (ids.length === 0) {
                    toastr.warning('يرجى اختيار سجل واحد على الأقل');
                    return;
                }

                if (!$('#bulk_assign_engineer').val()) {
                    toastr.warning('يرجى اختيار المدقق');
                    return;
                }

                $.post(@json(route('inf-audit.public-buildings.assign')), {
                    _token: @json(csrf_token()),
                    ids: ids,
                    assigned_to: $('#bulk_assign_engineer').val()
                }).done(function (response) {
                    toastr.success(response.message || 'تم الإسناد بنجاح');
                    table.ajax.reload(null, false);
                }).fail(function (xhr) {
                    toastr.error(xhr.responseJSON?.message || 'حدث خطأ أثناء الإسناد');
                });
            });
        });
    </script>
@endsection
