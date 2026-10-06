@props(['name'])
<svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor"
    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" />
            <rect x="14" y="3" width="7" height="7" />
            <rect x="3" y="14" width="7" height="7" />
            <rect x="14" y="14" width="7" height="7" />
        @break

        @case('users')
            <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
        @break

        @case('accounts')
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path d="M8 9h8M8 13h5" />
        @break

        @case('user-types')
            <path d="M20 7h-9M14 17H5M17 4l3 3-3 3M8 14l-3 3 3 3" />
        @break

        @case('addresses')
            <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1116 0z" />
            <circle cx="12" cy="10" r="2" />
        @break

        @case('contacts')
            <path d="M4 4h16v16H4zM8 8h8M8 12h5M8 16h7" />
        @break

        @case('education')
            <path d="M2 10l10-5 10 5-10 5zM6 12v5c3 2 9 2 12 0v-5" />
        @break

        @case('roles')
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            <path d="M9 12l2 2 4-4" />
        @break

        @case('permissions')
            <circle cx="8" cy="15" r="3" />
            <path d="M10.5 13L20 3M15 5l2 2M17 3l2 2" />
        @break

        @case('posts')
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <path d="M14 2v6h6M8 13h8M8 17h6M8 9h2" />
        @break

        @case('pages')
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <path d="M14 2v6h6" />
        @break

        @case('media')
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <circle cx="8.5" cy="8.5" r="1.5" />
            <path d="m21 15-5-5L5 21" />
        @break

        @case('procurement')
            <circle cx="9" cy="20" r="1" />
            <circle cx="19" cy="20" r="1" />
            <path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 8H6" />
        @break

        @case('company')
            <path d="M4 21V5l8-3v19M12 8h8v13M7 7h2M7 11h2M7 15h2M15 11h2M15 15h2M2 21h20" />
        @break

        @case('plant')
            <path d="M3 21V10l6 3V9l6 4V7l6 4v10H3Z" />
            <path d="M7 17h2M12 17h2M17 17h2M5 10V4h3v7" />
        @break

        @case('active-purchases')
            <path d="M3 4h2l2.2 10.4a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 2-1.6L20 8H6" />
            <circle cx="10" cy="20" r="1" /><circle cx="18" cy="20" r="1" /><path d="M14 4h7M18 1l3 3-3 3" />
        @break

        @case('purchased')
            <path d="M4 7l8-4 8 4-8 4-8-4Z" /><path d="M4 7v10l8 4 8-4V7M12 11v10" />
            <path d="M8 5l8 4" />
        @break

        @case('consumed')
            <path d="M4 19V9M10 19V5M16 19v-7M22 19V3" /><path d="M2 21h20" />
        @break

        @case('balance')
            <path d="M12 3v18M5 6h14M7 6l-4 7h8L7 6ZM17 6l-4 7h8l-4-7ZM8 21h8" />
        @break

        @case('materials')
            <path d="M4 7l4-3 4 3-4 3-4-3ZM12 7l4-3 4 3-4 3-4-3ZM4 15l4-3 4 3-4 3-4-3ZM12 15l4-3 4 3-4 3-4-3Z" />
        @break

        @case('purchase-entry')
            <path d="M9 5h6M9 3h6v4H9zM6 5H4v16h16V5h-2" /><path d="M8 12h8M8 16h5" />
        @break

        @case('daily-consumption')
            <path d="M12 2s6 7 6 12a6 6 0 0 1-12 0c0-5 6-12 6-12Z" /><path d="M9 15c.8 1.3 1.8 2 3 2" />
        @break

        @case('modules')
            <circle cx="12" cy="12" r="3" />
            <circle cx="12" cy="5" r="3" />
            <circle cx="6" cy="15.5" r="3" />
            <circle cx="18" cy="15.5" r="3" />
        @break

        @case('theme')
            <path d="M12 3a9 9 0 0 0 0 18h1.5a1.5 1.5 0 0 0 0-3H12a2 2 0 0 1 0-4h2a7 7 0 0 0 0-14Z" />
            <circle cx="7.5" cy="10" r=".8" />
            <circle cx="9" cy="6.5" r=".8" />
            <circle cx="14" cy="6" r=".8" />
            <circle cx="17.5" cy="9" r=".8" />
        @break

        @case('master')
            <circle cx="8" cy="15" r="3" />
            <path d="M10.5 13 20 3M15 5l2 2M5.5 17.5 3 20M8 12V9" />
        @break

        @case('settings')
            <circle cx="12" cy="12" r="3" />
            <path
                d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1 1.56V21h-4v-.09a1.7 1.7 0 0 0-1-1.56 1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.56-1H3v-4h.09a1.7 1.7 0 0 0 1.56-1 1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.56V3h4v.09a1.7 1.7 0 0 0 1 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9a1.7 1.7 0 0 0 1.56 1H21v4h-.09a1.7 1.7 0 0 0-1.51 1Z" />
        @break

        @case('monitoring')
            <rect x="3" y="4" width="18" height="14" rx="2" />
            <path d="M8 22h8M12 18v4" />
        @break

        @case('logout')
            <path d="M10 17l5-5-5-5M15 12H3" />
            <path d="M10 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7" />
        @break

        @default
            <circle cx="12" cy="12" r="9" />
            <path d="M12 8v8M8 12h8" />
    @endswitch
</svg>
