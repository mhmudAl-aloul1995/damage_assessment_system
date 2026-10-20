<form id="reh-filters" class="reh-filter-bar" role="search" aria-label="تصفية سجلات المشروع">
    <div class="reh-filter-heading">
        <div><strong>ابحث عن الملفات التي تحتاجها</strong><span class="reh-filter-summary" data-filter-summary>مكتمل ميدانيًا</span></div>
        <button type="reset" class="reh-reset-filters">إعادة الافتراضي</button>
    </div>
    <div class="reh-filter-primary">
        <div class="reh-search"><label for="reh-q">البحث عن وحدة أو مبنى</label><input id="reh-q" name="q" class="form-control" placeholder="اكتب معرّف الوحدة أو المبنى، الاسم أو الكود…" autocomplete="off"></div>
        <div><label for="reh-survey-status">حالة الاستبيان</label><select id="reh-survey-status" name="survey_status" class="form-select" data-dropdown-parent="#reh-filters">@foreach($survey::FIELD_STATUSES as $value => $label)<option value="{{ $value }}" @selected(($filters['survey_status'] ?? 'completed') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="reh-status">حالة التدقيق</label><select id="reh-status" name="status" class="form-select" data-dropdown-parent="#reh-filters"><option value="">كل حالات التدقيق</option>@foreach($survey::WORKFLOW as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
        @can('reh.scores.view')<div><label for="reh-priority">مستوى الأولوية</label><select id="reh-priority" name="priority" class="form-select" data-dropdown-parent="#reh-filters"><option value="">كل مستويات الأولوية</option>@foreach(\App\Modules\Reh\Services\RehScoring::PRIORITIES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach<option value="unclassified">غير مصنفة · بانتظار الاستكمال</option></select></div>@endcan
    </div>
    <div class="reh-filter-bottom">
        <button type="button" data-open-filters class="btn reh-advanced-trigger" aria-haspopup="dialog" aria-controls="reh-advanced-filters">فلاتر متقدمة <span data-advanced-count>0</span></button>
        <span class="reh-filter-result-count" data-result-count aria-live="polite"></span>
        @can('reh.export')<div class="reh-export-actions"><button type="button" data-export class="btn btn-light btn-sm">تصدير CSV</button><button type="button" data-export-pdf class="btn btn-light-primary btn-sm">تقرير النتائج PDF</button></div>@endcan
    </div>
    <div class="reh-active-filters" data-active-filters aria-label="الفلاتر المطبقة"></div>
    <dialog id="reh-advanced-filters" class="reh-advanced-dialog" aria-labelledby="reh-advanced-title">
        <header><div><span class="reh-eyebrow">تخصيص النتائج</span><h3 id="reh-advanced-title">فلاتر متقدمة</h3><p>اجمع أكثر من شرط للوصول إلى الملفات المطلوبة.</p></div><button type="button" data-cancel-filters class="btn btn-light btn-sm" aria-label="إغلاق الفلاتر">إغلاق ✕</button></header>
        <div class="reh-filter-search"><label for="reh-filter-search">ابحث داخل الفلاتر</label><input id="reh-filter-search" type="search" class="form-control" placeholder="اكتب موضوع الفلتر: كهرباء، ركام، ملكية…" autocomplete="off" aria-describedby="reh-filter-search-help"><span id="reh-filter-search-help" class="reh-filter-search-help">البحث داخل أسماء الفلاتر وخياراتها؛ استخدم كلمة محددة بدل «هل».</span><small data-filter-search-status role="status" aria-live="polite"></small></div>
        <div class="reh-advanced-body">
            <div data-advanced-message class="alert alert-danger" role="alert" hidden></div>
            <details data-filter-section class="reh-filter-section"><summary><span>01</span> الموقع والزيارة <b data-section-count></b></summary><div class="reh-advanced-grid">
                <div><label for="reh-governorate">المحافظة</label><select id="reh-governorate" name="governorate" class="form-select" data-dropdown-parent="#reh-advanced-filters"><option value="">كل المحافظات</option>@foreach($survey->dictionary()['choices']['governorate'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                <div><label for="reh-neighborhood">الحي</label><select id="reh-neighborhood" name="neighborhood" class="form-select" data-dropdown-parent="#reh-advanced-filters"><option value="">كل الأحياء</option>@foreach($survey->dictionary()['choices']['neighborhood'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                <div><label for="reh-building-reference">معرّف المبنى</label><input id="reh-building-reference" name="building_old_objectid" maxlength="100" class="form-control" placeholder="مثال: 2328"></div>
                <div><label for="reh-engineer">المهندس</label><select id="reh-engineer" name="engineer" class="form-select" data-dropdown-parent="#reh-advanced-filters"><option value="">كل المهندسين</option>@foreach($engineers as $value)<option value="{{ $value }}">{{ $survey->value('engineer_name', $value) }}</option>@endforeach</select></div>
                <div><label for="reh-visit-from">تاريخ الزيارة من</label><input id="reh-visit-from" name="visit_from" type="date" data-global-date-range="true" class="form-control"></div>
                <div><label for="reh-visit-to">تاريخ الزيارة إلى</label><input id="reh-visit-to" name="visit_to" type="date" data-global-date-range="true" class="form-control"></div>
            </div></details>
            <details data-filter-section class="reh-filter-section"><summary><span>02</span> التقييم والتدقيق <b data-section-count></b></summary><div class="reh-advanced-grid">
                @can('reh.scores.view')
                    <div><label for="reh-evaluation">اكتمال التقييم</label><select id="reh-evaluation" name="evaluation" class="form-select" data-dropdown-parent="#reh-advanced-filters"><option value="">كل الملفات</option><option value="complete">درجة نهائية محسوبة</option><option value="incomplete">بيانات ناقصة · الدرجة معلقة</option></select></div>
                @endcan
                <div><label for="reh-decision">قرار الوحدة</label><select id="reh-decision" name="decision" class="form-select" data-dropdown-parent="#reh-advanced-filters"><option value="">كل القرارات</option><option value="pending">بانتظار القرار</option><option value="selected">مقبول مبدئيًا</option><option value="excluded">مستبعد</option></select></div>
                <div><label for="reh-edits">سجل تصحيح الحقول</label><select id="reh-edits" name="edits" class="form-select" data-dropdown-parent="#reh-advanced-filters"><option value="">كل الملفات</option><option value="yes">توجد تعديلات محفوظة</option><option value="no">دون تعديلات محفوظة</option></select><small>على الوحدة أو مبناها، ضمن صلاحياتك.</small></div>
            </div>
            @foreach(['score' => ['الدرجة النهائية', 100, 'reh.scores.view'], 'engineering' => ['الدرجة الهندسية', 40, 'reh.scores.view'], 'social' => ['الدرجة الاجتماعية', 60, 'reh.sensitive']] as $prefix => [$label, $maximum, $permission])
                @can($permission)<div class="reh-filter-range"><strong>{{ $label }} <small>/ {{ $maximum }}</small></strong><div><label for="reh-{{ $prefix }}-min">من</label><input id="reh-{{ $prefix }}-min" name="{{ $prefix }}_min" type="number" min="0" max="{{ $maximum }}" class="form-control" placeholder="0"></div><span aria-hidden="true">—</span><div><label for="reh-{{ $prefix }}-max">إلى</label><input id="reh-{{ $prefix }}-max" name="{{ $prefix }}_max" type="number" min="0" max="{{ $maximum }}" class="form-control" placeholder="{{ $maximum }}"></div></div>@endcan
            @endforeach
            <p class="reh-filter-hint">نطاق الدرجات يشمل الدرجات المكتملة فقط؛ القيمة الناقصة لا تُحسب صفرًا.</p>
            </details>
            @can('reh.sensitive')<details data-filter-section class="reh-filter-section"><summary><span>03</span> الأسرة والمؤشرات الاجتماعية <b data-section-count></b></summary>
                <div class="reh-filter-range"><strong>أفراد أسرة المالك<small> شاملًا المالك</small></strong><div><label for="reh-household-min">من</label><input id="reh-household-min" name="household_min" type="number" min="0" max="1000000" class="form-control"></div><span aria-hidden="true">—</span><div><label for="reh-household-max">إلى</label><input id="reh-household-max" name="household_max" type="number" min="0" max="1000000" class="form-control"></div></div>
                <div class="reh-advanced-grid">@foreach(['hosting' => 'أسر أخرى في الوحدة', 'owner_disability' => 'إعاقة المالك', 'family_chronic' => 'أمراض مزمنة بأسرة المالك'] as $field => $label)<div><label for="reh-{{ $field }}">{{ $label }}</label><select id="reh-{{ $field }}" name="{{ $field }}" class="form-select" data-dropdown-parent="#reh-advanced-filters"><option value="">الكل</option><option value="yes">نعم</option><option value="no">لا</option><option value="__missing">غير مسجل</option></select></div>@endforeach</div>
                <p class="reh-filter-hint">تعتمد على الإجابات المسجلة؛ البيانات غير المسجلة لا تعني «لا».</p>
            </details>@endcan
            @include('reh::partials.survey-filters')
        </div>
        <footer><button type="button" data-clear-advanced class="reh-reset-filters">مسح الفلاتر المتقدمة</button><div><button type="button" data-cancel-filters class="btn btn-light">إلغاء</button><button type="submit" class="btn btn-primary" data-apply-filters>تطبيق الفلاتر</button></div></footer>
    </dialog>
</form>
