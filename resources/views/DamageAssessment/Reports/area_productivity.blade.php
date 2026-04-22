@extends('layouts.app')

@section('title', __($title_key))
@section('pageName', __($title_key))

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header border-0 pt-6" style="direction: {{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }};">
                    <div class="card-title">
                        <h2 style="color: green;">
                            {{ __($title_key) }}: {{ $start_date }}
                            <span class="text-gray-400">{{ __('multilingual.area_productivity_reports.labels.to') }}</span>
                            {{ $end_date }}
                        </h2>
                    </div>

                    <div class="card-toolbar">
                        <form action="{{ route($route_name) }}" method="GET" id="filter_form" class="w-100">
                            <input type="hidden" name="start_date" id="start_date" value="{{ $start_date }}">
                            <input type="hidden" name="end_date" id="end_date" value="{{ $end_date }}">

                            <div class="d-flex flex-wrap align-items-center gap-3">
                                <a href="{{ route($export_route_name, array_merge(request()->query(), ['start_date' => $start_date, 'end_date' => $end_date])) }}"
                                    class="btn btn-success">
                                    <i class="fa fa-file-excel"></i>
                                    {{ __('multilingual.area_productivity_reports.actions.export_excel') }}
                                </a>

                                <button type="submit" class="btn btn-primary">
                                    {{ __('multilingual.area_productivity_reports.actions.filter') }}
                                </button>

                                <div class="input-group w-md-300px">
                                    <input class="form-control form-control-solid" value="{{ $date_range_label }}"
                                        placeholder="{{ __('multilingual.area_productivity_reports.filters.date_range') }}"
                                        id="kt_daterangepicker" readonly />
                                    <span class="input-group-text"><i class="ki-duotone ki-calendar fs-2"></i></span>
                                </div>

                                <button class="btn btn-light" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#area-productivity-advanced-filters" aria-expanded="false">
                                    {{ __('multilingual.area_productivity_reports.actions.advanced_filters') }}
                                </button>
                            </div>

                            <div class="collapse mt-5" id="area-productivity-advanced-filters">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <select name="governorate" class="form-select form-select-solid area-report-select"
                                            data-placeholder="{{ __('multilingual.area_productivity_reports.filters.governorate') }}">
                                            <option value="">{{ __('multilingual.area_productivity_reports.filters.all_governorates') }}</option>
                                            @foreach ($filter_options['governorates'] as $governorate)
                                                <option value="{{ $governorate }}" @selected($filters['governorate'] === $governorate)>{{ $governorate }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="municipalitie" class="form-select form-select-solid area-report-select"
                                            data-placeholder="{{ __('multilingual.area_productivity_reports.filters.municipality') }}">
                                            <option value="">{{ __('multilingual.area_productivity_reports.filters.all_municipalities') }}</option>
                                            @foreach ($filter_options['municipalities'] as $municipality)
                                                <option value="{{ $municipality }}" @selected($filters['municipalitie'] === $municipality)>{{ $municipality }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="neighborhood" class="form-select form-select-solid area-report-select"
                                            data-placeholder="{{ __('multilingual.area_productivity_reports.filters.neighborhood') }}">
                                            <option value="">{{ __('multilingual.area_productivity_reports.filters.all_neighborhoods') }}</option>
                                            @foreach ($filter_options['neighborhoods'] as $neighborhood)
                                                <option value="{{ $neighborhood }}" @selected($filters['neighborhood'] === $neighborhood)>{{ $neighborhood }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="zone_code" class="form-select form-select-solid area-report-select"
                                            data-placeholder="{{ __('multilingual.area_productivity_reports.filters.zone_code') }}">
                                            <option value="">{{ __('multilingual.area_productivity_reports.filters.all_zone_codes') }}</option>
                                            @foreach ($filter_options['zone_codes'] as $zoneCode)
                                                <option value="{{ $zoneCode }}" @selected($filters['zone_code'] === $zoneCode)>{{ $zoneCode }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="assignedto" class="form-select form-select-solid area-report-select"
                                            data-placeholder="{{ __('multilingual.area_productivity_reports.filters.assignedto') }}">
                                            <option value="">{{ __('multilingual.area_productivity_reports.filters.all_assignedto') }}</option>
                                            @foreach ($filter_options['assignedto'] as $assignedto)
                                                <option value="{{ $assignedto }}" @selected($filters['assignedto'] === $assignedto)>{{ $assignedto }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-flex gap-3">
                                        <button type="submit" class="btn btn-primary flex-fill">
                                            {{ __('multilingual.area_productivity_reports.actions.apply_filters') }}
                                        </button>
                                        <a href="{{ route($route_name) }}" class="btn btn-light flex-fill">
                                            {{ __('multilingual.area_productivity_reports.actions.reset') }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-body py-4">
                    <table class="table table-rounded table-striped table-row-bordered gy-7" id="area_productivity_table">
                        <thead>
                            <tr class="fw-bolder fs-6 text-gray-800 text-uppercase">
                                <th>{{ __('multilingual.area_productivity_reports.columns.total_count') }}</th>
                                <th>{{ __('multilingual.area_productivity_reports.columns.cra') }}</th>
                                <th>{{ __('multilingual.area_productivity_reports.columns.pda') }}</th>
                                <th>{{ __('multilingual.area_productivity_reports.columns.tda') }}</th>
                                <th>{{ __('multilingual.area_productivity_reports.columns.engineers') }}</th>
                                <th>{{ __('multilingual.area_productivity_reports.columns.neighborhood') }}</th>
                                <th>{{ __('multilingual.area_productivity_reports.columns.municipality') }}</th>
                                <th>{{ __('multilingual.area_productivity_reports.columns.governorate') }}</th>
                                <th>{{ __('multilingual.area_productivity_reports.columns.sector') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="fw-bold">{{ $row->total_count }}</td>
                                    <td>{{ $row->cra_range }}</td>
                                    <td>{{ $row->pda_range }}</td>
                                    <td>{{ $row->tda_range }}</td>
                                    <td>{{ $row->no_eng }}</td>
                                    <td>{{ $row->neighborhood ?: __('multilingual.area_productivity_reports.labels.not_available') }}</td>
                                    <td>{{ $row->municipalitie ?: __('multilingual.area_productivity_reports.labels.not_available') }}</td>
                                    <td>{{ $row->governorate ?: __('multilingual.area_productivity_reports.labels.not_available') }}</td>
                                    <td>{{ __($sector_key) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">
                                        {{ __('multilingual.area_productivity_reports.labels.empty') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="border-top-2">
                            <tr class="fw-bold bg-light">
                                <td class="text-success fs-5">{{ $summary['total_records'] }}</td>
                                <td class="text-primary">{{ $summary['cra'] }}</td>
                                <td class="text-warning">{{ $summary['pda'] }}</td>
                                <td class="text-danger">{{ $summary['tda'] }}</td>
                                <td>{{ $summary['engineers'] }}</td>
                                <td colspan="4" class="text-end">
                                    {{ __('multilingual.area_productivity_reports.labels.grand_totals') }}
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
        $(document).ready(function () {
            $('.area-report-select').select2({
                allowClear: true,
                width: '100%'
            });

            $('#kt_daterangepicker').daterangepicker({
                startDate: moment(@json($start_date)),
                endDate: moment(@json($end_date)),
                locale: {
                    format: 'MM/DD/YYYY'
                },
                ranges: {
                    @if (app()->getLocale() === 'ar')
                        'آخر 30 يوم': [moment().subtract(29, 'days'), moment()],
                        'هذا الشهر': [moment().startOf('month'), moment().endOf('month')]
                    @else
                        'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                        'This Month': [moment().startOf('month'), moment().endOf('month')]
                    @endif
                }
            }, function (start, end) {
                $('#kt_daterangepicker').val(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            });

            $('#area_productivity_table').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                language: {
                    url: @json(app()->getLocale() === 'ar' ? '//cdn.datatables.net/plug-ins/1.13.4/i18n/ar.json' : '//cdn.datatables.net/plug-ins/1.13.4/i18n/en-GB.json')
                }
            });
        });
    </script>
@endsection
