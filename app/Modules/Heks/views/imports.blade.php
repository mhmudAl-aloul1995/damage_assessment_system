@extends('layouts.app')

@section('title', 'استيراد ملفات HEKS')
@section('pageName', 'استيراد الملفات')

@section('content')
    @include('heks::partials.nav')

    <div class="card card-flush mb-6">
        <div class="card-header"><h3 class="card-title">استيراد ملف Excel</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('heks.imports.store') }}" enctype="multipart/form-data" class="row g-4 align-items-end">
                @csrf
                <div class="col-lg-5">
                    <label class="form-label">الملف</label>
                    <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                </div>
                <div class="col-lg-4">
                    <label class="form-label">نوع الملف</label>
                    <select name="type" class="form-select">
                        <option value="scores">ملف HEKS الكامل: تقييم، اختيار، دفعات، مجموعات عمل</option>
                        <option value="labels">ملف التقييم الأولي 180 حالة</option>
                        <option value="followups">ملف المتابعة 125 مستفيد</option>
                        <option value="auto">اكتشاف تلقائي</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <button class="btn btn-primary w-100">استيراد</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-flush">
        <div class="card-header"><h3 class="card-title">سجل الاستيراد</h3></div>
        <div class="card-body table-responsive">
            <table class="table align-middle">
                <thead><tr><th>الملف</th><th>النوع</th><th>الشيتات</th><th>الإجمالي</th><th>جديد</th><th>تحديث</th><th>متجاوز</th><th>المستخدم</th><th>التاريخ</th></tr></thead>
                <tbody>
                @forelse ($imports as $import)
                    <tr>
                        <td>{{ $import->filename }}</td>
                        <td>{{ $import->type }}</td>
                        <td>{{ $import->sheet_name }}</td>
                        <td>{{ $import->total_rows }}</td>
                        <td>{{ $import->created_rows }}</td>
                        <td>{{ $import->updated_rows }}</td>
                        <td>{{ $import->skipped_rows }}</td>
                        <td>{{ $import->user?->name ?? '-' }}</td>
                        <td>{{ $import->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted">لا توجد عمليات استيراد بعد.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $imports->links() }}
        </div>
    </div>
@endsection
