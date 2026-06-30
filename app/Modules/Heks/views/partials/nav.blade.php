<div class="d-flex flex-wrap gap-2 mb-6">
    <a class="btn btn-sm {{ request()->routeIs('heks.dashboard') ? 'btn-primary' : 'btn-light' }}" href="{{ route('heks.dashboard') }}">نظرة عامة</a>
    <a class="btn btn-sm {{ request()->routeIs('heks.imports') ? 'btn-primary' : 'btn-light' }}" href="{{ route('heks.imports') }}">استيراد الملفات</a>
    <a class="btn btn-sm {{ request()->routeIs('heks.beneficiaries*') ? 'btn-primary' : 'btn-light' }}" href="{{ route('heks.beneficiaries') }}">الحالات والمستفيدون</a>
    <a class="btn btn-sm {{ request()->routeIs('heks.scores') ? 'btn-primary' : 'btn-light' }}" href="{{ route('heks.scores') }}">التقييم والدرجات</a>
    <a class="btn btn-sm {{ request()->routeIs('heks.labels') ? 'btn-primary' : 'btn-light' }}" href="{{ route('heks.labels') }}">معايير التقييم</a>
    <a class="btn btn-sm {{ request()->routeIs('heks.follow-ups') ? 'btn-primary' : 'btn-light' }}" href="{{ route('heks.follow-ups') }}">المتابعات</a>
    <a class="btn btn-sm {{ request()->routeIs('heks.quality') ? 'btn-primary' : 'btn-light' }}" href="{{ route('heks.quality') }}">فحص البيانات</a>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        {{ $errors->first() }}
    </div>
@endif
