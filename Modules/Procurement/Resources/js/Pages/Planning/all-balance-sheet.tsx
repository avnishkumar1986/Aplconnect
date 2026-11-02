import { DataTableSortHeader } from '@/components/master/data-table-sort-header';
import ProcurementPageHeader from '@/components/procurement-page-header';
import { RowExpandButton } from '@/components/row-expand-button';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Boxes,
    Calculator,
    CalendarDays,
    Check,
    ChevronDown,
    Factory,
    FileSpreadsheet,
    Filter,
    Layers3,
    Maximize2,
    Minimize2,
    Printer,
    Search,
    TrendingDown,
} from 'lucide-react';
import { FormEvent, Fragment, useEffect, useRef, useState } from 'react';

type Row = {
    material: string;
    material_name: string;
    unit: string | null;
    plant_code: string;
    plant_name: string;
    opening_stock: number;
    opening_stock_value: number;
    auto_monthly_consumption: number;
    auto_perday_consumption: number;
    daily_consumption: number;
    days: number;
    consumption: number;
    planned_po: number;
    planned_buying: number;
    imports: number;
    supplier_receipts: number;
    total_buying: number;
    total_availability: number;
    closing_stock: number;
    shortage: number;
};
type Page<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
type Filters = {
    month: string;
    company_code: string;
    plant_code: string;
    material: string;
    search: string;
    per_page: number;
    sort: string;
    direction: 'asc' | 'desc';
};
const number = (value: number) =>
    Number(value).toLocaleString('en-IN', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    });
const money = (value: number) =>
    Number(value).toLocaleString('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

function SearchableFilterSelect({
    value,
    options,
    placeholder,
    onValueChange,
}: {
    value: string;
    options: { value: string; label: string }[];
    placeholder: string;
    onValueChange: (value: string) => void;
}) {
    const selectedLabel = options.find((option) => option.value === value)?.label ?? '';
    const [query, setQuery] = useState(selectedLabel);
    const [open, setOpen] = useState(false);

    useEffect(() => setQuery(selectedLabel), [selectedLabel]);

    const normalizedQuery = query.trim().toLowerCase();
    const filteredOptions = options.filter(
        (option) => !normalizedQuery || option.label.toLowerCase().includes(normalizedQuery) || option.value.toLowerCase().includes(normalizedQuery),
    );

    return (
        <div className="relative font-normal text-slate-900">
            <Search className="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-slate-400" />
            <input
                role="combobox"
                aria-expanded={open}
                aria-autocomplete="list"
                autoComplete="off"
                value={query}
                placeholder={placeholder}
                className="border-input h-10 w-full rounded-lg border bg-white pr-9 pl-9 text-sm font-medium shadow-sm transition outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/20"
                onFocus={() => {
                    setOpen(true);
                    setQuery('');
                }}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setOpen(true);
                }}
                onBlur={() =>
                    window.setTimeout(() => {
                        setOpen(false);
                        setQuery(options.find((option) => option.value === value)?.label ?? '');
                    }, 150)
                }
                onKeyDown={(event) => {
                    if (event.key === 'Enter' && open && filteredOptions.length > 0) {
                        event.preventDefault();
                        onValueChange(filteredOptions[0].value);
                        setQuery(filteredOptions[0].label);
                        setOpen(false);
                    }
                    if (event.key === 'Escape') setOpen(false);
                }}
            />
            <ChevronDown className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-slate-500" />
            {open && (
                <div className="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-slate-200 bg-white p-1 shadow-xl">
                    {filteredOptions.length ? (
                        filteredOptions.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                className="flex min-h-9 w-full items-center justify-between gap-2 rounded px-3 py-2 text-left text-sm hover:bg-cyan-50"
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => {
                                    onValueChange(option.value);
                                    setQuery(option.label);
                                    setOpen(false);
                                }}
                            >
                                <span className="truncate">{option.label}</span>
                                {option.value === value && <Check className="size-4 shrink-0 text-cyan-600" />}
                            </button>
                        ))
                    ) : (
                        <p className="px-3 py-2 text-sm text-slate-500">No matching options</p>
                    )}
                </div>
            )}
        </div>
    );
}

export default function AllBalanceSheet({
    rows,
    companies,
    plants,
    materials,
    filters,
    summary,
    planningMode,
}: {
    rows: Page<Row>;
    companies: { id: number; company_code: string; company_name: string }[];
    plants: { id: number; company_code: string; company_name: string; parent_company_id: string }[];
    materials: { matnr: string; maktx: string; meins: string | null; mtart: string }[];
    filters: Filters;
    summary: {
        material_type: 'ZRAW' | 'YRAW';
        plants: number;
        materials: number;
        opening_stock: number;
        opening_stock_value: number;
        consumption: number;
        closing_stock: number;
        shortage: number;
    };
    planningMode: 'auto' | 'manual' | 'mixed';
}) {
    const { datatable } = usePage<SharedData>().props;
    const [month, setMonth] = useState(filters.month);
    const [companyCode, setCompanyCode] = useState(filters.company_code);
    const [plantCode, setPlantCode] = useState(filters.plant_code || 'all');
    const [material, setMaterial] = useState(filters.material || 'all');
    const [expanded, setExpanded] = useState(false);
    const [showFormulas, setShowFormulas] = useState(false);
    const [expandedRow, setExpandedRow] = useState<string | null>(null);
    const consumptionSource = planningMode === 'auto' ? 'SAP' : planningMode === 'manual' ? 'Manual' : 'Mixed';
    const tableRef = useRef<HTMLElement>(null);
    useEffect(() => setMonth(filters.month), [filters.month]);
    const availablePlants = plants.filter((plant) => String(plant.parent_company_id) === companyCode);
    const apply = (event?: FormEvent) => {
        event?.preventDefault();
        router.get(
            '/admin/procurement/all-balance-sheet',
            {
                month,
                company_code: companyCode,
                plant_code: plantCode === 'all' ? undefined : plantCode,
                material: material === 'all' ? undefined : material,
                per_page: filters.per_page,
                sort: filters.sort,
                direction: filters.direction,
            },
            { preserveState: true, replace: true },
        );
    };
    const loadTable = (sort = filters.sort, direction = filters.direction, perPage = filters.per_page) =>
        router.get(
            '/admin/procurement/all-balance-sheet',
            {
                month: filters.month,
                company_code: filters.company_code,
                plant_code: filters.plant_code || undefined,
                material: filters.material || undefined,
                per_page: perPage,
                sort,
                direction,
            },
            { preserveState: true, replace: true },
        );
    const sortTable = (column: string) => loadTable(column, filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc');
    const toggleExpanded = async () => (document.fullscreenElement ? document.exitFullscreen() : tableRef.current?.requestFullscreen());
    useEffect(() => {
        const sync = () => setExpanded(document.fullscreenElement === tableRef.current);
        document.addEventListener('fullscreenchange', sync);
        return () => document.removeEventListener('fullscreenchange', sync);
    }, []);
    const exportCurrent = () => {
        const columns: (keyof Row)[] = [
            'material',
            'material_name',
            'plant_code',
            'plant_name',
            'opening_stock',
            'auto_monthly_consumption',
            'auto_perday_consumption',
            'daily_consumption',
            'consumption',
            'planned_buying',
            'imports',
            'supplier_receipts',
            'total_buying',
            'total_availability',
            'closing_stock',
            'shortage',
        ];
        const csv = [
            columns.join(','),
            ...rows.data.map((row) => columns.map((key) => `"${String(row[key] ?? '').replaceAll('"', '""')}"`).join(',')),
        ].join('\n');
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        link.download = `all-material-all-plant-balance-${filters.month}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
    };
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Procurement', href: '/admin/procurement' },
                {
                    title: 'Balance sheet',
                    href: '/admin/procurement/all-balance-sheet',
                },
            ]}
        >
            <Head title="Procurement Balance Sheet" />
            <main className="procurement-compact w-full min-w-0 space-y-3 p-3 sm:p-4">
                <div className="relative z-30 grid items-center overflow-visible rounded-2xl border border-t-[3px] border-white/10 border-t-orange-500 bg-gradient-to-br from-[#062f42] via-[#075b70] to-[#0b91a7] p-3 shadow-lg shadow-slate-900/15 xl:grid-cols-[minmax(420px,0.75fr)_minmax(0,2fr)] xl:gap-4 print:hidden">
                    <ProcurementPageHeader
                        title="Procurement Balance Sheet"
                        icon={FileSpreadsheet}
                        embedded
                        meta={
                            <button
                                type="button"
                                className="inline-flex items-center gap-1.5 font-semibold text-white transition hover:text-cyan-200 focus-visible:outline-none"
                                onClick={() => setShowFormulas(true)}
                            >
                                <Calculator className="size-3" />
                                View formulas
                            </button>
                        }
                        actions={
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    size="icon"
                                    variant="outline"
                                    className="size-9 border-white/30 bg-white/10 text-white hover:bg-white hover:text-slate-900"
                                    onClick={exportCurrent}
                                    aria-label="Export balance sheet"
                                    title="Export balance sheet"
                                >
                                    <FileSpreadsheet className="size-4" />
                                </Button>
                                <Button
                                    size="icon"
                                    variant="outline"
                                    className="size-9 border-white/30 bg-white/10 text-white hover:bg-white hover:text-slate-900"
                                    onClick={() => window.print()}
                                    aria-label="Print balance sheet"
                                    title="Print balance sheet"
                                >
                                    <Printer className="size-4" />
                                </Button>
                            </div>
                        }
                    />
                    <section className="rounded-xl border border-white/15 bg-white/10 p-3 shadow-inner shadow-black/5 backdrop-blur-md">
                        <form onSubmit={apply} className="grid gap-3 sm:grid-cols-2 xl:grid-cols-12 xl:items-end">
                            <label className="space-y-1 text-[10px] font-bold tracking-wide text-cyan-50 uppercase xl:col-span-3">
                                Company
                                <SearchableFilterSelect
                                    value={companyCode}
                                    options={companies.map((company) => ({
                                        value: company.company_code,
                                        label: `${company.company_code} · ${company.company_name}`,
                                    }))}
                                    placeholder="Search company..."
                                    onValueChange={(nextCompanyCode) => {
                                        setCompanyCode(nextCompanyCode);
                                        setPlantCode('all');
                                        setMaterial('all');
                                        router.get(
                                            '/admin/procurement/all-balance-sheet',
                                            {
                                                company_code: nextCompanyCode,
                                                month,
                                                per_page: filters.per_page,
                                                sort: filters.sort,
                                                direction: filters.direction,
                                            },
                                            { preserveState: false, replace: true },
                                        );
                                    }}
                                />
                            </label>
                            <label className="space-y-1 text-[10px] font-bold tracking-wide text-cyan-50 uppercase xl:col-span-3">
                                Plant
                                <SearchableFilterSelect
                                    value={plantCode}
                                    onValueChange={setPlantCode}
                                    options={[
                                        { value: 'all', label: 'All Plants' },
                                        ...availablePlants.map((plant) => ({
                                            value: plant.company_code,
                                            label: `${plant.company_code} · ${plant.company_name}`,
                                        })),
                                    ]}
                                    placeholder="Search plant..."
                                />
                            </label>
                            <label className="space-y-1 text-[10px] font-bold tracking-wide text-cyan-50 uppercase xl:col-span-4">
                                Material
                                <SearchableFilterSelect
                                    value={material}
                                    onValueChange={setMaterial}
                                    options={[
                                        { value: 'all', label: `All ${materials[0]?.mtart || ''} Materials` },
                                        ...materials.map((item) => ({
                                            value: item.matnr,
                                            label: `${item.matnr} · ${item.maktx} · ${item.mtart}`,
                                        })),
                                    ]}
                                    placeholder="Search material code or description..."
                                />
                            </label>
                            <Button className="h-10 rounded-lg bg-cyan-500 px-6 font-semibold text-white shadow-md shadow-cyan-950/15 hover:bg-cyan-400 xl:col-span-2">
                                <Filter className="size-4" />
                                Apply
                            </Button>
                        </form>
                    </section>
                </div>
                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                    {[
                        [Factory, 'Plants', summary.plants, 'In report scope', 'text-cyan-700 bg-cyan-50'],
                        [Layers3, 'Materials', summary.materials, 'Active materials', 'text-blue-700 bg-blue-50'],
                        [
                            Boxes,
                            `${summary.material_type} opening stock`,
                            money(summary.opening_stock_value),
                            'Month opening value',
                            'text-indigo-700 bg-indigo-50',
                        ],
                        [
                            CalendarDays,
                            `${consumptionSource} consumption`,
                            `${number(summary.consumption)} MT`,
                            'Expected this month',
                            'text-amber-700 bg-amber-50',
                        ],
                        [TrendingDown, 'Closing stock', `${number(summary.closing_stock)} MT`, 'Projected closing', 'text-emerald-700 bg-emerald-50'],
                        [AlertTriangle, 'Shortage', `${number(summary.shortage)} MT`, 'Purchase attention', 'text-rose-700 bg-rose-50'],
                    ].map(([Icon, label, value, note, color]) => (
                        <article key={String(label)} className="rounded-xl border bg-white p-4 shadow-sm">
                            <div className="flex items-start gap-3">
                                <span className={`grid size-9 shrink-0 place-items-center rounded-lg ${color}`}>
                                    <Icon className="size-4" />
                                </span>
                                <div className="min-w-0">
                                    <p className="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">{label}</p>
                                    <p className="truncate text-lg font-bold text-slate-900">{value}</p>
                                    <p className="text-[10px] text-slate-400">{note}</p>
                                </div>
                            </div>
                        </article>
                    ))}
                </section>
                <Dialog open={showFormulas} onOpenChange={setShowFormulas}>
                    <DialogContent className="max-w-4xl p-0 sm:max-w-4xl">
                        <DialogHeader className="border-b bg-gradient-to-r from-[#07374b] to-[#0b8da3] px-6 py-5 text-left text-white">
                            <DialogTitle className="flex items-center gap-2 text-white">
                                <Calculator className="size-5 text-cyan-200" /> Calculation formulas
                            </DialogTitle>
                            <DialogDescription className="text-cyan-50/80">
                                Balance-sheet calculations for the selected {summary.material_type} scope.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-3 p-6 text-sm sm:grid-cols-2 lg:grid-cols-3">
                            {[
                                [`${summary.material_type} opening stock value`, `Σ ${summary.material_type} SAP stock value (₹)`],
                                [
                                    `${consumptionSource} monthly consumption`,
                                    planningMode === 'mixed'
                                        ? 'SAP or Manual average/day × Days (according to each row)'
                                        : `${consumptionSource} average/day × Days`,
                                ],
                                ['Purchase order (PO)', 'Planned buying + Imports'],
                                ['Availability', 'Opening stock (OS) + Purchase order (PO)'],
                                ['Closing stock', '(OS + PO) − Monthly consumption'],
                                ['Shortage', 'max(Monthly consumption − (OS + PO), 0)'],
                            ].map(([label, formula]) => (
                                <div key={label} className="rounded-lg border border-cyan-100 bg-cyan-50/50 px-4 py-3">
                                    <p className="font-semibold text-slate-700">{label}</p>
                                    <p className="mt-1.5 font-mono text-xs text-cyan-800">{formula}</p>
                                </div>
                            ))}
                        </div>
                    </DialogContent>
                </Dialog>
                <section
                    ref={tableRef}
                    className={`min-w-0 overflow-hidden border bg-white shadow-sm ${expanded ? 'flex h-screen flex-col rounded-none' : 'rounded-xl'}`}
                >
                    <header className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                        <div>
                            <h2 className="font-semibold text-slate-900">Balance details</h2>
                            <p className="text-xs text-slate-500">
                                {filters.company_code} · {filters.plant_code || 'All Plants'} ·{' '}
                                {planningMode === 'auto' ? 'SAP Auto' : planningMode === 'manual' ? 'Manual' : 'Mixed SAP/Manual'} mode
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                {rows.total.toLocaleString()} rows
                            </span>
                            <Button type="button" size="sm" variant="outline" className="gap-2 print:hidden" onClick={toggleExpanded}>
                                {expanded ? <Minimize2 className="size-4" /> : <Maximize2 className="size-4" />}
                                {expanded ? 'Collapse' : 'Expand'}
                            </Button>
                        </div>
                    </header>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1380px] border-separate border-spacing-0 text-xs">
                            <thead className="sticky top-0 z-20 bg-slate-100 text-[10px] font-bold tracking-wide text-slate-600 uppercase">
                                <tr>
                                    <th className="sticky left-0 z-30 w-14 min-w-14 border-b bg-slate-100 px-1 py-3 text-center">Action</th>
                                    <th className="sticky left-14 z-30 w-16 min-w-16 border-b bg-slate-100 px-3 py-3 text-center">S.No.</th>
                                    <DataTableSortHeader
                                        label="Material"
                                        column="material_name"
                                        activeColumn={filters.sort}
                                        direction={filters.direction}
                                        onSort={sortTable}
                                        className="sticky left-[7.5rem] z-30 min-w-72 border-b bg-slate-100 px-3 py-3 text-left"
                                    />
                                    <DataTableSortHeader
                                        label="Plant"
                                        column="plant_name"
                                        activeColumn={filters.sort}
                                        direction={filters.direction}
                                        onSort={sortTable}
                                        className="sticky left-[25.5rem] z-30 min-w-64 border-b bg-slate-100 px-3 py-3 text-left"
                                    />
                                    <th className="border-b px-3 py-3 text-center">Unit</th>
                                    <th className="border-b px-3 py-3 text-left">Source</th>
                                    {[
                                        ['Opening Stock', 'opening_stock'],
                                        ['Planned PO', 'planned_po'],
                                        ['Avg/Day', 'daily_consumption'],
                                    ].map(([label, column]) => (
                                        <DataTableSortHeader
                                            key={column}
                                            label={label}
                                            column={column}
                                            activeColumn={filters.sort}
                                            direction={filters.direction}
                                            onSort={sortTable}
                                            className="border-b px-3 py-3 text-right"
                                        />
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.data.map((row, index) => {
                                    const rowKey = `${row.material}-${row.plant_code}`;
                                    const rowOpen = expandedRow === rowKey;
                                    const rowSource = row.planning_mode === 'auto' ? 'SAP Auto' : 'Manual';
                                    return (
                                        <Fragment key={rowKey}>
                                            <tr
                                                tabIndex={0}
                                                aria-expanded={rowOpen}
                                                onClick={() => setExpandedRow(rowOpen ? null : rowKey)}
                                                onKeyDown={(event) => {
                                                    if (event.key === 'Enter' || event.key === ' ') {
                                                        event.preventDefault();
                                                        setExpandedRow(rowOpen ? null : rowKey);
                                                    }
                                                }}
                                                className={`cursor-pointer transition-colors hover:bg-cyan-50/40 focus-visible:bg-cyan-50/40 focus-visible:outline-none ${rowOpen ? 'bg-cyan-50/30' : ''}`}
                                            >
                                                <td className="sticky left-0 z-10 w-14 min-w-14 bg-white px-1 py-2 text-center">
                                                    <RowExpandButton
                                                        expanded={rowOpen}
                                                        onClick={() => setExpandedRow(rowOpen ? null : rowKey)}
                                                        label={`${rowOpen ? 'Collapse' : 'Expand'} ${row.material_name} at ${row.plant_name}`}
                                                        className="mx-auto"
                                                    />
                                                </td>
                                                <td className="sticky left-14 z-10 w-16 min-w-16 bg-white px-3 py-3 text-center text-slate-500 hover:bg-cyan-50">
                                                    {(rows.from || 1) + index}
                                                </td>
                                                <td className="sticky left-[7.5rem] z-10 bg-white px-3 py-3 hover:bg-cyan-50">
                                                    <b className="block text-slate-900">{row.material_name}</b>
                                                    <span className="font-mono text-[11px] text-slate-500">{row.material}</span>
                                                </td>
                                                <td className="sticky left-[25.5rem] z-10 bg-white px-3 py-3 hover:bg-cyan-50">
                                                    <b>{row.plant_name}</b>
                                                    <span className="block text-[11px] text-slate-500">{row.plant_code}</span>
                                                </td>
                                                <td className="px-3 py-3 text-center font-semibold text-slate-600">{row.unit || '—'}</td>
                                                <td className="px-3 py-3">
                                                    <span
                                                        className={`inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold ${row.planning_mode === 'auto' ? 'bg-cyan-50 text-cyan-700' : 'bg-amber-50 text-amber-700'}`}
                                                    >
                                                        {rowSource}
                                                    </span>
                                                </td>
                                                {[row.opening_stock, row.planned_po, row.daily_consumption].map((value, index) => (
                                                    <td key={index} className="px-3 py-3 text-right tabular-nums">
                                                        {number(value)}
                                                    </td>
                                                ))}
                                            </tr>
                                            {rowOpen && (
                                                <tr className="bg-orange-50/40">
                                                    <td colSpan={9} className="border-y border-orange-100 p-0">
                                                        <div
                                                            className={`sticky left-0 px-4 py-5 sm:px-6 ${expanded ? 'w-[calc(100vw-1rem)]' : 'w-[calc(100vw-1rem)] lg:w-[calc(100vw-17rem)]'}`}
                                                        >
                                                            <div className="mx-auto w-full max-w-7xl rounded-xl border border-orange-100 bg-white p-5 shadow-sm sm:p-6">
                                                                <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                                                                    <div>
                                                                        <p className="text-sm font-bold text-slate-900">{row.material_name}</p>
                                                                        <p className="mt-0.5 text-xs text-slate-500">
                                                                            {row.material} · {row.unit || '—'} · {row.plant_name} ({row.plant_code})
                                                                        </p>
                                                                    </div>
                                                                    <div className="flex flex-wrap items-center justify-end gap-2">
                                                                        <span
                                                                            className={`rounded-full px-3 py-1 text-[11px] font-bold ${row.opening_stock <= 0 || row.shortage > 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'}`}
                                                                        >
                                                                            {row.shortage > 0
                                                                                ? `Shortage ${number(row.shortage)} MT`
                                                                                : row.opening_stock <= 0
                                                                                  ? 'Stock insufficient'
                                                                                  : 'Stock sufficient'}
                                                                        </span>
                                                                        <Button
                                                                            size="sm"
                                                                            variant="outline"
                                                                            asChild
                                                                            className="h-8 gap-2 border-cyan-200 bg-cyan-50 text-cyan-800 hover:bg-cyan-100"
                                                                        >
                                                                            <Link
                                                                                href={`/admin/procurement/balance-sheet?material=${encodeURIComponent(row.material)}&month=${encodeURIComponent(filters.month)}&months=3`}
                                                                            >
                                                                                <FileSpreadsheet className="size-3.5" /> View RM Inventories
                                                                            </Link>
                                                                        </Button>
                                                                    </div>
                                                                </div>
                                                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                                                    {[
                                                                        ['Opening stock', row.opening_stock, null],
                                                                        [
                                                                            'Opening stock value',
                                                                            row.opening_stock_value,
                                                                            money(row.opening_stock_value),
                                                                        ],
                                                                        [`Average per day (${rowSource})`, row.daily_consumption, null],
                                                                        [
                                                                            `Monthly consumption (${rowSource})`,
                                                                            row.consumption,
                                                                            `${number(row.daily_consumption)} × ${row.days} = ${number(row.consumption)}`,
                                                                        ],
                                                                        ['Planned buying', row.planned_buying, null],
                                                                        ['Imports', row.imports, null],
                                                                        ['Supplier receipts', row.supplier_receipts, null],
                                                                        [
                                                                            'Total buying',
                                                                            row.total_buying,
                                                                            `${number(row.planned_buying)} + ${number(row.imports)} = ${number(row.total_buying)}`,
                                                                        ],
                                                                        [
                                                                            'Availability',
                                                                            row.total_availability,
                                                                            `${number(row.opening_stock)} + ${number(row.total_buying)} = ${number(row.total_availability)}`,
                                                                        ],
                                                                        [
                                                                            'Closing stock',
                                                                            row.closing_stock,
                                                                            `${number(row.total_availability)} − ${number(row.consumption)} = ${number(row.closing_stock)}`,
                                                                        ],
                                                                        [
                                                                            'Shortage',
                                                                            row.shortage,
                                                                            `max(${number(row.consumption)} − ${number(row.total_availability)}, 0) = ${number(row.shortage)}`,
                                                                        ],
                                                                    ].map(([label, value, formula]) => (
                                                                        <div
                                                                            key={String(label)}
                                                                            className="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2.5"
                                                                        >
                                                                            <p className="text-[10px] font-bold tracking-wide text-slate-400 uppercase">
                                                                                {label}
                                                                            </p>
                                                                            <p className="mt-1 text-sm font-bold text-slate-800 tabular-nums">
                                                                                {label === 'Opening stock value'
                                                                                    ? money(Number(value))
                                                                                    : `${number(Number(value))} MT`}
                                                                            </p>
                                                                            {formula && label !== 'Opening stock value' && (
                                                                                <p className="mt-1 font-mono text-[10px] leading-4 text-cyan-700">
                                                                                    {formula} MT
                                                                                </p>
                                                                            )}
                                                                        </div>
                                                                    ))}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            )}
                                        </Fragment>
                                    );
                                })}
                            </tbody>
                        </table>
                        {rows.data.length === 0 && (
                            <div className="grid min-h-72 place-items-center text-sm text-slate-500">
                                No balance data matches the selected filters.
                            </div>
                        )}
                    </div>
                    <footer className="flex flex-wrap items-center justify-between gap-3 border-t bg-slate-50 px-4 py-3 print:hidden">
                        <div className="flex items-center gap-2 text-xs text-slate-500">
                            <span>Show</span>
                            <select
                                className="h-8 rounded-md border bg-white px-2"
                                value={filters.per_page}
                                onChange={(event) => loadTable(filters.sort, filters.direction, Number(event.target.value))}
                            >
                                {datatable.pageSizeOptions.map((size) => (
                                    <option key={size} value={size}>
                                        {size}
                                    </option>
                                ))}
                            </select>
                            <span>
                                entries · Showing {rows.from || 0}–{rows.to || 0} of {rows.total}
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={!rows.prev_page_url}
                                onClick={() => rows.prev_page_url && router.get(rows.prev_page_url, {}, { preserveState: true })}
                            >
                                Previous
                            </Button>
                            <span className="text-xs font-semibold">
                                Page {rows.current_page} of {rows.last_page}
                            </span>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={!rows.next_page_url}
                                onClick={() => rows.next_page_url && router.get(rows.next_page_url, {}, { preserveState: true })}
                            >
                                Next
                            </Button>
                        </div>
                    </footer>
                </section>
            </main>
        </AppLayout>
    );
}
