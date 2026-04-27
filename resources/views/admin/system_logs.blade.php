@extends('layouts.app')

@section('content')

<div class="container-xxl">

    <div class="card card-flush shadow-sm">

        <div class="card-header align-items-center py-5">
            <div class="card-title">
                <h3 class="fw-bold m-0">System Logs</h3>
            </div>
        </div>

        <div class="card-body pt-0">

            <div class="table-responsive">

                <table class="table align-middle table-row-dashed fs-6 gy-5 w-100" id="kt_logs_table">

                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>ID</th>
                            <th>Type</th>
                            <th>Layer</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Inserted</th>
                            <th>Updated</th>
                            <th>Skipped</th>
                            <th>Duration</th>
                            <th>Speed</th>
                            <th>Finished</th>
                        </tr>
                    </thead>

                    <tbody></tbody>

                </table>

            </div>

        </div>
    </div>

</div>

@endsection


@section('scripts')

<script>
$(document).ready(function () {

    $('#kt_logs_table').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        order: [[0, 'desc']],
        ajax: "{{ route('system.logs.data') }}",

        language: {
            processing: "Loading...",
            search: "Search:",
            lengthMenu: "Show _MENU_",
            info: "Showing _START_ to _END_ of _TOTAL_ rows",
            paginate: {
                previous: "Prev",
                next: "Next"
            }
        },

        columns: [
            {data: 'id', name: 'id'},
            {data: 'operation_type', name: 'operation_type'},
            {data: 'layer_name', name: 'layer_name', defaultContent: '-'},
            {data: 'status', name: 'status', orderable: false, searchable: false},
            {data: 'total_records', name: 'total_records'},
            {data: 'inserted', name: 'inserted'},
            {data: 'updated', name: 'updated'},
            {data: 'skipped', name: 'skipped'},
            {data: 'duration_seconds', name: 'duration_seconds'},
            {data: 'records_per_second', name: 'records_per_second'},
            {data: 'finished_at', name: 'finished_at'}
        ]

    });

});
</script>

@endsection