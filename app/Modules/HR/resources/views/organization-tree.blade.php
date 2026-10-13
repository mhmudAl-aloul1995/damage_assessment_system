<ul class="list-unstyled border-end border-primary pe-4">
@forelse($nodes as $node)
<li class="my-3"><details open><summary class="fw-bold fs-5">{{ $node['name'] }} @if(!$employees && !$node['active'])<span class="badge badge-light">مؤرشف</span>@endif</summary>
@if($employees)<div class="my-2"><a href="{{ route('hr.employees.show', $node['id']) }}">{{ $node['number'] }}</a><span class="text-muted"> — {{ $node['title'] ?? 'بدون تكليف ساري' }} — {{ $node['department'] ?? '' }}</span></div>@endif
@if($node['children'] !== [])@include('hr::organization-tree', ['nodes'=>$node['children'], 'employees'=>$employees])@endif
</details></li>
@empty<li class="text-muted">لا توجد سجلات في هذا النطاق.</li>@endforelse
</ul>
