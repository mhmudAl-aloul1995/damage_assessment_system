@php($surveyFilters = app(\App\Modules\Reh\Services\RehSurveyFilters::class))
@foreach($surveyFilters->groups() as $group => [$title, $scope, $sensitive])
    @if(!$sensitive || auth()->user()->can('reh.sensitive'))
        <details data-filter-section class="reh-filter-section">
            <summary><span>{{ str_pad((string) ($loop->iteration + 3), 2, '0', STR_PAD_LEFT) }}</span> {{ $title }} <b data-section-count></b></summary>
            <div class="reh-advanced-grid">
            @foreach($surveyFilters->definitions() as $key => $definition)
                @continue($definition['group'] !== $group)
                @php($range = in_array($definition['type'], ['integer', 'decimal', 'date', 'datetime'], true))
                @php($hasCondition = !empty($filters[$key.'_op']) || \App\Modules\Reh\Services\RehFilterOperators::active($filters ?? [], $key))
                @php($conditionOperator = $filters[$key.'_op'] ?? $definition['default_operator'])
                @php($inputType = match($definition['type']) { 'integer', 'decimal' => 'number', 'date' => 'date', 'datetime' => 'datetime-local', default => 'text' })
                <div data-filter-field data-survey-condition="{{ $key }}" data-filter-condition="{{ $key }}" data-condition-type="{{ $definition['type'] }}" data-condition-default="{{ $definition['default_operator'] }}" @if($range) data-condition-lazy data-condition-expanded="{{ $hasCondition ? 'true' : 'false' }}" @endif>
                    <div class="reh-condition-heading">
                        <label for="reh-{{ $key }}">{{ $definition['label'] }}</label>
                        <button type="button" class="reh-condition-trigger" data-condition-trigger aria-expanded="false" aria-controls="reh-{{ $key }}-conditions" aria-label="تغيير شرط {{ $definition['label'] }}"><span data-condition-label>{{ $range && !$hasCondition ? 'إضافة شرط' : ($definition['operators'][$conditionOperator] ?? 'الكل') }}</span> <span aria-hidden="true">⌄</span></button>
                    </div>
                    <input type="hidden" id="reh-{{ $key }}-operator" name="{{ $key }}_op" value="" data-filter-label="{{ $title }} · {{ $definition['label'] }} · الشرط" data-condition-operator>
                    <div class="reh-condition-menu" id="reh-{{ $key }}-conditions" role="group" aria-label="شروط {{ $definition['label'] }}" hidden>
                        @foreach($definition['operators'] as $operator => $operatorLabel)<button type="button" data-condition-option="{{ $operator }}" aria-pressed="false">{{ $operatorLabel }}</button>@endforeach
                        @if($range)<button type="button" data-condition-clear @if(!$hasCondition) hidden @endif>إزالة الشرط</button>@endif
                    </div>
                    @if($range)
                        <div class="reh-survey-range" data-condition-range @if(!$hasCondition || $conditionOperator !== 'between') hidden @endif>
                            @foreach(['min' => 'من', 'max' => 'إلى'] as $bound => $boundLabel)
                                <div><label for="reh-{{ $key }}-{{ $bound }}">{{ $boundLabel }}</label><input id="reh-{{ $key }}-{{ $bound }}" name="{{ $key }}_{{ $bound }}" data-filter-label="{{ $title }} · {{ $definition['label'] }} {{ $boundLabel }}" type="{{ $inputType }}" @if($inputType === 'number') min="-1000000" max="1000000" step="{{ $definition['type'] === 'integer' ? '1' : 'any' }}" @elseif($inputType === 'datetime-local') step="1" @endif class="form-control" data-condition-value @if(!$hasCondition || $conditionOperator !== 'between') disabled @endif></div>
                            @endforeach
                        </div>
                        <div data-condition-single @if(!$hasCondition || $conditionOperator === 'between' || in_array($conditionOperator, ['blank', 'not_blank'], true)) hidden @endif><input id="reh-{{ $key }}" name="{{ $key }}" data-filter-label="{{ $title }} · {{ $definition['label'] }}" type="{{ $inputType }}" @if($inputType === 'number') min="-1000000" max="1000000" step="{{ $definition['type'] === 'integer' ? '1' : 'any' }}" @elseif($inputType === 'datetime-local') step="1" @endif class="form-control" data-condition-value @if(!$hasCondition || $conditionOperator === 'between' || in_array($conditionOperator, ['blank', 'not_blank'], true)) disabled @endif></div>
                    @elseif(in_array($definition['type'], ['choice', 'multiple'], true))
                        <div data-condition-single><select multiple data-control="select2" data-placeholder="الكل" data-close-on-select="false" id="reh-{{ $key }}" name="{{ $key }}" data-filter-label="{{ $title }} · {{ $definition['label'] }}" class="form-select" data-dropdown-parent="#reh-advanced-filters" data-condition-value>@foreach($definition['choices'] as $value => $choice)<option value="{{ $value }}">{{ $choice }}</option>@endforeach</select></div>
                        @if($definition['type'] === 'multiple')<small>يشمل أيًّا من الإجابات المختارة ضمن الإجابات المسجلة.</small>@endif
                    @elseif($definition['type'] === 'text')
                        <div data-condition-single><input id="reh-{{ $key }}" name="{{ $key }}" data-filter-label="{{ $title }} · {{ $definition['label'] }}" class="form-control" maxlength="1000" placeholder="اكتب القيمة المطلوبة…" data-condition-value></div>
                    @endif
                    <div class="reh-condition-no-value" data-condition-no-value @if($definition['type'] !== 'presence') hidden @endif>{{ $definition['type'] === 'presence' ? 'اختر فارغ أو غير فارغ' : 'لا يلزم اختيار إجابة' }}</div>
                    @if(trim($definition['context']) !== trim($title))<small class="reh-condition-context">{{ $definition['context'] }}</small>@endif
                </div>
            @endforeach
            </div>
            @if($sensitive)<p class="reh-filter-hint">بيانات حساسة · تُطبق الفلاتر ضمن صلاحياتك.</p>@endif
        </details>
    @endif
@endforeach
<details data-filter-section class="reh-filter-section">
    <summary><span>+</span> أعمال التأهيل والكميات <b data-section-count></b></summary>
    <div data-filter-field><label for="reh-work-item">بند الأعمال</label><select multiple data-control="select2" data-placeholder="الكل" data-close-on-select="false" id="reh-work-item" name="work_item" class="form-select" data-dropdown-parent="#reh-advanced-filters">@foreach($surveyFilters->workItems() as $key => $definition)<option value="{{ $key }}">{{ $definition['label'] }}</option>@endforeach</select></div>
    <div data-filter-field data-work-condition="item"><label for="reh-work-state">حالة الكمية</label><select multiple data-control="select2" data-placeholder="الكل" data-close-on-select="false" id="reh-work-state" name="work_state" class="form-select" data-dropdown-parent="#reh-advanced-filters"><option value="positive">أكبر من صفر</option><option value="zero">صفر مسجل</option><option value="missing">غير مسجل</option></select></div>
    <div data-filter-field data-work-condition="positive" class="reh-filter-range"><strong>كمية البند المختار</strong><div><label for="reh-work-min">من</label><input id="reh-work-min" name="work_min" data-filter-label="كمية البند من" type="number" min="0" max="1000000" step="any" class="form-control"></div><span>—</span><div><label for="reh-work-max">إلى</label><input id="reh-work-max" name="work_max" data-filter-label="كمية البند إلى" type="number" min="0" max="1000000" step="any" class="form-control"></div></div>
    <p class="reh-filter-hint">الكمية الميدانية بوحدة البند المختار. «صفر مسجل» يختلف عن «غير مسجل».</p>
    <div data-filter-field class="reh-filter-range"><strong>مساحة التدخل (م²)</strong><div><label for="reh-intervention-min">من</label><input id="reh-intervention-min" name="intervention_min" data-filter-label="مساحة التدخل من" type="number" min="0" max="1000000" step="any" class="form-control"></div><span>—</span><div><label for="reh-intervention-max">إلى</label><input id="reh-intervention-max" name="intervention_max" data-filter-label="مساحة التدخل إلى" type="number" min="0" max="1000000" step="any" class="form-control"></div></div>
    <div data-filter-field><label for="reh-additional-work">بنود إضافية غير مدرجة</label><select multiple data-control="select2" data-placeholder="الكل" data-close-on-select="false" id="reh-additional-work" name="additional_work" class="form-select" data-dropdown-parent="#reh-advanced-filters"><option value="yes">يوجد وصف لبند إضافي</option><option value="no">لا يوجد وصف مسجل</option></select></div>
</details>
