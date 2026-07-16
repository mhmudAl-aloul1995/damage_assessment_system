<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>HEKS BOQ - {{ $beneficiary->code }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "DejaVu Sans", "Tahoma", "Arial", sans-serif; direction: rtl; color: #172033; background: #f5f7fb; font-size: 11px; line-height: 1.55; }
        .paper { background: #fff; border: 1px solid #dfe6f0; border-radius: 14px; padding: 18px 20px; }
        .header { display: flex; justify-content: space-between; gap: 18px; border-bottom: 2px solid #e8eef6; padding-bottom: 14px; margin-bottom: 14px; }
        .brand { min-width: 260px; }
        .eyebrow { color: #1b84ff; font-weight: 800; font-size: 11px; margin-bottom: 4px; }
        h1 { margin: 0 0 6px; color: #0f172a; font-size: 24px; font-weight: 900; }
        .meta { color: #64748b; font-size: 11px; }
        .summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 14px; }
        .card { border: 1px solid #e5eaf2; border-radius: 10px; padding: 10px 12px; background: #fbfdff; }
        .card-label { color: #64748b; font-size: 10px; margin-bottom: 4px; }
        .card-value { font-size: 17px; font-weight: 900; color: #111827; direction: ltr; text-align: right; }
        .card-value.success { color: #16a34a; }
        .section-title { margin: 16px 0 7px; padding: 8px 10px; border-right: 4px solid #1b84ff; background: #f3f8ff; color: #0f172a; font-weight: 900; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th { background: #10233f; color: #fff; font-size: 10px; padding: 7px 6px; border: 1px solid #10233f; }
        td { padding: 7px 6px; border: 1px solid #e3e9f2; vertical-align: top; background: #fff; }
        tbody tr:nth-child(even) td { background: #fafcff; }
        .num { direction: ltr; text-align: right; unicode-bidi: plaintext; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .code { width: 58px; }
        .section { width: 120px; }
        .desc { width: auto; }
        .unit { width: 55px; }
        .money { width: 92px; }
        .qty { width: 68px; }
        .notes { width: 125px; }
        .footer { margin-top: 12px; display: flex; justify-content: space-between; color: #8a97aa; font-size: 9px; }
        .empty { padding: 24px; text-align: center; color: #64748b; border: 1px dashed #ccd6e3; border-radius: 10px; background: #fbfdff; }
    </style>
</head>
<body>
    <main class="paper">
        <header class="header">
            <div class="brand">
                <div class="eyebrow">HEKS Shelter Repairs</div>
                <h1>جدول الكميات والتسعير BOQ</h1>
                <div class="meta">
                    كود المستفيد: {{ $beneficiary->code ?: '-' }}
                    @if ($beneficiary->identity_number)
                        | رقم الهوية: {{ $beneficiary->identity_number }}
                    @endif
                </div>
            </div>
            <div class="brand">
                <div class="eyebrow">بيانات المستفيد</div>
                <h1 style="font-size: 18px;">{{ $beneficiary->name ?: '-' }}</h1>
                <div class="meta">
                    @if ($beneficiary->phone)
                        جوال: {{ $beneficiary->phone }}
                    @endif
                    @if ($beneficiary->field_engineer)
                        | المهندس: {{ $beneficiary->field_engineer }}
                    @endif
                </div>
            </div>
        </header>

        <section class="summary">
            <div class="card">
                <div class="card-label">إجمالي التسعير</div>
                <div class="card-value success">{{ number_format($boqTotal, 2) }} ILS</div>
            </div>
            <div class="card">
                <div class="card-label">عدد البنود المسعرة</div>
                <div class="card-value">{{ number_format($pricingRows->count()) }}</div>
            </div>
            <div class="card">
                <div class="card-label">قيمة المنحة</div>
                <div class="card-value">{{ $beneficiary->grant_amount ? number_format((float) $beneficiary->grant_amount, 2).' ILS' : '-' }}</div>
            </div>
        </section>

        @forelse ($pricingSections as $section)
            @php
                $sectionRows = $pricingRows->filter(fn ($row) => ($row['section'] !== '' ? $row['section'] : 'بدون قسم') === $section['section'])->values();
            @endphp

            <div class="section-title">
                {{ $section['section'] }} | {{ number_format($section['items_count']) }} بند | {{ number_format((float) $section['total'], 2) }} ILS
            </div>
            <table>
                <colgroup>
                    <col class="code">
                    <col class="section">
                    <col class="desc">
                    <col class="unit">
                    <col class="money">
                    <col class="qty">
                    <col class="money">
                    <col class="notes">
                </colgroup>
                <thead>
                    <tr>
                        <th>رقم</th>
                        <th>القسم</th>
                        <th>الوصف</th>
                        <th>الوحدة</th>
                        <th>سعر الوحدة ILS</th>
                        <th>الكمية</th>
                        <th>الإجمالي ILS</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sectionRows as $row)
                        <tr>
                            <td class="num">{{ $row['item_code'] ?: '-' }}</td>
                            <td>{{ $row['section'] ?: '-' }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td class="num">{{ $row['unit'] ?: '-' }}</td>
                            <td class="num">{{ number_format((float) $row['unit_price_ils'], 2) }}</td>
                            <td class="num">{{ number_format((float) $row['quantity'], 3) }}</td>
                            <td class="num">{{ number_format((float) $row['total_price_ils'], 2) }}</td>
                            <td>{{ $row['notes'] ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @empty
            <div class="empty">لا توجد بنود مسعرة لهذا المستفيد.</div>
        @endforelse

        <footer class="footer">
            <span>Damage Assessment Project | HEKS BOQ</span>
            <span>{{ $generatedAt }}</span>
        </footer>
    </main>
</body>
</html>
