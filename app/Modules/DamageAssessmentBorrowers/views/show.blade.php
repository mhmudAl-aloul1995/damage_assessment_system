@extends('layouts.app')

@section('title', 'بيانات مستفيد قرض بنك التنمية الإسلامي')
@section('pageName', 'بيانات المستفيد')

@section('content')
    @php
        $riskColors = [
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'primary',
            'low' => 'success',
        ];
        $riskColor = $riskColors[$borrower->risk_level] ?? 'secondary';

        $value = static fn (mixed $item): string => filled($item) ? (string) $item : '-';
        $money = static fn (mixed $item): string => number_format((float) $item, 2);
        $isImageUrl = static function (?string $url): bool {
            if (! filled($url)) {
                return false;
            }

            return preg_match('/\.(jpe?g|png|webp|gif)(\?.*)?$/i', $url) === 1;
        };
        $attachmentHref = static function ($attachment): ?string {
            if (filled($attachment->url)) {
                return $attachment->url;
            }

            return null;
        };
        $displayList = static fn (?array $items): array => collect($items ?? [])
            ->map(fn ($item) => is_array($item) ? ($item['name'] ?? implode(' - ', array_filter($item))) : $item)
            ->filter()
            ->values()
            ->all();
    @endphp

    <style>
        .borrower-show-page .borrower-detail-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .borrower-show-page .borrower-detail-item {
            border-bottom: 1px dashed var(--bs-gray-300);
            padding-bottom: 0.85rem;
        }

        .borrower-show-page .borrower-detail-label {
            color: var(--bs-gray-500);
            font-size: 0.82rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .borrower-show-page .borrower-detail-value {
            color: var(--bs-gray-800);
            font-weight: 700;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .borrower-show-page .borrower-attachment-preview {
            aspect-ratio: 4 / 3;
            background: var(--bs-gray-100);
            border: 1px solid var(--bs-gray-200);
            border-radius: 0.5rem;
            display: block;
            object-fit: cover;
            width: 100%;
        }

        .borrower-show-page .borrower-attachment-fallback {
            align-items: center;
            aspect-ratio: 4 / 3;
            background: var(--bs-gray-100);
            border: 1px dashed var(--bs-gray-300);
            border-radius: 0.5rem;
            color: var(--bs-gray-600);
            display: flex;
            justify-content: center;
            text-align: center;
        }

        @media (max-width: 991.98px) {
            .borrower-show-page .borrower-detail-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .borrower-show-page .borrower-detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="borrower-show-page">
        <div class="card card-flush mb-6">
            <div class="card-header align-items-center gap-3">
                <div class="card-title">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge badge-light-{{ $riskColor }}">{{ $labels['risk_level'] }} - {{ $borrower->risk_score }}/100</span>
                            <span class="badge badge-light">{{ $borrower->form_number ?: '-' }}</span>
                        </div>
                        <h3 class="fw-bold mb-1">{{ $borrower->borrower_name }}</h3>
                        <div class="text-muted fs-7">{{ $borrower->borrower_id_number ?: '-' }}</div>
                    </div>
                </div>
                <div class="card-toolbar gap-2">
                    <a href="{{ route('damage-assessment-borrowers.index') }}" class="btn btn-light-primary">رجوع</a>
                    <a href="{{ route('damage-assessment-borrowers.pricing', $borrower) }}" class="btn btn-primary">تسعير</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-5">
                    <div class="col-md-3 col-6">
                        <div class="border rounded p-4 h-100">
                            <div class="text-muted fs-7 mb-1">إجمالي الدولار</div>
                            <div class="fs-3 fw-bold text-primary">{{ $money($borrower->boq_total_usd) }} $</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="border rounded p-4 h-100">
                            <div class="text-muted fs-7 mb-1">إجمالي الشيكل</div>
                            <div class="fs-3 fw-bold text-success">{{ $money($borrower->boq_total_ils) }} ILS</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="border rounded p-4 h-100">
                            <div class="text-muted fs-7 mb-1">بنود BOQ</div>
                            <div class="fs-3 fw-bold">{{ $borrower->boqItems->count() }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="border rounded p-4 h-100">
                            <div class="text-muted fs-7 mb-1">الصور</div>
                            <div class="fs-3 fw-bold">{{ $borrower->attachments->count() }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-6">
            <div class="col-xl-6">
                <div class="card card-flush h-100">
                    <div class="card-header">
                        <div class="card-title"><h4 class="fw-bold mb-0">بيانات المقترض</h4></div>
                    </div>
                    <div class="card-body">
                        <div class="borrower-detail-grid">
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">الاسم</div>
                                <div class="borrower-detail-value">{{ $value($borrower->borrower_name) }}</div>
                            </div>
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">رقم الهوية</div>
                                <div class="borrower-detail-value">{{ $value($borrower->borrower_id_number) }}</div>
                            </div>
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">عدد أفراد الأسرة</div>
                                <div class="borrower-detail-value">{{ $value($borrower->family_members_count) }}</div>
                            </div>
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">الحالة الاجتماعية</div>
                                <div class="borrower-detail-value">{{ $labels['marital_status'] }}</div>
                            </div>
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">الوضع الوظيفي</div>
                                <div class="borrower-detail-value">{{ $labels['employment_status'] }}</div>
                            </div>
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">على قيد الحياة</div>
                                <div class="borrower-detail-value">{{ $borrower->is_borrower_alive ? 'نعم' : 'لا' }}</div>
                            </div>
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">اسم الزوج/ة</div>
                                <div class="borrower-detail-value">{{ $value($borrower->spouse_name) }}</div>
                            </div>
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">هوية الزوج/ة</div>
                                <div class="borrower-detail-value">{{ $value($borrower->spouse_id_number) }}</div>
                            </div>
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">أدخل بواسطة</div>
                                <div class="borrower-detail-value">{{ $labels['submitted_by'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card card-flush h-100">
                    <div class="card-header">
                        <div class="card-title"><h4 class="fw-bold mb-0">السكن والوحدة</h4></div>
                    </div>
                    <div class="card-body">
                        <div class="borrower-detail-grid">
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">حالة النزوح</div>
                                <div class="borrower-detail-value">{{ $labels['displacement_status'] }}</div>
                            </div>
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">محافظة النزوح</div>
                                <div class="borrower-detail-value">{{ $labels['displaced_to_governorate'] }}</div>
                            </div>
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">رقم التواصل 1</div>
                                <div class="borrower-detail-value">{{ $value($borrower->phone_primary) }}</div>
                            </div>
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">رقم التواصل 2</div>
                                <div class="borrower-detail-value">{{ $value($borrower->phone_secondary) }}</div>
                            </div>
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">إشغال الوحدة</div>
                                <div class="borrower-detail-value">{{ $labels['loan_unit_occupancy_status'] }}</div>
                            </div>
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">ضرر الوحدة</div>
                                <div class="borrower-detail-value">{{ $labels['loan_unit_damage_status'] }}</div>
                            </div>
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">مساحة الوحدة</div>
                                <div class="borrower-detail-value">{{ $value($borrower->loan_unit_area) }}</div>
                            </div>
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">رقم القطعة</div>
                                <div class="borrower-detail-value">{{ $value($borrower->parcel_number) }}</div>
                            </div>
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">رقم القسيمة</div>
                                <div class="borrower-detail-value">{{ $value($borrower->plot_number) }}</div>
                            </div>
                        </div>
                        <div class="separator my-5"></div>
                        <div class="borrower-detail-item">
                            <div class="borrower-detail-label">عنوان السكن الحالي</div>
                            <div class="borrower-detail-value">{{ $value($borrower->current_residence_address) }}</div>
                        </div>
                        <div class="borrower-detail-item mt-4">
                            <div class="borrower-detail-label">عنوان الوحدة المستهدفة</div>
                            <div class="borrower-detail-value">{{ $value($borrower->loan_unit_address) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card card-flush h-100">
                    <div class="card-header">
                        <div class="card-title"><h4 class="fw-bold mb-0">الكفلاء والمؤشرات</h4></div>
                    </div>
                    <div class="card-body">
                        <div class="borrower-detail-grid">
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">عدد الكفلاء</div>
                                <div class="borrower-detail-value">{{ $value($borrower->guarantors_count) }}</div>
                            </div>
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">حياة الكفلاء</div>
                                <div class="borrower-detail-value">{{ $labels['guarantors_alive_status'] }}</div>
                            </div>
                            <div class="borrower-detail-item">
                                <div class="borrower-detail-label">درجة الخطورة</div>
                                <div class="borrower-detail-value">{{ $labels['risk_level'] }} - {{ $borrower->risk_score }}/100</div>
                            </div>
                        </div>

                        <div class="separator my-5"></div>

                        <div class="mb-5">
                            <div class="borrower-detail-label">الكفلاء المتوفون</div>
                            @forelse ($displayList($borrower->deceased_guarantors) as $name)
                                <span class="badge badge-light-danger me-2 mb-2">{{ $name }}</span>
                            @empty
                                <span class="text-muted">-</span>
                            @endforelse
                        </div>

                        <div class="mb-5">
                            <div class="borrower-detail-label">الكفلاء المتضررون</div>
                            @forelse ($displayList($borrower->affected_guarantors) as $name)
                                <span class="badge badge-light-warning me-2 mb-2">{{ $name }}</span>
                            @empty
                                <span class="text-muted">-</span>
                            @endforelse
                        </div>

                        <div>
                            <div class="borrower-detail-label">أسباب الخطورة</div>
                            @forelse ($borrower->risk_reasons ?? [] as $reason)
                                <div class="alert alert-light-{{ $riskColor }} py-2 px-3 mb-2">{{ $reason }}</div>
                            @empty
                                <div class="text-muted">لا توجد أسباب خطورة محفوظة.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card card-flush h-100">
                    <div class="card-header">
                        <div class="card-title"><h4 class="fw-bold mb-0">الأسر المقيمة في الوحدة</h4></div>
                    </div>
                    <div class="card-body">
                        @if ($borrower->residentHouseholds->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-row-dashed align-middle">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th>رب الأسرة</th>
                                            <th>الهوية</th>
                                            <th>الأفراد</th>
                                            <th>الجوال</th>
                                            <th>العمل</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($borrower->residentHouseholds as $household)
                                            <tr>
                                                <td>{{ $household->head_name }}</td>
                                                <td>{{ $household->id_number ?: '-' }}</td>
                                                <td>{{ $household->members_count ?: '-' }}</td>
                                                <td>{{ $household->phone ?: '-' }}</td>
                                                <td>{{ $household->employment_status ?: '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-muted">لا توجد أسر مقيمة محفوظة لهذا المستفيد.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card card-flush">
                    <div class="card-header">
                        <div class="card-title"><h4 class="fw-bold mb-0">صور المستفيد</h4></div>
                    </div>
                    <div class="card-body">
                        @if ($borrower->attachments->isNotEmpty())
                            <div class="row g-5">
                                @foreach ($borrower->attachments as $attachment)
                                    @php
                                        $href = $attachmentHref($attachment);
                                    @endphp
                                    <div class="col-xl-3 col-md-4 col-sm-6">
                                        <div class="border rounded p-3 h-100">
                                            @if ($href && $isImageUrl($href))
                                                <a href="{{ $href }}" target="_blank" rel="noopener">
                                                    <img src="{{ $href }}" alt="{{ $attachment->filename ?: 'صورة مستفيد' }}" class="borrower-attachment-preview">
                                                </a>
                                            @else
                                                <div class="borrower-attachment-fallback">لا يمكن عرض معاينة مباشرة</div>
                                            @endif
                                            <div class="fw-semibold mt-3 text-truncate" title="{{ $attachment->filename ?: $href }}">
                                                {{ $attachment->filename ?: 'مرفق رقم '.$attachment->source_index }}
                                            </div>
                                            @if ($href)
                                                <a href="{{ $href }}" target="_blank" rel="noopener" class="btn btn-sm btn-light-primary mt-3 w-100">فتح المرفق</a>
                                            @else
                                                <div class="text-muted fs-7 mt-3">لا يوجد رابط محفوظ، الاسم فقط متوفر.</div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-muted">لا توجد صور محفوظة لهذا المستفيد.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card card-flush">
                    <div class="card-header">
                        <div class="card-title"><h4 class="fw-bold mb-0">بنود BOQ المحفوظة</h4></div>
                    </div>
                    <div class="card-body">
                        @if ($borrower->boqItems->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-row-dashed align-middle">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th>الكود</th>
                                            <th>البند</th>
                                            <th>الوحدة</th>
                                            <th>الكمية</th>
                                            <th>سعر الوحدة $</th>
                                            <th>الإجمالي $</th>
                                            <th>الإجمالي ILS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($borrower->boqItems as $item)
                                            <tr>
                                                <td>{{ $item->item_code ?: '-' }}</td>
                                                <td class="min-w-300px">{{ $item->description }}</td>
                                                <td>{{ $item->unit ?: '-' }}</td>
                                                <td>{{ $money($item->quantity) }}</td>
                                                <td>{{ $money($item->unit_price) }}</td>
                                                <td class="fw-bold">{{ $money($item->total_price) }}</td>
                                                <td class="fw-bold text-success">{{ $money($item->total_price_ils) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-muted">لا توجد بنود BOQ محفوظة لهذا المستفيد.</div>
                        @endif
                    </div>
                </div>
            </div>

            @if (filled($borrower->notes))
                <div class="col-12">
                    <div class="card card-flush">
                        <div class="card-header">
                            <div class="card-title"><h4 class="fw-bold mb-0">ملاحظات</h4></div>
                        </div>
                        <div class="card-body">
                            <div class="text-gray-800 lh-lg">{!! nl2br(e($borrower->notes)) !!}</div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
