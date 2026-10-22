@php
    $hasPricedLines = $cost && collect($cost['lines'])->contains(fn (array $line): bool => $line['amount'] !== null);
    $hasAmount = $cost && ($cost['complete'] || $hasPricedLines);
@endphp
<div class="reh-maintenance-pricing" data-unit-pricing="{{ $unit->id }}">
    <small>{{ $cost && !$cost['complete'] && $hasPricedLines ? 'مبلغ جزئي' : 'التكلفة المحسوبة' }}</small>
    <strong dir="ltr">@if($hasAmount){{ number_format($cost['usd'], 2) }} <span>USD</span>@else<span class="reh-cost-unavailable">—</span>@endif</strong>
    <span class="reh-maintenance-badge {{ !$cost ? 'is-unavailable' : ($cost['complete'] ? '' : 'is-duplicate') }}">{{ !$cost ? 'بانتظار الأسعار' : ($cost['complete'] ? 'مكتمل التسعير' : 'تسعير غير مكتمل') }}</span>
    @if($cost && !$cost['complete'])<small class="reh-maintenance-pricing-reason" title="{{ implode(' · ', $cost['missing']) }}">{{ $cost['missing'][0] }}@if(count($cost['missing']) > 1) · و{{ count($cost['missing']) - 1 }} ملاحظة أخرى@endif</small>@endif
    <button type="button" class="reh-cost-details" data-price-unit="{{ route('reh.pricing.unit', $unit) }}" aria-label="تفاصيل تكلفة الوحدة {{ $unit->old_objectid }} بمعرّف التأهيل {{ $unit->object_id }}">تفاصيل التكلفة ←</button>
</div>
