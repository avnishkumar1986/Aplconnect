<div class="relative"><button type="button" class="header-icon" aria-label="Notifications"
        data-ui-dropdown="notifications-menu" aria-expanded="false"><span
            class="absolute right-2 top-2 h-2 w-2 rounded-full bg-red-400 ring-2 ring-white"></span><svg class="h-5 w-5"
            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
        </svg></button>
    <div id="notifications-menu" class="dropdown-panel hidden w-[min(22rem,calc(100vw-2rem))]">
        <div class="dropdown-heading">
            <div><strong>Notifications</strong>
                <p>3 unread updates</p>
            </div><span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">3 new</span>
        </div>
        <div class="divide-y divide-slate-100"><a class="notification-item"
                href="{{ route('admin.crud.index', 'users') }}"><span
                    class="notification-icon bg-blue-50 text-blue-700">U</span><span><strong>Employee profile
                        updated</strong><small>User records were updated recently.</small></span><time>5m</time></a><a
                class="notification-item" href="{{ route('admin.crud.index', 'education') }}"><span
                    class="notification-icon bg-emerald-50 text-emerald-700">E</span><span><strong>Education review
                        pending</strong><small>A qualification requires verification.</small></span><time>1h</time></a>
        </div>
    </div>
</div>
