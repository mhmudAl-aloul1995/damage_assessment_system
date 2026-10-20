<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>نتائج تأهيل المساكن</title></head><body>
@include('reh::pdf.style')
<div class="hero"><div class="eyebrow">REH / PHASE 02</div><h1>تقرير نتائج الوحدات السكنية</h1><p>ملخص الملفات المطابقة للفلاتر المعتمدة عند التصدير</p></div>
<table class="meta"><tr><td><span>الوحدات المطابقة</span><br><strong>{{ $rows->count() }} وحدة</strong></td><td><span>تاريخ إصدار التقرير</span><br><strong dir="ltr">{{ $generatedAt }}</strong></td></tr><tr><td><span>أُعد بواسطة</span><br>{{ $generatedBy }}</td><td><span>المباني ضمن النتائج</span><br>{{ $buildingCount }} مبنى</td></tr></table>
<h2 class="section-title">نطاق التقرير</h2>
<table class="data">@foreach($filterSummary as $label => $value)<tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>@endforeach</table>
<div class="notice">يشمل التقرير جميع الوحدات المطابقة، وليس الصفحة الحالية فقط. البيانات الناقصة لا تُستبدل بصفر. القبول المبدئي منفصل عن اعتماد التكلفة، والأسعار غير متوفرة حاليًا.</div>
@if($rows->isNotEmpty())<pagebreak />@endif
<h2 class="section-title">قائمة الوحدات · {{ $rows->count() }}</h2>
@if($columnSections)
@forelse($columnRows as $row)
@if(!$loop->first)<pagebreak />@endif
<h2 class="section-title">الملف {{ $loop->iteration }} من {{ $columnRows->count() }}@if(isset($row['unit_reference'])) · معرّف الوحدة {{ $row['unit_reference'] }}@endif</h2>
@foreach($columnSections as $group => $fields)
<h3>{{ $group }}</h3><table class="data">@foreach($fields as $key => $label)<tr><td>{{ $label }}</td><td>{{ \App\Modules\Reh\Services\RehPdf::columnValue($key, $row[$key] ?? null) }}</td></tr>@endforeach</table>
@endforeach
@empty<p>لا توجد وحدات مطابقة للفلاتر المحددة.</p>@endforelse
@elseif($columnGroups)
@if(count($columnGroups) > 1)<p class="muted">قُسّمت الأعمدة المختارة إلى {{ count($columnGroups) }} أجزاء لسهولة القراءة؛ يتكرر العمود الأول لربط الصفوف بين الأجزاء، مع ثبات ترتيب الوحدات.</p>@endif
@foreach($columnGroups as $group)
@if(!$loop->first)<pagebreak />@endif
@if(count($columnGroups) > 1)<h3>الأعمدة المختارة · الجزء {{ $loop->iteration }} من {{ count($columnGroups) }}</h3>@endif
<table class="listing"><thead><tr>@foreach($group as $key => $label)<th>{{ $label }}</th>@endforeach</tr></thead><tbody>
@forelse($columnRows as $row)<tr>@foreach($group as $key => $label)<td>{{ \App\Modules\Reh\Services\RehPdf::columnValue($key, $row[$key] ?? null) }}</td>@endforeach</tr>
@empty<tr><td colspan="{{ count($group) }}">لا توجد وحدات مطابقة للفلاتر المحددة.</td></tr>@endforelse
</tbody></table>
@endforeach
@else
<table class="listing"><thead><tr><th style="width:13%">الوحدة / المبنى</th><th style="width:23%">المالك والموقع</th><th style="width:23%">حالة التدقيق</th><th style="width:14%">الدرجة / 100</th><th style="width:27%">الأولوية</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td><strong>{{ $row['unit'] }}</strong><br><span class="muted">مبنى {{ $row['building'] }}</span></td><td>{{ $row['owner'] }}<br><span class="small">{{ $row['location'] }}</span></td><td>{{ $row['workflow'] }}@if($row['missing'])<br><span class="small">يلزم التحقق من المصدر</span>@endif</td><td>{{ $row['score'] }}</td><td>{{ $row['priority'] }}</td></tr>
@empty<tr><td colspan="5">لا توجد وحدات مطابقة للفلاتر المحددة.</td></tr>@endforelse
</tbody></table>
@endif
</body></html>
