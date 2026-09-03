<input type="hidden" name="calculation_type" value="stat_key">
<input type="hidden" name="value_suffix" value="">
<input type="hidden" name="decimal_places" value="0">

<div class="col-md-2">
    <label class="form-label">الترتيب</label>
    <input type="number" name="sort_order" class="form-control" min="0" value="0">
</div>
<div class="col-md-10">
    <label class="form-label">العنوان</label>
    <input type="text" name="title" class="form-control" required>
</div>

<div class="col-md-6">
    <label class="form-label">مصدر العدّ</label>
    <select name="source_bucket" class="form-select ltr-input js-source-bucket" required>
        @foreach ($sourceBuckets as $sourceBucket)
            <option value="{{ $sourceBucket }}">{{ $sourceBucket }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-6">
    <label class="form-label">مفتاح الإحصائية</label>
    <select name="stat_key" class="form-select ltr-input js-stat-key" required>
        @foreach ($statKeys as $sourceBucket => $keys)
            @foreach ($keys as $key)
                <option value="{{ $key }}" data-source-bucket="{{ $sourceBucket }}">{{ $key }}</option>
            @endforeach
        @endforeach
    </select>
</div>

<div class="col-md-4">
    <label class="form-label">حقل الشرط اختياري</label>
    <select name="filter_field" class="form-select ltr-input js-filter-field">
        <option value="">بدون شرط</option>
        @foreach ($filterFields as $sourceBucket => $fields)
            @foreach ($fields as $field)
                <option value="{{ $field }}" data-source-bucket="{{ $sourceBucket }}">{{ $field }}</option>
            @endforeach
        @endforeach
    </select>
</div>

<div class="col-md-3">
    <label class="form-label">عامل الشرط</label>
    <select name="filter_operator" class="form-select ltr-input">
        <option value="">بدون</option>
        @foreach ($operators as $operator)
            <option value="{{ $operator }}">{{ $operator }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-5">
    <label class="form-label">قيمة الشرط</label>
    <input type="text" name="filter_value" class="form-control ltr-input" placeholder="value">
</div>
<div class="col-md-12">
    <input type="hidden" name="is_active" value="0">
    <label class="form-check form-switch form-check-custom form-check-solid">
        <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
        <span class="form-check-label">مفعل</span>
    </label>
</div>
