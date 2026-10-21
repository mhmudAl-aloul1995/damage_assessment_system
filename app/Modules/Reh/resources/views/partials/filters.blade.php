<form id="reh-filters" class="reh-filter-bar" role="search" aria-label="تصفية سجلات المشروع">
    <div class="reh-filter-heading">
        <div><strong>ابحث عن الملفات التي تحتاجها</strong><span class="reh-filter-summary" data-filter-summary>مكتمل ميدانيًا</span></div>
        <button type="reset" class="reh-reset-filters">إعادة الافتراضي</button>
    </div>
    <div class="reh-filter-primary">
        @include('reh::partials.core-filter', ['filterKey' => 'q', 'filterLabel' => 'البحث عن وحدة أو مبنى'])
        @include('reh::partials.core-filter', ['filterKey' => 'survey_status', 'filterLabel' => 'حالة الاستبيان', 'filterOptions' => $survey::FIELD_STATUSES])
        @include('reh::partials.core-filter', ['filterKey' => 'status', 'filterLabel' => 'حالة التدقيق', 'filterOptions' => $survey::WORKFLOW])
        @can('reh.scores.view')@include('reh::partials.core-filter', ['filterKey' => 'priority', 'filterLabel' => 'مستوى الأولوية', 'filterOptions' => [...\App\Modules\Reh\Services\RehScoring::PRIORITIES, 'unclassified' => 'غير مصنفة · بانتظار الاستكمال']])@endcan
    </div>
    <div class="reh-filter-bottom">
        <button type="button" data-open-filters class="btn reh-advanced-trigger" aria-haspopup="dialog" aria-controls="reh-advanced-filters">فلاتر متقدمة <span data-advanced-count>0</span></button>
        <span class="reh-filter-result-count" data-result-count aria-live="polite"></span>
        @if(!($exportPage ?? false))
        @can('reh.export')<div class="reh-export-actions"><button type="button" data-export class="btn btn-light btn-sm">تصدير CSV</button><button type="button" data-export-pdf class="btn btn-light-primary btn-sm">تقرير النتائج PDF</button><a href="{{ route('reh.exports') }}" data-open-export-page class="btn btn-light btn-sm">صفحة التصدير</a></div>@endcan
        @endif
    </div>
    <div class="reh-active-filters" data-active-filters aria-label="الفلاتر المطبقة"></div>
    <dialog id="reh-advanced-filters" class="reh-advanced-dialog" aria-labelledby="reh-advanced-title">
        <header><div><span class="reh-eyebrow">تخصيص النتائج</span><h3 id="reh-advanced-title">فلاتر متقدمة</h3><p>اجمع أكثر من شرط للوصول إلى الملفات المطلوبة.</p></div><button type="button" data-cancel-filters class="btn btn-light btn-sm" aria-label="إغلاق الفلاتر">إغلاق ✕</button></header>
        <div class="reh-filter-search"><label for="reh-filter-search">ابحث داخل الفلاتر</label><input id="reh-filter-search" type="search" class="form-control" placeholder="اكتب موضوع الفلتر: كهرباء، ركام، ملكية…" autocomplete="off"><small data-filter-search-status role="status" aria-live="polite"></small></div>
        <div class="reh-advanced-body">
            <div data-advanced-message class="alert alert-danger" role="alert" hidden></div>
            <details data-filter-section class="reh-filter-section"><summary><span>01</span> الموقع والزيارة <b data-section-count></b></summary><div class="reh-advanced-grid">
                @include('reh::partials.core-filter', ['filterKey' => 'governorate', 'filterLabel' => 'المحافظة', 'filterOptions' => $survey->dictionary()['choices']['governorate']])
                @include('reh::partials.core-filter', ['filterKey' => 'neighborhood', 'filterLabel' => 'الحي', 'filterOptions' => $survey->dictionary()['choices']['neighborhood']])
                @include('reh::partials.core-filter', ['filterKey' => 'building_old_objectid', 'filterLabel' => 'معرّف حصر المبنى'])
                @include('reh::partials.core-filter', ['filterKey' => 'engineer', 'filterLabel' => 'المهندس', 'filterOptions' => $engineers->mapWithKeys(fn ($value) => [$value => $survey->value('engineer_name', $value)])->all()])
                @include('reh::partials.core-filter', ['filterKey' => 'visit', 'filterLabel' => 'تاريخ الزيارة'])
            </div></details>
            <details data-filter-section class="reh-filter-section"><summary><span>02</span> التقييم والتدقيق <b data-section-count></b></summary><div class="reh-advanced-grid">
                @can('reh.scores.view')
                    @include('reh::partials.core-filter', ['filterKey' => 'evaluation', 'filterLabel' => 'اكتمال التقييم', 'filterOptions' => ['complete' => 'درجة نهائية محسوبة', 'incomplete' => 'بيانات ناقصة · الدرجة معلقة']])
                @endcan
                @include('reh::partials.core-filter', ['filterKey' => 'decision', 'filterLabel' => 'قرار الوحدة', 'filterOptions' => ['pending' => 'بانتظار القرار', 'selected' => 'مقبول مبدئيًا', 'excluded' => 'مستبعد']])
                @include('reh::partials.core-filter', ['filterKey' => 'edits', 'filterLabel' => 'سجل تصحيح الحقول', 'filterOptions' => ['yes' => 'توجد تعديلات محفوظة', 'no' => 'دون تعديلات محفوظة']])
            </div>
            @foreach(['score' => ['الدرجة النهائية', 100, 'reh.scores.view'], 'engineering' => ['الدرجة الهندسية', 40, 'reh.scores.view'], 'social' => ['الدرجة الاجتماعية', 60, 'reh.sensitive']] as $prefix => [$label, $maximum, $permission])
                @can($permission)@include('reh::partials.core-filter', ['filterKey' => $prefix, 'filterLabel' => $label.' / '.$maximum, 'filterMaximum' => $maximum])@endcan
            @endforeach
            <p class="reh-filter-hint">نطاق الدرجات يشمل الدرجات المكتملة فقط؛ القيمة الناقصة لا تُحسب صفرًا.</p>
            </details>
            @can('reh.sensitive')<details data-filter-section class="reh-filter-section"><summary><span>03</span> الأسرة والمؤشرات الاجتماعية <b data-section-count></b></summary>
                @include('reh::partials.core-filter', ['filterKey' => 'household', 'filterLabel' => 'أفراد أسرة المالك · شاملًا المالك', 'filterMaximum' => 1000000])
                <div class="reh-advanced-grid">@foreach(['hosting' => 'أسر أخرى في الوحدة', 'owner_disability' => 'إعاقة المالك', 'family_chronic' => 'أمراض مزمنة بأسرة المالك'] as $field => $label)@include('reh::partials.core-filter', ['filterKey' => $field, 'filterLabel' => $label, 'filterOptions' => ['yes' => 'نعم', 'no' => 'لا', '__missing' => 'غير مسجل']])@endforeach</div>
                <p class="reh-filter-hint">تعتمد على الإجابات المسجلة؛ البيانات غير المسجلة لا تعني «لا».</p>
            </details>@endcan
            @include('reh::partials.survey-filters')
        </div>
        <footer><button type="button" data-clear-advanced class="reh-reset-filters">مسح الفلاتر المتقدمة</button><div><button type="button" data-cancel-filters class="btn btn-light">إلغاء</button><button type="submit" class="btn btn-primary" data-apply-filters>تطبيق الفلاتر</button></div></footer>
    </dialog>
</form>
