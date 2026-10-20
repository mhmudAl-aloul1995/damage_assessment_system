<div class="reh-export-workspace" data-export-total="{{ $totalUnits }}">
    <section class="reh-card reh-export-choice" aria-labelledby="reh-export-title">
        <div class="reh-section-heading"><div><span class="reh-eyebrow">تجهيز ملف التصدير</span><h3 id="reh-export-title">اختر صيغة الملف</h3></div><div class="reh-export-total"><strong>{{ number_format($totalUnits) }}</strong><span>وحدة مطابقة · {{ number_format($totalBuildings) }} مبنى</span></div></div>
        <div class="reh-export-formats" role="radiogroup" aria-label="صيغة ملف التصدير">
            <label class="reh-export-format"><input type="radio" name="export_format" value="excel" checked><span class="reh-format-icon">XLSX</span><span><strong>Excel</strong><small>جدول قابل للتحليل والفرز، يشمل جميع النتائج المطابقة.</small></span></label>
            <label class="reh-export-format"><input type="radio" name="export_format" value="pdf"><span class="reh-format-icon">PDF</span><span><strong>PDF</strong><small>تقرير عربي للطباعة يتضمن ملخص الفلاتر وقائمة الوحدات.</small></span></label>
        </div>
        <div class="reh-export-footer"><div><p data-export-format-help>Excel يشمل جميع النتائج المطابقة، وليس المعاينة فقط.</p><small>تُحجب بيانات الهوية والتقييم بحسب صلاحياتك. حد PDF: حتى 500 وحدة.</small></div><button type="button" class="btn btn-primary" data-download-filtered @disabled($totalUnits === 0)>تنزيل Excel</button></div>
        @if($totalUnits === 0)<div class="reh-empty">لا توجد نتائج مطابقة للتصدير. عدّل الفلاتر للمتابعة.</div>@endif
    </section>
    <section class="reh-card" aria-labelledby="reh-export-preview-title"><div class="reh-section-heading"><h4 id="reh-export-preview-title">معاينة نطاق التصدير</h4><span>أول {{ $preview->count() }} وحدات من {{ number_format($totalUnits) }}</span></div>
        @if($summary)<details class="reh-export-summary"><summary>الفلاتر المعتمدة · {{ count($summary) }}</summary><dl>@foreach($summary as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl></details>@endif
        <div class="table-responsive"><table class="table reh-table"><thead><tr><th>معرّف الوحدة</th><th>معرّف المبنى</th><th>المالك</th><th>حالة التدقيق</th><th>القرار</th></tr></thead><tbody>@forelse($preview as $unit)<tr><td>{{ $unit->old_objectid }}</td><td>{{ $unit->building->old_objectid }}</td><td>{{ $unit->owner_name ?: 'غير مسجل' }}</td><td>{{ \App\Modules\Reh\Services\RehSurvey::WORKFLOW[$unit->workflow_status] }}</td><td>{{ ['pending' => 'بانتظار القرار', 'selected' => 'مقبول مبدئيًا', 'excluded' => 'مستبعد'][$unit->decision] }}</td></tr>@empty<tr><td colspan="5">لا توجد وحدات مطابقة.</td></tr>@endforelse</tbody></table></div>
    </section>
</div>
