@extends('layouts.app')
@section('title', __('access_preview.title'))
@section('pageName', __('access_preview.title'))

@section('content')
<div class="card"><div class="card-body">
    <h2>{{ __('access_preview.ready') }}</h2>
    <p>{{ __('access_preview.navigate') }}</p>
    <div class="d-flex flex-wrap gap-3 mb-5">
        @foreach(auth()->user()->getRoleNames() as $roleName)<span class="badge badge-light-primary">{{ $roleName }}</span>@endforeach
    </div>
    <a href="{{ route('dashboard') }}" class="btn btn-primary">{{ __('access_preview.dashboard') }}</a>
</div></div>
@endsection
