import InputError from '@/components/input-error';
import { MasterDialogHeader, masterDialogBodyClass, masterDialogContentClass, masterDialogFooterClass, masterDialogFormClass } from '@/components/master/master-dialog-header';
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
import { ChevronLeft, ChevronRight, Edit3, Plus, Search, Trash2, TrendingUp } from 'lucide-react';
import { FormEvent, useState } from 'react';

type FormData = {
    billing_document_number: string;
    billing_type: string;
    billing_date: string;
    record_creation_date: string;
    company_code: string;
    plant_code: string;
    customer_number: string;
    customer_name: string;
    material_number: string;
    material_description: string;
    material_group: string;
    material_group_description: string;
    product_hierarchy: string;
    product_hierarchy_description: string;
    external_material_group: string;
    external_material_group_description: string;
    billed_quantity: string;
    billed_quantity_kg: string;
    billed_quantity_liters: string;
    billed_quantity_meters: string;
    sales_unit: string;
    base_unit: string;
    net_value: string;
    tax_amount: string;
    fiscal_year: string;
    condition_value: string;
    status: boolean;
};
type Sale = Record<string, string | number | boolean | null> & FormData & { id: number };
type Page = { data: Sale[]; current_page: number; last_page: number; total: number; from: number | null; to: number | null };
type Field = [keyof FormData, string, string?];
const empty: FormData = {
    billing_document_number: '',
    billing_type: '',
    billing_date: '',
    record_creation_date: '',
    company_code: '',
    plant_code: '',
    customer_number: '',
    customer_name: '',
    material_number: '',
    material_description: '',
    material_group: '',
    material_group_description: '',
    product_hierarchy: '',
    product_hierarchy_description: '',
    external_material_group: '',
    external_material_group_description: '',
    billed_quantity: '0',
    billed_quantity_kg: '0',
    billed_quantity_liters: '0',
    billed_quantity_meters: '0',
    sales_unit: '',
    base_unit: '',
    net_value: '0',
    tax_amount: '0',
    fiscal_year: '',
    condition_value: '0',
    status: true,
};
const sections: Array<{ title: string; fields: Field[] }> = [
    {
        title: 'Billing and customer',
        fields: [
            ['billing_document_number', 'Billing document number'],
            ['billing_type', 'Billing type'],
            ['billing_date', 'Billing date', 'date'],
            ['record_creation_date', 'Record creation date', 'date'],
            ['company_code', 'Company code'],
            ['plant_code', 'Plant code'],
            ['customer_number', 'Customer number'],
            ['customer_name', 'Customer name'],
            ['fiscal_year', 'Fiscal year'],
        ],
    },
    {
        title: 'Material classification',
        fields: [
            ['material_number', 'Material number'],
            ['material_description', 'Material description'],
            ['material_group', 'Material group'],
            ['material_group_description', 'Material group description'],
            ['product_hierarchy', 'Product hierarchy'],
            ['product_hierarchy_description', 'Product hierarchy description'],
            ['external_material_group', 'External material group'],
            ['external_material_group_description', 'External material group description'],
        ],
    },
    {
        title: 'Quantity and value',
        fields: [
            ['billed_quantity', 'Billed quantity', 'number'],
            ['billed_quantity_kg', 'Billed quantity (kg)', 'number'],
            ['billed_quantity_liters', 'Billed quantity (liters)', 'number'],
            ['billed_quantity_meters', 'Billed quantity (meters)', 'number'],
            ['sales_unit', 'Sales unit'],
            ['base_unit', 'Base unit'],
            ['net_value', 'Net value', 'number'],
            ['tax_amount', 'Tax amount', 'number'],
            ['condition_value', 'Condition value', 'number'],
        ],
    },
];
const money = (value: unknown) => Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const val = (sale: Sale, key: string) => String(sale[key] ?? '');

export default function DailySales({
    dailySales,
    filters,
    stats,
    canManage,
}: {
    dailySales: Page;
    filters: { search: string; per_page: number };
    stats: { total: number; active: number; netValue: number; taxAmount: number };
    canManage: boolean;
}) {
    const [search, setSearch] = useState(filters.search),
        [open, setOpen] = useState(false),
        [editing, setEditing] = useState<Sale | null>(null),
        [selected, setSelected] = useState<number[]>([]),
        [bulk, setBulk] = useState('');
    const form = useForm<FormData>(empty);
    const load = (page = 1) => router.get('/admin/sales-distribution/daily-sales', { search, page }, { preserveState: true, replace: true });
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
                    key === 'status' ? Boolean(sale[key]) : val(sale, key).slice(0, key.includes('date') ? 10 : undefined),
                ]),
            ) as FormData,
        );
        form.clearErrors();
        setOpen(true);
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        editing
            ? form.put(`/admin/sales-distribution/daily-sales/${editing.id}`, options)
            : form.post('/admin/sales-distribution/daily-sales', options);
    };
    const runBulk = () => {
        if (!bulk || !selected.length || (bulk === 'delete' && !confirm(`Delete ${selected.length} records?`))) return;
        router.patch(
            '/admin/sales-distribution/daily-sales/bulk',
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
    const all = dailySales.data.length > 0 && dailySales.data.every((sale) => selected.includes(sale.id));
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Sales & Distribution', href: '/admin/sales-distribution' },
                { title: 'Daily Sales', href: '/admin/sales-distribution/daily-sales' },
            ]}
        >
            <Head title="Daily Sales" />
            <div className="mx-auto w-full max-w-[1680px] space-y-4 p-3 md:p-6">
                <MasterPageHeader
                    title="Daily Sales Master"
                    description={`${stats.total} records · ₹${money(stats.netValue)} net value · ₹${money(stats.taxAmount)} tax`}
                    icon={<TrendingUp className="size-5" />}
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
                                    placeholder="Search customer, bill or material…"
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
                        <table className="w-full min-w-[1450px] text-left text-sm">
                            <thead className="sticky top-0 z-10 bg-slate-50 text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                                <tr>
                                    <th className="px-4 py-3">
                                        <Checkbox
                                            checked={all ? true : selected.length ? 'indeterminate' : false}
                                            onCheckedChange={(checked) => setSelected(checked === true ? dailySales.data.map((sale) => sale.id) : [])}
                                        />
                                    </th>
                                    <th>S.No.</th>
                                    <th>Customer / plant</th>
                                    <th>Billing document</th>
                                    <th>Material</th>
                                    <th className="text-right">Quantity</th>
                                    <th className="text-right">Net value</th>
                                    <th className="text-right">Tax</th>
                                    <th>Status</th>
                                    <th className="pr-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {dailySales.data.map((sale, index) => (
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
                                            {(dailySales.current_page - 1) * filters.per_page + index + 1}
                                        </td>
                                        <td>
                                            <b>{sale.customer_name || sale.customer_number}</b>
                                            <div className="text-xs text-slate-500">
                                                {sale.customer_number} · Plant {sale.plant_code}
                                            </div>
                                        </td>
                                        <td>
                                            <b className="font-mono text-xs">{sale.billing_document_number}</b>
                                            <div className="text-xs text-slate-500">
                                                {val(sale, 'billing_date') || '—'} · {val(sale, 'billing_type') || '—'}
                                            </div>
                                        </td>
                                        <td>
                                            <b>{sale.material_number}</b>
                                            <div className="max-w-80 truncate text-xs text-slate-500">
                                                {val(sale, 'material_description') || '—'} · {val(sale, 'material_group') || '—'}
                                            </div>
                                        </td>
                                        <td className="text-right tabular-nums">
                                            <b>{money(sale.billed_quantity)}</b>
                                            <div className="text-xs text-slate-500">{val(sale, 'sales_unit') || val(sale, 'base_unit') || '—'}</div>
                                        </td>
                                        <td className="text-right font-semibold">₹{money(sale.net_value)}</td>
                                        <td className="text-right font-semibold">₹{money(sale.tax_amount)}</td>
                                        <td>
                                            <StatusToggle
                                                active={Boolean(sale.status)}
                                                disabled={!canManage}
                                                label={sale.billing_document_number}
                                                onChange={() =>
                                                    router.patch(
                                                        `/admin/sales-distribution/daily-sales/${sale.id}/status`,
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
                                                                router.delete(`/admin/sales-distribution/daily-sales/${sale.id}`, {
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
                                {!dailySales.data.length && (
                                    <tr>
                                        <td colSpan={10} className="py-20 text-center text-slate-500">
                                            No daily sales records found.
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
                                {dailySales.from ?? 0}–{dailySales.to ?? 0}
                            </b>{' '}
                            of <b>{dailySales.total}</b>
                        </span>
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={dailySales.current_page <= 1}
                                onClick={() => load(dailySales.current_page - 1)}
                            >
                                <ChevronLeft className="size-4" /> Previous
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={dailySales.current_page >= dailySales.last_page}
                                onClick={() => load(dailySales.current_page + 1)}
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
                        <MasterDialogHeader compact title={editing ? 'Edit daily sale' : 'Create daily sale'} />
                        <div className={`${masterDialogBodyClass} space-y-5`}>
                            {sections.map((section) => (
                                <fieldset key={section.title} className="form-fieldset !m-0 !px-4 !pb-5">
                                    <legend className="px-2 text-sm font-bold text-cyan-600">{section.title}</legend>
                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        {section.fields.map(([key, label, type = 'text']) => (
                                            <div key={key}>
                                                <Label htmlFor={key} className="mb-1.5 block text-xs font-semibold">
                                                    {label}
                                                </Label>
                                                <Input
                                                    id={key}
                                                    type={type}
                                                    step={type === 'number' ? '0.001' : undefined}
                                                    value={String(form.data[key])}
                                                    onChange={(e) => form.setData(key, e.target.value)}
                                                />
                                                <InputError message={form.errors[key]} />
                                            </div>
                                        ))}
                                    </div>
                                </fieldset>
                            ))}
                            <div className="w-64">
                                <Label className="mb-1.5 block text-xs font-semibold">Status</Label>
                                <Select
                                    value={form.data.status ? 'active' : 'inactive'}
                                    onValueChange={(value) => form.setData('status', value === 'active')}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
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
