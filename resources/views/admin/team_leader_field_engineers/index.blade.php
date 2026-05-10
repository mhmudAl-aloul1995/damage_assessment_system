@extends('layouts.app')

@section('title', 'ربط Team Leader مع Field Engineer')
@section('pageName', 'ربط المهندسين')

@section('content')
<div class="card card-flush mb-7">
    <div class="card-header pt-7">
        <div class="card-title">
            <h2 class="fw-bold">ربط Team Leader مع Field Engineer</h2>
        </div>
    </div>
    <div class="card-body">
        <form id="teamLeaderFieldEngineerForm" class="row g-5 align-items-end">
            @csrf
            <div class="col-md-5">
                <label class="form-label fw-semibold">Team Leader</label>
                <select name="team_leader_id" class="form-select form-select-solid" data-control="select2" data-placeholder="اختر Team Leader">
                    <option value=""></option>
                    @foreach ($teamLeaders as $teamLeader)
                        <option value="{{ $teamLeader->id }}">{{ $teamLeader->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold">Field Engineer</label>
                <select name="field_engineer_id" class="form-select form-select-solid" data-control="select2" data-placeholder="اختر Field Engineer">
                    <option value=""></option>
                    @foreach ($fieldEngineers as $fieldEngineer)
                        <option value="{{ $fieldEngineer->id }}">{{ $fieldEngineer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">حفظ الربط</button>
            </div>
        </form>
    </div>
</div>

<div class="card card-flush">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="teamLeaderFieldEngineersTable">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                        <th>Team Leader</th>
                        <th>Field Engineer</th>
                        <th>Created By</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    $('[data-control="select2"]').select2({ dir: 'rtl', width: '100%' });

    const teamLeaderFieldEngineersTable = $('#teamLeaderFieldEngineersTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('team-leader-field-engineers.datatable') }}",
        columns: [
            { data: 'team_leader', name: 'teamLeader.name' },
            { data: 'field_engineer', name: 'fieldEngineer.name' },
            { data: 'created_by', name: 'creator.name' },
            { data: 'created_at_formatted', name: 'created_at' },
            { data: 'actions', name: 'actions', searchable: false, orderable: false },
        ],
    });

    $('#teamLeaderFieldEngineerForm').on('submit', function (event) {
        event.preventDefault();

        $.ajax({
            url: "{{ route('team-leader-field-engineers.store') }}",
            method: 'POST',
            data: $(this).serialize(),
            success: function (response) {
                toastr.success(response.message || 'تم الحفظ بنجاح');
                $('#teamLeaderFieldEngineerForm')[0].reset();
                $('[data-control="select2"]').val(null).trigger('change');
                teamLeaderFieldEngineersTable.ajax.reload();
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON?.message || 'فشل الحفظ');
            },
        });
    });

    $(document).on('click', '.js-delete-link', function () {
        const url = $(this).data('url');

        Swal.fire({
            text: 'هل تريد حذف هذا الربط؟',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'نعم',
            cancelButtonText: 'إلغاء',
            customClass: {
                confirmButton: 'btn btn-danger',
                cancelButton: 'btn btn-light',
            },
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                url: url,
                method: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    _method: 'DELETE',
                },
                success: function (response) {
                    toastr.success(response.message || 'تم الحذف');
                    teamLeaderFieldEngineersTable.ajax.reload();
                },
                error: function () {
                    toastr.error('فشل حذف الربط');
                },
            });
        });
    });
</script>
@endsection
