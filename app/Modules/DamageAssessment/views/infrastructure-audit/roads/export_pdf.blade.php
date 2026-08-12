<!DOCTYPE html>
<html lang="ar" dir="rtl">
@php
    $totalSurveyed = collect($rows)->sum('surveyed_count');
    $totalAudited = collect($rows)->sum('audited_count');
    $totalLength = collect($rows)->sum('road_length_meters');
    $auditRate = $totalSurveyed > 0 ? round(($totalAudited / $totalSurveyed) * 100, 1) : 0;
    $activeFilters = collect($filters)->filter(fn ($value) => filled($value));
@endphp
<head>
    <meta charset="UTF-8">
    <title>تقرير تدقيق الطرق</title>
    <style>
        @page { margin: 22px 24px 28px; }

        @font-face {
            font-family: 'Droid Arabic Kufi';
            font-style: normal;
            font-weight: 400;
            src: url('{{ public_path('DroidArabicKufi.ttf') }}') format('truetype');
        }

        body {
            background: #f5f8fa;
            color: #181c32;
            direction: rtl;
            font-family: 'Droid Arabic Kufi', DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.6;
            margin: 0;
        }

        .report-shell {
            background: #ffffff;
            border: 1px solid #eff2f5;
            border-radius: 10px;
            padding: 18px;
        }

        .hero {
            background: #009ef7;
            border-radius: 10px;
            color: #ffffff;
            margin-bottom: 14px;
            padding: 18px 20px;
        }

        .hero-table,
        .stats-table,
        .report-table {
            border-collapse: collapse;
            width: 100%;
        }

        .hero-table td {
            border: 0;
            padding: 0;
            vertical-align: middle;
        }

        .eyebrow {
            color: #f1faff;
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        h1 {
            font-size: 19px;
            line-height: 1.25;
            margin: 0;
        }

        .hero-meta {
            color: #f1faff;
            font-size: 9px;
            text-align: left;
        }

        .section-title {
            color: #3f4254;
            font-size: 13px;
            font-weight: 700;
            margin: 16px 0 8px;
        }

        .filters {
            margin-bottom: 12px;
        }

        .filter-chip {
            background: #f1faff;
            border: 1px solid #b5e4ff;
            border-radius: 16px;
            color: #3f4254;
            display: inline-block;
            font-size: 9px;
            margin: 0 0 7px 6px;
            padding: 5px 10px;
        }

        .filter-chip strong {
            color: #009ef7;
        }

        .empty-filters {
            background: #f9f9f9;
            border: 1px dashed #e1e3ea;
            border-radius: 8px;
            color: #7e8299;
            padding: 10px 12px;
        }

        .stats-table {
            border-spacing: 0;
            margin-bottom: 14px;
            table-layout: fixed;
        }

        .stats-table td {
            border: 1px solid #eff2f5;
            padding: 12px;
            vertical-align: top;
            width: 25%;
        }

        .stat-card {
            background: #f9f9f9;
            border-radius: 8px;
        }

        .stat-label {
            color: #7e8299;
            font-size: 9px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-value {
            color: #181c32;
            font-size: 17px;
            font-weight: 700;
            line-height: 1.25;
        }

        .stat-value.primary { color: #009ef7; }
        .stat-value.success { color: #50cd89; }
        .stat-value.warning { color: #ffc700; }

        .report-table {
            font-size: 9.5px;
        }

        .report-table th {
            background: #f1f4f7;
            border: 1px solid #e1e3ea;
            color: #3f4254;
            font-weight: 700;
            padding: 9px 8px;
            text-align: center;
            vertical-align: middle;
        }

        .report-table td {
            border: 1px solid #eff2f5;
            color: #3f4254;
            padding: 9px 8px;
            text-align: center;
            vertical-align: middle;
        }

        .report-table tbody tr:nth-child(even) td {
            background: #fcfcfc;
        }

        .report-table tfoot td {
            background: #eef6ff;
            color: #181c32;
            font-weight: 700;
        }

        .text-start {
            text-align: right;
        }

        .badge {
            border-radius: 12px;
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            min-width: 34px;
            padding: 3px 8px;
        }

        .badge-primary {
            background: #e9f3ff;
            color: #009ef7;
        }

        .badge-success {
            background: #e8fff3;
            color: #50cd89;
        }

        .badge-light {
            background: #f9f9f9;
            color: #7e8299;
        }

        .no-data {
            color: #7e8299;
            padding: 18px;
        }

        .footer-note {
            color: #a1a5b7;
            font-size: 9px;
            margin-top: 12px;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="report-shell">
        <div class="hero">
            <table class="hero-table">
                <tr>
                    <td>
                        <div class="eyebrow">Damage Assessment System</div>
                        <h1>تقرير تدقيق الطرق</h1>
                    </td>
                    <td class="hero-meta">
                        تاريخ التصدير<br>
                        {{ now()->format('Y-m-d H:i') }}
                    </td>
                </tr>
            </table>
        </div>

        <table class="stats-table">
            <tr>
                <td class="stat-card">
                    <div class="stat-label">ما تم حصره</div>
                    <div class="stat-value primary">{{ number_format((int) $totalSurveyed) }}</div>
                </td>
                <td class="stat-card">
                    <div class="stat-label">ما تم تدقيقه</div>
                    <div class="stat-value success">{{ number_format((int) $totalAudited) }}</div>
                </td>
                <td class="stat-card">
                    <div class="stat-label">نسبة التدقيق</div>
                    <div class="stat-value warning">{{ $auditRate }}%</div>
                </td>
                <td class="stat-card">
                    <div class="stat-label">أطوال الطرق (متر)</div>
                    <div class="stat-value">{{ number_format((float) $totalLength, 2) }}</div>
                </td>
            </tr>
        </table>

        <div class="section-title">الفلاتر الحالية</div>
        <div class="filters">
            @forelse ($activeFilters as $label => $value)
                <span class="filter-chip"><strong>{{ $label }}:</strong> {{ $value }}</span>
            @empty
                <div class="empty-filters">لا توجد فلاتر مفعلة. التقرير يعرض كامل النتائج المتاحة حسب الصلاحيات.</div>
            @endforelse
        </div>

        <div class="section-title">تفاصيل التقرير</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th class="text-start">المحافظة</th>
                    <th class="text-start">الحي</th>
                    <th>ما تم حصره</th>
                    <th>ما تم تدقيقه</th>
                    <th>نسبة التدقيق</th>
                    <th>أطوال الطرق (متر)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $rowAuditRate = (int) $row['surveyed_count'] > 0
                            ? round(((int) $row['audited_count'] / (int) $row['surveyed_count']) * 100, 1)
                            : 0;
                    @endphp
                    <tr>
                        <td class="text-start">{{ $row['governorate'] }}</td>
                        <td class="text-start">{{ $row['neighborhood'] }}</td>
                        <td><span class="badge badge-primary">{{ number_format((int) $row['surveyed_count']) }}</span></td>
                        <td><span class="badge badge-success">{{ number_format((int) $row['audited_count']) }}</span></td>
                        <td><span class="badge badge-light">{{ $rowAuditRate }}%</span></td>
                        <td>{{ number_format((float) $row['road_length_meters'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="no-data">لا توجد بيانات ضمن الفلاتر الحالية.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($rows !== [])
                <tfoot>
                    <tr>
                        <td colspan="2">الإجمالي</td>
                        <td>{{ number_format((int) $totalSurveyed) }}</td>
                        <td>{{ number_format((int) $totalAudited) }}</td>
                        <td>{{ $auditRate }}%</td>
                        <td>{{ number_format((float) $totalLength, 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>

        <div class="footer-note">تم إنشاء التقرير آليًا حسب نتائج صفحة تدقيق الطرق والفلاتر المحددة.</div>
    </div>
</body>
</html>
