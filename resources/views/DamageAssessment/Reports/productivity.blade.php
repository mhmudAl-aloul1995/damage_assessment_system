@extends('layouts.app')
@section('title', 'الوحدات السكنية')
@section('pageName', 'الوحدات السكنية')


@section('content')
    <style>



    </style>
    <div class="row">
        <div class="col-md-12 ">
            <div class="card">

                <!--begin::Card header-->
                <div class="card-header border-0 pt-6">
                    <!--begin::Card title-->
                    <div class="card-title">
                        <h2 style=" color: green; " class="">Productivity Rates TDA-PDA Report</h3>

                    </div>
                    <!--begin::Card title-->
                    <!--begin::Card toolbar-->
                    <div class="card-toolbar flex-row-fluid justify-content-end gap-5">
                        <!--begin::Flatpickr-->
                        <div class="input-group w-250px">
                            <input class="form-control form-control-solid rounded rounded-end-0" placeholder="تاريخ من إلى"
                                id="kt_ecommerce_sales_flatpickr" />
                            <button class="btn btn-icon btn-light" id="kt_ecommerce_sales_flatpickr_clear">
                                <i class="ki-duotone ki-cross fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </button>
                        </div>
                        <!--end::Flatpickr-->

                        <!--begin::Add product-->
                        <button id="export_btn" class="btn btn-primary">
                            تصدير تقرير</button>
                        <!--end::Add product-->
                    </div>
                    <!--end::Card toolbar-->
                </div>
                <!--end::Card header-->
                <!--begin::Card body-->
                <style>
                    /* Keeps the first few columns visible while scrolling right */
                    .sticky-col {
                        position: sticky;
                        left: 0;
                        background-color: white !important;
                        z-index: 2;
                        border-right: 2px solid #ebedf3;
                    }

                    /* Offset for the second sticky column */
                    .sticky-col-2 {
                        left: 50px;
                    }

                    .sticky-col-3 {
                        left: 150px;
                    }
                </style>

                <div class="card-body py-4">
                    <!-- .table-responsive enables horizontal scrolling -->

                    <div class="table-responsive">
                        <table class="table table-bordered table-stripped  text-center align-middle">
                            <thead style="  background-color: #ffc107;"> 
                                <tr>
                                    <th>Day</th>
                                    @foreach ($period as $date)
                                        <th style="width:10px;" colspan="3"> {{ $date->format('Y-m-d D') }} </th>
                                    @endforeach
                                    <th> </th>

                                </tr>
                                <tr>
                                    <th> ENG.Name</th>
                                    @foreach ($period as $date)
                                        <th scope="col">TDA</th>
                                        <th scope="col">PDA</th>
                                        <th scope="col">Total</th>
                                    @endforeach
                                    <th> Total </th>

                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    // Initialize an array to hold totals for each date
                                    $columnTotals = [];
                                    $grandTotal = 0;
                                @endphp

                                @foreach ($assignedto as $val)
                                    <tr>
                                        <td class="bg-warning">{{ $val }}</td>
                                        @foreach ($period as $date)
                                            @php
                                                $dateStr = $date->format('Y-m-d');
                                                $dayData = $stats[$val]['daily_breakdown'][$dateStr] ?? null;

                                                $pda = $dayData[0]['pda'] ?? 0;
                                                $tda = $dayData[0]['tda'] ?? 0;
                                                $rowDayTotal = $pda + $tda;

                                                // Add to vertical column totals
                                                $columnTotals[$dateStr]['pda'] = ($columnTotals[$dateStr]['pda'] ?? 0) + $pda;
                                                $columnTotals[$dateStr]['tda'] = ($columnTotals[$dateStr]['tda'] ?? 0) + $tda;
                                                $columnTotals[$dateStr]['total'] = ($columnTotals[$dateStr]['total'] ?? 0) + $rowDayTotal;
                                            @endphp
                                            <td class="text-white bg-danger-active">{{ $pda }}</td>
                                            <td class="text-white bg-success-active">{{ $tda }}</td>
                                            <td class="text-white bg-primary-active">{{ $rowDayTotal }}</td>
                                        @endforeach

                                        <td style="background-color: gray;" class="text-white">
                                            <b>
                                                @if (isset($stats[$val]))
                                                    @php $engTotal = $stats[$val]['engineer_total']; @endphp
                                                    {{ $engTotal }}
                                                    @php $grandTotal += $engTotal; @endphp
                                                @endif
                                            </b>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>

                           <tfoot>
    <tr style="background-color: #ffc107; font-weight: bold;">
        <td>الإجمالي (Total)</td>
        @foreach ($period as $date)
            @php $dateStr = $date->format('Y-m-d'); @endphp
            <td>{{ $columnTotals[$dateStr]['pda'] ?? 0 }}</td>
            <td>{{ $columnTotals[$dateStr]['tda'] ?? 0 }}</td>
            <td>{{ $columnTotals[$dateStr]['total'] ?? 0 }}</td>
        @endforeach
        <td class="bg-info text-white">
            <b>{{ $grandTotal }}</b>
        </td>
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

            "use strict";

            // Class definition
            var kTExportProductivity = function () {
                // Shared variables
                var table;
                var flatpickr;
                var minDate, maxDate;
                var minformatted, maxformatted
                const export_btn = document.getElementById('export_btn');

                // Handle flatpickr --- more info: https://flatpickr.js.org/events/
                var handleFlatpickr = (selectedDates, dateStr, instance) => {
                    minDate = selectedDates[0] ? new Date(selectedDates[0]) : null;
                    maxDate = selectedDates[1] ? new Date(selectedDates[1]) : null;

                    if (selectedDates.length > 0) {
                        // Manually format a date using instance.formatDate
                        minformatted = instance.formatDate(selectedDates[0], "Y-m-d");
                        maxformatted = instance.formatDate(selectedDates[1], "Y-m-d");
                    }
                }


                var initFlatpickr = () => {
                    const element = document.querySelector('#kt_ecommerce_sales_flatpickr');
                    flatpickr = $(element).flatpickr({
                        altInput: true,
                        altFormat: "Y-m-d",
                        dateFormat: "Y-m-d",
                        mode: "range",
                        onChange: function (selectedDates, dateStr, instance) {
                            handleFlatpickr(selectedDates, dateStr, instance);


                            if (minDate && maxDate) {
                                var millisecondsInDay = 1000 * 60 * 60 * 24;
                                // Calculate the difference and round down to a whole number
                                var days = Math.floor((minDate.getTime() - maxDate.getTime()) / millisecondsInDay);

                                window.location.href = "{{url('reports/productivity')}}?" + "minDate=" + minformatted + '&maxDate=' + maxformatted


                            }


                        },
                    });
                }






                // Handle clear flatpickr
                var handleClearFlatpickr = () => {
                    const clearButton = document.querySelector('#kt_ecommerce_sales_flatpickr_clear');
                    clearButton.addEventListener('click', e => {
                        flatpickr.clear();
                    });
                }
                function getUrlParameter(name) {
                    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
                    var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
                    var results = regex.exec(location.search);
                    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
                };
                export_btn.addEventListener('click', function (e) {
                    $(this).addClass('disabled');
    
setTimeout(() => {
  $(this).removeClass('disabled');
}, 2000);
                    window.location.href = "{{url('export_productivity')}}?" + "minDate=" + getUrlParameter('minDate') + '&maxDate=' + getUrlParameter('maxDate')
                })


                // Public methods
                return {
                    init: function () {

                        initFlatpickr();
                        handleClearFlatpickr();
                    }
                };
            }();

            // On document ready
            KTUtil.onDOMContentLoaded(function () {
                kTExportProductivity.init();
            });

        </script>
    @endsection