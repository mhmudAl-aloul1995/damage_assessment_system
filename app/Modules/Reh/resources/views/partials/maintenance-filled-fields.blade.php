<div class="reh-filled-fields-summary"><strong>معرّف حصر الوحدة {{ $unit->old_objectid }}</strong><small>معرّف التأهيل {{ $unit->object_id }} · كود الوحدة: <bdi>{{ filled($unit->code) ? $unit->code : 'غير متوفر' }}</bdi></small><span>{{ $population['filled'] }} حقلًا معبّأ من {{ $population['total'] }}</span></div>
@forelse($groups as $group)
    <section class="reh-filled-fields-group" data-populated-group><h4>{{ $group['label'] }}</h4><dl>
        @foreach($group['fields'] as $field)
            <div data-populated-field data-field="{{ $field['field'] }}"><dt>{{ $field['label'] }}</dt><dd dir="auto">{{ $field['value'] }}</dd></div>
        @endforeach
    </dl></section>
@empty
    <p class="reh-empty">لا توجد حقول معبّأة لهذه الوحدة.</p>
@endforelse
<small class="reh-filled-fields-note">يشمل العرض الإجابات والكميات والصور المحفوظة. عدد الحقول لا يحدد حالة اكتمال الاستبيان.</small>
