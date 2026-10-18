@extends('layouts.app')
@section('title', 'حضور موظفي المشاريع')
@section('pageName', 'موظفو المشاريع')
@section('content')
<div dir="rtl" lang="ar" class="d-flex flex-column gap-6">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div><h1 class="fs-2 fw-bold">تجهيز حضور موظفي المشاريع</h1><p class="text-muted mb-0">حساب واحد للموظف، وتكليف مستقل لكل مشروع وفترة دوام.</p></div>
        <a class="btn btn-light" href="{{ route('hr.home') }}">رئيسية الموارد البشرية</a>
    </div>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="alert alert-primary mb-0">لا يشترط أن يكون الموظف مثبتًا. الغياب يُراجع في أيام دوامه خلال فترة التكليف فقط؛ نهاية المشروع لا تلغي حسابه في المنظومة.</div>
    <div class="row g-5">
        <div class="col-xl-4"><div class="card h-100">
            <div class="card-header"><h2 class="card-title">١. إضافة المشروع</h2></div>
            <form class="card-body d-flex flex-column gap-4" method="post" action="{{ route('hr.attendance.projects.store') }}">@csrf
                <div><label for="project-name" class="form-label">اسم المشروع</label><input id="project-name" name="name" class="form-control" maxlength="255" value="{{ old('name') }}" required></div>
                <div><label for="project-code" class="form-label">رمز المشروع</label><input id="project-code" name="code" class="form-control" maxlength="50" value="{{ old('code') }}" required></div>
                <div><label for="project-start" class="form-label">بداية المشروع</label><input id="project-start" name="starts_on" type="date" class="form-control" value="{{ old('starts_on') }}" required></div>
                <div><label for="project-end" class="form-label">نهاية المشروع إن كانت محددة</label><input id="project-end" name="ends_on" type="date" class="form-control" value="{{ old('ends_on') }}"></div>
                <p class="text-muted mb-0">أدخل بيانات المشروع الفعلية. يمكنك إضافة أكثر من تكليف للموظف على المشروع حسب مدة عمله وجدوله.</p>
                <button class="btn btn-primary" type="submit">إضافة المشروع</button>
            </form>
        </div></div>
        <div class="col-xl-4"><div class="card h-100">
            <div class="card-header"><h2 class="card-title">٢. ربط حساب الموظف مرة واحدة</h2></div>
            <form class="card-body d-flex flex-column gap-4" method="post" action="{{ route('hr.attendance.projects.employees.store') }}">@csrf
                <p class="text-muted mb-0">إذا كان الموظف موجودًا في القائمة بالخطوة الثالثة، استخدم سجله الحالي.</p>
                @include('hr::attendance-identity-fields')
                <button class="btn btn-primary" type="submit">حفظ ملف الحضور وربط الحساب</button>
            </form>
        </div></div>
        <div class="col-xl-4"><div class="card h-100">
            <div class="card-header"><h2 class="card-title">٣. تكليف الموظف ودوامه</h2></div>
            <form class="card-body d-flex flex-column gap-4" method="post" action="{{ route('hr.attendance.projects.assignments.store') }}">@csrf
                <div><label for="assignment-employee" class="form-label">الموظف</label><select id="assignment-employee" name="employee_id" class="form-select" required><option value="">اختر الموظف الموجود</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->display_name }} · {{ $employee->employee_number }}</option>@endforeach</select></div>
                <div><label for="assignment-project" class="form-label">المشروع</label><select id="assignment-project" name="project_id" class="form-select" required><option value="">اختر المشروع</option>@foreach($projectOptions as $project)<option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>{{ $project->name }} · {{ $project->code }}</option>@endforeach</select></div>
                <div><label for="assignment-schedule" class="form-label">جدول الدوام</label><select id="assignment-schedule" name="work_schedule_id" class="form-select" required><option value="">اختر الجدول</option>@foreach($schedules as $schedule)<option value="{{ $schedule->id }}" @selected((string) old('work_schedule_id') === (string) $schedule->id)>{{ $schedule->name }}</option>@endforeach</select><a class="d-inline-block mt-2" href="{{ route('hr.attendance.setup') }}">إضافة جدول بأيام وساعات الدوام</a></div>
                <div><label for="assignment-start" class="form-label">أول يوم للتكليف</label><input id="assignment-start" name="starts_on" type="date" class="form-control" value="{{ old('starts_on') }}" required></div>
                <div><label for="assignment-end" class="form-label">آخر يوم للتكليف</label><input id="assignment-end" name="ends_on" type="date" class="form-control" value="{{ old('ends_on') }}" required></div>
                <label class="form-check"><input class="form-check-input" type="checkbox" name="enable_native_attendance" value="1" @checked(old('enable_native_attendance'))><span class="form-check-label">تفعيل الحضور عبر التطبيق للموظف المرتبط بحساب</span></label>
                <button class="btn btn-primary" type="submit">حفظ التكليف والدوام</button>
            </form>
        </div></div>
    </div>
    <div class="card"><div class="card-header"><h2 class="card-title">المشاريع المسجلة</h2></div><div class="card-body">
        <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr><th>المشروع</th><th>الفترة</th><th>تكليفات الدوام</th></tr></thead><tbody>
            @forelse($projects as $project)<tr><td><strong>{{ $project->name }}</strong><div class="text-muted">{{ $project->code }}</div></td><td>{{ $project->starts_on->toDateString() }} — {{ $project->ends_on?->toDateString() ?? 'نهاية غير محددة' }}</td><td>{{ $project->assignments_count }}</td></tr>
            @empty<tr><td colspan="3" class="text-center py-6">ابدأ بإضافة أول مشروع من النموذج أعلاه.</td></tr>@endforelse
        </tbody></table></div>{{ $projects->withQueryString()->links() }}
    </div></div>
    <div class="card"><div class="card-header"><h2 class="card-title">تكليفات موظفي المشاريع</h2></div><div class="card-body">
        <p class="text-muted">لتغيير الدوام، أنهِ التكليف الحالي من إعداد الحضور وأضف تكليفًا جديدًا. يبقى سجل الموظف السابق محفوظًا.</p>
        <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr><th>الموظف</th><th>المشروع</th><th>الدوام</th><th>فترة التكليف</th><th>الحضور</th></tr></thead><tbody>
            @forelse($assignments as $assignment)<tr><td>{{ $assignment->employee->display_name }}<div class="text-muted">{{ $assignment->employee->employee_number }}</div></td><td>{{ $assignment->project->name }}</td><td>{{ $assignment->schedule->name }}</td><td>{{ $assignment->starts_on->toDateString() }} — {{ $assignment->effectiveEndsOn()?->toDateString() ?? 'مستمر' }}</td><td>@can('viewAny', \App\Modules\HR\Models\Employee::class)<a href="{{ route('hr.attendance.index', ['employee_id' => $assignment->employee_id]) }}">عرض سجل الموظف</a>@endcan</td></tr>
            @empty<tr><td colspan="5" class="text-center py-6">لا توجد تكليفات مشاريع بعد.</td></tr>@endforelse
        </tbody></table></div>{{ $assignments->withQueryString()->links() }}
    </div></div>
</div>
@endsection
