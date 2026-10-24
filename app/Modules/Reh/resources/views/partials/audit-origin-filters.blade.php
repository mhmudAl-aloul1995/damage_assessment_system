<div class="reh-audit-origin-filters" role="group" aria-label="مصدر التعديل">
    @foreach(['all' => 'الكل', 'manual' => 'يدوي', 'sync' => 'مزامنة'] as $value => $label)
    <button type="button" data-audit-origin-filter="{{ $value }}" aria-pressed="{{ $value === 'all' ? 'true' : 'false' }}">{{ $label }}</button>
    @endforeach
</div>
