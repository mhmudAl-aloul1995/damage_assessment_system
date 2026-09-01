<form method="POST" action="{{ route('building-deletions.store') }}" id="buildingDeletionForm">
    @csrf

    <div class="building-deletion-errors d-none alert alert-danger"></div>

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="mb-7">
        <label class="form-label fw-semibold">{{ __('ui.building_deletions.buildings') }}</label>
        <select name="building_globalids[]" class="form-select form-select-solid" data-control="select2" data-placeholder="{{ __('ui.building_deletions.select_buildings') }}" multiple>
            @foreach ($buildings as $building)
                <option value="{{ $building->globalid }}" @selected(in_array($building->globalid, old('building_globalids', array_filter([$selectedBuildingGlobalId])), true))>
                    {{ $building->objectid }} - {{ $building->building_name ?? $building->globalid }} - {{ $building->municipalitie }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="mb-7">
        <label class="form-label fw-semibold">{{ __('ui.building_deletions.paste_objectids') }}</label>
        <textarea name="building_objectids_text" class="form-control form-control-solid" rows="4" placeholder="{{ __('ui.building_deletions.paste_objectids_placeholder') }}">{{ old('building_objectids_text') }}</textarea>
        <div class="form-text">{{ __('ui.building_deletions.paste_objectids_help') }}</div>
    </div>

    <div class="mb-7">
        <label class="required form-label fw-semibold">{{ __('ui.building_deletions.reason') }}</label>
        <textarea name="reason" class="form-control form-control-solid" rows="4" required>{{ old('reason') }}</textarea>
    </div>

    <div class="mb-7">
        <label class="form-label fw-semibold">{{ __('ui.building_deletions.notes') }}</label>
        <textarea name="notes" class="form-control form-control-solid" rows="3">{{ old('notes') }}</textarea>
    </div>

    <label class="form-check form-check-custom form-check-solid mb-7">
        <input class="form-check-input" type="checkbox" name="confirmation" value="1" required>
        <span class="form-check-label">{{ __('ui.building_deletions.confirmation') }}</span>
    </label>

    <div class="d-flex gap-3 justify-content-end">
        @if ($isModal ?? false)
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('ui.building_deletions.back') }}</button>
        @else
            <a href="{{ route('building-deletions.index') }}" class="btn btn-light">{{ __('ui.building_deletions.back') }}</a>
        @endif
        <button type="submit" class="btn btn-danger">{{ __('ui.building_deletions.submit_request') }}</button>
    </div>
</form>
