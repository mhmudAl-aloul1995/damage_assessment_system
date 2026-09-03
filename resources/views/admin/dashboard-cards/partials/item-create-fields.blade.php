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
    <select name="calculation_type" class="form-select js-dashboard-select2 js-calculation-type" data-control="select2" data-hide-search="true" required>
        <option value="stat_key">إحصائية جاهزة</option>
        <option value="count_condition">عدّ حسب الشرط</option>
    </select>
</div>
<div class="col-md-4">
    <label class="form-label">مصدر العدّ</label>
    <select name="source_bucket" class="form-select ltr-input js-dashboard-select2 js-source-bucket" data-control="select2" required>
        @foreach ($sourceBuckets as $sourceBucket)
            <option value="{{ $sourceBucket }}">{{ $sourceBucket }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-4 js-stat-key-group {{ old('calculation_type', 'stat_key') === 'count_condition' ? 'd-none' : '' }}">
    <label class="form-label">مفتاح الإحصائية</label>
    <select name="stat_key" class="form-select ltr-input js-dashboard-select2 js-stat-key" data-control="select2" required>
        @foreach ($statKeys as $sourceBucket => $keys)
            @foreach ($keys as $key)
                <option value="{{ $key }}" data-source-bucket="{{ $sourceBucket }}">{{ $key }}</option>
            @endforeach
        @endforeach
    </select>
</div>

<div class="col-12">
    <div class="condition-box js-condition-list" data-next-index="1">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <label class="form-label mb-0">الشروط</label>
            <button type="button" class="btn btn-sm btn-light-primary js-add-condition">
                <i class="ki-duotone ki-plus fs-3"></i>
                إضافة شرط
            </button>
        </div>

        <div class="row g-3 align-items-end js-condition-row condition-row">
            <div class="col-md-4">
                <label class="form-label">حقل الشرط</label>
                <select name="conditions[0][field]" class="form-select ltr-input js-dashboard-select2 js-filter-field" data-control="select2">
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
                <select name="conditions[0][operator]" class="form-select ltr-input js-dashboard-select2 js-filter-operator" data-control="select2" data-hide-search="true">
                    @foreach ($operators as $operator)
                        <option value="{{ $operator }}" @selected($operator === '=')>{{ $operator }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">قيمة الشرط</label>
                <select name="conditions[0][value][]" class="form-select ltr-input js-dashboard-select2 js-filter-value" data-control="select2" multiple>
                    <option value="__NULL__">فارغ</option>
                    <option value="">اختر حقل الشرط أولاً</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-icon btn-light-danger js-remove-condition d-none" title="حذف الشرط">
                    <i class="ki-duotone ki-trash fs-3"></i>
                </button>
            </div>
        </div>
    </div>
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
