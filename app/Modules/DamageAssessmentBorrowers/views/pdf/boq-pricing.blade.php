<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>BOQ - {{ $borrower->form_number ?: $borrower->id }}</title>
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
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 14px; }
        .card { border: 1px solid #e5eaf2; border-radius: 9px; padding: 10px 12px; background: #fbfdff; }
        .card-label { color: #64748b; font-size: 10px; margin-bottom: 4px; }
        .card-value { font-size: 16px; font-weight: 900; color: #111827; direction: ltr; text-align: right; }
        .primary { color: #1b84ff; }
        .success { color: #16a34a; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th { background: #10233f; color: #fff; font-size: 10px; padding: 7px 6px; border: 1px solid #10233f; }
        td { padding: 7px 6px; border: 1px solid #e3e9f2; vertical-align: top; background: #fff; }
        tbody tr:nth-child(even) td { background: #fafcff; }
        tfoot td { background: #eaf4ff; font-weight: 900; }
        .num { direction: ltr; text-align: right; unicode-bidi: plaintext; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .code { width: 62px; }
        .desc { width: auto; }
        .unit { width: 62px; }
        .money { width: 92px; }
        .qty { width: 70px; }
        .empty { padding: 24px; text-align: center; color: #64748b; border: 1px dashed #ccd6e3; border-radius: 10px; background: #fbfdff; }
        .footer { margin-top: 12px; display: flex; justify-content: space-between; color: #8a97aa; font-size: 9px; }
    </style>
</head>
<body>
    <main class="paper">
        <header class="header">
            <div>
                <div class="eyebrow">Damage Assessment Project</div>
                <h1>جدول الكميات والتسعير BOQ</h1>
                <div class="meta">
                    النموذج: {{ $borrower->form_number ?: '-' }}
                    | القرض: {{ $borrower->loan_number ?: '-' }}
                    | الهوية: {{ $borrower->borrower_id_number ?: '-' }}
                </div>
            </div>
            <div>
                <div class="eyebrow">بيانات المستفيد</div>
                <h2>{{ $borrower->borrower_name ?: '-' }}</h2>
                <div class="meta">
                    @if ($borrower->phone_primary)
                        جوال: {{ $borrower->phone_primary }}
                    @endif
                </div>
            </div>
        </header>

        <section class="summary">
            <div class="card">
                <div class="card-label">الإجمالي بالدولار</div>
                <div class="card-value primary">{{ number_format((float) $borrower->boq_total_usd, 2) }} $</div>
            </div>
            <div class="card">
                <div class="card-label">الإجمالي بالشيكل</div>
                <div class="card-value success">{{ number_format((float) $borrower->boq_total_ils, 2) }} ILS</div>
            </div>
            <div class="card">
                <div class="card-label">سعر الصرف</div>
                <div class="card-value">{{ number_format((float) $borrower->exchange_rate, 4) }}</div>
            </div>
            <div class="card">
                <div class="card-label">عدد البنود</div>
                <div class="card-value">{{ number_format($pricingRows->count()) }}</div>
            </div>
        </section>

        @if ($pricingRows->isEmpty())
            <div class="empty">لا توجد بنود مسعرة لهذا المستفيد.</div>
        @else
            <table>
                <colgroup>
                    <col class="code">
                    <col class="desc">
                    <col class="unit">
                    <col class="money">
                    <col class="money">
                    <col class="qty">
                    <col class="money">
                    <col class="money">
                </colgroup>
                <thead>
                    <tr>
                        <th>الكود</th>
                        <th>البند</th>
                        <th>الوحدة</th>
                        <th>السعر $</th>
                        <th>السعر ILS</th>
                        <th>الكمية</th>
                        <th>الإجمالي $</th>
                        <th>الإجمالي ILS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pricingRows as $row)
                        <tr>
                            <td class="num">{{ $row['item_code'] ?: '-' }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td class="num">{{ $row['unit'] ?: '-' }}</td>
                            <td class="num">{{ number_format((float) $row['unit_price'], 2) }}</td>
                            <td class="num">{{ number_format((float) $row['unit_price_ils'], 2) }}</td>
                            <td class="num">{{ number_format((float) $row['quantity'], 2) }}</td>
                            <td class="num">{{ number_format((float) $row['total_price'], 2) }}</td>
                            <td class="num">{{ number_format((float) $row['total_price_ils'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6">الإجمالي</td>
                        <td class="num">{{ number_format((float) $borrower->boq_total_usd, 2) }}</td>
                        <td class="num">{{ number_format((float) $borrower->boq_total_ils, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif

        <footer class="footer">
            <span>Palestinian Housing Council (PHC)</span>
            <span>{{ $generatedAt }}</span>
        </footer>
    </main>
</body>
</html>
