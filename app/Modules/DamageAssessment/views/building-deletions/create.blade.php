@extends('layouts.app')

@section('title', 'New Building Deletion Request')
@section('pageName', 'Building Deletion Management')

@section('content')
    @if ($dryRun)
        <div class="alert alert-warning">DRY RUN is enabled. The workflow will snapshot and verify, but will not call real ArcGIS deleteFeatures.</div>
    @endif

    <form method="POST" action="{{ route('building-deletions.store') }}" id="buildingDeletionForm">
        @csrf
        <input type="hidden" name="signature" id="signatureInput">

        <div class="card card-flush mb-6">
            <div class="card-header pt-7">
                <div class="card-title">
                    <h2>Deletion Request</h2>
                </div>
            </div>
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="mb-7">
                    <label class="required form-label fw-semibold">Building</label>
                    <select name="building_globalid" class="form-select form-select-solid" data-control="select2" required>
                        <option value=""></option>
                        @foreach ($buildings as $building)
                            <option value="{{ $building->globalid }}" @selected(old('building_globalid', $selectedBuildingGlobalId) === $building->globalid)>
                                {{ $building->objectid }} - {{ $building->building_name ?? $building->globalid }} - {{ $building->municipalitie }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="row g-5 mb-7">
                    <div class="col-md-6">
                        <div class="border rounded p-5 h-100">
                            <div class="fw-bold mb-2">Data Sources</div>
                            @foreach (($deletionPlan['layers'] ?? []) as $layer)
                                <div class="d-flex justify-content-between border-bottom py-2">
                                    <span>{{ $layer['name'] }}</span>
                                    <span class="badge badge-light-success">Configured</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-5 h-100">
                            <div class="fw-bold mb-2">Deletion Plan</div>
                            <div class="text-muted">{{ $deletionPlan['sync_direction'] ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="mb-7">
                    <label class="required form-label fw-semibold">Reason</label>
                    <textarea name="reason" class="form-control form-control-solid" rows="4" required>{{ old('reason') }}</textarea>
                </div>

                <div class="mb-7">
                    <label class="form-label fw-semibold">Notes</label>
                    <textarea name="notes" class="form-control form-control-solid" rows="3">{{ old('notes') }}</textarea>
                </div>

                <label class="form-check form-check-custom form-check-solid mb-7">
                    <input class="form-check-input" type="checkbox" name="confirmation" value="1" required>
                    <span class="form-check-label">أؤكد أنني راجعت بيانات المبنى والوحدات التابعة له وأن سبب طلب الحذف موضح بشكل صحيح.</span>
                </label>

                <div class="mb-7">
                    <label class="required form-label fw-semibold">Applicant Signature</label>
                    <canvas id="signaturePad" class="border rounded w-100" height="180"></canvas>
                    <button type="button" class="btn btn-sm btn-light mt-3" id="clearSignature">Clear</button>
                </div>

                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-danger">Submit Request</button>
                    <a href="{{ route('building-deletions.index') }}" class="btn btn-light">Back</a>
                </div>
            </div>
        </div>
    </form>
@endsection

@section('script')
    <script>
        $('[data-control="select2"]').select2({ dir: 'rtl', width: '100%' });

        const canvas = document.getElementById('signaturePad');
        const context = canvas.getContext('2d');
        let drawing = false;

        function resizeCanvas() {
            const data = canvas.toDataURL();
            canvas.width = canvas.offsetWidth;
            const image = new Image();
            image.onload = function () { context.drawImage(image, 0, 0); };
            image.src = data;
        }

        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        canvas.addEventListener('pointerdown', function (event) {
            drawing = true;
            context.beginPath();
            context.moveTo(event.offsetX, event.offsetY);
        });

        canvas.addEventListener('pointermove', function (event) {
            if (!drawing) return;
            context.lineWidth = 2;
            context.lineCap = 'round';
            context.lineTo(event.offsetX, event.offsetY);
            context.stroke();
        });

        window.addEventListener('pointerup', function () { drawing = false; });

        document.getElementById('clearSignature').addEventListener('click', function () {
            context.clearRect(0, 0, canvas.width, canvas.height);
        });

        document.getElementById('buildingDeletionForm').addEventListener('submit', function () {
            document.getElementById('signatureInput').value = canvas.toDataURL('image/png');
        });
    </script>
@endsection
