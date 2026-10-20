<section class="reh-field-group" data-record-identifiers aria-label="البيانات العامة">
    <h4>البيانات العامة <small class="text-muted">· للقراءة فقط</small></h4>
    <dl class="reh-fields">
        <div data-reference="building-previous"><dt>معرّف حصر المبنى</dt><dd><bdi>{{ $referenceBuilding->old_objectid }}</bdi></dd></div>
        <div data-reference="building-current"><dt>معرّف تأهيل المبنى</dt><dd><bdi>{{ $referenceBuilding->object_id ?? 'غير متوفر' }}</bdi></dd></div>
        @if($referenceUnit)
            <div data-reference="unit-previous"><dt>معرّف حصر الوحدة</dt><dd><bdi>{{ $referenceUnit->old_objectid }}</bdi></dd></div>
            <div data-reference="unit-current"><dt>معرّف تأهيل الوحدة</dt><dd><bdi>{{ $referenceUnit->object_id ?? 'غير متوفر' }}</bdi></dd></div>
        @endif
    </dl>
</section>
