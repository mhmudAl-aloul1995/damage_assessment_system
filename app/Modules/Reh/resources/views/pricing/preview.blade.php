<h3>معاينة قائمة الأسعار</h3><p>{{ $preview['filename'] }}</p>
<div class="reh-price-metrics"><div><small>بنود مطابقة</small><strong>{{ count($preview['items']) }}</strong></div><div><small>غير متوفر</small><strong>{{ collect($preview['items'])->where('availability','unavailable')->count() }}</strong></div><div><small>ملاحظات للمراجعة</small><strong>{{ collect($preview['items'])->whereNotNull('issue')->count() }}</strong></div></div>
@foreach($preview['items'] as $item)@if($item['issue'])<p class="reh-price-warning"><strong>{{ $item['code'] }}</strong> {{ $item['issue'] }}</p>@endif@endforeach
<form data-price-form action="{{ route('reh.pricing.store') }}" method="post">
<input type="hidden" name="token" value="{{ $token }}">
<div class="reh-price-form-grid"><label>اسم الإصدار<input class="form-control" name="name" value="أسعار التأهيل {{ now()->format('Y-m-d') }}" required maxlength="160"></label><label>تاريخ السريان<input class="form-control" type="date" name="effective_date" value="{{ now()->format('Y-m-d') }}" required></label><label>شيكل لكل دولار<input class="form-control" type="number" name="exchange_rate" min="0.000001" max="100000" step="0.000001" value="{{ $preview['exchange_rate'] }}" required></label></div>
<p>لن تُضاف أي ضريبة. البنود غير المتوفرة تبقى بلا سعر.</p>
<label>ملاحظة الاستيراد<textarea class="form-control" name="reason" required minlength="3" maxlength="2000"></textarea></label>
<button class="btn btn-primary mt-4">حفظ مسودة الأسعار</button>
</form>
