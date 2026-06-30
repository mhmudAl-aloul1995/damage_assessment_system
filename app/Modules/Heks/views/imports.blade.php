@extends('layouts.app')

@section('title', 'استيراد ملفات HEKS')
@section('pageName', 'استيراد الملفات')

@section('content')
    @include('heks::partials.nav')

    <div class="card card-flush mb-6">
        <div class="card-header">
            <div>
                <h3 class="card-title">استيراد ملف Excel</h3>
                <div class="text-muted">ارفع ملف HEKS الكامل مرة واحدة. النظام يقرأ الشيتات ويقسمها إلى حالات، درجات، دفعات، مرفقات، ومجموعات عمل.</div>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('heks.imports.store') }}" enctype="multipart/form-data" class="row g-4 align-items-end">
                @csrf
                <div class="col-lg-5">
                    <label class="form-label">الملف</label>
                    <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                </div>
                <div class="col-lg-4">
                    <label class="form-label">نوع الملف</label>
                    <select name="type" class="form-select">
                        <option value="scores">ملف HEKS الكامل: تقييم، اختيار، دفعات، مجموعات عمل</option>
                        <option value="labels">ملف التقييم الأولي 180 حالة</option>
                        <option value="followups">ملف المتابعة 125 مستفيد</option>
                        <option value="auto">اكتشاف تلقائي</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <button class="btn btn-primary w-100">استيراد</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-flush mb-6">
        <div class="card-header">
            <div>
                <h3 class="card-title">خريطة شيتات ملف HEKS</h3>
                <div class="text-muted">هذا الجدول يشرح وظيفة كل شيت وأين يظهر أثره داخل الموديول.</div>
            </div>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead>
                <tr class="fw-bold text-muted">
                    <th class="min-w-175px">Sheet Name</th>
                    <th class="min-w-250px">نوع البيانات</th>
                    <th class="min-w-450px">ماذا يمكن الاستفادة منها</th>
                </tr>
                </thead>
                <tbody>
                @foreach ([
                    [
                        'sheet' => 'Scoring-Heks Final',
                        'type' => 'شيت التقييم للمستفيدين والبيانات الخاصة بهم والدفعات',
                        'usage' => 'معرفة تكرار الحالات، الدفعات المالية، حالة كل مستفيد، قيم العقود والدفعات، والبيانات الأساسية للمستفيدين.',
                    ],
                    [
                        'sheet' => 'KOBO_List',
                        'type' => 'شيت البيانات الأولية للتقييم',
                        'usage' => 'معرفة عدد الوحدات، حالة الوحدات، المناطق الجغرافية، التعداد السكاني للحالات التي تم تقييمها.',
                    ],
                    [
                        'sheet' => '125 BNFs -Data',
                        'type' => 'بيانات المستفيدين الـ 125 من المشروع',
                        'usage' => 'عرض الحالات المعتمدة فقط، وربطها بملف المتابعة والدفعات والتنفيذ.',
                    ],
                    [
                        'sheet' => 'Scoring-Heks- V1',
                        'type' => 'ملف تقييم الحالات',
                        'usage' => 'المقارنة مع نسخة تقييم سابقة عند الحاجة، ومراجعة اختلافات الحساب أو التصنيف.',
                    ],
                    [
                        'sheet' => '3دفعات',
                        'type' => 'شيت البيانات المالية للمستفيدين الـ 125',
                        'usage' => 'معرفة قيم العقود والدفعات والحسابات البنكية والمفوضين.',
                    ],
                    [
                        'sheet' => 'Shelter Technical Weights',
                        'type' => 'شيت توضيحي للتقييم الفني',
                        'usage' => 'الربط مع شيت التقييم لتفسير وزن كل مؤشر فني في عملية التنقيط.',
                    ],
                    [
                        'sheet' => 'T-V',
                        'type' => 'شيت أسئلة التقييم الفني',
                        'usage' => 'تحويل الإجابات الفنية إلى قيم رقمية وربطها بعملية التنقيط.',
                    ],
                    [
                        'sheet' => 'S-V',
                        'type' => 'شيت أسئلة التقييم الاجتماعي',
                        'usage' => 'تحويل الإجابات الاجتماعية إلى قيم رقمية وربطها بعملية التنقيط.',
                    ],
                    [
                        'sheet' => 'group_un2xy00',
                        'type' => 'مرفقات مصدرة من الكوبو',
                        'usage' => 'عرض مستندات الحالة وروابطها وربطها بالمستفيد داخل صفحة التفاصيل.',
                    ],
                    [
                        'sheet' => 'group_lm1ok19',
                        'type' => 'ملفات مصدرة من الكوبو',
                        'usage' => 'عرض صور الوحدة السكنية وروابطها وربطها بالمستفيد داخل صفحة التفاصيل.',
                    ],
                    [
                        'sheet' => 'مجموعات العمل',
                        'type' => 'تقسيم الحالات على المهندسين',
                        'usage' => 'ربط كل مستفيد بالمهندس المسؤول وقيمة العقد والدفعة الأولى ومعلومات التواصل.',
                    ],
                ] as $sheet)
                    <tr>
                        <td class="fw-bold">{{ $sheet['sheet'] }}</td>
                        <td>{{ $sheet['type'] }}</td>
                        <td class="text-muted">{{ $sheet['usage'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card card-flush">
        <div class="card-header"><h3 class="card-title">سجل الاستيراد</h3></div>
        <div class="card-body table-responsive">
            <table class="table align-middle">
                <thead><tr><th>الملف</th><th>النوع</th><th>الشيتات</th><th>الإجمالي</th><th>جديد</th><th>تحديث</th><th>متجاوز</th><th>المستخدم</th><th>التاريخ</th></tr></thead>
                <tbody>
                @forelse ($imports as $import)
                    <tr>
                        <td>{{ $import->filename }}</td>
                        <td>{{ $import->type }}</td>
                        <td>{{ $import->sheet_name }}</td>
                        <td>{{ $import->total_rows }}</td>
                        <td>{{ $import->created_rows }}</td>
                        <td>{{ $import->updated_rows }}</td>
                        <td>{{ $import->skipped_rows }}</td>
                        <td>{{ $import->user?->name ?? '-' }}</td>
                        <td>{{ $import->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted">لا توجد عمليات استيراد بعد.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $imports->links() }}
        </div>
    </div>
@endsection
