<div class="d-flex flex-wrap gap-2 mb-6">
    <a class="btn btn-sm {{ request()->routeIs('heks.dashboard') ? 'btn-primary' : 'btn-light' }}" href="{{ route('heks.dashboard') }}">Dashboard</a>
    <a class="btn btn-sm {{ request()->routeIs('heks.imports') ? 'btn-primary' : 'btn-light' }}" href="{{ route('heks.imports') }}">Imports</a>
    <a class="btn btn-sm {{ request()->routeIs('heks.beneficiaries*') ? 'btn-primary' : 'btn-light' }}" href="{{ route('heks.beneficiaries') }}">Beneficiaries</a>
    <a class="btn btn-sm {{ request()->routeIs('heks.labels') ? 'btn-primary' : 'btn-light' }}" href="{{ route('heks.labels') }}">Labels</a>
    <a class="btn btn-sm {{ request()->routeIs('heks.follow-ups') ? 'btn-primary' : 'btn-light' }}" href="{{ route('heks.follow-ups') }}">Follow-ups</a>
    <a class="btn btn-sm {{ request()->routeIs('heks.scores') ? 'btn-primary' : 'btn-light' }}" href="{{ route('heks.scores') }}">Scores</a>
    <a class="btn btn-sm {{ request()->routeIs('heks.quality') ? 'btn-primary' : 'btn-light' }}" href="{{ route('heks.quality') }}">Data Quality</a>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        {{ $errors->first() }}
    </div>
@endif
