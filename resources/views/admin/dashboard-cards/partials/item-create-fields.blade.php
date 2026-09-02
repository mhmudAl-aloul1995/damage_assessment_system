<div class="col-md-1">
    <label class="form-label">الترتيب</label>
    <input type="number" name="sort_order" class="form-control" min="0" value="0">
</div>
<div class="col-md-2">
    <label class="form-label">العنوان</label>
    <input type="text" name="title" class="form-control" required>
</div>
<div class="col-md-2">
    <label class="form-label">المفتاح</label>
    <input type="text" name="key" class="form-control" required>
</div>
<div class="col-md-2">
    <label class="form-label">مصدر القيمة</label>
    <select name="source_bucket" class="form-select" required>
        @foreach ($sourceBuckets as $sourceBucket)
            <option value="{{ $sourceBucket }}">{{ $sourceBucket }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-2">
    <label class="form-label">stat_key</label>
    <input type="text" name="stat_key" class="form-control" required>
</div>
<div class="col-md-1">
    <label class="form-label">الأيقونة</label>
    <input type="text" name="icon" class="form-control" value="ki-dot" required>
</div>
<div class="col-md-2">
    <label class="form-label">الرابط</label>
    <div class="d-flex gap-2">
        <input type="text" name="link_group" class="form-control" placeholder="group">
        <input type="text" name="link_key" class="form-control" placeholder="key">
    </div>
</div>
<div class="col-md-2">
    <label class="form-label">الشرط</label>
    <div class="d-flex gap-2">
        <input type="text" name="filter_field" class="form-control" placeholder="field">
        <select name="filter_operator" class="form-select">
            <option value="">-</option>
            @foreach ($operators as $operator)
                <option value="{{ $operator }}">{{ $operator }}</option>
            @endforeach
        </select>
        <input type="text" name="filter_value" class="form-control" placeholder="value">
    </div>
</div>
<div class="col-md-1">
    <label class="form-label">لاحقة</label>
    <input type="text" name="value_suffix" class="form-control">
</div>
<div class="col-md-1">
    <label class="form-label">كسور</label>
    <input type="number" name="decimal_places" class="form-control" min="0" max="6" value="0">
</div>
<div class="col-md-1">
    <input type="hidden" name="calculation_type" value="stat_key">
    <input type="hidden" name="is_active" value="0">
    <label class="form-check form-switch form-check-custom form-check-solid mt-8">
        <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
        <span class="form-check-label">مفعل</span>
    </label>
</div>
