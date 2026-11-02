import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { BusinessModuleTabs } from '@/components/business-module-tabs';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    const showBreadcrumbs = usePage().url.split('?')[0] === '/dashboard' && breadcrumbs.length > 0;

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="h-svh min-h-0 overflow-x-hidden overflow-y-auto">
                <AppSidebarHeader />
                <main className="flex min-h-0 max-w-full min-w-0 flex-1 flex-col overflow-x-hidden">
                    <BusinessModuleTabs />
                    {showBreadcrumbs && (
                        <div className="min-w-0 shrink-0 bg-slate-50/70 px-3 pt-1.5 sm:px-5">
                            <div className="flex h-8 min-w-0 items-center overflow-x-auto rounded-md border border-slate-200 bg-white px-3 shadow-sm shadow-slate-900/5">
                                <span className="mr-3 h-4 w-1 shrink-0 rounded-full bg-gradient-to-b from-cyan-500 to-teal-600" aria-hidden="true" />
                                <Breadcrumbs breadcrumbs={breadcrumbs} />
                            </div>
                        </div>
                    )}
                    {children}
                </main>
                <footer className="border-t border-slate-200 bg-white px-3 py-2 text-center text-[10px] text-slate-400 sm:px-6 sm:text-[11px]">
                    Copyright © {new Date().getFullYear()} <span className="text-brand-plum font-semibold">Apollo Pipes Private Limited</span>. All
                    rights reserved.
                </footer>
            </AppContent>
        </AppShell>
    );
}
