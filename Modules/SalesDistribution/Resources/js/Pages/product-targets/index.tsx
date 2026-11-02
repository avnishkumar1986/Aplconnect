import InputError from '@/components/input-error';
import {
    MasterDialogHeader,
    masterDialogBodyClass,
    masterDialogContentClass,
    masterDialogFooterClass,
    masterDialogFormClass,
} from '@/components/master/master-dialog-header';
import { MasterPageHeader } from '@/components/master/master-page-header';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, ChevronsUpDown, Edit3, FileUp, Plus, Search, Target, Trash2 } from 'lucide-react';
import { FormEvent, useRef, useState } from 'react';

type Row = Record<string, string | number | boolean | null> & {
    id: number;
    source_id: number | null;
    customer_name: string;
    customer_code: string;
    state: string | null;
    city: string | null;
    month: string | null;
    fiscal_year: string;
    l3_code: string | null;
    l4_code: string | null;
    l5_code: string | null;
    l6_code: string | null;
    l3_name: string | null;
    l4_name: string | null;
    l5_name: string | null;
    l6_name: string | null;
    total_sale: string;
    status: boolean;
};
type Page = { data: Row[]; current_page: number; last_page: number; total: number; from: number | null; to: number | null };

const productLabels: Record<string, string> = {
    product1_pvc_pipes: 'PVC pipes',
    product2_colum_pipes: 'Column pipes',
    product3_casing_pipe: 'Casing pipe',
    product4_hdpe_pipe: 'HDPE pipe',
    product5_astm_pipe: 'ASTM pipe',
    product6_cpvc_pipe: 'CPVC pipe',
    product7_ppr_pipe: 'PPR pipe',
    product8_sprk_pipe: 'Sprinkler pipe',
    product9_swr_pipe: 'SWR pipe',
    product10_agri_swr_fit: 'Agri/SWR fittings',
    product11_hdpe_fit: 'HDPE fittings',
    product12_astm_fit: 'ASTM fittings',
    product13_sprk_fit: 'Sprinkler fittings',
    product14_cpvc_fit: 'CPVC fittings',
    product15_ppr_fit: 'PPR fittings',
    product16_mdpe_fit: 'MDPE fittings',
    product17_solvent: 'Solvent',
    product18_bathfit: 'Bath fittings',
    product19_watertank: 'Water tank',
    product20_mdpe_pipe: 'MDPE pipe',
    product21_plumbing_pipe: 'Plumbing pipe',
    product22_opvc: 'O-PVC',
    product23_dwc_pipe: 'DWC pipe',
    product24_other_products: 'Other products',
};

const baseForm = (columns: string[]) => ({
    source_id: '',
    customer_name: '',
    customer_code: '',
    state: '',
    city: '',
    month: '',
    fiscal_year: '',
    l3_code: '',
    l4_code: '',
    l5_code: '',
    l6_code: '',
    ...Object.fromEntries(columns.map((column) => [column, '0'])),
    total_sale: '0',
    status: true,
});
const number = (value: unknown) => Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function EmployeeSearchSelect({
    value,
    employees,
    onChange,
}: {
    value: string;
    employees: Array<{ code: string; name: string }>;
    onChange: (value: string) => void;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const selected = employees.find((employee) => employee.code === value);
    const normalized = query.trim().toLowerCase();
    const matches = employees.filter((employee) => normalized === '' || employee.name.toLowerCase().includes(normalized)).slice(0, 100);

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => {
                    setOpen((current) => !current);
                    setQuery('');
                }}
                className="border-input flex h-9 w-full items-center justify-between rounded-md border bg-white px-3 text-left text-sm shadow-xs outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20"
            >
                <span className={selected ? 'truncate text-slate-900' : 'text-slate-400'}>
                    {selected?.name ?? (value ? 'Employee not found' : 'Select employee')}
                </span>
                <ChevronsUpDown className="ml-2 size-4 shrink-0 text-slate-400" />
            </button>
            {open && (
                <div className="absolute z-[120] mt-1 w-full min-w-64 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl">
                    <div className="relative border-b p-2">
                        <Search className="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-slate-400" />
                        <Input
                            autoFocus
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Search employee name…"
                            className="h-9 pl-9"
                        />
                    </div>
                    <div className="max-h-56 overflow-y-auto p-1">
                        <button
                            type="button"
                            onClick={() => {
                                onChange('');
                                setOpen(false);
                            }}
                            className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-slate-500 hover:bg-slate-100"
                        >
                            <span className="size-4" /> Clear selection
                        </button>
                        {matches.map((employee) => (
                            <button
                                key={employee.code}
                                type="button"
                                onClick={() => {
                                    onChange(employee.code);
                                    setOpen(false);
                                }}
                                className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm hover:bg-cyan-50 hover:text-cyan-800"
                            >
                                <Check className={`size-4 shrink-0 ${employee.code === value ? 'opacity-100' : 'opacity-0'}`} />
                                <span className="truncate">{employee.name}</span>
                            </button>
                        ))}
                        {matches.length === 0 && <p className="px-3 py-6 text-center text-sm text-slate-500">No employee found</p>}
                    </div>
                </div>
            )}
        </div>
    );
}

export default function ProductTargets({
    targets,
    filters,
    fiscalYears,
    stats,
    productColumns,
    hierarchyEmployees,
    canManage,
}: {
    targets: Page;
    filters: { search: string; fiscal_year: string; per_page: number };
    fiscalYears: string[];
    stats: { records: number; customers: number; total: number };
    productColumns: string[];
    hierarchyEmployees: Array<{ code: string; name: string }>;
    canManage: boolean;
}) {
    const empty = baseForm(productColumns);
    const [search, setSearch] = useState(filters.search);
    const [year, setYear] = useState(filters.fiscal_year);
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Row | null>(null);
    const fileRef = useRef<HTMLInputElement>(null);
    const form = useForm<Record<string, string | boolean>>(empty);
    const importForm = useForm<{ file: File | null }>({ file: null });
    const load = (page = 1) =>
        router.get('/admin/sales-distribution/product-targets', { search, fiscal_year: year, page }, { preserveState: true, replace: true });
    const create = () => {
        setEditing(null);
        form.setData(empty);
        form.clearErrors();
        setOpen(true);
    };
    const edit = (row: Row) => {
        setEditing(row);
        const data = baseForm(productColumns);
        for (const key of Object.keys(data)) data[key] = key === 'status' ? Boolean(row[key]) : String(row[key] ?? '');
        form.setData(data);
        form.clearErrors();
        setOpen(true);
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        editing
            ? form.put(`/admin/sales-distribution/product-targets/${editing.id}`, options)
            : form.post('/admin/sales-distribution/product-targets', options);
    };
    const upload = (file: File) => {
        importForm.setData('file', file);
        importForm.post('/admin/sales-distribution/product-targets/import', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                importForm.reset();
                if (fileRef.current) fileRef.current.value = '';
            },
        });
    };
    const calculatedTotal = productColumns.reduce((sum, key) => sum + (Number(form.data[key]) || 0), 0);
    const setProductTarget = (key: string, value: string) => {
        const next = { ...form.data, [key]: value };
        next.total_sale = productColumns.reduce((sum, column) => sum + (Number(next[column]) || 0), 0).toFixed(2);
        form.setData(next);
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Sales & Distribution', href: '/admin/sales-distribution' },
                { title: 'Product Targets', href: '/admin/sales-distribution/product-targets' },
            ]}
        >
            <Head title="Product Targets" />
            <div className="mx-auto w-full max-w-[1680px] space-y-4 p-3 md:p-6">
                <MasterPageHeader
                    title="Product Target Master"
                    description={`${stats.records.toLocaleString('en-IN')} records · ${stats.customers.toLocaleString('en-IN')} customers · ${number(stats.total)} total target`}
                    icon={<Target className="size-5" />}
                    action={
                        <div className="flex flex-wrap gap-2">
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    load();
                                }}
                                className="flex gap-2"
                            >
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-cyan-100" />
                                    <Input
                                        value={search}
                                        onChange={(event) => setSearch(event.target.value)}
                                        placeholder="Search customer, state, city…"
                                        className="h-9 w-72 border-cyan-300/40 bg-cyan-500/20 pl-9 text-white"
                                    />
                                </div>
                                <select
                                    value={year}
                                    onChange={(event) => {
                                        setYear(event.target.value);
                                        router.get(
                                            '/admin/sales-distribution/product-targets',
                                            { search, fiscal_year: event.target.value },
                                            { preserveState: true, replace: true },
                                        );
                                    }}
                                    className="h-9 rounded-md border border-cyan-300/40 bg-cyan-700/50 px-3 text-sm text-white"
                                >
                                    <option value="">All years</option>
                                    {fiscalYears.map((item) => (
                                        <option key={item} value={item}>
                                            {item}
                                        </option>
                                    ))}
                                </select>
                            </form>
                            {canManage && (
                                <>
                                    <input
                                        ref={fileRef}
                                        type="file"
                                        accept=".csv,text/csv"
                                        className="hidden"
                                        onChange={(event) => event.target.files?.[0] && upload(event.target.files[0])}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={importForm.processing}
                                        onClick={() => fileRef.current?.click()}
                                        className="h-9"
                                    >
                                        <FileUp className="size-4" /> {importForm.processing ? 'Importing…' : 'Import CSV'}
                                    </Button>
                                    <Button type="button" onClick={create} className="h-9 bg-orange-500 text-white">
                                        <Plus className="size-4" /> Add target
                                    </Button>
                                </>
                            )}
                        </div>
                    }
                />
                {importForm.errors.file && <p className="text-sm text-red-600">{importForm.errors.file}</p>}
                <section className="overflow-hidden rounded-2xl border bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1150px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500 uppercase">
                                <tr>
                                    <th className="p-4">S.No.</th>
                                    <th>Customer</th>
                                    <th>Location</th>
                                    <th>Fiscal year</th>
                                    <th>Hierarchy</th>
                                    <th className="text-right">Total target</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {targets.data.map((row, index) => (
                                    <tr key={row.id} className="hover:bg-slate-50">
                                        <td className="p-4">{(targets.current_page - 1) * filters.per_page + index + 1}</td>
                                        <td>
                                            <b>{row.customer_name}</b>
                                            <div className="font-mono text-xs text-slate-500">{row.customer_code}</div>
                                        </td>
                                        <td>
                                            {row.city || '—'}
                                            <div className="text-xs text-slate-500">{row.state || '—'}</div>
                                        </td>
                                        <td>{row.fiscal_year}</td>
                                        <td className="min-w-72 py-2 text-xs">
                                            {(['l3', 'l4', 'l5', 'l6'] as const).map((level) => {
                                                const code = row[`${level}_code`];
                                                const name = row[`${level}_name`];
                                                return code ? (
                                                    <div key={level} className="grid grid-cols-[2rem_1fr] gap-2 py-0.5">
                                                        <span className="font-bold text-slate-400 uppercase">{level}</span>
                                                        <span>
                                                            <span className="font-semibold text-slate-800">{name || 'Employee not found'}</span>
                                                        </span>
                                                    </div>
                                                ) : null;
                                            })}
                                        </td>
                                        <td className="text-right font-bold">{number(row.total_sale)}</td>
                                        <td>
                                            <span
                                                className={`rounded-full px-2 py-1 text-xs font-semibold ${row.status ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}
                                            >
                                                {row.status ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td>
                                            <div className="flex gap-1">
                                                {canManage && (
                                                    <>
                                                        <Button size="icon" variant="ghost" onClick={() => edit(row)}>
                                                            <Edit3 className="size-4" />
                                                        </Button>
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            className="text-red-600"
                                                            onClick={() =>
                                                                confirm('Delete this product target?') &&
                                                                router.delete(`/admin/sales-distribution/product-targets/${row.id}`)
                                                            }
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex items-center justify-between border-t p-4 text-sm">
                        <span>
                            {targets.from ?? 0}–{targets.to ?? 0} of {targets.total}
                        </span>
                        <div className="flex gap-2">
                            <Button variant="outline" disabled={targets.current_page <= 1} onClick={() => load(targets.current_page - 1)}>
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                disabled={targets.current_page >= targets.last_page}
                                onClick={() => load(targets.current_page + 1)}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                </section>
            </div>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className={`${masterDialogContentClass} !p-0`}>
                    <form onSubmit={submit} className={masterDialogFormClass}>
                        <MasterDialogHeader
                            title={editing ? 'Edit product target' : 'Add product target'}
                            description="Maintain customer ownership, reporting hierarchy, and annual product allocation."
                        />
                        <div className={`${masterDialogBodyClass} space-y-4 bg-slate-100/70 !p-0`}>
                            <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                                <div className="border-b border-slate-100 bg-slate-50/80 px-4 py-3">
                                    <h3 className="text-sm font-bold text-slate-900">Customer and planning period</h3>
                                    <p className="mt-0.5 text-xs text-slate-500">Identify the customer and target period for this allocation.</p>
                                </div>
                                <div className="grid gap-x-4 gap-y-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
                                    {(
                                        [
                                            ['source_id', 'Source ID', 'number'],
                                            ['customer_name', 'Customer name', 'text'],
                                            ['customer_code', 'Customer code', 'text'],
                                            ['fiscal_year', 'Fiscal year', 'text'],
                                            ['state', 'State', 'text'],
                                            ['city', 'City', 'text'],
                                            ['month', 'Month', 'text'],
                                        ] as string[][]
                                    ).map(([key, label, type]) => (
                                        <div key={key}>
                                            <Label className="mb-1.5 block text-xs font-semibold text-slate-600">{label}</Label>
                                            <Input
                                                type={type}
                                                value={String(form.data[key] ?? '')}
                                                onChange={(event) => form.setData(key, event.target.value)}
                                                className="h-9 bg-white"
                                            />
                                            <InputError message={form.errors[key]} />
                                        </div>
                                    ))}
                                </div>
                            </section>

                            <section className="relative z-20 overflow-visible rounded-xl border border-slate-200 bg-white shadow-sm">
                                <div className="border-b border-slate-100 bg-slate-50/80 px-4 py-3">
                                    <h3 className="text-sm font-bold text-slate-900">Employee hierarchy</h3>
                                    <p className="mt-0.5 text-xs text-slate-500">Assign the reporting owners from L3 through L6.</p>
                                </div>
                                <div className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
                                    {(['l3_code', 'l4_code', 'l5_code', 'l6_code'] as const).map((key) => (
                                        <div key={key}>
                                            <Label className="mb-1.5 block text-xs font-semibold text-slate-600">
                                                {key.slice(0, 2).toUpperCase()} employee
                                            </Label>
                                            <EmployeeSearchSelect
                                                value={String(form.data[key] ?? '')}
                                                employees={hierarchyEmployees}
                                                onChange={(value) => form.setData(key, value)}
                                            />
                                            <InputError message={form.errors[key]} />
                                        </div>
                                    ))}
                                </div>
                            </section>

                            <section className="relative z-10 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                                <div className="flex items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/80 px-4 py-3">
                                    <div>
                                        <h3 className="text-sm font-bold text-slate-900">Product allocation</h3>
                                        <p className="mt-0.5 text-xs text-slate-500">Enter the target value for each product category.</p>
                                    </div>
                                    <div className="rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-2 text-right">
                                        <p className="text-[10px] font-bold tracking-wide text-cyan-700 uppercase">Total target</p>
                                        <p className="text-lg font-black text-cyan-900">{number(calculatedTotal)}</p>
                                    </div>
                                </div>
                                <div className="grid gap-x-4 gap-y-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
                                    {productColumns.map((key) => (
                                        <div key={key}>
                                            <Label className="mb-1.5 block text-xs font-semibold text-slate-600">{productLabels[key] ?? key}</Label>
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={String(form.data[key] ?? '0')}
                                                onChange={(event) => setProductTarget(key, event.target.value)}
                                                className="h-9 bg-white text-right font-medium tabular-nums"
                                            />
                                            <InputError message={form.errors[key]} />
                                        </div>
                                    ))}
                                </div>
                            </section>
                        </div>
                        <DialogFooter className={`${masterDialogFooterClass} flex items-center sm:justify-between`}>
                            <div className="mr-auto hidden sm:block">
                                <p className="text-xs text-slate-500">Calculated total</p>
                                <p className="font-bold text-slate-900">{number(calculatedTotal)}</p>
                            </div>
                            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                                Cancel
                            </Button>
                            <Button disabled={form.processing} className="min-w-32 bg-cyan-600 text-white hover:bg-cyan-700">
                                {form.processing ? 'Saving…' : 'Save target'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
