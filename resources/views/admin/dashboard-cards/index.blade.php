@extends('layouts.app')
@section('title', 'إدارة بطاقات لوحة التحكم')
@section('pageName', 'إدارة بطاقات لوحة التحكم')

@section('content')
    <div class="card card-flush mb-7">
        <div class="card-header pt-7">
            <div class="card-title">
                <h3 class="fw-bold mb-0">إضافة بطاقة</h3>
            </div>
        </div>
        <div class="card-body">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.dashboard-cards.store') }}" class="row g-4 align-items-end">
                @csrf
                @include('admin.dashboard-cards.partials.card-fields', ['card' => null])
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">إضافة</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-6">
        @foreach ($cards as $card)
            <div class="col-12">
                <div class="card card-flush">
                    <div class="card-header pt-7">
                        <div class="card-title d-flex align-items-center gap-3">
                            <span class="badge" style="background-color: {{ $card->color }};">&nbsp;</span>
                            <h3 class="fw-bold mb-0">{{ __($card->title) }}</h3>
                            @unless ($card->is_active)
                                <span class="badge badge-light-warning">مخفية</span>
                            @endunless
                        </div>
                        <div class="card-toolbar">
                            <form method="POST" action="{{ route('admin.dashboard-cards.destroy', $card) }}"
                                onsubmit="return confirm('حذف البطاقة سيحذف كل بنودها. هل أنت متأكد؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-light-danger">حذف البطاقة</button>
                            </form>
                        </div>
                    </div>

                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.dashboard-cards.update', $card) }}" class="row g-4 align-items-end mb-8">
                            @csrf
                            @method('PUT')
                            @include('admin.dashboard-cards.partials.card-fields', ['card' => $card])
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-light-primary w-100">حفظ البطاقة</button>
                            </div>
                        </form>

                        <div class="table-responsive mb-7">
                            <table class="table align-middle table-row-dashed">
                                <thead>
                                    <tr class="text-gray-500 fw-bold fs-7">
                                        <th>الترتيب</th>
                                        <th>العنوان</th>
                                        <th>المفتاح</th>
                                        <th>مصدر القيمة</th>
                                        <th>الشرط</th>
                                        <th>الرابط</th>
                                        <th>الحالة</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($card->items as $item)
                                        <tr>
                                            <form method="POST" action="{{ route('admin.dashboard-cards.items.update', [$card, $item]) }}">
                                                @csrf
                                                @method('PUT')
                                                @include('admin.dashboard-cards.partials.item-fields', ['item' => $item])
                                                <td class="text-end">
                                                    <button type="submit" class="btn btn-sm btn-light-primary mb-2">حفظ</button>
                                            </form>
                                                    <form method="POST" action="{{ route('admin.dashboard-cards.items.destroy', [$card, $item]) }}"
                                                        onsubmit="return confirm('هل تريد حذف هذا البند؟')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-light-danger">حذف</button>
                                                    </form>
                                                </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <form method="POST" action="{{ route('admin.dashboard-cards.items.store', $card) }}" class="row g-3 align-items-end">
                            @csrf
                            <div class="col-12 fw-bold">إضافة بند جديد</div>
                            @include('admin.dashboard-cards.partials.item-create-fields')
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-primary w-100">إضافة</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
