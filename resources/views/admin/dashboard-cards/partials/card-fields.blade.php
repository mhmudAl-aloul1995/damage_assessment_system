<div class="col-md-2">
    <label class="form-label">المفتاح</label>
    <input type="text" name="key" class="form-control" value="{{ old('key', $card?->key) }}" required>
</div>
<div class="col-md-2">
    <label class="form-label">العنوان</label>
    <input type="text" name="title" class="form-control" value="{{ old('title', $card?->title) }}" required>
</div>
<div class="col-md-2">
    <label class="form-label">النص الفرعي</label>
    <input type="text" name="subtitle" class="form-control" value="{{ old('subtitle', $card?->subtitle) }}">
</div>
<div class="col-md-2">
    <label class="form-label">مصدر البيانات</label>
    <select name="source_bucket" class="form-select" required>
        @foreach ($sourceBuckets as $sourceBucket)
            <option value="{{ $sourceBucket }}" @selected(old('source_bucket', $card?->source_bucket) === $sourceBucket)>{{ $sourceBucket }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-2">
    <label class="form-label">مفتاح الإجمالي</label>
    <input type="text" name="total_stat_key" class="form-control" value="{{ old('total_stat_key', $card?->total_stat_key) }}" required>
</div>
<div class="col-md-1">
    <label class="form-label">الأيقونة</label>
    <input type="text" name="icon" class="form-control" value="{{ old('icon', $card?->icon ?? 'ki-category') }}" required>
</div>
<div class="col-md-1">
    <label class="form-label">اللون</label>
    <input type="color" name="color" class="form-control form-control-color w-100" value="{{ old('color', $card?->color ?? '#315f72') }}" required>
</div>
<div class="col-md-1">
    <label class="form-label">الترتيب</label>
    <input type="number" name="sort_order" class="form-control" min="0" value="{{ old('sort_order', $card?->sort_order ?? 0) }}">
</div>
<div class="col-md-1">
    <label class="form-check form-switch form-check-custom form-check-solid mt-8">
        <input type="hidden" name="is_active" value="0">
        <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $card?->is_active ?? true))>
        <span class="form-check-label">مفعلة</span>
    </label>
</div>
