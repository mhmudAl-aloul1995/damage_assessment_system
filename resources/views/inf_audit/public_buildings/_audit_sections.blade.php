<div class="mb-2">
    <h3 class="fw-bold mb-6">{{ $sectionTitle }}</h3>

    @foreach ($sections as $section)
        @include('inf_audit.public_buildings._field_table', ['section' => $section])
    @endforeach
</div>
