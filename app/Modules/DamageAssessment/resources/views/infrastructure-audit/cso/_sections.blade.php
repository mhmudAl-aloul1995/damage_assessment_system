@foreach ($sections as $section)
    <section class="cso-field-section mb-5" data-field-section>
        <h4 class="cso-section-title fs-6 fw-bold mb-0 px-4 py-3">{{ $section['title'] }}</h4>
        @foreach ($section['rows'] as $row)
            <div class="cso-field-row px-4 py-4" data-field-row="{{ $row['table_type'] }}-{{ $row['record_id'] }}-{{ $row['field_name'] }}" data-has-answer="{{ $row['has_answer'] ? '1' : '0' }}" data-is-edited="{{ $row['is_edited'] ? '1' : '0' }}">
                <div class="cso-field-label fw-semibold" title="{{ $row['field_name'] }}">{{ $row['label'] }}</div>
                <div class="cso-field-answer">
                    <div class="cso-current-value cso-wrap-value {{ $row['has_answer'] ? 'fw-semibold' : 'text-muted' }}">{{ $row['value'] }}</div>
                    <div class="cso-field-history">
                        @if (count($row['history']))
                            <button type="button" class="btn btn-sm btn-light-primary mt-2 cso-history-toggle" data-bs-toggle="collapse" data-bs-target="#{{ $row['history_id'] }}" aria-controls="{{ $row['history_id'] }}" aria-expanded="false">تم تعديله · السجل ({{ count($row['history']) }})</button>
                            <div class="collapse" id="{{ $row['history_id'] }}">
                                <div class="cso-history-items mt-3">
                                    @foreach ($row['history'] as $item)
                                        <div class="cso-history-item p-3 mb-2">
                                            <div class="d-flex flex-wrap gap-2 text-muted fs-8 mb-2"><b>{{ $item->user?->name ?? '—' }}</b><span dir="ltr">{{ $item->created_at?->format('Y-m-d H:i') ?? '—' }}</span></div>
                                            <div class="cso-wrap-value fs-7"><span class="text-muted">السابق:</span> {{ $item->display_old_value ?? $item->old_value ?? 'لا يوجد جواب' }}</div>
                                            <div class="cso-wrap-value fs-7"><span class="text-muted">الجديد:</span> {{ $item->display_field_value ?? $item->new_value ?? 'لا يوجد جواب' }}</div>
                                            @if ($item->notes)<div class="cso-wrap-value fs-7 mt-2">{{ $item->notes }}</div>@endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="cso-field-actions">
                    @if ($canEditFields && $row['editable'])
                        <button type="button" class="btn btn-sm btn-light edit-field-btn" aria-label="تعديل {{ $row['label'] }}" data-table-type="{{ $row['table_type'] }}" data-record-id="{{ $row['record_id'] }}" data-field-name="{{ $row['field_name'] }}" data-field-type="{{ $row['field_type'] ?? '' }}" data-label="{{ $row['label'] }}" data-value="{{ $row['value'] }}" data-raw-value="{{ $row['raw_value'] ?? '' }}" data-history-id="{{ $row['history_id'] }}" data-options='@json($row['options'] ?? [])'>تعديل</button>
                    @endif
                </div>
            </div>
        @endforeach
    </section>
@endforeach
<div class="cso-no-fields alert alert-light text-center" hidden>لا توجد أسئلة تطابق البحث أو الفلتر في السجل المفتوح.</div>
