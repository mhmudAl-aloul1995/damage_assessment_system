@extends('layouts.app')

@section('title', 'Road Facility Survey')
@section('pageName', 'Road Facility Survey')

@section('content')
<div class="card card-flush mb-7">
    <div class="card-header pt-7">
        <div class="card-title d-flex flex-column">
            <h2 class="mb-1">{{ $survey->str_name ?? 'Road Facility Survey' }}</h2>
            <div class="text-muted">Object ID: {{ $survey->objectid ?? '-' }}</div>
        </div>
        <div class="card-toolbar">
            <a href="{{ route('road-facilities.index') }}" class="btn btn-sm btn-light">Back</a>
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
                    <div class="text-muted fs-7 mb-1">Damage Level</div>
                    <div class="fw-bold fs-5">{{ $survey->road_damage_level ?? '-' }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-4 h-100 bg-light-info">
                    <div class="text-muted fs-7 mb-1">Researcher</div>
                    <div class="fw-bold fs-5">{{ $survey->assigned_to ?? '-' }}</div>
                </div>
            </div>
        </div>

        <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold mb-8">
            <li class="nav-item">
                <a class="nav-link text-active-primary active" data-bs-toggle="tab" href="#tab_road_survey">
                    Survey
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-active-primary" data-bs-toggle="tab" href="#tab_road_items">
                    Required Items
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab_road_survey" role="tabpanel">
                @foreach ($sections as $section)
                    <div class="card card-bordered shadow-sm mb-6">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title fw-bold">{{ $section['title'] }}</h3>
                        </div>
                        <div class="card-body py-4">
                            <div class="table-responsive">
                                <table class="table table-rounded table-striped align-middle table-row-dashed fs-6 gy-5">
                                    <thead>
                                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                            <th class="min-w-250px">Question</th>
                                            <th class="min-w-300px">Answer</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($section['rows'] as $row)
                                            <tr>
                                                <td class="fw-semibold text-gray-800">{{ $row['question'] }}</td>
                                                <td class="text-gray-700">{{ $row['answer'] }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="2" class="text-center text-muted py-8">No data available.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="tab-pane fade" id="tab_road_items" role="tabpanel">
                @forelse ($itemSections as $section)
                    <div class="card card-bordered shadow-sm mb-6">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title fw-bold">{{ $section['title'] }}</h3>
                        </div>
                        <div class="card-body py-4">
                            <div class="table-responsive">
                                <table class="table table-rounded table-striped align-middle table-row-dashed fs-6 gy-5">
                                    <thead>
                                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                            <th class="min-w-250px">Question</th>
                                            <th class="min-w-300px">Answer</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($section['rows'] as $row)
                                            <tr>
                                                <td class="fw-semibold text-gray-800">{{ $row['question'] }}</td>
                                                <td class="text-gray-700">{{ $row['answer'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="alert alert-secondary">No repeated items available.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection