@extends('layouts.app')

@section('title', 'Audit Reviewers')
@section('pageName', 'Audit Reviewers')

@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <h3 class="fw-bold mb-0">إدارة مراجعي التدقيق</h3>
            </div>
        </div>

        <div class="card-body">
            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('audit.reviewers.store') }}" class="row g-3 align-items-end mb-8">
                @csrf

                <div class="col-md-8">
                    <label for="user_id" class="form-label fw-semibold">المستخدم</label>
                    <select id="user_id" name="user_id" class="form-select form-select-solid" required>
                        <option value="">اختر مستخدم</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">
                                {{ $user->name ?? '-' }}
                                @if ($user->id_no)
                                    - {{ $user->id_no }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ki-duotone ki-plus fs-2"></i>
                        إضافة Audit Reviewer
                    </button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>الاسم</th>
                            <th>رقم الهوية</th>
                            <th>البريد</th>
                            <th class="text-end">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 fw-semibold">
                        @forelse ($reviewers as $reviewer)
                            <tr>
                                <td>{{ $reviewer->name ?? '-' }}</td>
                                <td>{{ $reviewer->id_no ?? '-' }}</td>
                                <td>{{ $reviewer->email ?? '-' }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('audit.reviewers.destroy', $reviewer) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light-danger">
                                            إزالة
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-10">
                                    لا يوجد Audit Reviewers حاليا.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
