@extends('layouts.app')

@section('title', __('ui.building_deletions.new_title'))
@section('pageName', __('ui.building_deletions.title'))

@section('content')
    <div class="card card-flush mb-6">
        <div class="card-header pt-7">
            <div class="card-title">
                <h2>{{ __('ui.building_deletions.deletion_request') }}</h2>
            </div>
        </div>
        <div class="card-body">
            @include('damage-assessment::building-deletions._form')
        </div>
    </div>
@endsection

@section('script')
    <script>
        $('[data-control="select2"]').select2({ dir: @json(app()->getLocale() === 'ar' ? 'rtl' : 'ltr'), width: '100%' });
    </script>
@endsection
