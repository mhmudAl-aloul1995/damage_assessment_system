@extends('layouts.app')

@section('title', 'حالات HEKS')
@section('pageName', 'الحالات والمستفيدون')

@section('content')
    @include('heks::partials.nav')

    <div class="card card-flush">
        <div class="card-header align-items-end">
            <h3 class="card-title">الحالات والمستفيدون</h3>
            <form class="card-toolbar row g-2" method="GET">
                <div class="col-auto">
                    <input name="q" value="{{ request('q') }}" class="form-control" placeholder="بحث بالكود، الاسم، الهوية">
                </div>
                <div class="col-auto">
                    <select name="selected" class="form-select">
                        <option value="">كل الحالات</option>
                        <option value="1" @selected(request('selected') === '1')>المختارون فقط</option>
                        <option value="0" @selected(request('selected') === '0')>غير المختارين</option>
                    </select>
                </div>
                <div class="col-auto">
                    <select name="engineer" class="form-select">
                        <option value="">كل المهندسين</option>
                        @foreach ($engineers as $engineer)
                            <option value="{{ $engineer->id }}" @selected(request('engineer') === (string) $engineer->id)>{{ $engineer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-light-primary">تصفية</button>
                </div>
            </form>
        </div>
        <div class="card-body table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>الكود</th>
                    <th>المستفيد</th>
                    <th>الهوية</th>
                    <th>المهندس</th>
                    <th>المنحة</th>
                    <th>مرحلة الحالة</th>
                    <th>الملفات المرتبطة</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($beneficiaries as $beneficiary)
                    <tr>
                        <td class="fw-bold">{{ $beneficiary->code }}</td>
                        <td>{{ $beneficiary->name ?? '-' }}</td>
                        <td>{{ $beneficiary->identity_number ?? '-' }}</td>
                        <td>{{ $beneficiary->fieldEngineerUser?->name ?? $beneficiary->field_engineer ?? '-' }}</td>
                        <td>{{ $beneficiary->grant_amount ? number_format((float) $beneficiary->grant_amount, 2) : '-' }}</td>
                        <td>
                            <span class="badge {{ $beneficiary->is_selected ? 'badge-light-success' : 'badge-light' }}">
                                {{ $beneficiary->is_selected ? 'مختار' : 'تقييم أولي' }}
                            </span>
                            @if ($beneficiary->payment_status)
                                <span class="badge badge-light-warning">
                                    {{ match ($beneficiary->payment_status) {
                                        'paid_30' => 'دفعة 30%',
                                        'paid_80' => 'دفعات 80%',
                                        'paid_100' => 'مدفوع كامل',
                                        default => 'قيد الدفع',
                                    } }}
                                </span>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-light">{{ $beneficiary->scores_count }} تقييم</span>
                            <span class="badge badge-light">{{ $beneficiary->payments_count }} دفعة</span>
                            <span class="badge badge-light">{{ $beneficiary->work_assignments_count }} توزيع</span>
                            <span class="badge badge-light">{{ $beneficiary->follow_ups_count }} متابعة</span>
                            <span class="badge badge-light">{{ $beneficiary->attachments_count }} مرفق</span>
                        </td>
                        <td><a href="{{ route('heks.beneficiaries.edit', $beneficiary) }}" class="btn btn-sm btn-light-primary">فتح</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">لا توجد حالات بعد.</td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="heks-pagination">
                {{ $beneficiaries->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection

