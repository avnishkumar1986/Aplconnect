import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Factory, LayoutDashboard, Route, ShoppingCart, TrendingUp } from 'lucide-react';

export function BusinessModuleTabs() {
    const page = usePage<SharedData>();
    const { auth, activeModules, moduleDisplayLocations } = page.props;
    const isOwner = auth.roles.some((role) => role.toLowerCase().trim() === 'owner');
    const currentPath = page.url.split('?')[0];

    const modules = [
        {
            slug: 'group-overview',
            title: 'Group Overview',
            href: '/admin/group-overview',
            icon: LayoutDashboard,
            allowed: isOwner || auth.permissions.includes('group_overview.dashboard.view'),
            active: currentPath.startsWith('/admin/group-overview'),
            classes: 'border-indigo-200 bg-gradient-to-r from-indigo-50 to-violet-100 text-indigo-950 hover:border-indigo-400',
            iconClasses: 'from-indigo-500 to-violet-600',
            arrowClasses: 'text-indigo-700',
        },
        {
            slug: 'production-capacity',
            title: 'Production & Capacity',
            href: '/admin/production-capacity',
            icon: Factory,
            allowed: isOwner || auth.permissions.includes('production_capacity.dashboard.view'),
            active: currentPath.startsWith('/admin/production-capacity'),
            classes: 'border-blue-200 bg-gradient-to-r from-blue-50 to-cyan-100 text-blue-950 hover:border-blue-400',
            iconClasses: 'from-blue-600 to-cyan-600',
            arrowClasses: 'text-blue-700',
        },
        {
            slug: 'sales',
            title: 'Sales',
            href: '/admin/sales',
            icon: TrendingUp,
            allowed: isOwner || auth.permissions.some((permission) => permission.startsWith('sales.')),
            active: currentPath.startsWith('/admin/sales') && !currentPath.startsWith('/admin/sales-distribution'),
            classes: 'border-emerald-200 bg-gradient-to-r from-emerald-50 to-teal-100 text-emerald-950 hover:border-emerald-400',
            iconClasses: 'from-emerald-500 to-teal-600',
            arrowClasses: 'text-emerald-700',
        },
        {
            slug: 'sales-distribution',
            title: 'Sales & Distribution',
            href: '/admin/sales-distribution',
            icon: Route,
            allowed: isOwner || auth.permissions.includes('sales_distribution.dashboard.view'),
            active: currentPath.startsWith('/admin/sales-distribution'),
            classes: 'border-amber-200 bg-gradient-to-r from-amber-50 to-orange-100 text-amber-950 hover:border-amber-400',
            iconClasses: 'from-amber-500 to-orange-600',
            arrowClasses: 'text-amber-700',
        },
        {
            slug: 'procurement',
            title: 'Procurement',
            href: '/admin/procurement/dashboard',
            icon: ShoppingCart,
            allowed: isOwner || auth.permissions.some((permission) => permission.startsWith('procurement.')),
            active: currentPath.startsWith('/admin/procurement'),
            classes: 'border-cyan-200 bg-gradient-to-r from-cyan-50 to-sky-100 text-cyan-950 hover:border-cyan-400',
            iconClasses: 'from-cyan-500 to-sky-600',
            arrowClasses: 'text-cyan-700',
        },
    ].filter(
        (module) => activeModules.includes(module.slug) && (moduleDisplayLocations[module.slug] ?? ['sidebar']).includes('tabs') && module.allowed,
    );

    if (modules.length === 0) return null;

    return (
        <div className="min-w-0 shrink-0 bg-slate-50/70 px-3 pt-2 sm:px-5">
            <nav className="flex min-w-0 gap-2.5 overflow-x-auto pb-1" aria-label="Business modules">
                {modules.map((module) => {
                    const Icon = module.icon;

                    return (
                        <Link
                            key={module.slug}
                            href={module.href}
                            prefetch
                            aria-current={module.active ? 'page' : undefined}
                            className={`group inline-flex h-10 shrink-0 items-center gap-2 rounded-lg border px-3 text-[13px] font-semibold shadow-sm transition-all duration-150 hover:-translate-y-px hover:shadow-md focus-visible:ring-2 focus-visible:ring-cyan-300/60 focus-visible:outline-none active:translate-y-0 active:scale-[0.98] ${module.classes} ${module.active ? 'shadow-md ring-2 ring-slate-400/30' : ''}`}
                        >
                            <span
                                className={`grid size-6 place-items-center rounded-md bg-gradient-to-br text-white shadow-sm ${module.iconClasses}`}
                            >
                                <Icon className="size-3.5" />
                            </span>
                            {module.title}
                            <ArrowRight className={`size-3.5 transition-transform group-hover:translate-x-0.5 ${module.arrowClasses}`} />
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}
