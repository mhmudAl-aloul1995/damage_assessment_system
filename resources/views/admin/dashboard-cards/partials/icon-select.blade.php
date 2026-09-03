@php
    $selectedIcon = old($name, $selectedIcon ?? 'ki-dot');
@endphp

<div class="icon-picker js-icon-picker">
    <span class="icon-preview-box">
        <i class="ki-duotone js-icon-preview {{ $selectedIcon }} fs-2">
            <span class="path1"></span>
            <span class="path2"></span>
            <span class="path3"></span>
            <span class="path4"></span>
            <span class="path5"></span>
        </i>
    </span>
    <select name="{{ $name }}" class="form-select ltr-input js-icon-select" required>
        @foreach ($icons as $iconClass => $iconLabel)
            <option value="{{ $iconClass }}" @selected($selectedIcon === $iconClass)>{{ $iconLabel }} - {{ $iconClass }}</option>
        @endforeach

        @if ($selectedIcon && ! array_key_exists($selectedIcon, $icons))
            <option value="{{ $selectedIcon }}" selected>{{ $selectedIcon }}</option>
        @endif
    </select>
</div>
