@extends('layouts.app')

@section('title', 'Raw Deletion Snapshot')
@section('pageName', 'Building Deletion Management')

@section('content')
    <div class="card card-flush">
        <div class="card-header pt-7">
            <div class="card-title">
                <h2>Raw Snapshot #DEL-{{ str_pad((string) $request->id, 5, '0', STR_PAD_LEFT) }}</h2>
            </div>
        </div>
        <div class="card-body">
            @if ($snapshot)
                <pre class="bg-light p-5 rounded overflow-auto" style="max-height: 75vh;">{{ json_encode([
                    'base' => $snapshot->base_data,
                    'audited' => $snapshot->audited_data,
                    'target' => $snapshot->target_data,
                    'related' => $snapshot->related_data,
                    'attachments' => $snapshot->attachments_data,
                    'metadata' => $snapshot->metadata,
                    'schema' => $snapshot->schema_data,
                    'hash' => $snapshot->snapshot_hash,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) }}</pre>
            @else
                <div class="alert alert-info">No snapshot exists yet.</div>
            @endif
        </div>
    </div>
@endsection
