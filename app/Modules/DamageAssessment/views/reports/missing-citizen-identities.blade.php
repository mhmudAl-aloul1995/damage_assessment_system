@extends('layouts.app')
@section('title', __('ui.missing_citizen_identities.title'))
@section('pageName', __('ui.missing_citizen_identities.title'))

@section('content')
    <div class="row g-5 mb-5">
        <div class="col-md-4">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body d-flex align-items-center justify-content-between gap-4">
                    <div>
                        <div class="text-muted fs-7 mb-2">{{ __('ui.missing_citizen_identities.total_missing') }}</div>
                        <div class="fs-2x fw-bold text-danger">{{ number_format($missingCount) }}</div>
                    </div>
                    <div class="symbol symbol-50px">
                        <div class="symbol-label bg-light-danger">
                            <i class="ki-duotone ki-profile-circle fs-1 text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card card-flush border border-gray-200 h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="fw-bold fs-4 mb-2">{{ __('ui.missing_citizen_identities.rule_title') }}</div>
                    <div class="text-muted fs-6">{{ __('ui.missing_citizen_identities.rule_description') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush shadow-sm">
        <div class="card-header pt-6 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="card-title">
                <h3 class="fw-bold m-0">{{ __('ui.missing_citizen_identities.table_title') }}</h3>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="position-relative">
                    <i class="ki-duotone ki-magnifier fs-3 position-absolute top-50 translate-middle-y ms-4"></i>
                    <input type="text" data-kt-missing-citizens-filter="search" class="form-control form-control-solid w-250px ps-12" placeholder="{{ __('ui.missing_citizen_identities.search_placeholder') }}">
                </div>
                <button type="button" class="btn btn-light-primary" data-kt-missing-citizens-action="refresh">
                    <i class="ki-duotone ki-arrows-circle fs-2"></i>
                    {{ __('ui.missing_citizen_identities.refresh') }}
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-rounded table-striped align-middle table-row-dashed fs-6 gy-5 w-100" id="kt_table_missing_citizen_identities">
                    <thead>
                        <tr class="text-start text-muted fw-bold border-bottom border-gray-200 fs-7 text-uppercase gs-0">
                            <th>{{ __('ui.missing_citizen_identities.owner_name') }}</th>
                            <th>{{ __('ui.missing_citizen_identities.id_number') }}</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 fw-semibold"></tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        var KTMissingCitizenIdentities = function () {
            var table = document.getElementById('kt_table_missing_citizen_identities');
            var datatable;

            var initTable = function () {
                if (!table) {
                    return;
                }

                datatable = $(table).DataTable({
                    serverSide: true,
                    processing: true,
                    pageLength: 25,
                    order: [[1, 'asc']],
                    ajax: "{{ route('reports.missing-citizen-identities.data') }}",
                    columns: [
                        { data: 'owner_name', name: 'housing_units.unit_owner' },
                        { data: 'id_number1', name: 'housing_units.id_number1' },
                    ],
                    language: {
                        emptyTable: "{{ __('ui.missing_citizen_identities.empty_table') }}",
                        zeroRecords: "{{ __('ui.missing_citizen_identities.zero_records') }}",
                    },
                });
            };

            var bindEvents = function () {
                var search = document.querySelector('[data-kt-missing-citizens-filter="search"]');

                if (search) {
                    search.addEventListener('keyup', function (event) {
                        datatable.search(event.target.value).draw();
                    });
                }

                var refresh = document.querySelector('[data-kt-missing-citizens-action="refresh"]');

                if (refresh) {
                    refresh.addEventListener('click', function () {
                        datatable.ajax.reload(null, false);
                    });
                }
            };

            return {
                init: function () {
                    initTable();
                    bindEvents();
                }
            };
        }();

        KTUtil.onDOMContentLoaded(function () {
            KTMissingCitizenIdentities.init();
        });
    </script>
@endsection
