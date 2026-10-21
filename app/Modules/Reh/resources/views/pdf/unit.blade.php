<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>ملف تقييم الوحدة</title></head><body>
@include('reh::pdf.style')
<div class="hero"><div class="eyebrow">التقييم الهندسي والاجتماعي · المرحلة الثانية</div><h1>تقييم الوحدة السكنية<br>معرّف حصر الوحدة {{ $unitReference }}</h1><p>معرّف حصر المبنى {{ $buildingReference }} · {{ $ownerName }}</p></div>
<table class="meta"><tr><td><span>تاريخ الزيارة</span><br>{{ $visitDate }}</td><td><span>المحافظة / الحي</span><br>{{ $location }}</td></tr><tr><td><span>المهندس</span><br>{{ $engineer }}</td><td><span>حالة التدقيق</span><br>{{ $workflow }}</td></tr><tr><td><span>تاريخ إصدار التقرير</span><br>{{ $generatedAt }}</td><td><span>آخر مزامنة</span><br>{{ $syncedAt }}</td></tr></table>
<table class="scorecard"><tr><td><span>الدرجة النهائية / 100</span><br><strong class="score">{{ $total }}</strong><br>{{ $priority }}</td><td><span>الهندسي / 40</span><br><strong>{{ $engineering }}</strong><br><span>الاجتماعي / 60</span><br><strong>{{ $social }}</strong></td></tr></table>
<div class="notice">المشاركة في التقييم لا تعني الحصول على المساعدة. التكلفة تُراجع بصورة مستقلة في شاشة التسعير. لا تُستبدل الإجابة الناقصة بصفر. التقرير يعكس البيانات المتاحة وصلاحيات مُصدِره.</div>
@if($sourceMissing)<div class="notice">السجل أو المبنى غير موجود في آخر مزامنة. يلزم التحقق من المصدر قبل الاعتماد.</div>@endif
<p class="muted">أُعد بواسطة: {{ $generatedBy }}</p>
<pagebreak />
<h2 class="section-title">01 · المبنى والبيانات العامة</h2>
<table class="data">@foreach($identifiers as $label => $value)<tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>@endforeach</table>
@foreach($buildingSections as $section)<h3>{{ preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $section['label']) }}</h3><table class="data">@foreach($section['fields'] as $field)<tr><td>{{ $field['label'] }}</td><td>{{ $field['value'] }}</td></tr>@endforeach</table>@endforeach
<pagebreak />
<h2 class="section-title">02 · الوحدة والأسرة</h2>
@unless($sensitive)<div class="notice">بيانات الهوية والأسرة والصحة والحماية محجوبة وفق صلاحيات مُصدِر التقرير.</div>@endunless
@forelse($unitSections as $section)<h3>{{ preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $section['label']) }}</h3><table class="data">@foreach($section['fields'] as $field)<tr><td>{{ $field['label'] }}</td><td>{{ $field['value'] }}</td></tr>@endforeach</table>@empty<p>لا توجد بيانات ميدانية متاحة ضمن صلاحياتك.</p>@endforelse
<pagebreak />
<h2 class="section-title">03 · التقييم والأولوية</h2>
<p class="section-caption">النهائي: {{ $total }} / 100 · {{ $priority }}. النقاط المحسومة لا تمثل درجة نهائية عند وجود بيانات معلقة.</p>
@foreach($scoreGroups as $label => $group)<h3>{{ $label }}</h3><table class="listing"><thead><tr><th style="width:40%">المؤشر</th><th style="width:15%">النقاط</th><th style="width:45%">النتيجة والتفسير</th></tr></thead><tbody>@foreach($group['rows'] as $row)<tr><td>{{ $row['label'] }}</td><td><span dir="ltr">{{ $row['points'] ?? 'معلّق' }} / {{ $row['maximum'] }}</span></td><td>{{ $row['reason'] }}</td></tr>@endforeach</tbody></table>@endforeach
<pagebreak />
<h2 class="section-title">04 · كميات أعمال التأهيل والترميم</h2>
<div class="notice">هذه كميات وليست تكلفة معتمدة. تُعرض القيم الميدانية والمراجعات المحلية إن وجدت. الصور والمرفقات متاحة من ملف الوحدة داخل النظام.</div>
<table class="listing"><thead><tr><th style="width:52%">البند</th><th style="width:16%">الوحدة</th><th style="width:16%">الميداني</th><th style="width:16%">مراجعة محلية</th></tr></thead><tbody>
@foreach($quantities as $item)<tr><td>{{ $item['label'] }}</td><td>{{ \Illuminate\Support\Str::before($item['unit'], ' ') }}</td><td>{{ $item['source'] ?? 'غير مسجل' }}</td><td>{{ $item['reviewed'] ?? '—' }}</td></tr>@endforeach
</tbody></table>
</body></html>
