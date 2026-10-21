<section class="reh-price-panel">
<div class="reh-price-toolbar"><div><h3>إصدارات الأسعار</h3><p>الإصدار المعتمد محفوظ. أي تغيير لاحق يبدأ بمسودة مراجعة.</p></div>
<label>الإصدار<select class="form-select" data-price-book>@forelse($books as $edition)<option value="{{ $edition->id }}" @selected($book?->id === $edition->id)>{{ $edition->name }} · {{ $edition->status === 'approved' ? 'معتمد' : 'مسودة' }}</option>@empty<option>لم تُستورد أسعار بعد</option>@endforelse</select></label></div>
@can('reh.pricing.manage')
<details class="reh-price-import"><summary>استيراد قائمة أسعار Excel</summary>
<form data-price-form data-price-preview action="{{ route('reh.pricing.preview') }}" method="post" enctype="multipart/form-data">
<label>ملف تحليل الأسعار<input type="file" name="file" accept=".xlsx" required class="form-control"></label><button class="btn btn-primary">معاينة قبل الحفظ</button>
</form><p>لن تُعتمد الأسعار بمجرد رفع الملف.</p></details>
@endcan
</section>
@if($book)
<section class="reh-price-panel">
<div class="reh-price-metrics">
<div><small>البنود</small><strong>{{ $items->count() }}</strong></div>
<div><small>متوفر بسعر</small><strong>{{ $items->where('availability','available')->whereNull('issue')->count() }}</strong></div>
<div><small>غير متوفر بالسوق</small><strong>{{ $items->where('availability','unavailable')->count() }}</strong></div>
<div><small>يحتاج مراجعة</small><strong>{{ $items->whereNotNull('issue')->count() }}</strong></div>
</div>
<div class="reh-price-meta"><span class="badge {{ $book->status === 'approved' ? 'badge-light-success' : 'badge-light-warning' }}">{{ $book->status === 'approved' ? 'إصدار معتمد' : 'مسودة للمراجعة' }}</span><span>السريان {{ $book->effective_date->format('Y-m-d') }}</span><span>1 دولار = {{ (float)$book->exchange_rate }} ₪</span><span>عملة الميزانية: USD</span><span>الأسعار دون إضافة ضريبة</span></div>
<div class="reh-price-toolbar reh-price-filters">
<label>بحث عن بند<input type="search" class="form-control" data-price-search placeholder="الكود أو الاسم…"></label>
<label>المجموعة<select class="form-select" multiple data-price-filter="category">@foreach($items->pluck('category')->unique() as $category)<option>{{ $category }}</option>@endforeach</select></label>
<label>الحالة<select class="form-select" multiple data-price-filter="status"><option value="available">متوفر</option><option value="unavailable">غير متوفر بالسوق</option><option value="review">يحتاج مراجعة</option><option value="changed">سعر متغير</option></select></label>
</div>
@if($previous)<p class="text-muted">الفروق مقارنة بإصدار «{{ $previous->name }}». تغيير السعر لا يغيّر التكاليف المعتمدة سابقًا.</p>@endif
<div class="reh-price-table-wrap"><table class="table reh-price-table"><thead><tr><th>البند</th><th>الوحدة</th><th>السعر USD</th><th>الفارق USD</th><th>الحالة</th><th></th></tr></thead><tbody>
@foreach($items as $item)
@php($old = $previousPrices->get($item->field))
@php($delta = $old?->rate !== null && $item->rate !== null ? round((float)$item->rate/(float)$book->exchange_rate - (float)$old->rate/(float)$previous->exchange_rate,6) : null)
<tr data-price-row data-category="{{ $item->category }}" data-status="{{ $item->issue ? 'review' : $item->availability }}" data-changed="{{ $old && ($old->rate !== $item->rate || $old->availability !== $item->availability || $previous->exchange_rate !== $book->exchange_rate) ? '1' : '0' }}">
<td><span class="reh-price-code">{{ $item->code }}</span><strong>{{ $item->label }}</strong><small>{{ $item->category }}</small></td><td>{{ $item->unit }}</td><td class="reh-price-number">{{ $item->rate === null ? '—' : number_format((float)$item->rate/(float)$book->exchange_rate,4) }}<small>{{ $item->rate === null ? '—' : number_format((float)$item->rate,2).' ₪' }}</small></td><td>{{ $delta === null ? '—' : ($delta > 0 ? '+' : '').number_format($delta,4) }}</td><td><span class="badge {{ $item->issue ? 'badge-light-warning' : ($item->availability === 'available' ? 'badge-light-success' : 'badge-light') }}">{{ $item->issue ? 'يحتاج مراجعة' : ($item->availability === 'available' ? 'متوفر' : 'غير متوفر بالسوق') }}</span></td><td><button class="btn btn-light-primary btn-sm" data-price-open="{{ route('reh.pricing.item',$item) }}">تحليل السعر</button></td>
</tr>@endforeach
</tbody></table><p data-price-empty hidden>لا توجد بنود مطابقة لهذه الفلاتر.</p></div>
@if($book->status === 'draft')
@can('reh.pricing.manage')<details class="reh-price-import"><summary>اسم الإصدار وسعر الصرف وتاريخ السريان</summary><form data-price-form action="{{ route('reh.pricing.book.update',$book) }}" method="post"><input type="hidden" name="revision" value="{{ $book->revision }}"><div class="reh-price-form-grid"><label>اسم الإصدار<input class="form-control" name="name" value="{{ $book->name }}" required></label><label>شيكل لكل دولار<input class="form-control" type="number" name="exchange_rate" min="0.000001" step="0.000001" value="{{ $book->exchange_rate }}" required></label><label>تاريخ السريان<input class="form-control" type="date" name="effective_date" value="{{ $book->effective_date->format('Y-m-d') }}" required></label><label>سبب التعديل<input class="form-control" name="reason" required minlength="3"></label></div><button class="btn btn-light-primary">حفظ إعدادات الإصدار</button></form></details>@endcan
<button type="button" class="btn btn-light-primary mt-4" data-price-open="{{ route('reh.pricing.impact',$book) }}">معاينة أثر الأسعار على الوحدات</button>
@can('reh.pricing.approve')<form class="reh-price-action" data-price-form action="{{ route('reh.pricing.approve',$book) }}" method="post"><input type="hidden" name="revision" value="{{ $book->revision }}"><label>ملاحظة الاعتماد<input class="form-control" name="reason" required minlength="3" placeholder="مراجعة الأسعار وسعر الصرف وتاريخ السريان…"></label><button class="btn btn-primary" @disabled($items->whereNotNull('issue')->isNotEmpty())>اعتماد إصدار الأسعار</button></form>@endcan
@else
@can('reh.pricing.manage')<form class="reh-price-action" data-price-form action="{{ route('reh.pricing.revise',$book) }}" method="post"><label>سبب المراجعة<input class="form-control" name="reason" required minlength="3" placeholder="سبب إصدار قائمة أسعار جديدة"></label><button class="btn btn-light-primary">إنشاء مسودة مراجعة</button></form>@endcan
@endif
</section>
<section class="reh-price-panel"><h3>سجل تغييرات الأسعار</h3>@forelse($logs as $log)<details class="reh-price-log"><summary>{{ ['import'=>'استيراد مسودة','item_updated'=>'تعديل تحليل سعر','approved'=>'اعتماد أسعار','revision'=>'إصدار مراجعة','estimate_approved'=>'اعتماد تكلفة وحدة','settings_updated'=>'تعديل إعدادات الإصدار'][$log->action] ?? $log->action }} · {{ $log->user?->name }} · {{ $log->created_at->format('Y-m-d H:i') }}</summary><p>{{ $log->reason }}</p>
@if(isset($log->changes['code']))<p>البند {{ $log->changes['code'] }}: {{ $log->changes['before']['rate'] ?? 'غير متوفر' }} ← {{ $log->changes['after']['rate'] ?? 'غير متوفر' }} ₪</p>@endif
@if(isset($log->changes['after']['components']))
<div class="reh-price-table-wrap"><table class="table reh-price-table"><thead><tr><th>المكون</th><th>الكمية قبل</th><th>الكمية بعد</th><th>السعر قبل ₪</th><th>السعر بعد ₪</th></tr></thead><tbody>
@foreach($log->changes['after']['components'] as $component)
@php($original = collect($log->changes['before']['components'] ?? [])->firstWhere('id',$component['id']))
<tr><td>{{ $component['label'] }}</td><td>{{ $original['quantity'] ?? 'غير متوفر' }}</td><td>{{ $component['quantity'] ?? 'غير متوفر' }}</td><td>{{ $original['rate'] ?? 'غير متوفر' }}</td><td>{{ $component['rate'] ?? 'غير متوفر' }}</td></tr>
@endforeach
</tbody></table></div>
@endif
@if($log->action === 'settings_updated')
@foreach(['name'=>'اسم الإصدار','exchange_rate'=>'سعر الصرف','effective_date'=>'تاريخ السريان'] as $key=>$label)<p>{{ $label }}: {{ $log->changes['before'][$key] ?? 'غير متوفر' }} ← {{ $log->changes['after'][$key] ?? 'غير متوفر' }}</p>@endforeach
@endif
@if(isset($log->changes['total']))<p>التكلفة المعتمدة: {{ number_format((float)($log->changes['total_usd'] ?? ((float)$log->changes['total']/(float)$book->exchange_rate)),2) }} USD · المقابل {{ $log->changes['total'] }} ₪</p>@endif
</details>@empty<p>لا توجد تعديلات.</p>@endforelse</section>
@endif

