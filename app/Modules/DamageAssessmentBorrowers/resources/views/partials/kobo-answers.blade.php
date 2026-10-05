<div class="col-12">
    <div class="card card-flush">
        <div class="card-header">
            <div class="card-title"><h4 class="fw-bold mb-0">إجابات استبيان Kobo</h4></div>
        </div>
        <div class="card-body">
            @if ($answers->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead>
                            <tr class="fw-bold text-muted">
                                <th>الحقل</th>
                                <th>المسار في Kobo</th>
                                <th>الإجابة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($answers as $answer)
                                <tr>
                                    <td class="fw-semibold min-w-200px">{{ $answer->field_label }}</td>
                                    <td class="text-muted min-w-250px" dir="ltr">{{ $answer->field_key }}</td>
                                    <td class="min-w-300px">{!! nl2br(e($answer->value ?: '-')) !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-muted">لا توجد إجابات Kobo محفوظة لهذا المستفيد.</div>
            @endif
        </div>
    </div>
</div>
