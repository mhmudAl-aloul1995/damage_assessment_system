@extends('layouts.app')

@section('title', 'Local Database Import')
@section('pageName', 'Local Database Import')

@section('content')

<div class="container-xxl">
    <div class="card card-flush shadow-sm">
        <div class="card-header align-items-center py-5">
            <div class="card-title">
                <h3 class="fw-bold m-0">Local Database Import</h3>
            </div>
        </div>

        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success d-flex align-items-center p-5 mb-8">
                    <i class="ki-duotone ki-check-circle fs-2hx text-success me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div class="fw-semibold">{{ session('success') }}</div>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger d-flex align-items-start p-5 mb-8">
                    <i class="ki-duotone ki-information-5 fs-2hx text-danger me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="fw-semibold">{{ session('error') }}</div>
                </div>
            @endif

            <div class="row g-8">
                <div class="col-xl-5">
                    <div class="border border-dashed border-gray-300 rounded p-6 h-100">
                        <div class="fw-bold fs-5 mb-5">Target Connection</div>

                        <div class="d-flex flex-column gap-4">
                            <div>
                                <div class="text-muted fs-7">Connection</div>
                                <div class="fw-semibold">{{ $connectionName }}</div>
                            </div>
                            <div>
                                <div class="text-muted fs-7">Host</div>
                                <div class="fw-semibold">{{ $connection['host'] ?? 'local' }}:{{ $connection['port'] ?? '3306' }}</div>
                            </div>
                            <div>
                                <div class="text-muted fs-7">Database</div>
                                <div class="fw-semibold">{{ $connection['database'] ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="text-muted fs-7">Username</div>
                                <div class="fw-semibold">{{ $connection['username'] ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-7">
                    <form method="POST" action="{{ route('admin.local-database-import.store') }}" enctype="multipart/form-data" data-show-loader="true">
                        @csrf

                        <div class="mb-7">
                            <label class="form-label fw-semibold">Import source</label>
                            <div class="d-flex flex-column gap-3">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="radio" name="import_source" value="local_path" checked>
                                    <span class="form-check-label">Use a SQL file already in this project</span>
                                </label>
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="radio" name="import_source" value="upload">
                                    <span class="form-check-label">Upload a smaller SQL file</span>
                                </label>
                            </div>
                            @error('import_source')
                                <div class="text-danger fs-7 mt-2">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-7" id="local_path_group">
                            <label for="local_path" class="form-label fw-semibold">Local SQL file</label>
                            <input
                                id="local_path"
                                name="local_path"
                                type="text"
                                class="form-control @error('local_path') is-invalid @enderror"
                                value="{{ old('local_path', $sqlFiles[0]['path'] ?? '') }}"
                                list="local_sql_files"
                                placeholder="E:/myProjects/phc/phc_new1.sql">
                            <datalist id="local_sql_files">
                                @foreach($sqlFiles as $sqlFile)
                                    <option value="{{ $sqlFile['path'] }}">{{ $sqlFile['name'] }} ({{ $sqlFile['size'] }})</option>
                                @endforeach
                            </datalist>
                            @error('local_path')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Best for large dumps because PHP does not need to upload the file through the browser.</div>
                        </div>

                        <div class="mb-7 d-none" id="sql_file_group">
                            <label for="sql_file" class="form-label fw-semibold">SQL dump file</label>
                            <input id="sql_file" name="sql_file" type="file" class="form-control @error('sql_file') is-invalid @enderror" accept=".sql,.txt">
                            @error('sql_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Use upload only for smaller files. Large dumps should be imported from a local path.</div>
                        </div>

                        <div class="form-check mb-8">
                            <input class="form-check-input @error('confirm_database') is-invalid @enderror" type="checkbox" value="1" id="confirm_database" name="confirm_database">
                            <label class="form-check-label fw-semibold" for="confirm_database">
                                I confirm this import should run against {{ $connection['database'] ?? 'the configured database' }}.
                            </label>
                            @error('confirm_database')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="ki-duotone ki-file-up fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Import Database
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
<script>
$(document).ready(function () {
    const sourceInputs = $('input[name="import_source"]');
    const localPathGroup = $('#local_path_group');
    const fileGroup = $('#sql_file_group');

    const syncSource = function () {
        const source = sourceInputs.filter(':checked').val();

        localPathGroup.toggleClass('d-none', source !== 'local_path');
        fileGroup.toggleClass('d-none', source !== 'upload');
    };

    sourceInputs.on('change', syncSource);
    syncSource();
});
</script>
@endsection
