@extends('layouts.app')
@section('title', 'HEKS — المساعدة النقدية لإصلاح المأوى الطارئ')
@section('pageName', 'HEKS · المرحلة الثالثة')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/heks-shelter.css') }}?v={{ filemtime(base_path('assets/css/heks-shelter.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/custom/leaflet/leaflet.bundle.rtl.css') }}">
<div id="heks-workspace" dir="rtl">
    <header class="hs-hero">
        <div><span class="hs-eyebrow">HEKS / PHASE 03</span><h2>خطوة نحو مأوى آمن</h2><p>المساعدة النقدية لإصلاح المأوى الطارئ</p></div>
        <div class="hs-hero-meta"><span>المرحلة الثالثة</span><small>من الزيارة الميدانية إلى اكتمال الإصلاح</small></div>
    </header>
    <nav class="hs-tabs" aria-label="أقسام HEKS">
        @can('heks3.settings')<a href="{{ route('heks3.settings') }}">إعدادات الاستبيان</a>@endcan
        @foreach(['overview'=>'نظرة عامة','cases'=>'المستفيدون والتقييمات','map'=>'الخريطة','budget'=>'الكميات والمنح','payments'=>'الدفعات','visits'=>'المتابعة الميدانية','reports'=>'التقارير','mobile'=>'استبيانات الموبايل'] as $key=>$label)
            <a href="{{ route('heks3.index', [...$filters, 'tab'=>$key, 'page'=>1]) }}" @class(['active'=>$tab===$key]) @if($tab===$key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <form class="hs-panel hs-filters" action="{{ route('heks3.index') }}" method="get">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="hs-panel-title"><strong>نطاق العرض</strong><a href="{{ route('heks3.index', ['tab'=>$tab]) }}">مسح الفلاتر</a></div>
        <div class="hs-filter-grid">
            <label class="hs-search">بحث<input class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="الاسم، الهوية، الكود أو رقم الهاتف"></label>
            <label>الحالة<select class="form-select" name="status"><option value="">كل الحالات</option>@foreach($survey::STATUSES as $key=>$label)<option value="{{ $key }}" @selected(($filters['status']??'')===$key)>{{ $label }}</option>@endforeach</select></label>
            <label>المحافظة<select class="form-select" name="governorate"><option value="">كل المحافظات</option>@foreach($survey->fields()['governorate']['choices'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['governorate']??'')===$key)>{{ $label }}</option>@endforeach</select></label>
            <label>المهندس<select class="form-select" name="engineer"><option value="">كل المهندسين المتاحين</option>@foreach($engineers as $engineer)<option value="{{ $engineer->id }}" @selected(($filters['engineer']??'')==$engineer->id)>{{ $engineer->name }}</option>@endforeach</select></label>
            <button class="btn hs-primary" type="submit">تطبيق الفلاتر</button>
        </div>
    </form>
    <section class="hs-metrics" aria-label="ملخص النتائج">
        @foreach([['الملفات ضمن النطاق',$totals['cases'],'ملف'],['بانتظار التدقيق',($counts['received']??0)+($counts['review']??0),'ملف'],['معتمد وقيد التنفيذ',($counts['approved']??0)+($counts['in_progress']??0),'ملف'],['المنح المعتمدة',number_format($totals['grants'],2),'شيكل'],['المصروف',number_format($totals['paid'],2),'شيكل']] as [$label,$value,$unit])
            <article class="hs-metric"><span>{{ $label }}</span><strong dir="ltr">{{ $value }}</strong><small>{{ $unit }}</small></article>
        @endforeach
    </section>
    <details class="hs-panel" @if(!empty($filters['survey_filters'])) open @endif><summary>فلترة حسب أسئلة الاستبيان</summary><form method="get" action="{{ route('heks3.index') }}"><input type="hidden" name="tab" value="{{ $tab }}">@foreach(['search','status','governorate','engineer'] as $base)@if(!empty($filters[$base]))<input type="hidden" name="{{ $base }}" value="{{ $filters[$base] }}">@endif @endforeach
    <div data-survey-filter-rows>@foreach($filters['survey_filters']??[[]] as $row)<div class="hs-form-grid mb-4"><label>السؤال<select class="form-select" name="survey_filters[{{ $loop->index }}][field]">@foreach(app(\App\Modules\HeksShelter\Services\SurveyDesigner::class)->filterCatalog() as $key=>$question)<option value="{{ $key }}" @selected(($row['field']??'')===$key)>{{ $question->section }} — {{ $question->label }}</option>@endforeach</select></label><label>المقارنة<select class="form-select" name="survey_filters[{{ $loop->index }}][operator]">@foreach(['eq'=>'يساوي','ne'=>'لا يساوي','contains'=>'يحتوي','gt'=>'أكبر من','gte'=>'أكبر أو يساوي','lt'=>'أقل من','lte'=>'أقل أو يساوي','empty'=>'فارغ','filled'=>'غير فارغ'] as $key=>$label)<option value="{{ $key }}" @selected(($row['operator']??'')===$key)>{{ $label }}</option>@endforeach</select></label><label>القيمة<input class="form-control" name="survey_filters[{{ $loop->index }}][value]" value="{{ $row['value']??'' }}" placeholder="قيمة الخيار / رقم / نص / تاريخ"></label></div>@endforeach</div><p>تُطبّق الشروط معاً. نعم/لا: استخدم 1 أو 0. الاختيارات تستخدم القيمة الثابتة للخيار.</p><button type="button" class="btn hs-soft" onclick="const box=this.form.querySelector('[data-survey-filter-rows]');if(box.children.length<20){const row=box.firstElementChild.cloneNode(true);row.querySelectorAll('[name]').forEach(input=>{input.name=input.name.replace(/\[\d+\]/,'['+box.children.length+']');if(input.tagName==='INPUT')input.value='';});box.appendChild(row);}">إضافة شرط</button><button class="btn hs-primary">تطبيق فلاتر الاستبيان</button><a href="{{ route('heks3.index',['tab'=>$tab]) }}" class="btn btn-light">مسح الفلاتر</a></form></details>
    @if($tab==='overview')
        <section class="hs-overview">
            <div class="hs-panel"><div class="hs-panel-title"><h3>مسار ملفات المرحلة الثالثة</h3><span>{{ $totals['cases'] }} ملف</span></div>
                @foreach($survey::STATUSES as $key=>$label)
                    <a class="hs-status-row" href="{{ route('heks3.index', [...$filters,'tab'=>'cases','status'=>$key,'page'=>1]) }}"><span>{{ $label }}</span><progress max="{{ max(1,$totals['cases']) }}" value="{{ $counts[$key]??0 }}"></progress><b>{{ $counts[$key]??0 }}</b></a>
                @endforeach
            </div>
            <div class="hs-panel hs-guidance"><span class="hs-eyebrow">من التقييم إلى الإنجاز</span><h3>كل قرار موثّق في ملف واحد</h3><ol><li>استلام التقييم والصور من تطبيق الموبايل.</li><li>مراجعة الأهلية والتدقيق الهندسي والاجتماعي.</li><li>إعداد الكميات واعتماد المنحة.</li><li>تسجيل الصرف ومتابعة الإصلاح حتى الإنجاز.</li></ol><p>الاستفادة مرة واحدة في مشروع HEKS. حجز الاستفادة يبدأ بالاعتماد النهائي، ويثبت بأول صرف.</p><small>ترحيل المرحلتين الأولى والثانية مؤجل. يجري فحص الاستفادة السابقة من السجلات القديمة.</small></div>
        </section>
    @elseif($tab==='map')
        <section class="hs-panel"><div class="hs-panel-title"><h3>مواقع الوحدات</h3><span>{{ $mapCases->count() }} موقع · حتى 1000 نتيجة</span></div><p>تظهر الملفات التي تحتوي على إحداثيات ضمن الفلاتر الحالية.</p><div id="hs-map" aria-label="خريطة وحدات HEKS"></div>
            <script id="hs-map-data" type="application/json">{!! json_encode($mapCases->map(fn($item)=>['name'=>$item->name,'code'=>$item->code,'lat'=>(float)$item->latitude,'lng'=>(float)$item->longitude,'status'=>$survey::STATUSES[$item->status],'url'=>route('heks3.show',$item)]), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
        </section>
    @elseif($tab==='reports')
        <section class="hs-panel hs-report"><span class="hs-eyebrow">التقارير والتصدير</span><h3>بيانات المرحلة الثالثة كما تحتاجها</h3><p>يشمل ملف Excel إجابات الاستبيان والمهندس وحالة الملف والمنحة والمصروف، حسب الفلاتر الحالية وصلاحية الوصول.</p>@can('heks3.export')<a class="btn hs-primary" href="{{ route('heks3.export', $filters) }}">تنزيل Excel · {{ $totals['cases'] }} ملف</a>@else<p>تصدير البيانات يحتاج صلاحية التصدير.</p>@endcan<p>يمكن تنزيل ملف PDF مفصل من داخل ملف المستفيد.</p><div class="hs-notice">درجات الأولوية غير محسوبة؛ أوزان تقييم المرحلة الثالثة لم تُعتمد بعد.</div></section>
    @elseif($tab==='mobile')
        <section class="hs-panel hs-report"><span class="hs-eyebrow">الاستلام الميداني</span><h3>استبيان HEKS — المرحلة الثالثة</h3><p>يتوفر الاستبيان ضمن «تعبئة استبيان» في تطبيق الموبايل للحسابات المخولة. حدّث قائمة النماذج بعد تسجيل الدخول.</p><div class="hs-chips"><span>9 أقسام</span><span>144 حقلًا</span><span>صور ومستندات وإحداثيات</span></div><p>إعادة إرسال نفس الاستبيان لا تنشئ ملفًا مكررًا. التعديل يعيد التدقيق الهندسي والاجتماعي، ولا يتاح بعد الاعتماد.</p><div class="hs-notice">عند وجود مانع سلامة، سجّل السبب وأوقف التقييم الميداني. الصور الداخلية تؤخذ فقط عندما تكون الوحدة آمنة.</div><p>المرفقات: حتى 5 ملفات ومجموع 30 ميغابايت لكل إرسال، وفق حدود التطبيق الحالية.</p></section>
    @endif
    @if(!in_array($tab,['map','reports','mobile'],true))
    <section class="hs-panel hs-table-panel"><div class="hs-panel-title"><div><h3>{{ ['budget'=>'الكميات والمنح','payments'=>'متابعة الصرف','visits'=>'المتابعة الميدانية'][$tab]??'ملفات المستفيدين' }}</h3><span>نتائج المرحلة الثالثة · {{ $cases->total() }} ملف</span></div>@can('heks3.export')<a class="btn btn-sm btn-light" href="{{ route('heks3.export',$filters) }}">تصدير Excel</a>@endcan</div>
        <div class="hs-table-scroll"><table class="hs-table"><thead><tr><th>المستفيد</th><th>المنطقة</th><th>المهندس / الزيارة</th><th>حالة الملف</th><th>التدقيق</th>@if(in_array($tab,['budget','payments'],true))<th>المنحة / المصروف / المتبقي</th>@endif @if($tab==='visits')<th>زيارات المتابعة</th>@endif<th></th></tr></thead><tbody>
        @forelse($cases as $case)
            <tr><td><a href="{{ route('heks3.show',$case) }}" data-case-link><strong>{{ $case->name }}</strong></a><small dir="ltr">{{ $case->identity_number }}</small><small class="hs-code" dir="ltr">{{ $case->code }}</small></td><td>{{ $survey->value('governorate',$case->governorate) }}<small>{{ $case->area ?: '—' }}</small></td><td>{{ $case->engineer?->name ?? 'غير مسند' }}<small>{{ $case->visit_date?->format('Y-m-d') }}</small></td><td><span class="hs-badge hs-{{ $case->status }}">{{ $survey::STATUSES[$case->status] }}</span></td><td><small>هندسي: {{ $case->engineering_status==='approved'?'معتمد':'بانتظار التدقيق' }}</small><small>اجتماعي: {{ $case->social_status==='approved'?'معتمد':'بانتظار التدقيق' }}</small></td>@if(in_array($tab,['budget','payments'],true))<td><strong>{{ number_format($case->grant_amount,2) }}</strong><small>المصروف {{ number_format($case->payments_sum_amount??0,2) }}</small><small>المتبقي {{ number_format($case->grant_amount-($case->payments_sum_amount??0),2) }} شيكل</small></td>@endif @if($tab==='visits')<td>{{ $case->visits_count }}</td>@endif<td><a class="btn btn-sm hs-soft" href="{{ route('heks3.show',$case) }}" data-case-link>فتح الملف</a></td></tr>
        @empty<tr><td colspan="8"><div class="hs-empty"><span>⌂</span><h3>لا توجد ملفات ضمن هذا النطاق</h3><p>ستظهر الاستبيانات هنا بعد إرسالها من تطبيق الموبايل. يمكنك أيضًا تعديل الفلاتر.</p></div></td></tr>@endforelse
        </tbody></table></div><div class="hs-pagination">{{ $cases->links() }}</div>
    </section>
    @endif
    <dialog id="hs-detail" class="hs-drawer" aria-label="ملف مستفيد HEKS"><div class="hs-drawer-bar"><strong>ملف المرحلة الثالثة</strong><button type="button" class="btn btn-sm btn-light" data-close-detail>إغلاق ✕</button></div><div id="hs-detail-body"></div></dialog>
</div>
@endsection
@section('script')
<script src="{{ asset('assets/plugins/custom/leaflet/leaflet.bundle.js') }}" defer></script>
<script src="{{ asset('assets/js/custom/heks-shelter.js') }}?v={{ filemtime(base_path('assets/js/custom/heks-shelter.js')) }}" defer></script>
@endsection
