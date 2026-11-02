import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Boxes,
    Building2,
    CalendarDays,
    ChevronRight,
    ContactRound,
    FileSpreadsheet,
    LayoutDashboard,
    LayoutGrid,
    MessageCircle,
    Package,
    PackageCheck,
    PlugZap,
    Route,
    Scale,
    ShieldCheck,
    ShoppingCart,
    SlidersHorizontal,
    Tags,
    Target,
    TrendingUp,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import AppLogo from './app-logo';

const savedOpen = (key: string, fallback: boolean) =>
    typeof window === 'undefined' ? fallback : window.sessionStorage.getItem(key) === null ? fallback : window.sessionStorage.getItem(key) === '1';

export function AppSidebar() {
    const page = usePage<SharedData>();
    const { setOpen } = useSidebar();
    const { auth, activeModules, moduleDisplayLocations } = page.props;
    const showInSidebar = (slug: string) => (moduleDisplayLocations[slug] ?? ['sidebar']).includes('sidebar');
    const currentPath = page.url.split('?')[0];
    // Owner is the only global-access role. Every other role, including scoped
    // Superadmin roles such as Superadmin(1000), follows its assigned permissions.
    const hasGlobalAccess = auth.roles.some((role) => role.toLowerCase().trim() === 'owner');
    const canViewDashboard = hasGlobalAccess || auth.permissions.includes('dashboard.view');
    const groupOverviewActive =
        activeModules.includes('group-overview') && (hasGlobalAccess || auth.permissions.includes('group_overview.dashboard.view'));
    const procurementActive = activeModules.includes('procurement');
    const salesActive = activeModules.includes('sales') && (hasGlobalAccess || auth.permissions.includes('sales.overview.view'));
    const salesDistributionActive =
        activeModules.includes('sales-distribution') && (hasGlobalAccess || auth.permissions.includes('sales_distribution.dashboard.view'));
    const corporateChatActive = activeModules.includes('corporate-chat');
    const canViewUsers = hasGlobalAccess || auth.permissions.includes('users.view');
    const canViewSalesCustomers = hasGlobalAccess || auth.permissions.includes('sales_distribution.customers.view');
    const canViewPendingSales = hasGlobalAccess || auth.permissions.includes('sales_distribution.pending_sales.view');
    const canViewDailySales = hasGlobalAccess || auth.permissions.includes('sales_distribution.daily_sales.view');
    const canViewCustomerSalesSummary = hasGlobalAccess || auth.permissions.includes('sales_distribution.customer_sales_summary.view');
    const canViewCollections = hasGlobalAccess || auth.permissions.includes('sales_distribution.collections.view');
    const canViewProductTargets = hasGlobalAccess || auth.permissions.includes('sales_distribution.product_targets.view');
    const canViewSalesMaster = canViewPendingSales || canViewDailySales || canViewCustomerSalesSummary || canViewCollections || canViewProductTargets;
    const canViewRoles = hasGlobalAccess || auth.permissions.includes('roles.view');
    const canViewMaterialApi = hasGlobalAccess || auth.permissions.includes('procurement.api.material.view');
    const canViewProductionApi = hasGlobalAccess || auth.permissions.includes('procurement.api.production.view');
    const canViewQualityApi = hasGlobalAccess || auth.permissions.includes('procurement.api.quality.view');
    const canViewStockApi = hasGlobalAccess || auth.permissions.includes('procurement.api.stock.view');
    const canViewVendorApi = hasGlobalAccess || auth.permissions.includes('procurement.api.vendor.view');
    const canViewEmployeeApi = canViewUsers;
    const canViewMaterials = hasGlobalAccess || auth.permissions.includes('procurement.inventory.materials.view');
    const canViewMaterialStock = hasGlobalAccess || auth.permissions.includes('procurement.inventory.material_stock.view');
    const canViewProcurementDashboard = hasGlobalAccess || auth.permissions.includes('procurement.dashboard.view');
    const canViewRmPlanningGroup = hasGlobalAccess || auth.permissions.includes('procurement.rm_planning.view');
    const canViewPlanningDecisions = hasGlobalAccess || auth.permissions.includes('procurement.planning_decisions.view');
    const canViewMaterialsBalance = hasGlobalAccess || auth.permissions.includes('procurement.materials_balance.view');
    const canViewConsumption = hasGlobalAccess || auth.permissions.includes('procurement.consumption.view');
    const canViewRmInventories = hasGlobalAccess || auth.permissions.includes('procurement.rm_inventories.view');
    const canViewRmPlanning =
        canViewRmPlanningGroup || canViewPlanningDecisions || canViewMaterialsBalance || canViewConsumption || canViewRmInventories;
    const canViewProcurement = procurementActive && (canViewProcurementDashboard || canViewRmPlanning);
    const canViewMaterialInventory = canViewRmInventories;
    const procurementApiTools = [
        ...(canViewMaterialApi ? [{ title: 'Material API', href: '/admin/api-integrations?search=material', icon: PlugZap }] : []),
        ...(canViewProductionApi ? [{ title: 'Production API', href: '/admin/api-integrations?search=production', icon: PlugZap }] : []),
        ...(canViewQualityApi ? [{ title: 'Quality API', href: '/admin/api-integrations?search=quality', icon: PlugZap }] : []),
        ...(canViewStockApi ? [{ title: 'Stock API', href: '/admin/api-integrations?search=stock', icon: PlugZap }] : []),
        ...(canViewVendorApi ? [{ title: 'Vendor API', href: '/admin/api-integrations?search=vendor', icon: PlugZap }] : []),
        ...(canViewEmployeeApi ? [{ title: 'Employee API', href: '/admin/api-integrations?search=employee', icon: PlugZap }] : []),
        ...(hasGlobalAccess ? [{ title: 'Security Audit API', href: '/admin/api-integrations?search=security', icon: ShieldCheck }] : []),
    ];
    const procurementInventoryTools = [
        ...(canViewMaterials ? [{ title: 'Materials', href: '/admin/materials', icon: Package }] : []),
        ...(canViewMaterialStock ? [{ title: 'Material Stock', href: '/admin/material-stocks', icon: PackageCheck }] : []),
    ];
    const canViewMaster =
        canViewUsers ||
        canViewRoles ||
        canViewSalesCustomers ||
        canViewMaterialInventory ||
        procurementApiTools.length > 0 ||
        procurementInventoryTools.length > 0;
    const masterGroups = [
        {
            label: 'Organization',
            items: canViewUsers
                ? [
                      { title: 'Companies', href: '/admin/companies', icon: Building2 },
                      { title: 'Departments', href: '/admin/departments', icon: Building2 },
                      { title: 'Designation levels', href: '/admin/designation-levels', icon: BadgeCheck },
                      { title: 'Designations', href: '/admin/designations', icon: BadgeCheck },
                  ]
                : [],
        },
        {
            label: 'Procurement',
            items: [
                ...(procurementApiTools.length > 0 ? [{ title: 'API Integrations', href: '/admin/api-integrations', icon: PlugZap }] : []),
                ...procurementInventoryTools,
                ...(canViewMaterialInventory ? [{ title: 'Vendor List', href: '/admin/procurement/vendor-list', icon: ShoppingCart }] : []),
            ],
        },
        {
            label: 'Main',
            items: [
                ...(canViewUsers ? [{ title: 'Modules', href: '/admin/modules', icon: Boxes }] : []),
                ...(canViewUsers ? [{ title: 'User types', href: '/admin/user-types', icon: Tags }] : []),
                ...(canViewUsers ? [{ title: 'Employee', href: '/admin/user-master', icon: ContactRound }] : []),
                ...(canViewSalesCustomers ? [{ title: 'Customer', href: '/admin/sales-distribution/customers', icon: UsersRound }] : []),
                ...(canViewRoles ? [{ title: 'Roles & permissions', href: '/admin/roles', icon: ShieldCheck }] : []),
            ],
        },
    ].filter((group) => group.items.length > 0);
    const activeMasterGroup =
        masterGroups.find((group) =>
            group.items.some((item) => (item.href.includes('?') ? page.url === item.href : currentPath.startsWith(item.href))),
        )?.label ?? null;
    const [openMasterGroup, setOpenMasterGroup] = useState<string | null>(null);
    const [masterControlOpen, setMasterControlOpen] = useState(() => savedOpen('sidebar.master-control', true));
    const [salesDistributionOpen, setSalesDistributionOpen] = useState(() => savedOpen('sidebar.sales-distribution', false));
    const [salesMasterOpen, setSalesMasterOpen] = useState(false);
    const [procurementOpen, setProcurementOpen] = useState(() => savedOpen('sidebar.procurement', false));
    const [planningOpen, setPlanningOpen] = useState(false);
    const changeSalesDistribution = (open: boolean) => {
        setSalesDistributionOpen(open);
        window.sessionStorage.setItem('sidebar.sales-distribution', open ? '1' : '0');
        if (open) {
            setProcurementOpen(false);
            window.sessionStorage.setItem('sidebar.procurement', '0');
        }
    };
    const changeProcurement = (open: boolean) => {
        setProcurementOpen(open);
        window.sessionStorage.setItem('sidebar.procurement', open ? '1' : '0');
        if (open) {
            setSalesDistributionOpen(false);
            window.sessionStorage.setItem('sidebar.sales-distribution', '0');
        }
    };
    const changeMasterControl = (open: boolean) => {
        setMasterControlOpen(open);
        window.sessionStorage.setItem('sidebar.master-control', open ? '1' : '0');
    };
    const compactItems = [
        ...(canViewDashboard ? [{ title: 'Dashboard', href: '/dashboard', icon: LayoutGrid, active: currentPath.startsWith('/dashboard') }] : []),
        ...(groupOverviewActive && showInSidebar('group-overview')
            ? [
                  {
                      title: 'Group Overview',
                      href: '/admin/group-overview',
                      icon: LayoutDashboard,
                      active: currentPath.startsWith('/admin/group-overview'),
                  },
              ]
            : []),
        ...(salesActive && showInSidebar('sales')
            ? [{ title: 'Sales', href: '/admin/sales', icon: TrendingUp, active: currentPath === '/admin/sales' }]
            : []),
        ...(salesDistributionActive && showInSidebar('sales-distribution')
            ? [
                  {
                      title: 'Sales & Distribution',
                      href: '/admin/sales-distribution',
                      icon: Route,
                      active: currentPath.startsWith('/admin/sales-distribution'),
                  },
              ]
            : []),
        ...(canViewProcurement && showInSidebar('procurement')
            ? [
                  {
                      title: 'Procurement',
                      icon: ShoppingCart,
                      active:
                          currentPath.startsWith('/admin/procurement') ||
                          currentPath.startsWith('/admin/api-integrations') ||
                          currentPath.startsWith('/admin/materials') ||
                          currentPath.startsWith('/admin/material-stocks'),
                  },
              ]
            : []),
        ...(corporateChatActive && showInSidebar('corporate-chat')
            ? [{ title: 'Corporate chat', href: '/chat', icon: MessageCircle, active: currentPath.startsWith('/chat') }]
            : []),
        ...(canViewMaster ? [{ title: 'Master Control', icon: SlidersHorizontal, active: activeMasterGroup !== null }] : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset" className="border-r border-white/10">
            <SidebarHeader className="h-16 justify-center border-b border-white/10 px-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="focus-visible:ring-brand-gold/70 h-14 overflow-hidden bg-transparent group-data-[collapsible=icon]:!size-11 group-data-[collapsible=icon]:!p-1 hover:bg-transparent focus-visible:bg-transparent focus-visible:ring-1 active:bg-transparent data-[active=true]:bg-transparent data-[slot=sidebar-menu-button]:!p-1 data-[state=open]:bg-transparent"
                        >
                            <Link href={auth.homeUrl ?? (canViewDashboard ? '/dashboard' : '/settings/profile')} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="sidebar-corporate-scroll hidden px-2 py-3 group-data-[collapsible=icon]:flex">
                <SidebarGroup className="px-0 py-1">
                    <SidebarMenu className="items-center gap-2">
                        {compactItems.map((item) => {
                            const ItemIcon = item.icon;

                            return (
                                <SidebarMenuItem key={item.title} className="flex justify-center">
                                    {'href' in item && item.href ? (
                                        <SidebarMenuButton
                                            asChild
                                            isActive={item.active}
                                            tooltip={item.title}
                                            className="h-10 w-10 justify-center rounded-lg border border-transparent p-0 text-indigo-200/75 transition-all group-data-[collapsible=icon]:size-10! group-data-[collapsible=icon]:p-0! hover:border-indigo-300/15 hover:bg-indigo-300/10 hover:text-white data-[active=true]:border-indigo-300/20 data-[active=true]:bg-indigo-300/15 data-[active=true]:text-indigo-100 data-[active=true]:shadow-[0_0_16px_rgba(165,180,252,0.16)]"
                                        >
                                            <Link href={item.href} prefetch aria-label={item.title}>
                                                <ItemIcon className="!size-[18px]" />
                                                <span className="sr-only">{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    ) : (
                                        <SidebarMenuButton
                                            type="button"
                                            isActive={item.active}
                                            tooltip={`${item.title} menu`}
                                            aria-label={`Open ${item.title} menu`}
                                            onClick={() => setOpen(true)}
                                            className="h-10 w-10 justify-center rounded-lg border border-transparent p-0 text-indigo-200/75 transition-all group-data-[collapsible=icon]:size-10! group-data-[collapsible=icon]:p-0! hover:border-indigo-300/15 hover:bg-indigo-300/10 hover:text-white data-[active=true]:border-indigo-300/20 data-[active=true]:bg-indigo-300/15 data-[active=true]:text-indigo-100 data-[active=true]:shadow-[0_0_16px_rgba(165,180,252,0.16)]"
                                        >
                                            <ItemIcon className="!size-[18px]" />
                                            <span className="sr-only">{item.title}</span>
                                        </SidebarMenuButton>
                                    )}
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarContent className="sidebar-corporate-scroll px-2 py-3 group-data-[collapsible=icon]:hidden">
                <SidebarGroup className="px-0 py-2">
                    <SidebarGroupLabel className="text-brand-aqua h-7 px-3 text-[9px] font-semibold tracking-[0.15em] uppercase group-data-[collapsible=icon]:opacity-0">
                        Main
                    </SidebarGroupLabel>
                    <SidebarMenu className="gap-1">
                        {canViewDashboard && (
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={page.url.startsWith('/dashboard')}
                                    tooltip="Dashboard"
                                    className="data-[active=true]:border-brand-gold data-[active=true]:bg-brand-berry h-10 border-l-2 border-transparent px-3 text-[13px] font-medium text-white/80 transition-all hover:bg-white/10 hover:text-white data-[active=true]:text-white data-[active=true]:shadow-sm"
                                >
                                    <Link href="/dashboard" prefetch>
                                        <LayoutGrid className="!size-[17px] shrink-0" />
                                        <span className="truncate">Dashboard</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        )}
                        {groupOverviewActive && showInSidebar('group-overview') && (
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={page.url.startsWith('/admin/group-overview')}
                                    tooltip="Group Overview"
                                    className="data-[active=true]:border-brand-gold data-[active=true]:bg-brand-berry h-10 border-l-2 border-transparent px-3 text-[13px] font-medium text-white/80 transition-all hover:bg-white/10 hover:text-white data-[active=true]:text-white data-[active=true]:shadow-sm"
                                >
                                    <Link href="/admin/group-overview" prefetch>
                                        <LayoutDashboard className="!size-[17px] shrink-0" />
                                        <span className="truncate">Group Overview</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        )}
                        {salesActive && showInSidebar('sales') && (
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={currentPath === '/admin/sales'}
                                    tooltip="Sales"
                                    className="data-[active=true]:border-brand-gold data-[active=true]:bg-brand-berry h-10 border-l-2 border-transparent px-3 text-[13px] font-medium text-white/80 transition-all hover:bg-white/10 hover:text-white data-[active=true]:text-white data-[active=true]:shadow-sm"
                                >
                                    <Link href="/admin/sales" prefetch>
                                        <TrendingUp className="!size-[17px] shrink-0" />
                                        <span className="truncate">Sales</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        )}
                        {salesDistributionActive && showInSidebar('sales-distribution') && (
                            <Collapsible
                                asChild
                                open={salesDistributionOpen}
                                onOpenChange={changeSalesDistribution}
                                className="group/sales-distribution"
                            >
                                <SidebarMenuItem>
                                    <CollapsibleTrigger asChild>
                                        <SidebarMenuButton
                                            isActive={currentPath.startsWith('/admin/sales-distribution')}
                                            tooltip="Sales & Distribution"
                                            className="data-[active=true]:border-brand-gold data-[active=true]:bg-brand-berry h-10 border-l-2 border-transparent px-3 text-[13px] font-medium text-white/80 transition-all hover:bg-white/10 hover:text-white data-[active=true]:text-white data-[active=true]:shadow-sm"
                                        >
                                            <Route className="!size-[17px] shrink-0" />
                                            <span className="truncate">Sales & Distribution</span>
                                            <ChevronRight className="text-brand-aqua ml-auto !size-3.5 transition-transform group-data-[collapsible=icon]:hidden group-data-[state=open]/sales-distribution:rotate-90" />
                                        </SidebarMenuButton>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <SidebarMenuSub className="ml-1 gap-1 border-white/15 py-1.5 pl-1 group-data-[collapsible=icon]:hidden">
                                            <SidebarMenuSubItem>
                                                <SidebarMenuSubButton
                                                    asChild
                                                    isActive={currentPath === '/admin/sales-distribution'}
                                                    className="data-[active=true]:bg-brand-aqua/15 data-[active=true]:text-brand-aqua h-8 rounded-md px-2.5 text-[13px] font-medium text-white/65 transition-colors hover:bg-white/10 hover:text-white"
                                                >
                                                    <Link href="/admin/sales-distribution" prefetch>
                                                        <LayoutGrid className="!size-3.5" />
                                                        <span>Dashboard</span>
                                                    </Link>
                                                </SidebarMenuSubButton>
                                            </SidebarMenuSubItem>
                                            {canViewSalesMaster && (
                                                <Collapsible
                                                    asChild
                                                    open={salesMasterOpen}
                                                    onOpenChange={setSalesMasterOpen}
                                                    className="group/sales-master"
                                                >
                                                    <SidebarMenuSubItem>
                                                        <CollapsibleTrigger asChild>
                                                            <SidebarMenuSubButton
                                                                isActive={
                                                                    currentPath.startsWith('/admin/sales-distribution/pending-sales') ||
                                                                    currentPath.startsWith('/admin/sales-distribution/daily-sales') ||
                                                                    currentPath.startsWith('/admin/sales-distribution/customer-sales-summary') ||
                                                                    currentPath.startsWith('/admin/sales-distribution/collections') ||
                                                                    currentPath.startsWith('/admin/sales-distribution/product-targets')
                                                                }
                                                                className="data-[active=true]:bg-brand-aqua/15 data-[active=true]:text-brand-aqua h-8 w-full rounded-md px-2.5 text-[13px] font-medium text-white/65 transition-colors hover:bg-white/10 hover:text-white"
                                                            >
                                                                <UsersRound className="!size-3.5" />
                                                                <span className="truncate whitespace-nowrap">Sales Master</span>
                                                                <ChevronRight className="ml-auto !size-3 transition-transform group-data-[state=open]/sales-master:rotate-90" />
                                                            </SidebarMenuSubButton>
                                                        </CollapsibleTrigger>
                                                        <CollapsibleContent>
                                                            <div className="ml-1 space-y-1 border-l border-white/10 py-1 pl-1">
                                                                {[
                                                                    ...(canViewPendingSales
                                                                        ? [
                                                                              {
                                                                                  title: 'Pending Order',
                                                                                  href: '/admin/sales-distribution/pending-sales',
                                                                                  icon: FileSpreadsheet,
                                                                              },
                                                                          ]
                                                                        : []),
                                                                    ...(canViewDailySales
                                                                        ? [
                                                                              {
                                                                                  title: 'Daily Sales',
                                                                                  href: '/admin/sales-distribution/daily-sales',
                                                                                  icon: TrendingUp,
                                                                              },
                                                                          ]
                                                                        : []),
                                                                    ...(canViewCustomerSalesSummary
                                                                        ? [
                                                                              {
                                                                                  title: 'Customer Sales Summary',
                                                                                  href: '/admin/sales-distribution/customer-sales-summary',
                                                                                  icon: TrendingUp,
                                                                              },
                                                                          ]
                                                                        : []),
                                                                    ...(canViewCollections
                                                                        ? [
                                                                              {
                                                                                  title: 'Collection',
                                                                                  href: '/admin/sales-distribution/collections',
                                                                                  icon: BadgeCheck,
                                                                              },
                                                                          ]
                                                                        : []),
                                                                    ...(canViewProductTargets
                                                                        ? [
                                                                              {
                                                                                  title: 'Product Targets',
                                                                                  href: '/admin/sales-distribution/product-targets',
                                                                                  icon: Target,
                                                                              },
                                                                          ]
                                                                        : []),
                                                                ].map((item) => (
                                                                    <SidebarMenuSubButton
                                                                        key={item.title}
                                                                        asChild
                                                                        isActive={currentPath.startsWith(item.href)}
                                                                        className="data-[active=true]:bg-brand-aqua/15 data-[active=true]:text-brand-aqua h-8 rounded-md px-2.5 text-[13px] font-medium text-white/65 transition-colors hover:bg-white/10 hover:text-white"
                                                                    >
                                                                        <Link href={item.href} prefetch>
                                                                            <item.icon className="!size-3.5" />
                                                                            <span>{item.title}</span>
                                                                        </Link>
                                                                    </SidebarMenuSubButton>
                                                                ))}
                                                            </div>
                                                        </CollapsibleContent>
                                                    </SidebarMenuSubItem>
                                                </Collapsible>
                                            )}
                                        </SidebarMenuSub>
                                    </CollapsibleContent>
                                </SidebarMenuItem>
                            </Collapsible>
                        )}
                        {canViewProcurement && showInSidebar('procurement') && (
                            <Collapsible asChild open={procurementOpen} onOpenChange={changeProcurement} className="group/procurement">
                                <SidebarMenuItem>
                                    <CollapsibleTrigger asChild>
                                        <SidebarMenuButton
                                            isActive={
                                                page.url.startsWith('/admin/procurement') ||
                                                page.url.startsWith('/admin/api-integrations') ||
                                                page.url.startsWith('/admin/materials') ||
                                                page.url.startsWith('/admin/material-stocks')
                                            }
                                            tooltip="Procurement"
                                            className="data-[active=true]:border-brand-gold data-[active=true]:bg-brand-berry h-10 border-l-2 border-transparent px-3 text-[13px] font-medium text-white/80 transition-all hover:bg-white/10 hover:text-white data-[active=true]:text-white data-[active=true]:shadow-sm"
                                        >
                                            <ShoppingCart className="!size-[17px] shrink-0" />
                                            <span className="truncate">Procurement</span>
                                            <ChevronRight className="text-brand-aqua ml-auto !size-3.5 transition-transform group-data-[collapsible=icon]:hidden group-data-[state=open]/procurement:rotate-90" />
                                        </SidebarMenuButton>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <SidebarMenuSub className="ml-1 gap-1 border-white/15 py-1.5 pl-1 group-data-[collapsible=icon]:hidden">
                                            {canViewProcurementDashboard && (
                                                <SidebarMenuSubItem>
                                                    <SidebarMenuSubButton
                                                        asChild
                                                        isActive={currentPath === '/admin/procurement/dashboard'}
                                                        className="data-[active=true]:bg-brand-aqua/15 data-[active=true]:text-brand-aqua h-8 rounded-md px-2.5 text-[13px] font-medium text-white/65 transition-colors hover:bg-white/10 hover:text-white"
                                                    >
                                                        <Link href="/admin/procurement/dashboard" prefetch>
                                                            <LayoutGrid className="!size-3.5" />
                                                            <span>Dashboard</span>
                                                        </Link>
                                                    </SidebarMenuSubButton>
                                                </SidebarMenuSubItem>
                                            )}
                                            {canViewRmPlanning && (
                                                <Collapsible asChild open={planningOpen} onOpenChange={setPlanningOpen} className="group/planning">
                                                    <SidebarMenuSubItem>
                                                        <CollapsibleTrigger asChild>
                                                            <SidebarMenuSubButton
                                                                isActive={
                                                                    currentPath === '/admin/procurement' ||
                                                                    currentPath.includes('/admin/procurement/daily-consumption') ||
                                                                    currentPath.includes('/admin/procurement/balance-sheet') ||
                                                                    currentPath.includes('/admin/procurement/all-balance-sheet') ||
                                                                    currentPath.includes('/admin/procurement/planning-decisions')
                                                                }
                                                                className="data-[active=true]:bg-brand-aqua/15 data-[active=true]:text-brand-aqua h-8 w-full rounded-md px-2.5 text-[13px] font-medium text-white/65 transition-colors hover:bg-white/10 hover:text-white"
                                                            >
                                                                <CalendarDays className="!size-3.5" />
                                                                <span>RM Planning</span>
                                                                <ChevronRight className="ml-auto !size-3 transition-transform group-data-[state=open]/planning:rotate-90" />
                                                            </SidebarMenuSubButton>
                                                        </CollapsibleTrigger>
                                                        <CollapsibleContent>
                                                            <div className="ml-1 space-y-1 border-l border-white/10 py-1 pl-1">
                                                                {[
                                                                    ...(canViewPlanningDecisions
                                                                        ? [
                                                                              {
                                                                                  title: 'Planning Decisions',
                                                                                  href: '/admin/procurement/planning-decisions',
                                                                                  icon: SlidersHorizontal,
                                                                                  active: currentPath.startsWith(
                                                                                      '/admin/procurement/planning-decisions',
                                                                                  ),
                                                                              },
                                                                          ]
                                                                        : []),
                                                                    ...(canViewMaterialsBalance
                                                                        ? [
                                                                              {
                                                                                  title: 'Materials Balance',
                                                                                  href: '/admin/procurement/all-balance-sheet',
                                                                                  icon: FileSpreadsheet,
                                                                                  active: currentPath.startsWith(
                                                                                      '/admin/procurement/all-balance-sheet',
                                                                                  ),
                                                                              },
                                                                          ]
                                                                        : []),
                                                                    ...(canViewConsumption
                                                                        ? [
                                                                              {
                                                                                  title: 'Consumption',
                                                                                  href: '/admin/procurement/daily-consumption',
                                                                                  icon: CalendarDays,
                                                                                  active:
                                                                                      currentPath === '/admin/procurement' ||
                                                                                      currentPath === '/admin/procurement/daily-consumption',
                                                                              },
                                                                          ]
                                                                        : []),
                                                                    ...(canViewRmInventories
                                                                        ? [
                                                                              {
                                                                                  title: 'RM Inventories',
                                                                                  href: '/admin/procurement/balance-sheet',
                                                                                  icon: Scale,
                                                                                  active: currentPath.startsWith('/admin/procurement/balance-sheet'),
                                                                              },
                                                                          ]
                                                                        : []),
                                                                ].map((item) => (
                                                                    <SidebarMenuSubButton
                                                                        key={item.title}
                                                                        asChild
                                                                        isActive={item.active}
                                                                        className="data-[active=true]:bg-brand-aqua/15 data-[active=true]:text-brand-aqua h-8 rounded-md px-2.5 text-[13px] font-medium text-white/65 transition-colors hover:bg-white/10 hover:text-white"
                                                                    >
                                                                        <Link href={item.href} prefetch>
                                                                            <item.icon className="!size-3.5" />
                                                                            <span>{item.title}</span>
                                                                        </Link>
                                                                    </SidebarMenuSubButton>
                                                                ))}
                                                            </div>
                                                        </CollapsibleContent>
                                                    </SidebarMenuSubItem>
                                                </Collapsible>
                                            )}
                                        </SidebarMenuSub>
                                    </CollapsibleContent>
                                </SidebarMenuItem>
                            </Collapsible>
                        )}
                        {corporateChatActive && showInSidebar('corporate-chat') && (
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={page.url.startsWith('/chat')}
                                    tooltip="Corporate chat"
                                    className="data-[active=true]:border-brand-gold data-[active=true]:bg-sidebar-accent h-10 border-l-2 border-transparent px-3 text-[13px] font-medium text-white/80 hover:bg-white/10 hover:text-white data-[active=true]:text-white"
                                >
                                    <Link href="/chat" prefetch>
                                        <MessageCircle className="!size-[17px] shrink-0" />
                                        <span className="truncate">Corporate chat</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        )}
                    </SidebarMenu>
                </SidebarGroup>

                {canViewMaster && (
                    <SidebarGroup className="px-0 py-2">
                        <SidebarMenu className="gap-1">
                            <Collapsible asChild open={masterControlOpen} onOpenChange={changeMasterControl} className="group/master-control">
                                <SidebarMenuItem className="overflow-hidden rounded-xl border border-white/[0.07] bg-white/[0.025] p-1 shadow-[0_8px_24px_rgba(0,0,0,0.08)]">
                                    <CollapsibleTrigger asChild>
                                        <SidebarMenuButton
                                            tooltip="Master Control"
                                            className="h-11 rounded-lg px-2.5 text-[13px] font-semibold text-white/90 transition-all hover:bg-white/[0.07] hover:text-white data-[state=open]:bg-white/[0.045]"
                                        >
                                            <span className="border-brand-aqua/20 bg-brand-aqua/10 flex size-7 shrink-0 items-center justify-center rounded-lg border">
                                                <SlidersHorizontal className="text-brand-aqua !size-3.5" />
                                            </span>
                                            <span className="tracking-[0.01em]">Master Control</span>
                                            <span className="ml-auto flex size-6 items-center justify-center rounded-md bg-white/5">
                                                <ChevronRight className="!size-3.5 transition-transform duration-200 group-data-[state=open]/master-control:rotate-90" />
                                            </span>
                                        </SidebarMenuButton>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent className="group-data-[collapsible=icon]:hidden">
                                        <div className="relative mt-1 mb-1 ml-[22px] space-y-1.5 border-l border-white/15 py-1 pl-[18px]">
                                            {masterGroups.map((group) => {
                                                const groupActive = group.items.some((item) =>
                                                    item.href.includes('?') ? page.url === item.href : currentPath.startsWith(item.href),
                                                );
                                                return (
                                                    <Collapsible
                                                        key={group.label}
                                                        open={openMasterGroup === group.label}
                                                        onOpenChange={(open) => setOpenMasterGroup(open ? group.label : null)}
                                                        className="group/master-category"
                                                    >
                                                        <CollapsibleTrigger
                                                            className={`relative flex h-9 w-full items-center gap-2 rounded-lg px-2 text-left text-[13px] font-medium transition-all duration-200 hover:bg-white/[0.06] hover:text-white ${groupActive ? 'bg-brand-aqua/[0.08] text-brand-aqua' : 'text-white/70'}`}
                                                        >
                                                            <span
                                                                className={`absolute -left-[22px] size-2 rounded-full border-2 border-[#07374b] shadow-[0_0_0_2px_rgba(255,255,255,0.05)] transition-all ${groupActive ? 'bg-brand-aqua shadow-[0_0_8px_rgba(34,211,238,0.55)]' : 'bg-slate-500'}`}
                                                            />
                                                            <span>{group.label}</span>
                                                            <ChevronRight className="ml-auto !size-3 text-white/40 transition-transform duration-200 group-data-[state=open]/master-category:rotate-90" />
                                                        </CollapsibleTrigger>
                                                        <CollapsibleContent>
                                                            <div className="mt-1 space-y-0.5 pb-1 pl-1">
                                                                {group.items.map((item) => {
                                                                    const ItemIcon = item.icon;
                                                                    const itemActive = item.href.includes('?')
                                                                        ? page.url === item.href
                                                                        : currentPath.startsWith(item.href);
                                                                    return (
                                                                        <SidebarMenuSubButton
                                                                            key={item.href}
                                                                            asChild
                                                                            isActive={itemActive}
                                                                            className="data-[active=true]:bg-brand-aqua/15 data-[active=true]:text-brand-aqua h-8 rounded-lg px-2.5 text-[13px] font-medium text-white/55 transition-all hover:translate-x-0.5 hover:bg-white/[0.07] hover:text-white"
                                                                        >
                                                                            <Link href={item.href} prefetch>
                                                                                <ItemIcon className="!size-3.5" />
                                                                                <span>{item.title}</span>
                                                                            </Link>
                                                                        </SidebarMenuSubButton>
                                                                    );
                                                                })}
                                                            </div>
                                                        </CollapsibleContent>
                                                    </Collapsible>
                                                );
                                            })}
                                        </div>
                                    </CollapsibleContent>
                                </SidebarMenuItem>
                            </Collapsible>
                        </SidebarMenu>
                    </SidebarGroup>
                )}

                <div className="mx-3 mt-4 border-t border-white/10 pt-4 group-data-[collapsible=icon]:hidden">
                    <div className="rounded-lg border border-white/10 bg-white/5 p-3">
                        <div className="flex items-center gap-2 text-[11px] font-semibold text-white">
                            <span className="size-2 rounded-full bg-emerald-400" />
                            System online
                        </div>
                        <p className="text-brand-aqua mt-1.5 text-[10px] leading-4">Enterprise services are operating normally.</p>
                    </div>
                </div>
            </SidebarContent>
        </Sidebar>
    );
}
