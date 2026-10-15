<div class="modal fade cso-location-viewer" id="cso_location_viewer" tabindex="-1" aria-labelledby="cso_location_viewer_title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-md-down">
        <div class="modal-content">
            <div class="modal-header">
                <div><h3 class="fw-bold mb-1" id="cso_location_viewer_title">الموقع الجغرافي</h3><div class="text-muted fs-7">التنقل والتكبير متاحان للمراجعة فقط، دون تعديل الموقع.</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق الخريطة"></button>
            </div>
            <div class="modal-body d-flex flex-column gap-3">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge badge-light">للعرض فقط</span>
                    <button type="button" class="btn btn-sm btn-light-primary" id="cso_location_fit" disabled>إظهار الموقع كاملاً</button>
                    <a class="btn btn-sm btn-light" id="cso_location_google" target="_blank" rel="noopener noreferrer">فتح الموقع في Google Maps</a>
                </div>
                <div class="cso-location-large-stage">
                    <div id="cso_location_fallback"></div>
                    <div id="cso_location_map" role="region" aria-label="خريطة الموقع الجغرافي للعرض فقط"></div>
                </div>
                <p class="text-muted fs-7 mb-0" id="cso_location_status" role="status"></p>
            </div>
        </div>
    </div>
</div>
