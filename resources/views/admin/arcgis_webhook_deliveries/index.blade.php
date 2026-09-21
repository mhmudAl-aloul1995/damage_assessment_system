@extends('layouts.app')

@section('content')

<div class="container-xxl">

    <div class="card card-flush shadow-sm">

        <div class="card-header align-items-center py-5">
            <div class="card-title">
                <h3 class="fw-bold m-0">ArcGIS Webhook History</h3>
            </div>

            <div class="card-toolbar">
                <a href="{{ route('system.logs') }}" class="btn btn-light-primary">
                    System Logs
                </a>
            </div>
        </div>

        <div class="card-body pt-0">

            <div class="table-responsive">

                <table class="table align-middle table-row-dashed fs-6 gy-5 w-100" id="kt_arcgis_webhook_deliveries_table">

                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>ID</th>
                            <th>Status</th>
                            <th>Webhook</th>
                            <th>Events</th>
                            <th>HTTP</th>
                            <th>Signature</th>
                            <th>Summary</th>
                            <th>Error</th>
                            <th>Started</th>
                            <th>Finished</th>
                            <th>Duration</th>
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
$(document).ready(function () {

    $('#kt_arcgis_webhook_deliveries_table').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        order: [[0, 'desc']],
        ajax: "{{ route('admin.arcgis-webhook-deliveries.data') }}",

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
            {data: 'status', name: 'status', orderable: false, searchable: false},
            {data: 'webhook_name', name: 'webhook_name', defaultContent: '-'},
            {data: 'event_names', name: 'event_names', orderable: false},
            {data: 'http_status', name: 'http_status', defaultContent: '-'},
            {data: 'signature_present', name: 'signature_present'},
            {data: 'summary', name: 'summary', orderable: false, searchable: false},
            {data: 'error_message', name: 'error_message', orderable: false},
            {data: 'started_at', name: 'started_at'},
            {data: 'finished_at', name: 'finished_at'},
            {data: 'duration_ms', name: 'duration_ms'}
        ]

    });

});
</script>

@endsection
