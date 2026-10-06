@props(['id', 'title', 'icon' => 'dashboard', 'open' => false])
<div class="sidebar-group"><button type="button" class="sidebar-group-button" data-ui-sidebar-group="{{ $id }}"
        aria-expanded="{{ $open ? 'true' : 'false' }}"><span class="nav-icon"><x-app-icon :name="$icon" /></span><span
            class="nav-label">{{ $title }}</span><svg
            class="sidebar-chevron transition-transform {{ $open ? 'rotate-90' : '' }}" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2">
            <path d="m9 18 6-6-6-6" />
        </svg></button>
    <div id="{{ $id }}" class="sidebar-submenu {{ $open ? '' : 'hidden' }}">{{ $slot }}</div>
</div>
