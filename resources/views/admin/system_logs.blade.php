@extends('layouts.app')

@section('content')

<div class="container-xxl">

    <div class="card">

        <div class="card-header">
            <h3 class="card-title">System Logs</h3>
        </div>

        <div class="card-body">

            <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_logs_table">

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

            </table>

        </div>
    </div>

</div>

@endsection


@section('scripts')

<script>
$(function () {

    $('#kt_logs_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('system.logs.data') }}",
        responsive: true,
        pageLength: 25,
        order: [[0, 'desc']],

        columns: [
            {data: 'id', name: 'id'},
            {data: 'operation_type', name: 'operation_type'},
            {data: 'layer_name', name: 'layer_name'},
            {data: 'status', name: 'status'},
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