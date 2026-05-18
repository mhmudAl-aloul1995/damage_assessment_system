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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

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

        .leaflet-popup-content-wrapper,
        .leaflet-popup-tip {
            background: rgba(6, 18, 36, 0.92);
            color: #ffffff;
            border: 1px solid rgba(0, 242, 254, 0.2);
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
                        <h1 class="hud-title-main">المنظومة الوطنية المتكاملة لحصر الأضرار (INDAS 2026)</h1>
                    </div>
                </div>
                <div class="col-md-4 text-md-end text-start mt-2 mt-md-0">
                    <span class="hud-label d-block text-white-50">الهيئة العربية الدولية للإعمار في فلسطين</span>
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
                    <div class="hud-section-title"><i class="fa-solid fa-globe"></i> تقارير المحافظات والأحياء</div>
                    <div style="flex: 1; overflow-y: auto;">
                        @forelse ($governorateReports as $report)
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
                            <div class="text-center text-white-50 py-4">لا توجد بيانات محافظات حالياً</div>
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
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        const mapPoints = @json($mapPoints);
        const map = L.map('live-gis-hud-map', {
            zoomControl: false,
            attributionControl: false
        }).setView([31.42, 34.38], 11);

        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19
        }).addTo(map);

        const markerColors = {
            fully_damaged: '#ff0055',
            partially_damaged: '#fae813',
            committee_review: '#00f2fe',
            unclassified: '#00ff87'
        };

        mapPoints.forEach((point) => {
            const color = markerColors[point.status] || markerColors.unclassified;

            L.circleMarker([point.lat, point.lng], {
                radius: 6,
                color,
                fillColor: color,
                fillOpacity: 0.8,
                weight: 2
            })
                .bindPopup(point.title)
                .addTo(map);
        });

        if (mapPoints.length > 0) {
            map.fitBounds(mapPoints.map((point) => [point.lat, point.lng]), { padding: [40, 40], maxZoom: 14 });
        }

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
    </script>
</body>
</html>
