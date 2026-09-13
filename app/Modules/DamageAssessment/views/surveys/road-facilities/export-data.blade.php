@extends('layouts.app')

@section('title', 'تصدير بيانات الطرق')
@section('pageName', 'تصدير بيانات الطرق')

@section('content')
    <style>
        .road-export-page {
            max-width: 1500px;
            margin-inline: auto;
        }

        .road-export-hero,
        .road-export-bar,
        .road-export-section {
            border: 1px solid var(--bs-gray-200);
            border-radius: .75rem;
            background: #fff;
            box-shadow: 0 8px 22px rgba(15, 23, 42, .05);
        }

        .road-export-hero,
        .road-export-bar,
        .road-export-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .road-export-hero {
            margin-bottom: 1.25rem;
            padding: 1.5rem;
        }

        .road-export-bar {
            position: sticky;
            top: 78px;
            z-index: 7;
            margin-bottom: 1.25rem;
            padding: 1rem;
            background: rgba(255, 255, 255, .96);
            backdrop-filter: blur(8px);
        }

        .road-export-section {
            margin-bottom: 1.25rem;
        }

        .road-export-section-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--bs-gray-200);
        }

        .road-export-section-body {
            padding: 1.25rem;
        }

        .road-export-stat,
        .road-export-field-card {
            height: 100%;
            padding: 1rem;
            border: 1px solid var(--bs-gray-200);
            border-radius: .65rem;
            background: var(--bs-gray-100);
        }

        .road-export-field-card {
            background: #fff;
        }

        .road-export-format {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }

        .road-export-format .btn {
            min-height: 54px;
            white-space: normal;
        }

        .road-export-column-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem;
        }

        .road-export-column-list {
            max-height: 620px;
            overflow: auto;
            padding-inline-end: .35rem;
        }

        .road-export-column-grid .form-check {
            min-height: 56px;
            margin: 0;
            border: 1px solid var(--bs-gray-200);
            border-radius: .55rem;
            background: var(--bs-gray-100);
        }

        @media (max-width: 991.98px) {
            .road-export-hero,
            .road-export-bar,
            .road-export-section-header {
                align-items: stretch;
                flex-direction: column;
            }

            .road-export-bar {
                position: static;
            }

            .road-export-format,
            .road-export-column-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="road-export-page py-4">
        <div class="road-export-hero">
            <div>
                <div class="d-inline-flex align-items-center gap-2 text-primary fw-bold fs-7 mb-2">
                    <i class="ki-duotone ki-route fs-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    الطرق
                </div>
                <h2 class="fw-bold text-gray-900 mb-1">تصدير بيانات الطرق</h2>
                <div class="text-muted fw-semibold">حدد الفلاتر المطلوبة ثم اختر الحقول. ملف Excel يتضمن Sheet للطرق وSheet لبنود جدول الكميات.</div>
            </div>

            <div class="d-flex align-items-center flex-wrap gap-2">
                <a href="{{ route('road-facilities.index') }}" class="btn btn-light">عرض الجدول</a>
                <span class="badge badge-light-primary">الطرق: {{ $summary['total_surveys'] }}</span>
                <span class="badge badge-light-success">البنود: {{ $summary['total_items'] }}</span>
                <span class="badge badge-light-danger">متضررة: {{ $summary['damaged_roads'] }}</span>
            </div>
        </div>

        <form id="roadExportForm">
            <div class="road-export-bar">
                <div class="fw-semibold text-gray-700">خيارات التصدير</div>
                <div class="d-flex align-items-center justify-content-end flex-wrap gap-2">
                    <button type="button" class="btn btn-light" id="resetRoadExportFilters">إعادة ضبط</button>
                    <button type="button" class="btn btn-light-primary road-export-submit" data-format="xlsx">Excel</button>
                    <button type="button" class="btn btn-light-success road-export-submit" data-format="csv">CSV</button>
                    <button type="button" class="btn btn-light-danger road-export-submit" data-format="pdf">PDF</button>
                </div>
            </div>

            <div class="row g-5 mb-5">
                <div class="col-md-4">
                    <div class="road-export-stat">
                        <div class="text-muted fs-7 mb-2">{{ __('multilingual.road_facilities_page.total_surveys') }}</div>
                        <div class="fs-2 fw-bold text-gray-900">{{ $summary['total_surveys'] }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="road-export-stat">
                        <div class="text-muted fs-7 mb-2">{{ __('multilingual.road_facilities_page.repeated_items') }}</div>
                        <div class="fs-2 fw-bold text-primary">{{ $summary['total_items'] }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="road-export-stat">
                        <div class="text-muted fs-7 mb-2">{{ __('multilingual.road_facilities_page.damaged_roads') }}</div>
                        <div class="fs-2 fw-bold text-danger">{{ $summary['damaged_roads'] }}</div>
                    </div>
                </div>
            </div>

            <div class="road-export-section">
                <div class="road-export-section-header">
                    <div>
                        <h3 class="fw-bold mb-1">الفلاتر الأساسية</h3>
                        <div class="text-muted fs-7">هذه الفلاتر تطبق على كل صيغ التصدير.</div>
                    </div>
                </div>
                <div class="road-export-section-body">
                    <div class="row g-5">
                        <div class="col-lg-4">
                            <label class="form-label fw-bold" for="export_filter_municipalitie">{{ __('multilingual.road_facilities_page.municipality') }}</label>
                            <select id="export_filter_municipalitie" name="municipalitie[]" class="form-select form-select-solid road-export-select2" data-placeholder="{{ __('multilingual.road_facilities_page.select_municipality') }}" multiple>
                                @foreach ($filterOptions['municipalities'] as $municipality)
                                    <option value="{{ $municipality }}">{{ $municipality }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label fw-bold" for="export_filter_neighborhood">{{ __('multilingual.road_facilities_page.neighborhood') }}</label>
                            <select id="export_filter_neighborhood" name="neighborhood[]" class="form-select form-select-solid road-export-select2" data-placeholder="{{ __('multilingual.road_facilities_page.select_neighborhood') }}" multiple>
                                @foreach ($filterOptions['neighborhoods'] as $neighborhood)
                                    <option value="{{ $neighborhood }}">{{ $neighborhood }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label fw-bold" for="export_filter_assignedto">{{ __('multilingual.road_facilities_page.researcher') }}</label>
                            <select id="export_filter_assignedto" name="assignedto[]" class="form-select form-select-solid road-export-select2" data-placeholder="{{ __('multilingual.road_facilities_page.select_researcher') }}" multiple>
                                @foreach ($filterOptions['researchers'] as $researcher)
                                    <option value="{{ $researcher }}">{{ $researcher }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label fw-bold" for="export_filter_search">{{ __('multilingual.road_facilities_page.search') }}</label>
                            <input id="export_filter_search" name="search" type="text" class="form-control form-control-solid" placeholder="{{ __('multilingual.road_facilities_page.search_placeholder') }}">
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label fw-bold" for="export_filter_from_date">{{ __('multilingual.road_facilities_page.from_date') }}</label>
                            <input id="export_filter_from_date" name="from_date" type="date" class="form-control form-control-solid" value="{{ $filterOptions['min_submissiondate'] }}">
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label fw-bold" for="export_filter_to_date">{{ __('multilingual.road_facilities_page.to_date') }}</label>
                            <input id="export_filter_to_date" name="to_date" type="date" class="form-control form-control-solid" value="{{ $filterOptions['max_submissiondate'] }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="road-export-section">
                <div class="road-export-section-header">
                    <div>
                        <h3 class="fw-bold mb-1">فلاتر الاستبيان</h3>
                        <div class="text-muted fs-7">اختر قيمًا إضافية من حقول استبيان الطرق.</div>
                    </div>
                </div>
                <div class="road-export-section-body">
                    <div class="row g-5">
                        @foreach ($filterGroups as $groupName => $items)
                            <div class="col-lg-4">
                                <label class="form-label fw-bold" for="export_filter_{{ $groupName }}">{{ str($groupName)->replace('_', ' ')->title() }}</label>
                                <select id="export_filter_{{ $groupName }}" name="filters[{{ $groupName }}][]" class="form-select form-select-solid road-export-select2" data-placeholder="{{ __('multilingual.road_facilities_page.select_filter', ['label' => str($groupName)->replace('_', ' ')->lower()]) }}" multiple>
                                    @foreach ($items as $item)
                                        <option value="{{ $item->name }}">{{ $item->label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="road-export-section">
                <div class="road-export-section-header">
                    <div>
                        <h3 class="fw-bold mb-1">اختيار الحقول</h3>
                        <div class="text-muted fs-7">حدد الأعمدة التي تريد ظهورها في ملف التصدير.</div>
                    </div>
                </div>
                <div class="road-export-section-body">
                    <div class="row g-5">
                        <div class="col-lg-6">
                            <div class="road-export-field-card">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                                    <h4 class="fw-bold mb-0">Sheet الطرق</h4>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-light-primary" data-toggle-road-columns="road_facility_columns[]" data-checked="1">تحديد الكل</button>
                                        <button type="button" class="btn btn-sm btn-light" data-toggle-road-columns="road_facility_columns[]" data-checked="0">إلغاء الكل</button>
                                    </div>
                                </div>
                                <div class="road-export-column-list">
                                    @foreach ($exportColumnGroups['roads'] as $group => $columns)
                                        <div class="mb-5">
                                            <div class="fw-bold text-primary border-bottom pb-2 mb-3">{{ $group }}</div>
                                            <div class="road-export-column-grid">
                                                @foreach ($columns as $column => $label)
                                                    <label class="form-check form-check-custom form-check-solid p-3">
                                                        <input class="form-check-input" type="checkbox" name="road_facility_columns[]" value="{{ $column }}" checked>
                                                        <span class="form-check-label ms-3">
                                                            <strong class="d-block">{{ $label }}</strong>
                                                            <small class="text-muted">{{ $column }}</small>
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="road-export-field-card">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                                    <h4 class="fw-bold mb-0">Sheet البنود</h4>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-light-success" data-toggle-road-columns="road_facility_item_columns[]" data-checked="1">تحديد الكل</button>
                                        <button type="button" class="btn btn-sm btn-light" data-toggle-road-columns="road_facility_item_columns[]" data-checked="0">إلغاء الكل</button>
                                    </div>
                                </div>
                                <div class="road-export-column-list">
                                    @foreach ($exportColumnGroups['items'] as $group => $columns)
                                        <div class="mb-5">
                                            <div class="fw-bold text-success border-bottom pb-2 mb-3">{{ $group }}</div>
                                            <div class="road-export-column-grid">
                                                @foreach ($columns as $column => $label)
                                                    <label class="form-check form-check-custom form-check-solid p-3">
                                                        <input class="form-check-input" type="checkbox" name="road_facility_item_columns[]" value="{{ $column }}" checked>
                                                        <span class="form-check-label ms-3">
                                                            <strong class="d-block">{{ $label }}</strong>
                                                            <small class="text-muted">{{ $column }}</small>
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="road-export-section">
                <div class="road-export-section-body">
                    <label class="form-label fw-bold d-block mb-3">صيغة التصدير</label>
                    <div class="road-export-format">
                        <button type="button" class="btn btn-light-primary road-export-submit" data-format="xlsx">Excel مع الطرق والبنود</button>
                        <button type="button" class="btn btn-light-success road-export-submit" data-format="csv">CSV للطرق</button>
                        <button type="button" class="btn btn-light-danger road-export-submit" data-format="pdf">PDF للطرق</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const exportRouteTemplate = @json(route('road-facilities.export', ['format' => '__FORMAT__']));

            $('.road-export-select2').each(function () {
                $(this).select2({
                    placeholder: $(this).data('placeholder') || 'Select an option',
                    allowClear: true,
                    closeOnSelect: false,
                    dir: 'rtl',
                    width: '100%'
                });
            });

            const appendField = function (query, name, value) {
                if (Array.isArray(value)) {
                    value.filter(Boolean).forEach(function (item) {
                        query.append(name, item);
                    });
                    return;
                }

                if (value) {
                    query.set(name, value);
                }
            };

            const appendColumnSelection = function (query, inputName, queryName) {
                const inputs = $('input[name="' + inputName + '"]');
                const checkedInputs = inputs.filter(':checked');

                if (checkedInputs.length === inputs.length) {
                    return;
                }

                if (checkedInputs.length > inputs.length / 2) {
                    query.set(queryName + '_mode', 'except');

                    inputs.not(':checked').each(function () {
                        query.append(queryName + '_excluded[]', this.value);
                    });

                    return;
                }

                checkedInputs.each(function () {
                    query.append(queryName + '[]', this.value);
                });
            };

            const buildExportQuery = function () {
                const query = new URLSearchParams();

                appendField(query, 'municipalitie[]', $('#export_filter_municipalitie').val() || []);
                appendField(query, 'neighborhood[]', $('#export_filter_neighborhood').val() || []);
                appendField(query, 'assignedto[]', $('#export_filter_assignedto').val() || []);
                appendField(query, 'search', $('#export_filter_search').val());
                appendField(query, 'from_date', $('#export_filter_from_date').val());
                appendField(query, 'to_date', $('#export_filter_to_date').val());

                $('[name^="filters["]').each(function () {
                    const values = $(this).val() || [];
                    const name = $(this).attr('name');

                    appendField(query, name, values);
                });

                appendColumnSelection(query, 'road_facility_columns[]', 'road_facility_columns');
                appendColumnSelection(query, 'road_facility_item_columns[]', 'road_facility_item_columns');

                return query;
            };

            $('.road-export-submit').on('click', function () {
                const format = $(this).data('format');
                const query = buildExportQuery();
                const url = exportRouteTemplate.replace('__FORMAT__', format);

                window.location.href = url + (query.toString() ? '?' + query.toString() : '');
            });

            $('#resetRoadExportFilters').on('click', function () {
                $('#roadExportForm').find('input[type="text"], input[type="date"]').val('');
                $('.road-export-select2').val(null).trigger('change');
                $('input[name="road_facility_columns[]"], input[name="road_facility_item_columns[]"]').prop('checked', true);
            });

            $('[data-toggle-road-columns]').on('click', function () {
                const inputName = $(this).data('toggle-road-columns');
                const checked = String($(this).data('checked')) === '1';

                $('input[name="' + inputName + '"]').prop('checked', checked);
            });
        });
    </script>
@endsection
