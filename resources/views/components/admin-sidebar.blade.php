<div id="sidebar-overlay" class="fixed inset-0 z-40 hidden bg-slate-950/60 backdrop-blur-sm lg:hidden" aria-hidden="true">
</div>
@php
    $procurementModuleStatus = \Illuminate\Support\Facades\Schema::hasTable('tbl_modules')
        ? \App\Models\Module::where('slug', 'procurement')->value('status')
        : null;
    $procurementModuleActive = $procurementModuleStatus === null || $procurementModuleStatus === 'active';
@endphp
<aside id="sidebar"
    class="corporate-sidebar fixed inset-y-0 left-0 z-50 flex w-[min(18rem,86vw)] -translate-x-full flex-col overflow-visible text-white transition-transform duration-300 ease-out lg:w-72 lg:translate-x-0">
    <div class="sidebar-brand flex shrink-0 items-center justify-center">
        <a href="{{ route('admin.dashboard') }}" aria-label="APL Connect dashboard" class="sidebar-wordmark">
            <span class="sidebar-brand-mark" aria-hidden="true"><img src="{{ asset('images/apl-apollo-logo.webp') }}" alt=""></span>
            <span class="sidebar-brand-copy">
                <strong>APL <em>Connect</em></strong>
                <small class="enterprise-workspace-label">Enterprise workspace</small>
            </span>
        </a>
    </div>
    <nav class="flex-1 overflow-y-auto" aria-label="Main navigation">
        <p class="nav-section">Main</p>
        @can('dashboard.view')
            <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                href="{{ route('admin.dashboard') }}"><span class="nav-icon"><x-app-icon name="dashboard" /></span><span
                    class="nav-label">Dashboard</span></a>
        @endcan
        @if($procurementModuleActive)
          @canany(['procurement.overview.view', 'procurement.all_materials.view',
              'procurement.daily_consumption.view'])
            <x-sidebar-group id="procurement-menu" title="Procurement" icon="procurement" :open="request()->routeIs('admin.procurement.*')">
                @can('procurement.overview.view')
                    <a class="nav-link {{ request()->routeIs('admin.procurement.overview') ? 'active' : '' }}"
                        href="{{ route('admin.procurement.overview') }}"><span class="nav-label">Overview</span></a>
                @endcan
                @can('procurement.overview.view')
                    <a class="nav-link {{ request()->routeIs('admin.procurement.material-balance') ? 'active' : '' }}"
                        href="{{ route('admin.procurement.material-balance') }}"><span class="nav-label">Material
                            Balance</span></a>
                @endcan
                @can('procurement.all_materials.view')
                    <a class="nav-link {{ request()->routeIs('admin.procurement.materials') ? 'active' : '' }}"
                        href="{{ route('admin.procurement.materials') }}"><span class="nav-label">All Materials</span></a>
                @endcan
                @can('procurement.daily_consumption.view')
                    <a class="nav-link {{ request()->routeIs('admin.procurement.consumptions*') ? 'active' : '' }}"
                        href="{{ route('admin.procurement.consumptions') }}"><span class="nav-label">Daily
                            Consumption</span></a>
                @endcan
            </x-sidebar-group>
          @endcanany
        @endif
        <p class="nav-section nav-section-more">More</p>
        @can('modules.view')
            <x-sidebar-group id="modules-menu" title="Modules" icon="modules" :open="request()->routeIs('admin.modules.*')">
                <a class="nav-link {{ request()->routeIs('admin.modules.*') ? 'active' : '' }}"
                    href="{{ route('admin.modules.index') }}"><span class="nav-label">Module Upload</span></a>
            </x-sidebar-group>
        @endcan
        @can('theme-settings.view')
            <a class="nav-link {{ request()->routeIs('admin.theme-settings.*') ? 'active' : '' }}"
                href="{{ route('admin.theme-settings.edit') }}"><span class="nav-icon"><x-app-icon
                        name="theme" /></span><span class="nav-label">Theme</span></a>
        @endcan
        @canany(['users.view', 'user-types.view', 'companies.view', 'roles.view', 'permissions.view',
            'procurement.all_materials.view', 'api-integrations.view'])
            <x-sidebar-group id="master-menu" title="Master Control" icon="master" :open="request()->routeIs('admin.roles.*','admin.permissions.*','admin.users.*','admin.user-types.*','admin.companies.*','admin.organization-structure.*','admin.materials.*','admin.vendors.*','admin.api-integrations.*') || (request()->routeIs('admin.dummy.show') && request()->route('section') === 'master-control')">
                @canany(['companies.view', 'users.view', 'user-types.view'])
                    @php($organizationOpen = request()->routeIs('admin.companies.*','admin.users.*','admin.user-types.*','admin.organization-structure.*'))
                    <button type="button" class="master-subsection-toggle" data-ui-sidebar-group="master-organization" aria-expanded="{{ $organizationOpen ? 'true' : 'false' }}"><span>Organization</span><svg class="{{ $organizationOpen ? 'rotate-90' : '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button>
                    <div id="master-organization" class="master-subsection-menu {{ $organizationOpen ? '' : 'hidden' }}">
                @endcanany
                @can('companies.view')
                    <a class="nav-link {{ request()->routeIs('admin.companies.*') ? 'active' : '' }}"
                        href="{{ route('admin.companies.index') }}"><span class="nav-label">Companies & Plants</span></a>
                @endcan
                @can('users.view')
                    <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                        href="{{ route('admin.users.index') }}"><span class="nav-label">Users</span></a>
                @endcan
                @can('user-types.view')
                    <a class="nav-link {{ request()->routeIs('admin.user-types.*') ? 'active' : '' }}"
                        href="{{ route('admin.user-types.index') }}"><span class="nav-label">User Types</span></a>
                @endcan
                @can('users.view')
                    <a class="nav-link {{ request()->routeIs('admin.organization-structure.*') ? 'active' : '' }}"
                        href="{{ route('admin.organization-structure.index') }}"><span class="nav-label">Departments</span></a>
                @endcan
                @canany(['companies.view', 'users.view', 'user-types.view'])
                    </div>
                @endcanany
                @if($procurementModuleActive)
                  @can('procurement.all_materials.view')
                    @php($procurementMasterOpen = request()->routeIs('admin.materials.*','admin.vendors.*'))
                    <button type="button" class="master-subsection-toggle" data-ui-sidebar-group="master-procurement" aria-expanded="{{ $procurementMasterOpen ? 'true' : 'false' }}"><span>Procurement</span><svg class="{{ $procurementMasterOpen ? 'rotate-90' : '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button>
                    <div id="master-procurement" class="master-subsection-menu {{ $procurementMasterOpen ? '' : 'hidden' }}">
                    <a class="nav-link {{ request()->routeIs('admin.materials.*') ? 'active' : '' }}"
                        href="{{ route('admin.materials.index') }}"><span class="nav-label">All Materials</span></a>
                    <a class="nav-link {{ request()->routeIs('admin.vendors.*') ? 'active' : '' }}"
                        href="{{ route('admin.vendors.index') }}"><span class="nav-label">Vendors</span></a>
                    </div>
                  @endcan
                @endif
                @canany(['roles.view', 'permissions.view', 'api-integrations.view'])
                    @php($securityOpen = request()->routeIs('admin.roles.*','admin.permissions.*','admin.api-integrations.*'))
                    <button type="button" class="master-subsection-toggle" data-ui-sidebar-group="master-security" aria-expanded="{{ $securityOpen ? 'true' : 'false' }}"><span>Security</span><svg class="{{ $securityOpen ? 'rotate-90' : '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button>
                    <div id="master-security" class="master-subsection-menu {{ $securityOpen ? '' : 'hidden' }}">
                @endcanany
                @can('roles.view')
                    <a class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}"
                        href="{{ route('admin.roles.index') }}"><span class="nav-label">Roles</span></a>
                @endcan
                @can('permissions.view')
                    <a class="nav-link {{ request()->routeIs('admin.permissions.*') ? 'active' : '' }}"
                        href="{{ route('admin.permissions.index') }}"><span class="nav-label">Permissions</span></a>
                @endcan
                @can('api-integrations.view')
                    <a class="nav-link {{ request()->routeIs('admin.api-integrations.*') ? 'active' : '' }}"
                        href="{{ route('admin.api-integrations.index') }}"><span class="nav-label">Integrations</span></a>
                @endcan
                @canany(['roles.view', 'permissions.view', 'api-integrations.view'])
                    </div>
                @endcanany
            </x-sidebar-group>
        @endcanany
        @can('action-logs.view')
            <x-sidebar-group id="monitoring-menu" title="Monitoring" icon="monitoring" :open="request()->routeIs('admin.action-logs.*')">
                <a class="nav-link {{ request()->routeIs('admin.action-logs.*') ? 'active' : '' }}"
                    href="{{ route('admin.action-logs.index') }}"><span class="nav-label">Action Logs</span></a>
            </x-sidebar-group>
        @endcan
        <form method="POST" action="{{ route('logout') }}" class="sidebar-logout-form">@csrf
            <button type="submit" class="nav-link sidebar-logout"><span class="nav-icon"><x-app-icon
                        name="logout" /></span><span class="nav-label">Logout</span></button>
        </form>
    </nav>
</aside>
