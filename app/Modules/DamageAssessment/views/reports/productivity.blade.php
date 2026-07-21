@extends('layouts.app')
@section('title', 'Productivity Rates Report')
@section('pageName', 'Productivity Rates Report')

@section('content')
    <style>
        .productivity-report-shell {
            background: #f5f7fb;
            border-radius: 8px;
            padding: 18px;
        }

        .productivity-report-card {
            border: 1px solid #e4e9f2;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }

        .productivity-toolbar {
            align-items: flex-end;
            display: grid;
            gap: 12px;
            grid-template-columns: minmax(220px, 1.2fr) minmax(220px, 1fr) auto auto;
            width: 100%;
        }

        .productivity-filter-label {
            color: #48556a;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .productivity-table-wrap {
            border: 1px solid #e4e9f2;
            border-radius: 8px;
            max-height: calc(100vh - 320px);
            min-height: 360px;
            overflow: auto;
        }

        .productivity-table {
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 0;
            min-width: max-content;
        }

        .productivity-table thead {
            position: sticky;
            top: 0;
            z-index: 20;
        }

        .productivity-table tfoot {
            bottom: 0;
            position: sticky;
            z-index: 18;
        }

        .productivity-table th,
        .productivity-table td {
            border-color: #ffffff !important;
            font-size: 12px;
            height: 40px;
            min-width: 58px;
            padding: 8px 10px;
            white-space: nowrap;
        }

        .productivity-table thead th,
        .productivity-table tfoot td,
        .productivity-engineer-cell {
            background: #f4b400 !important;
            color: #111827 !important;
            font-weight: 800;
        }

        .productivity-table th:first-child,
        .productivity-table td:first-child {
            box-shadow: -2px 0 0 #e4e9f2;
            min-width: 190px;
            position: sticky;
            right: 0;
            z-index: 12;
        }

        .productivity-table thead th:first-child {
            z-index: 30;
        }

        .productivity-table tfoot td:first-child {
            z-index: 28;
        }

        .productivity-cell-pda {
            background: #16a34a !important;
            color: #ffffff !important;
            font-weight: 700;
        }

        .productivity-cell-tda {
            background: #dc2626 !important;
            color: #ffffff !important;
            font-weight: 700;
        }

        .productivity-cell-total {
            background: #0284c7 !important;
            color: #ffffff !important;
            font-weight: 800;
        }

        .productivity-row-total {
            background: #334155 !important;
            color: #ffffff !important;
            font-weight: 800;
        }

        .productivity-empty-state {
            align-items: center;
            color: #64748b;
            display: flex;
            flex-direction: column;
            gap: 6px;
            justify-content: center;
            min-height: 260px;
            text-align: center;
        }

        @media (max-width: 991.98px) {
            .productivity-toolbar {
                grid-template-columns: 1fr;
            }

            .productivity-toolbar .btn {
                width: 100%;
            }
        }
    </style>

    <div class="productivity-report-shell">
        <div class="card productivity-report-card">
            <div class="card-header border-0 pt-6">
                <div class="card-title flex-column align-items-start">
                    <h2 class="mb-1 text-gray-900">Productivity Rates TDA-PDA Report</h2>
                    <span class="text-muted fw-semibold">Daily engineer productivity by submitted housing units.</span>
                </div>
            </div>

            <div class="card-body pt-0">
                <form id="productivity_filters_form" class="productivity-toolbar mb-6" method="GET" action="{{ url('damage-assessment/reports/productivity') }}">
                    <div>
                        <label class="productivity-filter-label" for="engineer_name">Engineer name</label>
                        <input
                            id="engineer_name"
                            name="engineer_name"
                            value="{{ $filters['engineer_name'] ?? '' }}"
                            class="form-control form-control-solid"
                            list="productivity_engineers"
                            placeholder="Search engineer name"
                            autocomplete="off"
                        />
                        <datalist id="productivity_engineers">
                            @foreach ($allAssignedto as $engineer)
                                <option value="{{ $engineer }}"></option>
                            @endforeach
                        </datalist>
                    </div>

                    <div>
                        <label class="productivity-filter-label" for="kt_ecommerce_sales_flatpickr">Date range</label>
                        <div class="input-group">
                            <input
                                class="form-control form-control-solid rounded rounded-end-0"
                                placeholder="Date from - to"
                                id="kt_ecommerce_sales_flatpickr"
                                value="{{ trim(($filters['minDate'] ?? '').' to '.($filters['maxDate'] ?? ''), ' to') }}"
                            />
                            <button class="btn btn-icon btn-light" type="button" id="kt_ecommerce_sales_flatpickr_clear" title="Clear date range">
                                <i class="ki-duotone ki-cross fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </button>
                        </div>
                        <input type="hidden" name="minDate" id="minDate" value="{{ $filters['minDate'] ?? '' }}">
                        <input type="hidden" name="maxDate" id="maxDate" value="{{ $filters['maxDate'] ?? '' }}">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Search
                    </button>

                    <button id="export_btn" type="button" class="btn btn-light-primary">
                        Export report
                    </button>
                </form>

                @if ($assignedto->isEmpty())
                    <div class="productivity-empty-state">
                        <h4 class="mb-0">No engineers match the current filters.</h4>
                        <span>Try another engineer name or date range.</span>
                    </div>
                @else
                    <div class="productivity-table-wrap">
                        <table class="table table-bordered text-center align-middle productivity-table">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    @foreach ($period as $date)
                                        <th colspan="3">{{ $date->format('Y-m-d D') }}</th>
                                    @endforeach
                                    <th>Total</th>
                                </tr>
                                <tr>
                                    <th>ENG. Name</th>
                                    @foreach ($period as $date)
                                        <th scope="col">TDA</th>
                                        <th scope="col">PDA</th>
                                        <th scope="col">Total</th>
                                    @endforeach
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $columnTotals = [];
                                    $grandTotal = 0;
                                @endphp

                                @foreach ($assignedto as $val)
                                    <tr>
                                        <td class="productivity-engineer-cell">{{ $val }}</td>
                                        @foreach ($period as $date)
                                            @php
                                                $dateStr = $date->format('Y-m-d');
                                                $dayData = $stats[$val]['daily_breakdown'][$dateStr] ?? null;

                                                $pda = $dayData[0]['pda'] ?? 0;
                                                $tda = $dayData[0]['tda'] ?? 0;
                                                $rowDayTotal = $pda + $tda;

                                                $columnTotals[$dateStr]['tda'] = ($columnTotals[$dateStr]['tda'] ?? 0) + $tda;
                                                $columnTotals[$dateStr]['pda'] = ($columnTotals[$dateStr]['pda'] ?? 0) + $pda;
                                                $columnTotals[$dateStr]['total'] = ($columnTotals[$dateStr]['total'] ?? 0) + $rowDayTotal;
                                            @endphp
                                            <td class="productivity-cell-tda">{{ $tda }}</td>
                                            <td class="productivity-cell-pda">{{ $pda }}</td>
                                            <td class="productivity-cell-total">{{ $rowDayTotal }}</td>
                                        @endforeach

                                        <td class="productivity-row-total">
                                            @if (isset($stats[$val]))
                                                @php $engTotal = $stats[$val]['engineer_total']; @endphp
                                                {{ $engTotal }}
                                                @php $grandTotal += $engTotal; @endphp
                                            @else
                                                0
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>Total</td>
                                    @foreach ($period as $date)
                                        @php $dateStr = $date->format('Y-m-d'); @endphp
                                        <td>{{ $columnTotals[$dateStr]['tda'] ?? 0 }}</td>
                                        <td>{{ $columnTotals[$dateStr]['pda'] ?? 0 }}</td>
                                        <td>{{ $columnTotals[$dateStr]['total'] ?? 0 }}</td>
                                    @endforeach
                                    <td class="productivity-cell-total">{{ $grandTotal }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        "use strict";

        var kTExportProductivity = function () {
            var flatpickr;
            var minformatted = document.getElementById('minDate').value;
            var maxformatted = document.getElementById('maxDate').value;
            const exportButton = document.getElementById('export_btn');
            const filterForm = document.getElementById('productivity_filters_form');

            var initFlatpickr = () => {
                const element = document.querySelector('#kt_ecommerce_sales_flatpickr');
                const defaultDates = [minformatted, maxformatted].filter(Boolean);

                flatpickr = $(element).flatpickr({
                    altInput: true,
                    altFormat: "Y-m-d",
                    dateFormat: "Y-m-d",
                    defaultDate: defaultDates,
                    mode: "range",
                    onChange: function (selectedDates, dateStr, instance) {
                        if (selectedDates.length === 2) {
                            minformatted = instance.formatDate(selectedDates[0], "Y-m-d");
                            maxformatted = instance.formatDate(selectedDates[1], "Y-m-d");
                            document.getElementById('minDate').value = minformatted;
                            document.getElementById('maxDate').value = maxformatted;
                            filterForm.submit();
                        }
                    },
                });
            }

            var handleClearFlatpickr = () => {
                const clearButton = document.querySelector('#kt_ecommerce_sales_flatpickr_clear');
                clearButton.addEventListener('click', e => {
                    flatpickr.clear();
                    document.getElementById('minDate').value = '';
                    document.getElementById('maxDate').value = '';
                    filterForm.submit();
                });
            }

            var handleExport = () => {
                exportButton.addEventListener('click', function () {
                    exportButton.classList.add('disabled');

                    setTimeout(() => {
                        exportButton.classList.remove('disabled');
                    }, 2000);

                    const params = new URLSearchParams(new FormData(filterForm));
                    window.location.href = "{{ url('damage-assessment/export_productivity') }}?" + params.toString();
                });
            }

            return {
                init: function () {
                    initFlatpickr();
                    handleClearFlatpickr();
                    handleExport();
                }
            };
        }();

        KTUtil.onDOMContentLoaded(function () {
            kTExportProductivity.init();
        });
    </script>
@endsection
