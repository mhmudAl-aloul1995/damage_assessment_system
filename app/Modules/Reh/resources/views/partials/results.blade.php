<div class="reh-section-heading"><div><h3>{{ ['overview' => 'المشروع في لمحة', 'units' => 'ملفات الوحدات السكنية', 'buildings' => 'المباني والتوزيع الجغرافي', 'reports' => 'مؤشرات المتابعة', 'sync' => 'استقبال البيانات الميدانية'][$tab] }}</h3><p>{{ $lastSync ? 'آخر مزامنة مكتملة: '.$lastSync->finished_at->format('Y-m-d H:i') : 'لم تكتمل المزامنة الأولى بعد' }}</p></div><span class="badge badge-light-primary">المرحلة 2</span></div>
@if($buildingObjectId)<div class="alert alert-info">النتائج مقيّدة بالمبنى {{ $buildingObjectId }}. استخدم «مسح الفلاتر» للعودة إلى جميع السجلات.</div>@endif
@if(in_array($tab, ['overview', 'reports']))
    <div class="reh-metrics">
        @foreach([['units', 'الوحدات المستقبلة', 'units', ''], ['buildings', 'المباني', 'buildings', ''], ['ready', 'مكتمل التدقيق', 'units', 'ready'], ['returned', 'تحتاج استكمالًا', 'units', 'returned'], ['second_assessment', 'تقييم هندسي ثانٍ', 'units', 'second_assessment']] as [$key, $label, $target, $status])
            <button type="button" class="reh-metric" data-metric-tab="{{ $target }}" data-metric-status="{{ $status }}"><span>{{ $label }}</span><strong>{{ number_format($stats[$key]) }}</strong><small>عرض السجلات ←</small></button>
        @endforeach
    </div>
    <div class="reh-callout"><span class="reh-callout-icon">◎</span><div><strong>الكميات جاهزة للمراجعة، والتكلفة بانتظار التسعير</strong><p>السعر غير المتوفر لا يُحتسب صفرًا. القبول المبدئي منفصل عن اعتماد التكلفة.</p></div></div>
@endif
@if($tab === 'reports')
    <div class="reh-callout"><div><strong>الدرجة النهائية والأولوية تُحسبان للسجلات المكتملة</strong><p>تتوفر النقاط المحسومة وأسباب تعليق المؤشرات داخل ملفات الوحدات. لا تُرتّب الوحدات حسب مجموع جزئي ولا تُستنتج أولوية من بيانات ناقصة.</p></div></div>
    <div class="reh-report-grid"><article class="reh-card"><h4>توزيع مراحل التدقيق</h4>@foreach($survey::WORKFLOW as $key => $label)<div class="reh-report-row"><span>{{ $label }}</span><strong>{{ $counts[$key] ?? 0 }}</strong><progress max="{{ max(1, $stats['units']) }}" value="{{ $counts[$key] ?? 0 }}" aria-label="{{ $label }}"></progress></div>@endforeach</article><article class="reh-card"><h4>قرارات الوحدات ضمن الفلاتر الحالية</h4><div class="reh-report-row"><span>مقبول مبدئيًا</span><strong>{{ $stats['selected'] }}</strong></div><div class="reh-report-row"><span>مستبعد بقرار موثق</span><strong>{{ $stats['excluded'] }}</strong></div><div class="reh-report-row"><span>بانتظار القرار</span><strong>{{ $stats['units'] - $stats['selected'] - $stats['excluded'] }}</strong></div><p class="text-muted">تُحسب كل وحدة مرة واحدة. أعداد الأسر المستضافة لا تُجمع مع أسرة المالك في هذه المؤشرات.</p></article></div>
@endif
@if($unitRows)
    <div class="reh-card"><div class="reh-section-heading"><h4>{{ $tab === 'overview' ? 'أحدث ملفات العمل' : 'الوحدات' }}</h4><span>{{ $unitRows->total() }} وحدة</span></div>
    <div class="table-responsive"><table class="table align-middle reh-table"><thead><tr><th>معرّف حصر الوحدة / المالك</th><th>معرّف حصر المبنى والموقع</th><th>الفريق</th><th>حالة التدقيق</th><th>القرار</th><th>التسعير</th><th>التقييم والأولوية</th><th></th></tr></thead><tbody>
    @forelse($unitRows as $unit)
        <tr><td><a data-detail href="{{ route('reh.units.show', $unit) }}" class="fw-bold">وحدة {{ $unit->old_objectid }}</a>@if($unit->code)<small>الكود: {{ $unit->code }}</small>@endif<small>{{ $unit->owner_name ?: 'اسم المالك غير مسجل' }}</small><small>طابق {{ $unit->floor_number ?? '—' }} · شقة {{ $unit->apartment_number ?? '—' }}</small></td><td>مبنى {{ $unit->building->old_objectid }}<small>{{ $unit->building->name }}</small><small>{{ $survey->value('governorate', $unit->building->governorate) }} · {{ $survey->value('neighborhood', $unit->building->neighborhood) }}</small></td><td>{{ $survey->value('engineer_name', $unit->building->engineer) }}</td><td><span class="badge badge-light-{{ in_array($unit->workflow_status, ['returned', 'source_changed', 'second_assessment']) ? 'warning' : ($unit->workflow_status === 'ready' ? 'success' : 'primary') }}">{{ $survey::WORKFLOW[$unit->workflow_status] }}</span>@if($unit->source_missing || $unit->building->source_missing)<small class="text-danger">غير موجود في المصدر</small>@endif</td><td>{{ ['pending' => 'بانتظار القرار', 'selected' => 'مقبول مبدئيًا', 'excluded' => 'مستبعد'][$unit->decision] }}</td><td>
            @can('reh.pricing.view')
                @php($cost = $unitPricing->get($unit->id))
                <div data-unit-pricing="{{ $unit->id }}">
                @if($cost)
                    @php($hasPricedLines = collect($cost['lines'])->contains(fn (array $line): bool => $line['amount'] !== null))
                    @if($cost['complete'] || $hasPricedLines)
                        <strong class="d-block text-nowrap" dir="ltr">{{ number_format($cost['usd'],2) }} USD</strong>
                        @if(!$cost['complete'])<small>مبلغ جزئي</small>@endif
                    @endif
                    <span class="badge badge-light-{{ $cost['complete'] ? 'success' : 'warning' }}">{{ $cost['complete'] ? 'مكتمل التسعير' : 'تسعير غير مكتمل' }}</span>
                    @if(!$cost['complete'])<small>{{ $cost['missing'][0] }}@if(count($cost['missing']) > 1) · و{{ count($cost['missing']) - 1 }} ملاحظة أخرى@endif</small>@endif
                @else
                    <span class="badge badge-light">بانتظار قائمة أسعار معتمدة</span>
                @endif
                <button type="button" class="btn btn-sm btn-light-primary mt-2" data-price-unit="{{ route('reh.pricing.unit',$unit) }}">تفاصيل التكلفة</button>
                </div>
            @else<small>تحتاج صلاحية عرض التسعير</small>@endcan
        </td><td><div class="reh-score-cell">
            @can('reh.scores.view')
                <a data-detail data-detail-section="scoring" href="{{ route('reh.units.show', $unit) }}" class="reh-final-summary {{ $scores[$unit->id]['total'] === null ? 'is-pending' : '' }}" aria-label="الدرجة النهائية للوحدة {{ $unit->old_objectid }}: {{ $scores[$unit->id]['total'] ?? 'بانتظار الاستكمال' }}">
                    <span class="reh-final-label">الدرجة النهائية</span>
                    <strong class="reh-final-value" dir="ltr" data-final-score="{{ $scores[$unit->id]['total'] }}">{{ $scores[$unit->id]['total'] ?? '—' }} <span>/ 100</span></strong>
                    @if($scores[$unit->id]['total'] === null)<span class="reh-final-pending">بانتظار الاستكمال</span>@endif
                </a>
                @include('reh::partials.priority-badge', ['priority' => $scores[$unit->id]['priority']])
            @else<small>الدرجة والأولوية تحتاجان صلاحية عرض التقييم</small>@endcan
            <div class="reh-score-breakdown" aria-label="تفصيل الدرجات">
                @can('reh.sensitive')
                    <a data-detail data-detail-section="scoring" href="{{ route('reh.units.show', $unit) }}" class="reh-social-summary" data-social-score="{{ $scores[$unit->id]['social_total'] }}" @if($scores[$unit->id]['social_total'] === null) title="الاجتماعي: بانتظار الاستكمال" @endif><span>الاجتماعي:</span> <bdi dir="ltr">{{ $scores[$unit->id]['social_total'] ?? '—' }} / 60</bdi>@if($scores[$unit->id]['social_total'] === null)<span class="visually-hidden"> · بانتظار الاستكمال</span>@endif</a>
                @endcan
                <a data-detail data-detail-section="scoring" href="{{ route('reh.units.show', $unit) }}" class="reh-engineering-summary"><span>الهندسي:</span> <bdi dir="ltr">{{ $scores[$unit->id]['engineering']['subtotal'] }} / 40</bdi></a>
            </div>
            @if($scores[$unit->id]['engineering']['pending_count'] > 0)<small>{{ $scores[$unit->id]['engineering']['pending_count'] }} فئة هندسية معلقة</small>@endif
        </div></td><td><a data-detail href="{{ route('reh.units.show', $unit) }}" class="btn btn-sm btn-light-primary">فتح الملف</a></td></tr>
    @empty<tr><td colspan="8"><div class="reh-empty"><strong>{{ $lastSync ? 'لا توجد وحدات مطابقة' : 'مساحة العمل جاهزة لاستقبال الوحدات' }}</strong><p>{{ $lastSync ? 'جرّب تغيير البحث أو مسح الفلاتر.' : 'ابدأ المزامنة من قسم المزامنة بعد تجهيز اتصال ArcGIS.' }}</p></div></td></tr>@endforelse
    </tbody></table></div>@include('reh::partials.pagination', ['paginator' => $unitRows])</div>
@endif
@if($buildingRows)
    <div class="reh-card">
    <div class="reh-map-heading"><div><h4>من المبنى إلى ملف الوحدة</h4><p>اختر مبنى من الخريطة أو الجدول لعرض وحداته المطابقة للفلاتر.</p></div>
        <div class="reh-map-modes" role="group" aria-label="تلوين المباني حسب">
            <button type="button" data-map-mode="survey" aria-pressed="true">حالة الاستبيان</button>
            <button type="button" data-map-mode="status" aria-pressed="false">حالة التدقيق</button>
            @can('reh.scores.view')<button type="button" data-map-mode="priority" aria-pressed="false">الأولوية</button>@endcan
        </div>
    </div>
    <div data-map-legend class="reh-map-legend" aria-label="دليل ألوان المباني"></div>
    <div class="reh-map-layout"><div><div id="reh-map" class="reh-map" aria-label="خريطة مباني الصفحة الحالية"></div><p data-map-coverage class="reh-map-coverage" role="status"></p></div>
        <aside class="reh-map-selection" aria-labelledby="reh-map-selection-title"><h4 id="reh-map-selection-title">وحدات المبنى</h4><div data-map-selection aria-live="polite"><div class="reh-empty">اختر مبنى لعرض معرّفات الوحدات وحالات تدقيق وحداته.</div></div></aside>
    </div>
    <p class="text-muted" data-map-explanation>الخريطة تعرض مباني الصفحة الحالية فقط.</p>
    <div class="table-responsive"><table class="table align-middle reh-table"><thead><tr><th>معرّف حصر المبنى</th><th>الموقع</th><th>المهندس</th><th>الوحدات</th><th>السلامة الميدانية</th><th></th></tr></thead><tbody>
    @forelse($buildingRows as $building)<tr data-map-building-row="{{ $building->id }}"><td><button type="button" class="reh-map-building-link" data-map-building="{{ $building->id }}">مبنى {{ $building->old_objectid }}</button><small>{{ $building->name }}</small>@if($building->source_missing)<small class="text-danger">غير موجود في المصدر</small>@endif</td><td>{{ $survey->value('governorate', $building->governorate) }}<small>{{ $survey->value('neighborhood', $building->neighborhood) }}</small></td><td>{{ $survey->value('engineer_name', $building->engineer) }}</td><td><button type="button" class="btn btn-sm btn-light" data-building-units="{{ $building->id }}">{{ $building->units_count }} وحدة</button></td><td>{{ $survey->value('building_structurally_safe', $building->surveyData()['building_structurally_safe'] ?? null) }}</td><td><a data-detail href="{{ route('reh.buildings.show', $building) }}" class="btn btn-sm btn-light-primary">التفاصيل</a></td></tr>@empty<tr><td colspan="6"><div class="reh-empty">لا توجد مبانٍ مطابقة للفلاتر.</div></td></tr>@endforelse
    </tbody></table></div>@include('reh::partials.pagination', ['paginator' => $buildingRows])
    <div hidden id="reh-map-data" data-statuses="{{ json_encode($survey::WORKFLOW) }}" data-priorities="{{ json_encode(\App\Modules\Reh\Services\RehScoring::PRIORITIES) }}" data-buildings="{{ json_encode($mapBuildings) }}"></div></div>
@endif
@if($tab === 'sync')
    @can('reh.sync')
    @if($issueCount > 0)<div class="alert alert-warning">{{ $issueCount }} وحدة محفوظة تحتاج إصلاح الربط بالمبنى في المصدر. لا تدخل هذه الوحدات في أعداد الملفات الجاهزة للتدقيق.</div>@endif
    @php($syncRunning = $runs->contains('status', 'running'))
    <div class="reh-card"><div class="reh-section-heading"><div><h4>Survey123 → مساحة التدقيق</h4><p>تُقرأ المباني والوحدات من الخدمة، وتُحفظ القرارات محليًا مع سجل إجراءات.</p></div><button type="button" class="btn btn-primary" data-sync @disabled($syncRunning)>{{ $syncRunning ? 'المزامنة قيد التنفيذ…' : 'مزامنة الآن' }}</button></div><p class="text-muted">تبدأ المزامنة تلقائيًا بالخلفية عند الضغط على الزر، وتُحدّث الحالة والأعداد تلقائيًا. يمكنك متابعة العمل أثناء المزامنة.</p>
    <div class="table-responsive"><table class="table reh-table"><thead><tr><th>الوقت</th><th>الحالة</th><th>المباني</th><th>الوحدات</th><th>التفاصيل</th></tr></thead><tbody>@forelse($runs as $run)<tr><td>{{ $run->created_at->format('Y-m-d H:i') }}</td><td>{{ ['queued' => 'بانتظار التنفيذ', 'running' => 'قيد التنفيذ', 'completed' => 'مكتملة', 'completed_with_warnings' => 'مكتملة مع ملاحظات ربط', 'failed' => 'لم تكتمل'][$run->status] }}</td><td>{{ $run->buildings_count }}</td><td>{{ $run->units_count }}</td><td>{{ $run->message ?? 'بانتظار عامل قائمة الانتظار' }}</td></tr>@empty<tr><td colspan="5"><div class="reh-empty">لم تبدأ أي مزامنة بعد.</div></td></tr>@endforelse</tbody></table></div>
    @if($syncIssues->isNotEmpty())<h4>وحدات تحتاج إصلاح الربط — أول 100 حالة</h4><p>حُفظت بياناتها الأصلية. بعد تصحيح الربط في ArcGIS، أعد المزامنة لاستقبالها تلقائيًا.</p><table class="table"><thead><tr><th>معرّف حصر الوحدة في المصدر</th><th>سبب التعليق</th></tr></thead><tbody>@foreach($syncIssues as $issue)<tr><td>{{ \App\Modules\Reh\Services\RehSurvey::reference($issue->source['attributes'] ?? []) }}</td><td>{{ $issue->reason }}</td></tr>@endforeach</tbody></table>@endif</div>
    @else<div class="reh-empty">قسم المزامنة يحتاج صلاحية إدارة المزامنة.</div>@endcan
@endif
