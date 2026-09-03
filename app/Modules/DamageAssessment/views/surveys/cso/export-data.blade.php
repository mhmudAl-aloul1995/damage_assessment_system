@extends('layouts.app')

@section('title', 'تصدير بيانات CSO')
@section('pageName', 'تصدير بيانات CSO')

@section('content')
    <style>
        .cso-export-page {
            max-width: 1500px;
            margin-inline: auto;
        }

        .cso-export-hero,
        .cso-export-bar,
        .cso-export-section {
            border: 1px solid var(--bs-gray-200);
            border-radius: .75rem;
            background: #fff;
            box-shadow: 0 8px 22px rgba(15, 23, 42, .05);
        }

        .cso-export-hero,
        .cso-export-bar,
        .cso-export-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .cso-export-hero {
            margin-bottom: 1.25rem;
            padding: 1.5rem;
        }

        .cso-export-bar {
            position: sticky;
            top: 78px;
            z-index: 7;
            margin-bottom: 1.25rem;
            padding: 1rem;
            background: rgba(255, 255, 255, .96);
            backdrop-filter: blur(8px);
        }

        .cso-export-section {
            margin-bottom: 1.25rem;
        }

        .cso-export-section-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--bs-gray-200);
        }

        .cso-export-section-body {
            padding: 1.25rem;
        }

        .cso-export-stat,
        .cso-export-field-card {
            height: 100%;
            padding: 1rem;
            border: 1px solid var(--bs-gray-200);
            border-radius: .65rem;
            background: var(--bs-gray-100);
        }

        .cso-export-field-card {
            background: #fff;
        }

        .cso-export-format {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }

        .cso-export-format .btn {
            min-height: 54px;
            white-space: normal;
        }

        .cso-export-column-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem;
        }

        .cso-export-column-list {
            max-height: 620px;
            overflow: auto;
            padding-inline-end: .35rem;
        }

        .cso-export-column-grid .form-check {
            min-height: 56px;
            margin: 0;
            border: 1px solid var(--bs-gray-200);
            border-radius: .55rem;
            background: var(--bs-gray-100);
        }

        @media (max-width: 991.98px) {
            .cso-export-hero,
            .cso-export-bar,
            .cso-export-section-header {
                align-items: stretch;
                flex-direction: column;
            }

            .cso-export-bar {
                position: static;
            }

            .cso-export-format,
            .cso-export-column-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="cso-export-page py-4">
        <div class="cso-export-hero">
            <div>
                <div class="d-inline-flex align-items-center gap-2 text-primary fw-bold fs-7 mb-2">
                    <i class="ki-duotone ki-abstract-26 fs-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    CSO
                </div>
                <h2 class="fw-bold text-gray-900 mb-1">تصدير بيانات CSO</h2>
                <div class="text-muted fw-semibold">Excel يتضمن ثلاث Sheets. CSV وPDF يعرضان Survey والمنظمات والوحدات تحت بعض.</div>
            </div>

            <div class="d-flex align-items-center flex-wrap gap-2">
                <a href="{{ route('cso-surveys.index') }}" class="btn btn-light">عرض الجدول</a>
                <span class="badge badge-light-primary">الاستبيانات: {{ $summary['total_surveys'] }}</span>
                <span class="badge badge-light-success">المنظمات: {{ $summary['total_organizations'] }}</span>
                <span class="badge badge-light-info">الوحدات: {{ $summary['total_units'] }}</span>
                <span class="badge badge-light-danger">متضررة: {{ $summary['damaged_buildings'] }}</span>
            </div>
        </div>

        <form id="csoExportForm">
            <div class="cso-export-bar">
                <div class="fw-semibold text-gray-700">خيارات التصدير</div>
                <div class="d-flex align-items-center justify-content-end flex-wrap gap-2">
                    <button type="button" class="btn btn-light" id="resetCsoExportFilters">إعادة ضبط</button>
                    <button type="button" class="btn btn-light-primary cso-export-submit" data-format="xlsx">Excel</button>
                    <button type="button" class="btn btn-light-success cso-export-submit" data-format="csv">CSV</button>
                    <button type="button" class="btn btn-light-danger cso-export-submit" data-format="pdf">PDF</button>
                </div>
            </div>

            <div class="row g-5 mb-5">
                <div class="col-md-3">
                    <div class="cso-export-stat">
                        <div class="text-muted fs-7 mb-2">Total Surveys</div>
                        <div class="fs-2 fw-bold text-gray-900">{{ $summary['total_surveys'] }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="cso-export-stat">
                        <div class="text-muted fs-7 mb-2">Organizations</div>
                        <div class="fs-2 fw-bold text-primary">{{ $summary['total_organizations'] }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="cso-export-stat">
                        <div class="text-muted fs-7 mb-2">Units</div>
                        <div class="fs-2 fw-bold text-info">{{ $summary['total_units'] }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="cso-export-stat">
                        <div class="text-muted fs-7 mb-2">Damaged Buildings</div>
                        <div class="fs-2 fw-bold text-danger">{{ $summary['damaged_buildings'] }}</div>
                    </div>
                </div>
            </div>

            <div class="cso-export-section">
                <div class="cso-export-section-header">
                    <div>
                        <h3 class="fw-bold mb-1">الفلاتر الأساسية</h3>
                        <div class="text-muted fs-7">هذه الفلاتر تطبق على كل صيغ التصدير.</div>
                    </div>
                </div>
                <div class="cso-export-section-body">
                    <div class="row g-5">
                        <div class="col-lg-3">
                            <label class="form-label fw-bold" for="export_filter_municipalitie">Municipality</label>
                            <select id="export_filter_municipalitie" name="municipalitie[]" class="form-select form-select-solid cso-export-select2" data-placeholder="Select municipality" multiple>
                                @foreach ($filterOptions['municipalities'] as $municipality)
                                    <option value="{{ $municipality }}">{{ $municipality }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3">
                            <label class="form-label fw-bold" for="export_filter_neighborhood">Neighborhood</label>
                            <select id="export_filter_neighborhood" name="neighborhood[]" class="form-select form-select-solid cso-export-select2" data-placeholder="Select neighborhood" multiple>
                                @foreach ($filterOptions['neighborhoods'] as $neighborhood)
                                    <option value="{{ $neighborhood }}">{{ $neighborhood }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3">
                            <label class="form-label fw-bold" for="export_filter_assignedto">Researcher</label>
                            <select id="export_filter_assignedto" name="assignedto[]" class="form-select form-select-solid cso-export-select2" data-placeholder="Select researcher" multiple>
                                @foreach ($filterOptions['researchers'] as $researcher)
                                    <option value="{{ $researcher }}">{{ $researcher }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3">
                            <label class="form-label fw-bold" for="export_filter_building_damage_status">Damage Status</label>
                            <select id="export_filter_building_damage_status" name="building_damage_status[]" class="form-select form-select-solid cso-export-select2" data-placeholder="Select damage status" multiple>
                                @foreach ($filterOptions['damageStatuses'] as $status)
                                    <option value="{{ $status['value'] }}">{{ $status['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3">
                            <label class="form-label fw-bold" for="export_filter_operational_status">Operational Status</label>
                            <select id="export_filter_operational_status" name="operational_status[]" class="form-select form-select-solid cso-export-select2" data-placeholder="Select operational status" multiple>
                                @foreach ($filterOptions['operationalStatuses'] as $status)
                                    <option value="{{ $status }}">{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3">
                            <label class="form-label fw-bold" for="export_filter_search">Search</label>
                            <input id="export_filter_search" name="q" type="text" class="form-control form-control-solid" placeholder="Search organization, building, ObjectID">
                        </div>

                        <div class="col-lg-3">
                            <label class="form-label fw-bold" for="export_filter_from_date">From Date</label>
                            <input id="export_filter_from_date" name="from_date" type="date" class="form-control form-control-solid" value="{{ $filterOptions['min_creationdate'] }}">
                        </div>

                        <div class="col-lg-3">
                            <label class="form-label fw-bold" for="export_filter_to_date">To Date</label>
                            <input id="export_filter_to_date" name="to_date" type="date" class="form-control form-control-solid" value="{{ $filterOptions['max_creationdate'] }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="cso-export-section">
                <div class="cso-export-section-header">
                    <div>
                        <h3 class="fw-bold mb-1">اختيار الحقول</h3>
                        <div class="text-muted fs-7">حدد الأعمدة التي تريد ظهورها. في CSV/PDF تظهر القيم تحت بعض كصفوف حقول.</div>
                    </div>
                </div>
                <div class="cso-export-section-body">
                    <div class="row g-5">
                        @foreach ([
                            'cso_survey_columns[]' => ['title' => 'Sheet Survey', 'groups' => $exportColumnGroups['surveys'], 'color' => 'primary'],
                            'cso_organization_columns[]' => ['title' => 'Sheet CSO Organizations', 'groups' => $exportColumnGroups['organizations'], 'color' => 'success'],
                            'cso_unit_columns[]' => ['title' => 'Sheet Unit Information', 'groups' => $exportColumnGroups['units'], 'color' => 'info'],
                        ] as $inputName => $columnConfig)
                            <div class="col-xl-4">
                                <div class="cso-export-field-card">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                                        <h4 class="fw-bold mb-0">{{ $columnConfig['title'] }}</h4>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-light-{{ $columnConfig['color'] }}" data-toggle-cso-columns="{{ $inputName }}" data-checked="1">تحديد الكل</button>
                                            <button type="button" class="btn btn-sm btn-light" data-toggle-cso-columns="{{ $inputName }}" data-checked="0">إلغاء الكل</button>
                                        </div>
                                    </div>
                                    <div class="cso-export-column-list">
                                        @foreach ($columnConfig['groups'] as $group => $columns)
                                            <div class="mb-5">
                                                <div class="fw-bold text-{{ $columnConfig['color'] }} border-bottom pb-2 mb-3">{{ $group }}</div>
                                                <div class="cso-export-column-grid">
                                                    @foreach ($columns as $column => $label)
                                                        <label class="form-check form-check-custom form-check-solid p-3">
                                                            <input class="form-check-input" type="checkbox" name="{{ $inputName }}" value="{{ $column }}" checked>
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
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="cso-export-section">
                <div class="cso-export-section-body">
                    <label class="form-label fw-bold d-block mb-3">صيغة التصدير</label>
                    <div class="cso-export-format">
                        <button type="button" class="btn btn-light-primary cso-export-submit" data-format="xlsx">Excel بثلاث Sheets</button>
                        <button type="button" class="btn btn-light-success cso-export-submit" data-format="csv">CSV تحت بعض</button>
                        <button type="button" class="btn btn-light-danger cso-export-submit" data-format="pdf">PDF تحت بعض</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const exportRouteTemplate = @json(route('cso-surveys.export', ['format' => '__FORMAT__']));

            $('.cso-export-select2').each(function () {
                $(this).select2({
                    placeholder: $(this).data('placeholder') || 'Select an option',
                    allowClear: true,
                    closeOnSelect: false,
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
                appendField(query, 'building_damage_status[]', $('#export_filter_building_damage_status').val() || []);
                appendField(query, 'operational_status[]', $('#export_filter_operational_status').val() || []);
                appendField(query, 'q', $('#export_filter_search').val());
                appendField(query, 'from_date', $('#export_filter_from_date').val());
                appendField(query, 'to_date', $('#export_filter_to_date').val());

                appendColumnSelection(query, 'cso_survey_columns[]', 'cso_survey_columns');
                appendColumnSelection(query, 'cso_organization_columns[]', 'cso_organization_columns');
                appendColumnSelection(query, 'cso_unit_columns[]', 'cso_unit_columns');

                return query;
            };

            $('.cso-export-submit').on('click', function () {
                const format = $(this).data('format');
                const query = buildExportQuery();
                const url = exportRouteTemplate.replace('__FORMAT__', format);

                window.location.href = url + (query.toString() ? '?' + query.toString() : '');
            });

            $('#resetCsoExportFilters').on('click', function () {
                $('#csoExportForm').find('input[type="text"], input[type="date"]').val('');
                $('.cso-export-select2').val(null).trigger('change');
                $('input[name="cso_survey_columns[]"], input[name="cso_organization_columns[]"], input[name="cso_unit_columns[]"]').prop('checked', true);
            });

            $('[data-toggle-cso-columns]').on('click', function () {
                const inputName = $(this).data('toggle-cso-columns');
                const checked = String($(this).data('checked')) === '1';

                $('input[name="' + inputName + '"]').prop('checked', checked);
            });
        });
    </script>
@endsection
