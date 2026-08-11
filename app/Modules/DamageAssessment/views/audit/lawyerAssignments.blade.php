@extends('layouts.app')

@section('title', 'تأهيل وحدات - تدقيق قانوني')
@section('pageName', 'تأهيل وحدات - تدقيق قانوني')

@section('content')
    <div class="row mb-5">
        <div class="col-12">
            <div class="card card-flush">
                <div class="card-header align-items-center py-5">
                    <div class="card-title">
                        <h2 class="fw-bold m-0">قائمة الوحدات للمحامين</h2>
                    </div>
                    <div class="card-toolbar">
                        <button type="button" class="btn btn-sm btn-light-primary" id="lawyerAssignmentsRefresh">
                            تحديث
                        </button>
                    </div>
                </div>

                <div class="card-body pt-0">
                    <div class="row g-4 mb-6">
                        @foreach($ranges as $range)
                            <div class="col-md-6">
                                <div class="border rounded p-4 h-100">
                                    <div class="fw-bold text-gray-900">{{ $range['lawyer'] }}</div>
                                    <div class="text-muted">عداد الإكسل: {{ $range['from'] }} - {{ $range['to'] ?? 'آخر سطر' }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($restrictedLawyerName)
                        <div class="alert alert-info">
                            هذه الصفحة تعرض الوحدات المخصصة لـ {{ $restrictedLawyerName }} فقط، وهي للقراءة وفتح الوحدة دون تعديل.
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-4" id="lawyerAssignmentsTable">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th>عداد الإكسل</th>
                                    <th>المحامي</th>
                                    <th>ObjectID المبنى</th>
                                    <th>ObjectID الوحدة</th>
                                    <th>اسم المنتفع</th>
                                    <th>رقم الهوية</th>
                                    <th>الجوال</th>
                                    <th>الضرر</th>
                                    <th>الطابق</th>
                                    <th>رقم الوحدة</th>
                                    <th>المنطقة</th>
                                    <th class="text-end">فتح</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 fw-semibold"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $(function () {
            const table = $('#lawyerAssignmentsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('audit.lawyer-assignments') }}",
                order: [[0, 'asc']],
                columns: [
                    { data: 'excel_index', name: 'excel_index' },
                    { data: 'lawyer_name', name: 'lawyer_name' },
                    { data: 'building_objectid', name: 'building_objectid', defaultContent: '-' },
                    { data: 'housing_unit_objectid', name: 'housing_unit_objectid', defaultContent: '-' },
                    { data: 'owner_full_name', name: 'owner_full_name', defaultContent: '-' },
                    { data: 'id_number', name: 'id_number', defaultContent: '-' },
                    { data: 'mobile_number', name: 'mobile_number', defaultContent: '-' },
                    { data: 'unit_damage_status', name: 'unit_damage_status', defaultContent: '-' },
                    { data: 'floor_number', name: 'floor_number', defaultContent: '-' },
                    { data: 'housing_unit_number', name: 'housing_unit_number', defaultContent: '-' },
                    {
                        data: null,
                        name: 'neighborhood',
                        orderable: false,
                        searchable: false,
                        render: function (row) {
                            return [row.governorate, row.locality, row.neighborhood, row.street]
                                .filter(Boolean)
                                .join(' / ') || '-';
                        }
                    },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' },
                ],
                rowCallback: function (row, data) {
                    $(row)
                        .attr('data-assessment-url', data.assessment_url)
                        .addClass('cursor-pointer');
                },
                language: {
                    processing: 'جاري التحميل...',
                    search: 'بحث:',
                    lengthMenu: 'عرض _MENU_ سجلات',
                    info: 'عرض _START_ إلى _END_ من _TOTAL_',
                    infoEmpty: 'لا توجد سجلات',
                    infoFiltered: '(مفلترة من _MAX_ سجل)',
                    emptyTable: 'لا توجد بيانات',
                    zeroRecords: 'لا توجد نتائج مطابقة',
                    paginate: {
                        first: 'الأول',
                        last: 'الأخير',
                        next: 'التالي',
                        previous: 'السابق',
                    },
                }
            });

            $('#lawyerAssignmentsTable tbody').on('dblclick', 'tr', function () {
                const url = $(this).data('assessment-url');

                if (url) {
                    window.open(url, '_blank');
                }
            });

            $('#lawyerAssignmentsRefresh').on('click', function () {
                table.ajax.reload(null, false);
            });
        });
    </script>
@endsection
