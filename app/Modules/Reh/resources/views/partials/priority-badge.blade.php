@php($priorityStyle = array_key_exists($priority ?? '', \App\Modules\Reh\Services\RehScoring::PRIORITIES) ? $priority : 'pending')
<span class="reh-priority-badge reh-priority-{{ $priorityStyle }}" data-priority="{{ $priorityStyle }}"><span aria-hidden="true" class="reh-priority-dot"></span>{{ \App\Modules\Reh\Services\RehScoring::PRIORITIES[$priorityStyle] ?? 'الأولوية غير مصنفة' }}</span>
