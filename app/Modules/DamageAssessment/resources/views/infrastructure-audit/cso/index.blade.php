@extends('layouts.app')

@section('title', 'تدقيق منظمات المجتمع المدني')
@section('pageName', 'تدقيق منظمات المجتمع المدني')

@section('content')
    @include('damage-assessment::components.sector-navigation', ['sector' => 'cso-surveys'])

    <style>
        .cso-audit-workspace .cso-filter-panel {
            background: var(--bs-gray-100);
            border: 1px solid var(--bs-gray-200);
            border-radius: 16px;
        }
        .cso-audit-workspace .form-control,
        .cso-audit-workspace .select2-selection--single {
            min-height: 44px;
            background-color: var(--bs-body-bg);
            border: 1px solid var(--bs-gray-300);
            border-radius: 8px;
        }
        .cso-audit-workspace .select2-container { max-width: 100%; }
        .cso-audit-workspace .form-label { font-size: 13px; font-weight: 600; }
        .cso-audit-workspace .cso-filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            max-width: 100%;
            padding: 7px 12px;
            border: 1px solid var(--bs-primary);
            border-radius: 24px;
            color: var(--bs-primary);
            background: var(--bs-body-bg);
            font-size: 12px;
            text-align: start;
        }
        .cso-audit-workspace .cso-filter-chip span:first-child { overflow-wrap: anywhere; }
        .cso-audit-workspace .cso-assignment-select { width: 280px; max-width: 100%; }
        .cso-audit-workspace .cso-filter-toggle[aria-expanded="true"] .ki-down { transform: rotate(180deg); }
        @media (max-width: 575.98px) {
            .cso-audit-workspace .cso-assignment-select { width: 100%; }
        }
    </style>

    <div class="card card-flush cso-audit-workspace">
        <div class="card-header border-bottom py-6 align-items-center gap-4">
            <div>
                <div class="d-flex align-items-center flex-wrap gap-3 mb-2">
                    <h2 class="fw-bold mb-0">تدقيق منظمات المجتمع المدني</h2>
                    <span class="badge badge-light-success rounded-pill px-3 py-2">✓ مكتملة ميدانياً</span>
                </div>
                <p class="text-gray-600 mb-0 fs-7">استبيانات مكتملة وجاهزة للتدقيق. حدّد السجلات لإسنادها إلى مدقق.</p>
            </div>
            <button type="button" id="refresh_table_btn" class="btn btn-sm btn-light-primary d-inline-flex align-items-center gap-2">
                <i class="ki-duotone ki-arrows-circle fs-3"><span class="path1"></span><span class="path2"></span></i>
                تحديث النتائج
            </button>
        </div>

        <div class="card-body pt-6">
            <form id="cso_filters_form" class="cso-filter-panel p-5 p-lg-6 mb-6" aria-label="فلاتر تدقيق منظمات المجتمع المدني">
                <div class="d-flex align-items-center gap-2 mb-5">
                    <i class="ki-duotone ki-filter fs-3 text-primary"><span class="path1"></span><span class="path2"></span></i>
                    <h3 class="fs-6 fw-bold mb-0">البحث والتصفية</h3>
                </div>
                <div class="row g-4">
                    <div class="col-md-6 col-xl-3">
                        <label for="filter_objectid" class="form-label text-gray-800">رقم السجل · ObjectID</label>
                        <input id="filter_objectid" type="search" inputmode="numeric" class="form-control audit-filter" placeholder="أدخل رقم السجل" autocomplete="off">
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label for="filter_status" class="form-label text-gray-800">حالة التدقيق</label>
                        <select id="filter_status" class="form-select audit-filter audit-select" data-placeholder="كل حالات التدقيق">
                            <option value="">كل حالات التدقيق</option>
                            @foreach ($filterStatuses as $status)
                                <option value="{{ $status->name }}">{{ $status->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label for="filter_municipalitie" class="form-label text-gray-800">البلدية</label>
                        <select id="filter_municipalitie" class="form-select audit-filter audit-select" data-placeholder="كل البلديات">
                            <option value="">كل البلديات</option>
                            @foreach ($municipalities as $municipality)
                                <option value="{{ $municipality }}">{{ $municipality }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label for="filter_neighborhood" class="form-label text-gray-800">الحي</label>
                        <select id="filter_neighborhood" class="form-select audit-filter audit-select" data-placeholder="كل الأحياء">
                            <option value="">كل الأحياء</option>
                            @foreach ($neighborhoods as $neighborhood)
                                <option value="{{ $neighborhood }}">{{ $neighborhood }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="collapse" id="cso_advanced_filters">
                    <div class="row g-4 mt-2 pt-4">
                        <div class="col-md-6 col-xl-3">
                            <label for="filter_auditor" class="form-label text-gray-800">المدقق</label>
                            <select id="filter_auditor" class="form-select audit-filter audit-select" data-placeholder="كل المدققين">
                                <option value="">كل المدققين</option>
                                @foreach ($engineers as $engineer)
                                    <option value="{{ $engineer->id }}">{{ $engineer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="filter_field_engineer" class="form-label text-gray-800">المهندس الميداني</label>
                            <select id="filter_field_engineer" class="form-select audit-filter audit-select" data-placeholder="كل المهندسين الميدانيين">
                                <option value="">كل المهندسين الميدانيين</option>
                                @foreach ($fieldEngineers as $fieldEngineer)
                                    <option value="{{ $fieldEngineer['value'] }}">{{ $fieldEngineer['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-6">
                            <label for="cso_survey_date_range" class="form-label text-gray-800">نطاق تاريخ الاستبيان</label>
                            <input id="cso_survey_date_range" type="text" class="form-control date-range-picker"
                                data-global-date-range="true" data-from-input="filter_from_date" data-to-input="filter_to_date"
                                placeholder="اختر تاريخ البداية والنهاية" autocomplete="off">
                            <input id="filter_from_date" type="hidden" class="audit-filter">
                            <input id="filter_to_date" type="hidden" class="audit-filter">
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 mt-5">
                    <button type="button" class="btn btn-sm btn-link text-gray-700 cso-filter-toggle d-inline-flex align-items-center gap-2 p-0"
                        data-bs-toggle="collapse" data-bs-target="#cso_advanced_filters" aria-expanded="false" aria-controls="cso_advanced_filters">
                        فلاتر إضافية
                        <span id="advanced_filters_count" class="badge badge-light-primary d-none"></span>
                        <i class="ki-duotone ki-down fs-4"></i>
                    </button>
                    <div class="d-flex flex-wrap gap-3">
                        <button type="button" id="reset_filters_btn" class="btn btn-sm btn-light text-gray-700">مسح الفلاتر</button>
                        <button type="submit" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-2">
                            <i class="ki-duotone ki-magnifier fs-3"><span class="path1"></span><span class="path2"></span></i>
                            تطبيق الفلاتر
                        </button>
                    </div>
                </div>
                <div id="active_filters" class="d-flex flex-wrap gap-2 mt-5 d-none" aria-label="الفلاتر المطبقة" aria-live="polite"></div>
            </form>

            @role('Database Officer|Team Leader -INF|CSO Officer')
                <div id="cso_assignment_bar" class="d-none bg-light-primary border border-primary border-dashed rounded-3 p-4 mb-5">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-4">
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge badge-primary fs-6" id="selected_records_count" aria-live="polite">0</span>
                            <span class="fw-semibold text-gray-800">سجل محدد للإسناد</span>
                            <button type="button" id="clear_selection_btn" class="btn btn-sm btn-link p-0">إلغاء التحديد</button>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-3 flex-grow-1 justify-content-end">
                            <div class="cso-assignment-select">
                                <label for="bulk_assign_engineer" class="visually-hidden">المدقق المسؤول عن السجلات المحددة</label>
                                <select id="bulk_assign_engineer" class="form-select" data-placeholder="اختر المدقق المسؤول">
                                    <option value=""></option>
                                    @foreach ($engineers as $engineer)
                                        <option value="{{ $engineer->id }}">{{ $engineer->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="button" id="bulk_assign_btn" class="btn btn-sm btn-primary" disabled>إسناد السجلات المحددة</button>
                        </div>
                    </div>
                </div>
            @endrole

            <div class="d-flex align-items-center gap-3 mb-4">
                <h3 class="fs-6 fw-bold mb-0">نتائج التدقيق</h3>
                <span class="badge badge-light" id="cso_results_count" aria-live="polite">جارٍ التحميل…</span>
            </div>

            <div class="table-responsive">
                <table id="inf_cso_table" class="table table-row-bordered align-middle gy-4" aria-label="استبيانات منظمات المجتمع المدني المكتملة">
                    <thead>
                        <tr class="fw-bold text-gray-800">
                            <th class="w-40px">
                                <input type="checkbox" id="inf_audit_select_all" class="form-check-input" aria-label="تحديد جميع سجلات الصفحة">
                            </th>
                            <th>ObjectID</th>
                            <th>المهندس الميداني</th>
                            <th>المنظمة</th>
                            <th>المبنى</th>
                            <th>البلدية</th>
                            <th>الحي</th>
                            <th>حالة الضرر</th>
                            <th>حالة التدقيق</th>
                            <th>المدقق</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $(function () {
            $('.audit-select').select2({ width: '100%', allowClear: true });

            const filterDefinitions = [
                { key: 'objectid', id: 'filter_objectid', label: 'رقم السجل' },
                { key: 'status', id: 'filter_status', label: 'حالة التدقيق' },
                { key: 'municipalitie', id: 'filter_municipalitie', label: 'البلدية' },
                { key: 'neighborhood', id: 'filter_neighborhood', label: 'الحي' },
                { key: 'auditor', id: 'filter_auditor', label: 'المدقق', advanced: true },
                { key: 'field_engineer', id: 'filter_field_engineer', label: 'المهندس الميداني', advanced: true },
                { key: 'from_date', id: 'filter_from_date', label: 'من تاريخ', advanced: true },
                { key: 'to_date', id: 'filter_to_date', label: 'إلى تاريخ', advanced: true },
            ];
            let appliedFilters = {};
            let isAssigning = false;
            const dateRange = flatpickr('#cso_survey_date_range', {
                mode: 'range', dateFormat: 'Y-m-d', disableMobile: true,
                locale: document.documentElement.lang === 'ar' ? flatpickr.l10ns.ar : undefined,
                onChange: function (dates, dateString, picker) {
                    $('#filter_from_date').val(dates[0] ? picker.formatDate(dates[0], 'Y-m-d') : '');
                    $('#filter_to_date').val(dates[1] ? picker.formatDate(dates[1], 'Y-m-d') : '');
                },
            });

            function renderActiveFilters() {
                const container = $('#active_filters').empty();
                let advancedCount = 0;

                filterDefinitions.forEach(function (filter) {
                    const value = appliedFilters[filter.key];
                    if (!value) { return; }
                    if (filter.key === 'to_date' && appliedFilters.from_date) { return; }

                    if (filter.advanced) { advancedCount++; }
                    const input = $('#' + filter.id);
                    let label = input.is('select')
                        ? input.find('option').filter(function () { return this.value === value; }).text()
                        : value;
                    const isDate = filter.key === 'from_date' || filter.key === 'to_date';
                    if (isDate && appliedFilters.from_date && appliedFilters.to_date) {
                        label = appliedFilters.from_date + ' إلى ' + appliedFilters.to_date;
                    }
                    const chip = $('<button>', {
                        type: 'button', class: 'cso-filter-chip',
                        'aria-label': 'إزالة فلتر ' + filter.label,
                    });
                    chip.append($('<span>').text((isDate ? 'تاريخ الاستبيان' : filter.label) + ': ' + label));
                    chip.append($('<span>', { 'aria-hidden': 'true' }).text('×'));
                    chip.on('click', function () {
                        delete appliedFilters[filter.key];
                        input.val('').trigger('change.select2');
                        if (isDate) {
                            delete appliedFilters.from_date;
                            delete appliedFilters.to_date;
                            $('#filter_from_date, #filter_to_date').val('');
                            dateRange.clear(false);
                        }
                        renderActiveFilters();
                        table.ajax.reload();
                    });
                    container.append(chip);
                });

                container.toggleClass('d-none', container.children().length === 0);
                $('#advanced_filters_count').text(advancedCount).toggleClass('d-none', advancedCount === 0);
            }

            function updateSelection() {
                const all = $('#inf_cso_table .inf-audit-row-check');
                const selected = all.filter(':checked').length;
                $('#selected_records_count').text(selected);
                $('#cso_assignment_bar').toggleClass('d-none', selected === 0);
                $('#inf_audit_select_all').prop('checked', all.length > 0 && selected === all.length)
                    .prop('indeterminate', selected > 0 && selected < all.length);
                $('#bulk_assign_btn').prop('disabled', isAssigning || selected === 0 || !$('#bulk_assign_engineer').val());
            }

            const table = $('#inf_cso_table').DataTable({
                processing: true,
                serverSide: true,
                dom: 'rt<"d-flex flex-wrap align-items-center justify-content-between gap-3 mt-5"lip>',
                language: {
                    processing: 'جارٍ تحميل النتائج…',
                    info: 'عرض _START_–_END_ من _TOTAL_ استبيان',
                    infoEmpty: 'لا توجد نتائج',
                    infoFiltered: '(من أصل _MAX_ استبيان)',
                    lengthMenu: 'عرض _MENU_',
                    emptyTable: 'لا توجد استبيانات مكتملة جاهزة للتدقيق حالياً.',
                    zeroRecords: 'لا توجد نتائج مطابقة. جرّب تعديل الفلاتر أو مسحها.',
                },
                ajax: {
                    url: @json(route('inf-audit.cso.data')),
                    data: function (d) {
                        filterDefinitions.forEach(function (filter) {
                            d[filter.key] = appliedFilters[filter.key] || '';
                        });
                    }
                },
                columns: [
                    { data: 'selection', name: 'selection', orderable: false, searchable: false },
                    { data: 'objectid', name: 'objectid' },
                    { data: 'field_engineer', name: 'field_engineer', defaultContent: '-' },
                    { data: 'organization_name', name: 'organization_name', defaultContent: '-' },
                    { data: 'building_name', name: 'building_name', defaultContent: '-' },
                    { data: 'municipalitie', name: 'municipalitie', defaultContent: '-' },
                    { data: 'neighborhood', name: 'neighborhood', defaultContent: '-' },
                    { data: 'building_damage_status', name: 'building_damage_status', defaultContent: '-' },
                    { data: 'audit_status', name: 'audit_status', orderable: false, searchable: false },
                    { data: 'auditor', name: 'auditor', orderable: false, searchable: false },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false },
                ],
                order: [[1, 'desc']]
            });

            $('#cso_filters_form').on('submit', function (event) {
                event.preventDefault();
                const fromDate = $('#filter_from_date').val();
                const toDate = $('#filter_to_date').val();
                if (fromDate && toDate && fromDate > toDate) {
                    toastr.warning('تاريخ البداية يجب أن يسبق تاريخ النهاية.');
                    return;
                }
                appliedFilters = {};
                filterDefinitions.forEach(function (filter) {
                    appliedFilters[filter.key] = String($('#' + filter.id).val() || '').trim();
                });
                renderActiveFilters();
                table.ajax.reload();
            });

            $('#reset_filters_btn').on('click', function () {
                appliedFilters = {};
                $('.audit-filter').val('').trigger('change.select2');
                dateRange.clear(false);
                renderActiveFilters();
                table.search('');
                table.ajax.reload();
            });

            $('#refresh_table_btn').on('click', function () { table.ajax.reload(null, false); });

            $('#bulk_assign_engineer').select2({ width: '100%', allowClear: true }).on('change', updateSelection);

            $('#inf_audit_select_all').on('change', function () {
                $('#inf_cso_table .inf-audit-row-check').prop('checked', $(this).is(':checked'));
                updateSelection();
            });

            $('#inf_cso_table').on('change', '.inf-audit-row-check', updateSelection);
            $('#clear_selection_btn').on('click', function () {
                $('#inf_cso_table .inf-audit-row-check').prop('checked', false);
                updateSelection();
            });

            $('#inf_cso_table').on('draw.dt', function () {
                $('#inf_cso_table .inf-audit-row-check').prop('checked', false);
                $('#cso_results_count').text(table.page.info().recordsDisplay.toLocaleString('ar') + ' استبيان');
                updateSelection();
            });

            $('#bulk_assign_btn').on('click', function () {
                if (isAssigning) { return; }
                const ids = $('#inf_cso_table .inf-audit-row-check:checked').map(function () {
                    return $(this).val();
                }).get();

                if (ids.length === 0) {
                    toastr.warning('يرجى اختيار سجل واحد على الأقل');
                    return;
                }

                if (!$('#bulk_assign_engineer').val()) {
                    toastr.warning('يرجى اختيار المدقق');
                    return;
                }

                isAssigning = true;
                $('#bulk_assign_btn').text('جارٍ الإسناد…');
                updateSelection();

                $.post(@json(route('inf-audit.cso.assign')), {
                    _token: @json(csrf_token()),
                    ids: ids,
                    assigned_to: $('#bulk_assign_engineer').val()
                }).done(function (response) {
                    toastr.success(response.message || 'تم الإسناد بنجاح');
                    table.ajax.reload(null, false);
                }).fail(function (xhr) {
                    toastr.error(xhr.responseJSON?.message || 'حدث خطأ أثناء الإسناد');
                }).always(function () {
                    isAssigning = false;
                    $('#bulk_assign_btn').text('إسناد السجلات المحددة');
                    updateSelection();
                });
            });
        });
    </script>
@endsection
