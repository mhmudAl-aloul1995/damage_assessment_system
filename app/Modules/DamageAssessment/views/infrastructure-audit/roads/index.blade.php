@extends('layouts.app')

@section('title', 'تدقيق الطرق')
@section('pageName', 'تدقيق الطرق')

@section('content')
    <div class="card card-flush">
        <div class="card-header pt-6">
            <div class="card-title">
                <h2 class="fw-bold mb-0">تدقيق الطرق</h2>
            </div>
            <div class="card-toolbar d-flex gap-2 flex-wrap">
                @role('Database Officer|Team Leader -INF')
                <select id="bulk_assign_engineer" class="form-select form-select-solid w-250px"
                    data-placeholder="اختر المدقق">
                    <option value=""></option>
                    @foreach ($engineers as $engineer)
                        <option value="{{ $engineer->id }}">{{ $engineer->name }}</option>
                    @endforeach
                </select>
                <button type="button" id="bulk_assign_btn" class="btn btn-light-info">إسناد المحدد</button>
                @endrole
                <button type="button" id="open_roads_map_modal_btn" class="btn btn-light-primary" data-bs-toggle="modal"
                    data-bs-target="#inf_roads_map_modal">خريطة مرافق الطرق</button>
                <button type="button" id="open_export_modal_btn" class="btn btn-light-success" data-bs-toggle="modal"
                    data-bs-target="#inf_roads_export_modal">تصدير تقرير</button>
                <button type="button" id="reset_filters_btn" class="btn btn-light">إعادة تعيين الفلاتر</button>
                <button class="btn btn-light-primary"
                    onclick="$('#inf_roads_table').DataTable().ajax.reload(null, false)">تحديث</button>
            </div>
        </div>

        <div class="card-body">
            <div class="row g-3 mb-6">
                <div class="col-md-2">
                    <input id="filter_objectid" type="text" class="form-control form-control-solid audit-filter"
                        placeholder="ObjectID">
                </div>
                <div class="col-md-2">
                    <select id="filter_municipalitie" class="form-select form-select-solid audit-filter audit-select"
                        data-placeholder="البلدية">
                        <option value="">كل البلديات</option>
                        @foreach ($municipalities as $municipality)
                            <option value="{{ $municipality }}">{{ $municipality }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="filter_neighborhood" class="form-select form-select-solid audit-filter audit-select"
                        data-placeholder="الحي">
                        <option value="">كل الأحياء</option>
                        @foreach ($neighborhoods as $neighborhood)
                            <option value="{{ $neighborhood }}">{{ $neighborhood }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="filter_status" class="form-select form-select-solid audit-filter audit-select"
                        data-placeholder="الحالة">

                        <option value="">كل الحالات</option>

                        <option value="__no_audit_status__">
                            لم تأخذ حالة تدقيق
                        </option>

                        @foreach ($statuses as $status)
                            <option value="{{ $status->name }}">
                                {{ $status->label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="filter_auditor" class="form-select form-select-solid audit-filter audit-select"
                        data-placeholder="المدقق">
                        <option value="">كل المدققين</option>
                        @foreach ($engineers as $engineer)
                            <option value="{{ $engineer->id }}">{{ $engineer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="filter_field_engineer" class="form-select form-select-solid audit-filter audit-select"
                        data-placeholder="المهندس الميداني">
                        <option value="">كل المهندسين الميدانيين</option>
                        @foreach ($fieldEngineers as $fieldEngineer)
                            <option value="{{ $fieldEngineer['value'] }}">{{ $fieldEngineer['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input id="filter_from_date" type="date" class="form-control form-control-solid audit-filter">
                </div>
                <div class="col-md-2">
                    <input id="filter_to_date" type="date" class="form-control form-control-solid audit-filter">
                </div>
            </div>

            <div class="table-responsive">
                <table id="inf_roads_table" class="table table-row-bordered align-middle gy-4">
                    <thead>
                        <tr class="fw-bold text-gray-800">
                            <th class="w-40px">
                                <input type="checkbox" id="inf_audit_select_all" class="form-check-input">
                            </th>
                            <th>ObjectID</th>
                            <th>المهندس الميداني</th>
                            <th>اسم الطريق</th>
                            <th>البلدية</th>
                            <th>الحي</th>
                            <th>الحالة</th>
                            <th>المدقق</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="inf_roads_map_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">خريطة مرافق الطرق حسب فلاتر التدقيق</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <link rel="stylesheet" href="https://js.arcgis.com/4.22/esri/themes/light/main.css">

                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                        <div class="d-flex align-items-center position-relative">
                            <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <input type="text" data-kt-inf-road-map-filter="search"
                                class="form-control form-control-solid w-250px ps-13"
                                placeholder="{{ __('multilingual.damage_dashboard.search_road_facilities') }}" />
                        </div>
                        <span class="badge badge-light-primary">
                            نتائج الخريطة: <span id="infRoadsMapResultCount">0</span>
                        </span>
                    </div>

                    <div class="row g-5">
                        <div class="col-lg-5">
                            <div class="table-responsive">
                                <table class="table table-rounded table-striped align-middle fs-7 gy-4"
                                    id="inf_roads_map_table">
                                    <thead>
                                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                            <th>{{ __('multilingual.damage_dashboard.municipality') }}</th>
                                            <th>{{ __('multilingual.damage_dashboard.neighborhood') }}</th>
                                            <th>{{ __('multilingual.damage_dashboard.object_id') }}</th>
                                            <th>{{ __('multilingual.damage_dashboard.road_name') }}</th>
                                            <th>{{ __('multilingual.damage_dashboard.damage_level') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-gray-600 fw-semibold"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div id="infRoadFacilityViewDiv" class="w-100 rounded" style="height: 650px"></div>
                            <div id="infRoadFacilityLegendDiv"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="inf_roads_export_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تصدير تقرير الطرق</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light-info mb-0">
                        سيتم تصدير ملخص حسب الفلاتر الحالية ويشمل: ما تم حصره، ما تم تدقيقه، المحافظة، الحي، وأطوال الطرق.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إغلاق</button>
                    <button type="button" class="btn btn-light-primary inf-roads-export-btn"
                        data-format="xlsx">Excel</button>
                    <button type="button" class="btn btn-light-danger inf-roads-export-btn" data-format="pdf">PDF</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://js.arcgis.com/4.22/"></script>
    <script>
        $(function () {
            $('.audit-select').select2({ width: '100%', allowClear: true });

            const table = $('#inf_roads_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: @json(route('inf-audit.roads.data')),
                    data: function (d) {
                        d.objectid = $('#filter_objectid').val();
                        d.municipalitie = $('#filter_municipalitie').val();
                        d.neighborhood = $('#filter_neighborhood').val();
                        d.status = $('#filter_status').val();
                        d.auditor = $('#filter_auditor').val();
                        d.field_engineer = $('#filter_field_engineer').val();
                        d.from_date = $('#filter_from_date').val();
                        d.to_date = $('#filter_to_date').val();
                    }
                },
                columns: [
                    { data: 'selection', name: 'selection', orderable: false, searchable: false },
                    { data: 'objectid', name: 'objectid' },
                    { data: 'field_engineer', name: 'field_engineer', defaultContent: '-' },
                    { data: 'str_name', name: 'str_name', defaultContent: '-' },
                    { data: 'municipalitie', name: 'municipalitie', defaultContent: '-' },
                    { data: 'neighborhood', name: 'neighborhood', defaultContent: '-' },
                    { data: 'audit_status', name: 'audit_status', orderable: false, searchable: false },
                    { data: 'auditor', name: 'auditor', orderable: false, searchable: false },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false },
                ],
                order: [[0, 'desc']]
            });

            function currentFilterParams() {
                return $.param(currentFilterData());
            }

            function currentFilterData(includeTableSearch = true) {
                const filters = {
                    objectid: $('#filter_objectid').val(),
                    municipalitie: $('#filter_municipalitie').val(),
                    neighborhood: $('#filter_neighborhood').val(),
                    status: $('#filter_status').val(),
                    auditor: $('#filter_auditor').val(),
                    field_engineer: $('#filter_field_engineer').val(),
                    from_date: $('#filter_from_date').val(),
                    to_date: $('#filter_to_date').val(),
                };

                if (includeTableSearch) {
                    filters.search = table.search();
                }

                return filters;
            }

            const roadFacilityLayerUrl = @json($roadFacilityLayerUrl);
            const arcgisToken = @json($token);
            const roadAuditShowUrlTemplate = @json(route('inf-audit.roads.show', ['road' => '__GLOBALID__']));
            let auditRoadMapInitialized = false;
            let auditRoadMapTable = null;
            let auditRoadFeatureLayer = null;
            let auditRoadView = null;
            let auditRoadSelectionLayer = null;

            function auditRoadObjectIdExpression(objectIds) {
                if (!objectIds.length) {
                    return '1=0';
                }

                const chunks = [];

                for (let index = 0; index < objectIds.length; index += 900) {
                    chunks.push(objectIds.slice(index, index + 900));
                }

                return chunks
                    .map(function (chunk) {
                        return 'OBJECTID IN (' + chunk.join(',') + ')';
                    })
                    .join(' OR ');
            }

            function refreshAuditRoadMapLayer() {
                if (!auditRoadFeatureLayer) {
                    return;
                }

                $.getJSON(@json(route('inf-audit.roads.map-objectids')), currentFilterData(false))
                    .done(function (response) {
                        const objectIds = response.objectids || [];

                        $('#infRoadsMapResultCount').text(response.count || 0);
                        auditRoadFeatureLayer.definitionExpression = auditRoadObjectIdExpression(objectIds);

                        if (auditRoadSelectionLayer) {
                            auditRoadSelectionLayer.removeAll();
                        }
                    })
                    .fail(function () {
                        $('#infRoadsMapResultCount').text('0');
                        auditRoadFeatureLayer.definitionExpression = '1=0';
                        toastr.error('تعذر تحميل نتائج الخريطة');
                    });
            }

            function reloadAuditRoadMap() {
                if (!$('#inf_roads_map_modal').hasClass('show')) {
                    return;
                }

                if (auditRoadMapTable) {
                    auditRoadMapTable.ajax.reload(null, false);
                }

                refreshAuditRoadMapLayer();
            }

            function initAuditRoadMapTable() {
                if (auditRoadMapTable) {
                    return;
                }

                auditRoadMapTable = $('#inf_roads_map_table').DataTable({
                    serverSide: true,
                    ajax: {
                        url: @json(route('inf-audit.roads.map-data')),
                        type: 'GET',
                        data: function (data) {
                            Object.assign(data, currentFilterData(false));
                        }
                    },
                    dom:
                        "<'table-responsive'tr>" +
                        "<'row'<'col-sm-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-start'i>" +
                        "<'col-sm-12 col-md-7 d-flex align-items-center justify-content-center justify-content-md-end'p>>",
                    info: false,
                    order: [],
                    pageLength: 10,
                    lengthChange: false,
                    processing: true,
                    columns: [
                        { data: 'municipalitie', name: 'municipalitie', defaultContent: '-' },
                        { data: 'neighborhood', name: 'neighborhood', defaultContent: '-' },
                        { data: 'objectid', name: 'objectid', defaultContent: '-' },
                        { data: 'str_name', name: 'str_name', defaultContent: '-' },
                        { data: 'road_damage_level', name: 'road_damage_level', orderable: false, searchable: false },
                    ],
                    createdRow: function (row, data) {
                        $(row).css('cursor', 'pointer');
                        $(row).attr('data-objectid', data.objectid);
                    }
                });

                $('[data-kt-inf-road-map-filter="search"]').on('keydown', function (event) {
                    if (event.which === 13) {
                        event.preventDefault();
                        auditRoadMapTable.search(event.target.value).draw();
                    }
                });
            }

            function initAuditRoadMap() {
                if (auditRoadMapInitialized || !roadFacilityLayerUrl) {
                    return;
                }

                auditRoadMapInitialized = true;

                require([
                    'esri/Map',
                    'esri/views/MapView',
                    'esri/layers/FeatureLayer',
                    'esri/layers/GraphicsLayer',
                    'esri/Graphic',
                    'esri/identity/IdentityManager',
                    'esri/widgets/BasemapToggle',
                    'esri/widgets/Legend',
                    'esri/widgets/Search',
                    'esri/widgets/ScaleBar'
                ], function (
                    Map,
                    MapView,
                    FeatureLayer,
                    GraphicsLayer,
                    Graphic,
                    esriId,
                    BasemapToggle,
                    Legend,
                    Search,
                    ScaleBar
                ) {
                    esriId.registerToken({
                        server: roadFacilityLayerUrl,
                        token: arcgisToken,
                        expires: Date.now() + (60 * 60 * 1000)
                    });

                    auditRoadFeatureLayer = new FeatureLayer({
                        url: roadFacilityLayerUrl,
                        renderer: {
                            type: 'unique-value',
                            field: 'road_damage_level',
                            defaultSymbol: {
                                type: 'simple-line',
                                color: [128, 128, 128, 1],
                                width: 3
                            },
                            uniqueValueInfos: [
                                { value: 'destroyed', symbol: { type: 'simple-line', color: [255, 0, 0, 1], width: 4 }, label: @json(__('multilingual.damage_dashboard.destroyed')) },
                                { value: 'severe', symbol: { type: 'simple-line', color: [255, 94, 0, 1], width: 4 }, label: @json(__('multilingual.damage_dashboard.severe')) },
                                { value: 'moderate', symbol: { type: 'simple-line', color: [255, 193, 7, 1], width: 4 }, label: @json(__('multilingual.damage_dashboard.moderate')) },
                                { value: 'minor', symbol: { type: 'simple-line', color: [40, 167, 69, 1], width: 4 }, label: @json(__('multilingual.damage_dashboard.minor')) },
                                { value: 'No_Damage', symbol: { type: 'simple-line', color: [0, 123, 255, 1], width: 4 }, label: @json(__('multilingual.damage_dashboard.no_damage')) }
                            ]
                        },
                        outFields: ['*'],
                        definitionExpression: '1=0',
                        popupTemplate: {
                            title: function (event) {
                                const attrs = event.graphic.attributes;
                                const recordName = attrs.str_name || attrs.STR_NAME || 'Road facility';
                                const globalId = attrs.globalid || attrs.GlobalID || attrs.GLOBALID;
                                const detailsUrl = globalId ? roadAuditShowUrlTemplate.replace('__GLOBALID__', globalId) : '#';

                                return `${recordName} <a target="_blank" style="color:red;" href="${detailsUrl}">فتح التدقيق</a>`;
                            },
                            content: [{
                                type: 'fields',
                                fieldInfos: [
                                    { fieldName: 'objectid', label: @json(__('multilingual.damage_dashboard.object_id')) },
                                    { fieldName: 'str_name', label: @json(__('multilingual.damage_dashboard.road_name')) },
                                    { fieldName: 'municipalitie', label: @json(__('multilingual.damage_dashboard.municipality')) },
                                    { fieldName: 'neighborhood', label: @json(__('multilingual.damage_dashboard.neighborhood')) },
                                    { fieldName: 'road_damage_level', label: @json(__('multilingual.damage_dashboard.damage_level')) }
                                ]
                            }]
                        }
                    });

                    auditRoadSelectionLayer = new GraphicsLayer();

                    const map = new Map({
                        basemap: 'satellite',
                        layers: [auditRoadFeatureLayer, auditRoadSelectionLayer]
                    });

                    auditRoadView = new MapView({
                        container: 'infRoadFacilityViewDiv',
                        map: map,
                        center: [34.36, 31.45],
                        zoom: 11
                    });

                    auditRoadView.ui.add(new BasemapToggle({
                        view: auditRoadView,
                        nextBasemap: 'streets-navigation-vector'
                    }), 'bottom-left');

                    auditRoadView.ui.add(new ScaleBar({
                        view: auditRoadView,
                        unit: 'metric'
                    }), 'bottom-right');

                    new Legend({
                        view: auditRoadView,
                        container: 'infRoadFacilityLegendDiv',
                        layerInfos: [{
                            layer: auditRoadFeatureLayer,
                            title: @json(__('multilingual.damage_dashboard.road_damage_level'))
                        }]
                    });

                    auditRoadView.ui.add(new Search({
                        view: auditRoadView,
                        allPlaceholder: @json(__('multilingual.damage_dashboard.search_road_facilities')),
                        includeDefaultSources: false,
                        sources: [{
                            layer: auditRoadFeatureLayer,
                            searchFields: ['str_name', 'objectid', 'municipalitie', 'neighborhood'],
                            displayField: 'str_name',
                            exactMatch: false,
                            outFields: ['*'],
                            name: @json(__('multilingual.damage_dashboard.road_facilities_map')),
                            placeholder: @json(__('multilingual.damage_dashboard.search_road_facilities'))
                        }]
                    }), 'top-right');

                    function selectionSymbol(geometryType) {
                        if (geometryType === 'polyline') {
                            return {
                                type: 'simple-line',
                                color: [0, 255, 255, 1],
                                width: 4
                            };
                        }

                        return {
                            type: 'simple-marker',
                            style: 'circle',
                            size: 12,
                            color: [0, 255, 255, 0.25],
                            outline: {
                                color: [0, 255, 255, 1],
                                width: 2
                            }
                        };
                    }

                    function selectFeature(feature, openPopup) {
                        auditRoadSelectionLayer.removeAll();

                        auditRoadSelectionLayer.add(new Graphic({
                            geometry: feature.geometry,
                            symbol: selectionSymbol(feature.geometry.type)
                        }));

                        if (openPopup) {
                            auditRoadView.popup.open({
                                features: [feature],
                                location: feature.geometry.extent ? feature.geometry.extent.center : feature.geometry
                            });
                        }

                        const zoomTarget = feature.geometry.extent
                            ? feature.geometry.extent.expand(1.5)
                            : { target: feature.geometry, zoom: 18 };

                        auditRoadView.goTo(zoomTarget, {
                            duration: 1200,
                            easing: 'in-out-expo'
                        }).catch(function (error) {
                            if (error.name !== 'AbortError') {
                                console.error('GoTo failed:', error);
                            }
                        });
                    }

                    function zoomToFeatureByObjectId(objectId) {
                        const parsedObjectId = parseInt(objectId, 10);

                        if (Number.isNaN(parsedObjectId)) {
                            return;
                        }

                        const query = auditRoadFeatureLayer.createQuery();
                        query.where = `OBJECTID = ${parsedObjectId}`;
                        query.returnGeometry = true;
                        query.outFields = ['*'];

                        auditRoadFeatureLayer.queryFeatures(query).then(function (results) {
                            if (results.features.length) {
                                selectFeature(results.features[0], true);
                            }
                        });
                    }

                    auditRoadView.on('click', function (event) {
                        auditRoadView.hitTest(event).then(function (response) {
                            const result = response.results.find(function (item) {
                                return item.graphic && item.graphic.layer === auditRoadFeatureLayer;
                            });

                            if (result) {
                                selectFeature(result.graphic, false);
                            } else {
                                auditRoadSelectionLayer.removeAll();
                            }
                        });
                    });

                    $('#inf_roads_map_table tbody').on('click', 'tr', function () {
                        const objectId = $(this).attr('data-objectid');

                        if (objectId) {
                            zoomToFeatureByObjectId(objectId);
                        }
                    });

                    refreshAuditRoadMapLayer();
                });
            }

            $('.audit-filter').on('change', function () {
                table.ajax.reload();
                reloadAuditRoadMap();
            });

            $('#filter_objectid').on('input', function () {
                table.ajax.reload();
                reloadAuditRoadMap();
            });

            $('#reset_filters_btn').on('click', function () {
                $('.audit-filter').val('').trigger('change.select2');
                table.search('');
                table.ajax.reload();
                reloadAuditRoadMap();
            });

            $('#bulk_assign_engineer').select2({ width: '250px', allowClear: true });

            $('#inf_audit_select_all').on('change', function () {
                $('.inf-audit-row-check').prop('checked', $(this).is(':checked'));
            });

            $('#inf_roads_table').on('draw.dt', function () {
                $('#inf_audit_select_all').prop('checked', false);
            });

            $('#bulk_assign_btn').on('click', function () {
                const ids = $('.inf-audit-row-check:checked').map(function () {
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

                $.post(@json(route('inf-audit.roads.assign')), {
                    _token: @json(csrf_token()),
                    ids: ids,
                    assigned_to: $('#bulk_assign_engineer').val()
                }).done(function (response) {
                    toastr.success(response.message || 'تم الإسناد بنجاح');
                    table.ajax.reload(null, false);
                }).fail(function (xhr) {
                    toastr.error(xhr.responseJSON?.message || 'حدث خطأ أثناء الإسناد');
                });
            });

            $('.inf-roads-export-btn').on('click', function () {
                const format = $(this).data('format');
                const url = @json(route('inf-audit.roads.export', ['format' => '__FORMAT__'])).replace('__FORMAT__', format);

                window.location.href = url + '?' + currentFilterParams();
            });

            $('#inf_roads_map_modal').on('shown.bs.modal', function () {
                initAuditRoadMapTable();
                initAuditRoadMap();

                if (auditRoadMapTable) {
                    auditRoadMapTable.ajax.reload(null, false);
                }

                if (auditRoadView) {
                    auditRoadView.resize();
                }

                refreshAuditRoadMapLayer();
            });
        });
    </script>
@endsection