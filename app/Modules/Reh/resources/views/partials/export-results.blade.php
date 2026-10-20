<div class="reh-export-workspace" data-export-total="{{ $totalUnits }}">
    <section class="reh-card reh-export-choice" aria-labelledby="reh-export-title">
        <div class="reh-section-heading"><div><span class="reh-eyebrow">تجهيز ملف التصدير</span><h3 id="reh-export-title">اختر صيغة الملف</h3></div><div class="reh-export-total"><strong>{{ number_format($totalUnits) }}</strong><span>وحدة مطابقة · {{ number_format($totalBuildings) }} مبنى</span></div></div>
        <div class="reh-export-formats" role="radiogroup" aria-label="صيغة ملف التصدير">
            <label class="reh-export-format"><input type="radio" name="export_format" value="excel" checked><span class="reh-format-icon">XLSX</span><span><strong>Excel</strong><small>جدول قابل للتحليل والفرز، يشمل جميع النتائج المطابقة.</small></span></label>
            <label class="reh-export-format"><input type="radio" name="export_format" value="pdf"><span class="reh-format-icon">PDF</span><span><strong>PDF</strong><small>تقرير عربي للطباعة يتضمن ملخص الفلاتر وقائمة الوحدات.</small></span></label>
        </div>
        <div class="reh-export-columns">
            <div class="reh-section-heading"><div><label for="reh-export-columns">الأعمدة التي ستظهر في الملف</label><p>حقول الاستبيان كاملة، مع أعمدة الملخص والتقييم. ابحث باسم السؤال واختر ما يلزم.</p></div><span class="reh-export-column-count" data-export-column-count aria-live="polite">{{ count($selectedColumns) }} من {{ count($availableColumns) }} عمود</span></div>
            <select id="reh-export-columns" class="form-select" multiple data-export-columns aria-describedby="reh-export-columns-help">
                @foreach(collect($availableColumns)->groupBy('group', preserveKeys: true) as $group => $columns)
                    <optgroup label="{{ $group }}">@foreach($columns as $key => $column)<option value="{{ $key }}" @if(!isset($column['field'])) data-export-basic @endif @selected(in_array($key, $selectedColumns, true))>{{ $column['label'] }}</option>@endforeach</optgroup>
                @endforeach
            </select>
            <div class="reh-export-column-actions"><div><button type="button" data-export-select-all>تحديد الكل</button><button type="button" data-export-basic-columns>الأعمدة الأساسية</button><button type="button" data-export-clear-columns>مسح الاختيار</button><button type="button" data-export-toggle-columns aria-expanded="false" hidden>عرض بقية المختار</button></div><small id="reh-export-columns-help">تظهر الحقول حسب صلاحياتك. حقول الصور تُصدّر كمراجع ملفات.</small></div>
            <p class="reh-export-column-warning" data-export-column-warning role="status" hidden>اختر عمودًا واحدًا على الأقل لتفعيل التنزيل.</p>
        </div>
        <div class="reh-export-footer"><div><p data-export-format-help>Excel يشمل جميع النتائج المطابقة، وليس المعاينة فقط.</p><small>تُحجب بيانات الهوية والتقييم بحسب صلاحياتك. حد PDF: حتى 500 وحدة.</small></div><button type="button" class="btn btn-primary" data-download-filtered @disabled($totalUnits === 0)>تنزيل Excel</button></div>
        @if($totalUnits === 0)<div class="reh-empty">لا توجد نتائج مطابقة للتصدير. عدّل الفلاتر للمتابعة.</div>@endif
    </section>
    <section class="reh-card" aria-labelledby="reh-export-preview-title"><div class="reh-section-heading"><h4 id="reh-export-preview-title">معاينة نطاق التصدير</h4><span>أول {{ $preview->count() }} وحدات من {{ number_format($totalUnits) }}</span></div>
        @if($summary)<details class="reh-export-summary"><summary>الفلاتر المعتمدة · {{ count($summary) }}</summary><dl>@foreach($summary as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl></details>@endif
        <div class="table-responsive reh-export-preview-scroll" role="region" aria-label="معاينة أعمدة التصدير" tabindex="0"><table class="table reh-table"><thead><tr>@foreach($availableColumns as $key => $column)<th data-export-column-key="{{ $key }}" @if(!in_array($key, $selectedColumns, true)) hidden @endif>{{ $column['label'] }}</th>@endforeach</tr></thead><tbody>@forelse($previewRows as $row)<tr>@foreach($availableColumns as $key => $column)<td data-export-column-key="{{ $key }}" @if(!in_array($key, $selectedColumns, true)) hidden @endif>{{ $row[$key] ?? 'غير متوفر' }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($availableColumns) }}">لا توجد وحدات مطابقة.</td></tr>@endforelse</tbody></table></div>
    </section>
</div>
