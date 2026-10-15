<div class="modal fade cso-image-viewer" id="cso_attachment_viewer" tabindex="-1" aria-labelledby="cso_viewer_title" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header gap-3">
                <div class="min-w-0"><h3 class="fs-5 fw-bold mb-1" id="cso_viewer_title">مرفقات الاستبيان</h3><div class="text-muted fs-8 text-truncate" id="cso_viewer_name"></div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق معرض الصور"></button>
            </div>
            @if (count($imageAttachments))
                <div class="cso-viewer-controls px-4 py-3">
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-light" data-cso-image-step="-1" disabled aria-label="الصورة السابقة">السابق</button>
                        <span class="fs-7" data-cso-image-counter aria-live="polite">1 من {{ count($imageAttachments) }}</span>
                        <button type="button" class="btn btn-sm btn-light" data-cso-image-step="1" @disabled(count($imageAttachments) < 2) aria-label="الصورة التالية">التالي</button>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-light" id="cso_zoom_out" aria-label="تصغير الصورة" disabled>−</button>
                        <button type="button" class="btn btn-sm btn-light" id="cso_zoom_reset" aria-label="ملاءمة الصورة للشاشة">100%</button>
                        <button type="button" class="btn btn-sm btn-light" id="cso_zoom_in" aria-label="تكبير الصورة">+</button>
                        <a id="cso_viewer_original" href="{{ $imageAttachments[0]['url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-light-primary" aria-label="فتح الصورة الأصلية في نافذة جديدة">الأصل ↗</a>
                    </div>
                </div>
                <div class="cso-viewer-stage" id="cso_viewer_stage" tabindex="0" aria-label="الصورة المكبّرة، استخدم أزرار التكبير لعرض التفاصيل">
                    <div class="cso-viewer-canvas"><img id="cso_viewer_image" alt=""><p class="text-muted p-5 text-center" id="cso_viewer_error" hidden>تعذر تحميل الصورة. يمكنك استخدام زر «الأصل» لفتحها مباشرة.</p></div>
                </div>
                <div class="cso-viewer-thumbnails p-3" aria-label="اختيار صورة">
                    @foreach ($imageAttachments as $image)
                        <button type="button" class="cso-image-thumbnail {{ $loop->first ? 'active' : '' }}" data-cso-image-index="{{ $loop->index }}" aria-label="عرض الصورة {{ $loop->iteration }}: {{ $image['name'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"><img src="{{ $image['url'] }}" alt="" loading="lazy"><span>{{ $loop->iteration }}</span></button>
                    @endforeach
                </div>
            @else
                <div class="modal-body"><p class="text-muted text-center">لا توجد صور مرفقة بهذا الاستبيان.</p></div>
            @endif
            @if (count($fileAttachments))
                <div class="cso-viewer-files px-4 py-3"><span class="text-muted fs-8">ملفات أخرى:</span>
                    @foreach ($fileAttachments as $file)<a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-light">{{ $file['name'] }} ↗</a>@endforeach
                </div>
            @endif
        </div>
    </div>
</div>
