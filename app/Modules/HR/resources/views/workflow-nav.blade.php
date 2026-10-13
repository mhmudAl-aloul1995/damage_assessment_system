<nav class="d-flex flex-wrap gap-3 mb-5" aria-label="صفحات الحضور">
    @can('viewAnyProfiles', \App\Modules\HR\Models\Employee::class)<a class="btn btn-sm btn-light-primary" href="{{ route('hr.employees.index') }}">الموظفون</a>@endcan
    @can('viewAnyProfiles', \App\Modules\HR\Models\Employee::class)<a class="btn btn-sm btn-light-primary" href="{{ route('hr.organization.chart') }}">مخطط الهيكل</a>@endcan
    @can('hr.organization.manage')<a class="btn btn-sm btn-light-primary" href="{{ route('hr.organization.index', 'departments') }}">الهيكل التنظيمي</a>@endcan
    @can('viewAny', \App\Modules\HR\Models\Employee::class)
    <a class="btn btn-sm btn-light-primary" href="{{ route('hr.attendance.index') }}">الحضور والتقارير</a>
    <a class="btn btn-sm btn-light-primary" href="{{ route('hr.attendance.corrections') }}">التصحيحات والموافقات</a>
    <a class="btn btn-sm btn-light-primary" href="{{ route('hr.attendance.reports') }}">ملخص وتقارير Excel</a>
    <a class="btn btn-sm btn-light-primary" href="{{ route('hr.attendance.exceptions') }}">الاستثناءات والتسوية</a>
    @if(auth()->user()->can('hr.attendance.setup') || auth()->user()->can('hr.attendance.exceptions.manage'))<a class="btn btn-sm btn-light-primary" href="{{ route('hr.attendance.calendar') }}">العطلات والاستثناءات المعتمدة</a>@endif
    @can('hr.attendance.audit')<a class="btn btn-sm btn-light-primary" href="{{ route('hr.attendance.audit') }}">سجل التدقيق</a>@endcan
    @can('hr.attendance.audit')<a class="btn btn-sm btn-light-primary" href="{{ route('hr.attendance.security-report') }}">أمن الحضور</a>@endcan
    @can('hr.devices.view')<a class="btn btn-sm btn-light-primary" href="{{ route('hr.attendance.devices') }}">أجهزة الحضور</a>@endcan
    @endcan
</nav>
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
