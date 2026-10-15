<aside class="cso-attachment-column" aria-label="مرفقات الاستبيان">
    <div class="card cso-gallery-panel" id="cso_gallery_panel">
        <header class="p-4 cso-gallery-header">
            <div class="d-flex justify-content-between align-items-center gap-2"><h3 class="fs-6 fw-bold mb-0">مرفقات الاستبيان</h3><span class="badge badge-light">{{ count($arcgisAttachments ?? []) }}</span></div>
            <p class="text-muted fs-8 mt-2 mb-0">صور الاستبيان متاحة أثناء مراجعة الحقول.</p>
        </header>
        @if (count($imageAttachments))
            <figure class="m-0 px-4 pt-4">
                <button type="button" class="cso-gallery-preview" data-cso-open-gallery aria-label="تكبير الصورة المختارة">
                    <img id="cso_gallery_image" src="{{ $imageAttachments[0]['url'] }}" alt="{{ $imageAttachments[0]['name'] }}">
                    <span class="cso-image-error" id="cso_gallery_error" hidden>تعذر تحميل الصورة. افتحها بالحجم الكامل للمحاولة مجدداً.</span>
                    <span class="cso-image-expand">تكبير الصورة</span>
                </button>
                <figcaption class="text-truncate fs-8 mt-2" id="cso_gallery_name" title="{{ $imageAttachments[0]['name'] }}">{{ $imageAttachments[0]['name'] }}</figcaption>
            </figure>
            <div class="d-flex justify-content-between align-items-center gap-2 p-4">
                <button type="button" class="btn btn-sm btn-light" data-cso-image-step="-1" disabled aria-label="الصورة السابقة">السابق</button>
                <span class="text-muted fs-8" data-cso-image-counter aria-live="polite">1 من {{ count($imageAttachments) }}</span>
                <button type="button" class="btn btn-sm btn-light" data-cso-image-step="1" @disabled(count($imageAttachments) < 2) aria-label="الصورة التالية">التالي</button>
            </div>
        @else
            <div class="text-center text-muted fs-7 p-5">لا توجد صور مرفقة بهذا الاستبيان.</div>
        @endif
        <div class="cso-gallery-scroll px-4 pb-4" tabindex="0" aria-label="الصور المصغّرة والملفات">
            <div class="cso-gallery-thumbnails">
                @foreach ($imageAttachments as $image)
                    <button type="button" class="cso-image-thumbnail {{ $loop->first ? 'active' : '' }}" data-cso-image-index="{{ $loop->index }}" aria-label="عرض الصورة {{ $loop->iteration }}: {{ $image['name'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
                        <img src="{{ $image['url'] }}" alt="" loading="lazy"><span>{{ $loop->iteration }}</span>
                    </button>
                @endforeach
            </div>
            @if (count($fileAttachments))
                <h4 class="fs-7 fw-bold mt-5 mb-3">ملفات أخرى</h4>
                <div class="d-flex flex-column gap-2">
                    @foreach ($fileAttachments as $file)
                        <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="cso-attachment-file border rounded p-3 fs-8">{{ $file['name'] }} <span class="text-muted">↗</span></a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</aside>
<button type="button" class="btn btn-primary cso-mobile-gallery-trigger shadow" data-cso-open-gallery aria-label="فتح مرفقات الاستبيان">{{ count($imageAttachments) ? 'الصور' : 'المرفقات' }} <span class="badge badge-light ms-2">{{ count($imageAttachments) ?: count($fileAttachments) }}</span></button>
