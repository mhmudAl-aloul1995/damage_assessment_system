@extends('layouts.app')
@section('title', 'إدارة بطاقات لوحة التحكم')
@section('pageName', 'إدارة بطاقات لوحة التحكم')

@section('content')
    <style>
        .dashboard-card-admin {
            --admin-border: #e7edf5;
            --admin-muted: #7e8aa2;
            --admin-soft: #f6f9fc;
        }

        .dashboard-card-admin .toolbar-panel,
        .dashboard-card-admin .work-panel {
            border: 1px solid var(--admin-border);
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(33, 52, 84, .06);
        }

        .dashboard-card-admin .card-picker {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }

        .dashboard-card-admin .picker-card {
            display: block;
            min-height: 112px;
            border: 1px solid var(--admin-border);
            border-radius: 12px;
            padding: 14px;
            color: inherit;
            background: #fff;
            transition: .18s ease;
        }

        .dashboard-card-admin .picker-card:hover,
        .dashboard-card-admin .picker-card.is-active {
            border-color: var(--card-color);
            box-shadow: 0 12px 24px rgba(33, 52, 84, .08);
            transform: translateY(-1px);
        }

        .dashboard-card-admin .color-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--card-color);
            flex: 0 0 12px;
        }

        .dashboard-card-admin .muted-label {
            color: var(--admin-muted);
            font-size: 12px;
        }

        .dashboard-card-admin .item-row {
            border: 1px solid var(--admin-border);
            border-radius: 12px;
            background: #fff;
            overflow: hidden;
        }

        .dashboard-card-admin .item-summary {
            display: grid;
            grid-template-columns: 42px minmax(190px, 1fr) minmax(150px, .7fr) minmax(130px, .6fr) auto;
            gap: 12px;
            align-items: center;
            padding: 14px 16px;
            background: var(--admin-soft);
        }

        .dashboard-card-admin .item-details {
            padding: 16px;
        }

        .dashboard-card-admin .code-pill {
            display: inline-flex;
            max-width: 100%;
            padding: 5px 8px;
            border-radius: 8px;
            background: #eef3f8;
            color: #526174;
            font-size: 12px;
            direction: ltr;
            overflow-wrap: anywhere;
        }

        .dashboard-card-admin .soft-box {
            border: 1px dashed var(--admin-border);
            border-radius: 12px;
            background: #fbfdff;
            padding: 18px;
        }

        .dashboard-card-admin .settings-form {
            display: grid;
            gap: 16px;
        }

        .dashboard-card-admin .settings-pair {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .dashboard-card-admin .ltr-input,
        .dashboard-card-admin .code-pill {
            direction: ltr;
            text-align: left;
        }

        .dashboard-card-admin .color-control {
            height: 44px;
            min-width: 100%;
        }

        .dashboard-card-admin .icon-picker {
            display: grid;
            grid-template-columns: 44px minmax(0, 1fr);
            gap: 8px;
            align-items: center;
        }

        .dashboard-card-admin .icon-preview-box {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border: 1px solid var(--admin-border);
            border-radius: 8px;
            background: var(--admin-soft);
            color: #3e97ff;
        }

        .dashboard-card-admin .advanced-toggle {
            border: 0;
            background: transparent;
            color: #3e97ff;
            padding: 0;
        }

        @media (max-width: 991.98px) {
            .dashboard-card-admin .item-summary {
                grid-template-columns: 36px 1fr auto;
            }

            .dashboard-card-admin .item-summary .hide-mobile {
                display: none;
            }

            .dashboard-card-admin .settings-pair {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="dashboard-card-admin">
        <div class="card card-flush toolbar-panel mb-7">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-4 mb-6">
                    <div>
                        <div class="muted-label mb-2">لوحة التحكم</div>
                        <h2 class="fw-bold mb-2">إدارة البطاقات والبنود</h2>
                        <div class="text-gray-600">اختر بطاقة للتعديل، ثم رتّب البنود والشروط والروابط من مكان واحد.</div>
                    </div>
                    <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#create_dashboard_card">
                        <i class="ki-duotone ki-plus fs-2"></i>
                        بطاقة جديدة
                    </button>
                </div>

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <div class="collapse mb-7" id="create_dashboard_card">
                    <div class="soft-box">
                        <form method="POST" action="{{ route('admin.dashboard-cards.store') }}" class="row g-4 align-items-end">
                            @csrf
                            @include('admin.dashboard-cards.partials.card-fields', ['card' => null])
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">إضافة</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-picker">
                    @foreach ($cards as $card)
                        <a href="{{ route('admin.dashboard-cards.index', ['card' => $card->id]) }}"
                            class="picker-card text-decoration-none {{ $selectedCard?->is($card) ? 'is-active' : '' }}"
                            style="--card-color: {{ $card->color }};">
                            <div class="d-flex align-items-center gap-2 mb-4">
                                <span class="color-dot"></span>
                                <span class="fw-bold text-gray-900">{{ __($card->title) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-end">
                                <div>
                                    <div class="muted-label">عدد البنود</div>
                                    <div class="fs-2 fw-bold text-gray-900">{{ $card->items->count() }}</div>
                                </div>
                                @unless ($card->is_active)
                                    <span class="badge badge-light-warning">مخفية</span>
                                @else
                                    <span class="badge badge-light-success">مفعلة</span>
                                @endunless
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($selectedCard)
            <div class="row g-7">
                <div class="col-xl-4">
                    <div class="card card-flush work-panel">
                        <div class="card-header pt-7">
                            <div class="card-title d-flex align-items-center gap-3">
                                <span class="color-dot" style="--card-color: {{ $selectedCard->color }};"></span>
                                <h3 class="fw-bold mb-0">إعدادات البطاقة</h3>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('admin.dashboard-cards.update', $selectedCard) }}" class="settings-form">
                                @csrf
                                @method('PUT')

                                <div>
                                    <label class="form-label">العنوان</label>
                                    <input type="text" name="title" class="form-control" value="{{ old('title', __($selectedCard->title)) }}" required>
                                </div>

                                <div>
                                    <label class="form-label">النص الفرعي</label>
                                    <input type="text" name="subtitle" class="form-control" value="{{ old('subtitle', $selectedCard->subtitle ? __($selectedCard->subtitle) : null) }}">
                                </div>

                                <div class="settings-pair">
                                    <div>
                                        <label class="form-label">المفتاح</label>
                                        <input type="text" name="key" class="form-control ltr-input" value="{{ old('key', $selectedCard->key) }}" required>
                                    </div>
                                    <div>
                                        <label class="form-label">مصدر البيانات</label>
                                        <select name="source_bucket" class="form-select ltr-input" required>
                                            @foreach ($sourceBuckets as $sourceBucket)
                                                <option value="{{ $sourceBucket }}" @selected(old('source_bucket', $selectedCard->source_bucket) === $sourceBucket)>{{ $sourceBucket }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="settings-pair">
                                    <div>
                                        <label class="form-label">مفتاح الإجمالي</label>
                                        <input type="text" name="total_stat_key" class="form-control ltr-input" value="{{ old('total_stat_key', $selectedCard->total_stat_key) }}" required>
                                    </div>
                                    <div>
                                        <label class="form-label">الأيقونة</label>
                                        @include('admin.dashboard-cards.partials.icon-select', [
                                            'name' => 'icon',
                                            'selectedIcon' => $selectedCard->icon,
                                        ])
                                    </div>
                                </div>

                                <div class="settings-pair">
                                    <div>
                                        <label class="form-label">اللون</label>
                                        <input type="color" name="color" class="form-control form-control-color color-control" value="{{ old('color', $selectedCard->color) }}" required>
                                    </div>
                                    <div>
                                        <label class="form-label">الترتيب</label>
                                        <input type="number" name="sort_order" class="form-control" min="0" value="{{ old('sort_order', $selectedCard->sort_order) }}">
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center rounded bg-light px-4 py-3">
                                    <span class="fw-semibold text-gray-700">حالة البطاقة</span>
                                    <label class="form-check form-switch form-check-custom form-check-solid mb-0">
                                        <input type="hidden" name="is_active" value="0">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $selectedCard->is_active))>
                                        <span class="form-check-label">مفعلة</span>
                                    </label>
                                </div>

                                <div>
                                    <button type="submit" class="btn btn-primary w-100">حفظ إعدادات البطاقة</button>
                                </div>
                            </form>

                            <div class="separator my-7"></div>

                            <form method="POST" action="{{ route('admin.dashboard-cards.destroy', $selectedCard) }}"
                                onsubmit="return confirm('حذف البطاقة سيحذف كل بنودها. هل أنت متأكد؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-light-danger w-100">حذف البطاقة</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-xl-8">
                    <div class="card card-flush work-panel">
                        <div class="card-header pt-7">
                            <div class="card-title">
                                <div>
                                    <h3 class="fw-bold mb-1">بنود {{ __($selectedCard->title) }}</h3>
                                    <div class="text-gray-600 fs-7">كل بند يعرض إحصائية عددية من مصدر العدّ المختار، مع شرط ورابط اختياري.</div>
                                </div>
                            </div>
                            <div class="card-toolbar">
                                <button class="btn btn-light-primary" type="button" data-bs-toggle="collapse" data-bs-target="#create_dashboard_card_item">
                                    <i class="ki-duotone ki-plus fs-2"></i>
                                    بند جديد
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="collapse mb-6" id="create_dashboard_card_item">
                                <div class="soft-box">
                                    <form method="POST" action="{{ route('admin.dashboard-cards.items.store', $selectedCard) }}" class="row g-3 align-items-end">
                                        @csrf
                                        @include('admin.dashboard-cards.partials.item-create-fields')
                                        <div class="col-12 d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary px-10">إضافة البند</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="d-flex flex-column gap-4">
                                @forelse ($selectedCard->items as $item)
                                    <div class="item-row">
                                        <div class="item-summary">
                                            <div class="fw-bold text-gray-700 text-center">#{{ $item->sort_order }}</div>
                                            <div>
                                                <div class="fw-bold text-gray-900 mb-1">{{ __($item->title) }}</div>
                                                @if ($item->calculation_type === 'count_condition')
                                                    <span class="badge badge-light-primary">عدّ حسب الشرط</span>
                                                @else
                                                    <span class="badge badge-light-info">إحصائية جاهزة</span>
                                                @endif
                                            </div>
                                            <div class="hide-mobile">
                                                <div class="muted-label mb-1">مصدر العدّ</div>
                                                <span class="code-pill">
                                                    @if ($item->calculation_type === 'count_condition')
                                                        {{ $item->filter_field }} {{ $item->filter_operator }} {{ $item->filter_value }}
                                                    @else
                                                        {{ $item->source_bucket }}.{{ $item->stat_key }}
                                                    @endif
                                                </span>
                                            </div>
                                            <div class="hide-mobile">
                                                <div class="muted-label mb-1">الحالة</div>
                                                @if ($item->is_active)
                                                    <span class="badge badge-light-success">مفعل</span>
                                                @else
                                                    <span class="badge badge-light-warning">مخفي</span>
                                                @endif
                                            </div>
                                            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#dashboard_item_{{ $item->id }}">
                                                تعديل
                                            </button>
                                        </div>

                                        <div class="collapse" id="dashboard_item_{{ $item->id }}">
                                            <div class="item-details">
                                                <form method="POST" action="{{ route('admin.dashboard-cards.items.update', [$selectedCard, $item]) }}" class="row g-4 align-items-end">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="source_model" value="{{ $item->source_model }}">

                                                    <div class="col-md-8">
                                                        <label class="form-label">العنوان</label>
                                                        <input type="text" name="title" class="form-control" value="{{ old('title', __($item->title)) }}" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">الترتيب</label>
                                                        <input type="number" name="sort_order" class="form-control" min="0" value="{{ old('sort_order', $item->sort_order) }}">
                                                    </div>

                                                    <div class="col-md-4">
                                                        <label class="form-label">طريقة العدّ</label>
                                                        <select name="calculation_type" class="form-select js-calculation-type" required>
                                                            <option value="stat_key" @selected(old('calculation_type', $item->calculation_type) === 'stat_key')>إحصائية جاهزة</option>
                                                            <option value="count_condition" @selected(old('calculation_type', $item->calculation_type) === 'count_condition')>عدّ حسب الشرط</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">مصدر العدّ</label>
                                                        <select name="source_bucket" class="form-select ltr-input js-source-bucket" required>
                                                            @foreach ($sourceBuckets as $sourceBucket)
                                                                <option value="{{ $sourceBucket }}" @selected(old('source_bucket', $item->source_bucket) === $sourceBucket)>{{ $sourceBucket }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4 js-stat-key-group">
                                                        <label class="form-label">مفتاح الإحصائية</label>
                                                        <select name="stat_key" class="form-select ltr-input js-stat-key" data-selected="{{ old('stat_key', $item->stat_key) }}" required>
                                                            @foreach ($statKeys as $sourceBucket => $keys)
                                                                @foreach ($keys as $key)
                                                                    <option value="{{ $key }}" data-source-bucket="{{ $sourceBucket }}" @selected(old('stat_key', $item->stat_key) === $key)>{{ $key }}</option>
                                                                @endforeach
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">حقل الشرط</label>
                                                        <select name="filter_field" class="form-select ltr-input js-filter-field" data-selected="{{ old('filter_field', $item->filter_field) }}">
                                                            <option value="">بدون شرط</option>
                                                            @foreach ($filterFields as $sourceBucket => $fields)
                                                                @foreach ($fields as $field)
                                                                    <option value="{{ $field }}" data-source-bucket="{{ $sourceBucket }}" @selected(old('filter_field', $item->filter_field) === $field)>{{ $field }}</option>
                                                                @endforeach
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">عامل الشرط</label>
                                                        <select name="filter_operator" class="form-select">
                                                            <option value="">بدون</option>
                                                            @foreach ($operators as $operator)
                                                                <option value="{{ $operator }}" @selected(old('filter_operator', $item->filter_operator) === $operator)>{{ $operator }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">قيمة الشرط</label>
                                                        <select name="filter_value" class="form-select ltr-input js-filter-value" data-selected="{{ old('filter_value', $item->filter_value) }}">
                                                            <option value="{{ old('filter_value', $item->filter_value) }}">{{ old('filter_value', $item->filter_value) ?: 'اختر حقل الشرط أولاً' }}</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4">
                                                        <input type="hidden" name="is_active" value="0">
                                                        <label class="form-check form-switch form-check-custom form-check-solid mt-8">
                                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active))>
                                                            <span class="form-check-label">مفعل</span>
                                                        </label>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <button type="submit" class="btn btn-primary w-100">حفظ البند</button>
                                                    </div>

                                                    <div class="col-12">
                                                        <button class="advanced-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#dashboard_item_advanced_{{ $item->id }}">
                                                            إعدادات متقدمة
                                                        </button>
                                                    </div>

                                                    <div class="collapse col-12" id="dashboard_item_advanced_{{ $item->id }}">
                                                        <div class="soft-box">
                                                            <div class="row g-4">
                                                                <div class="col-md-4">
                                                                    <label class="form-label">المفتاح الداخلي</label>
                                                                    <input type="text" name="key" class="form-control ltr-input" value="{{ old('key', $item->key) }}" required>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">الأيقونة</label>
                                                                    @include('admin.dashboard-cards.partials.icon-select', [
                                                                        'name' => 'icon',
                                                                        'selectedIcon' => $item->icon,
                                                                    ])
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">مجموعة الرابط</label>
                                                                    <input type="text" name="link_group" class="form-control ltr-input" value="{{ old('link_group', $item->link_group) }}" placeholder="group">
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">مفتاح الرابط</label>
                                                                    <input type="text" name="link_key" class="form-control ltr-input" value="{{ old('link_key', $item->link_key) }}" placeholder="key">
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">لاحقة</label>
                                                                    <input type="text" name="value_suffix" class="form-control" value="{{ old('value_suffix', $item->value_suffix) }}">
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">كسور</label>
                                                                    <input type="number" name="decimal_places" class="form-control" min="0" max="6" value="{{ old('decimal_places', $item->decimal_places) }}">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </form>

                                                <form method="POST" action="{{ route('admin.dashboard-cards.items.destroy', [$selectedCard, $item]) }}"
                                                    class="mt-3" onsubmit="return confirm('هل تريد حذف هذا البند؟')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-light-danger">حذف البند</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="alert alert-info mb-0">لا توجد بنود لهذه البطاقة بعد.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="alert alert-info">لا توجد بطاقات بعد. أضف البطاقة الأولى للبدء.</div>
        @endif
    </div>

    <script>
        const filterValuesUrl = @json(route('admin.dashboard-cards.filter-values'));

        document.querySelectorAll('.dashboard-card-admin form').forEach((form) => {
            const sourceBucket = form.querySelector('.js-source-bucket');
            const filterField = form.querySelector('.js-filter-field');
            const filterValue = form.querySelector('.js-filter-value');
            const statKey = form.querySelector('.js-stat-key');
            const statKeyGroup = form.querySelector('.js-stat-key-group');
            const calculationType = form.querySelector('.js-calculation-type');

            if (!sourceBucket) {
                return;
            }

            const syncSourceOptions = (select) => {
                if (!select) {
                    return;
                }

                const selectedSource = sourceBucket.value;
                const selectedValue = select.dataset.selected || select.value;
                let selectedValueStillAvailable = false;

                select.querySelectorAll('option').forEach((option) => {
                    if (!option.dataset.sourceBucket) {
                        option.hidden = false;
                        option.disabled = false;
                        return;
                    }

                    const isSameSource = option.dataset.sourceBucket === selectedSource;
                    option.hidden = !isSameSource;
                    option.disabled = !isSameSource;

                    if (isSameSource && option.value === selectedValue) {
                        selectedValueStillAvailable = true;
                    }
                });

                if (selectedValueStillAvailable) {
                    select.value = selectedValue;
                } else {
                    const firstAvailableOption = Array.from(select.options).find((option) => !option.disabled && option.value !== '');
                    select.value = select.required && firstAvailableOption ? firstAvailableOption.value : '';
                }

                select.dataset.selected = select.value;
            };

            const syncForm = () => {
                const isCountByCondition = calculationType?.value === 'count_condition';

                statKeyGroup?.classList.toggle('d-none', isCountByCondition);

                if (statKey) {
                    statKey.required = !isCountByCondition;
                }

                syncSourceOptions(statKey);
                syncSourceOptions(filterField);
                syncFilterValues();
            };

            const syncFilterValues = async () => {
                if (!filterField || !filterValue || !filterField.value) {
                    if (filterValue) {
                        filterValue.innerHTML = '<option value="">بدون قيمة</option>';
                        filterValue.value = '';
                        filterValue.dataset.selected = '';
                    }

                    return;
                }

                const selectedValue = filterValue.dataset.selected || filterValue.value;
                const url = new URL(filterValuesUrl, window.location.origin);
                url.searchParams.set('source_bucket', sourceBucket.value);
                url.searchParams.set('field', filterField.value);

                filterValue.disabled = true;
                filterValue.innerHTML = '<option value="">جاري التحميل...</option>';

                try {
                    const response = await fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    const payload = await response.json();
                    const values = Array.isArray(payload.values) ? payload.values : [];

                    filterValue.innerHTML = '<option value="">بدون قيمة</option>';
                    values.forEach((value) => {
                        const option = document.createElement('option');
                        option.value = value;
                        option.textContent = value;
                        filterValue.appendChild(option);
                    });

                    if (selectedValue && !values.includes(selectedValue)) {
                        const option = document.createElement('option');
                        option.value = selectedValue;
                        option.textContent = selectedValue;
                        filterValue.appendChild(option);
                    }

                    filterValue.value = selectedValue && Array.from(filterValue.options).some((option) => option.value === selectedValue)
                        ? selectedValue
                        : '';
                    filterValue.dataset.selected = filterValue.value;
                } catch (error) {
                    filterValue.innerHTML = '<option value="">تعذر تحميل القيم</option>';
                    filterValue.value = '';
                    filterValue.dataset.selected = '';
                } finally {
                    filterValue.disabled = false;
                }
            };

            calculationType?.addEventListener('change', syncForm);
            sourceBucket.addEventListener('change', syncForm);
            [statKey, filterField].forEach((select) => {
                select?.addEventListener('change', () => {
                    select.dataset.selected = select.value;
                    if (select === filterField && filterValue) {
                        filterValue.dataset.selected = '';
                        syncFilterValues();
                    }
                });
            });
            filterValue?.addEventListener('change', () => {
                filterValue.dataset.selected = filterValue.value;
            });
            syncForm();
        });

        document.querySelectorAll('.js-icon-picker').forEach((picker) => {
            const select = picker.querySelector('.js-icon-select');
            const preview = picker.querySelector('.js-icon-preview');

            if (!select || !preview) {
                return;
            }

            const syncIconPreview = () => {
                preview.className = `ki-duotone js-icon-preview ${select.value} fs-2`;
            };

            select.addEventListener('change', syncIconPreview);
            syncIconPreview();
        });
    </script>
@endsection
