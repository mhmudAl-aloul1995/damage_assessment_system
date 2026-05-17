@extends('layouts.app')

@section('title', 'PHC PDF Report')
@section('pageName', 'PHC PDF Report')

@section('content')
    @php
        $isPdfExport = (bool) ($isPdfExport ?? false);
        $formatNumber = fn ($value) => number_format((int) round((float) $value));
        $damageTotal = max(1, array_sum(array_map(fn ($item) => (int) $item['value'], $damageDistribution)));
        $occupancyTotal = max(1, array_sum(array_map(fn ($item) => (int) $item['value'], $occupancyDistribution)));
        $typeMax = max(1, ...array_map(fn ($item) => (int) $item['value'], $buildingTypeDistribution ?: [['value' => 1]]));
        $firstNeighborhoodPage = $neighborhoodPages[0] ?? ['governorate' => 'غير محدد', 'rows' => [], 'mapSvg' => $gazaMapSvg, 'english_name' => ''];
        $firstNeighborhoodRows = collect($firstNeighborhoodPage['rows']);
        $activeNeighborhood = $firstNeighborhoodRows->sortByDesc('housing_units')->first() ?? [
            'name' => 'غير محدد',
            'buildings' => 0,
            'housing_units' => 0,
            'fully_damaged' => 0,
            'partially_damaged' => 0,
            'committee_review' => 0,
            'occupied' => 0,
            'vacant' => 0,
        ];
        $allNeighborhoodRows = collect($neighborhoodPages)->flatMap(fn ($page) => $page['rows']);
        $topNeighborhoods = $allNeighborhoodRows->sortByDesc('housing_units')->take(8)->values();
        $topNeighborhoodMax = max(1, (int) $topNeighborhoods->max('housing_units'));
    @endphp

    <style>
        @if ($isPdfExport)
            #kt_app_header,
            #kt_app_sidebar,
            #kt_app_toolbar,
            #kt_app_footer,
            #appContainerLoading {
                display: none !important;
            }

            #kt_app_body,
            #kt_app_root,
            #kt_app_page,
            #kt_app_wrapper,
            #kt_app_main,
            #kt_app_content,
            #kt_app_content_container {
                background: #ffffff !important;
                margin: 0 !important;
                max-width: none !important;
                padding: 0 !important;
                width: 100% !important;
            }

            #kt_app_main,
            .app-main,
            .app-wrapper,
            .app-page {
                margin-inline-start: 0 !important;
                padding-inline-start: 0 !important;
            }
        @endif

        .phc-report-shell {
            direction: rtl;
            background: #f0f4f8;
            color: #24384a;
            font-family: "Droid Arabic Kufi", Tahoma, Arial, sans-serif;
            margin: {{ $isPdfExport ? '0' : '-20px' }};
            padding: {{ $isPdfExport ? '0' : '20px 20px 48px' }};
        }

        .phc-topbar {
            background: #0f3147;
            color: #fff;
            border-bottom: 4px solid #c5a02b;
            padding: 16px 22px;
            margin-bottom: 18px;
        }

        .phc-topbar-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .phc-topbar h1 {
            color: #c5a02b;
            font-size: 18px;
            font-weight: 900;
            margin: 0 0 4px;
        }

        .phc-topbar p {
            color: #d9e5ec;
            font-size: 11px;
            margin: 0;
        }

        .phc-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .phc-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            border: 0;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
        }

        .phc-btn.gold {
            background: #c5a02b;
            color: #0f3147;
        }

        .phc-btn.light {
            background: #eef5f8;
            color: #0f3147;
        }

        .phc-page {
            max-width: 1200px;
            margin: 0 auto 28px;
            background: #fff;
            border-radius: 12px;
            padding: 42px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, .06), 0 8px 10px -6px rgba(0, 0, 0, .06);
            page-break-after: always;
        }

        .phc-page:last-child {
            page-break-after: auto;
        }

        .phc-logo-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            align-items: center;
            border-bottom: 2px solid #c5a02b;
            padding-bottom: 18px;
            margin-bottom: 24px;
        }

        .phc-logo-card {
            min-height: 92px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            border-inline: 1px solid #e2e8ef;
        }

        .phc-logo-card:first-child,
        .phc-logo-card:last-child {
            border-inline: 0;
        }

        .phc-emblem {
            width: 86px;
            height: 86px;
        }

        .phc-title {
            text-align: center;
            margin: 20px 0 22px;
        }

        .phc-title h2 {
            color: #0f3147;
            font-size: 28px;
            font-weight: 900;
            margin: 0 0 8px;
        }

        .phc-title p {
            color: #c5a02b;
            font-size: 16px;
            font-weight: 800;
            margin: 0;
        }

        .phc-pill {
            display: inline-block;
            background: #f4f8fa;
            border: 1px solid #d8e4eb;
            border-radius: 999px;
            color: #5a6e7f;
            font-size: 11px;
            font-weight: 700;
            margin-top: 12px;
            padding: 6px 14px;
        }

        .phc-summary {
            background: #f4f8fa;
            border: 1px solid #d8e4eb;
            border-radius: 12px;
            color: #40586b;
            font-size: 13px;
            line-height: 2;
            padding: 18px;
            text-align: justify;
            margin-bottom: 24px;
        }

        .phc-map-section {
            background: #f4f8fa;
            border: 1px solid #d8e4eb;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 22px;
        }

        .phc-section-title {
            color: #0f3147;
            font-size: 16px;
            font-weight: 900;
            text-align: center;
            margin: 0 0 14px;
        }

        .phc-map-frame {
            background: #fff;
            border: 1px solid #d8e4eb;
            border-radius: 12px;
            padding: 12px;
            min-height: 260px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 16px;
        }

        .phc-map-frame svg {
            width: 100%;
            max-height: 300px;
        }

        .phc-grid-6,
        .phc-grid-4,
        .phc-grid-3,
        .phc-grid-2 {
            display: grid;
            gap: 14px;
        }

        .phc-grid-6 {
            grid-template-columns: repeat(6, 1fr);
        }

        .phc-grid-4 {
            grid-template-columns: repeat(4, 1fr);
        }

        .phc-grid-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        .phc-grid-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        .phc-kpi {
            background: #fff;
            border: 1px solid #d8e4eb;
            border-radius: 10px;
            padding: 14px 12px;
            text-align: center;
        }

        .phc-kpi strong {
            display: block;
            color: #0f3147;
            direction: ltr;
            font-size: 23px;
            font-weight: 900;
            line-height: 1.2;
        }

        .phc-kpi span {
            color: #5a6e7f;
            display: block;
            font-size: 10px;
            font-weight: 700;
            line-height: 1.5;
            margin-top: 5px;
        }

        .phc-kpi.blue strong {
            color: #006eb6;
        }

        .phc-kpi.green strong {
            color: #2e7d32;
        }

        .phc-kpi.orange strong {
            color: #e65100;
        }

        .phc-field-box {
            position: relative;
            border: 2px solid #c5a02b;
            border-radius: 14px;
            background: #fffdf6;
            padding: 22px 18px 18px;
            margin: 22px 0;
        }

        .phc-field-label {
            position: absolute;
            top: -14px;
            right: 28px;
            background: #fff;
            border: 2px solid #c5a02b;
            border-radius: 999px;
            color: #c5a02b;
            font-size: 12px;
            font-weight: 900;
            padding: 4px 14px;
        }

        .phc-mini-card {
            background: #fff;
            border: 1px solid #e5edf2;
            border-radius: 10px;
            padding: 14px;
            text-align: center;
        }

        .phc-mini-card strong {
            color: #0f3147;
            direction: ltr;
            display: block;
            font-size: 20px;
            font-weight: 900;
            margin-bottom: 8px;
        }

        .phc-table-wrap {
            border: 1px solid #d8e4eb;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 22px;
        }

        .phc-table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            table-layout: fixed;
        }

        .phc-table th,
        .phc-table td {
            border: 1px solid #d8e4eb;
            padding: 10px 7px;
            font-size: 11px;
            line-height: 1.5;
            vertical-align: middle;
            word-break: break-word;
        }

        .phc-table th {
            background: #0f3147;
            color: #fff;
            font-weight: 900;
        }

        .phc-table tbody tr:nth-child(even) td {
            background: #f7fafc;
        }

        .phc-table tfoot td,
        .phc-table tfoot th {
            background: #006eb6;
            color: #fff;
            font-weight: 900;
        }

        .phc-chart-card {
            background: #fff;
            border: 1px solid #d8e4eb;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            min-height: 260px;
        }

        .phc-chart-card h4 {
            color: #0f3147;
            font-size: 13px;
            font-weight: 900;
            margin: 0 0 12px;
        }

        .phc-bars {
            height: 150px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 12px;
            direction: ltr;
            border-bottom: 1px solid #d8e4eb;
            margin-bottom: 10px;
        }

        .phc-bar-item {
            width: 42px;
            text-align: center;
        }

        .phc-bar {
            width: 28px;
            min-height: 4px;
            margin: 0 auto 8px;
            border-radius: 6px 6px 0 0;
        }

        .phc-bar-label,
        .phc-legend-row {
            color: #5a6e7f;
            font-size: 9px;
            font-weight: 700;
        }

        .phc-legend {
            display: grid;
            gap: 7px;
            margin-top: 10px;
            text-align: right;
        }

        .phc-legend-row {
            display: grid;
            grid-template-columns: 12px 1fr auto;
            gap: 8px;
            align-items: center;
        }

        .phc-swatch {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .phc-donut {
            width: 142px;
            height: 142px;
            margin: 0 auto;
        }

        .phc-horizontal-row {
            display: grid;
            grid-template-columns: 70px 1fr 42px;
            gap: 8px;
            align-items: center;
            margin: 9px 0;
            font-size: 10px;
            color: #5a6e7f;
        }

        .phc-horizontal-track {
            height: 12px;
            background: #edf5fa;
            border-radius: 999px;
            overflow: hidden;
        }

        .phc-horizontal-fill {
            height: 100%;
            background: #0f3147;
        }

        .phc-neighborhood-stage {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            align-items: stretch;
            background: #fffaf4;
            border: 1px solid #f1caa9;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 22px;
        }

        .phc-neighborhood-focus {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .phc-neighborhood-focus h3 {
            color: #0f3147;
            font-size: 17px;
            font-weight: 900;
            margin: 0 0 8px;
        }

        .phc-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            border-top: 1px solid #d8e4eb;
            color: #5a6e7f;
            font-size: 11px;
            margin-top: 26px;
            padding-top: 12px;
        }

        .phc-filter {
            max-width: 1200px;
            margin: 0 auto 18px;
            background: #fff;
            border: 1px solid #d8e4eb;
            border-radius: 12px;
            padding: 18px;
        }

        .phc-filter-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        .phc-filter label {
            color: #0f3147;
            display: block;
            font-size: 11px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .phc-filter input,
        .phc-filter select {
            border: 1px solid #d8e4eb;
            border-radius: 8px;
            color: #24384a;
            min-height: 38px;
            padding: 7px 10px;
            width: 100%;
        }

        @media (max-width: 992px) {
            .phc-grid-6,
            .phc-grid-4,
            .phc-grid-3,
            .phc-grid-2,
            .phc-filter-grid,
            .phc-logo-row,
            .phc-neighborhood-stage {
                grid-template-columns: 1fr;
            }

            .phc-page {
                padding: 24px;
            }
        }

        @media print {
            body,
            .phc-report-shell {
                background: #fff !important;
            }

            .phc-report-shell {
                margin: 0;
                padding: 0;
            }

            .phc-topbar,
            .phc-filter,
            .no-print {
                display: none !important;
            }

            .phc-page {
                box-shadow: none;
                border-radius: 0;
                margin: 0;
                max-width: none;
                min-height: 190mm;
                padding: 12mm;
                width: 100%;
            }
        }
    </style>

    <div class="phc-report-shell">
        <div class="phc-topbar no-print">
            <div class="phc-topbar-inner">
                <div>
                    <h1>المجلس الفلسطيني للإسكان بالتعاون مع UNDP</h1>
                    <p>مشروع حصر الأضرار - قطاع غزة</p>
                </div>
                <div class="phc-actions">
                    <button type="button" onclick="window.print()" class="phc-btn light">طباعة الصفحة</button>
                    <a href="{{ route('damage-assessment.reports.phc.export', request()->query()) }}" class="phc-btn gold" target="_blank">Export PDF</a>
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('damage-assessment.reports.phc') }}" class="phc-filter no-print">
            <div class="phc-filter-grid">
                <div>
                    <label>تاريخ البداية</label>
                    <input type="date" name="start_date" value="{{ request('start_date') }}">
                </div>
                <div>
                    <label>تاريخ النهاية</label>
                    <input type="date" name="end_date" value="{{ request('end_date') }}">
                </div>
                <div>
                    <label>المحافظة</label>
                    <select name="governorate">
                        <option value="">كل المحافظات</option>
                        @foreach ($governorates as $governorate)
                            <option value="{{ $governorate['english_name'] }}" @selected(request('governorate') === $governorate['english_name'])>
                                {{ $governorate['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>البلدية</label>
                    <input type="text" name="municipalitie" value="{{ request('municipalitie') }}" placeholder="municipalitie">
                </div>
            </div>
            <div class="phc-actions" style="margin-top: 14px;">
                <button type="submit" class="phc-btn gold">تطبيق الفلتر</button>
                <a href="{{ route('damage-assessment.reports.phc') }}" class="phc-btn light">إعادة ضبط</a>
            </div>
        </form>

        <section class="phc-page">
            <div class="phc-logo-row">
                <div class="phc-logo-card">
                    <svg class="phc-emblem" viewBox="0 0 120 100" xmlns="http://www.w3.org/2000/svg">
                        <g fill="#c5a02b"><path d="M57 11c0-3 2.5-6 5-6 2.5 0 3.5 2 3.5 4 0 1.5-1.5 3-3.5 3-1.5 0-2.5 2-2.5 3.5H57z"/><path d="M62 9l4.5 1-2.5 2.5z"/><path d="M56 15h8l2 9H54z"/><path d="M65 21c9 2 20 11 23 37l-8 1c-2-16-10-29-16-34z"/><path d="M55 21c-9 2-20 11-23 37l8 1c2-16 10-29 16-34z"/><path d="M52 65l4 11h8l4-11-8 4z"/></g>
                        <path d="M51 24h18l-2 32q-7 6-14 0z" fill="#fff" stroke="#c5a02b" stroke-width="1.8"/>
                        <path d="M52 25.2h5.3v30.3L52 52.2z" fill="#000"/><path d="M57.3 25.2h5.4v32.3l-5.4-2z" fill="#fff"/><path d="M62.7 25.2H68v27l-5.3 5.3z" fill="#149151"/><path d="M52 25.2h16L60 34z" fill="#d32f2f"/>
                        <text x="60" y="88" font-size="8.5" font-weight="900" fill="#0f3147" text-anchor="middle">دولة فلسطين</text>
                        <text x="60" y="96" font-size="5" font-weight="800" fill="#5a6e7f" text-anchor="middle">وزارة الأشغال العامة والإسكان</text>
                    </svg>
                </div>
                <div class="phc-logo-card">
                    <svg class="phc-emblem" viewBox="0 0 140 100" xmlns="http://www.w3.org/2000/svg">
                        <path d="M30 46l28-24 28 24z" stroke="#d32f2f" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                        <line x1="37" y1="46" x2="79" y2="46" stroke="#d32f2f" stroke-width="2.5"/><line x1="43" y1="41" x2="73" y2="41" stroke="#d32f2f" stroke-width="1.8"/><line x1="49" y1="36" x2="67" y2="36" stroke="#d32f2f" stroke-width="1.8"/>
                        <path d="M72 46c0-21 30-21 30 0zM58 46c0-15 24-15 24 0z" fill="#000"/>
                        <path d="M82 24q11-11 29-2" stroke="#2e7d32" stroke-width="1.5" fill="none"/>
                        <path d="M86 21c2-3 6-3 8 0-2 2-6 2-8 0zM96 17c2-3 6-3 8 0-2 3-6 3-8 0zM104 20c2-3 6-3 8 0-2 3-6 3-8 0z" fill="#2e7d32"/>
                        <path d="M22 56h96V46h-7v5h-7v-5h-7v5h-7v-5h-7v5h-7v-5h-7v5h-7v-5h-7v5h-7v-5h-7v5h-7v-5h-6v5h-6z" fill="#000"/>
                        <text x="70" y="75" font-size="9.5" font-weight="900" fill="#000" text-anchor="middle">المجلس الفلسطيني للإسكان</text>
                        <text x="70" y="86" font-size="6.5" font-weight="700" fill="#000" text-anchor="middle">Palestinian Housing Council</text>
                    </svg>
                </div>
                <div class="phc-logo-card">
                    <svg class="phc-emblem" viewBox="0 0 100 200" xmlns="http://www.w3.org/2000/svg">
                        <rect width="100" height="100" fill="#006eb6"/>
                        <g stroke="#fff" stroke-width=".8" fill="none"><circle cx="50" cy="50" r="22"/><circle cx="50" cy="50" r="15"/><circle cx="50" cy="50" r="8"/><line x1="50" y1="28" x2="50" y2="72"/><line x1="28" y1="50" x2="72" y2="50"/><line x1="34" y1="34" x2="66" y2="66"/><line x1="34" y1="66" x2="66" y2="34"/></g>
                        <g fill="#006eb6"><rect y="103" width="48.5" height="45.5"/><rect x="51.5" y="103" width="48.5" height="45.5"/><rect y="151.5" width="48.5" height="45.5"/><rect x="51.5" y="151.5" width="48.5" height="45.5"/></g>
                        <g fill="#fff" font-weight="800" font-size="31" text-anchor="middle" dominant-baseline="central"><text x="24" y="126">U</text><text x="76" y="126">N</text><text x="24" y="174">D</text><text x="76" y="174">P</text></g>
                    </svg>
                </div>
            </div>

            <div class="phc-title">
                <h2>مشروع حصر الأضرار - قطاع غزة</h2>
                <p>المجلس الفلسطيني للإسكان بالتعاون مع برنامج الأمم المتحدة الإنمائي (UNDP)</p>
                <span class="phc-pill">تقرير المتابعة الفنية الميداني لغاية: {{ $reportDate }}</span>
            </div>

            <div class="phc-summary">
                تم إعداد هذا التقرير لعرض النتائج الميدانية لعمليات حصر وتقييم أضرار قطاع الإسكان في محافظات قطاع غزة. تعتمد المؤشرات والجداول والخرائط أدناه على البيانات الحالية في جداول المباني والوحدات السكنية والتقييمات، مع احتساب السكان المتأثرين تقديرياً على أساس متوسط حجم الأسرة 5.3 عند عدم توفر قيمة مباشرة.
            </div>

            <div class="phc-map-section">
                <h3 class="phc-section-title">خارطة التدخل والتقييم الجغرافي - قطاع غزة</h3>
                <div class="phc-map-frame">{!! $gazaMapSvg !!}</div>
                <div class="phc-grid-6">
                    <div class="phc-kpi blue"><strong>{{ $formatNumber($totals['buildings']) }}</strong><span>إجمالي المباني</span></div>
                    <div class="phc-kpi blue"><strong>{{ $formatNumber($totals['housing_units']) }}</strong><span>الوحدات السكنية</span></div>
                    <div class="phc-kpi green"><strong>{{ $formatNumber($totals['assessed_buildings']) }}</strong><span>المباني التي قُيّمت</span></div>
                    <div class="phc-kpi green"><strong>{{ $formatNumber($totals['assessed_housing_units']) }}</strong><span>الوحدات التي قُيّمت</span></div>
                    <div class="phc-kpi orange"><strong>{{ $formatNumber($totals['affected_population']) }}</strong><span>السكان المتأثرون</span></div>
                    <div class="phc-kpi"><strong>5.3</strong><span>متوسط حجم الأسرة</span></div>
                </div>
            </div>

            <div class="phc-field-box">
                <span class="phc-field-label">لهذا التقرير الفني الميداني</span>
                <div class="phc-grid-4">
                    <div class="phc-mini-card"><strong>{{ $formatNumber($totals['edited_assessments']) }}</strong><span>سجلات التعديلات</span></div>
                    <div class="phc-mini-card"><strong>{{ $formatNumber($totals['assessments']) }}</strong><span>سجلات التقييم</span></div>
                    <div class="phc-mini-card"><strong>{{ $formatNumber($totals['building_statuses']) }}</strong><span>حالات المباني</span></div>
                    <div class="phc-mini-card"><strong>{{ $formatNumber($totals['housing_statuses']) }}</strong><span>حالات الوحدات</span></div>
                </div>
            </div>

            <div class="phc-table-wrap">
                <table class="phc-table">
                    <thead>
                        <tr>
                            <th>المحافظة</th>
                            <th>إجمالي المباني</th>
                            <th>الوحدات السكنية</th>
                            <th>الوحدات المقيّمة</th>
                            <th>السكان المتأثرون</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summaryRows as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $formatNumber($row['buildings']) }}</td>
                                <td>{{ $formatNumber($row['housing_units']) }}</td>
                                <td>{{ $formatNumber($row['assessed_housing_units']) }}</td>
                                <td>{{ $formatNumber($row['affected_population']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>المجموع</th>
                            <th>{{ $formatNumber($totals['buildings']) }}</th>
                            <th>{{ $formatNumber($totals['housing_units']) }}</th>
                            <th>{{ $formatNumber($totals['assessed_housing_units']) }}</th>
                            <th>{{ $formatNumber($totals['affected_population']) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="phc-grid-3">
                <div class="phc-chart-card">
                    <h4>توزيع أضرار الوحدات السكنية المقيّمة</h4>
                    <div class="phc-bars">
                        @foreach (array_slice($damageDistribution, 0, 5) as $item)
                            <div class="phc-bar-item">
                                <div class="phc-bar" style="height: {{ max(6, ((int) $item['value'] / $damageTotal) * 140) }}px; background: {{ $item['color'] }}"></div>
                                <div class="phc-bar-label">{{ $item['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="phc-legend">
                        @foreach (array_slice($damageDistribution, 0, 5) as $item)
                            <div class="phc-legend-row"><span class="phc-swatch" style="background: {{ $item['color'] }}"></span><span>{{ $item['label'] }}</span><strong>{{ $formatNumber($item['value']) }}</strong></div>
                        @endforeach
                    </div>
                </div>

                <div class="phc-chart-card">
                    <h4>استخدام وإشغال الوحدات السكنية</h4>
                    <svg class="phc-donut" viewBox="0 0 140 140">
                        @php $offset = 0; $circumference = 314; @endphp
                        @foreach (array_slice($occupancyDistribution, 0, 5) as $item)
                            @php
                                $segmentLength = ((int) $item['value'] / $occupancyTotal) * $circumference;
                            @endphp
                            <circle cx="70" cy="70" r="50" fill="none" stroke="{{ $item['color'] }}" stroke-width="24" stroke-dasharray="{{ $segmentLength }} {{ $circumference - $segmentLength }}" stroke-dashoffset="{{ -$offset }}" transform="rotate(-90 70 70)" />
                            @php $offset += $segmentLength; @endphp
                        @endforeach
                        <circle cx="70" cy="70" r="31" fill="#fff" />
                        <text x="70" y="75" text-anchor="middle" font-size="12" fill="#006eb6" font-weight="900">{{ $formatNumber($occupancyTotal) }}</text>
                    </svg>
                    <div class="phc-legend">
                        @foreach (array_slice($occupancyDistribution, 0, 5) as $item)
                            <div class="phc-legend-row"><span class="phc-swatch" style="background: {{ $item['color'] }}"></span><span>{{ $item['label'] }}</span><strong>{{ $formatNumber($item['value']) }}</strong></div>
                        @endforeach
                    </div>
                </div>

                <div class="phc-chart-card">
                    <h4>توزيع المباني المقيّمة حسب نوع المنشأة</h4>
                    @foreach (array_slice($buildingTypeDistribution, 0, 6) as $item)
                        <div class="phc-horizontal-row">
                            <span>{{ $item['label'] }}</span>
                            <div class="phc-horizontal-track"><div class="phc-horizontal-fill" style="width: {{ max(3, ((int) $item['value'] / $typeMax) * 100) }}%; background: {{ $item['color'] }}"></div></div>
                            <strong>{{ $formatNumber($item['value']) }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>

            <footer class="phc-footer">
                <span>مشروع حصر الأضرار - قطاع غزة {{ now()->year }}</span>
                <strong>وزارة الأشغال العامة والإسكان | المجلس الفلسطيني للإسكان | UNDP</strong>
            </footer>
        </section>

        @foreach ($governorates as $governorate)
            @php
                $governorateDamageTotal = max(1, array_sum(array_map(fn ($item) => (int) $item['value'], $governorate['damage'])));
                $governorateOccupancyTotal = max(1, array_sum(array_map(fn ($item) => (int) $item['value'], $governorate['occupancy'])));
                $governorateTypeMax = max(1, ...array_map(fn ($item) => (int) $item['value'], $governorate['building_types'] ?: [['value' => 1]]));
            @endphp
            <section class="phc-page">
                <div class="phc-title">
                    <h2>محافظة {{ $governorate['name'] }}</h2>
                    <p>تقرير التقييمات التفصيلية للمحافظات والبلديات</p>
                </div>

                <div class="phc-grid-4" style="margin-bottom: 22px;">
                    <div class="phc-kpi blue"><strong>{{ $formatNumber($governorate['totals']['buildings']) }}</strong><span>إجمالي عدد المباني</span></div>
                    <div class="phc-kpi blue"><strong>{{ $formatNumber($governorate['totals']['housing_units']) }}</strong><span>الوحدات السكنية</span></div>
                    <div class="phc-kpi green"><strong>{{ $formatNumber($governorate['totals']['assessed_housing_units']) }}</strong><span>الوحدات المقيّمة</span></div>
                    <div class="phc-kpi orange"><strong>{{ $formatNumber($governorate['totals']['affected_population']) }}</strong><span>السكان المتأثرون</span></div>
                </div>

                <div class="phc-neighborhood-stage">
                    <div class="phc-map-frame" style="margin-bottom: 0;">{!! $governorate['mapSvg'] !!}</div>
                    <div>
                        <h3 class="phc-section-title">مؤشرات الضرر في المحافظة</h3>
                        <div class="phc-grid-2">
                            @foreach (array_slice($governorate['damage'], 0, 4) as $item)
                                <div class="phc-kpi"><strong style="color: {{ $item['color'] }}">{{ $formatNumber($item['value']) }}</strong><span>{{ $item['label'] }}</span></div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="phc-grid-3" style="margin-bottom: 22px;">
                    <div class="phc-chart-card">
                        <h4>توزيع أضرار الوحدات السكنية المقيّمة</h4>
                        <div class="phc-bars">
                            @foreach (array_slice($governorate['damage'], 0, 5) as $item)
                                <div class="phc-bar-item">
                                    <div class="phc-bar" style="height: {{ max(6, ((int) $item['value'] / $governorateDamageTotal) * 140) }}px; background: {{ $item['color'] }}"></div>
                                    <div class="phc-bar-label">{{ $item['label'] }}</div>
                                </div>
                            @endforeach
                        </div>
                        <div class="phc-legend">
                            @foreach (array_slice($governorate['damage'], 0, 5) as $item)
                                <div class="phc-legend-row"><span class="phc-swatch" style="background: {{ $item['color'] }}"></span><span>{{ $item['label'] }}</span><strong>{{ $formatNumber($item['value']) }}</strong></div>
                            @endforeach
                        </div>
                    </div>

                    <div class="phc-chart-card">
                        <h4>استخدام وإشغال الوحدات السكنية</h4>
                        <svg class="phc-donut" viewBox="0 0 140 140">
                            @php $governorateOffset = 0; $governorateCircumference = 314; @endphp
                            @foreach (array_slice($governorate['occupancy'], 0, 5) as $item)
                                @php
                                    $governorateSegmentLength = ((int) $item['value'] / $governorateOccupancyTotal) * $governorateCircumference;
                                @endphp
                                <circle cx="70" cy="70" r="50" fill="none" stroke="{{ $item['color'] }}" stroke-width="24" stroke-dasharray="{{ $governorateSegmentLength }} {{ $governorateCircumference - $governorateSegmentLength }}" stroke-dashoffset="{{ -$governorateOffset }}" transform="rotate(-90 70 70)" />
                                @php $governorateOffset += $governorateSegmentLength; @endphp
                            @endforeach
                            <circle cx="70" cy="70" r="31" fill="#fff" />
                            <text x="70" y="75" text-anchor="middle" font-size="12" fill="#006eb6" font-weight="900">{{ $formatNumber($governorateOccupancyTotal) }}</text>
                        </svg>
                        <div class="phc-legend">
                            @foreach (array_slice($governorate['occupancy'], 0, 5) as $item)
                                <div class="phc-legend-row"><span class="phc-swatch" style="background: {{ $item['color'] }}"></span><span>{{ $item['label'] }}</span><strong>{{ $formatNumber($item['value']) }}</strong></div>
                            @endforeach
                        </div>
                    </div>

                    <div class="phc-chart-card">
                        <h4>توزيع المباني المقيّمة حسب نوع المنشأة</h4>
                        @foreach (array_slice($governorate['building_types'], 0, 6) as $item)
                            <div class="phc-horizontal-row">
                                <span>{{ $item['label'] }}</span>
                                <div class="phc-horizontal-track"><div class="phc-horizontal-fill" style="width: {{ max(3, ((int) $item['value'] / $governorateTypeMax) * 100) }}%; background: {{ $item['color'] }}"></div></div>
                                <strong>{{ $formatNumber($item['value']) }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="phc-table-wrap">
                    <table class="phc-table">
                        <thead>
                            <tr>
                                <th>البلدية</th>
                                <th>المباني</th>
                                <th>الوحدات</th>
                                <th>ضرر جزئي</th>
                                <th>ضرر كلي</th>
                                <th>مراجعة لجنة</th>
                                <th>مشغولة</th>
                                <th>غير مشغولة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($governorate['municipalities'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $formatNumber($row['buildings']) }}</td>
                                    <td>{{ $formatNumber($row['housing_units']) }}</td>
                                    <td>{{ $formatNumber($row['partially_damaged']) }}</td>
                                    <td>{{ $formatNumber($row['fully_damaged']) }}</td>
                                    <td>{{ $formatNumber($row['committee_review']) }}</td>
                                    <td>{{ $formatNumber($row['occupied']) }}</td>
                                    <td>{{ $formatNumber($row['vacant']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8">لا توجد بيانات</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <footer class="phc-footer">
                    <span>PHC - {{ $governorate['english_name'] }}</span>
                    <strong>مشروع حصر الأضرار - قطاع غزة</strong>
                </footer>
            </section>
        @endforeach

        <section class="phc-page">
            <div class="phc-title">
                <h2>استعراض الأحياء</h2>
                <p>تفصيل ديناميكي للأحياء حسب المحافظة وعدد الوحدات والأضرار والإشغال</p>
            </div>

            <div class="phc-neighborhood-stage">
                <div class="phc-neighborhood-focus">
                    <h3>الحي الأعلى في الوحدات المسجلة: {{ $activeNeighborhood['name'] }}</h3>
                    <div class="phc-summary" style="margin-bottom: 14px;">
                        يتم ترتيب الأحياء بناءً على عدد الوحدات السكنية المسجلة ضمن البيانات الحالية. تعرض الخريطة والجدول أدناه أعلى الأحياء من حيث حجم البيانات، مع إبراز مؤشرات الضرر والإشغال.
                    </div>
                    <div class="phc-grid-2">
                        <div class="phc-kpi blue"><strong>{{ $formatNumber($activeNeighborhood['buildings']) }}</strong><span>مبانٍ مسجلة</span></div>
                        <div class="phc-kpi blue"><strong>{{ $formatNumber($activeNeighborhood['housing_units']) }}</strong><span>وحدات سكنية</span></div>
                        <div class="phc-kpi orange"><strong>{{ $formatNumber($activeNeighborhood['fully_damaged']) }}</strong><span>ضرر كلي</span></div>
                        <div class="phc-kpi green"><strong>{{ $formatNumber($activeNeighborhood['occupied']) }}</strong><span>وحدات مشغولة</span></div>
                    </div>
                </div>
                <div class="phc-map-frame" style="margin-bottom: 0;">{!! $firstNeighborhoodPage['mapSvg'] !!}</div>
            </div>

            <div class="phc-table-wrap">
                <table class="phc-table">
                    <thead>
                        <tr>
                            <th>الحي</th>
                            <th>المباني</th>
                            <th>الوحدات</th>
                            <th>ضرر جزئي</th>
                            <th>ضرر كلي</th>
                            <th>مراجعة لجنة</th>
                            <th>مشغولة</th>
                            <th>غير مشغولة</th>
                            <th>السكان المتأثرون</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topNeighborhoods as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $formatNumber($row['buildings']) }}</td>
                                <td>{{ $formatNumber($row['housing_units']) }}</td>
                                <td>{{ $formatNumber($row['partially_damaged']) }}</td>
                                <td>{{ $formatNumber($row['fully_damaged']) }}</td>
                                <td>{{ $formatNumber($row['committee_review']) }}</td>
                                <td>{{ $formatNumber($row['occupied']) }}</td>
                                <td>{{ $formatNumber($row['vacant']) }}</td>
                                <td>{{ $formatNumber($row['housing_units'] * 5.3) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9">لا توجد بيانات أحياء ضمن الفلاتر الحالية</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="phc-grid-2">
                <div class="phc-chart-card">
                    <h4>أعلى الأحياء حسب الوحدات السكنية</h4>
                    @foreach ($topNeighborhoods as $row)
                        <div class="phc-horizontal-row" style="grid-template-columns: 120px 1fr 52px;">
                            <span>{{ $row['name'] }}</span>
                            <div class="phc-horizontal-track"><div class="phc-horizontal-fill" style="width: {{ max(3, ((int) $row['housing_units'] / $topNeighborhoodMax) * 100) }}%; background: #e65100;"></div></div>
                            <strong>{{ $formatNumber($row['housing_units']) }}</strong>
                        </div>
                    @endforeach
                </div>
                <div class="phc-chart-card">
                    <h4>ملخص الإشغال ضمن أعلى الأحياء</h4>
                    <div class="phc-grid-2">
                        <div class="phc-kpi green"><strong>{{ $formatNumber($topNeighborhoods->sum('occupied')) }}</strong><span>مشغولة</span></div>
                        <div class="phc-kpi orange"><strong>{{ $formatNumber($topNeighborhoods->sum('vacant')) }}</strong><span>غير مشغولة</span></div>
                        <div class="phc-kpi orange"><strong>{{ $formatNumber($topNeighborhoods->sum('fully_damaged')) }}</strong><span>ضرر كلي</span></div>
                        <div class="phc-kpi blue"><strong>{{ $formatNumber($topNeighborhoods->sum('partially_damaged')) }}</strong><span>ضرر جزئي</span></div>
                    </div>
                </div>
            </div>

            <footer class="phc-footer">
                <span>PHC - Neighborhoods</span>
                <strong>وزارة الأشغال العامة والإسكان | المجلس الفلسطيني للإسكان | UNDP</strong>
            </footer>
        </section>
    </div>
@endsection
