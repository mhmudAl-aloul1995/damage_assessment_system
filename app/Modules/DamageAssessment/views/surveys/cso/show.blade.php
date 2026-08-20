@extends('layouts.app')

@section('title', 'CSO Damage Assessment')
@section('pageName', 'CSO Damage Assessment')

@section('content')
    <div class="card card-flush mb-7">
        <div class="card-header pt-7">
            <div class="card-title d-flex flex-column">
                <h2 class="mb-1">{{ $survey->organization_name ?? $survey->building_name ?? 'CSO Damage Assessment' }}</h2>
                <div class="text-muted">Object ID: {{ $survey->objectid ?? '-' }}</div>
                <div class="text-muted">Global ID: {{ $survey->globalid ?? '-' }}</div>
            </div>
            <div class="card-toolbar">
                <a href="{{ route('cso-surveys.index') }}" class="btn btn-sm btn-light">Back</a>
            </div>
        </div>

        <div class="card-body">
            <div class="row g-5 mb-8">
                <div class="col-md-3">
                    <div class="border rounded p-4 h-100 bg-light-primary">
                        <div class="text-muted fs-7 mb-1">Municipality</div>
                        <div class="fw-bold fs-5">{{ $survey->municipalitie ?? '-' }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-4 h-100 bg-light-success">
                        <div class="text-muted fs-7 mb-1">Neighborhood</div>
                        <div class="fw-bold fs-5">{{ $survey->neighborhood ?? '-' }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-4 h-100 bg-light-warning">
                        <div class="text-muted fs-7 mb-1">Damage Status</div>
                        <div class="fw-bold fs-5">{{ $survey->building_damage_status ?? '-' }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-4 h-100 bg-light-info">
                        <div class="text-muted fs-7 mb-1">Researcher</div>
                        <div class="fw-bold fs-5">{{ $survey->assignedto ?? '-' }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-4 h-100 bg-light">
                        <div class="text-muted fs-7 mb-1">Organizations</div>
                        <div class="fw-bold fs-5">{{ $survey->organizations->count() }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-4 h-100 bg-light">
                        <div class="text-muted fs-7 mb-1">Units</div>
                        <div class="fw-bold fs-5">{{ $survey->units->count() }}</div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold mb-8">
                <li class="nav-item">
                    <a class="nav-link text-active-primary active" data-bs-toggle="tab" href="#tab_cso_survey">
                        Survey
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-active-primary" data-bs-toggle="tab" href="#tab_cso_organizations">
                        CSO Organizations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-active-primary" data-bs-toggle="tab" href="#tab_cso_units">
                        Unit Information
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab_cso_survey" role="tabpanel">
                    @foreach ($sections as $section)
                        @include('damage-assessment::surveys.cso._section_table', ['section' => $section])
                    @endforeach
                </div>

                <div class="tab-pane fade" id="tab_cso_organizations" role="tabpanel">
                    @forelse ($organizationSections as $section)
                        @include('damage-assessment::surveys.cso._section_table', ['section' => $section])
                    @empty
                        <div class="alert alert-secondary">No CSO organizations available.</div>
                    @endforelse
                </div>

                <div class="tab-pane fade" id="tab_cso_units" role="tabpanel">
                    @forelse ($unitSections as $section)
                        @include('damage-assessment::surveys.cso._section_table', ['section' => $section])
                    @empty
                        <div class="alert alert-secondary">No unit information available.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
