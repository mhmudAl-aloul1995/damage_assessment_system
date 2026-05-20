<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHC Dashboard - Live Satellite HUD</title>
    <link rel="shortcut icon" href="{{ asset('assets/media/logos/logo_641.png') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
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
            grid-template-columns: 1fr repeat(4, minmax(52px, auto));
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

        .hud-map-popup-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 10px 0 8px;
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

        .hud-map-popup-action.is-audit {
            background: rgba(250, 232, 19, 0.12);
            border-color: rgba(250, 232, 19, 0.42);
        }

        .hud-map-popup-action.is-map {
            background: rgba(0, 255, 135, 0.12);
            border-color: rgba(0, 255, 135, 0.42);
        }

        .hud-map-popup-unit-select {
            background: rgba(0, 242, 254, 0.10);
            border: 1px solid rgba(0, 242, 254, 0.38);
            border-radius: 6px;
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 800;
            min-height: 36px;
            min-width: 170px;
            padding: 7px 10px;
        }

        .hud-map-popup-unit-select option {
            background: #081529;
            color: #ffffff;
        }

        .hud-map-filter-panel {
            background: rgba(6, 18, 36, 0.88);
            backdrop-filter: blur(14px) saturate(160%);
            -webkit-backdrop-filter: blur(14px) saturate(160%);
            border: 1px solid rgba(0, 242, 254, 0.25);
            border-radius: 8px;
            box-shadow: 0 0 22px rgba(0, 242, 254, 0.14), 0 18px 42px rgba(0, 0, 0, 0.4);
            color: #ffffff;
            flex-shrink: 0;
            max-height: min(520px, calc(100vh - 230px));
            overflow: hidden;
            position: relative;
            transition: max-height 0.22s ease, width 0.22s ease;
            width: 100%;
            z-index: 1;
        }

        .hud-map-filter-panel.is-collapsed {
            max-height: 52px;
        }

        .hud-map-filter-header {
            align-items: center;
            border-bottom: 1px solid rgba(0, 242, 254, 0.14);
            cursor: pointer;
            display: flex;
            gap: 10px;
            justify-content: space-between;
            min-height: 52px;
            padding: 10px 12px;
        }

        .hud-map-filter-title {
            align-items: center;
            color: #ffffff;
            display: flex;
            font-size: 0.9rem;
            font-weight: 800;
            gap: 8px;
            margin: 0;
        }

        .hud-map-filter-count {
            background: rgba(0, 242, 254, 0.12);
            border: 1px solid rgba(0, 242, 254, 0.2);
            border-radius: 6px;
            color: #8beeff;
            font-size: 0.72rem;
            font-weight: 800;
            padding: 4px 8px;
            white-space: nowrap;
        }

        .hud-map-filter-toggle {
            align-items: center;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 6px;
            color: #ffffff;
            display: inline-flex;
            height: 30px;
            justify-content: center;
            width: 34px;
        }

        .hud-map-filter-panel.is-collapsed .hud-map-filter-toggle i {
            transform: rotate(180deg);
        }

        .hud-map-filter-body {
            max-height: min(468px, calc(100vh - 282px));
            overflow-y: auto;
            padding: 10px 12px 0;
        }

        .hud-map-filter-field {
            margin-bottom: 8px;
        }

        .hud-map-filter-field label {
            color: #d8e8ff;
            font-size: 0.72rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .hud-map-filter-panel .form-control,
        .hud-map-filter-panel .form-select,
        .hud-map-filter-panel .select2-container--default .select2-selection--multiple {
            background-color: rgba(255, 255, 255, 0.08);
            border-color: rgba(174, 205, 255, 0.22);
            border-radius: 7px;
            color: #ffffff;
            font-size: 0.8rem;
            min-height: 34px;
        }

        .hud-map-filter-panel .form-control:focus,
        .hud-map-filter-panel .form-select:focus,
        .hud-map-filter-panel .select2-container--default.select2-container--focus .select2-selection--multiple {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: rgba(0, 242, 254, 0.7);
            box-shadow: 0 0 0 0.16rem rgba(0, 242, 254, 0.12);
            color: #ffffff;
        }

        .hud-map-filter-panel .form-control::placeholder {
            color: rgba(216, 232, 255, 0.54);
        }

        .hud-map-filter-panel .form-select option {
            background: #061224;
            color: #ffffff;
        }

        .hud-map-filter-panel .select2-container {
            width: 100% !important;
        }

        .hud-map-filter-panel .select2-container--default .select2-selection--multiple {
            padding: 2px 4px;
        }

        .hud-map-filter-panel .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background: rgba(0, 242, 254, 0.14);
            border: 1px solid rgba(0, 242, 254, 0.32);
            border-radius: 6px;
            color: #ffffff;
            font-size: 0.75rem;
            margin-top: 4px;
        }

        .hud-map-filter-panel .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #ffffff;
        }

        .select2-dropdown.hud-select2-dropdown {
            background: #061224;
            border-color: rgba(0, 242, 254, 0.32);
            color: #ffffff;
        }

        .select2-dropdown.hud-select2-dropdown .select2-results__option--highlighted.select2-results__option--selectable {
            background: rgba(0, 242, 254, 0.24);
            color: #ffffff;
        }

        .select2-dropdown.hud-select2-dropdown .select2-results__option--selected {
            background: rgba(255, 255, 255, 0.12);
        }

        .hud-map-filter-actions {
            background: linear-gradient(180deg, rgba(6, 18, 36, 0.72) 0%, rgba(6, 18, 36, 0.98) 34%);
            bottom: 0;
            display: grid;
            gap: 8px;
            grid-template-columns: 1fr 1fr;
            margin: 0 -12px;
            padding: 10px 12px 12px;
            position: sticky;
            z-index: 2;
        }

        .hud-map-filter-actions .btn {
            border-radius: 7px;
            font-size: 0.8rem;
            font-weight: 800;
            min-height: 36px;
        }

        .hud-basemap-switcher {
            align-items: center;
            background: rgba(6, 18, 36, 0.9);
            backdrop-filter: blur(14px) saturate(160%);
            -webkit-backdrop-filter: blur(14px) saturate(160%);
            border: 1px solid rgba(0, 242, 254, 0.32);
            border-radius: 8px;
            bottom: 18px;
            box-shadow: 0 0 20px rgba(0, 242, 254, 0.14), 0 14px 32px rgba(0, 0, 0, 0.36);
            color: #ffffff;
            display: flex;
            gap: 8px;
            left: 50%;
            min-width: min(360px, calc(100vw - 32px));
            padding: 8px 10px;
            pointer-events: auto;
            position: fixed;
            transform: translateX(-50%);
            z-index: 4;
        }

        .hud-basemap-switcher label {
            color: var(--neon-blue);
            font-size: 0.78rem;
            font-weight: 800;
            margin: 0;
            white-space: nowrap;
        }

        .hud-basemap-switcher .form-select {
            background-color: rgba(255, 255, 255, 0.08);
            border-color: rgba(174, 205, 255, 0.22);
            border-radius: 7px;
            color: #ffffff;
            font-size: 0.8rem;
            min-height: 34px;
        }

        .hud-basemap-switcher .form-select option {
            background: #061224;
            color: #ffffff;
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
    <div class="hud-basemap-switcher" aria-label="تغيير خريطة ArcGIS">
        <label for="hudBasemapSelect">نوع الخريطة</label>
        <select id="hudBasemapSelect" class="form-select">
            <option value="satellite">ArcGIS Satellite</option>
            <option value="hybrid">ArcGIS Hybrid</option>
            <option value="streets-vector" selected>ArcGIS Streets</option>
            <option value="topo-vector">ArcGIS Topographic</option>
            <option value="osm">OpenStreetMap</option>
            <option value="gray-vector">ArcGIS Light Gray</option>
            <option value="dark-gray-vector">ArcGIS Dark Gray</option>
        </select>
    </div>

    <div class="hud-container">
        <header class="hud-header hud-interactive">
            <div class="row align-items-center">
                <div class="col-md-8 text-start">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="hud-badge-pulse"><i class="fa-solid fa-circle-dot me-1"></i>LIVE GIS HUD</span>
                        <h1 class="hud-title-main">المجلس الفلسطيني للإسكان بالتعاون مع ال undp</h1>
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
                        <span id="hudTotalBuildings" class="hud-digital-number">{{ $formatNumber($summaryStats['total_buildings']) }}</span>
                    </div>
                    <i class="fa-solid fa-layer-group text-info fs-4 opacity-50"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-hud-glass d-flex align-items-center justify-content-between" style="border-left: 3px solid var(--neon-green);">
                    <div>
                        <span class="hud-label d-block mb-1">المباني المقيّمة ميدانياً</span>
                        <span id="hudAssessedBuildings" class="hud-digital-number text-success">{{ $formatNumber($summaryStats['assessed_buildings']) }}</span>
                    </div>
                    <i class="fa-solid fa-satellite-dish text-success fs-4 opacity-50"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-hud-glass d-flex align-items-center justify-content-between" style="border-left: 3px solid var(--neon-red);">
                    <div>
                        <span class="hud-label d-block mb-1">وحدات مدمرة كلياً</span>
                        <span id="hudFullyDamagedUnits" class="hud-digital-number text-danger">{{ $formatNumber($summaryStats['fully_damaged_units']) }}</span>
                    </div>
                    <i class="fa-solid fa-house-damage text-danger fs-4 opacity-50"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-hud-glass d-flex align-items-center justify-content-between" style="border-left: 3px solid var(--neon-yellow);">
                    <div>
                        <span class="hud-label d-block mb-1">تقديرات الركام الكلي</span>
                        <span id="hudRubbleQuantity" class="hud-digital-number text-warning">{{ $formattedRubble }} <small class="fs-6 text-muted">طن</small></span>
                    </div>
                    <i class="fa-solid fa-truck-pickup text-warning fs-4 opacity-50"></i>
                </div>
            </div>
        </div>

        <div class="hud-workspace">
            <div class="hud-sidebar hud-interactive">
                <aside id="hudMapFilterPanel" class="hud-map-filter-panel is-collapsed" aria-label="فلترة الخريطة">
                    <div id="hudMapFilterHeader" class="hud-map-filter-header">
                        <div>
                            <h2 class="hud-map-filter-title"><i class="fa-solid fa-filter"></i> فلترة الخريطة</h2>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="hud-map-filter-count">نتائج الخريطة: <span id="hudMapFilterCount">0</span> مبنى</span>
                            <button type="button" id="hudMapFilterToggle" class="hud-map-filter-toggle" aria-label="إظهار أو إخفاء فلتر الخريطة">
                                <i class="fa-solid fa-chevron-up"></i>
                            </button>
                        </div>
                    </div>

                    <div class="hud-map-filter-body">
                        <div class="hud-map-filter-field">
                            <label for="hud_filter_assignedto">المهندس الميداني</label>
                            <select id="hud_filter_assignedto" class="form-select hud-map-filter-select hud-map-filter-multiple" data-field="assignedto" data-placeholder="الكل" multiple>
                            </select>
                        </div>

                        <div class="hud-map-filter-field">
                            <label for="hud_filter_building_name">اسم المبنى</label>
                            <input type="text" id="hud_filter_building_name" class="form-control" placeholder="اسم المبنى">
                        </div>

                        <div class="hud-map-filter-field">
                            <label for="hud_filter_field_status">حالة الإستبيان</label>
                            <select id="hud_filter_field_status" class="form-select hud-map-filter-multiple" data-field="field_status" data-placeholder="الكل" multiple>
                                <option value="COMPLETED">مكتمل</option>
                                <option value="Not_Completed">غير مكتمل</option>
                            </select>
                        </div>

                        <div class="hud-map-filter-field">
                            <label for="hud_filter_building_damage_status">حالة الضرر</label>
                            <select id="hud_filter_building_damage_status" class="form-select hud-map-filter-select hud-map-filter-multiple" data-field="building_damage_status" data-placeholder="الكل" multiple>
                            </select>
                        </div>

                        <div class="hud-map-filter-field">
                            <label for="hud_filter_security_priority">يوجد عائق</label>
                            <div class="form-check form-switch text-white-50">
                                <input type="checkbox" id="hud_filter_security_priority" class="form-check-input">
                                <label class="form-check-label" for="hud_filter_security_priority">السيمبولجي الأزرق</label>
                            </div>
                        </div>

                        <div class="hud-map-filter-field">
                            <label for="hud_filter_municipalitie">البلدية</label>
                            <select id="hud_filter_municipalitie" class="form-select hud-map-filter-select hud-map-filter-multiple" data-field="municipalitie" data-placeholder="الكل" multiple>
                            </select>
                        </div>

                        <div class="hud-map-filter-field">
                            <label for="hud_filter_neighborhood">الحي</label>
                            <select id="hud_filter_neighborhood" class="form-select hud-map-filter-select hud-map-filter-multiple" data-field="neighborhood" data-placeholder="الكل" multiple>
                            </select>
                        </div>

                        <div class="hud-map-filter-field">
                            <label for="hud_filter_search">بحث ObjectID / GlobalID</label>
                            <input type="text" id="hud_filter_search" class="form-control" placeholder="ObjectID / GlobalID">
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <div class="hud-map-filter-field">
                                    <label for="hud_filter_from_date">من تاريخ</label>
                                    <input type="date" id="hud_filter_from_date" class="form-control">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="hud-map-filter-field">
                                    <label for="hud_filter_to_date">إلى تاريخ</label>
                                    <input type="date" id="hud_filter_to_date" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="hud-map-filter-actions">
                            <button type="button" id="hudMapFilterApply" class="btn btn-primary">بحث</button>
                            <button type="button" id="hudMapFilterReset" class="btn btn-light">إعادة تعيين</button>
                        </div>
                    </div>
                </aside>

                <div class="card-hud-glass">
                    <div class="hud-section-title"><i class="fa-solid fa-building"></i> تحليل المباني حسب حالة الضرر</div>
                    <div style="position: relative; height: 135px;">
                        <canvas id="hudBuildingDamageChart"></canvas>
                    </div>
                    <div class="p-2 text-center border-top border-secondary mt-2" style="background: rgba(255, 255, 255, 0.03);">
                        <small class="text-white-50">إجمالي المباني المفلترة: <span id="hudBuildingChartTotal" class="text-info fw-bold">{{ $formatNumber(array_sum($buildingDamageChart['data'])) }}</span></small>
                    </div>
                </div>

                <div class="card-hud-glass">
                    <div class="hud-section-title"><i class="fa-solid fa-chart-pie"></i> تحليل الوحدات حسب حالة الضرر</div>
                    <div style="position: relative; height: 135px;">
                        <canvas id="hudDoughnutChart"></canvas>
                    </div>
                    <div class="p-2 text-center border-top border-secondary mt-2" style="background: rgba(255, 255, 255, 0.03);">
                        <small class="text-white-50">إجمالي الوحدات التابعة للمباني المفلترة: <span id="hudUnitChartTotal" class="text-info fw-bold">{{ $formatNumber(array_sum($damageChart['data'])) }}</span></small>
                    </div>
                </div>

                <div class="card-hud-glass">
                    <div class="hud-section-title"><i class="fa-solid fa-shield-halved"></i> تقييم السلامة الإنشائية للوحدات</div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small opacity-75">
                            <span>مدمرة كلياً</span>
                            <span id="hudSafetyDestroyedValue" class="text-danger fw-bold">{{ $safetyStats['destroyed'] }}%</span>
                        </div>
                        <div class="cyber-progress text-danger"><div id="hudSafetyDestroyedBar" class="cyber-progress-fill bg-danger" style="width: {{ $safetyStats['destroyed'] }}%"></div></div>
                    </div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small opacity-75">
                            <span>تحتاج تدعيم إنشائي</span>
                            <span id="hudSafetySupportValue" class="text-warning fw-bold">{{ $safetyStats['support_needed'] }}%</span>
                        </div>
                        <div class="cyber-progress text-warning"><div id="hudSafetySupportBar" class="cyber-progress-fill bg-warning" style="width: {{ $safetyStats['support_needed'] }}%"></div></div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between small opacity-75">
                            <span>صالحة للسكن</span>
                            <span id="hudSafetyHabitableValue" class="text-success fw-bold">{{ $safetyStats['habitable'] }}%</span>
                        </div>
                        <div class="cyber-progress text-success"><div id="hudSafetyHabitableBar" class="cyber-progress-fill bg-success" style="width: {{ $safetyStats['habitable'] }}%"></div></div>
                    </div>
                </div>
            </div>

            <div class="hud-center-space"></div>

            <div class="hud-sidebar hud-interactive">
                <div class="card-hud-glass" style="flex: 1; display: flex; flex-direction: column;">
                    <div class="hud-section-title"><i class="fa-solid fa-globe"></i> تقارير البلديات والأحياء</div>
                    <ul class="nav nav-pills nav-fill gap-2 mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-2 fw-bold" id="hudBuildingReportsTab" data-bs-toggle="tab" data-bs-target="#hudBuildingReportsPane" type="button" role="tab">المباني</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-2 fw-bold" id="hudUnitReportsTab" data-bs-toggle="tab" data-bs-target="#hudUnitReportsPane" type="button" role="tab">الوحدات</button>
                        </li>
                    </ul>
                    <div class="tab-content" style="flex: 1; min-height: 0;">
                        <div id="hudBuildingReportsPane" class="tab-pane fade show active h-100" role="tabpanel" aria-labelledby="hudBuildingReportsTab">
                            <div id="hudBuildingMunicipalityReports" style="height: 100%; overflow-y: auto;">
                                @forelse ($buildingMunicipalityReports as $report)
                            <section class="governorate-report">
                                <div class="governorate-report-header">
                                    <div class="governorate-report-name">{{ $report['name'] }}</div>
                                    <div class="governorate-report-metric">
                                        <span>مبانٍ</span>
                                        <strong>{{ $formatNumber($report['summary']['assessed']) }}</strong>
                                    </div>
                                    <div class="governorate-report-metric">
                                        <span>جزئي</span>
                                        <strong>{{ $formatNumber($report['summary']['partial']) }}</strong>
                                    </div>
                                    <div class="governorate-report-metric">
                                        <span>مدمر</span>
                                        <strong class="text-danger">{{ $formatNumber($report['summary']['destroyed']) }}</strong>
                                    </div>
                                    <div class="governorate-report-metric">
                                        <span>عائق</span>
                                        <strong>{{ $formatNumber($report['summary']['obstacle']) }}</strong>
                                    </div>
                                </div>

                                <div class="governorate-report-body">
                                    <div class="municipality-chart-wrap">
                                        <canvas id="buildingMunicipalityChart{{ $loop->index }}"></canvas>
                                    </div>

                                    <table class="table table-sm table-cyber align-middle mb-0 text-center">
                                        <thead>
                                            <tr>
                                                <th class="text-start">الحي</th>
                                                <th>مبانٍ</th>
                                                <th>مدمر</th>
                                                <th>جزئي</th>
                                                <th>لجنة</th>
                                                <th>عائق</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($report['neighborhoods'] as $neighborhood)
                                                <tr>
                                                    <td class="text-start fw-bold text-info">{{ $neighborhood['name'] }}</td>
                                                    <td>{{ $formatNumber($neighborhood['assessed']) }}</td>
                                                    <td class="text-danger fw-bold">{{ $formatNumber($neighborhood['destroyed']) }}</td>
                                                    <td>{{ $formatNumber($neighborhood['partial']) }}</td>
                                                    <td>{{ $formatNumber($neighborhood['committee']) }}</td>
                                                    <td>{{ $formatNumber($neighborhood['obstacle']) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-white-50 py-3">لا توجد أحياء لهذه المحافظة</td>
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
                        </div>
                        <div id="hudUnitReportsPane" class="tab-pane fade h-100" role="tabpanel" aria-labelledby="hudUnitReportsTab">
                            <div id="hudUnitMunicipalityReports" style="height: 100%; overflow-y: auto;">
                                @forelse ($unitMunicipalityReports as $report)
                            <section class="governorate-report">
                                <div class="governorate-report-header">
                                    <div class="governorate-report-name">{{ $report['name'] }}</div>
                                    <div class="governorate-report-metric">
                                        <span>وحدات</span>
                                        <strong>{{ $formatNumber($report['summary']['units']) }}</strong>
                                    </div>
                                    <div class="governorate-report-metric">
                                        <span>جزئي</span>
                                        <strong>{{ $formatNumber($report['summary']['partial']) }}</strong>
                                    </div>
                                    <div class="governorate-report-metric">
                                        <span>مدمر</span>
                                        <strong class="text-danger">{{ $formatNumber($report['summary']['destroyed']) }}</strong>
                                    </div>
                                </div>

                                <div class="governorate-report-body">
                                    <div class="municipality-chart-wrap">
                                        <canvas id="unitMunicipalityChart{{ $loop->index }}"></canvas>
                                    </div>

                                    <table class="table table-sm table-cyber align-middle mb-0 text-center">
                                        <thead>
                                            <tr>
                                                <th class="text-start">الحي</th>
                                                <th>وحدات</th>
                                                <th>مدمر</th>
                                                <th>جزئي</th>
                                                <th>لجنة</th>
                                                <th>غير مصنف</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($report['neighborhoods'] as $neighborhood)
                                                <tr>
                                                    <td class="text-start fw-bold text-info">{{ $neighborhood['name'] }}</td>
                                                    <td>{{ $formatNumber($neighborhood['units']) }}</td>
                                                    <td class="text-danger fw-bold">{{ $formatNumber($neighborhood['destroyed']) }}</td>
                                                    <td>{{ $formatNumber($neighborhood['partial']) }}</td>
                                                    <td>{{ $formatNumber($neighborhood['committee']) }}</td>
                                                    <td>{{ $formatNumber($neighborhood['unclassified']) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-white-50 py-3">لا توجد أحياء لهذه المحافظة</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        @empty
                            <div class="text-center text-white-50 py-4">لا توجد بيانات وحدات حالياً</div>
                        @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="p-2 text-center border-top border-secondary mt-2" style="background: rgba(255, 255, 255, 0.03);">
                        <small class="text-white-50">إجمالي الوحدات التابعة للمباني المفلترة: <span id="hudAssessedUnitsTotal" class="text-info fw-bold">{{ $formatNumber(array_sum($damageChart['data'])) }}</span></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://js.arcgis.com/4.22/"></script>

    <script>
        const mapPoints = @json($mapPoints);
        const municipalityReports = @json($municipalityReports);
        const buildingMunicipalityReports = @json($buildingMunicipalityReports);
        const unitMunicipalityReports = @json($unitMunicipalityReports);
        const buildingLayerUrl = @json($buildingLayerUrl);
        const arcgisToken = @json($token);
        const assessmentBaseUrl = @json(url('assessment'));
        const auditBaseUrl = window.location.pathname.replace(/\/damageAssessment\/hud\/?$/, '/showAssessmentAudit');
        const arcgisOptionsUrl = window.location.pathname.replace(/\/hud\/?$/, '/arcgis/options');
        const hudStatsUrl = window.location.pathname.replace(/\/hud\/?$/, '/hud/stats');
        const hudBuildingUnitsUrl = window.location.pathname.replace(/\/hud\/?$/, '/hud/building-units');

        function hudSelectedValues(element) {
            return Array.from(element?.selectedOptions || [])
                .map(function (option) {
                    return option.value;
                })
                .filter(function (optionValue) {
                    return optionValue !== '';
                });
        }

        require([
            'esri/Map',
            'esri/views/MapView',
            'esri/layers/FeatureLayer',
            'esri/geometry/Extent',
            'esri/identity/IdentityManager',
            'esri/widgets/Legend',
            'esri/widgets/ScaleBar',
            'esri/widgets/BasemapGallery',
            'esri/widgets/Expand'
        ], function (Map, MapView, FeatureLayer, Extent, esriId, Legend, ScaleBar, BasemapGallery, Expand) {
            if (buildingLayerUrl && arcgisToken) {
                esriId.registerToken({
                    server: buildingLayerUrl,
                    token: arcgisToken,
                    expires: Date.now() + (60 * 60 * 1000)
                });
            }

            const damageRenderer = {
                type: 'unique-value',
                valueExpression: `
                    When(
                        Lower(Trim(DefaultValue($feature.assessment_obstacle, ''))) == 'yes' ||
                        Lower(Trim(DefaultValue($feature.security_situation, ''))) == 'unsafe',
                        'security_priority',
                        DefaultValue($feature.building_damage_status, '')
                    )
                `,
                valueExpressionTitle: 'حالة الضرر / يوجد عائق',
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
                        value: 'security_priority',
                        label: 'يوجد عائق',
                        symbol: {
                            type: 'simple-fill',
                            color: [0, 122, 255, 0.66],
                            outline: {
                                color: [255, 255, 255, 1],
                                width: 1.2
                            }
                        }
                    },
                    {
                        value: 'fully_damaged',
                        label: 'مدمر كلياً',
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
                        label: 'متضرر جزئياً',
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
                        label: 'مراجعة لجنة',
                        symbol: {
                            type: 'simple-fill',
                            color: [178, 92, 255, 0.52],
                            outline: {
                                color: [255, 255, 255, 0.9],
                                width: 0.9
                            }
                        }
                    }
                ]
            };
            const buildingNameLabelingInfo = [{
                labelExpressionInfo: {
                    expression: "DefaultValue($feature.building_name, '')"
                },
                labelPlacement: 'always-horizontal',
                symbol: {
                    type: 'text',
                    color: [255, 255, 255, 0.96],
                    haloColor: [3, 10, 24, 0.95],
                    haloSize: 1.6,
                    font: {
                        family: 'Cairo',
                        size: 10,
                        weight: 'bold'
                    }
                },
                where: "building_name IS NOT NULL AND building_name <> ''"
            }];

            function value(attributes, ...keys) {
                const normalizedAttributes = Object.entries(attributes).reduce(function (carry, [key, attributeValue]) {
                    carry[String(key).toLowerCase()] = attributeValue;

                    return carry;
                }, {});

                for (const key of keys) {
                    if (attributes[key] !== undefined && attributes[key] !== null && String(attributes[key]).trim() !== '') {
                        return attributes[key];
                    }

                    const normalizedKey = String(key).toLowerCase();

                    if (normalizedAttributes[normalizedKey] !== undefined && normalizedAttributes[normalizedKey] !== null && String(normalizedAttributes[normalizedKey]).trim() !== '') {
                        return normalizedAttributes[normalizedKey];
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

            function numericValue(attributes, ...keys) {
                const rawValue = value(attributes, ...keys);
                const parsedValue = Number(rawValue);

                return Number.isFinite(parsedValue) ? parsedValue : null;
            }

            function googleMapsUrl(graphic) {
                const attributes = graphic.attributes || {};
                const geometry = graphic.geometry || {};
                const point = geometry.extent?.center || geometry.centroid || geometry;
                const latitude = Number.isFinite(point.latitude)
                    ? point.latitude
                    : (Number.isFinite(point.y) ? point.y : numericValue(attributes, 'latitude', 'Latitude', 'LATITUDE'));
                const longitude = Number.isFinite(point.longitude)
                    ? point.longitude
                    : (Number.isFinite(point.x) ? point.x : numericValue(attributes, 'longitude', 'Longitude', 'LONGITUDE'));

                if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                    return '#';
                }

                return `https://www.google.com/maps?q=${latitude},${longitude}`;
            }

            function auditUrl(buildingGlobalId, housingGlobalId = null) {
                const url = `${auditBaseUrl}/${encodeURIComponent(buildingGlobalId)}`;

                return housingGlobalId ? `${url}/${encodeURIComponent(housingGlobalId)}` : url;
            }

            function populateHudUnitAuditSelect(select, buildingGlobalId) {
                if (!buildingGlobalId || buildingGlobalId === '-') {
                    select.disabled = true;
                    select.replaceChildren(new Option('لا توجد وحدات', ''));

                    return;
                }

                const url = new URL(hudBuildingUnitsUrl, window.location.origin);
                url.searchParams.set('building_globalid', buildingGlobalId);

                fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Building units request failed with status ' + response.status);
                        }

                        return response.json();
                    })
                    .then(function (data) {
                        const units = Array.isArray(data.results) ? data.results : [];
                        select.replaceChildren(new Option(units.length ? 'أسماء مالكي الوحدات' : 'لا توجد وحدات', ''));

                        units.forEach(function (unit) {
                            select.appendChild(new Option(unit.text, unit.id));
                        });

                        select.disabled = units.length === 0;
                    })
                    .catch(function (error) {
                        console.error('HUD building units failed:', error);
                        select.disabled = true;
                        select.replaceChildren(new Option('تعذر تحميل الوحدات', ''));
                    });
            }

            function buildBuildingPopup(event) {
                const attributes = event.graphic.attributes || {};
                const wrapper = document.createElement('div');
                const title = document.createElement('strong');
                const table = document.createElement('table');
                const action = document.createElement('a');
                const auditAction = document.createElement('a');
                const mapAction = document.createElement('a');
                const unitAuditSelect = document.createElement('select');
                const actions = document.createElement('div');
                const globalId = value(attributes, 'globalid', 'GlobalID', 'GLOBALID');
                const mapsUrl = googleMapsUrl(event.graphic);

                wrapper.className = 'hud-map-popup';
                title.textContent = value(attributes, 'building_name', 'Building_Name', 'name', 'NAME');
                actions.className = 'hud-map-popup-actions';

                table.append(
                    popupTableRow('Object ID', value(attributes, 'objectid', 'OBJECTID')),
                    popupTableRow('Global ID', globalId),
                    popupTableRow('Building Name', value(attributes, 'building_name', 'Building_Name', 'name', 'NAME')),
                    popupTableRow('building_damage_status', value(attributes, 'building_damage_status', 'Building_Damage_Status')),
                    popupTableRow('Assessment obstacle', value(attributes, 'assessment_obstacle', 'Assessment_Obstacle')),
                    popupTableRow('Security situation', value(attributes, 'security_situation', 'Security_Situation')),
                    popupTableRow('Field status', value(attributes, 'field_status', 'Field_Status')),
                    popupTableRow('Assigned to', value(attributes, 'assignedto', 'AssignedTo')),
                    popupTableRow('Municipality', value(attributes, 'municipalitie', 'Municipalitie')),
                    popupTableRow('Neighborhood', value(attributes, 'neighborhood', 'Neighborhood'))
                );

                action.className = 'hud-map-popup-action';
                action.target = '_blank';
                action.rel = 'noopener';
                action.href = globalId !== '-' ? `${assessmentBaseUrl}/${encodeURIComponent(globalId)}` : '#';
                action.textContent = 'فتح تفاصيل التقييم';

                auditAction.className = 'hud-map-popup-action is-audit';
                auditAction.target = '_blank';
                auditAction.rel = 'noopener';
                auditAction.href = globalId !== '-' ? auditUrl(globalId) : '#';
                auditAction.textContent = 'التدقيق';

                mapAction.className = 'hud-map-popup-action is-map';
                mapAction.target = '_blank';
                mapAction.rel = 'noopener';
                mapAction.href = mapsUrl;
                mapAction.textContent = 'Google Maps';

                unitAuditSelect.className = 'hud-map-popup-unit-select';
                unitAuditSelect.disabled = true;
                unitAuditSelect.appendChild(new Option('تحميل الوحدات...', ''));
                unitAuditSelect.addEventListener('change', function (selectEvent) {
                    const housingGlobalId = selectEvent.target.value;

                    if (housingGlobalId && globalId !== '-') {
                        window.location.href = auditUrl(globalId, housingGlobalId);
                        selectEvent.target.value = '';
                    }
                });

                actions.append(action, auditAction, mapAction, unitAuditSelect);
                wrapper.append(title, actions, table);
                populateHudUnitAuditSelect(unitAuditSelect, globalId);

                return wrapper;
            }

            let hudArcgisDateField = null;

            function escapeArcgisValue(value) {
                return String(value).replace(/'/g, "''");
            }

            function getArcgisField(fieldName) {
                const fields = buildingsLayer.fields || [];

                return fields.find(function (field) {
                    return String(field.name).toLowerCase() === String(fieldName).toLowerCase();
                }) || null;
            }

            function hudArcgisFieldName(fieldName) {
                return getArcgisField(fieldName)?.name || fieldName;
            }

            function resolveHudArcgisDateField() {
                if (hudArcgisDateField) {
                    return hudArcgisDateField;
                }

                hudArcgisDateField = getArcgisField('end')
                    || getArcgisField('editdate')
                    || getArcgisField('creationdate');

                return hudArcgisDateField;
            }

            function hudArcgisFieldExpression(field) {
                return String(field.name).toLowerCase() === 'end'
                    ? '"' + field.name + '"'
                    : field.name;
            }

            function hudArcgisDateExpression(field, operator, value) {
                const fieldExpression = hudArcgisFieldExpression(field);

                if (String(field.type).toLowerCase().includes('date')) {
                    return fieldExpression + " " + operator + " TIMESTAMP '" + value + " 00:00:00'";
                }

                return fieldExpression + " " + operator + " '" + escapeArcgisValue(value) + "'";
            }

            function hudArcgisInExpression(field, values) {
                const escapedValues = values
                    .map(function (fieldValue) {
                        return "'" + escapeArcgisValue(fieldValue) + "'";
                    })
                    .join(', ');

                return field + ' IN (' + escapedValues + ')';
            }

            function hudArcgisSecurityPriorityExpression() {
                const clauses = [];
                const obstacleField = getArcgisField('assessment_obstacle');
                const securityField = getArcgisField('security_situation');

                if (obstacleField) {
                    clauses.push(hudArcgisInExpression(obstacleField.name, ['yes', 'Yes', 'YES']));
                }

                if (securityField) {
                    clauses.push(hudArcgisInExpression(securityField.name, ['Unsafe', 'unsafe', 'UNSAFE']));
                }

                return clauses.length ? '(' + clauses.join(' OR ') + ')' : '1=0';
            }

            function buildHudArcgisWhere() {
                const clauses = [];
                const allowedFields = [
                    'assignedto',
                    'field_status',
                    'building_damage_status',
                    'municipalitie',
                    'neighborhood'
                ];

                allowedFields.forEach(function (field) {
                    const element = document.querySelector('[data-field="' + field + '"]');
                    const fieldValues = hudSelectedValues(element);
                    const arcgisField = hudArcgisFieldName(field);

                    if (fieldValues.length === 1) {
                        clauses.push(arcgisField + " = '" + escapeArcgisValue(fieldValues[0]) + "'");
                    } else if (fieldValues.length > 1) {
                        clauses.push(hudArcgisInExpression(arcgisField, fieldValues));
                    }
                });

                if (document.getElementById('hud_filter_security_priority')?.checked) {
                    clauses.push(hudArcgisSecurityPriorityExpression());
                }

                const buildingNameValue = (document.getElementById('hud_filter_building_name')?.value || '').trim();

                if (buildingNameValue !== '') {
                    clauses.push(hudArcgisFieldName('building_name') + " LIKE '%" + escapeArcgisValue(buildingNameValue) + "%'");
                }

                const searchValue = (document.getElementById('hud_filter_search')?.value || '').trim();

                if (searchValue !== '') {
                    if (/^\d+$/.test(searchValue)) {
                        clauses.push(hudArcgisFieldName('objectid') + ' = ' + parseInt(searchValue, 10));
                    } else {
                        clauses.push(hudArcgisFieldName('globalid') + " LIKE '%" + escapeArcgisValue(searchValue) + "%'");
                    }
                }

                const dateField = resolveHudArcgisDateField();
                const fromDate = document.getElementById('hud_filter_from_date')?.value || '';
                const toDate = document.getElementById('hud_filter_to_date')?.value || '';

                if (dateField && fromDate) {
                    clauses.push(hudArcgisDateExpression(dateField, '>=', fromDate));
                }

                if (dateField && toDate) {
                    clauses.push(hudArcgisDateExpression(dateField, '<=', toDate));
                }

                return clauses.length ? clauses.join(' AND ') : '1=1';
            }

            function updateHudFilterCount(whereExpression) {
                const countElement = document.getElementById('hudMapFilterCount');
                const query = buildingsLayer.createQuery();
                query.where = whereExpression || '1=1';
                query.returnGeometry = false;

                return buildingsLayer.queryFeatureCount(query)
                    .then(function (count) {
                        countElement.textContent = count.toLocaleString('en-US');

                        return count;
                    })
                    .catch(function (error) {
                        console.error('HUD ArcGIS count query failed:', error);
                        countElement.textContent = '0';

                        return 0;
                    });
            }

            function applyHudMapFilters() {
                const whereExpression = buildHudArcgisWhere();
                const query = buildingsLayer.createQuery();
                query.where = whereExpression;
                query.returnGeometry = true;
                buildingsLayer.definitionExpression = whereExpression;
                refreshHudDashboardData();

                Promise.all([
                    buildingsLayer.queryFeatureCount(query),
                    buildingsLayer.queryExtent(query)
                ]).then(function (results) {
                    const count = results[0];
                    const extentResult = results[1];

                    document.getElementById('hudMapFilterCount').textContent = count.toLocaleString('en-US');

                    if (count > 0 && extentResult.extent) {
                        view.goTo(extentResult.extent.expand(1.18)).catch(function (error) {
                            if (error.name !== 'AbortError') {
                                console.error('HUD filtered goTo failed:', error);
                            }
                        });
                    }
                }).catch(function (error) {
                    console.error('HUD ArcGIS filter failed:', error);
                    document.getElementById('hudMapFilterCount').textContent = '0';
                });
            }

            function resetHudMapFilters() {
                document.querySelectorAll('#hudMapFilterPanel select').forEach(function (select) {
                    if (select.multiple) {
                        Array.from(select.options).forEach(function (option) {
                            option.selected = false;
                        });
                    } else {
                        select.value = '';
                    }
                });
                if (window.jQuery) {
                    $('#hudMapFilterPanel .hud-map-filter-multiple').val(null).trigger('change.select2');
                }
                document.getElementById('hud_filter_building_name').value = '';
                document.getElementById('hud_filter_search').value = '';
                document.getElementById('hud_filter_security_priority').checked = false;
                document.getElementById('hud_filter_from_date').value = '';
                document.getElementById('hud_filter_to_date').value = '';

                buildingsLayer.definitionExpression = '1=1';
                updateHudFilterCount('1=1');
                refreshHudDashboardData();
                view.goTo(gazaStripExtent, { duration: 900 }).catch(function (error) {
                    if (error.name !== 'AbortError') {
                        console.error('HUD reset goTo failed:', error);
                    }
                });
            }

            function loadHudFilterSelectOptions(select) {
                const field = select.dataset.field;
                const url = new URL(arcgisOptionsUrl, window.location.origin);
                url.searchParams.set('field', field);
                select.disabled = true;

                fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Options request failed with status ' + response.status);
                        }

                        return response.json();
                    })
                    .then(function (data) {
                        const options = Array.isArray(data) ? data : (data.results || []);
                        select.innerHTML = '';

                        options.forEach(function (option) {
                            const choice = document.createElement('option');
                            choice.value = option.id;
                            choice.textContent = option.text;
                            select.appendChild(choice);
                        });

                        if (window.jQuery && $(select).hasClass('select2-hidden-accessible')) {
                            $(select).trigger('change.select2');
                        }
                    })
                    .catch(function (error) {
                        console.error('HUD ArcGIS options failed for ' + field + ':', error);
                    })
                    .finally(function () {
                        select.disabled = false;
                    });
            }

            function initializeHudMapFilters() {
                const panel = document.getElementById('hudMapFilterPanel');
                const header = document.getElementById('hudMapFilterHeader');
                const toggle = document.getElementById('hudMapFilterToggle');

                if (window.jQuery && $.fn.select2) {
                    $('#hudMapFilterPanel .hud-map-filter-multiple').select2({
                        allowClear: true,
                        closeOnSelect: false,
                        dir: 'rtl',
                        dropdownCssClass: 'hud-select2-dropdown',
                        placeholder: function () {
                            return $(this).data('placeholder') || 'الكل';
                        },
                        width: '100%'
                    });
                }

                document.querySelectorAll('.hud-map-filter-select').forEach(loadHudFilterSelectOptions);
                document.getElementById('hudMapFilterApply').addEventListener('click', applyHudMapFilters);
                document.getElementById('hudMapFilterReset').addEventListener('click', resetHudMapFilters);

                header.addEventListener('click', function (event) {
                    if (!event.target.closest('select, input, button')) {
                        panel.classList.toggle('is-collapsed');
                    }
                });

                toggle.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    panel.classList.toggle('is-collapsed');
                });

                buildingsLayer.load()
                    .then(function () {
                        return updateHudFilterCount(buildingsLayer.definitionExpression || '1=1');
                    })
                    .catch(function (error) {
                        console.error('HUD filter initialization failed:', error);
                    });
            }

            const buildingsLayer = new FeatureLayer({
                url: buildingLayerUrl,
                renderer: damageRenderer,
                labelingInfo: buildingNameLabelingInfo,
                labelsVisible: true,
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
                basemap: 'streets-vector',
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

            const basemapGallery = new BasemapGallery({
                view
            });

            view.ui.add(new Expand({
                view,
                content: basemapGallery,
                expandIconClass: 'esri-icon-basemap',
                expandTooltip: 'ArcGIS basemaps'
            }), 'top-left');

            document.getElementById('hudBasemapSelect')?.addEventListener('change', function (event) {
                map.basemap = event.target.value;
            });

            view.when(function () {
                view.goTo(gazaStripExtent, { duration: 1200 }).catch(function () {});
                initializeHudMapFilters();
            });
        });

        function hudChartOptions(legendPosition = 'left') {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: legendPosition,
                        labels: {
                            color: '#8fa0b7',
                            font: { family: 'Cairo', size: 10, weight: '600' },
                            boxWidth: 10
                        }
                    }
                },
                cutout: '72%'
            };
        }

        const ctxBuildingDamage = document.getElementById('hudBuildingDamageChart').getContext('2d');
        const hudBuildingDamageChart = new Chart(ctxBuildingDamage, {
            type: 'doughnut',
            data: {
                labels: @json($buildingDamageChart['labels']),
                datasets: [{
                    data: @json($buildingDamageChart['data']),
                    backgroundColor: ['#ff0055', '#fae813', '#00f2fe', '#b25cff'],
                    borderColor: '#061224',
                    borderWidth: 2
                }]
            },
            options: hudChartOptions('left')
        });

        const ctxDoughnut = document.getElementById('hudDoughnutChart').getContext('2d');
        const hudDoughnutChart = new Chart(ctxDoughnut, {
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
            options: hudChartOptions('left')
        });

        const hudBuildingMunicipalityChartLabels = ['مدمر', 'جزئي', 'لجنة', 'عائق'];
        const hudUnitMunicipalityChartLabels = ['مدمر', 'جزئي', 'لجنة', 'غير مصنف'];
        const hudBuildingMunicipalityChartColors = ['#ff0055', '#fae813', '#00f2fe', '#b25cff'];
        const hudUnitMunicipalityChartColors = ['#ff0055', '#fae813', '#00f2fe', '#00ff87'];
        let hudMunicipalityCharts = [];

        function formatHudNumber(value) {
            return Number(value || 0).toLocaleString('en-US');
        }

        function formatHudRubble(value) {
            const numericValue = Number(value || 0);

            if (numericValue >= 1000000) {
                return (numericValue / 1000000).toFixed(1) + 'M <small class="fs-6 text-muted">طن</small>';
            }

            return formatHudNumber(numericValue) + ' <small class="fs-6 text-muted">طن</small>';
        }

        function escapeHudHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, function (character) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[character];
            });
        }

        function currentHudFilterParams() {
            const params = new URLSearchParams();

            document.querySelectorAll('#hudMapFilterPanel [data-field]').forEach(function (element) {
                hudSelectedValues(element).forEach(function (fieldValue) {
                    params.append(element.dataset.field + '[]', fieldValue);
                });

                if (!element.multiple && element.value) {
                    params.set(element.dataset.field, element.value);
                }
            });

            const fields = {
                building_name: document.getElementById('hud_filter_building_name')?.value || '',
                search: document.getElementById('hud_filter_search')?.value || '',
                security_priority: document.getElementById('hud_filter_security_priority')?.checked ? '1' : '',
                from_date: document.getElementById('hud_filter_from_date')?.value || '',
                to_date: document.getElementById('hud_filter_to_date')?.value || ''
            };

            Object.entries(fields).forEach(function ([field, value]) {
                if (String(value).trim() !== '') {
                    params.set(field, value);
                }
            });

            return params;
        }

        function updateHudSafetyMetric(key, value) {
            const textElement = document.getElementById(`hudSafety${key}Value`);
            const barElement = document.getElementById(`hudSafety${key}Bar`);
            const percentage = Number(value || 0);

            if (textElement) {
                textElement.textContent = percentage + '%';
            }

            if (barElement) {
                barElement.style.width = percentage + '%';
            }
        }

        function createHudMunicipalityCharts(reports, chartPrefix, labels, colors) {
            reports.forEach((report, index) => {
                const canvas = document.getElementById(`${chartPrefix}${index}`);

                if (!canvas) {
                    return;
                }

                hudMunicipalityCharts.push(new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            data: report.chart,
                            backgroundColor: colors,
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
                }));
            });
        }

        function renderHudBuildingMunicipalityReports(reports) {
            const container = document.getElementById('hudBuildingMunicipalityReports');

            if (!container) {
                return;
            }

            if (!Array.isArray(reports) || reports.length === 0) {
                container.innerHTML = '<div class="text-center text-white-50 py-4">لا توجد بيانات مبانٍ حالياً</div>';

                return;
            }

            container.innerHTML = reports.map(function (report, index) {
                const neighborhoods = Array.isArray(report.neighborhoods) && report.neighborhoods.length > 0
                    ? report.neighborhoods.map(function (neighborhood) {
                        return `
                            <tr>
                                <td class="text-start fw-bold text-info">${escapeHudHtml(neighborhood.name)}</td>
                                <td>${formatHudNumber(neighborhood.assessed)}</td>
                                <td class="text-danger fw-bold">${formatHudNumber(neighborhood.destroyed)}</td>
                                <td>${formatHudNumber(neighborhood.partial)}</td>
                                <td>${formatHudNumber(neighborhood.committee)}</td>
                                <td>${formatHudNumber(neighborhood.obstacle)}</td>
                            </tr>
                        `;
                    }).join('')
                    : '<tr><td colspan="6" class="text-white-50 py-3">لا توجد أحياء لهذه المحافظة</td></tr>';

                return `
                    <section class="governorate-report">
                        <div class="governorate-report-header">
                            <div class="governorate-report-name">${escapeHudHtml(report.name)}</div>
                            <div class="governorate-report-metric">
                                <span>مبانٍ</span>
                                <strong>${formatHudNumber(report.summary?.assessed)}</strong>
                            </div>
                            <div class="governorate-report-metric">
                                <span>جزئي</span>
                                <strong>${formatHudNumber(report.summary?.partial)}</strong>
                            </div>
                            <div class="governorate-report-metric">
                                <span>مدمر</span>
                                <strong class="text-danger">${formatHudNumber(report.summary?.destroyed)}</strong>
                            </div>
                            <div class="governorate-report-metric">
                                <span>عائق</span>
                                <strong>${formatHudNumber(report.summary?.obstacle)}</strong>
                            </div>
                        </div>

                        <div class="governorate-report-body">
                            <div class="municipality-chart-wrap">
                                <canvas id="buildingMunicipalityChart${index}"></canvas>
                            </div>

                            <table class="table table-sm table-cyber align-middle mb-0 text-center">
                                <thead>
                                    <tr>
                                        <th class="text-start">الحي</th>
                                        <th>مبانٍ</th>
                                        <th>مدمر</th>
                                        <th>جزئي</th>
                                        <th>لجنة</th>
                                        <th>عائق</th>
                                    </tr>
                                </thead>
                                <tbody>${neighborhoods}</tbody>
                            </table>
                        </div>
                    </section>
                `;
            }).join('');
        }

        function renderHudUnitMunicipalityReports(reports) {
            const container = document.getElementById('hudUnitMunicipalityReports');

            if (!container) {
                return;
            }

            if (!Array.isArray(reports) || reports.length === 0) {
                container.innerHTML = '<div class="text-center text-white-50 py-4">لا توجد بيانات وحدات حالياً</div>';

                return;
            }

            container.innerHTML = reports.map(function (report, index) {
                const neighborhoods = Array.isArray(report.neighborhoods) && report.neighborhoods.length > 0
                    ? report.neighborhoods.map(function (neighborhood) {
                        return `
                            <tr>
                                <td class="text-start fw-bold text-info">${escapeHudHtml(neighborhood.name)}</td>
                                <td>${formatHudNumber(neighborhood.units)}</td>
                                <td class="text-danger fw-bold">${formatHudNumber(neighborhood.destroyed)}</td>
                                <td>${formatHudNumber(neighborhood.partial)}</td>
                                <td>${formatHudNumber(neighborhood.committee)}</td>
                                <td>${formatHudNumber(neighborhood.unclassified)}</td>
                            </tr>
                        `;
                    }).join('')
                    : '<tr><td colspan="6" class="text-white-50 py-3">لا توجد أحياء لهذه المحافظة</td></tr>';

                return `
                    <section class="governorate-report">
                        <div class="governorate-report-header">
                            <div class="governorate-report-name">${escapeHudHtml(report.name)}</div>
                            <div class="governorate-report-metric">
                                <span>وحدات</span>
                                <strong>${formatHudNumber(report.summary?.units)}</strong>
                            </div>
                            <div class="governorate-report-metric">
                                <span>جزئي</span>
                                <strong>${formatHudNumber(report.summary?.partial)}</strong>
                            </div>
                            <div class="governorate-report-metric">
                                <span>مدمر</span>
                                <strong class="text-danger">${formatHudNumber(report.summary?.destroyed)}</strong>
                            </div>
                        </div>

                        <div class="governorate-report-body">
                            <div class="municipality-chart-wrap">
                                <canvas id="unitMunicipalityChart${index}"></canvas>
                            </div>

                            <table class="table table-sm table-cyber align-middle mb-0 text-center">
                                <thead>
                                    <tr>
                                        <th class="text-start">الحي</th>
                                        <th>وحدات</th>
                                        <th>مدمر</th>
                                        <th>جزئي</th>
                                        <th>لجنة</th>
                                        <th>غير مصنف</th>
                                    </tr>
                                </thead>
                                <tbody>${neighborhoods}</tbody>
                            </table>
                        </div>
                    </section>
                `;
            }).join('');
        }

        function renderHudMunicipalityReports(buildingReports, unitReports) {
            hudMunicipalityCharts.forEach(function (chart) {
                chart.destroy();
            });
            hudMunicipalityCharts = [];
            renderHudBuildingMunicipalityReports(buildingReports);
            renderHudUnitMunicipalityReports(unitReports);
            createHudMunicipalityCharts(buildingReports || [], 'buildingMunicipalityChart', hudBuildingMunicipalityChartLabels, hudBuildingMunicipalityChartColors);
            createHudMunicipalityCharts(unitReports || [], 'unitMunicipalityChart', hudUnitMunicipalityChartLabels, hudUnitMunicipalityChartColors);
        }

        function refreshHudDashboardData() {
            const url = new URL(hudStatsUrl, window.location.origin);
            const params = currentHudFilterParams();

            params.forEach(function (value, key) {
                url.searchParams.append(key, value);
            });

            fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('HUD stats request failed with status ' + response.status);
                    }

                    return response.json();
                })
                .then(function (data) {
                    document.getElementById('hudTotalBuildings').textContent = formatHudNumber(data.summaryStats.total_buildings);
                    document.getElementById('hudAssessedBuildings').textContent = formatHudNumber(data.summaryStats.assessed_buildings);
                    document.getElementById('hudFullyDamagedUnits').textContent = formatHudNumber(data.summaryStats.fully_damaged_units);
                    document.getElementById('hudRubbleQuantity').innerHTML = formatHudRubble(data.summaryStats.rubble_quantity);
                    document.getElementById('hudAssessedUnitsTotal').textContent = formatHudNumber(data.assessedUnitsTotal);
                    document.getElementById('hudBuildingChartTotal').textContent = formatHudNumber(data.buildingDamageChart.data.reduce((total, value) => total + Number(value || 0), 0));
                    document.getElementById('hudUnitChartTotal').textContent = formatHudNumber(data.assessedUnitsTotal);

                    hudBuildingDamageChart.data.datasets[0].data = data.buildingDamageChart.data;
                    hudBuildingDamageChart.update();
                    hudDoughnutChart.data.datasets[0].data = data.damageChart.data;
                    hudDoughnutChart.update();

                    updateHudSafetyMetric('Destroyed', data.safetyStats.destroyed);
                    updateHudSafetyMetric('Support', data.safetyStats.support_needed);
                    updateHudSafetyMetric('Habitable', data.safetyStats.habitable);
                    renderHudMunicipalityReports(data.buildingMunicipalityReports, data.unitMunicipalityReports);
                })
                .catch(function (error) {
                    console.error('HUD stats refresh failed:', error);
                });
        }

        createHudMunicipalityCharts(buildingMunicipalityReports, 'buildingMunicipalityChart', hudBuildingMunicipalityChartLabels, hudBuildingMunicipalityChartColors);
        createHudMunicipalityCharts(unitMunicipalityReports, 'unitMunicipalityChart', hudUnitMunicipalityChartLabels, hudUnitMunicipalityChartColors);

    </script>
</body>
</html>
