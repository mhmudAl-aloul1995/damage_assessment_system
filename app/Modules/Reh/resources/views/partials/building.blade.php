<div class="reh-detail-content" data-building-object-id="{{ $building->old_objectid }}"><header class="reh-file-header"><span class="reh-eyebrow">ملف المبنى · المعرّف {{ $building->old_objectid }}</span><h2>مبنى {{ $building->old_objectid }}</h2><p>{{ $building->name }} · {{ $survey->value('governorate', $building->governorate) }} · {{ $survey->value('neighborhood', $building->neighborhood) }}</p><button type="button" class="btn btn-light-primary" data-building-units="{{ $building->id }}">عرض الوحدات ({{ $building->units_count }})</button></header>
@include('reh::partials.audit-tools')
@if($building->source_missing)<div class="alert alert-warning">المبنى غير موجود في آخر مزامنة مكتملة. راجع المصدر.</div>@endif
@include('reh::partials.identifiers', ['referenceBuilding' => $building, 'referenceUnit' => null])
<div class="reh-field-group"><h4>صور المبنى</h4><button type="button" class="btn btn-light" data-load-attachments="{{ route('reh.attachments', ['building', $building->id]) }}">تحميل الصور والوثائق</button><div class="reh-attachments" aria-live="polite"></div></div>
@include('reh::partials.fields', ['sections' => $survey->sections($building->source, 'building', auth()->user()->can('reh.sensitive'))])
@include('reh::partials.audit-history')
</div>
