@extends('layouts.app')

@section('title', __('multilingual.committee_decisions.title'))
@section('pageName', __('multilingual.committee_decisions.page_name'))

@section('content')
    @if (session('success'))
        <div class="alert alert-success mb-5">{{ session('success') }}</div>
    @endif

    <div class="row g-5 mb-5">
        <div class="col-md-6">
            <div class="card card-flush h-100 border border-gray-200">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted fs-6 mb-2">{{ __('multilingual.committee_decisions.buildings_waiting') }}</div>
                        <div class="fs-2hx fw-bold text-primary">{{ $buildingCount }}</div>
                    </div>
                    <i class="ki-duotone ki-home fs-3x text-primary">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-flush h-100 border border-gray-200">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted fs-6 mb-2">{{ __('multilingual.committee_decisions.housing_waiting') }}</div>
                        <div class="fs-2hx fw-bold text-warning">{{ $housingCount }}</div>
                    </div>
                    <i class="ki-duotone ki-home-2 fs-3x text-warning">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush shadow-sm">
        <div class="card-header pt-6">
            <div class="card-title">
                <h3 class="fw-bold m-0">{{ __('multilingual.committee_decisions.management_title') }}</h3>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-4 align-items-end mb-6">
                <div class="col-md-2">
                    <label class="form-label">ObjectID</label>
                    <input type="text" id="filter_objectid" class="form-control form-control-solid">
                </div>
                <div class="col-md-2">
                    <label class="form-label">البلدية</label>
                    <select id="filter_municipality" class="form-select form-select-solid">
                        <option value="">الكل</option>
                        @foreach ($municipalities as $municipality)
                            <option value="{{ $municipality }}">{{ $municipality }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">حالة الضرر الحالية</label>
                    <select id="filter_current_damage_status" class="form-select form-select-solid">
                        <option value="">الكل</option>
                        @foreach (['committee_review', 'committee_review2', 'commite_review'] as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">field_status</label>
                    <select id="filter_field_status" class="form-select form-select-solid">
                        <option value="">الكل</option>
                        @foreach (['COMPLETED', 'Not_Completed'] as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">وجود القرار</label>
                    <select id="filter_has_decision" class="form-select form-select-solid">
                        <option value="">الكل</option>
                        <option value="yes">يوجد</option>
                        <option value="no">لا يوجد</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">نوع القرار</label>
                    <select id="filter_decision_type" class="form-select form-select-solid">
                        <option value="">الكل</option>
                        <option value="fully_damaged">fully_damaged</option>
                        <option value="partially_damaged">partially_damaged</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">حالة القرار</label>
                    <select id="filter_decision_status" class="form-select form-select-solid">
                        <option value="">الكل</option>
                        <option value="draft">draft</option>
                        <option value="pending_signatures">pending_signatures</option>
                        <option value="completed">completed</option>
                        <option value="approved">approved</option>
                        <option value="rejected">rejected</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">ArcGIS</label>
                    <select id="filter_arcgis_status" class="form-select form-select-solid">
                        <option value="">الكل</option>
                        <option value="synced">synced</option>
                        <option value="pending">pending</option>
                        <option value="failed">failed</option>
                        <option value="retrying">retrying</option>
                        <option value="skipped">skipped</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="button" id="committee_filters_apply" class="btn btn-primary flex-grow-1">بحث</button>
                    <button type="button" id="committee_filters_reset" class="btn btn-light">تصفير</button>
                </div>
            </div>

            <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x fs-6 fw-semibold mb-5">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#committee_buildings_tab">{{ __('multilingual.committee_decisions.tabs.buildings') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#committee_units_tab">{{ __('multilingual.committee_decisions.tabs.housing_units') }}</a>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="committee_buildings_tab" role="tabpanel">
                    <div class="table-responsive">
                        <table id="committee_buildings_table" class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 w-100">
                            <thead>
                                <tr class="fw-bold text-muted bg-light">
                                    <th>ObjectID</th>
                                    <th>{{ __('multilingual.committee_decisions.columns.building_name') }}</th>
                                    <th>البلدية</th>
                                    <th>{{ __('multilingual.committee_decisions.columns.neighborhood') }}</th>
                                    <th>{{ __('multilingual.committee_decisions.columns.field_engineer') }}</th>
                                    <th>{{ __('multilingual.committee_decisions.columns.current_status') }}</th>
                                    <th>{{ __('multilingual.committee_decisions.columns.decision') }}</th>
                                    <th>{{ __('multilingual.committee_decisions.columns.signatures') }}</th>
                                    <th>ArcGIS</th>
                                    <th class="text-end">{{ __('multilingual.committee_decisions.columns.actions') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="committee_units_tab" role="tabpanel">
                    <div class="table-responsive">
                        <table id="committee_units_table" class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 w-100">
                            <thead>
                                <tr class="fw-bold text-muted bg-light">
                                    <th>ObjectID</th>
                                    <th>{{ __('multilingual.committee_decisions.columns.owner_name') }}</th>
                                    <th>{{ __('multilingual.committee_decisions.columns.building') }}</th>
                                    <th>البلدية</th>
                                    <th>{{ __('multilingual.committee_decisions.columns.neighborhood') }}</th>
                                    <th>{{ __('multilingual.committee_decisions.columns.current_status') }}</th>
                                    <th>{{ __('multilingual.committee_decisions.columns.decision') }}</th>
                                    <th>{{ __('multilingual.committee_decisions.columns.signatures') }}</th>
                                    <th>ArcGIS</th>
                                    <th class="text-end">{{ __('multilingual.committee_decisions.columns.actions') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function committeeFilters() {
                return {
                    objectid: $('#filter_objectid').val(),
                    municipality: $('#filter_municipality').val(),
                    current_damage_status: $('#filter_current_damage_status').val(),
                    field_status: $('#filter_field_status').val(),
                    has_decision: $('#filter_has_decision').val(),
                    decision_type: $('#filter_decision_type').val(),
                    decision_status: $('#filter_decision_status').val(),
                    arcgis_status: $('#filter_arcgis_status').val(),
                };
            }

            const buildingsTable = $('#committee_buildings_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('committee-decisions.buildings.data') }}',
                    data: function (d) {
                        Object.assign(d, committeeFilters());
                    }
                },
                order: [[0, 'desc']],
                columns: [
                    { data: 'objectid', name: 'objectid' },
                    { data: 'building_name', name: 'building_name' },
                    { data: 'municipalitie', name: 'municipalitie', defaultContent: '-' },
                    { data: 'neighborhood', name: 'neighborhood' },
                    { data: 'assignedto', name: 'assignedto' },
                    { data: 'building_damage_status', name: 'building_damage_status' },
                    { data: 'has_decision', name: 'has_decision', orderable: false, searchable: false },
                    { data: 'signatures_count', name: 'signatures_count', orderable: false, searchable: false },
                    { data: 'arcgis_status', name: 'arcgis_status', orderable: false, searchable: false },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                ]
            });

            const unitsTable = $('#committee_units_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('committee-decisions.housing-units.data') }}',
                    data: function (d) {
                        Object.assign(d, committeeFilters());
                    }
                },
                order: [[0, 'desc']],
                columns: [
                    { data: 'objectid', name: 'objectid' },
                    { data: 'full_name', name: 'full_name', defaultContent: '-', orderable: false, searchable: false },
                    { data: 'building_name', name: 'building_name', orderable: false, searchable: false },
                    { data: 'municipalitie', name: 'municipalitie', defaultContent: '-' },
                    { data: 'neighborhood', name: 'neighborhood' },
                    { data: 'unit_damage_status', name: 'unit_damage_status' },
                    { data: 'has_decision', name: 'has_decision', orderable: false, searchable: false },
                    { data: 'signatures_count', name: 'signatures_count', orderable: false, searchable: false },
                    { data: 'arcgis_status', name: 'arcgis_status', orderable: false, searchable: false },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                ]
            });

            function reloadCommitteeTables() {
                buildingsTable.ajax.reload();
                unitsTable.ajax.reload();
            }

            $('#committee_filters_apply').on('click', reloadCommitteeTables);
            $('#filter_objectid').on('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    reloadCommitteeTables();
                }
            });
            $('#filter_municipality, #filter_current_damage_status, #filter_field_status, #filter_has_decision, #filter_decision_type, #filter_decision_status, #filter_arcgis_status').on('change', reloadCommitteeTables);
            $('#committee_filters_reset').on('click', function () {
                $('#filter_objectid').val('');
                $('#filter_municipality, #filter_current_damage_status, #filter_field_status, #filter_has_decision, #filter_decision_type, #filter_decision_status, #filter_arcgis_status').val('');
                reloadCommitteeTables();
            });
        });
    </script>
@endsection
