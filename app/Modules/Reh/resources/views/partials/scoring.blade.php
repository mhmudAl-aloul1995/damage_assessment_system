@php
    $canSeeSocial = auth()->user()->can('reh.sensitive');
    $engineering = $score['engineering'];
    $scoreHistory = $unit->reviews->filter(fn ($review) => isset($review->changes['scoring']));
@endphp
<div class="reh-scoring" data-scoring>
    <div class="reh-section-heading"><div><h3>التقييم والأولوية</h3><p>كل نقطة مرتبطة بإجابة أو كمية محفوظة · إصدار {{ \App\Modules\Reh\Services\RehScoring::VERSION }}</p></div><span class="badge badge-light-{{ ($canSeeSocial ? $score['total'] !== null : $engineering['total'] !== null) ? 'success' : 'warning' }}">{{ ($canSeeSocial ? $score['total'] !== null : $engineering['total'] !== null) ? 'مكتمل الاحتساب' : 'يلزم استكمال البيانات' }}</span></div>
    <div class="reh-score-metrics" aria-label="ملخص السكورينج">
        <article class="reh-score-metric reh-score-total"><span>الدرجة النهائية</span><strong data-final-score="{{ $canSeeSocial ? $score['total'] : '' }}">{{ $canSeeSocial ? ($score['total'] ?? '—') : '—' }} <small>/ 100</small></strong><span class="badge badge-light-{{ $canSeeSocial && $score['total'] !== null ? 'primary' : 'warning' }}">{{ $canSeeSocial ? (\App\Modules\Reh\Services\RehScoring::PRIORITIES[$score['priority']] ?? 'الأولوية غير مصنفة') : 'تحتاج صلاحية البيانات الحساسة' }}</span><p>السقف الممكن وفق المصفوفة 97 نقطة. لا تُستبدل الإجابة الناقصة بصفر.</p></article>
        <article class="reh-score-metric"><span>الاجتماعي</span><strong>{{ $canSeeSocial ? ($score['social_total'] ?? '—') : '—' }} <small>/ 60 اسميًا</small></strong><p>{{ $canSeeSocial ? ($score['social_total'] !== null ? 'تُعتمد أعلى نتيجة اجتماعية مكتملة بين أسرة المالك ومجموعة الأسر الأخرى.' : 'يلزم اكتمال المؤشرات المنطبقة قبل اعتماد المقارنة.') : 'تفاصيل الأسرة تحتاج صلاحية البيانات الحساسة.' }}</p>@if($canSeeSocial)<button type="button" class="reh-score-link" data-score-jump="social">تفاصيل التقييم الاجتماعي ←</button>@endif</article>
        <article class="reh-score-metric"><span>{{ $engineering['total'] === null ? 'نقاط هندسية محسومة' : 'الدرجة الهندسية' }}</span><strong data-engineering-subtotal="{{ $engineering['subtotal'] }}">{{ $engineering['subtotal'] }} <small>/ 40</small></strong><progress value="{{ $engineering['subtotal'] }}" max="40" aria-label="النقاط الهندسية المحسومة"></progress><p>{{ $engineering['calculated_count'] }} من 12 فئة محسوبة · {{ $engineering['pending_count'] }} معلقة</p><button type="button" class="reh-score-link" data-score-jump="engineering">البنود وسبب الاحتساب ←</button></article>
    </div>
    <div class="reh-score-notice"><div><strong>{{ $canSeeSocial && $score['total'] !== null ? 'الدرجة محسوبة من البيانات المكتملة' : 'النقاط الجزئية ليست درجة نهائية' }}</strong><p>كل إجابة مؤثرة على السكور إلزامية عند انطباقها. يبقى اعتماد المستفيد قرارًا موثقًا منفصلًا.</p></div><button type="button" class="btn btn-sm btn-light-warning" data-score-jump="rules">قواعد الحساب والاستكمال</button></div>
    @if($unit->source_missing || $unit->workflow_status === 'source_changed')<div class="alert alert-warning">تغيّرت بيانات المصدر أو فُقد السجل؛ راجع البيانات الحالية قبل اعتماد التدقيق.</div>@endif
    <nav class="reh-score-tabs" role="tablist" aria-label="تفاصيل السكورينج">
        @foreach(($canSeeSocial ? ['social' => 'الاجتماعي'] : []) + ['engineering' => 'الهندسي', 'rules' => 'القواعد والاستكمال', 'history' => 'أثر التغييرات'] as $key => $label)
            <button type="button" id="reh-score-tab-{{ $key }}" role="tab" data-score-tab="{{ $key }}" aria-controls="reh-score-panel-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
    </nav>
    @if($canSeeSocial)
    <section id="reh-score-panel-social" role="tabpanel" aria-labelledby="reh-score-tab-social" data-score-panel="social">
        <div class="reh-section-heading"><h4>أسرة المالك ومجموعة الأسر الأخرى</h4><small class="text-muted">{{ $score['selected_family'] !== null ? 'المعتمد: '.($score['selected_family'] === 'owner' ? 'أسرة المالك' : 'مجموعة الأسر المقيمة / المستضافة') : 'المقارنة بانتظار استكمال البيانات' }}</small></div>
        <div class="reh-score-families">
        @foreach(['owner' => 'أسرة المالك', 'other' => 'مجموعة الأسر المقيمة / المستضافة'] as $family => $label)
            @if($score[$family] !== null)<button type="button" data-score-family="{{ $family }}" aria-pressed="{{ $family === 'owner' ? 'true' : 'false' }}"><span>{{ $label }}<small>{{ $score[$family]['calculated_count'] }} من 23 مؤشرًا محسوبًا</small></span><strong>{{ $score[$family]['subtotal'] }}<small>نقطة محسومة</small></strong></button>@endif
        @endforeach
        </div>
        @foreach(['owner' => 'أسرة المالك', 'other' => 'مجموعة الأسر المقيمة / المستضافة'] as $family => $label)
            @if($score[$family] !== null)<div data-score-family-panel="{{ $family }}" @if($family !== 'owner') hidden @endif>
                @if($family === 'other')<p class="reh-score-explainer">تُحسب الأعداد المجمّعة لكل الأسر التي يمثلها المقيم كسجل واحد. لا تُقسم الأعداد على عدد الأسر، ولا تتضاعف النقاط بعددها.</p>@endif
                @include('reh::partials.scoring-rows', ['rows' => $score[$family]['rows'], 'engineeringRows' => false])
            </div>@endif
        @endforeach
        @if($score['other'] === null)<p class="text-muted mt-4">لا توجد أسرة إضافية وفق بيانات الاستضافة الحالية.</p>@endif
    </section>
    @endif
    <section id="reh-score-panel-engineering" role="tabpanel" aria-labelledby="reh-score-tab-engineering" data-score-panel="engineering" @if($canSeeSocial) hidden @endif>
        <div class="reh-section-heading"><div><h4>احتياجات التأهيل</h4><p>الكمية المراجعة مقدّمة على الميدانية عند وجودها؛ التغييرات غير المحفوظة لا تدخل في الحساب.</p></div><span class="badge badge-light-{{ $unit->engineering_status === 'approved' ? 'success' : 'warning' }}">{{ $unit->engineering_status === 'approved' ? 'مدقق هندسيًا' : 'بانتظار التدقيق الهندسي' }}</span></div>
        @include('reh::partials.scoring-rows', ['rows' => $engineering['rows'], 'engineeringRows' => true])
        <button type="button" class="btn btn-light-primary mt-5" data-score-quantities>مراجعة كميات الأعمال</button>
    </section>
    <section id="reh-score-panel-rules" role="tabpanel" aria-labelledby="reh-score-tab-rules" data-score-panel="rules" hidden>
        <h4>قواعد الاحتساب</h4>
        @foreach(\App\Modules\Reh\Services\RehScoring::RULES as $rule => $description)<div class="reh-score-rule"><span class="badge badge-light-primary">قاعدة الحساب</span><p>{{ $description }}</p></div>@endforeach
        <h4 class="mt-7">نواقص تحتاج مراجعة البيانات</h4>
        @php($parts = ['الهندسي' => $engineering] + ($canSeeSocial ? ['أسرة المالك' => $score['owner'], 'أسرة المقيم / المستضاف' => $score['other']] : []))
        @php($missingRows = collect($parts)->filter()->flatMap(fn ($part, $label) => collect($part['rows'])->whereIn('status', ['missing', 'invalid'])->map(fn ($row) => ['part' => $label] + $row)))
        @forelse($missingRows as $row)<div class="reh-score-rule"><strong>{{ $row['part'] }} · {{ $row['label'] }}</strong><p>{{ \App\Modules\Reh\Services\RehScoring::STATUSES[$row['status']] }}</p><button type="button" class="reh-score-link" data-score-target="{{ $row['id'] }}" data-score-jump="{{ $row['part'] === 'الهندسي' ? 'engineering' : 'social' }}" @if($row['part'] !== 'الهندسي') data-score-select-family="{{ $row['part'] === 'أسرة المالك' ? 'owner' : 'other' }}" @endif>عرض المؤشر ومصدره ←</button></div>@empty<p class="text-muted">كل المؤشرات المتاحة ضمن صلاحياتك مكتملة.</p>@endforelse
    </section>
    <section id="reh-score-panel-history" role="tabpanel" aria-labelledby="reh-score-tab-history" data-score-panel="history" hidden>
        <h4>أثر الإجراءات على النقاط</h4><p class="text-muted">تُحفظ لقطة الحساب وقت المراجعة أو تغيّر بيانات الوحدة. الإجراءات السابقة لإضافة السكورينج تبقى في سجل الإجراءات.</p>
        @forelse($scoreHistory as $review)
            @php($beforeScore = $review->changes['scoring']['before'])
            @php($afterScore = $review->changes['scoring']['after'])
            <article class="reh-history"><strong>{{ $survey::ACTIONS[$review->action] ?? $review->action }}</strong><small>{{ $review->user?->name ?? 'مزامنة النظام' }} · {{ $review->created_at->format('Y-m-d H:i') }}</small>
                <dl class="reh-score-diff"><div><dt>الهندسي · نقاط محسومة</dt><dd><span>قبل {{ $beforeScore['engineering']['subtotal'] }}</span><span>بعد {{ $afterScore['engineering']['subtotal'] }}</span></dd></div><div><dt>فئات هندسية معلقة</dt><dd><span>قبل {{ $beforeScore['engineering']['pending_count'] }}</span><span>بعد {{ $afterScore['engineering']['pending_count'] }}</span></dd></div>
                @if($canSeeSocial)@foreach(['owner' => 'أسرة المالك', 'other' => 'مجموعة الأسر المقيمة / المستضافة'] as $family => $label)@if(($beforeScore[$family] ?? null) || ($afterScore[$family] ?? null))<div><dt>{{ $label }} · نقاط محسومة</dt><dd><span>قبل {{ $beforeScore[$family]['subtotal'] ?? 'غير منطبق' }}</span><span>بعد {{ $afterScore[$family]['subtotal'] ?? 'غير منطبق' }}</span></dd></div>@endif@endforeach<div><dt>الدرجة النهائية</dt><dd><span>قبل {{ $beforeScore['total'] ?? 'غير مكتمل' }}</span><span>بعد {{ $afterScore['total'] ?? 'غير مكتمل' }}</span></dd></div>@endif</dl><small>إصدار الحساب: {{ $afterScore['version'] }}@if($canSeeSocial) · {{ \App\Modules\Reh\Services\RehScoring::PRIORITIES[$afterScore['priority'] ?? ''] ?? 'الأولوية غير مصنفة' }}@endif</small>
            </article>
        @empty<div class="reh-empty">لا توجد تغييرات مسجلة بعد إضافة السكورينج.</div>@endforelse
    </section>
</div>
