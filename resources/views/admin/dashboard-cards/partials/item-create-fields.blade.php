<input type="hidden" name="value_suffix" value="">
<input type="hidden" name="decimal_places" value="0">

<div class="col-md-2">
    <label class="form-label">الترتيب</label>
    <input type="number" name="sort_order" class="form-control" min="0" value="{{ $nextItemSortOrder ?? 1 }}">
</div>
<div class="col-md-10">
    <label class="form-label">العنوان</label>
    <input type="text" name="title" class="form-control" required>
</div>

<div class="col-md-4">
    <label class="form-label">طريقة العدّ</label>
    <select name="calculation_type" class="form-select js-calculation-type" required>
        <option value="stat_key">إحصائية جاهزة</option>
        <option value="count_condition">عدّ حسب الشرط</option>
    </select>
</div>
<div class="col-md-4">
    <label class="form-label">مصدر العدّ</label>
    <select name="source_bucket" class="form-select ltr-input js-source-bucket" required>
        @foreach ($sourceBuckets as $sourceBucket)
            <option value="{{ $sourceBucket }}">{{ $sourceBucket }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-4 js-stat-key-group">
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
    <select name="filter_value" class="form-select ltr-input js-filter-value">
        <option value="">اختر حقل الشرط أولاً</option>
    </select>
</div>
<div class="col-md-4">
    <label class="form-label">الأيقونة</label>
    @include('admin.dashboard-cards.partials.icon-select', [
        'name' => 'icon',
        'selectedIcon' => 'ki-dot',
    ])
</div>
<div class="col-md-12">
    <input type="hidden" name="is_active" value="0">
    <label class="form-check form-switch form-check-custom form-check-solid">
        <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
        <span class="form-check-label">مفعل</span>
    </label>
</div>
