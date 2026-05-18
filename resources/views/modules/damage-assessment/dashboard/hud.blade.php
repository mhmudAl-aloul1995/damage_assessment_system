<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INDAS Dashboard - Live Satellite HUD</title>
    <link rel="shortcut icon" href="{{ asset('assets/media/logos/logo_641.png') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://js.arcgis.com/4.22/esri/themes/dark/main.css">

    <style>
        :root {
            --neon-blue: #00f2fe;
            --neon-red: #ff0055;
            --neon-green: #00ff87;
            --neon-yellow: #fae813;
            --glass-bg: rgba(6, 18, 36, 0.75);
            --glass-border: rgba(0, 242, 254, 0.2);
        }

        body,
        html {
            height: 100%;
            margin: 0;
            font-family: 'Cairo', sans-serif;
            background-color: #030811;
            color: #ffffff;
            overflow: hidden;
        }

        #live-gis-hud-map {
            position: fixed;
            inset: 0;
            z-index: 1;
            filter: saturate(1.2) brightness(0.6) contrast(1.1);
        }

        .cyber-map-overlay {
            position: fixed;
            inset: 0;
            background: radial-gradient(circle, rgba(10, 34, 64, 0) 40%, rgba(3, 8, 17, 0.85) 90%);
            pointer-events: none;
            z-index: 2;
        }

        .hud-container {
            position: relative;
            z-index: 3;
            height: 100%;
            display: flex;
            flex-direction: column;
            padding: 15px;
            pointer-events: none;
        }

        .hud-interactive {
            pointer-events: auto;
        }

        .hud-header {
            background: linear-gradient(180deg, rgba(3, 15, 33, 0.95) 0%, rgba(3, 15, 33, 0.7) 100%);
            border: 1px solid var(--glass-border);
            border-bottom: 2px solid var(--neon-blue);
            border-radius: 10px;
            padding: 12px 20px;
            box-shadow: 0 0 15px rgba(0, 242, 254, 0.15);
            margin-bottom: 15px;
        }

        .hud-title-main {
            font-weight: 900;
            font-size: 1.35rem;
            letter-spacing: 0;
            text-shadow: 0 0 10px rgba(0, 242, 254, 0.6);
            color: #ffffff;
            margin: 0;
        }

        .card-hud-glass {
            background: var(--glass-bg);
            backdrop-filter: blur(12px) saturate(160%);
            -webkit-backdrop-filter: blur(12px) saturate(160%);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.37);
        }

        .hud-digital-number {
            font-family: 'Orbitron', 'Cairo', sans-serif;
            font-size: clamp(1.35rem, 2vw, 1.8rem);
            font-weight: 700;
            color: #ffffff;
            text-shadow: 0 0 8px rgba(255, 255, 255, 0.4);
            line-height: 1.1;
            overflow-wrap: anywhere;
        }

        .hud-label {
            font-size: 0.75rem;
            color: #8fa0b7;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        .hud-badge-pulse {
            background: rgba(255, 0, 85, 0.2);
            border: 1px solid var(--neon-red);
            color: #ff3377;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            box-shadow: 0 0 8px rgba(255, 0, 85, 0.3);
            white-space: nowrap;
        }

        .hud-workspace {
            flex: 1;
            display: flex;
            gap: 15px;
            min-height: 0;
        }

        .hud-sidebar {
            width: 360px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            overflow-y: auto;
        }

        .hud-center-space {
            flex: 1;
        }

        .cyber-progress {
            height: 6px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 6px;
        }

        .cyber-progress-fill {
            height: 100%;
            box-shadow: 0 0 8px currentColor;
        }

        .hud-section-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--neon-blue);
            letter-spacing: 0;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid rgba(0, 242, 254, 0.15);
            padding-bottom: 6px;
        }

        .table-cyber {
            color: #ffffff;
            font-size: 0.78rem;
        }

        .table-cyber,
        .table-cyber tbody,
        .table-cyber tr,
        .table-cyber td,
        .table-cyber td * {
            color: #ffffff !important;
        }

        .table-cyber th {
            background: rgba(0, 242, 254, 0.1) !important;
            color: var(--neon-blue);
            border-color: rgba(0, 242, 254, 0.15) !important;
            font-weight: 700;
        }

        .table-cyber td {
            background: transparent !important;
            border-color: rgba(255, 255, 255, 0.05) !important;
            padding: 8px 4px;
        }

        .governorate-report {
            border: 1px solid rgba(0, 242, 254, 0.12);
            border-radius: 8px;
            margin-bottom: 12px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.025);
        }

        .governorate-report-header {
            display: grid;
            grid-template-columns: 1fr repeat(3, minmax(58px, auto));
            gap: 10px;
            align-items: center;
            padding: 10px;
            background: rgba(0, 242, 254, 0.08);
        }

        .governorate-report-name {
            color: var(--neon-blue);
            font-weight: 800;
            min-width: 0;
        }

        .governorate-report-metric {
            text-align: center;
        }

        .governorate-report-metric span {
            display: block;
            color: #8fa0b7;
            font-size: 0.65rem;
            font-weight: 700;
        }

        .governorate-report-metric strong {
            display: block;
            color: #ffffff;
            font-family: 'Orbitron', 'Cairo', sans-serif;
            font-size: 0.8rem;
        }

        .governorate-report-body {
            padding: 0 10px 10px;
        }

        .municipality-chart-wrap {
            height: 130px;
            margin: 10px 0;
            position: relative;
        }

        .esri-view,
        .esri-view-root,
        .esri-view-surface {
            background: #030811;
        }

        .esri-popup__main-container,
        .esri-popup__header,
        .esri-popup__content {
            background: rgba(6, 18, 36, 0.94);
            color: #ffffff;
        }

        .esri-popup__main-container {
            border: 1px solid rgba(0, 242, 254, 0.25);
            box-shadow: 0 0 18px rgba(0, 242, 254, 0.22);
        }

        .esri-ui .esri-widget {
            background: rgba(6, 18, 36, 0.82);
            color: #ffffff;
        }

        .hud-map-popup {
            color: #ffffff;
            min-width: 260px;
        }

        .hud-map-popup strong {
            color: var(--neon-blue);
            display: block;
            font-size: 0.85rem;
            margin-bottom: 6px;
        }

        .hud-map-popup span {
            color: #8fa0b7;
            display: block;
            font-size: 0.75rem;
        }

        .hud-map-popup table {
            border-collapse: collapse;
            color: #ffffff;
            direction: ltr;
            font-size: 0.74rem;
            margin: 8px 0 10px;
            width: 100%;
        }

        .hud-map-popup th,
        .hud-map-popup td {
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #ffffff;
            padding: 5px 7px;
            vertical-align: top;
        }

        .hud-map-popup th {
            background: rgba(0, 242, 254, 0.12);
            color: var(--neon-blue);
            font-weight: 800;
            width: 42%;
        }

        .hud-map-popup-action {
            align-items: center;
            background: rgba(0, 242, 254, 0.14);
            border: 1px solid rgba(0, 242, 254, 0.45);
            border-radius: 6px;
            color: #ffffff !important;
            display: inline-flex;
            font-size: 0.75rem;
            font-weight: 800;
            gap: 6px;
            justify-content: center;
            padding: 7px 10px;
            text-decoration: none;
        }

        ::-webkit-scrollbar {
            width: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(0, 242, 254, 0.3);
            border-radius: 4px;
        }

        @media (max-width: 991.98px) {
            body,
            html {
                height: auto;
                min-height: 100%;
                overflow: auto;
            }

            .hud-container {
                height: auto;
                min-height: 100vh;
            }

            .hud-workspace {
                flex-direction: column;
            }

            .hud-sidebar {
                width: 100%;
                overflow: visible;
            }

            .hud-center-space {
                min-height: 280px;
            }
        }
    </style>
</head>
<body>
    @php
        $formatNumber = fn ($value) => number_format((float) $value);
        $rubbleQuantity = (float) $summaryStats['rubble_quantity'];
        $formattedRubble = $rubbleQuantity >= 1000000
            ? number_format($rubbleQuantity / 1000000, 1).'M'
            : number_format($rubbleQuantity);
    @endphp

    <div id="live-gis-hud-map"></div>
    <div class="cyber-map-overlay"></div>

    <div class="hud-container">
        <header class="hud-header hud-interactive">
            <div class="row align-items-center">
                <div class="col-md-8 text-start">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="hud-badge-pulse"><i class="fa-solid fa-circle-dot me-1"></i>LIVE GIS HUD</span>
                        <h1 class="hud-title-main">برنامج حصر الأضرار - قطاع غزة</h1>
                    </div>
                </div>
                <div class="col-md-4 text-md-end text-start mt-2 mt-md-0">
                    <span class="hud-label d-block text-white-50">المجلس الفلسطيني للإسكان</span>
                    <a href="{{ route('damageAssessment.index') }}" class="small text-info text-decoration-none">العودة للوحة الرئيسية</a>
                </div>
            </div>
        </header>

        <div class="row g-3 mb-3 hud-interactive">
            <div class="col-md-3">
                <div class="card-hud-glass d-flex align-items-center justify-content-between" style="border-left: 3px solid var(--neon-blue);">
                    <div>
                        <span class="hud-label d-block mb-1">إجمالي مباني القطاع</span>
                        <span class="hud-digital-number">{{ $formatNumber($summaryStats['total_buildings']) }}</span>
                    </div>
                    <i class="fa-solid fa-layer-group text-info fs-4 opacity-50"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-hud-glass d-flex align-items-center justify-content-between" style="border-left: 3px solid var(--neon-green);">
                    <div>
                        <span class="hud-label d-block mb-1">المباني المقيّمة ميدانياً</span>
                        <span class="hud-digital-number text-success">{{ $formatNumber($summaryStats['assessed_buildings']) }}</span>
                    </div>
                    <i class="fa-solid fa-satellite-dish text-success fs-4 opacity-50"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-hud-glass d-flex align-items-center justify-content-between" style="border-left: 3px solid var(--neon-red);">
                    <div>
                        <span class="hud-label d-block mb-1">وحدات مدمرة كلياً</span>
                        <span class="hud-digital-number text-danger">{{ $formatNumber($summaryStats['fully_damaged_units']) }}</span>
                    </div>
                    <i class="fa-solid fa-house-damage text-danger fs-4 opacity-50"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-hud-glass d-flex align-items-center justify-content-between" style="border-left: 3px solid var(--neon-yellow);">
                    <div>
                        <span class="hud-label d-block mb-1">تقديرات الركام الكلي</span>
                        <span class="hud-digital-number text-warning">{{ $formattedRubble }} <small class="fs-6 text-muted">طن</small></span>
                    </div>
                    <i class="fa-solid fa-truck-pickup text-warning fs-4 opacity-50"></i>
                </div>
            </div>
        </div>

        <div class="hud-workspace">
            <div class="hud-sidebar hud-interactive">
                <div class="card-hud-glass">
                    <div class="hud-section-title"><i class="fa-solid fa-chart-pie"></i> الهيكل التحليلي لمستويات الضرر</div>
                    <div style="position: relative; height: 160px;">
                        <canvas id="hudDoughnutChart"></canvas>
                    </div>
                </div>

                <div class="card-hud-glass">
                    <div class="hud-section-title"><i class="fa-solid fa-shield-halved"></i> تقييم السلامة الإنشائية للوحدات</div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small opacity-75">
                            <span>مدمرة كلياً</span>
                            <span class="text-danger fw-bold">{{ $safetyStats['destroyed'] }}%</span>
                        </div>
                        <div class="cyber-progress text-danger"><div class="cyber-progress-fill bg-danger" style="width: {{ $safetyStats['destroyed'] }}%"></div></div>
                    </div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small opacity-75">
                            <span>تحتاج تدعيم إنشائي</span>
                            <span class="text-warning fw-bold">{{ $safetyStats['support_needed'] }}%</span>
                        </div>
                        <div class="cyber-progress text-warning"><div class="cyber-progress-fill bg-warning" style="width: {{ $safetyStats['support_needed'] }}%"></div></div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between small opacity-75">
                            <span>صالحة للسكن</span>
                            <span class="text-success fw-bold">{{ $safetyStats['habitable'] }}%</span>
                        </div>
                        <div class="cyber-progress text-success"><div class="cyber-progress-fill bg-success" style="width: {{ $safetyStats['habitable'] }}%"></div></div>
                    </div>
                </div>
            </div>

            <div class="hud-center-space"></div>

            <div class="hud-sidebar hud-interactive">
                <div class="card-hud-glass" style="flex: 1; display: flex; flex-direction: column;">
                    <div class="hud-section-title"><i class="fa-solid fa-globe"></i> تقارير البلديات والأحياء</div>
                    <div style="flex: 1; overflow-y: auto;">
                        @forelse ($municipalityReports as $report)
                            <section class="governorate-report">
                                <div class="governorate-report-header">
                                    <div class="governorate-report-name">{{ $report['name'] }}</div>
                                    <div class="governorate-report-metric">
                                        <span>مقيّم</span>
                                        <strong>{{ $formatNumber($report['summary']['assessed']) }}</strong>
                                    </div>
                                    <div class="governorate-report-metric">
                                        <span>وحدات</span>
                                        <strong>{{ $formatNumber($report['summary']['units']) }}</strong>
                                    </div>
                                    <div class="governorate-report-metric">
                                        <span>مدمر</span>
                                        <strong class="text-danger">{{ $formatNumber($report['summary']['destroyed']) }}</strong>
                                    </div>
                                </div>

                                <div class="governorate-report-body">
                                    <div class="municipality-chart-wrap">
                                        <canvas id="municipalityChart{{ $loop->index }}"></canvas>
                                    </div>

                                    <table class="table table-sm table-cyber align-middle mb-0 text-center">
                                        <thead>
                                            <tr>
                                                <th class="text-start">الحي</th>
                                                <th>مقيّم</th>
                                                <th>وحدات</th>
                                                <th>مدمر</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($report['neighborhoods'] as $neighborhood)
                                                <tr>
                                                    <td class="text-start fw-bold text-info">{{ $neighborhood['name'] }}</td>
                                                    <td>{{ $formatNumber($neighborhood['assessed']) }}</td>
                                                    <td>{{ $formatNumber($neighborhood['units']) }}</td>
                                                    <td class="text-danger fw-bold">{{ $formatNumber($neighborhood['destroyed']) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-white-50 py-3">لا توجد أحياء لهذه المحافظة</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        @empty
                            <div class="text-center text-white-50 py-4">لا توجد بيانات بلديات حالياً</div>
                        @endforelse
                    </div>
                    <div class="p-2 text-center border-top border-secondary mt-2" style="background: rgba(255, 255, 255, 0.03);">
                        <small class="text-white-50">إجمالي الوحدات التي فُحصت: <span class="text-info fw-bold">{{ $formatNumber(array_sum($damageChart['data'])) }}</span></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://js.arcgis.com/4.22/"></script>

    <script>
        const mapPoints = @json($mapPoints);
        const municipalityReports = @json($municipalityReports);
        const buildingLayerUrl = @json($buildingLayerUrl);
        const arcgisToken = @json($token);
        const assessmentBaseUrl = @json(url('assessment'));

        require([
            'esri/Map',
            'esri/views/MapView',
            'esri/layers/FeatureLayer',
            'esri/geometry/Extent',
            'esri/identity/IdentityManager',
            'esri/widgets/Legend',
            'esri/widgets/ScaleBar'
        ], function (Map, MapView, FeatureLayer, Extent, esriId, Legend, ScaleBar) {
            if (buildingLayerUrl && arcgisToken) {
                esriId.registerToken({
                    server: buildingLayerUrl,
                    token: arcgisToken,
                    expires: Date.now() + (60 * 60 * 1000)
                });
            }

            const damageRenderer = {
                type: 'unique-value',
                field: 'building_damage_status',
                defaultSymbol: {
                    type: 'simple-fill',
                    color: [0, 255, 135, 0.26],
                    outline: {
                        color: [255, 255, 255, 0.75],
                        width: 0.7
                    }
                },
                defaultLabel: 'Unclassified',
                uniqueValueInfos: [
                    {
                        value: 'fully_damaged',
                        label: 'Fully damaged',
                        symbol: {
                            type: 'simple-fill',
                            color: [255, 0, 85, 0.62],
                            outline: {
                                color: [255, 255, 255, 0.95],
                                width: 1
                            }
                        }
                    },
                    {
                        value: 'partially_damaged',
                        label: 'Partially damaged',
                        symbol: {
                            type: 'simple-fill',
                            color: [250, 232, 19, 0.50],
                            outline: {
                                color: [255, 255, 255, 0.85],
                                width: 0.8
                            }
                        }
                    },
                    {
                        value: 'committee_review',
                        label: 'Committee review',
                        symbol: {
                            type: 'simple-fill',
                            color: [0, 242, 254, 0.48],
                            outline: {
                                color: [255, 255, 255, 0.9],
                                width: 0.9
                            }
                        }
                    }
                ]
            };

            function value(attributes, ...keys) {
                for (const key of keys) {
                    if (attributes[key] !== undefined && attributes[key] !== null && String(attributes[key]).trim() !== '') {
                        return attributes[key];
                    }
                }

                return '-';
            }

            function popupTableRow(label, displayValue) {
                const row = document.createElement('tr');
                const header = document.createElement('th');
                const cell = document.createElement('td');

                header.textContent = label;
                cell.textContent = displayValue;
                row.append(header, cell);

                return row;
            }

            function buildBuildingPopup(event) {
                const attributes = event.graphic.attributes || {};
                const wrapper = document.createElement('div');
                const title = document.createElement('strong');
                const table = document.createElement('table');
                const action = document.createElement('a');
                const globalId = value(attributes, 'globalid', 'GlobalID', 'GLOBALID');

                wrapper.className = 'hud-map-popup';
                title.textContent = value(attributes, 'building_name', 'Building_Name', 'name', 'NAME');

                table.append(
                    popupTableRow('Object ID', value(attributes, 'objectid', 'OBJECTID')),
                    popupTableRow('Global ID', globalId),
                    popupTableRow('Damage', value(attributes, 'building_damage_status')),
                    popupTableRow('Field status', value(attributes, 'field_status')),
                    popupTableRow('Assigned to', value(attributes, 'assignedto', 'AssignedTo')),
                    popupTableRow('Municipality', value(attributes, 'municipalitie')),
                    popupTableRow('Neighborhood', value(attributes, 'neighborhood'))
                );

                action.className = 'hud-map-popup-action';
                action.target = '_blank';
                action.rel = 'noopener';
                action.href = globalId !== '-' ? `${assessmentBaseUrl}/${globalId}` : '#';
                action.textContent = 'فتح تفاصيل التقييم';

                wrapper.append(title, table, action);

                return wrapper;
            }

            const buildingsLayer = new FeatureLayer({
                url: buildingLayerUrl,
                renderer: damageRenderer,
                outFields: ['*'],
                minScale: 0,
                maxScale: 0,
                popupTemplate: {
                    title: 'تفاصيل المبنى',
                    content: buildBuildingPopup
                }
            });
            const gazaStripExtent = new Extent({
                xmin: 34.18,
                ymin: 31.20,
                xmax: 34.56,
                ymax: 31.60,
                spatialReference: {
                    wkid: 4326
                }
            });

            const map = new Map({
                basemap: 'satellite',
                layers: [buildingsLayer]
            });

            const view = new MapView({
                container: 'live-gis-hud-map',
                map,
                center: [34.38, 31.42],
                zoom: 11,
                constraints: {
                    minZoom: 9
                },
                popup: {
                    dockEnabled: false
                }
            });

            view.ui.components = [];
            view.ui.add(new Legend({
                view,
                layerInfos: [{
                    layer: buildingsLayer,
                    title: 'Damage Symbology'
                }]
            }), 'bottom-right');
            view.ui.add(new ScaleBar({ view, unit: 'metric' }), 'bottom-left');

            view.when(function () {
                view.goTo(gazaStripExtent, { duration: 1200 }).catch(function () {});
            });
        });

        const ctxDoughnut = document.getElementById('hudDoughnutChart').getContext('2d');
        new Chart(ctxDoughnut, {
            type: 'doughnut',
            data: {
                labels: @json($damageChart['labels']),
                datasets: [{
                    data: @json($damageChart['data']),
                    backgroundColor: ['#ff0055', '#fae813', '#00f2fe', '#00ff87'],
                    borderColor: '#061224',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'left',
                        labels: {
                            color: '#8fa0b7',
                            font: { family: 'Cairo', size: 10, weight: '600' },
                            boxWidth: 10
                        }
                    }
                },
                cutout: '75%'
            }
        });

        municipalityReports.forEach((report, index) => {
            const canvas = document.getElementById(`municipalityChart${index}`);

            if (!canvas) {
                return;
            }

            new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['مدمر', 'جزئي', 'لجنة', 'غير مصنف'],
                    datasets: [{
                        data: report.chart,
                        backgroundColor: ['#ff0055', '#fae813', '#00f2fe', '#00ff87'],
                        borderWidth: 0,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#8fa0b7', font: { family: 'Cairo', size: 10 } },
                            grid: { color: 'rgba(255,255,255,0.04)' }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#8fa0b7', precision: 0, font: { family: 'Orbitron', size: 9 } },
                            grid: { color: 'rgba(255,255,255,0.06)' }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
