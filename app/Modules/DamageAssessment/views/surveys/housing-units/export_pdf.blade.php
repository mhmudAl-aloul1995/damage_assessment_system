<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>BOQ - Housing Units</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "DejaVu Sans", "Tahoma", "Arial", sans-serif; direction: rtl; color: #172033; background: #f5f7fb; font-size: 11px; line-height: 1.55; }
        .paper { background: #fff; border: 1px solid #dfe6f0; border-radius: 12px; padding: 18px 20px; }
        .header { display: flex; justify-content: space-between; gap: 18px; border-bottom: 2px solid #e8eef6; padding-bottom: 14px; margin-bottom: 14px; }
        .eyebrow { color: #1b84ff; font-weight: 800; font-size: 11px; margin-bottom: 4px; }
        h1 { margin: 0 0 6px; color: #0f172a; font-size: 23px; font-weight: 900; }
        h2 { margin: 0 0 6px; color: #0f172a; font-size: 20px; font-weight: 900; }
        .meta { color: #64748b; font-size: 11px; }
        .unit-block { margin-top: 14px; page-break-inside: auto; }
        .unit-meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 10px; }
        .unit-meta-item { border: 1px solid #d8e7fb; border-radius: 9px; padding: 8px 10px; background: #eaf4ff; }
        .unit-meta-label { color: #64748b; font-size: 10px; margin-bottom: 3px; }
        .unit-meta-value { font-size: 13px; font-weight: 900; color: #0f172a; direction: ltr; text-align: right; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #10233f; color: #fff; font-size: 10px; padding: 7px 6px; border: 1px solid #10233f; }
        td { padding: 7px 6px; border: 1px solid #e3e9f2; vertical-align: top; background: #fff; }
        tbody tr:nth-child(even) td { background: #fafcff; }
        .num { direction: ltr; text-align: right; unicode-bidi: plaintext; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .unit { width: 55px; }
        .qty { width: 70px; }
        .code { width: 62px; }
        .section { width: 120px; }
        .desc { width: auto; }
        .empty { padding: 24px; text-align: center; color: #64748b; border: 1px dashed #ccd6e3; border-radius: 10px; background: #fbfdff; }
        .footer { margin-top: 12px; display: flex; justify-content: space-between; color: #8a97aa; font-size: 9px; }
    </style>
</head>
<body>
    <main class="paper">
        <header class="header">
            <div>
                <div class="eyebrow">Damage Assessment Project</div>
                <h1>جدول الكميات BOQ</h1>
                <div class="meta">
                    مصدر البيانات: {{ $sourceTable ?? 'audited_housing_units' }}
                    | تاريخ التوليد: {{ $generatedAt }}
                </div>
            </div>
            <div>
                <div class="eyebrow">بيانات التصدير</div>
                <h2>وحدات الإسكان</h2>
            </div>
        </header>

        @if ($boqRows->isEmpty())
            <div class="empty">لا توجد بنود جدول كميات لهذه البيانات.</div>
        @else
            @foreach ($boqRows->groupBy('globalid') as $unitRows)
                @php($firstRow = $unitRows->first())
                <section class="unit-block">
                    <div class="unit-meta">
                        <div class="unit-meta-item">
                            <div class="unit-meta-label">Object ID للمبنى</div>
                            <div class="unit-meta-value">{{ $firstRow['building_objectid'] ?: '-' }}</div>
                        </div>
                        <div class="unit-meta-item">
                            <div class="unit-meta-label">اسم مالك الوحدة</div>
                            <div class="unit-meta-value">{{ $firstRow['unit_owner'] ?: '-' }}</div>
                        </div>
                        <div class="unit-meta-item">
                            <div class="unit-meta-label">Object ID للوحدة</div>
                            <div class="unit-meta-value">{{ $firstRow['objectid'] ?: '-' }}</div>
                        </div>
                    </div>

                    <table>
                        <colgroup>
                            <col class="section">
                            <col class="code">
                            <col class="desc">
                            <col class="unit">
                            <col class="qty">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>القسم</th>
                                <th>الكود</th>
                                <th>البند</th>
                                <th>الوحدة</th>
                                <th>الكمية</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($unitRows as $row)
                                <tr>
                                    <td>{{ $row['section'] }}</td>
                                    <td class="num">{{ $row['item_code'] ?: '-' }}</td>
                                    <td>{{ $row['description'] }}</td>
                                    <td class="num">{{ $row['unit'] ?: '-' }}</td>
                                    <td class="num">{{ $row['quantity'] ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endforeach
        @endif

        <footer class="footer">
            <span>Palestinian Housing Council (PHC)</span>
            <span>{{ $generatedAt }}</span>
        </footer>
    </main>
</body>
</html>
