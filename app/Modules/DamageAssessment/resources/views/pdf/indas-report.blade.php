<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">

    <title>PHC Report</title>

    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Tahoma, Arial, sans-serif;
            direction: rtl;
            background: #f5f8fa;
            color: #1f2937;
        }

        .page {
            width: 100%;
            min-height: 100vh;
            position: relative;
            background: white;
            padding: 35px;
            page-break-after: always;
        }

        .cover-page {
            background:
                linear-gradient(135deg, #0f3c68 0%, #0f3c68 55%, #f58220 55%, #f58220 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .cover-title {
            font-size: 42px;
            font-weight: bold;
            line-height: 2;
            margin-bottom: 30px;
        }

        .cover-subtitle {
            font-size: 24px;
        }

        .cover-date {
            margin-top: 40px;
            font-size: 20px;
        }

        .top-bar {
            height: 8px;
            background: #0f3c68;
            margin-bottom: 25px;
        }

        .section-title {
            color: #0f3c68;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .kpi-card {
            background: white;
            border-radius: 14px;
            border-top: 5px solid #0f3c68;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,.08);
        }

        .kpi-value {
            font-size: 30px;
            font-weight: bold;
            color: #0f3c68;
            margin-bottom: 10px;
        }

        .kpi-label {
            color: #6b7280;
            font-size: 15px;
        }

        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .chart-card {
            background: white;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,.08);
        }

        .chart-title {
            font-size: 18px;
            color: #0f3c68;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .damage-bars {
            display: flex;
            align-items: end;
            justify-content: space-around;
            height: 260px;
        }

        .bar-wrapper {
            width: 80px;
            text-align: center;
        }

        .bar {
            width: 100%;
            border-radius: 10px 10px 0 0;
        }

        .minor {
            background: #22c55e;
        }

        .moderate {
            background: #f59e0b;
        }

        .severe {
            background: #ef4444;
        }

        .destroyed {
            background: #111827;
        }

        .bar-label {
            margin-top: 10px;
            font-weight: bold;
        }

        .bar-value {
            margin-top: 8px;
            font-size: 13px;
        }

        .map-placeholder {
            height: 350px;
            border-radius: 16px;
            border: 3px dashed #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            color: #64748b;
            font-size: 22px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }

        table thead {
            background: #0f3c68;
            color: white;
        }

        table th,
        table td {
            padding: 12px;
            border: 1px solid #e5e7eb;
            text-align: center;
            font-size: 14px;
        }

        table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .footer {
            position: absolute;
            bottom: 15px;
            left: 30px;
            right: 30px;
            display: flex;
            justify-content: space-between;
            color: #64748b;
            font-size: 13px;
        }

        .gov-card {
            margin-bottom: 30px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,.08);
            background: white;
        }

        .gov-header {
            background: #0f3c68;
            color: white;
            padding: 18px;
            font-size: 22px;
            font-weight: bold;
        }

        .gov-body {
            padding: 20px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
        }

        .stat-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
        }

        .stat-number {
            font-size: 22px;
            font-weight: bold;
            color: #0f3c68;
        }

        .stat-text {
            margin-top: 6px;
            color: #6b7280;
            font-size: 13px;
        }
    </style>
</head>

<body>

    {{-- COVER PAGE --}}

    <div class="page cover-page">

        <div class="cover-title">
            المنظومة الوطنية المتكاملة<br>
            لحصر الأضرار – قطاع غزة
        </div>

        <div class="cover-subtitle">
            Damage Assessment System - PHC
        </div>

        <div class="cover-date">
            {{ $reportDate }}
        </div>

    </div>

    {{-- SUMMARY PAGE --}}

    <div class="page">

        <div class="top-bar"></div>

        <div class="section-title">
            ملخص قطاع غزة
        </div>

        <div class="kpi-grid">

            <div class="kpi-card">
                <div class="kpi-value">{{ $totalBuildings }}</div>
                <div class="kpi-label">إجمالي المباني</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-value">{{ $totalHousingUnits }}</div>
                <div class="kpi-label">إجمالي الوحدات</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-value">{{ $assessedBuildings }}</div>
                <div class="kpi-label">المباني المقيمة</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-value">{{ $assessedHousingUnits }}</div>
                <div class="kpi-label">الوحدات المقيمة</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-value">{{ $affectedPopulation }}</div>
                <div class="kpi-label">السكان المتأثرون</div>
            </div>

        </div>

        <div class="chart-grid">

            <div class="chart-card">

                <div class="chart-title">
                    توزيع الأضرار
                </div>

                <div class="damage-bars">

                    @php
                        $max = max($damageStats);
                    @endphp

                    <div class="bar-wrapper">

                        <div class="bar minor"
                            style="height: {{ ($damageStats['minor'] / max($max,1)) * 220 }}px">
                        </div>

                        <div class="bar-label">طفيف</div>

                        <div class="bar-value">
                            {{ number_format($damageStats['minor']) }}
                        </div>

                    </div>

                    <div class="bar-wrapper">

                        <div class="bar moderate"
                            style="height: {{ ($damageStats['moderate'] / max($max,1)) * 220 }}px">
                        </div>

                        <div class="bar-label">متوسط</div>

                        <div class="bar-value">
                            {{ number_format($damageStats['moderate']) }}
                        </div>

                    </div>

                    <div class="bar-wrapper">

                        <div class="bar severe"
                            style="height: {{ ($damageStats['severe'] / max($max,1)) * 220 }}px">
                        </div>

                        <div class="bar-label">شديد</div>

                        <div class="bar-value">
                            {{ number_format($damageStats['severe']) }}
                        </div>

                    </div>

                    <div class="bar-wrapper">

                        <div class="bar destroyed"
                            style="height: {{ ($damageStats['destroyed'] / max($max,1)) * 220 }}px">
                        </div>

                        <div class="bar-label">مدمر</div>

                        <div class="bar-value">
                            {{ number_format($damageStats['destroyed']) }}
                        </div>

                    </div>

                </div>

            </div>

            <div class="chart-card">

                <div class="chart-title">
                    خريطة الأضرار
                </div>

                <div class="map-placeholder">
                    MAP PLACEHOLDER
                </div>

            </div>

        </div>

        <table>

            <thead>
                <tr>
                    <th>المحافظة</th>
                    <th>إجمالي الوحدات</th>
                </tr>
            </thead>

            <tbody>

                @foreach($governorates as $gov)

                    <tr>
                        <td>{{ $gov->governorate_name }}</td>
                        <td>{{ number_format($gov->total_units) }}</td>
                    </tr>

                @endforeach

            </tbody>

        </table>

        <div class="footer">
            <div>PHC - GAZA</div>
            <div>Damage Assessment System</div>
        </div>

    </div>

    {{-- GOVERNORATE PAGES --}}

    @foreach($governorateDetails as $gov)

        <div class="page">

            <div class="top-bar"></div>

            <div class="gov-card">

                <div class="gov-header">
                    محافظة {{ $gov['name'] }}
                </div>

                <div class="gov-body">

                    <div class="stats-row">

                        <div class="stat-box">
                            <div class="stat-number">
                                {{ number_format($gov['total_units']) }}
                            </div>

                            <div class="stat-text">
                                إجمالي الوحدات
                            </div>
                        </div>

                        <div class="stat-box">
                            <div class="stat-number">
                                {{ number_format($gov['minor']) }}
                            </div>

                            <div class="stat-text">
                                ضرر طفيف
                            </div>
                        </div>

                        <div class="stat-box">
                            <div class="stat-number">
                                {{ number_format($gov['moderate']) }}
                            </div>

                            <div class="stat-text">
                                ضرر متوسط
                            </div>
                        </div>

                        <div class="stat-box">
                            <div class="stat-number">
                                {{ number_format($gov['severe']) }}
                            </div>

                            <div class="stat-text">
                                ضرر شديد
                            </div>
                        </div>

                        <div class="stat-box">
                            <div class="stat-number">
                                {{ number_format($gov['destroyed']) }}
                            </div>

                            <div class="stat-text">
                                مدمر
                            </div>
                        </div>

                    </div>

                    <div style="margin-top: 30px">

                        <div class="map-placeholder">
                            GOVERNORATE MAP
                        </div>

                    </div>

                </div>

            </div>

            <div class="footer">
                <div>PHC - GAZA</div>
                <div>{{ $gov['name'] }}</div>
            </div>

        </div>

    @endforeach

</body>
</html>