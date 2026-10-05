<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>Damage Assessment - PHC</title>
    @php
        $arabicFont = base64_encode(file_get_contents(public_path('DroidArabicKufi.ttf')));
    @endphp
    <style>
        @font-face {
            font-family: "Droid Arabic Kufi";
            src: url("data:font/truetype;charset=utf-8;base64,{{ $arabicFont }}") format("truetype");
            font-weight: normal;
            font-style: normal;
        }

        @page {
            size: A4 landscape;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            direction: rtl;
            color: #17324d;
            background: #ffffff;
            font-family: "Droid Arabic Kufi", Tahoma, Arial, sans-serif;
        }

        .page {
            width: 297mm;
            height: 210mm;
            page-break-after: always;
            position: relative;
            overflow: hidden;
            background: #ffffff;
            padding: 17mm 18mm 14mm;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .cover {
            padding: 0;
            color: #ffffff;
            background: linear-gradient(135deg, #0f4c81 0 58%, #16a6d9 58% 73%, #f58220 73% 100%);
        }

        .cover-inner {
            height: 100%;
            padding: 28mm 25mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: linear-gradient(90deg, rgba(15, 76, 129, .96), rgba(15, 76, 129, .62));
        }

        .cover-title {
            font-size: 28px;
            line-height: 1.9;
            font-weight: 700;
            max-width: 720px;
        }

        .cover-subtitle {
            font-size: 14px;
            color: #dff6ff;
            margin-top: 8px;
        }

        .cover-meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            max-width: 720px;
        }

        .cover-box,
        .metric,
        .panel {
            border-radius: 8px;
        }

        .cover-box {
            border: 1px solid rgba(255, 255, 255, .32);
            padding: 12px 14px;
            background: rgba(255, 255, 255, .12);
        }

        .cover-box strong {
            display: block;
            font-size: 18px;
            margin-bottom: 4px;
        }

        .header {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 10mm;
            background: linear-gradient(90deg, #f58220, #16a6d9, #0f4c81);
        }

        .report-head {
            display: grid;
            grid-template-columns: 160px 1fr 220px;
            align-items: start;
            gap: 14px;
            direction: ltr;
            padding-bottom: 9px;
            border-bottom: 1.5px solid #1687c7;
            margin-bottom: 10px;
        }

        .logos {
            display: flex;
            gap: 8px;
            direction: ltr;
        }

        .logo-mark {
            width: 44px;
            height: 44px;
            border: 1px solid #b7d6e8;
            color: #1687c7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            font-weight: 700;
            background: #f7fbfd;
        }

        .report-kicker {
            color: #f58220;
            font-size: 17px;
            font-weight: 700;
            text-align: right;
            margin-bottom: 3px;
            direction: rtl;
        }

        .report-subhead {
            color: #1687c7;
            font-size: 9px;
            line-height: 1.55;
            text-align: right;
            direction: rtl;
        }

        .stats-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin: 5px 44px 6px;
            padding-bottom: 6px;
            border-bottom: 1.3px solid #1687c7;
        }

        .stats-strip.four {
            grid-template-columns: repeat(4, 1fr);
            margin-top: 4px;
        }

        .big-stat {
            text-align: center;
        }

        .big-stat strong {
            display: block;
            color: #138bd0;
            font-size: 21px;
            font-weight: 500;
            line-height: 1.15;
        }

        .big-stat span {
            display: block;
            color: #526b82;
            font-size: 8px;
            line-height: 1.45;
        }

        .report-stage {
            display: grid;
            grid-template-columns: 1fr 205px;
            gap: 14px;
            align-items: center;
            min-height: 180px;
        }

        .side-stat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 14px;
        }

        .side-stat {
            border-left: 1px solid #a7b9c7;
            min-height: 42px;
            padding-left: 8px;
            text-align: right;
        }

        .side-stat strong {
            color: #138bd0;
            font-size: 18px;
            font-weight: 500;
            display: block;
            line-height: 1.1;
        }

        .side-stat span {
            color: #526b82;
            font-size: 7px;
            line-height: 1.35;
        }

        .chart-footer {
            position: absolute;
            left: 18mm;
            right: 18mm;
            bottom: 17mm;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            align-items: end;
            background: #fff;
        }

        .chart-block {
            min-height: 92px;
            overflow: hidden;
        }

        .chart-caption {
            color: #1f4e79;
            font-size: 9px;
            font-weight: 700;
            text-align: center;
            margin-top: 2px;
        }

        .chart-svg {
            width: 100%;
            height: 54px;
            display: block;
        }

        .chart-combo {
            display: grid;
            grid-template-columns: 92px 1fr;
            gap: 6px;
            align-items: center;
            direction: rtl;
            height: 76px;
        }

        .chart-combo.wide {
            grid-template-columns: 1fr;
            gap: 2px;
        }

        .chart-combo.wide .chart-svg {
            height: 48px;
        }

        .chart-legend {
            display: grid;
            gap: 2px;
            direction: rtl;
            text-align: right;
            min-width: 0;
        }

        .chart-legend-row {
            display: grid;
            grid-template-columns: 9px minmax(0, 1fr) auto;
            gap: 5px;
            align-items: center;
            color: #31526c;
            font-size: 6.5px;
            line-height: 1.25;
            white-space: nowrap;
        }

        .chart-legend-row span:nth-child(2) {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chart-legend-swatch {
            width: 7px;
            height: 7px;
            display: inline-block;
        }

        .chart-legend-value {
            color: #138bd0;
            font-weight: 700;
            direction: ltr;
        }

        .footer {
            position: absolute;
            bottom: 5mm;
            left: 18mm;
            right: 18mm;
            display: flex;
            justify-content: space-between;
            color: #718096;
            font-size: 10px;
            border-top: 1px solid #d7e3eb;
            padding-top: 5px;
        }

        h1 {
            margin: 0 0 8px;
            color: #0f4c81;
            font-size: 19px;
            line-height: 1.45;
        }

        h2 {
            margin: 0 0 8px;
            color: #0f4c81;
            font-size: 14px;
            line-height: 1.5;
        }

        .subtitle {
            color: #5a7288;
            font-size: 9px;
            margin-bottom: 12px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .grid-5 {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 9px;
        }

        .metric {
            padding: 10px 12px;
            min-height: 70px;
            background: #f7fbfd;
            border: 1px solid #dbeaf1;
            border-top: 4px solid #16a6d9;
        }

        .metric strong {
            display: block;
            color: #0f4c81;
            font-size: 18px;
            margin-bottom: 4px;
        }

        .metric span {
            color: #5a7288;
            font-size: 8px;
        }

        .panel {
            border: 1px solid #dbeaf1;
            background: #ffffff;
            padding: 11px;
            min-height: 105px;
        }

        .panel.fill {
            background: #f7fbfd;
        }

        .chart-row {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            height: 145px;
            gap: 8px;
            padding-top: 10px;
            direction: ltr;
        }

        .bar-item {
            width: 80px;
            text-align: center;
            direction: rtl;
        }

        .bar {
            width: 42px;
            margin: 0 auto 7px;
            border-radius: 5px 5px 0 0;
            min-height: 4px;
        }

        .bar-label {
            font-size: 7px;
            color: #486277;
        }

        .bar-value {
            color: #0f4c81;
            font-size: 8px;
            font-weight: 700;
        }

        .donut-list {
            display: grid;
            gap: 7px;
            margin-top: 8px;
        }

        .legend-item {
            display: grid;
            grid-template-columns: 12px 1fr auto;
            align-items: center;
            gap: 7px;
            font-size: 8px;
            color: #486277;
        }

        .swatch {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 9px;
        }

        th,
        td {
            border: 1px solid #d8e4eb;
            padding: 2px 3px;
            text-align: center;
            font-size: 7px;
            line-height: 1.2;
            word-break: break-word;
        }

        th {
            color: #ffffff;
            background: #0f4c81;
            font-weight: 700;
        }

        tbody tr:nth-child(even) td {
            background: #f7fbfd;
        }

        .map-box {
            height: 180px;
        }

        .map-box svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .note-list {
            margin: 8px 0 0;
            padding: 0 18px 0 0;
            color: #34546d;
            font-size: 10px;
            line-height: 1.9;
        }

        .section-band {
            display: inline-block;
            color: #ffffff;
            background: #f58220;
            padding: 5px 12px;
            border-radius: 5px;
            font-size: 9px;
            margin-bottom: 8px;
        }

        .mini-title {
            color: #0f4c81;
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .brand-page {
            background: #ffffff;
            padding: 13mm 15mm 12mm;
        }

        .brand-rule {
            position: absolute;
            top: 0;
            right: 0;
            left: 0;
            height: 5mm;
            background: #0f3147;
            border-bottom: 1.8mm solid #c5a02b;
        }

        .brand-head {
            display: grid;
            grid-template-columns: 132px 1fr 132px;
            align-items: center;
            gap: 10px;
            border-bottom: 1.5px solid #c5a02b;
            padding-bottom: 6px;
            margin-bottom: 9px;
        }

        .brand-logo {
            border: 1px solid #d8e4eb;
            min-height: 39px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0f3147;
            background: #f4f8fa;
            font-size: 8px;
            font-weight: 700;
        }

        .brand-logo.undp {
            background: #006eb6;
            color: #ffffff;
            letter-spacing: 1px;
        }

        .brand-title {
            text-align: center;
        }

        .brand-title h1 {
            color: #0f3147;
            font-size: 16px;
            line-height: 1.45;
            margin: 0;
        }

        .brand-title p {
            color: #5a6e7f;
            font-size: 8px;
            line-height: 1.5;
            margin: 2px 0 0;
        }

        .brand-page > h1,
        .brand-page > .subtitle,
        .brand-page > .grid-2,
        .brand-page > .grid-3 {
            display: none;
        }

        .neighborhood-hero {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 10px;
            align-items: stretch;
            margin-bottom: 9px;
        }

        .neighborhood-map {
            border: 1px solid #d8e4eb;
            background: #f4f8fa;
            padding: 8px;
            min-height: 158px;
            overflow: hidden;
        }

        .neighborhood-map svg {
            width: 100%;
            height: 150px;
            display: block;
        }

        .neighborhood-focus {
            border: 2px solid #e65100;
            background: #fffaf4;
            padding: 10px;
            min-height: 158px;
            position: relative;
        }

        .focus-pill {
            display: inline-block;
            background: #ffffff;
            border: 1.5px solid #e65100;
            color: #e65100;
            padding: 3px 10px;
            font-size: 8px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        .focus-name {
            color: #0f3147;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.35;
            margin-bottom: 6px;
        }

        .focus-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin-top: 8px;
        }

        .focus-stat {
            background: #ffffff;
            border: 1px solid #eadcc0;
            padding: 6px;
        }

        .focus-stat strong {
            display: block;
            color: #006eb6;
            font-size: 13px;
            line-height: 1.1;
            direction: ltr;
        }

        .focus-stat span {
            color: #5a6e7f;
            font-size: 7px;
        }

        .neighborhood-kpis {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 7px;
            margin-bottom: 8px;
        }

        .neighborhood-kpi {
            background: #f4f8fa;
            border: 1px solid #d8e4eb;
            border-right: 3px solid #006eb6;
            padding: 7px 6px;
            min-height: 48px;
        }

        .neighborhood-kpi.orange {
            border-right-color: #e65100;
        }

        .neighborhood-kpi.green {
            border-right-color: #2e7d32;
        }

        .neighborhood-kpi strong {
            display: block;
            color: #0f3147;
            font-size: 13px;
            line-height: 1.15;
            direction: ltr;
        }

        .neighborhood-kpi span {
            color: #5a6e7f;
            font-size: 7px;
            line-height: 1.35;
        }

        .neighborhood-bottom {
            display: grid;
            grid-template-columns: 1fr 192px;
            gap: 10px;
            align-items: start;
        }

        .neighborhood-table-wrap {
            border: 1px solid #d8e4eb;
            overflow: hidden;
            background: #ffffff;
        }

        .neighborhood-table-wrap h2 {
            background: #0f3147;
            color: #ffffff;
            padding: 5px 8px;
            margin: 0;
            font-size: 9px;
        }

        .neighborhood-table {
            margin-top: 0;
        }

        .neighborhood-table th {
            background: #006eb6;
            font-size: 6.5px;
            padding: 3px 2px;
        }

        .neighborhood-table td {
            font-size: 6.4px;
            padding: 2.5px 2px;
        }

        .neighborhood-insights {
            display: grid;
            gap: 7px;
        }

        .insight-card {
            background: #ffffff;
            border: 1px solid #d8e4eb;
            padding: 8px;
        }

        .insight-card h3 {
            color: #0f3147;
            font-size: 8px;
            margin: 0 0 6px;
        }

        .rank-row {
            display: grid;
            grid-template-columns: 1fr 42px;
            gap: 5px;
            align-items: center;
            margin-bottom: 5px;
            font-size: 6.5px;
            color: #31526c;
        }

        .rank-bar {
            height: 6px;
            background: #edf5fa;
            overflow: hidden;
            margin-top: 2px;
        }

        .rank-fill {
            height: 100%;
            background: #e65100;
        }
    </style>
</head>
<body>
@php
    $pageNumber = 1;
    $formatNumber = fn ($value) => number_format((int) $value);
    $barChart = function (array $items): string {
        $max = max(1, ...array_map(fn ($item) => (int) $item['value'], $items));

        return collect($items)->map(function (array $item) use ($max): string {
            $height = max(6, ((int) $item['value'] / $max) * 118);

            return '<div class="bar-item">'
                .'<div class="bar" style="height: '.$height.'px; background: '.$item['color'].'"></div>'
                .'<div class="bar-label">'.e($item['label']).'</div>'
                .'<div class="bar-value">'.number_format((int) $item['value']).'</div>'
                .'</div>';
        })->implode('');
    };
    $legend = fn (array $items): string => collect($items)->map(fn (array $item): string => '<div class="legend-item"><i class="swatch" style="background: '.$item['color'].'"></i><span>'.e($item['label']).'</span><strong>'.number_format((int) $item['value']).' - '.$item['percent'].'%</strong></div>')->implode('');
    $compactLegend = fn (array $items, int $limit = 5): string => '<div class="chart-legend">'.collect($items)->take($limit)->map(fn (array $item): string => '<div class="chart-legend-row"><i class="chart-legend-swatch" style="background: '.$item['color'].'"></i><span>'.e($item['label']).'</span><strong class="chart-legend-value">'.number_format((int) $item['value']).'</strong></div>')->implode('').'</div>';
    $donutSvg = function (array $items) use ($compactLegend): string {
        $total = max(1, array_sum(array_map(fn ($item) => (int) $item['value'], $items)));
        $radius = 24;
        $circumference = 2 * pi() * $radius;
        $offset = 0;
        $segments = collect($items)->take(5)->map(function (array $item) use (&$offset, $total, $circumference, $radius): string {
            $length = ((int) $item['value'] / $total) * $circumference;
            $segment = '<circle cx="44" cy="38" r="'.$radius.'" fill="none" stroke="'.$item['color'].'" stroke-width="13" stroke-dasharray="'.$length.' '.($circumference - $length).'" stroke-dashoffset="'.(-$offset).'" transform="rotate(-90 44 38)" />';
            $offset += $length;

            return $segment;
        })->implode('');

        return '<div class="chart-combo"><svg class="chart-svg" viewBox="0 0 88 78" xmlns="http://www.w3.org/2000/svg">'.$segments.'<circle cx="44" cy="38" r="14" fill="#fff"/><text x="44" y="41" font-size="9" text-anchor="middle" fill="#138bd0">'.$total.'</text></svg>'.$compactLegend($items, 4).'</div>';
    };
    $miniBarsSvg = function (array $items) use ($compactLegend): string {
        $items = array_slice($items, 0, 5);
        $values = array_map(fn ($item) => (int) $item['value'], $items);
        $max = max(array_merge([1], $values));
        $bars = collect($items)->values()->map(function (array $item, int $index) use ($max): string {
            $height = max(3, ((int) $item['value'] / $max) * 48);
            $x = 18 + ($index * 38);
            $y = 58 - $height;

            return '<rect x="'.$x.'" y="'.$y.'" width="18" height="'.$height.'" fill="'.$item['color'].'"/>';
        })->implode('');

        return '<div class="chart-combo wide"><svg class="chart-svg" viewBox="0 0 220 64" xmlns="http://www.w3.org/2000/svg"><line x1="10" y1="58" x2="210" y2="58" stroke="#b8c9d6" stroke-width="1"/>'.$bars.'</svg>'.$compactLegend($items, 5).'</div>';
    };
    $stackSvg = function (array $items) use ($compactLegend): string {
        $total = max(1, array_sum(array_map(fn ($item) => (int) $item['value'], $items)));
        $x = 12;
        $segments = collect($items)->take(4)->map(function (array $item) use (&$x, $total): string {
            $width = max(2, ((int) $item['value'] / $total) * 190);
            $segment = '<rect x="'.$x.'" y="26" width="'.$width.'" height="22" fill="'.$item['color'].'"/>';
            $x += $width;

            return $segment;
        })->implode('');

        return '<div class="chart-combo wide"><svg class="chart-svg" viewBox="0 0 220 56" xmlns="http://www.w3.org/2000/svg"><rect x="12" y="18" width="190" height="22" fill="#edf5fa"/>'.$segments.'</svg>'.$compactLegend($items, 4).'</div>';
    };
    $logos = '<div class="logos"><div class="logo-mark">GOV</div><div class="logo-mark">PHC</div><div class="logo-mark">AIOCP</div></div>';
@endphp

<section class="page cover">
    <div class="cover-inner">
        <div>
            <div class="section-band">PHC - Damage Assessment</div>
            <div class="cover-title">تقرير حصر أضرار المباني والوحدات السكنية في قطاع غزة</div>
            <div class="cover-subtitle">المنظومة الوطنية المتكاملة لحصر الأضرار</div>
        </div>
        <div class="cover-meta">
            <div class="cover-box"><strong>{{ $formatNumber($totals['buildings']) }}</strong>مبنى</div>
            <div class="cover-box"><strong>{{ $formatNumber($totals['housing_units']) }}</strong>وحدة سكنية</div>
            <div class="cover-box"><strong>{{ $reportDate }}</strong>تاريخ التقرير</div>
        </div>
    </div>
</section>

<section class="page">
    <div class="header"></div>
    <div class="report-head">
        {!! $logos !!}
        <div></div>
        <div>
            <div class="report-kicker">قطاع غزة</div>
            <div class="report-subhead">المجلس الفلسطيني للإسكان<br>قطاع الإسكان<br>{{ $reportDate }}</div>
        </div>
    </div>
    <h1 style="text-align: center;">نتائج وإحصائيات مشروع المنظومة الوطنية المتكاملة لحصر الأضرار - قطاع غزة</h1>
    <div class="subtitle" style="text-align: center;">تعرض هذه الصفحة إجمالي المؤشرات والجداول والشارتس المستخرجة مباشرة من قاعدة البيانات.</div>

    <div class="stats-strip">
        <div class="big-stat"><strong>{{ $formatNumber($totals['affected_population']) }}</strong><span>إجمالي عدد السكان المتأثرين</span></div>
        <div class="big-stat"><strong>{{ $formatNumber($totals['housing_units']) }}</strong><span>الوحدات السكنية المقدرة</span></div>
        <div class="big-stat"><strong>{{ $formatNumber($totals['buildings']) }}</strong><span>عدد المباني</span></div>
    </div>
    <div class="stats-strip four">
        <div class="big-stat"><strong>{{ $formatNumber($totals['assessed_buildings']) }}</strong><span>المباني التي تم تقييمها</span></div>
        <div class="big-stat"><strong>{{ $formatNumber($totals['assessed_housing_units']) }}</strong><span>الوحدات التي تم تقييمها</span></div>
        <div class="big-stat"><strong>{{ $formatNumber($totals['affected_population']) }}</strong><span>السكان المتأثرون</span></div>
        <div class="big-stat"><strong>{{ $formatNumber(5800000) }}</strong><span>طن - تقدير مرجعي للأنقاض</span></div>
    </div>

    <div class="report-stage">
        <div class="map-box">{!! $gazaMapSvg !!}</div>
        <div class="side-stat-grid">
            @foreach (array_slice($damageDistribution, 0, 4) as $item)
                <div class="side-stat"><strong>{{ $formatNumber($item['value']) }}</strong><span>{{ $item['label'] }}</span></div>
            @endforeach
        </div>
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <th>المحافظة</th>
                <th>المباني</th>
                <th>الوحدات السكنية</th>
                <th>الوحدات المقيمة</th>
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
    </table>

    <div class="chart-footer">
        <div class="chart-block">{!! $miniBarsSvg($buildingTypeDistribution) !!}<div class="chart-caption">توزيع أنواع المباني</div></div>
        <div class="chart-block">{!! $stackSvg($occupancyDistribution) !!}<div class="chart-caption">استخدام وإشغال الوحدات السكنية</div></div>
        <div class="chart-block">{!! $donutSvg($damageDistribution) !!}<div class="chart-caption">توزيع أضرار الوحدات السكنية</div></div>
    </div>
    <div class="footer"><span>PHC - Gaza Damage Assessment</span><span>صفحة {{ $pageNumber++ }} من {{ $totalPages }}</span></div>
</section>

@foreach ($governorates as $governorate)
    <section class="page">
        <div class="header"></div>
        <div class="report-head">
            {!! $logos !!}
            <div></div>
            <div>
                <div class="report-kicker">محافظة {{ $governorate['name'] }}</div>
                <div class="report-subhead">المجلس الفلسطيني للإسكان<br>قطاع الإسكان<br>{{ $reportDate }}</div>
            </div>
        </div>
        <div class="subtitle" style="color: #f58220; text-align: left;">إحصائيات المحافظات</div>

        <div class="stats-strip">
            <div class="big-stat"><strong>{{ $formatNumber($governorate['totals']['affected_population']) }}</strong><span>السكان المتأثرون</span></div>
            <div class="big-stat"><strong>{{ $formatNumber($governorate['totals']['housing_units']) }}</strong><span>من الوحدات السكنية</span></div>
            <div class="big-stat"><strong>{{ $formatNumber($governorate['totals']['buildings']) }}</strong><span>إجمالي عدد المباني</span></div>
        </div>
        <div class="stats-strip four">
            <div class="big-stat"><strong>{{ $formatNumber($governorate['totals']['assessed_buildings']) }}</strong><span>المباني التي تم تقييمها</span></div>
            <div class="big-stat"><strong>{{ $formatNumber($governorate['totals']['assessed_housing_units']) }}</strong><span>الوحدات التي تم تقييمها</span></div>
            <div class="big-stat"><strong>{{ $formatNumber($governorate['totals']['affected_population']) }}</strong><span>السكان المتأثرون</span></div>
            <div class="big-stat"><strong>{{ $formatNumber(max(1, $governorate['totals']['buildings']) * 21) }}</strong><span>طن - تقدير مرجعي للأنقاض</span></div>
        </div>

        <div class="report-stage">
            <div class="map-box">{!! $governorate['mapSvg'] !!}</div>
            <div class="side-stat-grid">
                @foreach (array_slice($governorate['damage'], 0, 4) as $item)
                    <div class="side-stat"><strong>{{ $formatNumber($item['value']) }}</strong><span>{{ $item['label'] }}</span></div>
                @endforeach
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>البلدية</th>
                    <th>إجمالي عدد المباني</th>
                    <th>الوحدات السكنية المقدرة</th>
                    <th>المباني التي تم تقييمها</th>
                    <th>الوحدات التي تم تقييمها</th>
                    <th>طفيف</th>
                    <th>متوسط</th>
                    <th>شديد</th>
                    <th>مدمر</th>
                    <th>السكان المتأثرون</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($governorate['municipalities'] as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $formatNumber($row['buildings']) }}</td>
                        <td>{{ $formatNumber($row['housing_units']) }}</td>
                        <td>{{ $formatNumber($row['buildings']) }}</td>
                        <td>{{ $formatNumber($row['housing_units']) }}</td>
                        <td>{{ $formatNumber(0) }}</td>
                        <td>{{ $formatNumber(0) }}</td>
                        <td>{{ $formatNumber($row['partially_damaged']) }}</td>
                        <td>{{ $formatNumber($row['fully_damaged']) }}</td>
                        <td>{{ $formatNumber($row['housing_units'] * 5.3) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10">لا توجد بيانات</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="chart-footer">
            <div class="chart-block">{!! $miniBarsSvg($governorate['building_types']) !!}<div class="chart-caption">توزيع أنواع المباني</div></div>
            <div class="chart-block">{!! $stackSvg($governorate['occupancy']) !!}<div class="chart-caption">استخدام وإشغال الوحدات السكنية</div></div>
            <div class="chart-block">{!! $donutSvg($governorate['damage']) !!}<div class="chart-caption">توزيع أضرار الوحدات السكنية</div></div>
        </div>
        <div class="footer"><span>PHC - {{ $governorate['english_name'] }}</span><span>صفحة {{ $pageNumber++ }} من {{ $totalPages }}</span></div>
    </section>
@endforeach

@foreach ($neighborhoodPages as $page)
    @php
        $rows = collect($page['rows']);
        $featuredNeighborhood = $rows->sortByDesc(fn (array $row): int => (int) $row['housing_units'])->first() ?? [
            'name' => 'غير محدد',
            'buildings' => 0,
            'housing_units' => 0,
            'partially_damaged' => 0,
            'fully_damaged' => 0,
            'committee_review' => 0,
            'occupied' => 0,
            'vacant' => 0,
        ];
        $neighborhoodTotals = [
            'count' => $rows->count(),
            'buildings' => $rows->sum('buildings'),
            'housing_units' => $rows->sum('housing_units'),
            'affected' => (int) round($rows->sum('housing_units') * 5.3),
            'partial' => $rows->sum('partially_damaged'),
            'total' => $rows->sum('fully_damaged'),
            'committee' => $rows->sum('committee_review'),
            'occupied' => $rows->sum('occupied'),
            'vacant' => $rows->sum('vacant'),
        ];
        $rankMax = max(1, (int) $rows->max('housing_units'));
    @endphp
    <section class="page brand-page">
        <div class="brand-rule"></div>
        <div class="brand-head">
            <div class="brand-logo">وزارة الأشغال العامة والإسكان</div>
            <div class="brand-title">
                <h1>استعراض أحياء محافظة {{ $page['governorate'] }}</h1>
                <p>المجلس الفلسطيني للإسكان بالتعاون مع برنامج الأمم المتحدة الإنمائي - مشروع حصر الأضرار في قطاع غزة</p>
            </div>
            <div class="brand-logo undp">UNDP</div>
        </div>

        <div class="neighborhood-hero">
            <div class="neighborhood-map">{!! $page['mapSvg'] !!}</div>
            <div class="neighborhood-focus">
                <span class="focus-pill">الحي الأعلى في الوحدات المسجلة</span>
                <div class="focus-name">{{ $featuredNeighborhood['name'] }}</div>
                <div class="subtitle" style="margin-bottom: 0;">تعرض هذه البطاقة الحي الأكثر ظهوراً في بيانات المحافظة الحالية، مع مؤشرات الأضرار والإشغال المستخرجة مباشرة من قاعدة البيانات.</div>
                <div class="focus-grid">
                    <div class="focus-stat"><strong>{{ $formatNumber($featuredNeighborhood['buildings']) }}</strong><span>مبانٍ مسجلة</span></div>
                    <div class="focus-stat"><strong>{{ $formatNumber($featuredNeighborhood['housing_units']) }}</strong><span>وحدات سكنية</span></div>
                    <div class="focus-stat"><strong>{{ $formatNumber($featuredNeighborhood['fully_damaged']) }}</strong><span>ضرر كلي</span></div>
                    <div class="focus-stat"><strong>{{ $formatNumber($featuredNeighborhood['partially_damaged']) }}</strong><span>ضرر جزئي</span></div>
                </div>
            </div>
        </div>

        <div class="neighborhood-kpis">
            <div class="neighborhood-kpi"><strong>{{ $formatNumber($neighborhoodTotals['count']) }}</strong><span>عدد الأحياء المعروضة</span></div>
            <div class="neighborhood-kpi"><strong>{{ $formatNumber($neighborhoodTotals['buildings']) }}</strong><span>إجمالي المباني</span></div>
            <div class="neighborhood-kpi"><strong>{{ $formatNumber($neighborhoodTotals['housing_units']) }}</strong><span>إجمالي الوحدات</span></div>
            <div class="neighborhood-kpi orange"><strong>{{ $formatNumber($neighborhoodTotals['affected']) }}</strong><span>السكان المتأثرون تقديرياً</span></div>
            <div class="neighborhood-kpi orange"><strong>{{ $formatNumber($neighborhoodTotals['total']) }}</strong><span>وحدات مدمرة كلياً</span></div>
            <div class="neighborhood-kpi green"><strong>{{ $formatNumber($neighborhoodTotals['occupied']) }}</strong><span>وحدات مشغولة</span></div>
        </div>

        <div class="neighborhood-bottom">
            <div class="neighborhood-table-wrap">
                <h2>جدول الأحياء التفصيلي حسب المحافظة</h2>
                <table class="neighborhood-table">
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
                        @forelse ($page['rows'] as $row)
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
                            <tr><td colspan="9">لا توجد بيانات أحياء لهذه المحافظة ضمن الفلاتر الحالية</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>المجموع</th>
                            <th>{{ $formatNumber($neighborhoodTotals['buildings']) }}</th>
                            <th>{{ $formatNumber($neighborhoodTotals['housing_units']) }}</th>
                            <th>{{ $formatNumber($neighborhoodTotals['partial']) }}</th>
                            <th>{{ $formatNumber($neighborhoodTotals['total']) }}</th>
                            <th>{{ $formatNumber($neighborhoodTotals['committee']) }}</th>
                            <th>{{ $formatNumber($neighborhoodTotals['occupied']) }}</th>
                            <th>{{ $formatNumber($neighborhoodTotals['vacant']) }}</th>
                            <th>{{ $formatNumber($neighborhoodTotals['affected']) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="neighborhood-insights">
                <div class="insight-card">
                    <h3>أعلى الأحياء حسب الوحدات السكنية</h3>
                    @foreach ($rows->sortByDesc('housing_units')->take(5) as $row)
                        <div class="rank-row">
                            <div>
                                <span>{{ $row['name'] }}</span>
                                <div class="rank-bar"><div class="rank-fill" style="width: {{ max(3, ((int) $row['housing_units'] / $rankMax) * 100) }}%;"></div></div>
                            </div>
                            <strong>{{ $formatNumber($row['housing_units']) }}</strong>
                        </div>
                    @endforeach
                </div>
                <div class="insight-card">
                    <h3>مؤشرات الضرر والإشغال</h3>
                    <div class="rank-row"><span>ضرر كلي</span><strong>{{ $formatNumber($neighborhoodTotals['total']) }}</strong></div>
                    <div class="rank-row"><span>ضرر جزئي</span><strong>{{ $formatNumber($neighborhoodTotals['partial']) }}</strong></div>
                    <div class="rank-row"><span>مراجعة لجنة</span><strong>{{ $formatNumber($neighborhoodTotals['committee']) }}</strong></div>
                    <div class="rank-row"><span>غير مشغولة</span><strong>{{ $formatNumber($neighborhoodTotals['vacant']) }}</strong></div>
                </div>
            </div>
        </div>
        <h1>أحياء محافظة {{ $page['governorate'] }}</h1>
        <div class="subtitle">الخرائط والجداول حسب الحي، مع أعمدة الضرر والإشغال من قاعدة البيانات.</div>
        <div class="grid-2">
            <div class="panel map-box">{!! $page['mapSvg'] !!}</div>
            <div class="panel">
                <h2>جدول الأحياء</h2>
                <table>
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
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($page['rows'] as $row)
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
        </div>
        <div class="grid-3" style="margin-top: 12px;">
            <div class="panel fill"><div class="mini-title">الأزرق</div>يمثل نقاط المباني المسجلة مكانياً.</div>
            <div class="panel fill"><div class="mini-title">السماوي والبرتقالي</div>يمثلان حالات الضرر الجزئي والكلي في الخرائط والشارتس.</div>
            <div class="panel fill"><div class="mini-title">الأخضر</div>يستخدم لحالات المراجعة أو المؤشرات الداعمة.</div>
        </div>
        <div class="footer"><span>PHC - Neighborhoods</span><span>صفحة {{ $pageNumber++ }} من {{ $totalPages }}</span></div>
    </section>
@endforeach

<section class="page">
    <div class="header"></div>
    <h1>المنهجية</h1>
    <div class="grid-2">
        <div class="panel fill">
            <h2>مصادر البيانات</h2>
            <ul class="note-list">
                <li>جدول buildings لحصر المباني، حالة الضرر، النوع، الاستخدام، والبيانات المكانية.</li>
                <li>جدول housing_units لحصر الوحدات، حالة الضرر، الإشغال، والبيانات السكانية.</li>
                <li>جداول assessments و edit_assessments و assessment_statuses و building_statuses و housing_statuses لقياس حركة التقييم والتدقيق.</li>
            </ul>
        </div>
        <div class="panel fill">
            <h2>قواعد الحساب</h2>
            <ul class="note-list">
                <li>استخدام municipalitie كما هو موجود فعلياً في قاعدة البيانات.</li>
                <li>حساب ضرر المباني من building_damage_status وحساب ضرر الوحدات من unit_damage_status.</li>
                <li>التقدير السكاني مبني على عدد الوحدات المقيمة مضروباً بمتوسط 5.3 أفراد للأسرة عند غياب قيمة أفراد مباشرة.</li>
            </ul>
        </div>
    </div>
    <div class="grid-3" style="margin-top: 12px;">
        <div class="metric"><strong>{{ $formatNumber($totals['assessments']) }}</strong><span>سجلات التقييم</span></div>
        <div class="metric"><strong>{{ $formatNumber($totals['assessment_statuses']) }}</strong><span>حالات التقييم المرجعية</span></div>
        <div class="metric"><strong>{{ $formatNumber($totals['building_statuses'] + $totals['housing_statuses']) }}</strong><span>سجلات حالات المباني والوحدات</span></div>
    </div>
    <div class="footer"><span>PHC - Methodology</span><span>صفحة {{ $pageNumber++ }} من {{ $totalPages }}</span></div>
</section>

<section class="page">
    <div class="header"></div>
    <h1>التحديات والقيود والتوصيات</h1>
    <div class="grid-3">
        <div class="panel fill">
            <h2>التحديات</h2>
            <ul class="note-list">
                <li>تفاوت اكتمال بيانات المواقع لبعض السجلات، لذلك تعتمد الخرائط على الإحداثيات المتاحة فقط.</li>
                <li>وجود قيم غير محددة في بعض حقول الضرر أو النوع أو الإشغال.</li>
                <li>تعدد صيغ أسماء المحافظات بين المسافات والشرطات السفلية في بعض السجلات.</li>
            </ul>
        </div>
        <div class="panel fill">
            <h2>القيود</h2>
            <ul class="note-list">
                <li>الشارتس والجداول تمثل لقطة وقت توليد التقرير وليست أرقاماً ثابتة.</li>
                <li>التقدير السكاني مرجعي عند عدم توفر عدد أفراد الأسرة لكل وحدة بشكل موحد.</li>
                <li>المطابقة البصرية تعتمد على HTML/CSS وBrowsershot مع دعم RTL والخط العربي.</li>
            </ul>
        </div>
        <div class="panel fill">
            <h2>التوصيات</h2>
            <ul class="note-list">
                <li>استكمال الإحداثيات للمباني والوحدات لتحسين دقة الخرائط.</li>
                <li>توحيد القيم المرجعية لحقول الضرر والإشغال والمحافظة والبلدية.</li>
                <li>مراجعة السجلات ذات الحالة غير المحددة قبل اعتماد النسخ الرسمية.</li>
            </ul>
        </div>
    </div>
    <div class="grid-2" style="margin-top: 12px;">
        <div class="panel">
            <h2>توزيع استخدام المباني</h2>
            <div class="donut-list">{!! $legend($buildingUseDistribution) !!}</div>
        </div>
        <div class="panel">
            <h2>إشغال الوحدات</h2>
            <div class="donut-list">{!! $legend($occupancyDistribution) !!}</div>
        </div>
    </div>
    <div class="footer"><span>PHC - Recommendations</span><span>صفحة {{ $pageNumber++ }} من {{ $totalPages }}</span></div>
</section>
</body>
</html>
