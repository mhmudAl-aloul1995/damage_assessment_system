@extends('layouts.app')

@section('title', 'ربط المهندس الميداني بقائد الفريق')
@section('pageName', 'ربط المهندس الميداني بقائد الفريق')

@section('content')

@php
    $authUser = auth()->user();
    $isTeamLeader = $authUser?->hasAnyRole(['Team Leader', 'Team leader']);
@endphp

<div class="app-content flex-column-fluid" dir="rtl">
    <div class="app-container container-fluid">

        <div class="card mb-7">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="fw-bold">إضافة ربط جديد</h3>
                </div>
            </div>

            <div class="card-body">
                <form id="assignment_form">
                    @csrf

                    <div class="row g-5">
                        <div class="col-md-5">
                            <label class="form-label required">قائد الفريق</label>

                            <select id="team_leader_id"
                                    name="team_leader_id"
                                    class="form-select"
                                    @if($isTeamLeader) disabled @endif>

                                @if($isTeamLeader)
                                    <option value="{{ $authUser->id }}" selected>
                                        {{ $authUser->name }}
                                        @if($authUser->name_en)
                                            - {{ $authUser->name_en }}
                                        @endif
                                    </option>
                                @endif
                            </select>

                            @if($isTeamLeader)
                                <input type="hidden" name="team_leader_id" value="{{ $authUser->id }}">
                            @endif
                        </div>

                        <div class="col-md-5">
                            <label class="form-label required">المهندس الميداني</label>
                            <select id="field_engineer_id"
                                    name="field_engineer_id[]"
                                    class="form-select"
                                    multiple>
                            </select>
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                إضافة
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-7">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="fw-bold">الفلاتر</h3>
                </div>

                <div class="card-toolbar">
                    <button id="reset_filters" class="btn btn-light-danger me-2">إعادة تعيين</button>
                    <a id="export_btn" href="#" class="btn btn-light-success">تصدير Excel</a>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-5">
                    <div class="col-md-4">
                        <label class="form-label">المنطقة</label>
                        <select id="filter_region" class="form-select">
                            <option value="">الكل</option>
                            @foreach($regions as $region)
                                <option value="{{ $region }}">{{ $region }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">قائد الفريق</label>
                        <select id="filter_team_leader_id"
                                class="form-select"
                                multiple>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">المهندس الميداني</label>
                        <select id="filter_field_engineer_id"
                                class="form-select"
                                multiple>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="fw-bold">قائمة الربط</h3>
                </div>
            </div>

            <div class="card-body">
                <table id="team_leader_field_engineers_table"
                       class="table table-row-bordered table-row-gray-300 align-middle gs-0 gy-4">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th>#</th>
                            <th>قائد الفريق</th>
                            <th>المهندس الميداني</th>
                            <th>المنطقة</th>
                            <th>تمت الإضافة بواسطة</th>
                            <th>تاريخ الإضافة</th>
                            <th class="text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

    </div>
</div>

@endsection

@section('script')
<script>
$(function () {
    const isTeamLeader = @json($isTeamLeader);

    const table = $('#team_leader_field_engineers_table').DataTable({
        processing: true,
        serverSide: true,
        searching: false,
        ordering: false,
        ajax: {
            url: "{{ route('admin.team-leader-field-engineers.data') }}",
            data: function (d) {
                d.region = $('#filter_region').val();
                d.team_leader_id = $('#filter_team_leader_id').val();
                d.field_engineer_id = $('#filter_field_engineer_id').val();
            }
        },
        columns: [
            { data: 'index', name: 'index' },
            { data: 'team_leader', name: 'team_leader' },
            { data: 'field_engineer', name: 'field_engineer' },
            { data: 'field_engineer_region', name: 'field_engineer_region' },
            { data: 'created_by', name: 'created_by' },
            { data: 'created_at', name: 'created_at' },
            { data: 'actions', orderable: false, searchable: false },
        ],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
        }
    });

    function initTeamLeaderSelect(selector) {
        $(selector).select2({
            placeholder: 'اختر قائد الفريق',
            allowClear: true,
            multiple: selector !== '#team_leader_id',
            closeOnSelect: selector === '#team_leader_id',
            ajax: {
                url: "{{ route('admin.team-leader-field-engineers.select.team-leaders') }}",
                dataType: 'json',
                delay: 300,
                data: params => ({ q: params.term }),
                processResults: data => data
            }
        });
    }

    function initFieldEngineerSelect(selector) {
        $(selector).select2({
            placeholder: 'اختر المهندس الميداني',
            allowClear: true,
            multiple: true,
            closeOnSelect: false,
            ajax: {
                url: "{{ route('admin.team-leader-field-engineers.select.field-engineers') }}",
                dataType: 'json',
                delay: 300,
                data: params => ({
                    q: params.term,
                    region: $('#filter_region').val()
                }),
                processResults: data => data
            }
        });
    }

    if (!isTeamLeader) {
        initTeamLeaderSelect('#team_leader_id');
    }

    initTeamLeaderSelect('#filter_team_leader_id');

    initFieldEngineerSelect('#field_engineer_id');
    initFieldEngineerSelect('#filter_field_engineer_id');

    $('#assignment_form').on('submit', function (e) {
        e.preventDefault();

        $.ajax({
            url: "{{ route('admin.team-leader-field-engineers.store') }}",
            method: 'POST',
            data: $(this).serializeArray(),
            success: function (response) {
                toastr.success(response.message);

                if (!isTeamLeader) {
                    $('#team_leader_id').val(null).trigger('change');
                }

                $('#field_engineer_id').val(null).trigger('change');
                table.ajax.reload(null, false);
            },
            error: function (xhr) {
                let message = xhr.responseJSON?.message || 'حدث خطأ أثناء الحفظ';
                toastr.error(message);
            }
        });
    });

    $('#filter_region').on('change', function () {
        $('#field_engineer_id').val(null).trigger('change');
        $('#filter_field_engineer_id').val(null).trigger('change');
        table.ajax.reload();
    });

    $('#filter_team_leader_id, #filter_field_engineer_id').on('change', function () {
        table.ajax.reload();
    });

    $('#reset_filters').on('click', function () {
        $('#filter_region').val('').trigger('change');
        $('#filter_team_leader_id').val(null).trigger('change');
        $('#filter_field_engineer_id').val(null).trigger('change');
        table.ajax.reload();
    });

    $(document).on('click', '.delete-assignment', function () {
        if (!confirm('هل أنت متأكد من حذف هذا الربط؟')) {
            return;
        }

        $.ajax({
            url: $(this).data('url'),
            method: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                _method: 'DELETE'
            },
            success: function (response) {
                toastr.success(response.message);
                table.ajax.reload(null, false);
            },
            error: function (xhr) {
                let message = xhr.responseJSON?.message || 'حدث خطأ أثناء الحذف';
                toastr.error(message);
            }
        });
    });

    $('#export_btn').on('click', function (e) {
        e.preventDefault();

        const params = $.param({
            region: $('#filter_region').val(),
            team_leader_id: $('#filter_team_leader_id').val(),
            field_engineer_id: $('#filter_field_engineer_id').val(),
        });

        window.location.href = "{{ route('admin.team-leader-field-engineers.export') }}" + '?' + params;
    });
});
</script>
@endsection