import InputError from '@/components/input-error';
import {
    MasterDialogHeader,
    masterDialogBodyClass,
    masterDialogContentClass,
    masterDialogFooterClass,
    masterDialogFormClass,
} from '@/components/master/master-dialog-header';
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
import { ChevronLeft, ChevronRight, Edit3, FileSpreadsheet, Plus, Search, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Sale = Record<string, string | number | boolean | null> & {
    id: number;
    customer_number: string;
    customer_name?: string | null;
    sales_document_number: string;
    material_number: string;
    status: boolean;
};
type Page = { data: Sale[]; current_page: number; last_page: number; total: number; from: number | null; to: number | null };
type FormData = {
    customer_number: string;
    plant_code: string;
    sales_document_number: string;
    document_date: string;
    external_material_group: string;
    material_group: string;
    material_number: string;
    order_quantity: string;
    amount: string;
    open_delivery_amount: string;
    delivered_quantity: string;
    material_text_1: string;
    material_text_2: string;
    material_text_3: string;
    status: boolean;
};
const empty: FormData = {
    customer_number: '',
    plant_code: '',
    sales_document_number: '',
    document_date: '',
    external_material_group: '',
    material_group: '',
    material_number: '',
    order_quantity: '0',
    amount: '0',
    open_delivery_amount: '0',
    delivered_quantity: '0',
    material_text_1: '',
    material_text_2: '',
    material_text_3: '',
    status: true,
};
const fields: Array<[keyof FormData, string, string?]> = [
    ['customer_number', 'Customer number'],
    ['plant_code', 'Plant code'],
    ['sales_document_number', 'Sales document number'],
    ['document_date', 'Document date', 'date'],
    ['external_material_group', 'External material group'],
    ['material_group', 'Material group'],
    ['material_number', 'Material number'],
    ['order_quantity', 'Order quantity', 'number'],
    ['amount', 'Amount', 'number'],
    ['open_delivery_amount', 'Open delivery amount', 'number'],
    ['delivered_quantity', 'Delivered quantity', 'number'],
    ['material_text_1', 'Material text 1'],
    ['material_text_2', 'Material text 2'],
    ['material_text_3', 'Material text 3'],
];
const money = (value: unknown) => Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const value = (sale: Sale, key: string) => String(sale[key] ?? '');

export default function PendingSales({
    pendingSales,
    filters,
    stats,
    canManage,
}: {
    pendingSales: Page;
    filters: { search: string; per_page: number };
    stats: { total: number; active: number; amount: number; openAmount: number };
    canManage: boolean;
}) {
    const [search, setSearch] = useState(filters.search);
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Sale | null>(null);
    const [selected, setSelected] = useState<number[]>([]);
    const [bulk, setBulk] = useState('');
    const form = useForm<FormData>(empty);
    const load = (page = 1) => router.get('/admin/sales-distribution/pending-sales', { search, page }, { preserveState: true, replace: true });
    const create = () => {
        setEditing(null);
        form.setData(empty);
        form.clearErrors();
        setOpen(true);
    };
    const edit = (sale: Sale) => {
        setEditing(sale);
        form.setData(
            Object.fromEntries(
                Object.keys(empty).map((key) => [
                    key,
                    key === 'status' ? Boolean(sale[key]) : value(sale, key).slice(0, key === 'document_date' ? 10 : undefined),
                ]),
            ) as FormData,
        );
        form.clearErrors();
        setOpen(true);
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        if (editing) form.put(`/admin/sales-distribution/pending-sales/${editing.id}`, options);
        else form.post('/admin/sales-distribution/pending-sales', options);
    };
    const runBulk = () => {
        if (!bulk || !selected.length || (bulk === 'delete' && !confirm(`Delete ${selected.length} records?`))) return;
        router.patch(
            '/admin/sales-distribution/pending-sales/bulk',
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
    const all = pendingSales.data.length > 0 && pendingSales.data.every((sale) => selected.includes(sale.id));
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Sales & Distribution', href: '/admin/sales-distribution' },
                { title: 'Pending Order', href: '/admin/sales-distribution/pending-sales' },
            ]}
        >
            <Head title="Pending Order" />
            <div className="mx-auto w-full max-w-[1680px] space-y-4 p-3 md:p-6">
                <MasterPageHeader
                    title="Pending Order Master"
                    description={`${stats.total} records · ${stats.active} active · ₹${money(stats.openAmount)} open`}
                    icon={<FileSpreadsheet className="size-5" />}
                    action={
                        <div className="flex gap-2">
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    load();
                                }}
                                className="relative"
                            >
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-cyan-100" />
                                <Input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Search customer, order or material…"
                                    className="h-9 w-80 border-cyan-300/40 bg-cyan-500/20 pl-9 text-white placeholder:text-cyan-100/80"
                                />
                            </form>
                            {canManage && (
                                <Button type="button" onClick={create} className="h-9 bg-orange-500 text-white hover:bg-orange-600">
                                    <Plus className="size-4" /> Add record
                                </Button>
                            )}
                        </div>
                    }
                />
                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {canManage && selected.length > 0 && (
                        <div className="flex items-center gap-3 border-b bg-cyan-50 px-4 py-3">
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
                        <table className="w-full min-w-[1250px] text-left text-sm">
                            <thead className="sticky top-0 z-10 bg-slate-50 text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                                <tr>
                                    <th className="px-4 py-3">
                                        <Checkbox
                                            checked={all ? true : selected.length ? 'indeterminate' : false}
                                            onCheckedChange={(checked) =>
                                                setSelected(checked === true ? pendingSales.data.map((sale) => sale.id) : [])
                                            }
                                        />
                                    </th>
                                    <th>S.No.</th>
                                    <th>Customer / plant</th>
                                    <th>Sales document</th>
                                    <th>Material</th>
                                    <th className="text-right">Ordered / delivered</th>
                                    <th className="text-right">Amount</th>
                                    <th className="text-right">Open delivery</th>
                                    <th>Status</th>
                                    <th className="pr-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {pendingSales.data.map((sale, index) => (
                                    <tr key={sale.id} className="hover:bg-cyan-50/30">
                                        <td className="px-4 py-3">
                                            <Checkbox
                                                checked={selected.includes(sale.id)}
                                                onCheckedChange={(checked) =>
                                                    setSelected((current) =>
                                                        checked === true
                                                            ? [...new Set([...current, sale.id])]
                                                            : current.filter((id) => id !== sale.id),
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="text-xs font-bold text-slate-400">
                                            {(pendingSales.current_page - 1) * filters.per_page + index + 1}
                                        </td>
                                        <td>
                                            <b>{sale.customer_name || sale.customer_number}</b>
                                            <div className="text-xs text-slate-500">
                                                {sale.customer_name ? `${sale.customer_number} · ` : ''}Plant {value(sale, 'plant_code') || '—'}
                                            </div>
                                        </td>
                                        <td>
                                            <b className="font-mono text-xs">{sale.sales_document_number}</b>
                                            <div className="text-xs text-slate-500">{value(sale, 'document_date') || '—'}</div>
                                        </td>
                                        <td>
                                            <b>{sale.material_number}</b>
                                            <div className="max-w-72 truncate text-xs text-slate-500">
                                                {value(sale, 'material_text_1') || '—'} · {value(sale, 'material_group') || '—'}
                                            </div>
                                        </td>
                                        <td className="text-right tabular-nums">
                                            <b>{money(sale.order_quantity)}</b>
                                            <div className="text-xs text-slate-500">{money(sale.delivered_quantity)}</div>
                                        </td>
                                        <td className="text-right font-semibold">₹{money(sale.amount)}</td>
                                        <td className="text-right font-semibold text-orange-600">₹{money(sale.open_delivery_amount)}</td>
                                        <td>
                                            <StatusToggle
                                                active={sale.status}
                                                disabled={!canManage}
                                                label={sale.sales_document_number}
                                                onChange={() =>
                                                    router.patch(
                                                        `/admin/sales-distribution/pending-sales/${sale.id}/status`,
                                                        { active: !sale.status },
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="pr-4">
                                            <div className="flex justify-end">
                                                {canManage && (
                                                    <>
                                                        <Button type="button" size="icon" variant="ghost" onClick={() => edit(sale)}>
                                                            <Edit3 className="size-4" />
                                                        </Button>
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            className="text-red-500"
                                                            onClick={() =>
                                                                confirm('Delete this record?') &&
                                                                router.delete(`/admin/sales-distribution/pending-sales/${sale.id}`, {
                                                                    preserveScroll: true,
                                                                })
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
                                {!pendingSales.data.length && (
                                    <tr>
                                        <td colSpan={10} className="py-20 text-center text-slate-500">
                                            No pending sales records found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex justify-between border-t bg-slate-50 px-4 py-3 text-xs text-slate-500">
                        <span>
                            Showing{' '}
                            <b>
                                {pendingSales.from ?? 0}–{pendingSales.to ?? 0}
                            </b>{' '}
                            of <b>{pendingSales.total}</b>
                        </span>
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={pendingSales.current_page <= 1}
                                onClick={() => load(pendingSales.current_page - 1)}
                            >
                                <ChevronLeft className="size-4" /> Previous
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={pendingSales.current_page >= pendingSales.last_page}
                                onClick={() => load(pendingSales.current_page + 1)}
                            >
                                Next <ChevronRight className="size-4" />
                            </Button>
                        </div>
                    </div>
                </section>
            </div>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className={masterDialogContentClass}>
                    <form onSubmit={submit} className={masterDialogFormClass}>
                        <MasterDialogHeader compact title={editing ? 'Edit pending order' : 'Create pending order'} />
                        <div className={masterDialogBodyClass}>
                            <fieldset className="form-fieldset !m-0 !px-4 !pb-5">
                                <legend className="px-2 text-sm font-bold text-cyan-600">Pending order details</legend>
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {fields.map(([key, label, type = 'text']) => (
                                        <div key={key}>
                                            <Label htmlFor={key} className="mb-1.5 block text-xs font-semibold">
                                                {label}
                                            </Label>
                                            <Input
                                                id={key}
                                                type={type}
                                                step={type === 'number' ? '0.01' : undefined}
                                                value={String(form.data[key])}
                                                onChange={(event) => form.setData(key, event.target.value)}
                                            />
                                            <InputError message={form.errors[key]} />
                                        </div>
                                    ))}
                                </div>
                            </fieldset>
                        </div>
                        <DialogFooter className={masterDialogFooterClass}>
                            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                                Cancel
                            </Button>
                            <Button disabled={form.processing} className="bg-cyan-600 text-white">
                                {form.processing ? 'Saving…' : editing ? 'Update record' : 'Create record'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
