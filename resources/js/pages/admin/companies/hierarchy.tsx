import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Building2, ChevronDown, ChevronLeft, ChevronRight, Factory, Landmark, Package, Search, Unlink } from 'lucide-react';
import { useState } from 'react';

type CompanyNode = {
    id: number;
    code: string;
    name: string;
    type: number;
    parent_code: string | null;
    active: boolean;
    children: CompanyNode[];
};
type CompanyMaterial = {
    code: string;
    name: string;
    stock: number;
    active: boolean;
};
type MaterialGroup = { code: string; total: number; items: CompanyMaterial[] };
type CompanyMaterials = {
    type: string;
    total: number;
    stock: number;
    groups: MaterialGroup[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Company hierarchy', href: '/admin/companies/hierarchy' },
];
const typeDetails = {
    1: {
        label: 'Company',
        icon: Building2,
        color: 'border-cyan-200 bg-cyan-50 text-cyan-800',
        iconColor: 'bg-cyan-600 text-white',
    },
    2: {
        label: 'Plant',
        icon: Factory,
        color: 'border-amber-200 bg-amber-50 text-amber-800',
        iconColor: 'bg-amber-500 text-white',
    },
    3: {
        label: 'Head Office',
        icon: Landmark,
        color: 'border-violet-200 bg-violet-50 text-violet-800',
        iconColor: 'bg-violet-600 text-white',
    },
} as const;

function MaterialSlider({ items }: { items: CompanyMaterial[] }) {
    const pageSize = 6;
    const [page, setPage] = useState(0);
    const pageCount = Math.max(1, Math.ceil(items.length / pageSize));
    const visibleItems = items.slice(page * pageSize, page * pageSize + pageSize);

    return (
        <div>
            <div className="grid gap-2 sm:grid-cols-2">
                {visibleItems.map((material) => (
                    <div key={material.code} className="min-h-24 rounded-lg border border-slate-100 bg-slate-50 p-3">
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0">
                                <p className="font-mono text-xs font-semibold text-slate-500">{material.code}</p>
                                <p className="mt-1 text-sm font-semibold text-slate-800" title={material.name}>
                                    {material.name}
                                </p>
                            </div>
                            <span className={`mt-1 size-2 shrink-0 rounded-full ${material.active ? 'bg-emerald-500' : 'bg-slate-300'}`} />
                        </div>
                    </div>
                ))}
            </div>
            {pageCount > 1 && (
                <div className="mt-3 flex items-center justify-between border-t border-slate-100 pt-3">
                    <span className="text-xs font-medium text-slate-500">
                        {page * pageSize + 1}–{Math.min((page + 1) * pageSize, items.length)} of {items.length}
                    </span>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            aria-label="Previous materials"
                            disabled={page === 0}
                            onClick={() => setPage((current) => Math.max(0, current - 1))}
                            className="grid size-8 place-items-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <ChevronLeft className="size-4" />
                        </button>
                        <span className="min-w-16 text-center text-xs font-bold text-slate-600">
                            {page + 1} / {pageCount}
                        </span>
                        <button
                            type="button"
                            aria-label="Next materials"
                            disabled={page >= pageCount - 1}
                            onClick={() => setPage((current) => Math.min(pageCount - 1, current + 1))}
                            className="grid size-8 place-items-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <ChevronRight className="size-4" />
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

function HierarchyNode({
    node,
    materialsByCompany,
    depth = 0,
    showChildren = true,
    showMaterials = false,
}: {
    node: CompanyNode;
    materialsByCompany: Record<string, CompanyMaterials>;
    depth?: number;
    showChildren?: boolean;
    showMaterials?: boolean;
}) {
    const details = typeDetails[node.type as keyof typeof typeDetails] ?? typeDetails[1];
    const Icon = details.icon;
    return (
        <div
            className={
                depth
                    ? 'relative ml-7 border-l-2 border-slate-200 pl-7 before:absolute before:top-7 before:left-0 before:w-7 before:border-t-2 before:border-slate-200'
                    : ''
            }
        >
            <div
                className={`group relative mb-3 overflow-hidden rounded-2xl border bg-white p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-lg ${
                    depth === 0 ? 'border-cyan-200 shadow-cyan-950/5' : 'border-slate-200 shadow-slate-900/5'
                }`}
            >
                <span
                    className={`absolute inset-y-0 left-0 w-1 ${node.type === 1 ? 'bg-cyan-500' : node.type === 2 ? 'bg-amber-400' : 'bg-violet-500'}`}
                />
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex min-w-0 items-center gap-3">
                        <span className={`grid size-11 shrink-0 place-items-center rounded-xl shadow-sm ring-4 ring-slate-50 ${details.iconColor}`}>
                            <Icon className="size-5" />
                        </span>
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h3 className="truncate text-base font-bold text-slate-900">{node.name}</h3>
                                <span className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${details.color}`}>{details.label}</span>
                            </div>
                            <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-500">
                                <span className="font-mono font-semibold text-slate-700">{node.code}</span>
                            </div>
                        </div>
                    </div>
                    <span
                        className={`w-fit rounded-full px-2.5 py-1 text-xs font-bold ${node.active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}
                    >
                        {node.active ? 'Active' : 'Inactive'}
                    </span>
                </div>
                {showMaterials && node.type === 1 && materialsByCompany[node.code] && (
                    <Collapsible className="group/materials mt-4 overflow-hidden rounded-xl border border-emerald-200 bg-gradient-to-r from-emerald-50 to-teal-50">
                        <CollapsibleTrigger className="flex w-full items-center gap-3 p-3 text-left transition hover:bg-emerald-100/70">
                            <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-emerald-600 text-white">
                                <Package className="size-4" />
                            </span>
                            <span className="flex-1 font-bold text-emerald-900">Materials</span>
                            <span className="rounded-full bg-emerald-600 px-2.5 py-1 text-xs font-bold whitespace-nowrap text-white shadow-sm">
                                Total:{' '}
                                {materialsByCompany[node.code].stock.toLocaleString('en-IN', {
                                    maximumFractionDigits: 3,
                                })}{' '}
                                MTN
                            </span>
                            <span className="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-emerald-700 shadow-sm">
                                {materialsByCompany[node.code].total.toLocaleString('en-IN')}
                            </span>
                            <ChevronDown className="size-4 text-emerald-700 transition-transform group-data-[state=open]/materials:rotate-180" />
                        </CollapsibleTrigger>
                        <CollapsibleContent className="border-t border-emerald-200 p-3">
                            <Collapsible className="group/material-type overflow-hidden rounded-lg border border-teal-200 bg-white shadow-sm">
                                <CollapsibleTrigger className="flex w-full items-center gap-3 bg-teal-50 p-3 text-left transition hover:bg-teal-100">
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-mono text-lg font-extrabold text-teal-950">
                                            {materialsByCompany[node.code].type}
                                        </span>
                                    </span>
                                    <span className="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-teal-700 shadow-sm">
                                        {materialsByCompany[node.code].total.toLocaleString('en-IN')}
                                    </span>
                                    <ChevronDown className="size-4 text-teal-700 transition-transform group-data-[state=open]/material-type:rotate-180" />
                                </CollapsibleTrigger>
                                <CollapsibleContent className="border-t border-teal-200 p-2">
                                    <div className="space-y-2">
                                        {materialsByCompany[node.code].groups.map((group) => (
                                            <Collapsible
                                                key={group.code}
                                                className="group/material-group overflow-hidden rounded-lg border border-emerald-100 bg-white shadow-sm"
                                            >
                                                <CollapsibleTrigger className="flex w-full items-center gap-3 p-3 text-left transition hover:bg-emerald-50">
                                                    <span className="min-w-0 flex-1">
                                                        <span className="block text-xs font-semibold tracking-wide text-emerald-700 uppercase">
                                                            Material Group
                                                        </span>
                                                        <span className="mt-0.5 block font-mono text-base font-extrabold text-slate-900">
                                                            {group.code}
                                                        </span>
                                                    </span>
                                                    <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                        {group.total.toLocaleString('en-IN')}
                                                    </span>
                                                    <ChevronDown className="size-4 text-emerald-700 transition-transform group-data-[state=open]/material-group:rotate-180" />
                                                </CollapsibleTrigger>
                                                <CollapsibleContent className="border-t border-emerald-100 p-2">
                                                    <MaterialSlider items={group.items} />
                                                </CollapsibleContent>
                                            </Collapsible>
                                        ))}
                                        {materialsByCompany[node.code].groups.length === 0 && (
                                            <p className="py-3 text-center text-sm text-emerald-700">
                                                No {materialsByCompany[node.code].type} materials found.
                                            </p>
                                        )}
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>
                        </CollapsibleContent>
                    </Collapsible>
                )}
            </div>
            {[...node.children]
                .filter((child) => showChildren || child.type === 3)
                .sort((left, right) => {
                    if (left.type === 3 && right.type !== 3) return -1;
                    if (left.type !== 3 && right.type === 3) return 1;

                    const comparison = left.code.localeCompare(right.code, undefined, { numeric: true, sensitivity: 'base' });
                    return comparison;
                })
                .map((child) => (
                    <HierarchyNode
                        key={child.id}
                        node={child}
                        materialsByCompany={materialsByCompany}
                        depth={depth + 1}
                        showChildren
                        showMaterials={showMaterials}
                    />
                ))}
        </div>
    );
}

export default function CompanyHierarchy({
    view,
    materialsByCompany,
    hierarchy,
    orphans,
}: {
    view: 'companies' | 'plants' | 'materials';
    materialsByCompany: Record<string, CompanyMaterials>;
    hierarchy: CompanyNode[];
    orphans: CompanyNode[];
}) {
    const [selectedCompany, setSelectedCompany] = useState('all');
    const filtered = selectedCompany === 'all' ? hierarchy : hierarchy.filter((company) => company.code === selectedCompany);
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Company hierarchy" />
            <div className="min-h-full bg-gradient-to-br from-slate-50 via-cyan-50/30 to-violet-50/40">
                <div className="mx-auto w-full max-w-[1500px] space-y-5 p-4 md:p-6">
                    <nav className="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Hierarchy views">
                        {[
                            { key: 'companies', label: 'Companies', icon: Building2 },
                            { key: 'plants', label: 'Plants', icon: Factory },
                            { key: 'materials', label: 'Materials', icon: Package },
                        ].map((item) => (
                            <button
                                key={item.key}
                                type="button"
                                onClick={() =>
                                    router.get(
                                        '/admin/companies/hierarchy',
                                        { view: item.key },
                                        {
                                            preserveScroll: false,
                                            preserveState: false,
                                            replace: true,
                                        },
                                    )
                                }
                                aria-pressed={view === item.key}
                                className={`inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition ${
                                    view === item.key
                                        ? 'bg-gradient-to-r from-cyan-500 to-sky-600 text-white shadow-md shadow-cyan-500/20'
                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                                }`}
                            >
                                <item.icon className="size-4" />
                                {item.label}
                            </button>
                        ))}
                    </nav>

                    <section className="rounded-3xl border border-slate-200/80 bg-white/80 p-4 shadow-lg shadow-slate-900/5 backdrop-blur md:p-6">
                        <div className="mb-6 flex flex-col gap-4 border-b border-slate-100 pb-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 className="text-lg font-bold text-slate-900">Organization tree</h2>
                                <p className="text-sm text-slate-500">Companies and their linked operational units</p>
                            </div>
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <label htmlFor="company-filter" className="text-xs font-bold tracking-wide text-slate-500 uppercase">
                                    Company
                                </label>
                                <select
                                    id="company-filter"
                                    value={selectedCompany}
                                    onChange={(event) => setSelectedCompany(event.target.value)}
                                    className="min-w-56 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20"
                                >
                                    <option value="all">All companies</option>
                                    {hierarchy.map((company) => (
                                        <option key={company.id} value={company.code}>
                                            {company.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div className={`grid items-start gap-4 ${filtered.length > 1 ? 'lg:grid-cols-2' : 'mx-auto max-w-3xl'}`}>
                            {filtered.map((node) => (
                                <HierarchyNode
                                    key={node.id}
                                    node={node}
                                    materialsByCompany={materialsByCompany}
                                    showChildren={view !== 'companies'}
                                    showMaterials={view === 'materials'}
                                />
                            ))}
                            {!filtered.length && (
                                <div className="py-16 text-center text-slate-500 lg:col-span-2">
                                    <Search className="mx-auto mb-3 size-8 text-slate-300" />
                                    No matching company found.
                                </div>
                            )}
                        </div>
                    </section>
                    {orphans.length > 0 && (
                        <section className="rounded-2xl border border-orange-200 bg-orange-50/60 p-5">
                            <div className="mb-4 flex items-center gap-3">
                                <Unlink className="size-5 text-orange-600" />
                                <div>
                                    <h2 className="font-bold text-orange-900">Unlinked records</h2>
                                    <p className="text-sm text-orange-700">
                                        These records reference a parent code that is not present in the company master.
                                    </p>
                                </div>
                            </div>
                            {orphans.map((node) => (
                                <HierarchyNode
                                    key={node.id}
                                    node={node}
                                    materialsByCompany={materialsByCompany}
                                    showMaterials={view === 'materials'}
                                />
                            ))}
                        </section>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
