<div class="mb-6">
    <div class="fw-bold fs-5 mb-3 text-primary">{{ $section['title'] }}</div>
    <div class="table-responsive">
        <table class="table table-row-bordered align-middle gy-3">
            <tbody>
                @foreach ($section['rows'] as $row)
                    @php
                        $history = $row['history'] ?? [];
                        $latestHistory = $history[0] ?? null;
                        $hasHistory = $latestHistory !== null;
                    @endphp
                    <tr data-field-row="{{ $row['table_type'] }}-{{ $row['record_id'] }}-{{ $row['field_name'] }}"
                        data-has-answer="{{ ($row['has_answer'] ?? false) ? '1' : '0' }}"
                        data-is-edited="{{ ($row['is_edited'] ?? false) ? '1' : '0' }}">
                        <th class="w-350px text-gray-800">
                            <div class="fw-bold">{{ $row['label'] }}</div>
                            <div class="text-muted small">{{ $row['field_name'] }}</div>
                        </th>
                        <td class="fw-semibold text-center inf-audit-answer-cell">
                            @if ($hasHistory)
                                <div class="inf-audit-edit-card">
                                    <div class="inf-audit-label">الأصل</div>
                                    <div class="inf-audit-original-value">{{ $latestHistory->display_old_value ?? $latestHistory->old_value ?? 'لا يوجد جواب' }}</div>

                                    <div class="inf-audit-label text-warning mt-3">آخر تعديل</div>
                                    <div class="inf-audit-new-value">{{ $latestHistory->display_field_value ?? $latestHistory->field_value ?? 'لا يوجد جواب' }}</div>

                                    <div class="inf-audit-label text-primary mt-3">اسم المعدّل</div>
                                    <div class="fw-bold">{{ $latestHistory->user?->name ?? '-' }}</div>

                                    <div class="inf-audit-label text-primary mt-3">وقت التعديل</div>
                                    <div class="fw-bold">{{ $latestHistory->created_at?->format('Y-m-d h:i A') ?? '-' }}</div>

                                    <button type="button"
                                        class="btn btn-sm btn-light-primary mt-4"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#{{ $row['history_id'] }}">
                                        عرض سجل التعديلات ({{ count($history) }})
                                    </button>

                                    <div class="collapse mt-3" id="{{ $row['history_id'] }}">
                                        @foreach ($history as $historyItem)
                                            <div class="border rounded bg-light-info p-2 mb-2 text-start">
                                                <div><span class="inf-audit-label">القيمة</span>: <span class="fw-semibold">{{ $historyItem->display_field_value ?? $historyItem->field_value ?? '-' }}</span></div>
                                                <div><span class="inf-audit-label">الأصل</span>: {{ $historyItem->display_old_value ?? $historyItem->old_value ?? '-' }}</div>
                                                <div><span class="inf-audit-label">المستخدم</span>: {{ $historyItem->user?->name ?? '-' }}</div>
                                                <div><span class="inf-audit-label">الوقت</span>: {{ $historyItem->created_at?->format('Y-m-d h:i A') ?? '-' }}</div>
                                                @if ($historyItem->notes)
                                                    <div><span class="inf-audit-label">ملاحظات</span>: {{ $historyItem->notes }}</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <span>{{ $row['value'] }}</span>
                            @endif
                        </td>
                        <td class="text-end w-150px">
                            <button type="button"
                                class="btn btn-sm btn-light-primary edit-field-btn"
                                data-table-type="{{ $row['table_type'] }}"
                                data-record-id="{{ $row['record_id'] }}"
                                data-field-name="{{ $row['field_name'] }}"
                                data-field-type="{{ $row['field_type'] ?? '' }}"
                                data-list-name="{{ $row['list_name'] ?? '' }}"
                                data-label="{{ $row['label'] }}"
                                data-value="{{ $row['value'] }}"
                                data-raw-value="{{ $row['raw_value'] ?? '' }}"
                                data-history-id="{{ $row['history_id'] }}"
                                data-options='@json($row['options'] ?? [])'>
                                تعديل
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
