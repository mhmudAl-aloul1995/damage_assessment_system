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

        .dashboard-card-admin .condition-box {
            border: 1px dashed var(--admin-border);
            border-radius: 12px;
            background: #fbfdff;
            padding: 16px;
        }

        .dashboard-card-admin .condition-row + .condition-row {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--admin-border);
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

        .dashboard-card-admin .icon-preview-box-sm {
            width: 30px;
            height: 30px;
        }

        .dashboard-card-admin .icon-select2-option {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: 100%;
        }

        .dashboard-card-admin .icon-select2-text {
            direction: ltr;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dashboard-card-admin .select2-container {
            width: 100% !important;
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
                                        <select name="source_bucket" class="form-select ltr-input js-dashboard-select2" data-control="select2" required>
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
                                    @php
                                        $itemConditions = collect($item->options['conditions'] ?? [])
                                            ->filter(fn ($condition) => is_array($condition) && ! empty($condition['field']))
                                            ->values();

                                        if ($itemConditions->isEmpty() && $item->filter_field) {
                                            $itemConditions = collect([[
                                                'field' => $item->filter_field,
                                                'operator' => $item->filter_operator ?: '=',
                                                'value' => $item->filter_value,
                                            ]]);
                                        }

                                        $itemConditions = $itemConditions
                                            ->map(function ($condition) {
                                                $values = $condition['value'] ?? [];
                                                $values = is_array($values) ? $values : [$values];

                                                return [
                                                    ...$condition,
                                                    'value' => collect($values)
                                                        ->filter(fn ($value) => $value !== null && $value !== '')
                                                        ->values()
                                                        ->all(),
                                                ];
                                            });
                                    @endphp
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
                                                        {{ $itemConditions->map(fn ($condition) => trim(($condition['field'] ?? '') . ' ' . ($condition['operator'] ?? '=') . ' ' . collect($condition['value'] ?? [])->map(fn ($value) => $value === '__NULL__' ? 'فارغ' : $value)->implode(', ')))->implode(' + ') ?: 'بدون شروط' }}
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
                                                        <select name="calculation_type" class="form-select js-dashboard-select2 js-calculation-type" data-control="select2" data-hide-search="true" required>
                                                            <option value="stat_key" @selected(old('calculation_type', $item->calculation_type) === 'stat_key')>إحصائية جاهزة</option>
                                                            <option value="count_condition" @selected(old('calculation_type', $item->calculation_type) === 'count_condition')>عدّ حسب الشرط</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">مصدر العدّ</label>
                                                        <select name="source_bucket" class="form-select ltr-input js-dashboard-select2 js-source-bucket" data-control="select2" required>
                                                            @foreach ($sourceBuckets as $sourceBucket)
                                                                <option value="{{ $sourceBucket }}" @selected(old('source_bucket', $item->source_bucket) === $sourceBucket)>{{ $sourceBucket }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4 js-stat-key-group {{ old('calculation_type', $item->calculation_type) === 'count_condition' ? 'd-none' : '' }}">
                                                        <label class="form-label">مفتاح الإحصائية</label>
                                                        <select name="stat_key" class="form-select ltr-input js-dashboard-select2 js-stat-key" data-control="select2" data-selected="{{ old('stat_key', $item->stat_key) }}" required>
                                                            @foreach ($statKeys as $sourceBucket => $keys)
                                                                @foreach ($keys as $key)
                                                                    <option value="{{ $key }}" data-source-bucket="{{ $sourceBucket }}" @selected(old('stat_key', $item->stat_key) === $key)>{{ $key }}</option>
                                                                @endforeach
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="condition-box js-condition-list" data-next-index="{{ max(1, $itemConditions->count()) }}">
                                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                                <label class="form-label mb-0">الشروط</label>
                                                                <button type="button" class="btn btn-sm btn-light-primary js-add-condition">
                                                                    <i class="ki-duotone ki-plus fs-3"></i>
                                                                    إضافة شرط
                                                                </button>
                                                            </div>

                                                            @forelse ($itemConditions as $conditionIndex => $condition)
                                                                <div class="row g-3 align-items-end js-condition-row condition-row">
                                                                    <div class="col-md-4">
                                                                        <label class="form-label">حقل الشرط</label>
                                                                        <select name="conditions[{{ $conditionIndex }}][field]" class="form-select ltr-input js-dashboard-select2 js-filter-field" data-control="select2" data-selected="{{ $condition['field'] ?? '' }}">
                                                                            <option value="">بدون شرط</option>
                                                                            @foreach ($filterFields as $sourceBucket => $fields)
                                                                                @foreach ($fields as $field)
                                                                                    <option value="{{ $field }}" data-source-bucket="{{ $sourceBucket }}" @selected(($condition['field'] ?? '') === $field)>{{ $field }}</option>
                                                                                @endforeach
                                                                            @endforeach
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-3">
                                                                        <label class="form-label">عامل الشرط</label>
                                                                        <select name="conditions[{{ $conditionIndex }}][operator]" class="form-select js-dashboard-select2 js-filter-operator" data-control="select2" data-hide-search="true">
                                                                            @foreach ($operators as $operator)
                                                                                <option value="{{ $operator }}" @selected(($condition['operator'] ?? '=') === $operator)>{{ $operator }}</option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <label class="form-label">قيمة الشرط</label>
                                                                        <select name="conditions[{{ $conditionIndex }}][value][]" class="form-select ltr-input js-dashboard-select2 js-filter-value" data-control="select2" data-selected-values='@json($condition['value'] ?? [])' multiple>
                                                                            <option value="__NULL__" @selected(in_array('__NULL__', $condition['value'] ?? [], true))>فارغ</option>
                                                                            @foreach ($condition['value'] ?? [] as $conditionValue)
                                                                                @continue($conditionValue === '__NULL__')
                                                                                <option value="{{ $conditionValue }}" selected>{{ $conditionValue }}</option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-1">
                                                                        <button type="button" class="btn btn-icon btn-light-danger js-remove-condition {{ $itemConditions->count() <= 1 ? 'd-none' : '' }}" title="حذف الشرط">
                                                                            <i class="ki-duotone ki-trash fs-3"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            @empty
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
                                                                        <select name="conditions[0][operator]" class="form-select js-dashboard-select2 js-filter-operator" data-control="select2" data-hide-search="true">
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
                                                            @endforelse
                                                        </div>
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

@endsection

@section('script')
    <template id="dashboard_condition_row_template">
        <div class="row g-3 align-items-end js-condition-row condition-row">
            <div class="col-md-4">
                <label class="form-label">حقل الشرط</label>
                <select data-name="field" class="form-select ltr-input js-dashboard-select2 js-filter-field" data-control="select2">
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
                <select data-name="operator" class="form-select js-dashboard-select2 js-filter-operator" data-control="select2" data-hide-search="true">
                    @foreach ($operators as $operator)
                        <option value="{{ $operator }}" @selected($operator === '=')>{{ $operator }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">قيمة الشرط</label>
                <select data-name="value" class="form-select ltr-input js-dashboard-select2 js-filter-value" data-control="select2" multiple>
                    <option value="__NULL__">فارغ</option>
                    <option value="">اختر حقل الشرط أولاً</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-icon btn-light-danger js-remove-condition" title="حذف الشرط">
                    <i class="ki-duotone ki-trash fs-3"></i>
                </button>
            </div>
        </div>
    </template>

    <script>
        const filterValuesUrl = @json(route('admin.dashboard-cards.filter-values'));
        const dashboardCardsDirection = @json(app()->getLocale() === 'ar' ? 'rtl' : 'ltr');
        const iconPathMarkup = '<span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span>';
        const sourceOptionsCache = new WeakMap();

        const renderIconSelect2Option = (option) => {
            if (!option.id || !option.element?.closest('.js-icon-select')) {
                return option.text;
            }

            const wrapper = $('<span class="icon-select2-option"></span>');
            const preview = $('<span class="icon-preview-box icon-preview-box-sm"></span>');
            const icon = $('<i class="ki-duotone fs-3"></i>').addClass(option.id).html(iconPathMarkup);
            const text = $('<span class="icon-select2-text"></span>').text(option.text);

            preview.append(icon);
            wrapper.append(preview, text);

            return wrapper;
        };

        const dashboardSelect2Options = (select) => ({
            dir: dashboardCardsDirection,
            width: '100%',
            minimumResultsForSearch: $(select).data('hide-search') ? Infinity : 0,
            templateResult: renderIconSelect2Option,
            templateSelection: renderIconSelect2Option,
        });

        const initializeDashboardSelect2Element = (select) => {
            if (!window.jQuery || !$.fn.select2) {
                return;
            }

            const selectElement = $(select);

            if (selectElement.hasClass('select2-hidden-accessible')) {
                return;
            }

            selectElement.select2(dashboardSelect2Options(select));
        };

        const refreshDashboardSelect2Element = (select) => {
            if (!window.jQuery || !$.fn.select2 || !select) {
                return;
            }

            const selectElement = $(select);

            if (selectElement.hasClass('select2-hidden-accessible')) {
                selectElement.select2('destroy');
            }

            initializeDashboardSelect2Element(select);
        };

        const initializeDashboardSelect2 = () => {
            $('.dashboard-card-admin .js-dashboard-select2').each(function() {
                initializeDashboardSelect2Element(this);
            });
        };

        document.querySelectorAll('.dashboard-card-admin form').forEach((form) => {
            const sourceBucket = form.querySelector('.js-source-bucket');
            const conditionList = form.querySelector('.js-condition-list');
            const statKey = form.querySelector('.js-stat-key');
            const statKeyGroup = form.querySelector('.js-stat-key-group');
            const calculationType = form.querySelector('.js-calculation-type');
            let select2Ready = false;

            if (!sourceBucket) {
                return;
            }

            const syncSourceOptions = (select) => {
                if (!select) {
                    return;
                }

                if (!sourceOptionsCache.has(select)) {
                    sourceOptionsCache.set(select, Array.from(select.options).map((option) => option.cloneNode(true)));
                }

                const selectedSource = sourceBucket.value;
                const selectedValue = select.dataset.selected || select.value;
                const availableOptions = sourceOptionsCache.get(select)
                    .filter((option) => !option.dataset.sourceBucket || option.dataset.sourceBucket === selectedSource)
                    .map((option) => option.cloneNode(true));

                select.replaceChildren(...availableOptions);

                const selectedValueStillAvailable = Array.from(select.options)
                    .some((option) => option.value === selectedValue);

                if (selectedValueStillAvailable) {
                    select.value = selectedValue;
                } else {
                    const firstAvailableOption = Array.from(select.options).find((option) => option.value !== '');
                    select.value = select.required && firstAvailableOption ? firstAvailableOption.value : '';
                }

                select.dataset.selected = select.value;
                refreshDashboardSelect2Element(select);
            };

            const conditionRows = () => Array.from(form.querySelectorAll('.js-condition-row'));

            const selectedFilterValues = (filterValue) => {
                if (!filterValue) {
                    return [];
                }

                if (filterValue.dataset.selectedValues) {
                    try {
                        return JSON.parse(filterValue.dataset.selectedValues);
                    } catch (error) {
                        return [];
                    }
                }

                return $(filterValue).val() || [];
            };

            const updateRemoveButtons = () => {
                const rows = conditionRows();

                rows.forEach((row) => {
                    row.querySelector('.js-remove-condition')?.classList.toggle('d-none', rows.length <= 1);
                });
            };

            const syncForm = () => {
                const isCountByCondition = calculationType?.value === 'count_condition';

                if (statKeyGroup) {
                    statKeyGroup.classList.toggle('d-none', isCountByCondition);
                    statKeyGroup.style.display = isCountByCondition ? 'none' : '';
                }

                if (statKey) {
                    statKey.required = !isCountByCondition;
                    statKey.disabled = isCountByCondition;

                    const statKeySelect = $(statKey);

                    if (select2Ready && statKeySelect.hasClass('select2-hidden-accessible')) {
                        statKeySelect.select2('close');
                        statKeySelect.next('.select2-container').toggleClass('d-none', isCountByCondition).toggle(!isCountByCondition);
                    }
                }

                if (!isCountByCondition) {
                    syncSourceOptions(statKey);
                }

                conditionRows().forEach((row) => {
                    const filterField = row.querySelector('.js-filter-field');
                    const filterValue = row.querySelector('.js-filter-value');

                    syncSourceOptions(filterField);
                    syncFilterValues(filterField, filterValue);
                });

                updateRemoveButtons();
            };

            const syncFilterValues = async (filterField, filterValue) => {
                if (!filterField || !filterValue || !filterField.value) {
                    if (filterValue) {
                        filterValue.innerHTML = '<option value="__NULL__">فارغ</option><option value="">اختر حقل الشرط أولاً</option>';
                        filterValue.dataset.selectedValues = '[]';
                        $(filterValue).val([]);
                        refreshDashboardSelect2Element(filterValue);
                    }

                    return;
                }

                const selectedValues = selectedFilterValues(filterValue);
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

                    filterValue.innerHTML = '';
                    const nullOption = document.createElement('option');
                    nullOption.value = '__NULL__';
                    nullOption.textContent = 'فارغ';
                    filterValue.appendChild(nullOption);

                    if (values.length === 0) {
                        const emptyOption = document.createElement('option');
                        emptyOption.value = '';
                        emptyOption.textContent = 'لا توجد قيم محفوظة لهذا الحقل';
                        filterValue.appendChild(emptyOption);
                    } else {
                        values.forEach((value) => {
                            const option = document.createElement('option');
                            option.value = value;
                            option.textContent = value;
                            filterValue.appendChild(option);
                        });
                    }

                    selectedValues
                        .filter((selectedValue) => selectedValue && !values.includes(selectedValue) && selectedValue !== '__NULL__')
                        .forEach((selectedValue) => {
                        const option = document.createElement('option');
                        option.value = selectedValue;
                        option.textContent = selectedValue;
                        filterValue.appendChild(option);
                    });

                    const availableValues = Array.from(filterValue.options).map((option) => option.value);
                    const nextSelectedValues = selectedValues.filter((selectedValue) => availableValues.includes(selectedValue));
                    $(filterValue).val(nextSelectedValues);
                    filterValue.dataset.selectedValues = JSON.stringify(nextSelectedValues);
                    refreshDashboardSelect2Element(filterValue);
                } catch (error) {
                    filterValue.innerHTML = '<option value="__NULL__">فارغ</option><option value="">تعذر تحميل القيم</option>';
                    filterValue.dataset.selectedValues = '[]';
                    $(filterValue).val([]);
                    refreshDashboardSelect2Element(filterValue);
                } finally {
                    filterValue.disabled = false;
                }
            };

            const prepareConditionRow = (row, index) => {
                row.querySelectorAll('[data-name]').forEach((input) => {
                    input.name = input.multiple
                        ? `conditions[${index}][${input.dataset.name}][]`
                        : `conditions[${index}][${input.dataset.name}]`;
                });
            };

            const addConditionRow = () => {
                if (!conditionList) {
                    return;
                }

                const template = document.getElementById('dashboard_condition_row_template');
                const row = template.content.firstElementChild.cloneNode(true);
                const index = Number(conditionList.dataset.nextIndex || conditionRows().length);
                conditionList.dataset.nextIndex = String(index + 1);

                prepareConditionRow(row, index);
                conditionList.appendChild(row);
                row.querySelectorAll('.js-dashboard-select2').forEach((select) => {
                    initializeDashboardSelect2Element(select);
                });
                syncForm();
            };

            calculationType?.addEventListener('change', syncForm);
            $(calculationType).on('change select2:select', syncForm);
            sourceBucket.addEventListener('change', syncForm);
            $(sourceBucket).on('change select2:select', syncForm);
            [statKey].forEach((select) => {
                select?.addEventListener('change', () => {
                    select.dataset.selected = select.value;
                });
                $(select).on('change select2:select', () => {
                    select.dataset.selected = select.value;
                });
            });

            form.addEventListener('click', (event) => {
                if (event.target.closest('.js-add-condition')) {
                    addConditionRow();
                    return;
                }

                const removeButton = event.target.closest('.js-remove-condition');

                if (removeButton) {
                    const row = removeButton.closest('.js-condition-row');

                    row?.querySelectorAll('.js-dashboard-select2.select2-hidden-accessible').forEach((select) => {
                        $(select).select2('destroy');
                    });

                    row?.remove();
                    updateRemoveButtons();
                }
            });

            form.addEventListener('change', (event) => {
                const filterField = event.target.closest('.js-filter-field');
                const filterValue = event.target.closest('.js-filter-value');

                if (filterField) {
                    const row = filterField.closest('.js-condition-row');
                    const rowFilterValue = row?.querySelector('.js-filter-value');
                    filterField.dataset.selected = filterField.value;

                    if (rowFilterValue) {
                        rowFilterValue.dataset.selected = '';
                        syncFilterValues(filterField, rowFilterValue);
                    }
                }

                if (filterValue) {
                    filterValue.dataset.selectedValues = JSON.stringify($(filterValue).val() || []);
                }
            });

            $(form).on('select2:select select2:clear', '.js-filter-field', function() {
                const row = this.closest('.js-condition-row');
                const filterValue = row?.querySelector('.js-filter-value');

                this.dataset.selected = this.value;

                if (filterValue) {
                    filterValue.dataset.selected = '';
                    syncFilterValues(this, filterValue);
                }
            });

            $(form).on('select2:select select2:clear', '.js-filter-value', function() {
                this.dataset.selectedValues = JSON.stringify($(this).val() || []);
            });
            syncForm();

            form.addEventListener('dashboard-select2-ready', () => {
                select2Ready = true;
                syncForm();
            });
        });

        initializeDashboardSelect2();
        document.querySelectorAll('.dashboard-card-admin form').forEach((form) => {
            form.dispatchEvent(new Event('dashboard-select2-ready'));
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
