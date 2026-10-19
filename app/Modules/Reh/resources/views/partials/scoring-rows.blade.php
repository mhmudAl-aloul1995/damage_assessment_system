<div class="reh-score-rows">
@foreach($rows as $row)
    <details class="reh-score-row" data-score-row="{{ $row['id'] }}" data-score-status="{{ $row['status'] }}">
        <summary><span>{{ $row['label'] }}<small>{{ \App\Modules\Reh\Services\RehScoring::STATUSES[$row['status']] }}</small></span><strong>{{ $row['points'] ?? '—' }} <small>/ {{ $row['maximum'] }}</small></strong></summary>
        <div class="reh-score-row-body"><p>{{ $row['reason'] }}</p>
            @foreach($row['inputs'] as $input)
                <div class="reh-score-source"><span>{{ $input['label'] }}</span><strong>{{ $engineeringRows ? (($input['not_requested'] ?? false) ? 'لم تُدرج له أعمال · 0 للاحتساب' : ($input['quantity'] ?? 'غير مسجل')) : $input['display'] }}</strong>
                @if($engineeringRows)<small>بند {{ $input['code'] }} · {{ $input['reviewed'] !== null ? 'كمية مراجعة محفوظة' : 'كمية ميدانية' }}</small><button type="button" class="reh-score-link" data-score-quantity="{{ $input['field'] }}">مراجعة الكمية ←</button>@endif</div>
            @endforeach
            @if($row['inputs'])
                <button type="button" class="reh-score-link" data-score-source="{{ $row['label'] }} · {{ $sourceContext ?? ($engineeringRows ? 'التقييم الهندسي' : 'التقييم الاجتماعي') }}" aria-haspopup="dialog" aria-controls="reh-score-source-dialog">عرض البيانات الميدانية</button>
                <template data-score-source-content>
                    <div class="reh-source-explainer"><span class="badge badge-light-{{ $row['status'] === 'calculated' ? 'success' : 'warning' }}">{{ \App\Modules\Reh\Services\RehScoring::STATUSES[$row['status']] }}</span><p>{{ $row['reason'] }}</p></div>
                    <dl class="reh-source-fields">
                        @foreach($row['inputs'] as $input)
                            <div data-indicator-field="{{ $input['field'] }}"><dt>{{ $input['label'] }}</dt>
                                @if($engineeringRows)
                                    <dd><span>الكمية الميدانية: <strong>{{ $input['source'] ?? 'غير مسجل' }}</strong></span><span>الكمية المراجعة: <strong>{{ $input['reviewed'] ?? 'لم تُعدّل' }}</strong></span><small>بند {{ $input['code'] }}@if(!empty($input['unit'])) · {{ trim(explode("\n", $input['unit'])[0]) }}@endif</small>@if($input['not_requested'] ?? false)<small>لا توجد أعمال مدرجة لهذا البند؛ يُعامل كصفر ضمن الحصر القائم.</small>@endif</dd>
                                @else
                                    <dd>{{ $input['display'] }}</dd>
                                @endif
                            </div>
                        @endforeach
                    </dl>
                </template>
            @endif
        </div>
    </details>
@endforeach
</div>
