<header
    class="admin-topbar sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-slate-200/80 bg-white/95 px-4 backdrop-blur sm:h-20 sm:px-6 lg:px-8">
    <button id="sidebar-open" type="button"
        class="grid h-10 w-10 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white text-slate-800 shadow-sm lg:hidden"
        aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false"><svg class="h-5 w-5" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 7h16M4 12h16M4 17h16" />
        </svg></button>
    <button id="sidebar-close" type="button"
        class="sidebar-header-toggle hidden h-9 w-9 shrink-0 place-items-center lg:grid"
        aria-label="Toggle compact navigation"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2">
            <path d="M5 7h14M5 12h14M5 17h14" />
        </svg></button>
    <div
        class="hidden h-10 max-w-md flex-1 items-center rounded-xl border border-slate-200/70 bg-slate-50 px-3 lg:flex">
        <svg class="h-4 w-4 text-[#008f9d]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.5-3.5" />
        </svg><span class="ml-2 text-sm text-slate-400">Search APL Connect…</span>
    </div>
    <div class="ml-auto flex items-center gap-2 sm:gap-3"><x-header-notifications /><x-header-profile /></div>
</header>
