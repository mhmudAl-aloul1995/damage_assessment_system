<div class="reh-detail-content" data-unit-id="{{ $unit->id }}" data-unit-object-id="{{ $unit->old_objectid }}">
<header class="reh-file-header"><div class="reh-eyebrow">ملف الوحدة · المعرّف {{ $unit->old_objectid }}</div><h2>وحدة {{ $unit->old_objectid }}</h2><p>{{ $unit->owner_name ?: 'اسم المالك غير مسجل' }} · مبنى {{ $unit->building->old_objectid }}@if($unit->building->name) · {{ $unit->building->name }}@endif@if($unit->code) · الكود: {{ $unit->code }}@endif · طابق {{ $unit->floor_number ?? '—' }} · شقة {{ $unit->apartment_number ?? '—' }}</p><div class="d-flex gap-2 flex-wrap"><span class="badge badge-light-primary">{{ $survey::WORKFLOW[$unit->workflow_status] }}</span><span class="badge badge-light">التسعير: بانتظار الأسعار</span><span class="badge badge-light">{{ ['pending' => 'بانتظار القرار', 'selected' => 'مقبول مبدئيًا', 'excluded' => 'مستبعد'][$unit->decision] }}</span></div></header>
@can('reh.export')<div class="reh-file-export"><button type="button" data-pdf-download="{{ route('reh.units.pdf', $unit) }}" class="btn btn-light-primary btn-sm">تصدير ملف الوحدة PDF</button><small>البيانات الميدانية والتقييم والكميات ضمن صلاحياتك</small></div>@endcan
@include('reh::partials.audit-tools')
<nav class="reh-detail-tabs" aria-label="أقسام ملف الوحدة">@foreach(['summary' => 'الملخص والمراجعة', 'scoring' => 'التقييم والأولوية', 'data' => 'البيانات الميدانية', 'quantities' => 'كميات الأعمال', 'photos' => 'الصور والوثائق', 'history' => 'سجل الإجراءات'] as $key => $label)<button type="button" data-detail-tab="{{ $key }}" class="{{ $loop->first ? 'active' : '' }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>@endforeach</nav>
<section data-detail-panel="summary">
    <dl class="reh-fields"><div><dt>التدقيق الهندسي</dt><dd>{{ $unit->engineering_status === 'approved' ? 'مكتمل' : 'بانتظار المراجعة' }}</dd></div><div><dt>التدقيق الاجتماعي</dt><dd>{{ $unit->social_status === 'approved' ? 'مكتمل' : 'بانتظار المراجعة' }}</dd></div><div><dt>آلية التدقيق</dt><dd>مباشر حسب الصلاحية · دون إسناد</dd></div><div><dt>آخر استقبال من المصدر</dt><dd>{{ $unit->synced_at?->format('Y-m-d H:i') ?? '—' }}</dd></div></dl>
    @if($issues)<div class="reh-issues"><strong>ما الذي يمنع القبول المبدئي؟</strong><ul>@foreach($issues as $issue)<li>{{ $issue }}</li>@endforeach</ul><p>يلزم معالجة هذه التنبيهات قبل القبول المبدئي. لا تعني استبعاد الوحدة تلقائيًا. راجع البيانات الميدانية والصور، واستخدم «تصحيح» لحفظ التعديل في قاعدة البيانات وArcGIS.</p></div>@else<div class="alert alert-success">لا توجد تنبيهات ضمن الفحوص المتاحة. يبقى توثيق التدقيق والقرار مطلوبًا.</div>@endif
    @php
        $actions = collect(['engineering_approve' => 'reh.review.engineering', 'social_approve' => 'reh.review.social', 'return' => 'reh.review', 'second_assessment' => 'reh.review.engineering', 'select' => 'reh.decide', 'exclude' => 'reh.decide', 'reopen' => 'reh.decide'])->filter(fn ($permission, $action) => auth()->user()->can($permission) && ($action !== 'social_approve' || auth()->user()->can('reh.sensitive')) && ($unit->decision === 'pending' ? $action !== 'reopen' : $action === 'reopen'));
    @endphp
    @if($auditReady && $actions->isNotEmpty())
    @php
        $actionDescriptions = [
            'engineering_approve' => 'توثيق انتهاء مراجعة الكميات وحالة الوحدة والصور. لا يعتمد التكلفة.',
            'social_approve' => 'توثيق انتهاء مراجعة بيانات الأسرة والإجابات المؤثرة في التقييم الاجتماعي.',
            'return' => 'طلب استكمال أو تصحيح البيانات. يعيد التدقيقين إلى انتظار المراجعة.',
            'second_assessment' => 'طلب زيارة أو تقييم هندسي إضافي. يعيد التدقيقين إلى انتظار المراجعة.',
            'select' => 'قبول مبدئي بعد التدقيقين ومعالجة التنبيهات. التكلفة تبقى بانتظار الأسعار.',
            'exclude' => 'توثيق قرار استبعاد مع كتابة سببه. ليس مجرد طلب استكمال.',
            'reopen' => 'إلغاء القرار السابق وإعادة التدقيقين إلى انتظار المراجعة، مع حفظ السجل السابق.',
        ];
        $blockedActions = [];
        foreach (['engineering_approve' => 'engineering_status', 'social_approve' => 'social_status'] as $action => $status) {
            if ($unit->source_missing || $unit->building->source_missing) {
                $blockedActions[$action] = 'تحقق من السجل المفقود في المصدر أولًا.';
            } elseif ($unit->$status === 'approved') {
                $blockedActions[$action] = 'تم اعتماد هذا التدقيق بالفعل.';
            }
        }
        $acceptanceRequirements = [];
        if ($unit->engineering_status !== 'approved') {
            $acceptanceRequirements[] = 'اعتماد التدقيق الهندسي';
        }
        if ($unit->social_status !== 'approved') {
            $acceptanceRequirements[] = 'اعتماد التدقيق الاجتماعي';
        }
        if ($issues) {
            $acceptanceRequirements[] = 'معالجة التنبيهات أعلاه';
        }
        if ($acceptanceRequirements) {
            $blockedActions['select'] = 'متبقي: '.implode('، ', $acceptanceRequirements).'.';
        }
    @endphp
    <form data-review-form action="{{ route('reh.units.review', $unit) }}" class="reh-review-form" data-action-form>
        @csrf<input type="hidden" name="version" value="{{ $unit->version }}">
        <div class="reh-review-heading"><h4>ماذا تريد أن تفعل بهذا الملف؟</h4><p>اختر إجراءً واحدًا، ثم اكتب ملاحظتك واحفظ. لن يتغير الملف بمجرد اختيار الإجراء.</p></div>
        @foreach(['مراجعة الملف' => ['engineering_approve', 'social_approve'], 'طلب استكمال أو تقييم إضافي' => ['return', 'second_assessment'], 'قرار الوحدة' => ['select', 'exclude', 'reopen']] as $group => $groupActions)
            @if($actions->keys()->intersect($groupActions)->isNotEmpty())
            <fieldset class="reh-action-group"><legend>{{ $group }}</legend><div class="reh-action-grid">
                @foreach($groupActions as $action)
                    @if($actions->has($action))
                    <label class="reh-action-option {{ isset($blockedActions[$action]) ? 'is-unavailable' : '' }} {{ $action === 'exclude' ? 'is-destructive' : '' }}">
                        <input type="radio" name="action" value="{{ $action }}" required @disabled(isset($blockedActions[$action])) aria-describedby="reh-action-{{ $action }}-description" data-action-label="{{ $survey::ACTIONS[$action] }}">
                        <span class="reh-action-copy"><strong>{{ $survey::ACTIONS[$action] }}</strong><span id="reh-action-{{ $action }}-description">{{ $actionDescriptions[$action] }}</span>@if(isset($blockedActions[$action]))<small class="reh-action-reason">{{ $blockedActions[$action] }}</small>@endif</span>
                    </label>
                    @endif
                @endforeach
            </div></fieldset>
            @endif
        @endforeach
        <div class="reh-review-note"><label for="reh-review-note">ملاحظتك وسبب الإجراء <span class="text-muted">(مطلوب)</span></label><textarea id="reh-review-note" name="note" rows="3" class="form-control" required minlength="3" maxlength="2000" placeholder="مثال: تمت مراجعة الصور والكميات ومطابقتها مع الحصر الميداني."></textarea><p class="text-muted" id="reh-review-note-help">تظهر الملاحظة لفريق التدقيق. تجنب كتابة تفاصيل صحية أو معلومات حماية حساسة.</p></div>
        <div class="reh-review-footer"><span data-action-selection aria-live="polite">لم تختر إجراءً بعد.</span><button class="btn btn-primary" type="submit" data-action-submit disabled>اختر إجراءً للمتابعة</button></div>
    </form>
    @endif
</section>
<section data-detail-panel="scoring" hidden>
    @include('reh::partials.scoring')
</section>
<section id="reh-detail-panel-data" data-detail-panel="data" hidden>
    @include('reh::partials.identifiers', ['referenceBuilding' => $unit->building, 'referenceUnit' => $unit])
    @cannot('reh.sensitive')<div class="alert alert-info">بيانات الهوية والأسرة والصحة والحماية تحتاج صلاحية الاطلاع على البيانات الحساسة.</div>@endcannot
    @include('reh::partials.fields', ['sections' => $survey->sections($unit->source, 'unit', auth()->user()->can('reh.sensitive'))])
</section>
<section id="reh-detail-panel-quantities" data-detail-panel="quantities" hidden>
    <div class="reh-callout"><div><strong>كميات الأعمال · تدقيق المصدر</strong><p>كل تصحيح يُحفظ في قاعدة البيانات وArcGIS مع سببه. الأسعار غير متوفرة حاليًا.</p></div></div>
    <label class="d-flex gap-2 mb-5"><input type="checkbox" data-show-all-quantities> إظهار جميع البنود بما فيها الفارغة والصفرية</label>
    <div class="table-responsive"><table class="table reh-table"><thead><tr><th>البند</th><th>الوحدة</th><th>القيمة الحالية</th><th>مراجعة محلية سابقة</th><th>التدقيق</th></tr></thead><tbody>
    @foreach($quantities as $item)<tr data-quantity-row data-quantity-field="{{ $item['field'] }}" data-has-quantity="{{ (float) ($item['source'] ?? 0) > 0 || $item['reviewed'] !== null ? '1' : '0' }}" @if(!((float) ($item['source'] ?? 0) > 0 || $item['reviewed'] !== null)) hidden @endif><td>{{ $item['label'] }}@if(mb_strlen($item['unit']) > 80)<details class="reh-quantity-spec"><summary>المواصفات الفنية</summary><p>{{ $item['unit'] }}</p></details>@endif</td><td>{{ mb_strlen($item['unit']) > 80 ? \Illuminate\Support\Str::before($item['unit'], ' ') : $item['unit'] }}</td><td>{{ $item['source'] ?? 'غير مسجل' }}</td><td>{{ $item['reviewed'] ?? '—' }}@if($item['reviewed'] !== null)<small>يُزال الاستثناء السابق عند حفظ التصحيح.</small>@endif</td><td>@include('reh::partials.audit-button', ['auditFieldName' => $item['field']])</td></tr>@endforeach
    </tbody></table></div>
    <div class="reh-field-group mt-6"><h4>الأعمال الإضافية — للتوثيق فقط</h4>@for($i = 1; $i <= 3; $i++)@if(!empty($unit->source['additional_item_description_'.$i]))<p>{{ $unit->source['additional_item_description_'.$i] }} · الكمية: {{ $unit->source['additional_item_quantity_'.$i] ?? 'غير مسجلة' }}</p>@endif@endfor<p class="text-muted">لا تُحتسب تكلفة البنود الإضافية قبل اعتماد آلية تسعيرها.</p></div>
</section>
<section data-detail-panel="photos" hidden><p>تُحمّل المرفقات عند الطلب، وتُعرض بحسب صلاحية المستخدم.</p><button type="button" class="btn btn-light-primary" data-load-attachments="{{ route('reh.attachments', ['unit', $unit->id]) }}">تحميل صور ووثائق الوحدة</button><div class="reh-attachments" aria-live="polite"></div><hr><button type="button" class="btn btn-light" data-load-attachments="{{ route('reh.attachments', ['building', $unit->building_id]) }}">تحميل صور المبنى</button><div class="reh-attachments" aria-live="polite"></div></section>
<section data-detail-panel="history" hidden>@include('reh::partials.audit-history')<h4>سجل الإجراءات — أحدث 100 إجراء</h4>@forelse($unit->reviews as $review)<article class="reh-history"><strong>{{ $survey::ACTIONS[$review->action] ?? $review->action }}</strong><small>{{ $review->user?->name ?? 'مزامنة النظام' }} · {{ $review->created_at->format('Y-m-d H:i') }}</small><p>{{ $review->note }}</p>@if($review->action === 'quantities')@foreach(($review->changes['after']['quantity_overrides'] ?? []) as $field => $quantity)<small>{{ $survey->label($field) }}: {{ $quantity }}</small>@endforeach@endif</article>@empty<div class="reh-empty">لم تُسجل إجراءات على هذه الوحدة بعد.</div>@endforelse</section>
</div>
