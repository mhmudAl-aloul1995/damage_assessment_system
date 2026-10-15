<div class="tab-pane" id="cso_{{ $kind }}_pane" role="tabpanel" aria-labelledby="cso_{{ $kind }}_tab" tabindex="0" data-record-browser>
    @forelse ($groups as $group)
        @if ($loop->first)
            <div class="cso-record-browser">
                <aside class="cso-record-list p-4" aria-label="قائمة {{ $plural }}">
                    <div class="text-muted fw-semibold fs-7 px-2 mb-3 cso-record-list-help">اختر {{ $singular }} لعرض بياناتها</div>
                    <div class="d-flex flex-column gap-2">
                        @foreach ($groups as $item)
                            <button type="button" class="cso-record-select {{ $loop->first ? 'active' : '' }}" data-record-target="cso_{{ $kind }}_record_{{ $item['record_id'] }}" aria-controls="cso_{{ $kind }}_record_{{ $item['record_id'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
                                <span class="cso-record-select-name fw-bold">{{ $item['title'] }}</span>
                                <span class="fs-8 text-muted">{{ $singular }} {{ $loop->iteration }} · <span dir="ltr">#{{ $item['objectid'] }}</span></span>
                            </button>
                        @endforeach
                    </div>
                </aside>
                <div class="cso-record-details p-5" tabindex="0" aria-label="تفاصيل {{ $plural }}">
        @endif
                    <article id="cso_{{ $kind }}_record_{{ $group['record_id'] }}" class="cso-record-content" data-record-content data-title-field="{{ $group['title_field'] }}" @if (! $loop->first) hidden @endif>
                        <header class="cso-child-header mb-5">
                            <div class="min-w-0"><div class="text-muted fs-7 mb-2">{{ $singular }} {{ $loop->iteration }} من {{ count($groups) }} · <span dir="ltr">#{{ $group['objectid'] }}</span></div><h3 class="fw-bold mb-0 cso-child-title">{{ $group['title'] }}</h3></div>
                            @if (count($groups) > 1)
                                <div class="d-flex gap-2 flex-shrink-0" aria-label="التنقل بين {{ $plural }}">
                                    <button type="button" class="btn btn-sm btn-light cso-record-step" data-step="-1" @disabled($loop->first)>السابق</button>
                                    <button type="button" class="btn btn-sm btn-light cso-record-step" data-step="1" @disabled($loop->last)>التالي</button>
                                </div>
                            @endif
                        </header>
                        @include('damage-assessment::infrastructure-audit.cso._sections', ['sections' => $group['sections']])
                    </article>
        @if ($loop->last)
                </div>
            </div>
        @endif
    @empty
        <div class="cso-empty-state p-10 text-center"><h3 class="fs-5 fw-bold">لا توجد {{ $kind === 'organizations' ? 'منظمات' : 'وحدات' }} مرتبطة بهذا الاستبيان</h3><p class="text-muted fs-7 mb-0">يمكنك متابعة التدقيق من تبويب بيانات الاستبيان.</p></div>
    @endforelse
</div>
