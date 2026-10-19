@foreach($sections as $section)
<section class="reh-field-group"><h4>{{ $section['label'] }}</h4><dl class="reh-fields">@foreach($section['fields'] as $field)<div data-source-field="{{ $field['field'] }}"><dt>{{ $field['label'] }}</dt><dd>{{ $field['value'] }}</dd>@include('reh::partials.audit-button', ['auditFieldName' => $field['field']])</div>@endforeach</dl></section>
@endforeach
