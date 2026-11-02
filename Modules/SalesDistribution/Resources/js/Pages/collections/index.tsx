import InputError from '@/components/input-error';
import { MasterDialogHeader, masterDialogBodyClass, masterDialogContentClass, masterDialogFooterClass, masterDialogFormClass } from '@/components/master/master-dialog-header';
import { MasterPageHeader } from '@/components/master/master-page-header';
import { StatusToggle } from '@/components/master/status-toggle';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { BadgeIndianRupee, ChevronLeft, ChevronRight, Edit3, Plus, Search, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Row = {
    id: number;
    customer_number: string;
    company_code: string | null;
    document_number: string;
    fiscal_year: number;
    posting_date: string;
    document_date: string | null;
    document_type: string;
    amount_received: string;
    currency: string | null;
    reference_number: string | null;
    description: string | null;
    status: boolean;
};
type Page = { data: Row[]; current_page: number; last_page: number; total: number; from: number | null; to: number | null };
const empty = {
    customer_number: '',
    company_code: '',
    document_number: '',
    fiscal_year: String(new Date().getFullYear()),
    posting_date: '',
    document_date: '',
    document_type: 'DZ',
    amount_received: '0',
    currency: 'INR',
    reference_number: '',
    description: '',
    status: true,
};
type Form = typeof empty;
const money = (v: unknown) => Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function Collections({
    collections,
    filters,
    stats,
    canManage,
}: {
    collections: Page;
    filters: { search: string; per_page: number };
    stats: { total: number; active: number; amount: number };
    canManage: boolean;
}) {
    const [search, setSearch] = useState(filters.search),
        [open, setOpen] = useState(false),
        [editing, setEditing] = useState<Row | null>(null),
        [selected, setSelected] = useState<number[]>([]);
    const form = useForm<Form>(empty);
    const load = (page = 1) => router.get('/admin/sales-distribution/collections', { search, page }, { preserveState: true, replace: true });
    const create = () => {
        setEditing(null);
        form.setData(empty);
        form.clearErrors();
        setOpen(true);
    };
    const edit = (r: Row) => {
        setEditing(r);
        form.setData({
            customer_number: r.customer_number,
            company_code: r.company_code || '',
            document_number: r.document_number,
            fiscal_year: String(r.fiscal_year),
            posting_date: r.posting_date?.slice(0, 10) || '',
            document_date: r.document_date?.slice(0, 10) || '',
            document_type: r.document_type,
            amount_received: String(r.amount_received),
            currency: r.currency || 'INR',
            reference_number: r.reference_number || '',
            description: r.description || '',
            status: Boolean(r.status),
        });
        setOpen(true);
    };
    const submit = (e: FormEvent) => {
        e.preventDefault();
        const o = { preserveScroll: true, onSuccess: () => setOpen(false) };
        editing ? form.put(`/admin/sales-distribution/collections/${editing.id}`, o) : form.post('/admin/sales-distribution/collections', o);
    };
    const all = collections.data.length > 0 && collections.data.every((r) => selected.includes(r.id));
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Sales & Distribution', href: '/admin/sales-distribution' },
                { title: 'Collections', href: '/admin/sales-distribution/collections' },
            ]}
        >
            <Head title="Collections" />
            <div className="mx-auto w-full max-w-[1680px] space-y-4 p-3 md:p-6">
                <MasterPageHeader
                    title="Collection Master"
                    description={`${stats.total.toLocaleString('en-IN')} receipts · ₹${money(stats.amount)} received`}
                    icon={<BadgeIndianRupee className="size-5" />}
                    action={
                        <div className="flex gap-2">
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
                                    placeholder="Search customer or document…"
                                    className="h-9 w-80 border-cyan-300/40 bg-cyan-500/20 pl-9 text-white"
                                />
                            </form>
                            {canManage && (
                                <Button type="button" onClick={create} className="h-9 bg-orange-500 text-white">
                                    <Plus className="size-4" /> Add collection
                                </Button>
                            )}
                        </div>
                    }
                />
                <section className="overflow-hidden rounded-2xl border bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1200px] text-left text-sm">
                            <thead className="sticky top-0 bg-slate-50 text-xs text-slate-500 uppercase">
                                <tr>
                                    <th className="p-4">
                                        <Checkbox
                                            checked={all ? true : selected.length ? 'indeterminate' : false}
                                            onCheckedChange={(v) => setSelected(v === true ? collections.data.map((r) => r.id) : [])}
                                        />
                                    </th>
                                    <th>S.No.</th>
                                    <th>Customer</th>
                                    <th>Document</th>
                                    <th>Date</th>
                                    <th>Company</th>
                                    <th className="text-right">Amount</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {collections.data.map((r, i) => (
                                    <tr key={r.id}>
                                        <td className="p-4">
                                            <Checkbox
                                                checked={selected.includes(r.id)}
                                                onCheckedChange={(v) =>
                                                    setSelected((c) => (v === true ? [...new Set([...c, r.id])] : c.filter((id) => id !== r.id)))
                                                }
                                            />
                                        </td>
                                        <td>{(collections.current_page - 1) * filters.per_page + i + 1}</td>
                                        <td className="font-mono">{r.customer_number}</td>
                                        <td>
                                            <b>{r.document_number}</b>
                                            <div className="text-xs text-slate-500">{r.document_type}</div>
                                        </td>
                                        <td>{r.posting_date?.slice(0, 10)}</td>
                                        <td>{r.company_code || '—'}</td>
                                        <td className="text-right font-semibold">₹{money(r.amount_received)}</td>
                                        <td className="max-w-64 truncate">{r.description || '—'}</td>
                                        <td>
                                            <StatusToggle
                                                active={r.status}
                                                disabled={!canManage}
                                                label={r.document_number}
                                                onChange={() =>
                                                    router.patch(
                                                        `/admin/sales-distribution/collections/${r.id}/status`,
                                                        { active: !r.status },
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            />
                                        </td>
                                        <td>
                                            {canManage && (
                                                <>
                                                    <Button type="button" size="icon" variant="ghost" onClick={() => edit(r)}>
                                                        <Edit3 className="size-4" />
                                                    </Button>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        className="text-red-500"
                                                        onClick={() =>
                                                            confirm('Delete this collection?') &&
                                                            router.delete(`/admin/sales-distribution/collections/${r.id}`)
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {!collections.data.length && (
                                    <tr>
                                        <td colSpan={10} className="py-20 text-center">
                                            No collections found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex justify-between border-t bg-slate-50 p-3 text-xs">
                        <span>
                            Showing {collections.from || 0}–{collections.to || 0} of {collections.total}
                        </span>
                        <div className="flex gap-2">
                            <Button variant="outline" disabled={collections.current_page <= 1} onClick={() => load(collections.current_page - 1)}>
                                <ChevronLeft /> Previous
                            </Button>
                            <Button
                                variant="outline"
                                disabled={collections.current_page >= collections.last_page}
                                onClick={() => load(collections.current_page + 1)}
                            >
                                Next <ChevronRight />
                            </Button>
                        </div>
                    </div>
                </section>
            </div>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className={masterDialogContentClass}>
                    <form onSubmit={submit} className={masterDialogFormClass}>
                        <MasterDialogHeader compact title={editing ? 'Edit collection' : 'Add collection'} />
                        <div className={`${masterDialogBodyClass} grid gap-4 sm:grid-cols-2 lg:grid-cols-3`}>
                            {(
                                [
                                    ['customer_number', 'Customer number'],
                                    ['company_code', 'Company code'],
                                    ['document_number', 'Document number'],
                                    ['fiscal_year', 'Fiscal year', 'number'],
                                    ['posting_date', 'Posting date', 'date'],
                                    ['document_date', 'Document date', 'date'],
                                    ['document_type', 'Document type'],
                                    ['amount_received', 'Amount received', 'number'],
                                    ['currency', 'Currency'],
                                    ['reference_number', 'Reference number'],
                                    ['description', 'Description'],
                                ] as Array<[keyof Form, string, string?]>
                            ).map(([key, label, type = 'text']) => (
                                <div key={key}>
                                    <Label>{label}</Label>
                                    <Input
                                        type={type}
                                        step={type === 'number' ? '0.001' : undefined}
                                        value={String(form.data[key])}
                                        onChange={(e) => form.setData(key, e.target.value as never)}
                                    />
                                    <InputError message={form.errors[key]} />
                                </div>
                            ))}
                        </div>
                        <DialogFooter className={masterDialogFooterClass}>
                            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                                Cancel
                            </Button>
                            <Button disabled={form.processing}>Save collection</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
