<div class="mb-2">
    <h3 class="fw-bold mb-6">{{ $sectionTitle }}</h3>

    @foreach ($sections as $section)
        @include('modules.damage-assessment.infrastructure-audit.public-buildings._field_table', ['section' => $section])
    @endforeach
</div>
