@forelse($logs as $log)
    @php
        $snapshot = $log->values['snapshot'] ?? null;
        $restoration = $restorations->get($log->id);
    @endphp
    <article>
        <div><strong>معرّف الحصر {{ $log->values['reference'] ?? 'غير متوفر' }} · معرّف التأهيل {{ $log->object_id }}</strong><small>{{ $log->user?->name ?? 'مستخدم غير متوفر' }} · {{ $log->created_at->format('Y-m-d H:i') }} · {{ ['database' => 'قاعدة البيانات فقط', 'arcgis' => 'ArcGIS فقط', 'both' => 'ArcGIS وقاعدة البيانات'][$log->field] }}</small></div>
        <span class="reh-maintenance-badge {{ $log->status === 'completed' ? '' : 'is-missing' }}">{{ ['completed' => 'مكتمل', 'failed' => 'لم يتم الحذف', 'pending' => 'جارٍ التنفيذ', 'needs_review' => 'يلزم التحقق', 'partial' => 'حذف جزئي'][$log->status] ?? $log->status }}</span>
        <p>{{ $log->reason }}</p><small>{{ $log->message }}</small>
        @if($snapshot)
            <div class="reh-snapshot-summary"><strong>نسخة كاملة محفوظة</strong><small>{{ count($snapshot['unit']) }} حقلًا · {{ count($snapshot['reviews']) }} مراجعات · {{ count($snapshot['cost_estimates']) }} نسخ تكلفة · {{ count($snapshot['remote']['attachments'] ?? []) }} مرفقات مؤرشفة</small><div class="reh-maintenance-actions"><a href="{{ route('reh.maintenance.snapshot', $log) }}" class="btn btn-sm btn-light">تنزيل حقول النسخة JSON</a>@if($log->status === 'completed' && !$restoration)<button type="button" class="btn btn-sm btn-light-primary" data-restore-unit="{{ route('reh.maintenance.restore', $log) }}" data-reference="{{ $log->values['reference'] ?? 'غير متوفر' }}" data-destination="{{ ['database' => 'قاعدة البيانات', 'arcgis' => 'ArcGIS والسجل المحلي المرتبط', 'both' => 'ArcGIS وقاعدة البيانات'][$log->field] }}">استرجاع النسخة</button>@endif</div></div>
        @else
            <small>أرشيف سابق: لا تتوفر نسخة كاملة للاسترجاع الآلي.</small>
        @endif
        @if($restoration)
            <div class="reh-restoration-result" data-restoration @if(in_array($restoration->status, ['queued', 'running'], true)) data-restore-status="{{ route('reh.maintenance.restore.status', $restoration) }}" @endif>
                <strong>الاسترجاع</strong><p data-restoration-message role="status">{{ $restoration->message }}</p><small>{{ $restoration->user?->name ?? 'مستخدم غير متوفر' }} · {{ $restoration->reason }}</small>
                @if(!empty($restoration->values['resumes']))<small>آخر متابعة: {{ collect($restoration->values['resumes'])->last()['name'] }} · {{ collect($restoration->values['resumes'])->last()['at'] }}</small>@endif
                <div class="reh-maintenance-actions"><button type="button" class="btn btn-sm btn-light-primary" data-resume-restore="{{ route('reh.maintenance.restore.resume', $restoration) }}" @if($restoration->status === 'completed') hidden @endif>التحقق والاستكمال</button><a class="btn btn-sm btn-light-primary" data-restored-link @if($restoration->status === 'completed') href="{{ route('reh.units.show', $restoration->values['restored_unit_id']) }}" @else hidden @endif>فتح الوحدة المسترجعة</a></div>
            </div>
        @endif
        @if(in_array($log->status, ['pending', 'needs_review'], true))<button type="button" class="btn btn-sm btn-light-primary" data-verify-deletion="{{ route('reh.maintenance.verify', $log) }}">التحقق من نتيجة الحذف</button>@endif
    </article>
@empty
    <p class="text-muted mt-5">لا توجد عمليات حذف مسجلة.</p>
@endforelse
@if($history?->hasPages())<nav class="reh-maintenance-pagination" aria-label="صفحات سجل الحذف">@if($history->previousPageUrl())<a class="btn btn-sm btn-light" href="{{ $history->previousPageUrl() }}">الأحدث</a>@endif<span>صفحة {{ $history->currentPage() }} من {{ $history->lastPage() }}</span>@if($history->nextPageUrl())<a class="btn btn-sm btn-light" href="{{ $history->nextPageUrl() }}">الأقدم</a>@endif</nav>@endif
