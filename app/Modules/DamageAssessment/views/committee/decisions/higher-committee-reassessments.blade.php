@extends('layouts.app')

@section('title', 'إعادة تقييم قرارات اللجنة العليا')
@section('pageName', 'إعادة تقييم قرارات اللجنة العليا')

@section('content')
    @if (session('success'))
        <div class="alert alert-success mb-5">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger mb-5">{{ $errors->first() }}</div>
    @endif

    <div class="card card-flush shadow-sm">
        <div class="card-header pt-6">
            <div class="card-title">
                <h3 class="fw-bold m-0">إعادة تقييم قرارات اللجنة العليا</h3>
            </div>
            <div class="card-toolbar">
                <a href="{{ route('committee-decisions.index') }}" class="btn btn-light btn-sm">رجوع لقرارات اللجنة</a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="higher_committee_reassessments_table" class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 w-100">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th>نوع السجل</th>
                            <th>Building ObjectID</th>
                            <th>Housing Unit ObjectID</th>
                            <th>الاسم / المالك</th>
                            <th>البلدية</th>
                            <th>الحي</th>
                            <th>تاريخ قرار اللجنة العليا</th>
                            <th>التواقيع السابقة</th>
                            <th>حالة إعادة التقييم</th>
                            <th class="text-end">الإجراء</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            $('#higher_committee_reassessments_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('committee-decisions.higher-committee-reassessments.data') }}',
                order: [[6, 'desc']],
                columns: [
                    { data: 'record_type', name: 'decisionable_type', orderable: false, searchable: false },
                    { data: 'building_objectid', name: 'building_objectid', orderable: false, searchable: false },
                    { data: 'housing_unit_objectid', name: 'housing_unit_objectid', orderable: false, searchable: false },
                    { data: 'record_name', name: 'record_name', orderable: false, searchable: false },
                    { data: 'municipality', name: 'municipality', orderable: false, searchable: false },
                    { data: 'neighborhood', name: 'neighborhood', orderable: false, searchable: false },
                    { data: 'decision_date', name: 'decision_date' },
                    { data: 'signatures_count', name: 'signatures_count', orderable: false, searchable: false },
                    { data: 'reassessment_status', name: 'reassessment_status', orderable: false, searchable: false },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' },
                ]
            });
        });
    </script>
@endsection
