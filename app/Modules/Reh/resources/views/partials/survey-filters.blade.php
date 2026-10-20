@php($surveyFilters = app(\App\Modules\Reh\Services\RehSurveyFilters::class))
@foreach($surveyFilters::GROUPS as $group => [$title, $scope, $sensitive])
    @if(!$sensitive || auth()->user()->can('reh.sensitive'))
        <details data-filter-section class="reh-filter-section">
            <summary><span>{{ str_pad((string) ($loop->iteration + 3), 2, '0', STR_PAD_LEFT) }}</span> {{ $title }} <b data-section-count></b></summary>
            <div class="reh-advanced-grid">
            @foreach($surveyFilters->definitions() as $key => $definition)
                @continue($definition['group'] !== $group)
                @php($dependency = $surveyFilters::DEPENDENCIES[$key] ?? null)
                <div data-filter-field @if($dependency) data-filter-parent="{{ $dependency[0] }}" data-filter-parent-value="{{ $dependency[1] }}" @endif>
                    @if(in_array($definition['type'], ['integer', 'decimal'], true))
                        <span class="reh-survey-filter-label">{{ $definition['label'] }}</span>
                        <div class="reh-survey-range">
                            @foreach(['min' => 'من', 'max' => 'إلى'] as $bound => $boundLabel)
                                <div><label for="reh-{{ $key }}-{{ $bound }}">{{ $boundLabel }}</label><input id="reh-{{ $key }}-{{ $bound }}" name="{{ $key }}_{{ $bound }}" data-filter-label="{{ $title }} · {{ $definition['label'] }} {{ $boundLabel }}" type="number" min="0" max="1000000" step="{{ $definition['type'] === 'integer' ? '1' : 'any' }}" class="form-control"></div>
                            @endforeach
                        </div>
                    @else
                        <label for="reh-{{ $key }}">{{ $definition['label'] }}</label>
                        <select id="reh-{{ $key }}" name="{{ $key }}" data-filter-label="{{ $title }} · {{ $definition['label'] }}" class="form-select" data-dropdown-parent="#reh-advanced-filters"><option value="">الكل</option>@foreach($definition['choices'] as $value => $choice)<option value="{{ $value }}">{{ $choice }}</option>@endforeach</select>
                        @if($definition['type'] === 'multiple')<small>يشمل الإجابة المختارة ضمن الإجابات المسجلة.</small>@endif
                    @endif
                </div>
            @endforeach
            </div>
            @if($sensitive)<p class="reh-filter-hint">بيانات حساسة · تُطبق الفلاتر ضمن صلاحياتك.</p>@endif
        </details>
    @endif
@endforeach
<details data-filter-section class="reh-filter-section">
    <summary><span>09</span> أعمال التأهيل والكميات <b data-section-count></b></summary>
    <div data-filter-field><label for="reh-work-item">بند الأعمال</label><select id="reh-work-item" name="work_item" class="form-select" data-dropdown-parent="#reh-advanced-filters"><option value="">كل البنود</option>@foreach($surveyFilters->workItems() as $key => $definition)<option value="{{ $key }}">{{ $definition['label'] }}</option>@endforeach</select></div>
    <div data-filter-field data-work-condition="item"><label for="reh-work-state">حالة الكمية</label><select id="reh-work-state" name="work_state" class="form-select" data-dropdown-parent="#reh-advanced-filters"><option value="">اختر حالة الكمية</option><option value="positive">أكبر من صفر</option><option value="zero">صفر مسجل</option><option value="missing">غير مسجل</option></select></div>
    <div data-filter-field data-work-condition="positive" class="reh-filter-range"><strong>كمية البند المختار</strong><div><label for="reh-work-min">من</label><input id="reh-work-min" name="work_min" data-filter-label="كمية البند من" type="number" min="0" max="1000000" step="any" class="form-control"></div><span>—</span><div><label for="reh-work-max">إلى</label><input id="reh-work-max" name="work_max" data-filter-label="كمية البند إلى" type="number" min="0" max="1000000" step="any" class="form-control"></div></div>
    <p class="reh-filter-hint">الكمية الميدانية بوحدة البند المختار. «صفر مسجل» يختلف عن «غير مسجل».</p>
    <div data-filter-field class="reh-filter-range"><strong>مساحة التدخل (م²)</strong><div><label for="reh-intervention-min">من</label><input id="reh-intervention-min" name="intervention_min" data-filter-label="مساحة التدخل من" type="number" min="0" max="1000000" step="any" class="form-control"></div><span>—</span><div><label for="reh-intervention-max">إلى</label><input id="reh-intervention-max" name="intervention_max" data-filter-label="مساحة التدخل إلى" type="number" min="0" max="1000000" step="any" class="form-control"></div></div>
    <div data-filter-field><label for="reh-additional-work">بنود إضافية غير مدرجة</label><select id="reh-additional-work" name="additional_work" class="form-select" data-dropdown-parent="#reh-advanced-filters"><option value="">الكل</option><option value="yes">يوجد وصف لبند إضافي</option><option value="no">لا يوجد وصف مسجل</option></select></div>
</details>
