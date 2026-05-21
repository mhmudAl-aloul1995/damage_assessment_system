<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>Damage Assessment - PHC</title>
    <style>
        @page { size: A4-L; margin: 10mm; }
        body { direction: rtl; font-family: dejavusans, sans-serif; color: #17324d; font-size: 10pt; }
        .cover { background: #0f4c81; color: #fff; text-align: center; padding-top: 55mm; height: 125mm; }
        .cover h1 { font-size: 25pt; line-height: 1.8; margin: 0 0 10mm; }
        .cover p { font-size: 13pt; }
        .head { border-bottom: 1.5px solid #1687c7; padding-bottom: 4mm; margin-bottom: 5mm; }
        .kicker { color: #f58220; font-size: 15pt; font-weight: bold; text-align: left; }
        .sub { color: #1687c7; font-size: 9pt; text-align: left; }
        h2 { color: #0f4c81; font-size: 15pt; text-align: center; margin: 4mm 0; }
        h3 { color: #0f4c81; font-size: 12pt; margin: 3mm 0; text-align: center; }
        .stats td { text-align: center; border-bottom: 1px solid #1687c7; padding: 3mm 2mm; }
        .num { color: #138bd0; font-size: 16pt; display: block; }
        .label { color: #526b82; font-size: 8pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 4mm; }
        th { background: #0f4c81; color: #fff; font-weight: bold; }
        th, td { border: 1px solid #c9d9e4; padding: 2mm; text-align: center; font-size: 8pt; }
        tr:nth-child(even) td { background: #f4f9fc; }
        .chart td { border: 0; padding: 1mm; font-size: 8pt; vertical-align: top; }
        .bar-bg { background: #edf5fa; height: 4mm; }
        .bar { height: 4mm; background: #1687c7; }
        .note { line-height: 1.9; text-align: right; font-size: 10pt; }
        .footer { border-top: 1px solid #d7e3eb; color: #718096; font-size: 8pt; margin-top: 5mm; padding-top: 2mm; text-align: left; }
    </style>
</head>
<body>
@php
    $pageNumber = 1;
    $formatNumber = fn ($value) => number_format((int) $value);
    $bars = function (array $items): string {
        $max = max(array_merge([1], array_map(fn ($item) => (int) $item['value'], $items)));

        return collect($items)->take(5)->map(function (array $item) use ($max): string {
            $width = max(1, ((int) $item['value'] / $max) * 100);

            return '<tr><td style="width: 28%;">'.e($item['label']).'</td><td><div class="bar-bg"><div class="bar" style="width: '.$width.'%;"></div></div></td><td style="width: 20%;">'.number_format((int) $item['value']).'</td></tr>';
        })->implode('');
    };
@endphp

<div class="cover">
    <h1>المنظومة الوطنية المتكاملة<br>لحصر الأضرار - قطاع غزة<br>قطاع الإسكان</h1>
    <p>Damage Assessment System - INDAS</p>
    <p>{{ $reportDate }}</p>
</div>

<pagebreak />

<div>
    <div class="head"><div class="kicker">قطاع غزة</div><div class="sub">المنظومة الوطنية المتكاملة في قطاع غزة<br>{{ $reportDate }}</div></div>
    <h2>
    نتائج وإحصائيات مشروع حصر الأضرار - قطاع غزة
</h2>
    <table class="stats">
        <tr>
            <td><span class="num">{{ $formatNumber($totals['affected_population']) }}</span><span class="label">السكان المتأثرون</span></td>
            <td><span class="num">{{ $formatNumber($totals['housing_units']) }}</span><span class="label">الوحدات السكنية</span></td>
            <td><span class="num">{{ $formatNumber($totals['buildings']) }}</span><span class="label">إجمالي المباني</span></td>
            <td><span class="num">{{ $formatNumber($totals['assessed_housing_units']) }}</span><span class="label">الوحدات التي تم تقييمها</span></td>
        </tr>
    </table>
    <table>
        <thead><tr><th>المحافظة</th><th>المباني</th><th>الوحدات السكنية</th><th>الوحدات المقيمة</th><th>السكان المتأثرون</th></tr></thead>
        <tbody>
            @foreach ($summaryRows as $row)
                <tr><td>{{ $row['name'] }}</td><td>{{ $formatNumber($row['buildings']) }}</td><td>{{ $formatNumber($row['housing_units']) }}</td><td>{{ $formatNumber($row['assessed_housing_units']) }}</td><td>{{ $formatNumber($row['affected_population']) }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <table class="chart"><tr><td><h3>توزيع أضرار الوحدات السكنية</h3><table class="chart">{!! $bars($damageDistribution) !!}</table></td><td><h3>استخدام وإشغال الوحدات</h3><table class="chart">{!! $bars($occupancyDistribution) !!}</table></td><td><h3>توزيع أنواع المباني</h3><table class="chart">{!! $bars($buildingTypeDistribution) !!}</table></td></tr></table>
    <div class="footer">صفحة {{ $pageNumber++ }} من {{ $totalPages }}</div>
</div>

@foreach ($governorates as $governorate)
    <pagebreak />
    <div>
        <div class="head"><div class="kicker">محافظة {{ $governorate['name'] }}</div><div class="sub">إحصائيات المحافظات - {{ $reportDate }}</div></div>
        <table class="stats">
            <tr>
                <td><span class="num">{{ $formatNumber($governorate['totals']['affected_population']) }}</span><span class="label">السكان المتأثرون</span></td>
                <td><span class="num">{{ $formatNumber($governorate['totals']['housing_units']) }}</span><span class="label">الوحدات السكنية</span></td>
                <td><span class="num">{{ $formatNumber($governorate['totals']['buildings']) }}</span><span class="label">إجمالي المباني</span></td>
                <td><span class="num">{{ $formatNumber($governorate['totals']['assessed_housing_units']) }}</span><span class="label">الوحدات التي تم تقييمها</span></td>
            </tr>
        </table>
        <table>
            <thead><tr><th>البلدية</th><th>المباني</th><th>الوحدات</th><th>ضرر جزئي</th><th>ضرر كلي</th><th>مراجعة لجنة</th><th>مشغولة</th><th>غير مشغولة</th></tr></thead>
            <tbody>
                @forelse ($governorate['municipalities'] as $row)
                    <tr><td>{{ $row['name'] }}</td><td>{{ $formatNumber($row['buildings']) }}</td><td>{{ $formatNumber($row['housing_units']) }}</td><td>{{ $formatNumber($row['partially_damaged']) }}</td><td>{{ $formatNumber($row['fully_damaged']) }}</td><td>{{ $formatNumber($row['committee_review']) }}</td><td>{{ $formatNumber($row['occupied']) }}</td><td>{{ $formatNumber($row['vacant']) }}</td></tr>
                @empty
                    <tr><td colspan="8">لا توجد بيانات</td></tr>
                @endforelse
            </tbody>
        </table>
        <table class="chart"><tr><td><h3>توزيع الأضرار</h3><table class="chart">{!! $bars($governorate['damage']) !!}</table></td><td><h3>إشغال الوحدات</h3><table class="chart">{!! $bars($governorate['occupancy']) !!}</table></td><td><h3>أنواع المباني</h3><table class="chart">{!! $bars($governorate['building_types']) !!}</table></td></tr></table>
        <div class="footer">صفحة {{ $pageNumber++ }} من {{ $totalPages }}</div>
    </div>
@endforeach

@foreach ($neighborhoodPages as $page)
    <pagebreak />
    <div>
        <div class="head"><div class="kicker">أحياء محافظة {{ $page['governorate'] }}</div><div class="sub">جداول الأحياء - {{ $reportDate }}</div></div>
        <table>
            <thead><tr><th>الحي</th><th>المباني</th><th>الوحدات</th><th>ضرر جزئي</th><th>ضرر كلي</th><th>مراجعة لجنة</th><th>مشغولة</th><th>غير مشغولة</th></tr></thead>
            <tbody>
                @forelse ($page['rows'] as $row)
                    <tr><td>{{ $row['name'] }}</td><td>{{ $formatNumber($row['buildings']) }}</td><td>{{ $formatNumber($row['housing_units']) }}</td><td>{{ $formatNumber($row['partially_damaged']) }}</td><td>{{ $formatNumber($row['fully_damaged']) }}</td><td>{{ $formatNumber($row['committee_review']) }}</td><td>{{ $formatNumber($row['occupied']) }}</td><td>{{ $formatNumber($row['vacant']) }}</td></tr>
                @empty
                    <tr><td colspan="8">لا توجد بيانات</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="footer">صفحة {{ $pageNumber++ }} من {{ $totalPages }}</div>
    </div>
@endforeach

<pagebreak />
<div>
    <div class="head"><div class="kicker">المنهجية</div></div>
    <div class="note">
        تعتمد المنهجية على جداول buildings و housing_units و assessments و edit_assessments و assessment_statuses و building_statuses و housing_statuses.
        تستخدم الحسابات حقل municipalitie كما هو موجود فعلياً، وحقل building_damage_status للمباني، وحقل unit_damage_status للوحدات السكنية.
        جميع الأرقام ديناميكية من قاعدة البيانات، مع استخدام تقدير مرجعي للسكان عند غياب عدد أفراد موحد لكل وحدة.
    </div>
    <div class="footer">صفحة {{ $pageNumber++ }} من {{ $totalPages }}</div>
</div>

<pagebreak />
<div>
    <div class="head"><div class="kicker">التحديات والقيود والتوصيات</div></div>
    <div class="note">
        تشمل التحديات تفاوت اكتمال بيانات المواقع، ووجود قيم غير محددة في بعض الحقول، وتعدد صيغ أسماء المحافظات.
        توصي النسخة بتوحيد القيم المرجعية، واستكمال الإحداثيات، ومراجعة السجلات غير المحددة قبل اعتماد النسخ الرسمية.
        هذه النسخة تعمل بمحرك mPDF ولا تحتاج إلى Chrome على السيرفر.
    </div>
    <div class="footer">صفحة {{ $pageNumber++ }} من {{ $totalPages }}</div>
</div>
</body>
</html>
