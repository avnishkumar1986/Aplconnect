import InputError from '@/components/input-error';
import { MasterDialogHeader } from '@/components/master/master-dialog-header';
import { MasterPageHeader } from '@/components/master/master-page-header';
import { StatusToggle } from '@/components/master/status-toggle';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { BadgeIndianRupee, ChevronLeft, ChevronRight, Edit3, Plus, Search, Trash2, Upload } from 'lucide-react';
import { ChangeEvent, FormEvent, Fragment, useRef, useState } from 'react';

type FormData = {
    customer_number: string;
    year_to_date_sales: string;
    last_year_sales: string;
    pending_sales: string;
    last_month_sales: string;
    current_month_sales: string;
    two_months_ago_sales: string;
    last_month_outstanding: string;
    current_month_outstanding: string;
    two_months_ago_outstanding: string;
    last_month_overdue: string;
    current_month_overdue: string;
    two_months_ago_overdue: string;
    last_month_payment_received: string;
    current_month_payment_received: string;
    two_months_ago_payment_received: string;
    payment_terms_code: string;
    payment_terms_description: string;
    credit_limit: string;
    status: boolean;
};
type Summary = FormData & { id: number; customer_name?: string };
type Page = {
    data: Summary[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
};
type Field = [keyof FormData, string];
const empty: FormData = {
    customer_number: '',
    year_to_date_sales: '0',
    last_year_sales: '0',
    pending_sales: '0',
    last_month_sales: '0',
    current_month_sales: '0',
    two_months_ago_sales: '0',
    last_month_outstanding: '0',
    current_month_outstanding: '0',
    two_months_ago_outstanding: '0',
    last_month_overdue: '0',
    current_month_overdue: '0',
    two_months_ago_overdue: '0',
    last_month_payment_received: '0',
    current_month_payment_received: '0',
    two_months_ago_payment_received: '0',
    payment_terms_code: '',
    payment_terms_description: '',
    credit_limit: '0',
    status: true,
};
const sections: { title: string; fields: Field[] }[] = [
    {
        title: 'Customer and credit terms',
        fields: [
            ['customer_number', 'Customer number'],
            ['payment_terms_code', 'Payment terms code'],
            ['payment_terms_description', 'Payment terms description'],
            ['credit_limit', 'Credit limit'],
        ],
    },
    {
        title: 'Sales performance',
        fields: [
            ['year_to_date_sales', 'Year-to-date sales'],
            ['last_year_sales', 'Last year sales'],
            ['pending_sales', 'Pending orders'],
            ['last_month_sales', 'Last month sales'],
            ['current_month_sales', 'Current month sales'],
            ['two_months_ago_sales', 'Two months ago sales'],
        ],
    },
    {
        title: 'Outstanding balances',
        fields: [
            ['last_month_outstanding', 'Last month outstanding'],
            ['current_month_outstanding', 'Current month outstanding'],
            ['two_months_ago_outstanding', 'Two months ago outstanding'],
        ],
    },
    {
        title: 'Overdue balances',
        fields: [
            ['last_month_overdue', 'Last month overdue'],
            ['current_month_overdue', 'Current month overdue'],
            ['two_months_ago_overdue', 'Two months ago overdue'],
        ],
    },
    {
        title: 'Payments received',
        fields: [
            ['last_month_payment_received', 'Last month received'],
            ['current_month_payment_received', 'Current month received'],
            ['two_months_ago_payment_received', 'Two months ago received'],
        ],
    },
];
const money = (v: unknown) =>
    Number(v ?? 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
const crores = (v: unknown) =>
    (Number(v ?? 0) / 10000000).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

export default function CustomerSalesSummary({
    summaries,
    filters,
    stats,
    canManage,
}: {
    summaries: Page;
    filters: { search: string; per_page: number };
    stats: {
        total: number;
        yearToDateSales: number;
        currentMonthSales: number;
        currentOutstanding: number;
    };
    canManage: boolean;
}) {
    const [search, setSearch] = useState(filters.search),
        [open, setOpen] = useState(false),
        [editing, setEditing] = useState<Summary | null>(null),
        [detail, setDetail] = useState<Summary | null>(null),
        [selected, setSelected] = useState<number[]>([]),
        [bulk, setBulk] = useState('');
    const [importing, setImporting] = useState(false),
        [importError, setImportError] = useState('');
    const importInput = useRef<HTMLInputElement>(null);
    const form = useForm<FormData>(empty);
    const load = (page = 1) =>
        router.get('/admin/sales-distribution/customer-sales-summary', { search, page }, { preserveState: true, replace: true });
    const create = () => {
        setEditing(null);
        form.setData(empty);
        form.clearErrors();
        setOpen(true);
    };
    const edit = (row: Summary) => {
        setEditing(row);
        form.setData(
            Object.fromEntries(
                Object.keys(empty).map((k) => [k, k === 'status' ? Boolean(row.status) : String(row[k as keyof Summary] ?? '')]),
            ) as FormData,
        );
        form.clearErrors();
        setOpen(true);
    };
    const submit = (e: FormEvent) => {
        e.preventDefault();
        const o = { preserveScroll: true, onSuccess: () => setOpen(false) };
        if (editing) {
            form.put(`/admin/sales-distribution/customer-sales-summary/${editing.id}`, o);
        } else {
            form.post('/admin/sales-distribution/customer-sales-summary', o);
        }
    };
    const runBulk = () => {
        if (!bulk || !selected.length || (bulk === 'delete' && !confirm(`Delete ${selected.length} records?`))) return;
        router.patch(
            '/admin/sales-distribution/customer-sales-summary/bulk',
            { ids: selected, action: bulk },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelected([]);
                    setBulk('');
                },
            },
        );
    };
    const importSheet = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) return;
        setImportError('');
        setImporting(true);
        router.post(
            '/admin/sales-distribution/customer-sales-summary/import',
            { file },
            {
                forceFormData: true,
                preserveScroll: true,
                onError: (errors) => setImportError(String(errors.file || 'The sheet could not be imported.')),
                onFinish: () => {
                    setImporting(false);
                    event.target.value = '';
                },
            },
        );
    };
    const all = summaries.data.length > 0 && summaries.data.every((r) => selected.includes(r.id));
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Sales & Distribution', href: '/admin/sales-distribution' },
                {
                    title: 'Customer Sales Summary',
                    href: '/admin/sales-distribution/customer-sales-summary',
                },
            ]}
        >
            <Head title="Customer Sales Summary" />
            <div className="mx-auto w-full max-w-[1680px] space-y-4 p-3 md:p-6">
                <MasterPageHeader
                    title="Customer Sales Summary Master"
                    description={`${stats.total.toLocaleString('en-IN')} customers  —  YTD: ₹${crores(stats.yearToDateSales)} Cr  —  MTD: ₹${crores(stats.currentMonthSales)} Cr`}
                    icon={<BadgeIndianRupee className="size-5" />}
                    action={
                        <div className="flex flex-wrap justify-end gap-2">
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    load();
                                }}
                                className="relative"
                            >
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-cyan-100" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search customer or terms…"
                                    className="h-9 w-80 border-cyan-300/40 bg-cyan-500/20 pl-9 text-white placeholder:text-cyan-100/80"
                                />
                            </form>
                            {canManage && (
                                <>
                                    <input ref={importInput} type="file" accept=".xlsx,.csv,.txt" className="hidden" onChange={importSheet} />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={importing}
                                        onClick={() => importInput.current?.click()}
                                        className="h-9 border-white/60 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                                    >
                                        <Upload className="size-4" /> {importing ? 'Importing…' : 'Import sheet'}
                                    </Button>
                                    <Button type="button" onClick={create} className="h-9 bg-orange-500 text-white hover:bg-orange-600">
                                        <Plus className="size-4" /> Add record
                                    </Button>
                                </>
                            )}
                        </div>
                    }
                />
                {importError && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{importError}</div>
                )}
                <section className="flex min-h-0 flex-col overflow-hidden rounded-2xl border bg-white shadow-sm">
                    {canManage && selected.length > 0 && (
                        <div className="flex shrink-0 items-center gap-3 border-b bg-cyan-50 px-4 py-3">
                            <b className="text-xs text-cyan-700">{selected.length} selected</b>
                            <Select value={bulk} onValueChange={setBulk}>
                                <SelectTrigger className="h-9 w-44 bg-white">
                                    <SelectValue placeholder="Bulk action" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="activate">Mark active</SelectItem>
                                    <SelectItem value="deactivate">Mark inactive</SelectItem>
                                    <SelectItem value="delete">Delete selected</SelectItem>
                                </SelectContent>
                            </Select>
                            <Button size="sm" variant="outline" onClick={runBulk}>
                                Apply
                            </Button>
                        </div>
                    )}
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1480px] text-left text-sm">
                            <thead className="sticky top-0 z-10 bg-slate-50 text-[10px] font-bold tracking-wider text-slate-500 uppercase shadow-sm">
                                <tr>
                                    <th className="px-4 text-center">Action</th>
                                    <th className="px-4 py-3">
                                        <Checkbox
                                            checked={all ? true : selected.length ? 'indeterminate' : false}
                                            onCheckedChange={(v) => setSelected(v === true ? summaries.data.map((r) => r.id) : [])}
                                        />
                                    </th>
                                    <th>S.No.</th>
                                    <th>Customer</th>
                                    <th className="text-right">Sales</th>
                                    <th className="text-right">Outstanding</th>
                                    <th className="text-right">Overdue</th>
                                    <th className="text-right">Payment received (2 months ago)</th>
                                    <th className="text-right">Payment received (last month)</th>
                                    <th className="text-right">Payment received (current month)</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {summaries.data.map((r, i) => (
                                    <Fragment key={r.id}>
                                        <tr className="hover:bg-cyan-50/30">
                                            <td className="px-4 text-center">
                                                <Button
                                                    size="icon"
                                                    variant="outline"
                                                    onClick={() => setDetail(detail?.id === r.id ? null : r)}
                                                    aria-label={`View ${r.customer_number}`}
                                                >
                                                    <ChevronRight
                                                        className={`size-4 transition-transform ${detail?.id === r.id ? 'rotate-90' : ''}`}
                                                    />
                                                </Button>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Checkbox
                                                    checked={selected.includes(r.id)}
                                                    onCheckedChange={(v) =>
                                                        setSelected((c) => (v === true ? [...new Set([...c, r.id])] : c.filter((id) => id !== r.id)))
                                                    }
                                                />
                                            </td>
                                            <td className="text-xs font-bold text-slate-400">
                                                {(summaries.current_page - 1) * filters.per_page + i + 1}
                                            </td>
                                            <td>
                                                <b>{r.customer_name || 'Customer'}</b>
                                                <div className="font-mono text-xs text-slate-500">{r.customer_number}</div>
                                            </td>
                                            <td className="text-right">
                                                <b>₹{money(r.year_to_date_sales)}</b>
                                                <div className="text-xs text-slate-500">
                                                    Current ₹{money(r.current_month_sales)} · Pending ₹{money(r.pending_sales)}
                                                </div>
                                            </td>
                                            <td className="text-right">
                                                <b>₹{money(r.current_month_outstanding)}</b>
                                                <div className="text-xs text-slate-500">Last ₹{money(r.last_month_outstanding)}</div>
                                            </td>
                                            <td className="text-right text-orange-600">
                                                <b>₹{money(r.current_month_overdue)}</b>
                                                <div className="text-xs">Last ₹{money(r.last_month_overdue)}</div>
                                            </td>
                                            <td className="text-right font-semibold">₹{money(r.two_months_ago_payment_received)}</td>
                                            <td className="text-right font-semibold">₹{money(r.last_month_payment_received)}</td>
                                            <td className="text-right font-semibold">₹{money(r.current_month_payment_received)}</td>
                                        </tr>
                                        {detail?.id === r.id && (
                                            <tr className="bg-cyan-50/40">
                                                <td colSpan={10} className="px-5 py-4">
                                                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                                        <div className="rounded-xl border bg-white p-4">
                                                            <p className="text-xs font-bold text-slate-500 uppercase">Payment terms</p>
                                                            <p className="mt-1 font-bold">{r.payment_terms_code || '—'}</p>
                                                            <p className="text-sm text-slate-600">
                                                                {r.payment_terms_description || 'No description'}
                                                            </p>
                                                        </div>
                                                        <div className="rounded-xl border bg-white p-4">
                                                            <p className="text-xs font-bold text-slate-500 uppercase">Credit limit</p>
                                                            <p className="mt-1 text-lg font-bold">₹{money(r.credit_limit)}</p>
                                                        </div>
                                                        <div className="rounded-xl border bg-white p-4">
                                                            <p className="text-xs font-bold text-slate-500 uppercase">Last year sales</p>
                                                            <p className="mt-1 text-lg font-bold">₹{money(r.last_year_sales)}</p>
                                                        </div>
                                                        <div className="rounded-xl border bg-white p-4">
                                                            <p className="text-xs font-bold text-slate-500 uppercase">Status</p>
                                                            <div className="mt-2">
                                                                <StatusToggle
                                                                    active={Boolean(r.status)}
                                                                    disabled={!canManage}
                                                                    label={r.customer_number}
                                                                    onChange={() =>
                                                                        router.patch(
                                                                            `/admin/sales-distribution/customer-sales-summary/${r.id}/status`,
                                                                            { active: !r.status },
                                                                            {
                                                                                preserveScroll: true,
                                                                                onSuccess: () => setDetail(null),
                                                                            },
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                    {canManage && (
                                                        <div className="mt-3 flex justify-end gap-2">
                                                            <Button
                                                                variant="outline"
                                                                onClick={() => {
                                                                    setDetail(null);
                                                                    edit(r);
                                                                }}
                                                            >
                                                                <Edit3 className="size-4" /> Edit
                                                            </Button>
                                                            <Button
                                                                variant="outline"
                                                                className="text-red-600"
                                                                onClick={() =>
                                                                    confirm('Delete this record?') &&
                                                                    router.delete(`/admin/sales-distribution/customer-sales-summary/${r.id}`, {
                                                                        preserveScroll: true,
                                                                        onSuccess: () => setDetail(null),
                                                                    })
                                                                }
                                                            >
                                                                <Trash2 className="size-4" /> Delete
                                                            </Button>
                                                        </div>
                                                    )}
                                                </td>
                                            </tr>
                                        )}
                                    </Fragment>
                                ))}
                                {!summaries.data.length && (
                                    <tr>
                                        <td colSpan={10} className="py-20 text-center text-slate-500">
                                            No customer sales summaries found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex shrink-0 items-center justify-between border-t bg-slate-50 px-4 py-3 text-xs text-slate-500">
                        <span>
                            Showing{' '}
                            <b>
                                {summaries.from ?? 0}–{summaries.to ?? 0}
                            </b>{' '}
                            of <b>{summaries.total}</b> · Page <b>{summaries.current_page}</b> of <b>{summaries.last_page}</b>
                        </span>
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={summaries.current_page <= 1}
                                onClick={() => load(summaries.current_page - 1)}
                            >
                                <ChevronLeft className="size-4" /> Previous
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={summaries.current_page >= summaries.last_page}
                                onClick={() => load(summaries.current_page + 1)}
                            >
                                Next <ChevronRight className="size-4" />
                            </Button>
                        </div>
                    </div>
                </section>
            </div>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="h-dvh max-h-dvh w-full max-w-none gap-0 overflow-hidden rounded-none border-0 !p-0 sm:h-auto sm:max-h-[90dvh] sm:w-[calc(100vw-2rem)] sm:max-w-[1100px] sm:rounded-xl sm:border [&>button]:top-4 [&>button]:right-4 [&>button]:z-10 [&>button]:rounded-full [&>button]:p-1.5 [&>button]:text-white/70 [&>button]:hover:bg-white/10 [&>button]:hover:text-white sm:[&>button]:top-5 sm:[&>button]:right-5">
                    <form onSubmit={submit} className="grid h-full min-h-0 grid-rows-[auto_minmax(0,1fr)_auto] sm:h-auto sm:max-h-[90dvh]">
                        <MasterDialogHeader title={editing ? 'Edit customer sales summary' : 'Create customer sales summary'} />
                        <div className="min-h-0 space-y-3 overflow-y-auto overscroll-contain bg-slate-50 p-3 sm:p-5">
                            {sections.map((s) => (
                                <fieldset key={s.title} className="rounded-lg border border-slate-200 bg-white p-3 sm:p-4">
                                    <legend className="px-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">{s.title}</legend>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {s.fields.map(([key, label]) => (
                                            <div key={key} className="min-w-0">
                                                <Label htmlFor={key} className="mb-1.5 block text-xs font-medium text-slate-600">
                                                    {label}
                                                </Label>
                                                <Input
                                                    id={key}
                                                    className="h-11 bg-white"
                                                    type={key === 'customer_number' || key.includes('terms') ? 'text' : 'number'}
                                                    step="0.001"
                                                    value={String(form.data[key])}
                                                    onChange={(e) => form.setData(key, e.target.value as never)}
                                                />
                                                <InputError message={form.errors[key]} />
                                            </div>
                                        ))}
                                    </div>
                                </fieldset>
                            ))}
                            <div className="rounded-lg border border-slate-200 bg-white p-3 sm:p-4">
                                <Label className="mb-1.5 block text-xs font-medium text-slate-600">Status</Label>
                                <Select
                                    value={form.data.status ? 'active' : 'inactive'}
                                    onValueChange={(v) => form.setData('status', v === 'active')}
                                >
                                    <SelectTrigger className="h-11 sm:max-w-[calc(50%-0.375rem)]">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <DialogFooter className="shrink-0 gap-2 border-t bg-white px-4 py-3 sm:px-6 sm:py-4">
                            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                                Cancel
                            </Button>
                            <Button disabled={form.processing} className="bg-brand-teal">
                                {form.processing ? 'Saving…' : editing ? 'Update record' : 'Create record'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
