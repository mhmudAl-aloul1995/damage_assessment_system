<td>
    <input type="number" name="sort_order" class="form-control form-control-sm" min="0" value="{{ old('sort_order', $item->sort_order) }}">
</td>
<td>
    <input type="text" name="title" class="form-control form-control-sm" value="{{ old('title', __($item->title)) }}" required>
</td>
<td>
    <input type="text" name="key" class="form-control form-control-sm" value="{{ old('key', $item->key) }}" required>
</td>
<td>
    <div class="d-flex gap-2">
        <select name="source_bucket" class="form-select form-select-sm" required>
            @foreach ($sourceBuckets as $sourceBucket)
                <option value="{{ $sourceBucket }}" @selected(old('source_bucket', $item->source_bucket) === $sourceBucket)>{{ $sourceBucket }}</option>
            @endforeach
        </select>
        <input type="text" name="stat_key" class="form-control form-control-sm" value="{{ old('stat_key', $item->stat_key) }}" required>
    </div>
    <input type="hidden" name="calculation_type" value="{{ $item->calculation_type }}">
    <input type="hidden" name="source_model" value="{{ $item->source_model }}">
</td>
<td>
    <div class="d-flex gap-2">
        <input type="text" name="filter_field" class="form-control form-control-sm" value="{{ old('filter_field', $item->filter_field) }}" placeholder="field">
        <select name="filter_operator" class="form-select form-select-sm">
            <option value="">-</option>
            @foreach ($operators as $operator)
                <option value="{{ $operator }}" @selected(old('filter_operator', $item->filter_operator) === $operator)>{{ $operator }}</option>
            @endforeach
        </select>
        <input type="text" name="filter_value" class="form-control form-control-sm" value="{{ old('filter_value', $item->filter_value) }}" placeholder="value">
    </div>
</td>
<td>
    <div class="d-flex gap-2">
        <input type="text" name="link_group" class="form-control form-control-sm" value="{{ old('link_group', $item->link_group) }}" placeholder="group">
        <input type="text" name="link_key" class="form-control form-control-sm" value="{{ old('link_key', $item->link_key) }}" placeholder="key">
    </div>
    <div class="d-flex gap-2 mt-2">
        <input type="text" name="icon" class="form-control form-control-sm" value="{{ old('icon', $item->icon) }}" required>
        <input type="text" name="value_suffix" class="form-control form-control-sm" value="{{ old('value_suffix', $item->value_suffix) }}" placeholder="لاحقة">
        <input type="number" name="decimal_places" class="form-control form-control-sm" min="0" max="6" value="{{ old('decimal_places', $item->decimal_places) }}">
    </div>
</td>
<td>
    <label class="form-check form-switch form-check-custom form-check-solid">
        <input type="hidden" name="is_active" value="0">
        <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active))>
    </label>
</td>
