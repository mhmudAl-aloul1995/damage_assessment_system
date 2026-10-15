<div class="cso-current-value cso-location-card" @if ($location['status'] === 'valid') data-cso-location='@json($location['geometry'])' data-location-center='@json($location['center'])' @endif>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <span class="fw-semibold">{{ ($location['is_polygon'] ?? false) ? 'حدود الموقع' : 'الموقع على الخريطة' }}</span>
        <span class="badge badge-light">للعرض فقط</span>
    </div>
    @if ($location['status'] === 'valid')
        <div class="cso-location-preview">
            <svg class="cso-location-outline" viewBox="0 0 640 360" role="img" aria-label="{{ $location['is_polygon'] ? 'رسم حدود الموقع الجغرافي' : 'نقطة الموقع الجغرافي' }}">
                @if ($location['is_polygon'])
                    <path d="{{ implode(' ', $location['paths']) }}" fill-rule="evenodd"/>
                @else
                    <circle cx="320" cy="180" r="12"/>
                @endif
            </svg>
            <div class="cso-location-map" aria-hidden="true" inert></div>
        </div>
        <p class="cso-location-status text-muted fs-8 mt-2 mb-3" role="status">جاري تحميل خريطة الأقمار الصناعية…</p>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-light-primary cso-open-location">تكبير الخريطة</button>
            <a class="btn btn-sm btn-light" href="{{ $location['google_url'] }}" target="_blank" rel="noopener noreferrer">فتح الموقع في Google Maps <span class="visually-hidden">(نقطة وسط نطاق الموقع، يفتح في نافذة جديدة)</span></a>
        </div>
        <p class="text-muted fs-8 mt-3 mb-0">يمكن مراجعة الموقع فقط؛ تعديل الحدود غير متاح هنا.</p>
    @else
        <p class="text-muted fs-7 mb-0" role="status">{{ match ($location['status']) { 'missing' => 'لم يتم تسجيل موقع جغرافي لهذا الاستبيان.', 'unsupported' => 'نظام إحداثيات الموقع غير مدعوم للعرض حالياً.', default => 'بيانات الموقع غير صالحة للعرض على الخريطة.' } }}</p>
    @endif
    @if ($location['source'] !== '')
        <details class="cso-location-source mt-3">
            <summary class="text-muted fs-8">البيانات الهندسية</summary>
            <pre class="fs-8 mt-2 mb-0 p-3" dir="ltr">{{ $location['source'] }}</pre>
        </details>
    @endif
</div>
