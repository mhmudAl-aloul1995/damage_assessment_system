<div class="card card-flush shadow-sm mb-5">
    <div class="card-header pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">{{ $title }}</h3>
        </div>
    </div>
    <div class="card-body">
        @if ($missingCurrent)
            <div class="alert alert-light-warning mb-5">
                السجل الحالي غير موجود في قاعدة البيانات، لذلك تظهر المقارنة مع قيمة فارغة.
            </div>
        @endif

        <div class="row g-5 mb-6">
            <div class="col-lg-6">
                <div class="border border-gray-200 rounded p-4 h-100">
                    <div class="fw-bold mb-3">السجل السابق</div>
                    <pre class="bg-light p-4 rounded text-break mb-0" style="white-space: pre-wrap; max-height: 360px; overflow: auto;">{{ $previousRecord ?? '-' }}</pre>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="border border-gray-200 rounded p-4 h-100">
                    <div class="fw-bold mb-3">السجل الحالي</div>
                    <pre class="bg-light p-4 rounded text-break mb-0" style="white-space: pre-wrap; max-height: 360px; overflow: auto;">{{ $currentRecord ?? '-' }}</pre>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                <thead>
                    <tr class="fw-bold text-muted bg-light">
                        <th style="width: 22%">الحقل</th>
                        <th style="width: 34%">السجل السابق</th>
                        <th style="width: 34%">السجل الحالي</th>
                        <th style="width: 10%">الفرق</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr @class(['table-light-warning' => $row['changed']])>
                            <td class="fw-semibold">{{ $row['label'] }}</td>
                            <td class="text-break">{{ $formatValue($row['old']) }}</td>
                            <td class="text-break">{{ $formatValue($row['current']) }}</td>
                            <td>
                                @if ($row['changed'])
                                    <span class="badge badge-light-warning">تغير</span>
                                @else
                                    <span class="badge badge-light-success">نفسه</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
