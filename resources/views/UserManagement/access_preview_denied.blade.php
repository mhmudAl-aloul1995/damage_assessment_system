@extends('layouts.app')
@section('title', __('access_preview.denied'))
@section('pageName', __('access_preview.denied'))

@section('content')
<div class="card"><div class="card-body">
    <h2>{{ __('access_preview.denied') }}</h2>
    <p>{{ __('access_preview.denied_help') }}</p>
    <a href="{{ route('access-preview.show') }}" class="btn btn-light">{{ __('access_preview.title') }}</a>
</div></div>
@endsection
