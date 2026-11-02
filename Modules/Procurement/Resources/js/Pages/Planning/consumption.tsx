import InputError from '@/components/input-error';
import { MasterDialogHeader } from '@/components/master/master-dialog-header';
import ProcurementPageHeader from '@/components/procurement-page-header';
import { RowExpandButton } from '@/components/row-expand-button';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, Check, ChevronDown, Database, Edit3, Eye, PackageCheck, Plus, Search, ShoppingCart, Trash2, TrendingUp } from 'lucide-react';
import type { FormEventHandler, ReactNode } from 'react';
import { Fragment, useEffect, useMemo, useState } from 'react';
type Company = {
    id: number;
    company_code: string;
    company_name: string;
    record_type: number;
    parent_company_id: string | null;
};
type Material = { matnr: string; maktx: string; mtart: string | null };
type MaterialStock = {
    matnr: string;
    plant_code: string;
    current_stock: number;
};
type ManualPlanningDecision = {
    company_id: number;
    plant_id: number;
    material: string;
    planning_month: string;
};
type Purchase = {
    id: number;
    company_id: number;
    plant_id: number;
    company_name: string | null;
    plant_name: string | null;
    vendor_name: string;
    vendor_source: string;
    material: string;
    purchase_date: string;
    quantity_mt: number;
    expected_delivery_date: string;
    delivery_status: string;
    reference: string | null;
};
type Consumption = {
    id: number;
    company_id: number;
    plant_id: number;
    company_name: string | null;
    plant_name: string | null;
    plant_code: string;
    material: string;
    material_desc: string;
    meins: string;
    consumption_date: string;
    effective_to: string | null;
    period_type: string;
    day_count: number | null;
    quantity_mt: number;
    planned_buying_mt: number;
    imports_mt: number;
    total_buying_mt: number;
    remarks: string | null;
    opening_stock_mt: number | null;
    stock_value: number;
    planned_po_mt: number;
    planned_buying_mt: number;
    total_availability_mt: number;
    total_required_mt: number;
    projected_closing_mt: number;
    gross_shortage_mt: number;
    remaining_shortage_mt: number;
    cover_days: number | null;
    planning_mode: 'auto' | 'manual';
};
const purchaseBlank = {
    company_id: '',
    plant_id: '',
    consumption_id: '',
    vendor_name: '',
    vendor_source: 'sap',
    material: '',
    purchase_date: '',
    quantity_mt: '',
    expected_delivery_date: '',
    delivery_status: 'planned',
    reference: '',
    remarks: '',
};
const consumptionBlank = {
    company_id: '',
    consumption_date: '',
    period_type: 'monthly',
    day_count: '',
    material: '',
    common_remarks: '',
    entries: [] as { plant_id: string; quantity_mt: string; remarks: string }[],
};
const todayInIndia = () => {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Kolkata',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const value = Object.fromEntries(parts.map((part) => [part.type, part.value]));
    return `${value.year}-${value.month}-${value.day}`;
};
const daysInSelectedMonth = (date: string) => {
    const [year, month] = date.split('-').map(Number);
    return year && month ? new Date(year, month, 0).getDate() : 31;
};
const remainingDaysInSelectedMonth = (date: string) => {
    const day = Number(date.split('-')[2]);
    return day ? daysInSelectedMonth(date) - day + 1 : daysInSelectedMonth(date);
};
const formatMt = (value: number | null, decimals = 3) =>
    value === null
        ? 'N/A'
        : Number(value).toLocaleString('en-IN', {
              minimumFractionDigits: decimals,
              maximumFractionDigits: decimals,
          });
const formatMoney = (value: number) =>
    new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 }).format(Number(value || 0));
const emptyStats = {
    purchases: 0,
    purchase_mt: 0,
    planned_mt: 0,
    open_orders: 0,
};
export default function Procurement({
    purchases,
    consumptions,
    companies,
    stats = emptyStats,
    filters,
    planningMode,
    canManagePurchases,
    canManageConsumptions,
}: {
    purchases: Purchase[];
    consumptions: Consumption[];
    companies: Company[];
    stats: {
        purchases: number;
        purchase_mt: number;
        planned_mt: number;
        open_orders: number;
    };
    filters: { search: string; tab: 'purchases' | 'consumptions'; month: string };
    planningMode: 'auto' | 'manual';
    canManagePurchases: boolean;
    canManageConsumptions: boolean;
}) {
    const { datatable } = usePage<SharedData>().props;
    const tab = filters.tab;
    const consumptionMode = planningMode;
    const [purchaseOpen, setPurchaseOpen] = useState(false),
        [consumptionOpen, setConsumptionOpen] = useState(false),
        [viewingConsumption, setViewingConsumption] = useState<Consumption | null>(null),
        [editingConsumption, setEditingConsumption] = useState<Consumption | null>(null),
        [selectedConsumptionIds, setSelectedConsumptionIds] = useState<number[]>([]),
        [expandedConsumptionIds, setExpandedConsumptionIds] = useState<number[]>([]),
        [consumptionPage, setConsumptionPage] = useState(1),
        [consumptionPageSize, setConsumptionPageSize] = useState(datatable.perPage),
        [consumptionSort, setConsumptionSort] = useState<keyof Consumption>('consumption_date'),
        [consumptionSortDirection, setConsumptionSortDirection] = useState<'asc' | 'desc'>('desc'),
        [search, setSearch] = useState(filters.search ?? ''),
        [bulkAction, setBulkAction] = useState(''),
        [materials, setMaterials] = useState<Material[]>([]),
        [materialStocks, setMaterialStocks] = useState<MaterialStock[]>([]),
        [manualPlanningDecisions, setManualPlanningDecisions] = useState<ManualPlanningDecision[]>([]),
        [purchaseVendors, setPurchaseVendors] = useState<string[]>([]),
        [manualVendor, setManualVendor] = useState(false),
        [vendorLoading, setVendorLoading] = useState(false),
        [referenceLoading, setReferenceLoading] = useState(false),
        [referenceError, setReferenceError] = useState('');
    const purchase = useForm(purchaseBlank),
        consumption = useForm(consumptionBlank);
    const loadReferenceData = async () => {
        if (materials.length > 0 || referenceLoading) return;
        setReferenceLoading(true);
        setReferenceError('');
        try {
            const response = await fetch('/admin/procurement/reference-data', {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error(`Unable to load material reference data (HTTP ${response.status}).`);
            const data = (await response.json()) as {
                materials: Material[];
                materialStocks: MaterialStock[];
                manualPlanningDecisions: ManualPlanningDecision[];
            };
            setMaterials(data.materials);
            setMaterialStocks(data.materialStocks);
            setManualPlanningDecisions(data.manualPlanningDecisions);
        } catch (error) {
            setMaterials([]);
            setMaterialStocks([]);
            setManualPlanningDecisions([]);
            setReferenceError(error instanceof Error ? error.message : 'Unable to load material reference data.');
        } finally {
            setReferenceLoading(false);
        }
    };
    const openPurchase = () => {
        setManualVendor(false);
        purchase.setData('purchase_date', todayInIndia());
        setPurchaseOpen(true);
        void loadReferenceData();
    };
    const openConsumption = () => {
        const today = todayInIndia();
        setEditingConsumption(null);
        consumption.reset();
        consumption.setData({
            ...consumptionBlank,
            consumption_date: today,
            day_count: String(remainingDaysInSelectedMonth(today)),
        });
        setConsumptionOpen(true);
        void loadReferenceData();
    };
    const editConsumption = (item: Consumption) => {
        setEditingConsumption(item);
        consumption.setData({
            company_id: String(item.company_id),
            consumption_date: item.consumption_date,
            period_type: item.period_type,
            day_count: String(item.day_count ?? ''),
            material: item.material,
            common_remarks: item.remarks ?? '',
            entries: [
                {
                    plant_id: String(item.plant_id),
                    quantity_mt: String(item.quantity_mt),
                    remarks: item.remarks ?? '',
                },
            ],
        });
        setConsumptionOpen(true);
        void loadReferenceData();
    };
    const submitPurchase: FormEventHandler = (e) => {
        e.preventDefault();
        purchase.post('/admin/procurement/purchases', {
            preserveScroll: true,
            onSuccess: () => {
                setPurchaseOpen(false);
                setManualVendor(false);
                purchase.reset();
            },
        });
    };
    const submitConsumption: FormEventHandler = (e) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setConsumptionOpen(false);
                setEditingConsumption(null);
                consumption.reset();
            },
        };
        if (editingConsumption) {
            consumption.put(`/admin/procurement/consumptions/${editingConsumption.id}`, options);
        } else {
            consumption.post('/admin/procurement/consumptions', options);
        }
    };
    const runConsumptionBulkAction = () => {
        if (bulkAction !== 'delete' || selectedConsumptionIds.length === 0) return;
        if (!window.confirm(`Move ${selectedConsumptionIds.length} selected consumption record(s) to inactive status?`)) return;
        router.patch(
            '/admin/procurement/consumptions/bulk',
            { ids: selectedConsumptionIds, action: bulkAction },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedConsumptionIds([]);
                    setBulkAction('');
                },
            },
        );
    };
    const submitSearch: FormEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        setConsumptionPage(1);
        router.get(
            tab === 'consumptions' ? '/admin/procurement/daily-consumption' : '/admin/procurement',
            { search: search.trim(), month: filters.month },
            { preserveState: true, replace: true },
        );
    };
    const clearSearch = () => {
        setSearch('');
        setConsumptionPage(1);
        router.get(
            tab === 'consumptions' ? '/admin/procurement/daily-consumption' : '/admin/procurement',
            { month: filters.month },
            { preserveState: true, replace: true },
        );
    };
    const selectedPurchaseCompany = companies.find((company) => String(company.id) === purchase.data.company_id);
    const purchaseMaterials = materials;
    const selectedPurchasePlant = companies.find((company) => String(company.id) === purchase.data.plant_id);
    const purchasePlants = companies.filter(
        (company) => company.record_type === 2 && company.parent_company_id === selectedPurchaseCompany?.company_code,
    );
    useEffect(() => {
        const plantCode = selectedPurchasePlant?.company_code;
        const material = purchase.data.material;
        setPurchaseVendors([]);

        if (!plantCode || !material) {
            setVendorLoading(false);
            return;
        }

        const controller = new AbortController();
        const query = new URLSearchParams({ plant_code: plantCode, material });
        setVendorLoading(true);
        void fetch(`/admin/procurement/vendors?${query.toString()}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) throw new Error('Unable to load vendors.');
                return response.json() as Promise<{ vendors: string[] }>;
            })
            .then((data) => setPurchaseVendors(data.vendors ?? []))
            .catch((error: unknown) => {
                if (!(error instanceof DOMException && error.name === 'AbortError')) setPurchaseVendors([]);
            })
            .finally(() => {
                if (!controller.signal.aborted) setVendorLoading(false);
            });

        return () => controller.abort();
    }, [purchase.data.material, selectedPurchasePlant?.company_code]);
    const selectedCompany = companies.find((company) => String(company.id) === consumption.data.company_id);
    const consumptionMonth = (consumption.data.consumption_date || todayInIndia()).slice(0, 7);
    const consumptionMaterials = materials.filter((material) =>
        manualPlanningDecisions.some(
            (decision) =>
                String(decision.company_id) === consumption.data.company_id &&
                decision.material === material.matnr &&
                decision.planning_month.slice(0, 7) === consumptionMonth,
        ),
    );
    const consumptionError = Object.values(consumption.errors)[0];
    const consumptionPlants = companies.filter(
        (company) =>
            company.record_type !== 1 &&
            !['head office', 'other plant'].includes(company.company_name.trim().toLowerCase()) &&
            company.parent_company_id === selectedCompany?.company_code &&
            manualPlanningDecisions.some(
                (decision) =>
                    decision.company_id === selectedCompany?.id &&
                    decision.plant_id === company.id &&
                    decision.material === consumption.data.material &&
                    decision.planning_month.slice(0, 7) === consumptionMonth,
            ) &&
            (!editingConsumption || company.id === editingConsumption.plant_id),
    );
    const manualConsumptions = consumptions.filter((item) => item.planning_mode === 'manual');
    const allConsumptionsSelected = manualConsumptions.length > 0 && manualConsumptions.every((item) => selectedConsumptionIds.includes(item.id));
    const sortedConsumptions = useMemo(() => {
        return [...consumptions].sort((left, right) => {
            const a = left[consumptionSort] ?? '';
            const b = right[consumptionSort] ?? '';
            const comparison =
                typeof a === 'number' && typeof b === 'number' ? a - b : String(a).localeCompare(String(b), undefined, { numeric: true });
            return consumptionSortDirection === 'asc' ? comparison : -comparison;
        });
    }, [consumptions, consumptionSort, consumptionSortDirection]);
    const consumptionPageCount = Math.max(1, Math.ceil(sortedConsumptions.length / consumptionPageSize));
    const safeConsumptionPage = Math.min(consumptionPage, consumptionPageCount);
    const consumptionStart = (safeConsumptionPage - 1) * consumptionPageSize;
    const paginatedConsumptions = sortedConsumptions.slice(consumptionStart, consumptionStart + consumptionPageSize);
    const sortConsumptionsBy = (key: keyof Consumption) => {
        setConsumptionPage(1);
        if (consumptionSort === key) setConsumptionSortDirection(consumptionSortDirection === 'asc' ? 'desc' : 'asc');
        else {
            setConsumptionSort(key);
            setConsumptionSortDirection('asc');
        }
    };
    const setPlantEntry = (plantId: number, selected: boolean) =>
        consumption.setData(
            'entries',
            selected
                ? [...consumption.data.entries, { plant_id: String(plantId), quantity_mt: '', remarks: '' }]
                : consumption.data.entries.filter((entry) => entry.plant_id !== String(plantId)),
        );
    const updatePlantEntry = (plantId: number, field: 'quantity_mt' | 'remarks', value: string) =>
        consumption.setData(
            'entries',
            consumption.data.entries.map((entry) => (entry.plant_id === String(plantId) ? { ...entry, [field]: value } : entry)),
        );
    const SortHeading = ({ column, children, align = 'left' }: { column: keyof Consumption; children: ReactNode; align?: 'left' | 'right' }) => (
        <th className={`bg-slate-100/95 px-3 py-3.5 backdrop-blur ${align === 'right' ? 'text-right' : ''}`}>
            <button
                type="button"
                className={`inline-flex w-full items-center gap-1 hover:text-[#ff8200] ${align === 'right' ? 'justify-end' : ''}`}
                onClick={() => sortConsumptionsBy(column)}
            >
                {children}
                <span className={consumptionSort === column ? 'text-[#ff8200]' : 'text-slate-300'}>
                    {consumptionSort === column ? (consumptionSortDirection === 'asc' ? '▲' : '▼') : '↕'}
                </span>
            </button>
        </th>
    );
    return (
        <AppLayout breadcrumbs={[{ title: 'Procurement', href: '/admin/procurement' }]}>
            <Head title="Procurement" />
            <div className="procurement-compact mx-auto w-full max-w-[1680px] space-y-3 p-3 md:p-4">
                <ProcurementPageHeader
                    title="Procurement"
                    subtitle="Purchase planning, material demand and delivery tracking"
                    icon={ShoppingCart}
                    actions={
                        <div className="flex flex-wrap justify-end gap-2">
                            {consumptionMode === 'manual' && canManageConsumptions && (
                                <Button
                                    type="button"
                                    onClick={openConsumption}
                                    aria-label="Add consumption"
                                    className="group h-10 gap-2 rounded-xl border border-white/80 bg-white px-2.5 text-xs font-semibold text-cyan-800 shadow-sm transition-all hover:-translate-y-0.5 hover:border-cyan-200 hover:bg-cyan-50 hover:text-cyan-900 hover:shadow-lg sm:px-3.5 sm:text-sm"
                                >
                                    <span className="grid size-6 place-items-center rounded-md bg-cyan-50 text-cyan-700 transition-colors group-hover:bg-cyan-100">
                                        <CalendarDays className="size-3.5" />
                                    </span>
                                    <span className="hidden sm:inline">Add consumption</span>
                                </Button>
                            )}
                            {canManagePurchases && (
                                <Button
                                    type="button"
                                    onClick={openPurchase}
                                    aria-label="Add purchase"
                                    className="group h-10 gap-2 rounded-xl border border-white/80 bg-white px-2.5 text-xs font-semibold text-cyan-800 shadow-sm transition-all hover:-translate-y-0.5 hover:border-cyan-200 hover:bg-cyan-50 hover:text-cyan-900 hover:shadow-lg sm:px-3.5 sm:text-sm"
                                >
                                    <span className="grid size-6 place-items-center rounded-md bg-cyan-50 text-cyan-700 transition-colors group-hover:bg-cyan-100">
                                        <Plus className="size-3.5" />
                                    </span>
                                    <span className="hidden sm:inline">Add purchase</span>
                                </Button>
                            )}
                        </div>
                    }
                />
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        [PackageCheck, 'Purchase plans', stats.purchases],
                        [TrendingUp, 'Purchase quantity', `${Number(stats.purchase_mt).toLocaleString()} MT`],
                        [CalendarDays, 'Planned consumption', `${Number(stats.planned_mt).toLocaleString()} MT`],
                        [ShoppingCart, 'Open orders', stats.open_orders],
                    ].map(([Icon, label, value]) => (
                        <div key={String(label)} className="rounded-xl border bg-white p-3 shadow-sm">
                            <div className="flex items-center gap-3">
                                <span className="grid size-9 place-items-center rounded-lg bg-amber-50 text-amber-600">
                                    <Icon className="size-4" />
                                </span>
                                <div>
                                    <p className="text-xs text-slate-500">{label}</p>
                                    <b className="text-lg">{value}</b>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
                <section className="overflow-hidden rounded-2xl border bg-white shadow-sm">
                    <div className="flex flex-col gap-2 border-b bg-slate-50/60 px-4 py-2.5 md:flex-row md:items-center md:justify-between">
                        <div className="flex flex-wrap items-center gap-2">
                            {tab === 'consumptions' && consumptionMode === 'manual' && canManageConsumptions && (
                                <>
                                    <Select value={bulkAction} onValueChange={setBulkAction}>
                                        <SelectTrigger className="h-10 w-44 bg-white text-base">
                                            <SelectValue placeholder="Bulk action" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="delete">Move to inactive</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        size="sm"
                                        className="bg-brand-teal hover:bg-brand-teal/90 h-10 px-4 text-base"
                                        disabled={!bulkAction || selectedConsumptionIds.length === 0}
                                        onClick={runConsumptionBulkAction}
                                    >
                                        Apply ({selectedConsumptionIds.length})
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="h-10 border-red-200 bg-white px-4 text-base text-red-600 hover:bg-red-50 hover:text-red-700"
                                        onClick={() =>
                                            window.confirm(
                                                'Permanently delete all procurement data, including consumption plans, purchases, MOU plans, history, balance results, planning decisions, imported stock, and imported vendor purchase orders? Company, plant, material, user, and integration settings will remain. This cannot be undone.',
                                            ) && router.patch('/admin/procurement/consumptions/empty', {}, { preserveScroll: true })
                                        }
                                    >
                                        <Database className="size-4" /> Empty database
                                    </Button>
                                </>
                            )}
                        </div>
                        <form onSubmit={submitSearch} className="flex w-full items-center gap-2 md:w-auto">
                            <div className="relative min-w-0 flex-1 md:w-80">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                <Input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Search material, plant or company"
                                    aria-label="Search consumption records"
                                    className="h-10 bg-white pr-3 pl-9"
                                />
                            </div>
                            <Button type="submit" variant="outline" className="h-10 bg-white px-4">
                                Search
                            </Button>
                            {(filters.search || search) && (
                                <Button type="button" variant="ghost" className="h-10 px-3" onClick={clearSearch}>
                                    Clear
                                </Button>
                            )}
                        </form>
                    </div>
                    <div className="overflow-x-auto">
                        {tab === 'purchases' ? (
                            <table className="w-full min-w-[1000px] text-sm">
                                <thead className="bg-amber-50/60 text-left text-[10px] uppercase">
                                    <tr>
                                        <th className="px-4 py-3">Material</th>
                                        <th className="px-4 py-3">Vendor</th>
                                        <th className="px-4 py-3">Company / Plant</th>
                                        <th className="px-4 py-3">Purchase / ETA</th>
                                        <th className="px-4 py-3 text-right">Quantity MT</th>
                                        <th className="px-4 py-3">Delivery</th>
                                        <th className="px-4 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {purchases.map((p) => (
                                        <tr key={p.id}>
                                            <td className="px-4 py-3 font-mono font-semibold">{p.material}</td>
                                            <td className="px-4 py-3">
                                                <b>{p.vendor_name}</b>
                                                <div className="text-xs text-slate-500">{p.vendor_source}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {p.company_name || '—'}
                                                <div className="text-xs text-slate-500">{p.plant_name || '—'}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {p.purchase_date}
                                                <div className="text-xs text-slate-500">ETA {p.expected_delivery_date}</div>
                                            </td>
                                            <td className="px-4 py-3 text-right font-semibold">{Number(p.quantity_mt).toLocaleString()}</td>
                                            <td className="px-4 py-3">
                                                <Select
                                                    value={p.delivery_status}
                                                    disabled={!canManagePurchases}
                                                    onValueChange={(value) =>
                                                        router.patch(
                                                            `/admin/procurement/purchases/${p.id}/delivery-status`,
                                                            { delivery_status: value },
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger className="h-8 w-44">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {['planned', 'in_transit', 'partially_received', 'received', 'cancelled'].map((v) => (
                                                            <SelectItem key={v} value={v}>
                                                                {v.replaceAll('_', ' ')}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {canManagePurchases && (
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        className="text-red-500"
                                                        onClick={() =>
                                                            confirm('Delete this purchase plan?') &&
                                                            router.delete(`/admin/procurement/purchases/${p.id}`)
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        ) : (
                            <table className="w-full min-w-[1250px] border-separate border-spacing-0 text-sm">
                                <thead className="sticky top-0 z-10 text-left text-[11px] font-bold tracking-[0.06em] text-slate-600 uppercase shadow-[0_1px_0_0_#cbd5e1]">
                                    <tr>
                                        <th className="w-12 bg-slate-100/95 px-3 py-3.5 backdrop-blur"></th>
                                        <th className="w-12 bg-slate-100/95 px-3 py-3.5 backdrop-blur">
                                            {consumptionMode === 'manual' && (
                                                <Checkbox
                                                    aria-label="Select all consumption records"
                                                    checked={allConsumptionsSelected}
                                                    onCheckedChange={(checked) =>
                                                        setSelectedConsumptionIds(checked ? manualConsumptions.map((item) => item.id) : [])
                                                    }
                                                />
                                            )}
                                        </th>
                                        <th className="w-16 bg-slate-100/95 px-3 py-3.5 text-center backdrop-blur">S.No.</th>
                                        <SortHeading column="material">MATNR</SortHeading>
                                        <SortHeading column="plant_name">Plant Name</SortHeading>
                                        <SortHeading column="material_desc">Material Description</SortHeading>
                                        <SortHeading column="meins">MEINS</SortHeading>
                                        <SortHeading column="opening_stock_mt" align="right">
                                            Current stock (MT)
                                        </SortHeading>
                                        <SortHeading column="stock_value" align="right">
                                            Stock Value
                                        </SortHeading>
                                        <SortHeading column="planned_po_mt" align="right">
                                            Planned PO (MT)
                                        </SortHeading>
                                        <SortHeading column="quantity_mt" align="right">
                                            Daily consumption (MT/day)
                                        </SortHeading>
                                        <SortHeading column="planning_mode">Planning Mode</SortHeading>
                                    </tr>
                                </thead>
                                <tbody className="text-[13px] text-slate-700">
                                    {paginatedConsumptions.map((c, index) => {
                                        const expanded = expandedConsumptionIds.includes(c.id);
                                        return (
                                            <Fragment key={c.id}>
                                                <tr
                                                    className={`group border-b border-slate-200 transition-colors ${expanded ? 'bg-cyan-50/70' : index % 2 === 0 ? 'bg-white' : 'bg-slate-50/45'} hover:bg-cyan-50/60`}
                                                >
                                                    <td className="border-b border-slate-200 px-3 py-3.5">
                                                        <RowExpandButton
                                                            expanded={expanded}
                                                            label={expanded ? 'Collapse calculation details' : 'Expand calculation details'}
                                                            onClick={() =>
                                                                setExpandedConsumptionIds(
                                                                    expanded
                                                                        ? expandedConsumptionIds.filter((id) => id !== c.id)
                                                                        : [...expandedConsumptionIds, c.id],
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                    <td className="border-b border-slate-200 px-3 py-3.5">
                                                        {c.planning_mode === 'manual' && (
                                                            <Checkbox
                                                                aria-label={`Select consumption ${index + 1}`}
                                                                checked={selectedConsumptionIds.includes(c.id)}
                                                                onCheckedChange={(checked) =>
                                                                    setSelectedConsumptionIds(
                                                                        checked
                                                                            ? [...new Set([...selectedConsumptionIds, c.id])]
                                                                            : selectedConsumptionIds.filter((id) => id !== c.id),
                                                                    )
                                                                }
                                                            />
                                                        )}
                                                    </td>
                                                    <td className="border-b border-slate-200 px-3 py-3.5 text-center font-medium text-slate-400">
                                                        {consumptionStart + index + 1}
                                                    </td>
                                                    <td className="border-b border-slate-200 px-3 py-3.5">
                                                        <span className="inline-flex rounded-md bg-slate-100 px-2 py-1 font-mono text-xs font-bold text-slate-800 ring-1 ring-slate-200">
                                                            {c.material}
                                                        </span>
                                                    </td>
                                                    <td className="border-b border-slate-200 px-3 py-3.5 font-semibold text-slate-800">
                                                        {c.plant_name || '—'}
                                                    </td>
                                                    <td className="border-b border-slate-200 px-3 py-3.5">
                                                        <span className="font-semibold text-slate-800">{c.material_desc || '—'}</span>
                                                    </td>
                                                    <td className="border-b border-slate-200 px-3 py-3.5 text-center font-semibold text-slate-700">
                                                        {c.meins || '—'}
                                                    </td>
                                                    <td className="border-b border-slate-200 px-3 py-3.5 text-right font-medium tabular-nums">
                                                        {formatMt(c.opening_stock_mt)}
                                                    </td>
                                                    <td className="border-b border-slate-200 px-3 py-3.5 text-right font-semibold tabular-nums">
                                                        {formatMoney(c.stock_value)}
                                                    </td>
                                                    <td className="border-b border-slate-200 px-3 py-3.5 text-right font-medium tabular-nums">
                                                        {formatMt(c.planned_po_mt)}
                                                    </td>
                                                    <td className="border-b border-slate-200 px-3 py-3.5 text-right font-medium tabular-nums">
                                                        {formatMt(Number(c.quantity_mt))}
                                                    </td>
                                                    <td className="border-b border-slate-200 px-3 py-3.5">
                                                        <span
                                                            className={`inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold tracking-wide uppercase ring-1 ${c.planning_mode === 'auto' ? 'bg-cyan-50 text-cyan-700 ring-cyan-100' : 'bg-amber-50 text-amber-700 ring-amber-200'}`}
                                                        >
                                                            {c.planning_mode === 'auto' ? 'SAP Auto' : 'Manual'}
                                                        </span>
                                                    </td>
                                                </tr>
                                                {expanded && (
                                                    <tr className="bg-cyan-50/30">
                                                        <td colSpan={12} className="border-b border-slate-200 px-6 py-4">
                                                            <div className="grid gap-x-12 gap-y-0 rounded-xl border border-slate-200 bg-white px-8 py-3 shadow-inner md:grid-cols-2">
                                                                {[
                                                                    ['Days in plan', String(c.day_count ?? 1), ''],
                                                                    ['Total required (MT)', formatMt(c.total_required_mt), ''],
                                                                    [
                                                                        'Gross shortage (MT)',
                                                                        formatMt(c.gross_shortage_mt),
                                                                        c.gross_shortage_mt > 0 ? 'font-bold text-red-600' : 'text-emerald-700',
                                                                    ],
                                                                    [
                                                                        'Projected closing (MT)',
                                                                        formatMt(c.projected_closing_mt),
                                                                        c.projected_closing_mt < 0 ? 'text-red-600' : 'text-emerald-700',
                                                                    ],
                                                                    ['Cover days', c.cover_days === null ? '—' : formatMt(c.cover_days, 1), ''],
                                                                    [
                                                                        'Remaining shortage (MT)',
                                                                        formatMt(c.remaining_shortage_mt),
                                                                        c.remaining_shortage_mt > 0 ? 'text-red-600' : 'text-emerald-700',
                                                                    ],
                                                                    ['Remarks', c.remarks || '—', ''],
                                                                    ['Version', '1', ''],
                                                                ].map(([label, value, color]) => (
                                                                    <div
                                                                        key={label}
                                                                        className="grid grid-cols-[minmax(170px,1fr)_minmax(120px,1.3fr)] items-center border-b border-slate-200 py-2.5 last:border-b-0"
                                                                    >
                                                                        <span className="text-xs font-bold tracking-wide text-slate-600 uppercase">
                                                                            {label}
                                                                        </span>
                                                                        <span className={`text-base font-semibold ${color}`}>{value}</span>
                                                                    </div>
                                                                ))}
                                                                <div className="grid grid-cols-[minmax(170px,1fr)_minmax(120px,1.3fr)] items-center py-2.5 md:col-start-2">
                                                                    <span className="text-xs font-bold tracking-wide text-slate-600 uppercase">
                                                                        Action
                                                                    </span>
                                                                    <div className="flex gap-1">
                                                                        <Button
                                                                            size="sm"
                                                                            variant="outline"
                                                                            aria-label={`View consumption ${index + 1}`}
                                                                            title="View"
                                                                            className="gap-1.5 text-slate-700 hover:bg-cyan-50 hover:text-cyan-800"
                                                                            onClick={() => {
                                                                                if (c.planning_mode === 'auto') {
                                                                                    const params = new URLSearchParams({
                                                                                        company_id: String(c.company_id),
                                                                                        plant_id: String(c.plant_id),
                                                                                        material: c.material,
                                                                                        month: c.consumption_date.slice(0, 7),
                                                                                    });
                                                                                    router.get(
                                                                                        `/admin/procurement/consumptions/auto/supply-plan?${params.toString()}`,
                                                                                    );
                                                                                    return;
                                                                                }

                                                                                router.get(`/admin/procurement/consumptions/${c.id}/supply-plan`);
                                                                            }}
                                                                        >
                                                                            <Eye className="size-4" />
                                                                            View
                                                                        </Button>
                                                                        {canManageConsumptions && c.planning_mode === 'manual' && (
                                                                            <>
                                                                                <Button
                                                                                    size="icon"
                                                                                    variant="ghost"
                                                                                    aria-label={`Edit consumption ${index + 1}`}
                                                                                    title="Edit"
                                                                                    className="text-brand-teal hover:bg-cyan-50"
                                                                                    onClick={() => editConsumption(c)}
                                                                                >
                                                                                    <Edit3 className="size-4" />
                                                                                </Button>
                                                                                <Button
                                                                                    size="icon"
                                                                                    variant="ghost"
                                                                                    aria-label={`Move consumption ${index + 1} to inactive status`}
                                                                                    title="Move to inactive"
                                                                                    className="text-red-500 hover:bg-red-50 hover:text-red-600"
                                                                                    onClick={() =>
                                                                                        confirm('Move this consumption plan to inactive status?') &&
                                                                                        router.delete(`/admin/procurement/consumptions/${c.id}`)
                                                                                    }
                                                                                >
                                                                                    <Trash2 className="size-4" />
                                                                                </Button>
                                                                            </>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                )}
                                            </Fragment>
                                        );
                                    })}
                                    {consumptions.length === 0 && (
                                        <tr>
                                            <td colSpan={11} className="px-6 py-14 text-center text-sm text-slate-500">
                                                No active consumption records found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        )}
                    </div>
                    {tab === 'consumptions' && consumptions.length > 0 && (
                        <div className="flex flex-col gap-3 border-t border-slate-200 bg-white px-5 py-3.5 text-xs text-slate-600 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2">
                                <span className="font-medium text-slate-500">Rows per page</span>
                                <Select
                                    value={String(consumptionPageSize)}
                                    onValueChange={(value) => {
                                        setConsumptionPageSize(Number(value));
                                        setConsumptionPage(1);
                                    }}
                                >
                                    <SelectTrigger className="h-8 w-20 border-slate-200 bg-slate-50 font-semibold shadow-none">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {datatable.pageSizeOptions.map((size) => (
                                            <SelectItem key={size} value={String(size)}>
                                                {size}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <span className="text-slate-500">
                                    Showing{' '}
                                    <b className="text-slate-700">
                                        {consumptionStart + 1}–{Math.min(consumptionStart + consumptionPageSize, sortedConsumptions.length)} of{' '}
                                        {sortedConsumptions.length}
                                    </b>
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={safeConsumptionPage === 1}
                                    onClick={() => setConsumptionPage(safeConsumptionPage - 1)}
                                >
                                    Previous
                                </Button>
                                <b className="min-w-20 text-center text-slate-700">
                                    Page {safeConsumptionPage} of {consumptionPageCount}
                                </b>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={safeConsumptionPage === consumptionPageCount}
                                    onClick={() => setConsumptionPage(safeConsumptionPage + 1)}
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    )}
                </section>
            </div>
            <Dialog open={viewingConsumption !== null} onOpenChange={(open) => !open && setViewingConsumption(null)}>
                <DialogContent className="max-w-xl overflow-hidden p-0 sm:p-0">
                    <MasterDialogHeader title="Consumption details" />
                    {viewingConsumption && (
                        <div className="grid gap-4 p-6 sm:grid-cols-2">
                            {[
                                ['Material', viewingConsumption.material],
                                ['Company', viewingConsumption.company_name || '—'],
                                ['Plant', viewingConsumption.plant_name || '—'],
                                ['Consumption date', viewingConsumption.consumption_date],
                                ['Period', viewingConsumption.period_type],
                                ['Day count', viewingConsumption.day_count ?? '—'],
                                ['Consumption', `${Number(viewingConsumption.quantity_mt).toLocaleString()} MT`],
                                ['Buying', `${Number(viewingConsumption.total_buying_mt).toLocaleString()} MT`],
                            ].map(([label, value]) => (
                                <div key={String(label)} className="rounded-lg border bg-slate-50 px-4 py-3">
                                    <p className="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">{label}</p>
                                    <p className="mt-1 font-medium text-slate-900">{value}</p>
                                </div>
                            ))}
                            <div className="rounded-lg border bg-slate-50 px-4 py-3 sm:col-span-2">
                                <p className="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Remarks</p>
                                <p className="mt-1 text-sm whitespace-pre-wrap text-slate-700">{viewingConsumption.remarks || '—'}</p>
                            </div>
                        </div>
                    )}
                    <DialogFooter className="border-t bg-slate-50 px-6 py-4">
                        {canManageConsumptions && viewingConsumption && (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    const item = viewingConsumption;
                                    setViewingConsumption(null);
                                    editConsumption(item);
                                }}
                            >
                                <Edit3 className="size-4" /> Edit
                            </Button>
                        )}
                        <Button className="bg-brand-teal" onClick={() => setViewingConsumption(null)}>
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <Dialog open={purchaseOpen} onOpenChange={setPurchaseOpen}>
                <DialogContent className="h-dvh max-h-dvh w-full max-w-none gap-0 overflow-hidden rounded-none border-0 p-0 sm:h-auto sm:max-h-[90dvh] sm:w-[calc(100vw-2rem)] sm:max-w-[1100px] sm:rounded-xl sm:border sm:p-0 [&>button]:top-4 [&>button]:right-4 [&>button]:z-10 [&>button]:rounded-full [&>button]:p-1.5 [&>button]:text-white/70 [&>button]:hover:bg-white/10 [&>button]:hover:text-white sm:[&>button]:top-5 sm:[&>button]:right-5">
                    <form className="grid h-full min-h-0 grid-rows-[auto_minmax(0,1fr)_auto] sm:h-auto sm:max-h-[90dvh]" onSubmit={submitPurchase}>
                        <MasterDialogHeader title="Create purchase plan" />
                        <div className="grid min-h-0 content-start gap-3 overflow-y-auto overscroll-contain bg-slate-50 p-3 sm:grid-cols-2 sm:overflow-visible sm:p-5 [&>label:nth-child(-n+3)>span:first-child]:sr-only [&>label>button]:h-11 [&>label>div>button]:h-11">
                            <FieldSelect
                                label="Company"
                                value={purchase.data.company_id}
                                options={companies.filter((company) => company.record_type === 1)}
                                onChange={(v) => {
                                    purchase.setData('company_id', v);
                                    purchase.setData('plant_id', '');
                                    purchase.setData('material', '');
                                    purchase.setData('vendor_name', '');
                                    purchase.setData('vendor_source', 'sap');
                                    setManualVendor(false);
                                }}
                            />
                            <FieldSelect
                                label="Plant"
                                value={purchase.data.plant_id}
                                options={purchasePlants}
                                onChange={(v) => {
                                    purchase.setData('plant_id', v);
                                    purchase.setData('vendor_name', '');
                                    purchase.setData('vendor_source', 'sap');
                                    setManualVendor(false);
                                }}
                            />
                            <MaterialSelect
                                value={purchase.data.material}
                                materials={purchaseMaterials}
                                onChange={(v) => {
                                    purchase.setData('material', v);
                                    purchase.setData('vendor_name', '');
                                    purchase.setData('vendor_source', 'sap');
                                    setManualVendor(false);
                                }}
                                loading={referenceLoading}
                            />
                            <div className="min-w-0">
                                <VendorSelect
                                    value={manualVendor ? 'Local / other vendor' : purchase.data.vendor_name}
                                    vendors={purchaseVendors}
                                    onChange={(value) => {
                                        setManualVendor(false);
                                        purchase.setData('vendor_name', value);
                                        purchase.setData('vendor_source', 'sap');
                                    }}
                                    onOther={() => {
                                        setManualVendor(true);
                                        purchase.setData('vendor_name', '');
                                        purchase.setData('vendor_source', 'local');
                                    }}
                                    loading={vendorLoading}
                                />
                                {!manualVendor && <InputError message={purchase.errors.vendor_name} />}
                            </div>
                            {manualVendor && (
                                <div className="rounded-lg border border-sky-200 bg-sky-50/60 p-3 sm:col-span-2">
                                    <label className="relative block">
                                        <span className="pointer-events-none absolute top-1.5 left-3 z-10 text-[10px] font-medium tracking-wide text-slate-500 uppercase">
                                            Vendor name *
                                        </span>
                                        <Input
                                            autoFocus
                                            className="h-11 bg-white pt-4"
                                            placeholder="Enter vendor name"
                                            value={purchase.data.vendor_name}
                                            onChange={(event) => purchase.setData('vendor_name', event.target.value)}
                                        />
                                    </label>
                                    <p className="mt-1.5 px-1 text-xs text-slate-500">
                                        Use this when the vendor is not available in the mapped list.
                                    </p>
                                    <InputError message={purchase.errors.vendor_name} />
                                </div>
                            )}
                            <Input
                                className="h-11"
                                type="number"
                                step="0.001"
                                placeholder="Quantity MT *"
                                value={purchase.data.quantity_mt}
                                onChange={(e) => purchase.setData('quantity_mt', e.target.value)}
                            />
                            <label className="relative block min-w-0">
                                <span className="pointer-events-none absolute top-1.5 left-3 z-10 text-[10px] font-medium text-slate-500">
                                    Delivery date
                                </span>
                                <Input
                                    className="h-11 pt-4"
                                    type="date"
                                    value={purchase.data.expected_delivery_date}
                                    onChange={(e) => purchase.setData('expected_delivery_date', e.target.value)}
                                />
                            </label>
                            <label className="relative block min-w-0">
                                <span className="pointer-events-none absolute top-1.5 left-3 z-10 text-[10px] font-medium text-slate-500">
                                    Order status
                                </span>
                                <Select value={purchase.data.delivery_status} onValueChange={(value) => purchase.setData('delivery_status', value)}>
                                    <SelectTrigger className="h-11 pt-4 capitalize">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {['planned', 'in_transit', 'partially_received', 'received', 'cancelled'].map((status) => (
                                            <SelectItem key={status} value={status} className="capitalize">
                                                {status.replaceAll('_', ' ')}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </label>
                            <Textarea
                                className="h-11 min-h-11 resize-none sm:col-span-2"
                                placeholder="Remarks"
                                value={purchase.data.remarks}
                                onChange={(e) => purchase.setData('remarks', e.target.value)}
                            />
                        </div>
                        <DialogFooter className="shrink-0 gap-2 border-t bg-white px-4 py-3 sm:px-6 sm:py-4">
                            <Button type="button" variant="outline" onClick={() => setPurchaseOpen(false)}>
                                Cancel
                            </Button>
                            <Button className="bg-brand-teal" disabled={purchase.processing}>
                                Save purchase
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog
                open={consumptionOpen}
                onOpenChange={(open) => {
                    setConsumptionOpen(open);
                    if (!open) setEditingConsumption(null);
                }}
            >
                <DialogContent className="h-dvh max-h-dvh w-full max-w-none gap-0 overflow-hidden rounded-none border-0 p-0 sm:h-auto sm:max-h-[calc(100dvh-1rem)] sm:w-[calc(100vw-1rem)] sm:max-w-[1400px] sm:rounded-xl sm:border sm:p-0 [&>button]:top-4 [&>button]:right-4 [&>button]:z-10 [&>button]:rounded-full [&>button]:p-1.5 [&>button]:text-white/70 [&>button]:hover:bg-white/10 [&>button]:hover:text-white sm:[&>button]:top-5 sm:[&>button]:right-5">
                    <form className="grid h-full min-h-0 grid-rows-[auto_minmax(0,1fr)_auto]" onSubmit={submitConsumption}>
                        <MasterDialogHeader title={editingConsumption ? 'Edit Daily Consumption' : 'Add Daily Consumption'} />
                        <div className="min-h-0 space-y-2 overflow-y-auto overscroll-contain bg-slate-50 p-2 sm:space-y-3 sm:p-3">
                            <div className="grid gap-2 rounded-lg border bg-white p-2.5 sm:grid-cols-2 sm:p-3 lg:grid-cols-3 [&>label:nth-child(-n+5)]:relative [&>label:nth-child(-n+5)>button]:h-11 [&>label:nth-child(-n+5)>button]:pt-3.5 [&>label:nth-child(-n+5)>div>button]:h-11 [&>label:nth-child(-n+5)>div>button]:pt-3.5 [&>label:nth-child(-n+5)>input]:h-11 [&>label:nth-child(-n+5)>input]:pt-4 [&>label:nth-child(-n+5)>span:first-child]:pointer-events-none [&>label:nth-child(-n+5)>span:first-child]:absolute [&>label:nth-child(-n+5)>span:first-child]:top-1 [&>label:nth-child(-n+5)>span:first-child]:left-3 [&>label:nth-child(-n+5)>span:first-child]:z-10 [&>label:nth-child(-n+5)>span:first-child]:text-[9px] [&>label:nth-child(-n+5)>span:first-child]:font-medium [&>label:nth-child(-n+5)>span:first-child]:text-slate-500">
                                <FieldSelect
                                    label="Company"
                                    value={consumption.data.company_id}
                                    options={companies.filter((company) => company.record_type === 1)}
                                    onChange={(v) => {
                                        consumption.setData('company_id', v);
                                        consumption.setData('material', '');
                                        consumption.setData('entries', []);
                                    }}
                                />
                                <MaterialSelect
                                    value={consumption.data.material}
                                    materials={consumptionMaterials}
                                    onChange={(v) => consumption.setData('material', v)}
                                    loading={referenceLoading}
                                />
                                {referenceError && (
                                    <p className="text-xs text-red-600 sm:col-span-2 lg:col-span-3" role="alert">
                                        {referenceError} Please refresh or contact the administrator.
                                    </p>
                                )}
                                <FormField label="Period">
                                    <Select
                                        value={consumption.data.period_type}
                                        onValueChange={(value) => {
                                            consumption.setData('period_type', value);
                                            consumption.setData(
                                                'day_count',
                                                value === 'datewise'
                                                    ? '1'
                                                    : String(remainingDaysInSelectedMonth(consumption.data.consumption_date || todayInIndia())),
                                            );
                                        }}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="monthly">Monthly</SelectItem>
                                            <SelectItem value="datewise">Date-wise</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormField>
                                <FormField label="Consumption date">
                                    <Input
                                        type="date"
                                        value={consumption.data.consumption_date}
                                        onChange={(event) => {
                                            const value = event.target.value;
                                            const monthChanged = value.slice(0, 7) !== consumption.data.consumption_date.slice(0, 7);
                                            consumption.setData('consumption_date', value);
                                            if (monthChanged) {
                                                consumption.setData('material', '');
                                                consumption.setData('entries', []);
                                            }
                                            if (value && consumption.data.period_type === 'monthly') {
                                                consumption.setData('day_count', String(remainingDaysInSelectedMonth(value)));
                                            }
                                        }}
                                    />
                                </FormField>
                                <FormField label="Day count">
                                    <Input
                                        type="number"
                                        min="1"
                                        max={
                                            consumption.data.period_type === 'monthly'
                                                ? remainingDaysInSelectedMonth(consumption.data.consumption_date)
                                                : 1
                                        }
                                        value={consumption.data.day_count}
                                        disabled
                                        className="bg-slate-100 font-semibold opacity-100"
                                    />
                                </FormField>
                                <Textarea
                                    className="min-h-14 resize-none sm:min-h-11 lg:h-11"
                                    placeholder="Common remarks for all selected plant entries (optional)"
                                    value={consumption.data.common_remarks}
                                    onChange={(e) => consumption.setData('common_remarks', e.target.value)}
                                />
                            </div>
                            {consumptionError && (
                                <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                                    <InputError message={consumptionError} />
                                </div>
                            )}
                            <div className="overflow-hidden rounded-xl border bg-white">
                                <div className="flex flex-col gap-3 border-b p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4">
                                    <div>
                                        <h3 className="font-bold">Plant-wise consumption</h3>
                                        <p className="text-xs text-slate-500">Enter daily consumption for all applicable plants.</p>
                                    </div>
                                    <label className="flex shrink-0 items-center gap-2 text-xs font-semibold">
                                        <input
                                            type="checkbox"
                                            checked={consumptionPlants.length > 0 && consumption.data.entries.length === consumptionPlants.length}
                                            onChange={(e) =>
                                                consumption.setData(
                                                    'entries',
                                                    e.target.checked
                                                        ? consumptionPlants.map((plant) => ({
                                                              plant_id: String(plant.id),
                                                              quantity_mt: '',
                                                              remarks: '',
                                                          }))
                                                        : [],
                                                )
                                            }
                                        />{' '}
                                        Select all plants
                                    </label>
                                </div>
                                <div className="max-h-[clamp(260px,42dvh,410px)] overflow-auto overscroll-contain">
                                    <table className="w-full min-w-[900px] text-sm">
                                        <thead className="sticky top-0 bg-slate-100 text-left text-xs">
                                            <tr>
                                                <th className="p-3">Select</th>
                                                <th className="p-3">Plant</th>
                                                <th className="p-3">Current stock</th>
                                                <th className="p-3">Daily consumption (MT/day)</th>
                                                <th className="p-3">Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {consumptionPlants.map((plant) => {
                                                const entry = consumption.data.entries.find((item) => item.plant_id === String(plant.id));
                                                const stock = materialStocks.find(
                                                    (item) => item.matnr === consumption.data.material && item.plant_code === plant.company_code,
                                                );
                                                return (
                                                    <tr key={plant.id}>
                                                        <td className="p-3">
                                                            <input
                                                                type="checkbox"
                                                                checked={!!entry}
                                                                onChange={(e) => setPlantEntry(plant.id, e.target.checked)}
                                                            />
                                                        </td>
                                                        <td className="p-3">
                                                            <b>
                                                                {plant.company_code} - {plant.company_name}
                                                            </b>
                                                            <div className="text-xs text-slate-500">Applicable plant</div>
                                                        </td>
                                                        <td className="p-3">
                                                            <span className="text-xs text-slate-500">Current stock</span>
                                                            <div className="font-bold">{Number(stock?.current_stock || 0).toFixed(3)} MT</div>
                                                        </td>
                                                        <td className="p-3">
                                                            <Input
                                                                disabled={!entry}
                                                                type="number"
                                                                min="0"
                                                                step="0.001"
                                                                placeholder="Enter consumption"
                                                                value={entry?.quantity_mt || ''}
                                                                onChange={(e) => updatePlantEntry(plant.id, 'quantity_mt', e.target.value)}
                                                            />
                                                        </td>
                                                        <td className="p-3">
                                                            <Input
                                                                disabled={!entry}
                                                                placeholder="Optional remarks"
                                                                value={entry?.remarks || ''}
                                                                onChange={(e) => updatePlantEntry(plant.id, 'remarks', e.target.value)}
                                                            />
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                    {consumptionPlants.length === 0 && (
                                        <div className="p-8 text-center text-sm text-slate-500">Select a company to load its plants.</div>
                                    )}
                                </div>
                            </div>
                        </div>
                        <DialogFooter className="shrink-0 gap-2 border-t bg-slate-50 px-3 py-3 sm:px-6 sm:py-4">
                            <Button type="button" variant="outline" onClick={() => setConsumptionOpen(false)}>
                                Cancel
                            </Button>
                            <Button className="bg-brand-teal" disabled={consumption.processing}>
                                {editingConsumption ? 'Update consumption' : 'Save all plant entries'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
function FieldSelect({ label, value, options, onChange }: { label: string; value: string; options: Company[]; onChange: (v: string) => void }) {
    return (
        <FormField label={label}>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger>
                    <SelectValue placeholder={`Select ${label.toLowerCase()}`} />
                </SelectTrigger>
                <SelectContent>
                    {options.map((o) => (
                        <SelectItem key={o.id} value={String(o.id)}>
                            {o.company_name} ({o.company_code})
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </FormField>
    );
}
function FormField({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="grid min-w-0 gap-1.5 text-xs font-semibold text-slate-600">
            <span>{label}</span>
            {children}
        </label>
    );
}
function MaterialSelect({
    value,
    materials,
    onChange,
    loading = false,
}: {
    value: string;
    materials: Material[];
    onChange: (v: string) => void;
    loading?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const selected = materials.find((material) => material.matnr === value);
    const matches = materials
        .filter((material) => `${material.matnr} ${material.maktx}`.toLowerCase().includes(query.trim().toLowerCase()))
        .slice(0, 100);

    return (
        <FormField label="Material code">
            <div className="relative min-w-0">
                <button
                    type="button"
                    onClick={() => setOpen((current) => !current)}
                    className="border-input focus-visible:ring-ring flex h-9 w-full items-center justify-between gap-2 rounded-md border bg-transparent px-3 text-left text-sm shadow-xs outline-none focus-visible:ring-1"
                >
                    <span className={`truncate ${selected ? '' : 'text-muted-foreground'}`}>
                        {selected
                            ? `${selected.matnr} · ${selected.maktx} · ${selected.mtart}`
                            : loading
                              ? 'Loading materials...'
                              : 'Select material'}
                    </span>
                    <ChevronDown className="size-4 shrink-0 opacity-50" />
                </button>
                {open && (
                    <div className="bg-popover text-popover-foreground absolute left-0 z-[60] mt-1 w-full min-w-0 overflow-hidden rounded-md border shadow-lg sm:min-w-[360px] xl:right-0 xl:left-auto">
                        <div className="relative border-b p-2">
                            <Search className="absolute top-4 left-4 size-4 text-slate-400" />
                            <Input
                                autoFocus
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                onKeyDown={(event) => {
                                    if (event.key === 'Escape') setOpen(false);
                                }}
                                className="pl-9"
                                placeholder="Search material code or description..."
                            />
                        </div>
                        <div className="max-h-64 overflow-y-auto p-1">
                            {matches.length === 0 ? (
                                <p className="p-4 text-center text-xs text-slate-500">No material found.</p>
                            ) : (
                                matches.map((material) => (
                                    <button
                                        type="button"
                                        key={material.matnr}
                                        onClick={() => {
                                            onChange(material.matnr);
                                            setOpen(false);
                                            setQuery('');
                                        }}
                                        className="flex w-full items-start gap-2 rounded-sm px-2 py-2 text-left text-sm hover:bg-slate-100"
                                    >
                                        <Check className={`mt-0.5 size-4 shrink-0 ${value === material.matnr ? 'opacity-100' : 'opacity-0'}`} />
                                        <span>
                                            <b>{material.matnr}</b>
                                            <span className="ml-2 text-slate-500">{material.maktx}</span>
                                            <span className="ml-2 rounded bg-cyan-50 px-1.5 py-0.5 text-[10px] font-semibold text-cyan-700">
                                                {material.mtart}
                                            </span>
                                        </span>
                                    </button>
                                ))
                            )}
                        </div>
                    </div>
                )}
            </div>
        </FormField>
    );
}

function VendorSelect({
    value,
    vendors,
    onChange,
    onOther,
    loading = false,
    disabled = false,
}: {
    value: string;
    vendors: string[];
    onChange: (value: string) => void;
    onOther: () => void;
    loading?: boolean;
    disabled?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const matches = vendors.filter((vendor) => vendor.toLowerCase().includes(query.trim().toLowerCase())).slice(0, 100);

    return (
        <div className="relative min-w-0">
            <button
                type="button"
                disabled={disabled}
                onClick={() => setOpen((current) => !current)}
                className="border-input focus-visible:ring-ring flex h-11 w-full items-center justify-between gap-2 rounded-md border bg-transparent px-3 text-left text-sm shadow-xs outline-none focus-visible:ring-1 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:opacity-70"
            >
                <span className={`truncate ${value ? '' : 'text-muted-foreground'}`}>
                    {value || (disabled ? 'Select plant and material first' : loading ? 'Loading vendors...' : 'Select vendor')}
                </span>
                <ChevronDown className="size-4 shrink-0 opacity-50" />
            </button>
            {open && (
                <div className="bg-popover text-popover-foreground absolute left-0 z-[60] mt-1 w-full min-w-0 overflow-hidden rounded-md border shadow-lg sm:right-0 sm:left-auto sm:min-w-[360px]">
                    <div className="relative border-b p-2">
                        <Search className="absolute top-4 left-4 size-4 text-slate-400" />
                        <Input
                            autoFocus
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Escape') setOpen(false);
                            }}
                            className="pl-9"
                            placeholder="Search vendor..."
                        />
                    </div>
                    <div className="max-h-48 overflow-y-auto overscroll-contain p-1">
                        <button
                            type="button"
                            onClick={() => {
                                onOther();
                                setOpen(false);
                                setQuery('');
                            }}
                            className="text-brand-teal flex w-full items-center gap-2 rounded-sm px-2 py-2.5 text-left text-sm font-medium hover:bg-slate-100"
                        >
                            <Plus className="size-4 shrink-0" />
                            Local / other vendor (enter name)
                        </button>
                        {matches.length === 0 ? (
                            <p className="p-4 text-center text-xs text-slate-500">No vendor found.</p>
                        ) : (
                            matches.map((vendor) => (
                                <button
                                    type="button"
                                    key={vendor}
                                    onClick={() => {
                                        onChange(vendor);
                                        setOpen(false);
                                        setQuery('');
                                    }}
                                    className="flex w-full items-center justify-between gap-3 rounded-sm px-2 py-2 text-left text-sm hover:bg-slate-100"
                                >
                                    <span className="truncate">{vendor}</span>
                                    <Check className={`size-4 shrink-0 ${value === vendor ? 'opacity-100' : 'opacity-0'}`} />
                                </button>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
