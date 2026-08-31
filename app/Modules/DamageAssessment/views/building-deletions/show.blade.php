@extends('layouts.app')

@section('title', 'Building Deletion Request')
@section('pageName', 'Building Deletion Management')

@section('content')
    @if ($dryRun)
        <div class="alert alert-warning">DRY RUN is enabled. Real ArcGIS deleteFeatures calls are disabled.</div>
    @endif

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="card card-flush mb-6">
        <div class="card-header pt-7">
            <div class="card-title d-block">
                <h2>#DEL-{{ str_pad((string) $request->id, 5, '0', STR_PAD_LEFT) }}</h2>
                <div class="text-muted">{{ $building?->building_name ?? 'Archived Building' }} | {{ $request->building_objectid }} | {{ $request->building_globalid }}</div>
            </div>
            <div class="card-toolbar">
                <span class="badge badge-light-primary fs-6">{{ $request->status->value }}</span>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-5">
                @foreach (['request_submitted' => 'Request Created', 'gis_approved' => 'GIS Review', 'snapshot_verified' => 'Snapshot', 'gis_units_deleted' => 'GIS Housing Units', 'gis_building_deleted' => 'GIS Buildings', 'local_archived' => 'Local Archive', 'completed' => 'Completed'] as $step => $label)
                    <div class="col-md">
                        <div class="border rounded p-4 h-100 {{ $request->last_successful_step === $step ? 'bg-light-primary' : '' }}">
                            <div class="fw-bold">{{ $label }}</div>
                            <div class="text-muted small">{{ $request->auditLogs->firstWhere('step', $step)?->created_at?->format('Y-m-d H:i') ?? '-' }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row g-6">
        <div class="col-lg-7">
            <div class="card card-flush mb-6">
                <div class="card-header"><h3 class="card-title">Overview</h3></div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-4">Requested By</dt><dd class="col-sm-8">{{ $request->requester?->name }}</dd>
                        <dt class="col-sm-4">Reason</dt><dd class="col-sm-8">{{ $request->reason }}</dd>
                        <dt class="col-sm-4">Notes</dt><dd class="col-sm-8">{{ $request->notes ?? '-' }}</dd>
                        <dt class="col-sm-4">GIS Reviewer</dt><dd class="col-sm-8">{{ $request->gisReviewer?->name ?? '-' }}</dd>
                        <dt class="col-sm-4">Failed Step</dt><dd class="col-sm-8">{{ $request->failed_step ?? '-' }}</dd>
                        <dt class="col-sm-4">Failure Reason</dt><dd class="col-sm-8">{{ $request->failure_reason ?? '-' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card card-flush">
                <div class="card-header"><h3 class="card-title">Execution Timeline</h3></div>
                <div class="card-body">
                    @foreach ($request->auditLogs->sortByDesc('created_at') as $log)
                        <div class="border-bottom py-3">
                            <div class="fw-bold">{{ $log->step }} <span class="badge badge-light">{{ $log->status }}</span></div>
                            <div class="text-muted">{{ $log->created_at?->format('Y-m-d H:i:s') }} | {{ $log->user?->name ?? 'System' }}</div>
                            <div>{{ $log->message }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card card-flush mb-6">
                <div class="card-header"><h3 class="card-title">Snapshot</h3></div>
                <div class="card-body">
                    @if ($request->latestSnapshot)
                        <div class="mb-3"><span class="fw-bold">Hash:</span> <span class="text-break">{{ $request->latestSnapshot->snapshot_hash }}</span></div>
                        <div class="mb-3"><span class="fw-bold">Verified At:</span> {{ $request->latestSnapshot->verified_at?->format('Y-m-d H:i') }}</div>
                        <div class="row g-3">
                            <div class="col-6"><div class="border rounded p-3">Base Units<br><strong>{{ count($request->latestSnapshot->base_data['housing_units'] ?? []) }}</strong></div></div>
                            <div class="col-6"><div class="border rounded p-3">Audited Units<br><strong>{{ count($request->latestSnapshot->audited_data['housing_units'] ?? []) }}</strong></div></div>
                        </div>
                        @if ($canViewRawSnapshot)
                            <a href="{{ route('building-deletions.raw-snapshot', $request) }}" class="btn btn-light-primary mt-4">View Raw JSON</a>
                        @endif
                    @else
                        <div class="text-muted">Snapshot has not been created yet.</div>
                    @endif
                </div>
            </div>

            <div class="card card-flush mb-6">
                <div class="card-header"><h3 class="card-title">Approvals & Signatures</h3></div>
                <div class="card-body">
                    @foreach ($request->signatures as $signature)
                        <div class="border-bottom py-3">
                            <div class="fw-bold">{{ $signature->action->value }}</div>
                            <div class="text-muted">{{ $signature->user?->name }} | {{ $signature->signed_at?->format('Y-m-d H:i') }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($canReview && $request->status === \App\Enums\BuildingDeletionStatus::PendingGisReview)
                <div class="card card-flush">
                    <div class="card-header"><h3 class="card-title">GIS Decision</h3></div>
                    <div class="card-body">
                        <div class="alert alert-danger">اعتماد هذا الطلب يسمح للنظام ببدء الحذف فقط بعد نجاح Snapshot كامل والتحقق منه.</div>
                        <form method="POST" action="{{ route('building-deletions.review', $request) }}" id="gisReviewForm">
                            @csrf
                            <input type="hidden" name="signature" id="gisSignatureInput">
                            <div class="mb-5">
                                <select name="decision" class="form-select form-select-solid" required>
                                    <option value="approve">Approve & Sign</option>
                                    <option value="return">Return for Revision</option>
                                    <option value="reject">Reject</option>
                                </select>
                            </div>
                            <label class="form-check form-check-custom form-check-solid mb-4">
                                <input class="form-check-input" type="checkbox" name="reviewed_all_records" value="1">
                                <span class="form-check-label">I have reviewed the building and all related GIS records.</span>
                            </label>
                            <label class="form-check form-check-custom form-check-solid mb-5">
                                <input class="form-check-input" type="checkbox" name="understands_snapshot_gate" value="1">
                                <span class="form-check-label">I understand deletion starts only after a complete verified snapshot.</span>
                            </label>
                            <textarea name="gis_notes" class="form-control form-control-solid mb-5" rows="3" placeholder="GIS notes" required></textarea>
                            <canvas id="gisSignaturePad" class="border rounded w-100 mb-3" height="160"></canvas>
                            <button type="button" class="btn btn-sm btn-light mb-5" id="clearGisSignature">Clear</button>
                            <button type="submit" class="btn btn-danger d-block">Submit Decision</button>
                        </form>
                    </div>
                </div>
            @endif

            @if ($canProcess && $request->status === \App\Enums\BuildingDeletionStatus::Failed)
                <form method="POST" action="{{ route('building-deletions.retry', $request) }}">
                    @csrf
                    <button type="submit" class="btn btn-warning w-100 mt-5">Retry Failed Step</button>
                </form>
            @endif
        </div>
    </div>
@endsection

@section('script')
    <script>
        const gisCanvas = document.getElementById('gisSignaturePad');
        if (gisCanvas) {
            const ctx = gisCanvas.getContext('2d');
            let drawing = false;
            gisCanvas.width = gisCanvas.offsetWidth;
            gisCanvas.addEventListener('pointerdown', function (event) {
                drawing = true;
                ctx.beginPath();
                ctx.moveTo(event.offsetX, event.offsetY);
            });
            gisCanvas.addEventListener('pointermove', function (event) {
                if (!drawing) return;
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.lineTo(event.offsetX, event.offsetY);
                ctx.stroke();
            });
            window.addEventListener('pointerup', function () { drawing = false; });
            document.getElementById('clearGisSignature').addEventListener('click', function () {
                ctx.clearRect(0, 0, gisCanvas.width, gisCanvas.height);
            });
            document.getElementById('gisReviewForm').addEventListener('submit', function () {
                document.getElementById('gisSignatureInput').value = gisCanvas.toDataURL('image/png');
            });
        }
    </script>
@endsection
