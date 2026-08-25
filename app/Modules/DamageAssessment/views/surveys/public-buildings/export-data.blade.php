@extends('layouts.app')

@section('title', 'تصدير بيانات المباني العامة')
@section('pageName', 'تصدير بيانات المباني العامة')

@section('content')
    <style>
        .public-building-export-page {
            max-width: 1500px;
            margin-inline: auto;
        }

        .public-building-export-hero,
        .public-building-export-bar,
        .public-building-export-section {
            border: 1px solid var(--bs-gray-200);
            border-radius: .75rem;
            background: #fff;
            box-shadow: 0 8px 22px rgba(15, 23, 42, .05);
        }

        .public-building-export-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
            padding: 1.5rem;
        }

        .public-building-export-bar {
            position: sticky;
            top: 78px;
            z-index: 7;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
            padding: 1rem;
            background: rgba(255, 255, 255, .96);
            backdrop-filter: blur(8px);
        }

        .public-building-export-section {
            margin-bottom: 1.25rem;
        }

        .public-building-export-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--bs-gray-200);
        }

        .public-building-export-section-body {
            padding: 1.25rem;
        }

        .public-building-export-stat {
            height: 100%;
            padding: 1rem;
            border: 1px solid var(--bs-gray-200);
            border-radius: .65rem;
            background: var(--bs-gray-100);
        }

        .public-building-export-format {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }

        .public-building-export-format .btn {
            min-height: 54px;
            white-space: normal;
        }

        .public-building-export-field-card {
            height: 100%;
            padding: 1rem;
            border: 1px solid var(--bs-gray-200);
            border-radius: .65rem;
            background: #fff;
        }

        .public-building-export-column-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem;
        }

        .public-building-export-column-grid .form-check {
            min-height: 56px;
            margin: 0;
            border: 1px solid var(--bs-gray-200);
            border-radius: .55rem;
            background: var(--bs-gray-100);
        }

        @media (max-width: 991.98px) {
            .public-building-export-hero,
            .public-building-export-bar,
            .public-building-export-section-header {
                align-items: stretch;
                flex-direction: column;
            }

            .public-building-export-bar {
                position: static;
            }

            .public-building-export-format {
                grid-template-columns: 1fr;
            }

            .public-building-export-column-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="public-building-export-page py-4">
        <div class="public-building-export-hero">
            <div>
                <div class="d-inline-flex align-items-center gap-2 text-primary fw-bold fs-7 mb-2">
                    <i class="ki-duotone ki-office-bag fs-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                    </i>
                    المباني العامة
                </div>
                <h2 class="fw-bold text-gray-900 mb-1">تصدير بيانات المباني العامة</h2>
                <div class="text-muted fw-semibold">حدد الفلاتر المطلوبة ثم اختر صيغة الملف. ملف Excel يتضمن Sheet للمباني وSheet للوحدات المرتبطة.</div>
            </div>

            <div class="d-flex align-items-center flex-wrap gap-2">
                <a href="{{ route('public-buildings.index') }}" class="btn btn-light">عرض الجدول</a>
                <span class="badge badge-light-primary">المباني: {{ $summary['total_surveys'] }}</span>
                <span class="badge badge-light-success">الوحدات: {{ $summary['total_units'] }}</span>
                <span class="badge badge-light-danger">متضررة: {{ $summary['damaged_buildings'] }}</span>
            </div>
        </div>

        <form id="publicBuildingExportForm">
            <div class="public-building-export-bar">
                <div class="fw-semibold text-gray-700">خيارات التصدير</div>
                <div class="d-flex align-items-center justify-content-end flex-wrap gap-2">
                    <button type="button" class="btn btn-light" id="resetPublicBuildingExportFilters">
                        إعادة ضبط
                    </button>
                    <button type="button" class="btn btn-light-primary public-building-export-submit" data-format="xlsx">
                        Excel
                    </button>
                    <button type="button" class="btn btn-light-success public-building-export-submit" data-format="csv">
                        CSV
                    </button>
                    <button type="button" class="btn btn-light-danger public-building-export-submit" data-format="pdf">
                        PDF
                    </button>
                </div>
            </div>

            <div class="row g-5 mb-5">
                <div class="col-md-4">
                    <div class="public-building-export-stat">
                        <div class="text-muted fs-7 mb-2">{{ __('multilingual.public_buildings_page.total_surveys') }}</div>
                        <div class="fs-2 fw-bold text-gray-900">{{ $summary['total_surveys'] }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="public-building-export-stat">
                        <div class="text-muted fs-7 mb-2">{{ __('multilingual.public_buildings_page.repeated_units') }}</div>
                        <div class="fs-2 fw-bold text-primary">{{ $summary['total_units'] }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="public-building-export-stat">
                        <div class="text-muted fs-7 mb-2">{{ __('multilingual.public_buildings_page.damaged_buildings') }}</div>
                        <div class="fs-2 fw-bold text-danger">{{ $summary['damaged_buildings'] }}</div>
                    </div>
                </div>
            </div>

            <div class="public-building-export-section">
                <div class="public-building-export-section-header">
                    <div>
                        <h3 class="fw-bold mb-1">الفلاتر الأساسية</h3>
                        <div class="text-muted fs-7">هذه الفلاتر تطبق على كل صيغ التصدير.</div>
                    </div>
                </div>
                <div class="public-building-export-section-body">
                    <div class="row g-5">
                        <div class="col-lg-4">
                            <label class="form-label fw-bold" for="export_filter_municipalitie">{{ __('multilingual.public_buildings_page.municipality') }}</label>
                            <select id="export_filter_municipalitie" name="municipalitie[]" class="form-select form-select-solid public-building-export-select2" data-placeholder="{{ __('multilingual.public_buildings_page.select_municipality') }}" multiple>
                                @foreach ($filterOptions['municipalities'] as $municipality)
                                    <option value="{{ $municipality }}">{{ $municipality }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label fw-bold" for="export_filter_neighborhood">{{ __('multilingual.public_buildings_page.neighborhood') }}</label>
                            <select id="export_filter_neighborhood" name="neighborhood[]" class="form-select form-select-solid public-building-export-select2" data-placeholder="{{ __('multilingual.public_buildings_page.select_neighborhood') }}" multiple>
                                @foreach ($filterOptions['neighborhoods'] as $neighborhood)
                                    <option value="{{ $neighborhood }}">{{ $neighborhood }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label fw-bold" for="export_filter_assignedto">{{ __('multilingual.public_buildings_page.researcher') }}</label>
                            <select id="export_filter_assignedto" name="assignedto[]" class="form-select form-select-solid public-building-export-select2" data-placeholder="{{ __('multilingual.public_buildings_page.select_researcher') }}" multiple>
                                @foreach ($filterOptions['researchers'] as $researcher)
                                    <option value="{{ $researcher }}">{{ $researcher }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label fw-bold" for="export_filter_search">{{ __('multilingual.public_buildings_page.search') }}</label>
                            <input id="export_filter_search" name="search" type="text" class="form-control form-control-solid" placeholder="{{ __('multilingual.public_buildings_page.search_placeholder') }}">
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label fw-bold" for="export_filter_from_date">{{ __('multilingual.public_buildings_page.from_date') }}</label>
                            <input id="export_filter_from_date" name="from_date" type="date" class="form-control form-control-solid" value="{{ $filterOptions['min_damage_date'] }}">
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label fw-bold" for="export_filter_to_date">{{ __('multilingual.public_buildings_page.to_date') }}</label>
                            <input id="export_filter_to_date" name="to_date" type="date" class="form-control form-control-solid" value="{{ $filterOptions['max_damage_date'] }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="public-building-export-section">
                <div class="public-building-export-section-header">
                    <div>
                        <h3 class="fw-bold mb-1">فلاتر الاستبيان</h3>
                        <div class="text-muted fs-7">اختر قيمًا إضافية من حقول استبيان المباني العامة.</div>
                    </div>
                </div>
                <div class="public-building-export-section-body">
                    <div class="row g-5">
                        @foreach ($filterGroups as $groupName => $items)
                            <div class="col-lg-4">
                                <label class="form-label fw-bold" for="export_filter_{{ $groupName }}">{{ str($groupName)->replace('_', ' ')->title() }}</label>
                                <select id="export_filter_{{ $groupName }}" name="filters[{{ $groupName }}][]" class="form-select form-select-solid public-building-export-select2" data-placeholder="{{ __('multilingual.public_buildings_page.select_filter', ['label' => str($groupName)->replace('_', ' ')->lower()]) }}" multiple>
                                    @foreach ($items as $item)
                                        <option value="{{ $item->name }}">{{ $item->label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="public-building-export-section">
                <div class="public-building-export-section-header">
                    <div>
                        <h3 class="fw-bold mb-1">اختيار الحقول</h3>
                        <div class="text-muted fs-7">حدد الأعمدة التي تريد ظهورها في ملف التصدير.</div>
                    </div>
                </div>
                <div class="public-building-export-section-body">
                    <div class="row g-5">
                        <div class="col-lg-6">
                            <div class="public-building-export-field-card">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                                    <h4 class="fw-bold mb-0">Sheet المباني</h4>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-light-primary" data-toggle-public-building-columns="public_building_columns[]" data-checked="1">تحديد الكل</button>
                                        <button type="button" class="btn btn-sm btn-light" data-toggle-public-building-columns="public_building_columns[]" data-checked="0">إلغاء الكل</button>
                                    </div>
                                </div>
                                <div class="public-building-export-column-grid">
                                    @foreach ($exportColumns['buildings'] as $column => $label)
                                        <label class="form-check form-check-custom form-check-solid p-3">
                                            <input class="form-check-input" type="checkbox" name="public_building_columns[]" value="{{ $column }}" checked>
                                            <span class="form-check-label ms-3">
                                                <strong class="d-block">{{ $label }}</strong>
                                                <small class="text-muted">{{ $column }}</small>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="public-building-export-field-card">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                                    <h4 class="fw-bold mb-0">Sheet الوحدات</h4>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-light-success" data-toggle-public-building-columns="public_building_unit_columns[]" data-checked="1">تحديد الكل</button>
                                        <button type="button" class="btn btn-sm btn-light" data-toggle-public-building-columns="public_building_unit_columns[]" data-checked="0">إلغاء الكل</button>
                                    </div>
                                </div>
                                <div class="public-building-export-column-grid">
                                    @foreach ($exportColumns['units'] as $column => $label)
                                        <label class="form-check form-check-custom form-check-solid p-3">
                                            <input class="form-check-input" type="checkbox" name="public_building_unit_columns[]" value="{{ $column }}" checked>
                                            <span class="form-check-label ms-3">
                                                <strong class="d-block">{{ $label }}</strong>
                                                <small class="text-muted">{{ $column }}</small>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="public-building-export-section">
                <div class="public-building-export-section-body">
                    <label class="form-label fw-bold d-block mb-3">صيغة التصدير</label>
                    <div class="public-building-export-format">
                        <button type="button" class="btn btn-light-primary public-building-export-submit" data-format="xlsx">Excel مع المباني والوحدات</button>
                        <button type="button" class="btn btn-light-success public-building-export-submit" data-format="csv">CSV للمباني</button>
                        <button type="button" class="btn btn-light-danger public-building-export-submit" data-format="pdf">PDF للمباني</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const exportRouteTemplate = @json(route('public-buildings.export', ['format' => '__FORMAT__']));

            $('.public-building-export-select2').each(function () {
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

                $('input[name="public_building_columns[]"]:checked').each(function () {
                    query.append('public_building_columns[]', this.value);
                });

                $('input[name="public_building_unit_columns[]"]:checked').each(function () {
                    query.append('public_building_unit_columns[]', this.value);
                });

                return query;
            };

            $('.public-building-export-submit').on('click', function () {
                const format = $(this).data('format');
                const query = buildExportQuery();
                const url = exportRouteTemplate.replace('__FORMAT__', format);

                window.location.href = url + (query.toString() ? '?' + query.toString() : '');
            });

            $('#resetPublicBuildingExportFilters').on('click', function () {
                $('#publicBuildingExportForm').find('input[type="text"], input[type="date"]').val('');
                $('.public-building-export-select2').val(null).trigger('change');
                $('input[name="public_building_columns[]"], input[name="public_building_unit_columns[]"]').prop('checked', true);
            });

            $('[data-toggle-public-building-columns]').on('click', function () {
                const inputName = $(this).data('toggle-public-building-columns');
                const checked = String($(this).data('checked')) === '1';

                $('input[name="' + inputName + '"]').prop('checked', checked);
            });
        });
    </script>
@endsection
