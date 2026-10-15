@php
    $isUnits = $kind === 'units';
    $visibleGroups = $isUnits ? collect($groups)->where('organization_key', $initialOrganizationKey)->values() : collect($groups);
    $initialRecordId = $visibleGroups->first()['record_id'] ?? null;
@endphp
<div class="tab-pane {{ $isUnits ? 'cso-units-pane' : '' }}" id="cso_{{ $kind }}_pane" role="tabpanel" aria-labelledby="cso_{{ $kind }}_tab" tabindex="0" data-record-browser>
    @if ($isUnits)
        <div class="cso-unit-context px-5 py-3" aria-live="polite">
            <div class="min-w-0"><div class="text-muted fs-8">وحدات المنظمة المختارة</div><strong id="cso_unit_organization_name">{{ $organizationGroups[0]['title'] ?? 'وحدات غير مرتبطة' }}</strong></div>
            <div class="d-flex flex-wrap gap-2">
                @if ($organizationGroups !== [])
                    <button type="button" class="btn btn-sm btn-light-primary" id="cso_change_organization">تغيير المنظمة</button>
                @endif
                @if ($unassignedUnitCount > 0 && $organizationGroups !== [])
                    <button type="button" class="btn btn-sm btn-light" id="cso_unassigned_units" aria-pressed="false">وحدات غير مرتبطة <span class="badge badge-light ms-1">{{ $unassignedUnitCount }}</span></button>
                    <button type="button" class="btn btn-sm btn-light-primary" id="cso_return_organization_units" hidden>وحدات المنظمة المختارة</button>
                @endif
            </div>
        </div>
    @endif
    @if ($groups !== [] || $isUnits)
        <div class="cso-record-browser">
            <aside class="cso-record-list p-4" aria-label="قائمة {{ $plural }}">
                <div class="text-muted fw-semibold fs-7 px-2 mb-3 cso-record-list-help">اختر {{ $singular }} لعرض بياناتها</div>
                <div class="d-flex flex-column gap-2">
                    @foreach ($groups as $item)
                        @php($isVisible = ! $isUnits || $item['organization_key'] === $initialOrganizationKey)
                        @php($isSelected = $item['record_id'] === $initialRecordId)
                        <button type="button" class="cso-record-select {{ $isSelected ? 'active' : '' }}" data-organization-key="{{ $item['organization_key'] }}" data-record-target="cso_{{ $kind }}_record_{{ $item['record_id'] }}" aria-controls="cso_{{ $kind }}_record_{{ $item['record_id'] }}" aria-pressed="{{ $isSelected ? 'true' : 'false' }}" @if (! $isVisible) hidden @endif>
                            <span class="cso-record-select-name fw-bold">{{ $item['title'] }}</span>
                            <span class="fs-8 text-muted">{{ $singular }} · <span dir="ltr">#{{ $item['objectid'] }}</span></span>
                            @if (! $isUnits)<span class="fs-8">الوحدات <span class="badge badge-light ms-1">{{ $item['unit_count'] }}</span></span>@endif
                        </button>
                    @endforeach
                </div>
            </aside>
            <div class="cso-record-details p-5" tabindex="0" aria-label="تفاصيل {{ $plural }}">
                @foreach ($groups as $group)
                    @php($visibleIndex = $visibleGroups->search(fn (array $item): bool => $item['record_id'] === $group['record_id']))
                    <article id="cso_{{ $kind }}_record_{{ $group['record_id'] }}" class="cso-record-content" data-record-content data-organization-key="{{ $group['organization_key'] }}" data-title-field="{{ $group['title_field'] }}" @if ($group['record_id'] !== $initialRecordId) hidden @endif>
                        <header class="cso-child-header mb-5">
                            <div class="min-w-0"><div class="text-muted fs-7 mb-2"><span data-record-position>{{ $singular }} {{ $visibleIndex === false ? 0 : $visibleIndex + 1 }} من {{ $visibleGroups->count() }}</span> · <span dir="ltr">#{{ $group['objectid'] }}</span></div><h3 class="fw-bold mb-0 cso-child-title">{{ $group['title'] }}</h3></div>
                            <div class="d-flex flex-wrap gap-2 flex-shrink-0" aria-label="التنقل بين {{ $plural }}">
                                @if (! $isUnits)<button type="button" class="btn btn-sm btn-light-primary cso-open-units" data-organization-key="{{ $group['organization_key'] }}">عرض الوحدات ({{ $group['unit_count'] }})</button>@endif
                                @if (count($groups) > 1)
                                    <button type="button" class="btn btn-sm btn-light cso-record-step" data-step="-1" @disabled($visibleIndex === false || $visibleIndex === 0)>السابق</button>
                                    <button type="button" class="btn btn-sm btn-light cso-record-step" data-step="1" @disabled($visibleIndex === false || $visibleIndex === $visibleGroups->count() - 1)>التالي</button>
                                @endif
                            </div>
                        </header>
                        @include('damage-assessment::infrastructure-audit.cso._sections', ['sections' => $group['sections']])
                    </article>
                @endforeach
                @if ($isUnits)
                    <div class="cso-empty-state p-5 text-center" id="cso_no_organization_units" @if ($visibleGroups->isNotEmpty()) hidden @endif><h3 class="fs-5 fw-bold">{{ $initialOrganizationKey === 'unassigned' ? 'لا توجد وحدات غير مرتبطة' : 'لا توجد وحدات تابعة لهذه المنظمة' }}</h3><p class="text-muted fs-7 mb-0">اختر منظمة أخرى أو تابع مراجعة بياناتها.</p></div>
                @endif
            </div>
        </div>
    @else
        <div class="cso-empty-state p-10 text-center"><h3 class="fs-5 fw-bold">لا توجد منظمات مرتبطة بهذا الاستبيان</h3><p class="text-muted fs-7 mb-0">يمكنك متابعة التدقيق من تبويب بيانات الاستبيان.</p></div>
    @endif
</div>
