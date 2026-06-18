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

        <div class="table-responsive">
            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                <thead>
                    <tr class="fw-bold text-muted bg-light">
                        <th style="width: 22%">الحقل</th>
                        <th style="width: 34%">وقت الأرشفة</th>
                        <th style="width: 34%">الحالي</th>
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
