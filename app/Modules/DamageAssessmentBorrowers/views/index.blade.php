@extends('layouts.app')

@section('title', 'استبيان مقترضي بنك التنمية الإسلامي')
@section('pageName', 'استبيان المقترضين')

@section('content')
    @php
        $riskColors = [
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'primary',
            'low' => 'success',
        ];
    @endphp

    <div class="row g-5 mb-6" id="borrowerStats">
        <div class="col-md-2 col-6">
            <div class="card card-flush h-100">
                <div class="card-body">
                    <div class="text-gray-500 fw-semibold">الإجمالي</div>
                    <div class="fs-2hx fw-bold" data-stat="total">{{ $stats['total'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card card-flush h-100">
                <div class="card-body">
                    <div class="text-gray-500 fw-semibold">حرج</div>
                    <div class="fs-2hx fw-bold text-danger" data-stat="critical">{{ $stats['critical'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card card-flush h-100">
                <div class="card-body">
                    <div class="text-gray-500 fw-semibold">مرتفع</div>
                    <div class="fs-2hx fw-bold text-warning" data-stat="high">{{ $stats['high'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card card-flush h-100">
                <div class="card-body">
                    <div class="text-gray-500 fw-semibold">نازحون</div>
                    <div class="fs-2hx fw-bold text-info" data-stat="displaced">{{ $stats['displaced'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card card-flush h-100">
                <div class="card-body">
                    <div class="text-gray-500 fw-semibold">هدم كلي</div>
                    <div class="fs-2hx fw-bold text-danger" data-stat="destroyed">{{ $stats['destroyed'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card card-flush h-100">
                <div class="card-body">
                    <div class="text-gray-500 fw-semibold">كفلاء غير فعالين</div>
                    <div class="fs-2hx fw-bold text-warning" data-stat="inactive_guarantors">{{ $stats['inactive_guarantors'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-6">
        <div class="col-xl-7">
            <div class="card card-flush">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="fw-bold mb-0">تعبئة استبيان المقترض</h3>
                    </div>
                </div>
                <div class="card-body">
                    <form id="borrowerSurveyForm" class="row g-5" data-offline-sync="true">
                        @csrf

                        <div class="col-md-6">
                            <label class="form-label required">اسم المقترض رباعي</label>
                            <input class="form-control" name="borrower_name" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">رقم الهوية</label>
                            <input class="form-control" name="borrower_id_number">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">رقم الاستمارة</label>
                            <input class="form-control" name="form_number">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">تاريخ ووقت الزيارة</label>
                            <input type="datetime-local" class="form-control" name="surveyed_at">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">عدد أفراد الأسرة</label>
                            <input type="number" min="0" class="form-control" name="family_members_count">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">الحالة الاجتماعية</label>
                            <select class="form-select" name="marital_status">
                                <option value="">اختر</option>
                                <option value="married">متزوج/ة</option>
                                <option value="single">أعزب/عزباء</option>
                                <option value="widowed">أرمل/ة</option>
                                <option value="divorced">مطلق/ة</option>
                                <option value="abandoned">مهجور/ة</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">هل المقترض على قيد الحياة؟</label>
                            <select class="form-select" name="is_borrower_alive" required>
                                <option value="1">نعم</option>
                                <option value="0">لا</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">اسم الزوج/ة</label>
                            <input class="form-control" name="spouse_name">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">هوية الزوج/ة</label>
                            <input class="form-control" name="spouse_id_number">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">الوضع الوظيفي للمقترض</label>
                            <select class="form-select" name="employment_status">
                                <option value="">اختر</option>
                                <option value="working">على رأس عمله</option>
                                <option value="retired">متقاعد</option>
                                <option value="not_working">لا يعمل</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">مؤشرات الهشاشة داخل الأسرة</label>
                            <div class="d-flex flex-wrap gap-4">
                                @foreach([
                                    'martyrs' => 'يوجد شهداء',
                                    'injured' => 'يوجد مصابين',
                                    'disabled' => 'يوجد أشخاص ذوي إعاقة',
                                    'elderly' => 'يوجد كبار سن',
                                    'none' => 'ليس مما سبق',
                                ] as $value => $label)
                                    <label class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="vulnerability_types[]" value="{{ $value }}">
                                        <span class="form-check-label">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="separator my-3"></div>
                        <div class="col-12"><h4 class="fw-bold">الكفلاء</h4></div>

                        <div class="col-md-3">
                            <label class="form-label">عدد الكفلاء</label>
                            <input type="number" min="0" class="form-control" name="guarantors_count">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">هل جميع الكفلاء على قيد الحياة؟</label>
                            <select class="form-select" name="guarantors_alive_status">
                                <option value="">اختر</option>
                                <option value="yes">نعم</option>
                                <option value="no">لا</option>
                                <option value="none">لا يوجد</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الوضع الوظيفي للكفلاء</label>
                            <div class="d-flex flex-wrap gap-4">
                                @foreach([
                                    'all_working' => 'جميعهم على رأس عملهم',
                                    'retired' => 'يوجد كفيل متقاعد',
                                    'lost_job' => 'يوجد كفيل فقد عمله',
                                ] as $value => $label)
                                    <label class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="guarantors_employment_statuses[]" value="{{ $value }}">
                                        <span class="form-check-label">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">أسماء الكفلاء المتوفين</label>
                            <div id="deceasedGuarantorsRepeater" class="d-grid gap-2"></div>
                            <button type="button" class="btn btn-sm btn-light-primary mt-2" data-add-row="deceasedGuarantorsRepeater">إضافة كفيل متوفى</button>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">كفلاء متقاعدين/فاقدين عمل</label>
                            <div id="affectedGuarantorsRepeater" class="d-grid gap-2"></div>
                            <button type="button" class="btn btn-sm btn-light-primary mt-2" data-add-row="affectedGuarantorsRepeater">إضافة كفيل متأثر</button>
                        </div>

                        <div class="separator my-3"></div>
                        <div class="col-12"><h4 class="fw-bold">النزوح والسكن الحالي</h4></div>

                        <div class="col-md-4">
                            <label class="form-label">حالة النزوح</label>
                            <select class="form-select" name="displacement_status">
                                <option value="">اختر</option>
                                <option value="displaced">نازح</option>
                                <option value="returned">عائد إلى منزله</option>
                                <option value="resident">مقيم</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">المحافظة النازح إليها</label>
                            <select class="form-select" name="displaced_to_governorate">
                                <option value="">اختر</option>
                                <option value="north">محافظة الشمال</option>
                                <option value="gaza">محافظة غزة</option>
                                <option value="middle">محافظة الوسطى</option>
                                <option value="khan_younis">محافظة خانيونس</option>
                                <option value="rafah">محافظة رفح</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">رقم التواصل 1</label>
                            <input class="form-control" name="phone_primary">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">رقم التواصل 2</label>
                            <input class="form-control" name="phone_secondary">
                        </div>
                        <div class="col-12">
                            <label class="form-label">عنوان السكن الحالي</label>
                            <textarea class="form-control" name="current_residence_address" rows="2"></textarea>
                        </div>

                        <div class="separator my-3"></div>
                        <div class="col-12"><h4 class="fw-bold">بيانات الوحدة السكنية المستهدفة بالقرض</h4></div>

                        <div class="col-md-6">
                            <label class="form-label">عنوان الوحدة</label>
                            <textarea class="form-control" name="loan_unit_address" rows="2"></textarea>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">المساحة م2</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="loan_unit_area">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">رقم القطعة</label>
                            <input class="form-control" name="parcel_number">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">رقم القسيمة</label>
                            <input class="form-control" name="plot_number">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">وضع الأشخاص داخل الشقة</label>
                            <select class="form-select" name="loan_unit_occupancy_status">
                                <option value="">اختر</option>
                                <option value="owner_borrower">المقترض نفسه</option>
                                <option value="tenants">مستأجرين</option>
                                <option value="displaced_hosted">نازحين أو مستضافين</option>
                                <option value="buyers">مشترين</option>
                                <option value="heirs">وارثين</option>
                                <option value="none_due_damage">لا يوجد بسبب الضرر</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الوضع الإنشائي للوحدة</label>
                            <select class="form-select" name="loan_unit_damage_status">
                                <option value="">اختر</option>
                                <option value="destroyed">هدم كلي</option>
                                <option value="severe_uninhabitable">متضرر بليغ غير صالح للسكن</option>
                                <option value="severe_habitable">متضرر بليغ صالح للسكن</option>
                                <option value="minor">أضرار طفيفة</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">الأسر التي تعيش داخل الوحدة</label>
                            <div id="householdsRepeater" class="d-grid gap-3"></div>
                            <button type="button" class="btn btn-sm btn-light-primary mt-2" data-add-row="householdsRepeater">إضافة أسرة</button>
                        </div>

                        <div class="col-12">
                            <label class="form-label">ملاحظات إضافية</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary" id="borrowerSubmitBtn">
                                <span class="indicator-label">حفظ الاستبيان وتحليل الحالة</span>
                                <span class="indicator-progress">جاري الحفظ...
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card card-flush mb-6">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="fw-bold mb-0">نتيجة التحليل</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div id="analysisPanel" class="alert alert-light-primary mb-0">
                        احفظ الاستبيان لعرض درجة الخطورة وأسبابها.
                    </div>
                </div>
            </div>

            <div class="card card-flush">
                <div class="card-header align-items-center gap-3">
                    <div class="card-title">
                        <h3 class="fw-bold mb-0">آخر الاستبيانات</h3>
                    </div>
                    <div class="card-toolbar">
                        <input type="search" class="form-control form-control-sm w-200px" id="borrowerSearch" placeholder="بحث">
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th>المقترض</th>
                                    <th>النزوح</th>
                                    <th>الوحدة</th>
                                    <th>الخطورة</th>
                                </tr>
                            </thead>
                            <tbody id="borrowersTableBody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted">جاري التحميل...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        const borrowerRoutes = {
            data: @json(route('damage-assessment-borrowers.data')),
            store: @json(route('damage-assessment-borrowers.store')),
        };

        const riskClasses = {
            critical: 'danger',
            high: 'warning',
            medium: 'primary',
            low: 'success',
        };

        function csrfToken() {
            return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        }

        function repeaterRow(type) {
            if (type === 'householdsRepeater') {
                return `<div class="border rounded p-3 borrower-repeat-row">
                    <div class="row g-3">
                        <div class="col-md-3"><input class="form-control" data-field="head_name" placeholder="اسم رب الأسرة"></div>
                        <div class="col-md-2"><input class="form-control" data-field="id_number" placeholder="رقم الهوية"></div>
                        <div class="col-md-2"><input type="number" min="0" class="form-control" data-field="members_count" placeholder="عدد الأفراد"></div>
                        <div class="col-md-2"><input class="form-control" data-field="phone" placeholder="رقم التواصل"></div>
                        <div class="col-md-2">
                            <select class="form-select" data-field="employment_status">
                                <option value="">وظيفي</option>
                                <option value="working">على رأس عمله</option>
                                <option value="retired">متقاعد</option>
                                <option value="not_working">لا يعمل</option>
                            </select>
                        </div>
                        <div class="col-md-1"><button type="button" class="btn btn-sm btn-light-danger w-100" data-remove-row>حذف</button></div>
                    </div>
                </div>`;
            }

            if (type === 'affectedGuarantorsRepeater') {
                return `<div class="d-flex gap-2 borrower-repeat-row">
                    <input class="form-control" data-field="name" placeholder="اسم الكفيل">
                    <select class="form-select" data-field="status">
                        <option value="retired">متقاعد</option>
                        <option value="lost_job">فقد عمله</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-light-danger" data-remove-row>حذف</button>
                </div>`;
            }

            return `<div class="d-flex gap-2 borrower-repeat-row">
                <input class="form-control" data-field="name" placeholder="اسم الكفيل المتوفى">
                <button type="button" class="btn btn-sm btn-light-danger" data-remove-row>حذف</button>
            </div>`;
        }

        document.querySelectorAll('[data-add-row]').forEach((button) => {
            button.addEventListener('click', () => {
                const target = document.getElementById(button.dataset.addRow);
                target.insertAdjacentHTML('beforeend', repeaterRow(button.dataset.addRow));
            });
        });

        document.addEventListener('click', (event) => {
            if (event.target.matches('[data-remove-row]')) {
                event.target.closest('.borrower-repeat-row').remove();
            }
        });

        function collectRepeater(id) {
            return Array.from(document.querySelectorAll(`#${id} .borrower-repeat-row`)).map((row) => {
                const item = {};
                row.querySelectorAll('[data-field]').forEach((input) => {
                    item[input.dataset.field] = input.value;
                });
                return item;
            }).filter((item) => Object.values(item).some((value) => value !== ''));
        }

        function formPayload(form) {
            const data = new FormData(form);
            const payload = {};

            data.forEach((value, key) => {
                if (key.endsWith('[]')) {
                    const cleanKey = key.slice(0, -2);
                    payload[cleanKey] = payload[cleanKey] || [];
                    payload[cleanKey].push(value);
                    return;
                }

                payload[key] = value;
            });

            payload.deceased_guarantors = collectRepeater('deceasedGuarantorsRepeater');
            payload.affected_guarantors = collectRepeater('affectedGuarantorsRepeater');
            payload.resident_households = collectRepeater('householdsRepeater');

            return payload;
        }

        function renderStats(stats) {
            Object.entries(stats || {}).forEach(([key, value]) => {
                const target = document.querySelector(`[data-stat="${key}"]`);
                if (target) target.textContent = value;
            });
        }

        function renderAnalysis(analysis) {
            const color = riskClasses[analysis.risk_level] || 'secondary';
            const reasons = (analysis.risk_reasons || []).map((reason) => `<li>${reason}</li>`).join('');
            document.getElementById('analysisPanel').className = `alert alert-light-${color} mb-0`;
            document.getElementById('analysisPanel').innerHTML = `
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="fw-bold fs-4">درجة الخطورة: ${analysis.risk_score}/100</div>
                    <span class="badge badge-light-${color} fs-6">${analysis.risk_level}</span>
                </div>
                <ul class="mb-0">${reasons || '<li>لا توجد مؤشرات خطورة رئيسية.</li>'}</ul>
            `;
        }

        function renderRows(rows) {
            const body = document.getElementById('borrowersTableBody');
            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">لا توجد بيانات بعد</td></tr>';
                return;
            }

            body.innerHTML = rows.map((row) => {
                const color = riskClasses[row.risk_level] || 'secondary';
                return `<tr>
                    <td>
                        <div class="fw-bold">${row.borrower_name}</div>
                        <div class="text-muted small">${row.borrower_id_number || '-'}</div>
                    </td>
                    <td>${row.displacement_label || '-'}</td>
                    <td>${row.damage_label || '-'}</td>
                    <td><span class="badge badge-light-${color}">${row.risk_label} (${row.risk_score})</span></td>
                </tr>`;
            }).join('');
        }

        async function loadBorrowers() {
            const q = document.getElementById('borrowerSearch').value;
            const url = new URL(borrowerRoutes.data, window.location.origin);
            if (q) url.searchParams.set('q', q);

            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json'
                }
            });
            const result = await response.json();
            renderStats(result.stats);
            renderRows(result.data || []);
        }

        document.getElementById('borrowerSurveyForm').addEventListener('submit', async (event) => {
            event.preventDefault();

            const form = event.currentTarget;
            const button = document.getElementById('borrowerSubmitBtn');
            const payload = formPayload(form);
            button.setAttribute('data-kt-indicator', 'on');
            button.disabled = true;

            try {
                if (!navigator.onLine && window.phcOfflineSync) {
                    await window.phcOfflineSync.queue({
                        url: borrowerRoutes.store,
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                        },
                        body: JSON.stringify(payload),
                    });

                    form.reset();
                    document.getElementById('deceasedGuarantorsRepeater').innerHTML = '';
                    document.getElementById('affectedGuarantorsRepeater').innerHTML = '';
                    document.getElementById('householdsRepeater').innerHTML = '';

                    if (typeof toastr !== 'undefined') {
                        toastr.success('تم حفظ الاستبيان أوفلاين. سيتم إرساله تلقائيًا عند رجوع الإنترنت.');
                    }

                    return;
                }

                const response = await fetch(borrowerRoutes.store, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify(payload),
                });
                const result = await response.json();

                if (!response.ok || !result.status) {
                    const errors = result.errors ? Object.values(result.errors).flat().join('<br>') : (result.message || 'فشل حفظ الاستبيان');
                    if (typeof toastr !== 'undefined') toastr.error(errors);
                    return;
                }

                form.reset();
                document.getElementById('deceasedGuarantorsRepeater').innerHTML = '';
                document.getElementById('affectedGuarantorsRepeater').innerHTML = '';
                document.getElementById('householdsRepeater').innerHTML = '';
                renderAnalysis(result.analysis);
                renderStats(result.stats);
                await loadBorrowers();
                if (typeof toastr !== 'undefined') toastr.success(result.message);
            } finally {
                button.removeAttribute('data-kt-indicator');
                button.disabled = false;
            }
        });

        document.getElementById('borrowerSearch').addEventListener('input', () => {
            clearTimeout(window.borrowerSearchTimer);
            window.borrowerSearchTimer = setTimeout(loadBorrowers, 300);
        });

        loadBorrowers();
    </script>
@endsection
