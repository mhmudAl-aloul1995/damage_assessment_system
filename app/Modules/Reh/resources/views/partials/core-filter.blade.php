@php
    $condition = \App\Modules\Reh\Services\RehAdvancedFilters::conditions()[$filterKey];
    $range = in_array($condition['type'], ['integer', 'date'], true);
    $inputType = $condition['type'] === 'integer' ? 'number' : ($condition['type'] === 'date' ? 'date' : 'text');
@endphp
<div data-filter-field data-filter-condition="{{ $filterKey }}" data-condition-type="{{ $condition['type'] }}" data-condition-default="{{ $condition['default'] }}" class="reh-core-condition {{ $filterKey === 'q' ? 'reh-search' : '' }}">
    <div class="reh-condition-heading">
        <label for="reh-{{ $filterKey }}">{{ $filterLabel }}</label>
        <button type="button" class="reh-condition-trigger" data-condition-trigger aria-expanded="false" aria-controls="reh-{{ $filterKey }}-conditions" aria-label="تغيير شرط {{ $filterLabel }}"><span data-condition-label>{{ $condition['operators'][$condition['default']] }}</span> <span aria-hidden="true">⌄</span></button>
    </div>
    <input type="hidden" name="{{ $filterKey }}_op" data-condition-operator value="">
    <div class="reh-condition-menu" id="reh-{{ $filterKey }}-conditions" role="group" aria-label="شروط {{ $filterLabel }}" hidden>
        @foreach($condition['operators'] as $operator => $operatorLabel)<button type="button" data-condition-option="{{ $operator }}" aria-pressed="false">{{ $operatorLabel }}</button>@endforeach
    </div>
    @if($range)
        <div class="reh-survey-range" data-condition-range>
            @foreach(['min' => 'من', 'max' => 'إلى'] as $bound => $boundLabel)
                @php($boundName = $filterKey === 'visit' ? ($bound === 'min' ? 'visit_from' : 'visit_to') : $filterKey.'_'.$bound)
                <div><label for="reh-{{ $boundName }}">{{ $boundLabel }}</label><input id="reh-{{ $boundName }}" name="{{ $boundName }}" type="{{ $inputType }}" @if($inputType === 'number') min="0" max="{{ $filterMaximum ?? 1000000 }}" @endif class="form-control" data-condition-value></div>
            @endforeach
        </div>
        <div data-condition-single hidden><input id="reh-{{ $filterKey }}" name="{{ $filterKey }}" type="{{ $inputType }}" @if($inputType === 'number') min="0" max="{{ $filterMaximum ?? 1000000 }}" @endif class="form-control" data-condition-value disabled></div>
    @elseif($condition['type'] === 'choice')
        <div data-condition-single><select multiple data-control="select2" data-placeholder="الكل" data-close-on-select="false" id="reh-{{ $filterKey }}" name="{{ $filterKey }}" class="form-select" data-dropdown-parent="{{ in_array($filterKey, ['survey_status', 'status', 'priority'], true) ? '#reh-filters' : '#reh-advanced-filters' }}" data-condition-value>@foreach($filterOptions as $value => $option)<option value="{{ $value }}" @selected(in_array((string) $value, array_map('strval', (array) ($filters[$filterKey] ?? [])), true))>{{ $option }}</option>@endforeach</select></div>
    @else
        <div data-condition-single><input id="reh-{{ $filterKey }}" name="{{ $filterKey }}" class="form-control" maxlength="100" @if($filterKey === 'q') placeholder="اكتب معرّف الحصر للوحدة أو المبنى، الاسم أو الكود…" autocomplete="off" @else placeholder="مثال: 2328" @endif data-condition-value></div>
    @endif
    <div class="reh-condition-no-value" data-condition-no-value hidden>لا يلزم اختيار إجابة</div>
</div>
