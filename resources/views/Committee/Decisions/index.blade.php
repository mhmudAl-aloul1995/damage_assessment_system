@extends('layouts.app')

@section('title', 'قرارات اللجنة')
@section('pageName', 'قرارات اللجنة')

@section('content')
    @if (session('success'))
        <div class="alert alert-success mb-5">{{ session('success') }}</div>
    @endif

    <div class="row g-5 mb-5">
        <div class="col-md-6">
            <div class="card card-flush h-100 border border-gray-200">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted fs-6 mb-2">سجلات المباني بانتظار اللجنة</div>
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
                        <div class="text-muted fs-6 mb-2">سجلات الوحدات بانتظار اللجنة</div>
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
                <h3 class="fw-bold m-0">إدارة قرارات اللجنة الفنية</h3>
            </div>
        </div>
        <div class="card-body">
            <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x fs-6 fw-semibold mb-5">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#committee_buildings_tab">المباني</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#committee_units_tab">الوحدات السكنية</a>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="committee_buildings_tab" role="tabpanel">
                    <div class="table-responsive">
                        <table id="committee_buildings_table" class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 w-100">
                            <thead>
                                <tr class="fw-bold text-muted bg-light">
                                    <th>ObjectID</th>
                                    <th>GlobalID</th>
                                    <th>اسم المبنى</th>
                                    <th>الحي</th>
                                    <th>المهندس الميداني</th>
                                    <th>الحالة الحالية</th>
                                    <th>القرار</th>
                                    <th>التواقيع</th>
                                    <th>ArcGIS</th>
                                    <th>Telegram</th>
                                    <th class="text-end">الإجراء</th>
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
                                    <th>GlobalID</th>
                                    <th>الاسم / المالك</th>
                                    <th>المبنى</th>
                                    <th>الحي</th>
                                    <th>الحالة الحالية</th>
                                    <th>القرار</th>
                                    <th>التواقيع</th>
                                    <th>ArcGIS</th>
                                    <th>Telegram</th>
                                    <th class="text-end">الإجراء</th>
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
            $('#committee_buildings_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('committee-decisions.buildings.data') }}',
                order: [[0, 'desc']],
                columns: [
                    { data: 'objectid', name: 'objectid' },
                    { data: 'globalid', name: 'globalid' },
                    { data: 'building_name', name: 'building_name' },
                    { data: 'neighborhood', name: 'neighborhood' },
                    { data: 'assignedto', name: 'assignedto' },
                    { data: 'building_damage_status', name: 'building_damage_status' },
                    { data: 'has_decision', name: 'has_decision', orderable: false, searchable: false },
                    { data: 'signatures_count', name: 'signatures_count', orderable: false, searchable: false },
                    { data: 'arcgis_status', name: 'arcgis_status', orderable: false, searchable: false },
                    { data: 'telegram_status', name: 'telegram_status', orderable: false, searchable: false },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                ]
            });

            $('#committee_units_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('committee-decisions.housing-units.data') }}',
                order: [[0, 'desc']],
                columns: [
                    { data: 'objectid', name: 'objectid' },
                    { data: 'globalid', name: 'globalid' },
                    { data: 'full_name', name: 'full_name', defaultContent: '-', orderable: false, searchable: false },
                    { data: 'building_name', name: 'building_name', orderable: false, searchable: false },
                    { data: 'neighborhood', name: 'neighborhood' },
                    { data: 'unit_damage_status', name: 'unit_damage_status' },
                    { data: 'has_decision', name: 'has_decision', orderable: false, searchable: false },
                    { data: 'signatures_count', name: 'signatures_count', orderable: false, searchable: false },
                    { data: 'arcgis_status', name: 'arcgis_status', orderable: false, searchable: false },
                    { data: 'telegram_status', name: 'telegram_status', orderable: false, searchable: false },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                ]
            });
        });
    </script>
@endsection
