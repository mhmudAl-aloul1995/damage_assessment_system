@extends('layouts.app')

@section('title', 'تقرير تقييم المهندسين')
@section('pageName', 'تقرير تقييم المهندسين')

@section('content')
    <style>
        .engineer-audit-toolbar {
            width: 100%;
            display: grid;
            grid-template-columns: minmax(220px, 1.1fr) minmax(220px, 1fr) auto auto auto;
            gap: .75rem;
            align-items: end;
        }

        .engineer-audit-toolbar .form-label {
            margin-bottom: .35rem;
            color: #3f4254;
            font-weight: 700;
        }

        .engineer-audit-toolbar .btn {
            min-height: 43px;
            white-space: nowrap;
        }

        #engineer_audit_table th,
        #engineer_audit_table td {
            text-align: center !important;
            vertical-align: middle !important;
        }

        @media (max-width: 991.98px) {
            .engineer-audit-toolbar {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 575.98px) {
            .engineer-audit-toolbar {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="row" dir="rtl">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header border-0 pt-6 flex-column align-items-stretch">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-5">
                        <div class="card-title mb-0">
                            <h2 class="mb-0 text-success">تقرير تقييم المهندسين</h2>
                        </div>
                    </div>

                    <form action="{{ route('reports.engineer-audit') }}" method="GET" id="filter_form" class="w-100">
                        <input type="hidden" name="start_date" id="start_date" value="{{ $filters['start_date'] }}">
                        <input type="hidden" name="end_date" id="end_date" value="{{ $filters['end_date'] }}">
                        <input type="hidden" name="report_type" id="report_type" value="{{ $active_report_type }}">

                        <div class="engineer-audit-toolbar">
                            <div>
                                <label class="form-label">اسم الباحث الميداني</label>
                                <select name="assignedto" class="form-select form-select-solid engineer-audit-select"
                                    data-placeholder="كل الباحثين">
                                    <option value="">كل الباحثين</option>
                                    @foreach ($filter_options['engineers'] as $engineer)
                                        <option value="{{ $engineer }}" @selected($filters['assignedto'] === $engineer)>{{ $engineer }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="form-label">نطاق تاريخ التقديم</label>
                                <div class="input-group">
                                    <input class="form-control form-control-solid" id="engineer_audit_daterange"
                                        value="{{ \Carbon\Carbon::parse($filters['start_date'])->format('m/d/Y') }} - {{ \Carbon\Carbon::parse($filters['end_date'])->format('m/d/Y') }}"
                                        readonly>
                                    <span class="input-group-text"><i class="ki-duotone ki-calendar fs-2"></i></span>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                فلتر
                            </button>

                            <a href="{{ route('reports.engineer-audit') }}" class="btn btn-light">
                                تفريغ
                            </a>

                            <a href="{{ route('reports.engineer-audit.export', request()->query()) }}"
                                class="btn btn-success" id="engineer_audit_export">
                                <i class="fa fa-file-excel"></i>
                                تصدير إلى إكسل
                            </a>
                        </div>
                    </form>
                </div>

                <div class="card-body py-4">
                    @php
                        $tabQuery = request()->query();
                        unset($tabQuery['report_type']);
                    @endphp

                    <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold mb-6">
                        @foreach ($report_tabs as $reportType => $tab)
                            <li class="nav-item">
                                <a class="nav-link text-active-primary pb-4 {{ $active_report_type === $reportType ? 'active' : '' }}"
                                    href="{{ route('reports.engineer-audit', array_merge($tabQuery, ['report_type' => $reportType])) }}">
                                    {{ $tab['label'] }}
                                    <span class="badge badge-light-primary ms-2">{{ $tab['summary']['total_completed_count'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    <div class="row g-4 mb-6">
                        <div class="col-md-3">
                            <div class="border rounded p-4 bg-light-success">
                                <div class="text-muted fw-bold">المقبولة</div>
                                <div class="fs-2 fw-bolder text-success">{{ $summary['accepted_count'] }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-4 bg-light-danger">
                                <div class="text-muted fw-bold">المرفوضة</div>
                                <div class="fs-2 fw-bolder text-danger">{{ $summary['rejected_count'] }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-4 bg-light-warning">
                                <div class="text-muted fw-bold">تحتاج مراجعة</div>
                                <div class="fs-2 fw-bolder text-warning">{{ $summary['need_review_count'] }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-4 bg-light-primary">
                                <div class="text-muted fw-bold">{{ $total_label }}</div>
                                <div class="fs-2 fw-bolder text-primary">{{ $summary['total_completed_count'] }}</div>
                            </div>
                        </div>
                    </div>

                    <table class="table table-rounded table-striped table-row-bordered gy-7 text-center align-middle"
                        id="engineer_audit_table">
                        <thead>
                            <tr class="fw-bolder fs-6 text-gray-800">
                                <th>#</th>
                                <th>اسم الباحث الميداني</th>
                                <th>عدد {{ $item_label }} المقبولة</th>
                                <th>عدد {{ $item_label }} المرفوضة</th>
                                <th>عدد {{ $item_label }} تحتاج مراجعة</th>
                                <th>{{ $total_label }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>{{ $row->sequence }}</td>
                                    <td>{{ $row->field_engineer_name }}</td>
                                    <td class="fw-bold text-success">{{ $row->accepted_count }}</td>
                                    <td class="fw-bold text-danger">{{ $row->rejected_count }}</td>
                                    <td class="fw-bold text-warning">{{ $row->need_review_count }}</td>
                                    <td class="fw-bold text-primary">{{ $row->total_completed_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-top-2">
                            <tr class="fw-bold bg-light">
                                <td colspan="2" class="text-center">المجموع</td>
                                <td class="text-success">{{ $summary['accepted_count'] }}</td>
                                <td class="text-danger">{{ $summary['rejected_count'] }}</td>
                                <td class="text-warning">{{ $summary['need_review_count'] }}</td>
                                <td class="text-primary">{{ $summary['total_completed_count'] }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $(document).ready(function () {
            const filterForm = $('#filter_form');
            const exportBaseUrl = @json(route('reports.engineer-audit.export'));

            function updateExportUrl() {
                const query = filterForm.serialize();
                $('#engineer_audit_export').attr('href', exportBaseUrl + (query ? '?' + query : ''));
            }

            $('.engineer-audit-select').select2({
                allowClear: true,
                width: '100%'
            }).on('change', updateExportUrl);

            $('#engineer_audit_daterange').daterangepicker({
                startDate: moment(@json($filters['start_date'])),
                endDate: moment(@json($filters['end_date'])),
                locale: {
                    format: 'MM/DD/YYYY',
                    applyLabel: 'تطبيق',
                    cancelLabel: 'إلغاء',
                    customRangeLabel: 'تاريخ مخصص'
                },
                ranges: {
                    'من بداية 2026': [moment('2026-01-01'), moment()],
                    'آخر 30 يوم': [moment().subtract(29, 'days'), moment()],
                    'هذا الشهر': [moment().startOf('month'), moment().endOf('month')]
                }
            }, function (start, end) {
                $('#engineer_audit_daterange').val(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
                updateExportUrl();
            });

            updateExportUrl();

            $('#engineer_audit_table').DataTable({
                pageLength: 25,
                order: [[2, 'desc'], [5, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/ar.json',
                    emptyTable: 'لا توجد بيانات ضمن الفلاتر المحددة.'
                }
            });
        });
    </script>
@endsection
