<div class="reh-score-rows">
@foreach($rows as $row)
    <details class="reh-score-row" data-score-row="{{ $row['id'] }}" data-score-status="{{ $row['status'] }}">
        <summary><span>{{ $row['label'] }}<small>{{ \App\Modules\Reh\Services\RehScoring::STATUSES[$row['status']] }}</small></span><strong>{{ $row['points'] ?? '—' }} <small>/ {{ $row['maximum'] }}</small></strong></summary>
        <div class="reh-score-row-body"><p>{{ $row['reason'] }}</p>
            @foreach($row['inputs'] as $input)
                <div class="reh-score-source"><span>{{ $input['label'] }}</span><strong>{{ $engineeringRows ? (($input['not_requested'] ?? false) ? 'لم تُدرج له أعمال · 0 للاحتساب' : ($input['quantity'] ?? 'غير مسجل')) : $input['display'] }}</strong>
                @if($engineeringRows)<small>بند {{ $input['code'] }} · {{ $input['reviewed'] !== null ? 'كمية مراجعة محفوظة' : 'كمية ميدانية' }}</small><button type="button" class="reh-score-link" data-score-quantity="{{ $input['field'] }}">مراجعة الكمية ←</button>@endif</div>
            @endforeach
            @if(!$engineeringRows && $row['inputs'])<button type="button" class="reh-score-link" data-score-source="{{ $row['inputs'][0]['field'] }}">عرض البيانات الميدانية</button>@endif
        </div>
    </details>
@endforeach
</div>
