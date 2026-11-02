import { DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { type SharedData, type User } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Boxes, ClipboardCheck, Headphones, Inbox, LogOut, Settings, UserRound, WalletCards } from 'lucide-react';

interface UserMenuContentProps {
    user: User;
    showHeader?: boolean;
}

export function UserMenuContent({ user, showHeader = true }: UserMenuContentProps) {
    const cleanup = useMobileNavigation();
    const { auth, activeModules, moduleDisplayLocations } = usePage<SharedData>().props;
    const isOwner = auth.roles.some((role) => role.toLowerCase().trim() === 'owner');
    const dropdownModules = [
        {
            slug: 'group-overview',
            title: 'Group Overview',
            href: '/admin/group-overview',
            allowed: isOwner || auth.permissions.includes('group_overview.dashboard.view'),
        },
        {
            slug: 'procurement',
            title: 'Procurement',
            href: '/admin/procurement/dashboard',
            allowed: isOwner || auth.permissions.some((permission) => permission.startsWith('procurement.')),
        },
        { slug: 'sales', title: 'Sales', href: '/admin/sales', allowed: isOwner || auth.permissions.includes('sales.overview.view') },
        {
            slug: 'sales-distribution',
            title: 'Sales & Distribution',
            href: '/admin/sales-distribution',
            allowed: isOwner || auth.permissions.includes('sales_distribution.dashboard.view'),
        },
        { slug: 'corporate-chat', title: 'Corporate chat', href: '/chat', allowed: true },
    ].filter(
        (module) =>
            activeModules.includes(module.slug) && (moduleDisplayLocations[module.slug] ?? ['sidebar']).includes('dropdown') && module.allowed,
    );
    const canManageSupport =
        auth.roles.some((role) => role.toLowerCase() === 'owner') ||
        auth.permissions.includes('support.manage') ||
        auth.permissions.includes('support.view-all');

    return (
        <>
            {showHeader && (
                <>
                    <DropdownMenuLabel className="p-2 font-normal">
                        <div className="rounded-xl bg-slate-50 px-2 py-2 text-left text-sm">
                            <UserInfo user={user} showEmail={true} />
                        </div>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                </>
            )}
            {dropdownModules.length > 0 && (
                <>
                    <DropdownMenuLabel className="px-3 py-1.5 text-xs text-slate-500">Modules</DropdownMenuLabel>
                    {dropdownModules.map((module) => (
                        <DropdownMenuItem key={module.slug} asChild>
                            <Link
                                className="mx-1 flex min-h-10 w-[calc(100%-0.5rem)] items-center gap-3 rounded-lg px-3 font-medium text-slate-700"
                                href={module.href}
                                onClick={cleanup}
                            >
                                <Boxes className="size-4.5 text-cyan-700" />
                                {module.title}
                            </Link>
                        </DropdownMenuItem>
                    ))}
                    <DropdownMenuSeparator />
                </>
            )}
            <DropdownMenuItem asChild>
                <Link
                    className="mx-1 flex min-h-11 w-[calc(100%-0.5rem)] items-center gap-3 rounded-lg px-3 font-medium text-slate-700"
                    href="/settings/profile"
                    onClick={cleanup}
                >
                    <UserRound className="size-4.5 text-slate-500" />
                    Profile
                </Link>
            </DropdownMenuItem>
            <DropdownMenuItem asChild>
                <Link
                    className="mx-1 flex min-h-11 w-[calc(100%-0.5rem)] items-center gap-3 rounded-lg px-3 font-medium text-slate-700"
                    href="/chat"
                    onClick={cleanup}
                >
                    <Inbox className="size-4.5 text-slate-500" />
                    Inbox
                </Link>
            </DropdownMenuItem>
            <DropdownMenuItem disabled className="mx-1 min-h-11 gap-3 rounded-lg px-3 font-medium">
                <ClipboardCheck className="size-4.5 text-slate-400" />
                Task Manager
            </DropdownMenuItem>
            <DropdownMenuItem asChild>
                <Link
                    className="mx-1 flex min-h-11 w-[calc(100%-0.5rem)] items-center gap-3 rounded-lg px-3 font-medium text-slate-700"
                    href="/settings/password"
                    onClick={cleanup}
                >
                    <Settings className="size-4.5 text-slate-500" />
                    Settings
                </Link>
            </DropdownMenuItem>
            <DropdownMenuItem disabled className="mx-1 min-h-11 gap-3 rounded-lg px-3 font-medium">
                <WalletCards className="size-4.5 text-slate-400" />
                Balance
                <span className="ml-auto text-xs text-slate-400">—</span>
            </DropdownMenuItem>
            <DropdownMenuItem asChild>
                <Link
                    className="mx-1 flex min-h-11 w-[calc(100%-0.5rem)] items-center gap-3 rounded-lg px-3 font-medium text-slate-700"
                    href="/support"
                    onClick={cleanup}
                >
                    <Headphones className="size-4.5 text-slate-500" />
                    {canManageSupport ? 'Support Desk' : 'Create Ticket'}
                </Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator className="my-1" />
            <DropdownMenuItem asChild>
                <Link
                    className="mx-1 flex min-h-11 w-[calc(100%-0.5rem)] items-center gap-3 rounded-lg px-3 font-medium text-red-600 focus:bg-red-50 focus:text-red-700"
                    method="post"
                    href={route('logout')}
                    as="button"
                    onClick={cleanup}
                >
                    <LogOut className="size-4.5" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
