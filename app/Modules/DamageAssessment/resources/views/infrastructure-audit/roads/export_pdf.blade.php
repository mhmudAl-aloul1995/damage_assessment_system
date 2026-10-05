<!doctype html>
<html lang="ar" dir="rtl">
@php
    $totalSurveyed = collect($rows)->sum('surveyed_count');
    $totalAudited = collect($rows)->sum('audited_count');
    $totalLength = collect($rows)->sum('road_length_meters');
    $auditRate = $totalSurveyed > 0 ? round(($totalAudited / $totalSurveyed) * 100, 1) : 0;
    $activeFilters = collect($filters)->filter(fn ($value) => filled($value));
    $generatedAt = now()->format('H:i Y-m-d');
@endphp
<head>
    <meta charset="utf-8">
    <title>تقرير تدقيق الطرق</title>
    <style>
        @page { margin: 10mm; }

        @font-face {
            font-family: 'droidarabickufi';
            font-style: normal;
            font-weight: 400;
            src: url('{{ public_path('DroidArabicKufi.ttf') }}') format('truetype');
        }

        * { box-sizing: border-box; }

        body {
            background: #f5f7fb;
            color: #172033;
            direction: rtl;
            font-family: 'droidarabickufi', 'Droid Arabic Kufi', 'DejaVu Sans', 'Tahoma', 'Arial', sans-serif;
            font-size: 10px;
            line-height: 1.55;
            margin: 0;
        }

        .paper {
            background: #ffffff;
            border: 1px solid #dfe6f0;
            border-radius: 12px;
            padding: 18px 20px;
        }

        .header-table,
        .summary-table,
        .report-table,
        .footer-table {
            border-collapse: collapse;
            width: 100%;
        }

        .header {
            border-bottom: 2px solid #e8eef6;
            margin-bottom: 14px;
            padding-bottom: 14px;
        }

        .header-table td {
            border: 0;
            padding: 0;
            vertical-align: top;
            width: 50%;
        }

        .eyebrow {
            color: #1b84ff;
            font-size: 10px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        h1 {
            color: #0f172a;
            font-size: 22px;
            font-weight: 900;
            line-height: 1.35;
            margin: 0 0 6px;
        }

        h2 {
            color: #0f172a;
            font-size: 18px;
            font-weight: 900;
            line-height: 1.35;
            margin: 0 0 6px;
        }

        .meta {
            color: #64748b;
            font-size: 10px;
        }

        .summary-table {
            border-spacing: 10px;
            border-collapse: separate;
            margin: 0 -10px 14px;
            table-layout: fixed;
        }

        .summary-table td {
            background: #fbfdff;
            border: 1px solid #e5eaf2;
            border-radius: 9px;
            padding: 10px 12px;
            vertical-align: middle;
            width: 25%;
        }

        .card-label {
            color: #64748b;
            font-size: 9px;
            margin-bottom: 4px;
        }

        .card-value {
            color: #111827;
            direction: ltr;
            font-size: 16px;
            font-weight: 900;
            text-align: right;
        }

        .primary { color: #1b84ff; }
        .success { color: #16a34a; }
        .warning { color: #ffc700; }

        .filters {
            margin-bottom: 12px;
        }

        .filter-chip {
            background: #f1faff;
            border: 1px solid #d7e8fb;
            border-radius: 8px;
            color: #172033;
            display: inline-block;
            font-size: 9px;
            margin: 0 0 6px 6px;
            padding: 4px 8px;
        }

        .filter-chip strong {
            color: #1b84ff;
        }

        .section-title {
            color: #172033;
            font-size: 12px;
            font-weight: 900;
            margin: 0 0 8px;
        }

        .empty {
            background: #fbfdff;
            border: 1px dashed #ccd6e3;
            border-radius: 10px;
            color: #64748b;
            padding: 18px;
            text-align: center;
        }

        .report-table {
            table-layout: fixed;
        }

        .report-table th {
            background: #10233f;
            border: 1px solid #10233f;
            color: #ffffff;
            font-size: 9px;
            font-weight: 900;
            padding: 7px 6px;
            text-align: center;
            vertical-align: middle;
        }

        .report-table td {
            background: #ffffff;
            border: 1px solid #e3e9f2;
            color: #172033;
            padding: 7px 6px;
            text-align: center;
            vertical-align: middle;
        }

        .report-table tbody tr:nth-child(even) td {
            background: #fafcff;
        }

        .report-table tfoot td {
            background: #eaf4ff;
            font-weight: 900;
        }

        .text-start {
            text-align: right;
        }

        .num {
            direction: ltr;
            font-variant-numeric: tabular-nums;
            text-align: right;
            unicode-bidi: plaintext;
            white-space: nowrap;
        }

        .governorate { width: 14%; }
        .municipality { width: 15%; }
        .neighborhood { width: 14%; }
        .damage-level { width: 13%; }
        .count { width: 10%; }
        .rate { width: 11%; }
        .length { width: 15%; }

        .footer {
            color: #8a97aa;
            font-size: 9px;
            margin-top: 12px;
        }

        .footer-table td {
            border: 0;
            padding: 0;
        }

        .footer-left {
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="paper">
        <div class="header">
            <table class="header-table">
                <tr>
                    <td>
                        <div class="eyebrow">Damage Assessment Project</div>
                        <h1>تقرير تدقيق الطرق</h1>
                        <div class="meta">
                            النتائج حسب الفلاتر الحالية | تاريخ التصدير: {{ $generatedAt }}
                        </div>
                    </td>
                    <td>
                        <div class="eyebrow">ملخص التقرير</div>
                        <h2>تدقيق البنية التحتية - الطرق</h2>
                        <div class="meta">
                            ما تم حصره: {{ number_format((int) $totalSurveyed) }}
                            | ما تم تدقيقه: {{ number_format((int) $totalAudited) }}
                            | نسبة التدقيق: {{ $auditRate }}%
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div>
            <table class="summary-table">
                <tr>
                    <td>
                        <div class="card-label">أطوال الطرق (متر)</div>
                        <div class="card-value">{{ number_format((float) $totalLength, 2) }}</div>
                    </td>
                    <td>
                        <div class="card-label">نسبة التدقيق</div>
                        <div class="card-value warning">{{ $auditRate }}%</div>
                    </td>
                    <td>
                        <div class="card-label">ما تم تدقيقه</div>
                        <div class="card-value success">{{ number_format((int) $totalAudited) }}</div>
                    </td>
                    <td>
                        <div class="card-label">ما تم حصره</div>
                        <div class="card-value primary">{{ number_format((int) $totalSurveyed) }}</div>
                    </td>
                </tr>
            </table>
        </div>

        @if ($activeFilters->isNotEmpty())
            <div class="filters">
                @foreach ($activeFilters as $label => $value)
                    <span class="filter-chip"><strong>{{ $label }}:</strong> {{ $value }}</span>
                @endforeach
            </div>
        @endif

        @if ($rows === [])
            <div class="empty">لا توجد بيانات ضمن الفلاتر الحالية.</div>
        @else
            <div class="section-title">تفاصيل التقرير</div>
            <table class="report-table">
                <colgroup>
                    <col class="length">
                    <col class="rate">
                    <col class="count">
                    <col class="count">
                    <col class="damage-level">
                    <col class="neighborhood">
                    <col class="municipality">
                    <col class="governorate">
                </colgroup>
                <thead>
                    <tr>
                        <th>أطوال الطرق (متر)</th>
                        <th>نسبة التدقيق</th>
                        <th>ما تم تدقيقه</th>
                        <th>ما تم حصره</th>
                        <th>مستوى الضرر</th>
                        <th>الحي</th>
                        <th>البلدية</th>
                        <th>المحافظة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $rowAuditRate = (int) $row['surveyed_count'] > 0
                                ? round(((int) $row['audited_count'] / (int) $row['surveyed_count']) * 100, 1)
                                : 0;
                        @endphp
                        <tr>
                            <td class="num">{{ number_format((float) $row['road_length_meters'], 2) }}</td>
                            <td class="num">{{ $rowAuditRate }}%</td>
                            <td class="num">{{ number_format((int) $row['audited_count']) }}</td>
                            <td class="num">{{ number_format((int) $row['surveyed_count']) }}</td>
                            <td class="text-start">{{ $row['road_damage_level'] }}</td>
                            <td class="text-start">{{ $row['neighborhood'] }}</td>
                            <td class="text-start">{{ $row['municipality'] }}</td>
                            <td class="text-start">{{ $row['governorate'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td class="num">{{ number_format((float) $totalLength, 2) }}</td>
                        <td class="num">{{ $auditRate }}%</td>
                        <td class="num">{{ number_format((int) $totalAudited) }}</td>
                        <td class="num">{{ number_format((int) $totalSurveyed) }}</td>
                        <td colspan="4">الإجمالي</td>
                    </tr>
                </tfoot>
            </table>
        @endif

        <div class="footer">
            <table class="footer-table">
                <tr>
                    <td>Palestinian Housing Council (PHC)</td>
                    <td class="footer-left">{{ $generatedAt }}</td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
