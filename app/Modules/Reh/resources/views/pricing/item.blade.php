<span class="reh-eyebrow">البند {{ $item->code }} · {{ $item->unit }}</span><h3>{{ $item->label }}</h3><p>{{ $item->book->name }}</p>
@if($item->issue)<p class="reh-price-warning">{{ $item->issue }}</p>@endif
<details><summary>المواصفات ومصدر السعر</summary><p>{{ $item->description }}</p><small>{{ $item->source_cell }}</small></details>
@php($editable = $item->book->status === 'draft' && auth()->user()->can('reh.pricing.manage'))
<form data-price-form action="{{ route('reh.pricing.item.update',$item) }}" method="post">
<input type="hidden" name="revision" value="{{ $item->book->revision }}">
<div class="reh-price-table-wrap"><table class="table reh-price-table"><thead><tr><th>مكون التحليل</th><th>الوحدة</th><th>الكمية لكل وحدة بند</th><th>سعر المكون ₪</th><th>التكلفة ₪</th></tr></thead><tbody>
@foreach($item->components as $part)<tr><td>{{ $part->label }}<small>{{ $part->category }}</small><input type="hidden" name="components[{{ $loop->index }}][id]" value="{{ $part->id }}"></td><td>{{ $part->unit ?: '—' }}</td>
<td>@if($editable)<input aria-label="كمية {{ $part->label }}" class="form-control" type="number" step="0.000001" min="0" max="1000000" name="components[{{ $loop->index }}][quantity]" value="{{ $part->quantity }}">@else{{ $part->quantity === null ? '—' : (float)$part->quantity }}@endif</td>
<td>@if($editable)<input aria-label="سعر {{ $part->label }}" class="form-control" type="number" step="0.000001" min="0" max="1000000" name="components[{{ $loop->index }}][rate]" value="{{ $part->rate }}">@else{{ $part->rate === null ? '—' : number_format((float)$part->rate,2) }}@endif</td>
<td>{{ $part->quantity === null || $part->rate === null ? '—' : number_format((float)$part->quantity*(float)$part->rate,2) }}</td></tr>@endforeach</tbody></table></div>
<p class="reh-price-total">سعر وحدة البند: <strong>{{ $item->rate === null ? 'غير متوفر بالسوق' : number_format((float)$item->rate,2).' ₪' }}</strong></p>
@if($editable)
<label>التوفر<select class="form-select" name="availability"><option value="available" @selected($item->availability === 'available')>متوفر</option><option value="unavailable" @selected($item->availability === 'unavailable')>غير متوفر بالسوق</option></select></label>
@if($item->issue)<label class="mt-3"><input type="checkbox" name="resolve_issue" value="1" required> راجعت الملاحظة والمكونات، ويُحتسب السعر من هذا التحليل.</label>@endif
<label class="mt-3">سبب التعديل<textarea class="form-control" name="reason" required minlength="3"></textarea></label><button class="btn btn-primary mt-4">حفظ وإعادة احتساب السعر</button>
@endif
</form>
