import ProcurementPageHeader from '@/components/procurement-page-header';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Boxes, ChartPie, CircleAlert, Clock3, Info, PackageCheck, Search, ShoppingCart, TrendingUp } from 'lucide-react';
import { memo, useEffect, useMemo, useState } from 'react';

type MetricRow = { label: string | null; value: number };
type MaterialDemand = { code: string; name: string | null; value: number };
type MonthlyConsumption = { month: string; value: number };
type DeliveryStatus = {
    source: 'sap' | 'local';
    id: number;
    po_number: string;
    vendor_name: string;
    label: string;
    quantity_mt: number;
    expected_delivery_date: string;
};
type Props = {
    companies: { id: number; company_code: string; company_name: string }[];
    selectedCompany: string;
    selectedSource: 'sap' | 'manual';
    materials: { matnr: string; maktx: string | null; meins: string | null }[];
    selectedMaterial: string;
    consumptionPlants: { company_code: string; company_name: string }[];
    selectedConsumptionPlant: string;
    consumptionUnit: string;
    kpis: {
        active_consumptions: number;
        monthly_demand_mt: number;
        purchase_quantity_mt: number;
        open_orders: number;
        open_order_quantity_mt: number;
        inventory_mt: number;
    };
    plantDemand: MetricRow[];
    materialDemand: MaterialDemand[];
    stockByPlant: MetricRow[];
    monthlyConsumption: MonthlyConsumption[];
    deliveryStatus: DeliveryStatus[];
};

const number = (value: number) => Number(value || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
const label = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

export default function ProcurementDashboard({
    companies,
    selectedCompany,
    selectedSource,
    materials,
    selectedMaterial,
    consumptionPlants,
    selectedConsumptionPlant,
    consumptionUnit,
    kpis,
    plantDemand,
    materialDemand,
    stockByPlant,
    monthlyConsumption,
    deliveryStatus,
}: Props) {
    const selectedMaterialLabel = useMemo(() => {
        const material = materials.find((item) => item.matnr === selectedMaterial);
        return material ? `${material.matnr} · ${material.maktx || 'Material'}` : '';
    }, [materials, selectedMaterial]);
    const [materialQuery, setMaterialQuery] = useState(selectedMaterialLabel);
    useEffect(() => setMaterialQuery(selectedMaterialLabel), [selectedMaterialLabel]);

    const applyConsumptionMaterial = () => {
        const query = materialQuery.trim();
        const match =
            query === ''
                ? undefined
                : materials.find(
                      (material) =>
                          material.matnr.toLowerCase() === query.toLowerCase() ||
                          `${material.matnr} · ${material.maktx || 'Material'}`.toLowerCase() === query.toLowerCase(),
                  ) ||
                  materials.find(
                      (material) =>
                          material.matnr.toLowerCase().includes(query.toLowerCase()) ||
                          (material.maktx || '').toLowerCase().includes(query.toLowerCase()),
                  );
        if (query !== '' && !match) return;
        router.get(
            '/admin/procurement/dashboard',
            { company: selectedCompany, source: selectedSource, material: match?.matnr || '', consumption_plant: selectedConsumptionPlant },
            { preserveScroll: true, preserveState: true },
        );
    };
    const plantDemandGraph = useMemo(
        () => plantDemand.map((item) => ({ label: item.label ?? 'Unknown plant', value: Number(item.value) })),
        [plantDemand],
    );
    const materialDemandGraph = useMemo(
        () => materialDemand.map((item) => ({ label: item.code, value: Number(item.value), description: item.name ?? 'Material' })),
        [materialDemand],
    );
    const stockGraph = useMemo(
        () => stockByPlant.map((item) => ({ label: item.label ?? 'Unknown plant', value: Number(item.value) })),
        [stockByPlant],
    );
    const deliveryGraph = useMemo(
        () =>
            deliveryStatus.map((item) => ({
                label: item.po_number,
                value: Number(item.quantity_mt),
                description: `${item.source === 'sap' ? 'SAP API' : 'Local'} · ${label(item.label)} · ${item.vendor_name}`,
                color: item.source === 'sap' ? 'bg-orange-500' : 'bg-sky-500',
            })),
        [deliveryStatus],
    );
    const consumptionGraph = useMemo(
        () => monthlyConsumption.map((item) => ({ label: item.month, value: Number(item.value) })),
        [monthlyConsumption],
    );
    const hasDemand = plantDemandGraph.some((item) => item.value > 0) || materialDemandGraph.some((item) => item.value > 0);
    const hasConsumption = consumptionGraph.some((item) => item.value > 0);
    const demandOrStockBars = hasDemand ? plantDemandGraph : stockGraph;
    const demandOrStockDonut = materialDemandGraph.some((item) => item.value > 0)
        ? materialDemandGraph
        : stockGraph.map((item) => ({ ...item, description: 'Inventory by plant' }));
    const cards = [
        {
            title: 'Monthly demand',
            value: `${number(kpis.monthly_demand_mt)} MT`,
            note: selectedSource === 'sap' ? `${kpis.active_consumptions} SAP-locked decisions` : `${kpis.active_consumptions} locked manual plans`,
            formula:
                selectedSource === 'sap' ? 'Σ SAP daily consumption KG ÷ 1,000 × calendar days' : 'Σ manual daily requirement MT × planning days',
            icon: TrendingUp,
        },
        {
            title: 'Purchase quantity',
            value: `${number(kpis.purchase_quantity_mt)} MT`,
            note: selectedSource === 'sap' ? 'Active SAP orders' : 'Active manual orders',
            formula: selectedSource === 'sap' ? 'Σ SAP ordered quantity in MT' : 'Σ manually entered PO quantity in MT',
            icon: ShoppingCart,
        },
        {
            title: 'Open orders',
            value: number(kpis.open_orders),
            note: `${number(kpis.open_order_quantity_mt)} MT remaining`,
            formula: 'Count open POs · Σ ordered − received',
            icon: Clock3,
        },
        {
            title: 'Current inventory',
            value: `${number(kpis.inventory_mt)} MT`,
            note: 'Across active material stocks',
            formula: 'Σ current stock in KG ÷ 1,000',
            icon: Boxes,
        },
    ];

    return (
        <AppLayout>
            <Head title="Procurement Dashboard" />
            <div className="procurement-compact mx-auto w-full max-w-[1680px] space-y-3 p-3 md:p-4">
                <div className="relative z-30 overflow-hidden rounded-2xl border-t-[3px] border-orange-500 bg-gradient-to-r from-[#07374b] via-[#075b70] to-[#0b8da3] p-3 shadow-lg shadow-slate-900/10">
                    <ProcurementPageHeader
                        title="Procurement command center"
                        subtitle={`${selectedCompany === '1000' ? 'Apollo Pipes Limited · APL' : 'Kisan Mouldings Limited · KML'} · Demand, stock and purchase-order visibility`}
                        icon={PackageCheck}
                        actions={
                            <>
                                <label className="grid min-w-56 gap-1 text-[9px] font-bold tracking-[0.14em] text-cyan-100/70 uppercase">
                                    Operating company
                                    <select
                                        value={selectedCompany}
                                        onChange={(event) =>
                                            router.get(
                                                '/admin/procurement/dashboard',
                                                { company: event.target.value, source: selectedSource },
                                                { preserveScroll: true },
                                            )
                                        }
                                        className="h-10 rounded-lg border border-white/15 bg-[#0a6074] px-3 text-xs font-semibold text-white transition outline-none hover:border-white/30 focus:border-orange-300 focus:ring-2 focus:ring-orange-300/20"
                                    >
                                        {companies.map((company) => (
                                            <option key={company.id} value={company.company_code}>
                                                {company.company_code === '1000'
                                                    ? 'APL'
                                                    : company.company_code === '5000'
                                                      ? 'KML'
                                                      : company.company_code}{' '}
                                                · {company.company_name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <div className="min-w-48">
                                    <p className="mb-1 text-[9px] font-bold tracking-[0.14em] text-cyan-100/70 uppercase">Locked planning mode</p>
                                    <div className="grid h-10 grid-cols-2 rounded-lg border border-white/15 bg-[#084f61]/80 p-1">
                                        {(['sap', 'manual'] as const).map((source) => (
                                            <button
                                                key={source}
                                                type="button"
                                                onClick={() =>
                                                    router.get(
                                                        '/admin/procurement/dashboard',
                                                        {
                                                            company: selectedCompany,
                                                            source,
                                                            material: selectedMaterial,
                                                            consumption_plant: selectedConsumptionPlant,
                                                        },
                                                        { preserveScroll: true },
                                                    )
                                                }
                                                className={`rounded-md px-3 text-[11px] font-semibold transition ${selectedSource === source ? 'bg-white text-[#075b70] shadow-sm' : 'text-white/65 hover:bg-white/10 hover:text-white'}`}
                                            >
                                                {source === 'sap' ? 'SAP' : 'Manual'}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            </>
                        }
                        embedded
                    />
                </div>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map(({ title, value, note, formula, icon: Icon }, index) => (
                        <Card
                            key={title}
                            className="group relative overflow-hidden border-slate-200 shadow-[0_8px_28px_rgba(15,23,42,0.06)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_16px_36px_rgba(15,23,42,0.10)]"
                        >
                            <span
                                className={`absolute inset-x-0 top-0 h-1 ${['bg-cyan-500', 'bg-sky-500', 'bg-amber-500', 'bg-emerald-500'][index]}`}
                            />
                            <CardContent className="p-5">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="min-w-0">
                                        <p className="text-[11px] font-black tracking-[0.08em] text-slate-500 uppercase">{title}</p>
                                        <p className="mt-3 text-2xl font-black tracking-tight text-slate-950 tabular-nums">{value}</p>
                                        <p className="mt-2 text-xs font-semibold text-slate-600">{note}</p>
                                    </div>
                                    <span className="bg-brand-teal/10 text-brand-teal grid size-11 shrink-0 place-items-center rounded-xl transition-transform group-hover:scale-110">
                                        <Icon className="size-5.5" />
                                    </span>
                                </div>
                                <div className="mt-4 flex items-center justify-between border-t border-dashed border-slate-200 pt-3">
                                    <span className="inline-flex items-center gap-1 text-[10px] font-bold text-slate-500" title={formula}>
                                        <Info className="size-3" /> Calculation method
                                    </span>
                                    <span className="text-[9px] font-bold tracking-wide text-cyan-700 uppercase">
                                        {selectedSource === 'sap' ? 'SAP source' : 'Manual source'}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </section>

                <section>
                    <div className="grid gap-5 xl:grid-cols-12">
                        <Card className="overflow-hidden border-slate-200 shadow-[0_8px_28px_rgba(15,23,42,0.05)] xl:col-span-7">
                            <CardHeader className="border-b bg-gradient-to-r from-slate-50 to-cyan-50/50 px-5 py-4">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <CardTitle className="text-sm">{hasDemand ? 'Demand by plant' : 'Inventory by plant'}</CardTitle>
                                        <p className="mt-1 text-[11px] text-slate-500">
                                            {hasDemand
                                                ? 'Top plant requirements in metric tonnes'
                                                : 'Available stock across operating plants in metric tonnes'}
                                        </p>
                                    </div>
                                    <span className="grid size-9 place-items-center rounded-xl bg-cyan-100 text-cyan-700">
                                        <TrendingUp className="size-4" />
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent className="p-5">
                                <VerticalBarChart items={demandOrStockBars} />
                            </CardContent>
                        </Card>

                        <Card className="overflow-hidden border-slate-200 shadow-[0_8px_28px_rgba(15,23,42,0.05)] xl:col-span-5">
                            <CardHeader className="border-b bg-gradient-to-r from-slate-50 to-orange-50/50 px-5 py-4">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <CardTitle className="text-sm">{hasDemand ? 'Material demand mix' : 'Inventory distribution'}</CardTitle>
                                        <p className="mt-1 text-[11px] text-slate-500">
                                            {hasDemand ? 'Share of total requirement by material' : 'Share of available inventory by plant'}
                                        </p>
                                    </div>
                                    <span className="grid size-9 place-items-center rounded-xl bg-orange-100 text-orange-600">
                                        <ChartPie className="size-4" />
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent className="p-5">
                                <MaterialDemandDonut items={demandOrStockDonut} totalLabel={hasDemand ? 'Total demand' : 'Total inventory'} />
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <section>
                    <div className="grid items-stretch gap-5 xl:grid-cols-12">
                        <Card className="overflow-hidden border-slate-200 shadow-sm xl:col-span-7">
                            <CardHeader className="flex flex-col gap-3 border-b bg-gradient-to-r from-slate-50 to-cyan-50/40 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <CardTitle className="text-sm">Monthly consumption</CardTitle>
                                    <p className="mt-1 text-[11px] text-slate-500">
                                        {selectedSource === 'sap'
                                            ? 'Actual three-month consumption received from SAP Consumption API'
                                            : 'Last six months · manual requirement × planning days'}
                                    </p>
                                </div>
                                <div className="flex w-full items-center gap-2 sm:w-auto">
                                    <select
                                        value={selectedConsumptionPlant}
                                        onChange={(event) =>
                                            router.get(
                                                '/admin/procurement/dashboard',
                                                {
                                                    company: selectedCompany,
                                                    source: selectedSource,
                                                    material: selectedMaterial,
                                                    consumption_plant: event.target.value,
                                                },
                                                { preserveScroll: true, preserveState: true },
                                            )
                                        }
                                        className="h-9 min-w-36 rounded-lg border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-700 shadow-sm transition outline-none hover:border-cyan-300 focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/10"
                                    >
                                        <option value="">All plants</option>
                                        {consumptionPlants.map((plant) => (
                                            <option key={plant.company_code} value={plant.company_code}>
                                                {plant.company_name}
                                            </option>
                                        ))}
                                    </select>
                                    <form
                                        className="relative min-w-0 flex-1 sm:w-80"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            applyConsumptionMaterial();
                                        }}
                                    >
                                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                        <input
                                            list="procurement-consumption-materials"
                                            value={materialQuery}
                                            onChange={(event) => setMaterialQuery(event.target.value)}
                                            placeholder="Search material code or name"
                                            className="h-9 w-full rounded-lg border border-slate-200 bg-white pr-16 pl-9 text-xs font-semibold text-slate-700 shadow-sm transition outline-none placeholder:font-normal hover:border-cyan-300 focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/10"
                                        />
                                        <datalist id="procurement-consumption-materials">
                                            {materials.map((material) => (
                                                <option key={material.matnr} value={`${material.matnr} · ${material.maktx || 'Material'}`} />
                                            ))}
                                        </datalist>
                                        <button
                                            type="submit"
                                            className="absolute top-1/2 right-1.5 -translate-y-1/2 rounded-md bg-cyan-600 px-2.5 py-1 text-[9px] font-black tracking-wide text-white uppercase transition hover:bg-cyan-700"
                                        >
                                            View
                                        </button>
                                    </form>
                                    {(selectedMaterial || selectedConsumptionPlant) && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setMaterialQuery('');
                                                router.get(
                                                    '/admin/procurement/dashboard',
                                                    { company: selectedCompany, source: selectedSource },
                                                    { preserveScroll: true, preserveState: true },
                                                );
                                            }}
                                            className="text-[10px] font-bold text-cyan-700 hover:text-cyan-900"
                                        >
                                            Clear
                                        </button>
                                    )}
                                    <span className="shrink-0 rounded-full bg-cyan-100 px-3 py-1 text-[10px] font-bold text-cyan-800">
                                        {consumptionUnit}
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent className="p-5">
                                <MonthlyConsumptionChart
                                    items={consumptionGraph}
                                    hasData={hasConsumption}
                                    filtered={Boolean(selectedMaterial || selectedConsumptionPlant)}
                                    unit={consumptionUnit}
                                />
                            </CardContent>
                        </Card>
                        <Card className="overflow-hidden border-slate-200 shadow-sm xl:col-span-5">
                            <CardHeader className="border-b bg-gradient-to-r from-slate-50 via-white to-cyan-50/60 px-5 py-4">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <CardTitle className="text-sm text-slate-900">Inventory by plant</CardTitle>
                                        <p className="mt-1 text-[11px] text-slate-500">Available stock distribution across operating plants</p>
                                    </div>
                                    <div className="flex items-center gap-2 rounded-xl border border-cyan-100 bg-white px-3 py-2 shadow-sm">
                                        <span className="grid size-7 place-items-center rounded-lg bg-cyan-50 text-cyan-700">
                                            <Boxes className="size-3.5" />
                                        </span>
                                        <div className="text-right">
                                            <p className="text-[8px] font-bold tracking-wider text-slate-400 uppercase">Total stock</p>
                                            <p className="text-xs font-black text-slate-900 tabular-nums">{number(kpis.inventory_mt)} MT</p>
                                        </div>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="max-h-[320px] overflow-y-auto p-4 [scrollbar-width:thin]">
                                <StockLineGraph items={stockGraph} />
                                {stockByPlant.length === 0 && <EmptyState text="No stock data for the selected filters." />}
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <Card className="overflow-hidden border-slate-200 shadow-sm">
                    <CardHeader className="border-b bg-gradient-to-r from-slate-50 to-orange-50/40 px-5 py-4">
                        <CardTitle className="text-sm">{selectedSource === 'sap' ? 'SAP delivery pipeline' : 'Manual delivery pipeline'}</CardTitle>
                        <p className="mt-1 text-[11px] text-slate-500">Weight-based purchase orders with valid MT conversion</p>
                    </CardHeader>
                    <CardContent className="p-4 md:p-5">
                        <DeliveryTimeline items={deliveryGraph} />
                        {deliveryStatus.length === 0 && <EmptyState text="No purchase delivery data." />}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

type GraphItem = { label: string; value: number; description?: string; color?: string };

// eslint-disable-next-line @typescript-eslint/no-unused-vars
function SectionHeading({ eyebrow, title, description }: { eyebrow: string; title: string; description: string }) {
    return (
        <div className="relative overflow-hidden rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-[0_4px_18px_rgba(15,23,42,0.04)] sm:px-5">
            <span className="absolute inset-y-3 left-0 w-1 rounded-r-full bg-gradient-to-b from-cyan-500 to-teal-700" />
            <div className="flex items-center justify-between gap-4">
                <div>
                    <p className="text-[9px] font-black tracking-[0.16em] text-cyan-700 uppercase">{eyebrow}</p>
                    <h2 className="mt-0.5 text-sm font-black text-slate-900">{title}</h2>
                    <p className="mt-0.5 text-[11px] leading-4 text-slate-500">{description}</p>
                </div>
                <span className="hidden h-px min-w-20 flex-1 bg-gradient-to-r from-cyan-200 via-slate-100 to-transparent sm:block" />
            </div>
        </div>
    );
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const HorizontalComparisonGraph = memo(function HorizontalComparisonGraph({ items }: { items: GraphItem[] }) {
    const visibleItems = items.filter((item) => item.value > 0).slice(0, 10);
    if (!visibleItems.length)
        return (
            <ChartEmptyState
                title="No plant demand available"
                description="Run the consumption sync or add a locked manual plan to populate this comparison."
            />
        );
    const maximum = Math.max(...visibleItems.map((item) => item.value), 1);

    return (
        <div className="max-h-72 space-y-3 overflow-y-auto pr-2 [scrollbar-width:thin]">
            {visibleItems.map((item, index) => (
                <div key={`${item.label}-${index}`} className="grid grid-cols-[7rem_1fr_auto] items-center gap-3">
                    <span className="truncate text-xs font-semibold text-slate-700" title={item.label}>
                        {item.label}
                    </span>
                    <div className="h-3 overflow-hidden rounded-full bg-slate-100">
                        <div
                            className="h-full rounded-full bg-gradient-to-r from-cyan-500 to-sky-400 transition-all duration-500"
                            style={{ width: `${Math.max((item.value / maximum) * 100, 2)}%` }}
                        />
                    </div>
                    <strong className="w-20 text-right text-xs text-slate-900 tabular-nums">{number(item.value)} MT</strong>
                </div>
            ))}
        </div>
    );
});

const VerticalBarChart = memo(function VerticalBarChart({ items }: { items: GraphItem[] }) {
    const visibleItems = [...items]
        .filter((item) => item.value > 0)
        .sort((a, b) => b.value - a.value)
        .slice(0, 8);
    if (!visibleItems.length)
        return <ChartEmptyState title="No plant values available" description="Run the relevant SAP sync to populate this chart." />;
    const maximum = Math.max(...visibleItems.map((item) => item.value), 1);

    return (
        <div className="relative overflow-hidden rounded-xl border border-slate-100 bg-gradient-to-b from-slate-50/80 to-white px-3 pt-5 pb-3">
            <div className="pointer-events-none absolute inset-x-3 top-5 bottom-16 flex flex-col justify-between">
                {[0, 1, 2, 3].map((line) => (
                    <span key={line} className="block border-t border-dashed border-slate-200" />
                ))}
            </div>
            <div className="relative grid h-64 items-end gap-2" style={{ gridTemplateColumns: `repeat(${visibleItems.length}, minmax(52px, 1fr))` }}>
                {visibleItems.map((item, index) => {
                    const height = Math.max((item.value / maximum) * 100, 4);
                    return (
                        <div key={`${item.label}-${index}`} className="group flex h-full min-w-0 flex-col justify-end">
                            <div
                                className="mb-2 truncate text-center text-[10px] font-black text-slate-700 tabular-nums"
                                title={`${number(item.value)} MT`}
                            >
                                {number(item.value)} MT
                            </div>
                            <div className="relative mx-auto flex h-44 w-full max-w-14 items-end overflow-hidden rounded-t-xl bg-slate-100 ring-1 ring-slate-200/60">
                                <div
                                    className="w-full rounded-t-xl bg-gradient-to-t from-[#08768a] via-cyan-500 to-sky-300 shadow-[0_-8px_20px_rgba(6,182,212,0.18)] transition-all duration-700 group-hover:from-orange-600 group-hover:via-orange-500 group-hover:to-amber-300"
                                    style={{ height: `${height}%` }}
                                />
                            </div>
                            <p className="mt-2 line-clamp-2 min-h-8 text-center text-[10px] leading-4 font-bold text-slate-600" title={item.label}>
                                {item.label.replace(' Plant', '')}
                            </p>
                        </div>
                    );
                })}
            </div>
        </div>
    );
});

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const RankedMaterialGraph = memo(function RankedMaterialGraph({ items }: { items: GraphItem[] }) {
    if (!items.length) return null;
    const visibleItems = items.slice(0, 10);
    const total = Math.max(
        visibleItems.reduce((sum, item) => sum + item.value, 0),
        1,
    );
    const colors = ['bg-amber-500', 'bg-orange-500', 'bg-violet-500', 'bg-cyan-500', 'bg-emerald-500'];

    return (
        <div className="grid max-h-72 gap-2 overflow-y-auto pr-2 [scrollbar-width:thin] sm:grid-cols-2">
            {visibleItems.map((item, index) => (
                <div key={`${item.label}-${index}`} className="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                    <div className="flex items-start gap-2">
                        <span
                            className={`grid size-6 shrink-0 place-items-center rounded-md text-[10px] font-bold text-white ${colors[index % colors.length]}`}
                        >
                            {index + 1}
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-xs font-bold text-slate-900" title={item.label}>
                                {item.label}
                            </p>
                            <p className="truncate text-[10px] text-slate-500" title={item.description}>
                                {item.description || 'Material'}
                            </p>
                        </div>
                        <strong className="text-xs text-slate-900 tabular-nums">{number(item.value)}</strong>
                    </div>
                    <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-white">
                        <div
                            className={`h-full rounded-full ${colors[index % colors.length]}`}
                            style={{ width: `${Math.max((item.value / total) * 100, 1)}%` }}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
});

const MaterialDemandDonut = memo(function MaterialDemandDonut({ items, totalLabel = 'Total demand' }: { items: GraphItem[]; totalLabel?: string }) {
    const palette = ['#0891b2', '#f97316', '#7c3aed', '#10b981', '#f59e0b', '#64748b'];
    const sorted = [...items].filter((item) => item.value > 0).sort((a, b) => b.value - a.value);
    if (!sorted.length)
        return (
            <ChartEmptyState
                title="No material mix to display"
                description="Positive material demand will appear here as a proportional distribution."
                icon="pie"
            />
        );
    const leading = sorted.slice(0, 5);
    const remainder = sorted.slice(5).reduce((sum, item) => sum + item.value, 0);
    const segments = remainder > 0 ? [...leading, { label: 'Other materials', value: remainder, description: 'Combined remaining demand' }] : leading;
    const total = segments.reduce((sum, item) => sum + item.value, 0);
    let cursor = 0;
    const stops = segments.map((item, index) => {
        const start = cursor;
        cursor += total > 0 ? (item.value / total) * 360 : 0;
        return `${palette[index % palette.length]} ${start}deg ${cursor}deg`;
    });

    return (
        <div className="grid items-center gap-6 sm:grid-cols-[190px_1fr]">
            <div
                className="relative mx-auto grid size-48 place-items-center rounded-full border-[10px] border-white shadow-[0_14px_34px_rgba(15,23,42,0.14)]"
                style={{ background: `conic-gradient(${stops.join(', ')})` }}
                role="img"
                aria-label={`${totalLabel} distribution totaling ${number(total)} metric tonnes`}
            >
                <div className="grid size-[116px] place-items-center rounded-full border border-slate-100 bg-white text-center shadow-inner">
                    <div>
                        <p className="text-[9px] font-bold tracking-[0.1em] text-slate-400 uppercase">{totalLabel}</p>
                        <p className="mt-1 text-xl font-black text-slate-950 tabular-nums">{number(total)}</p>
                        <p className="text-[10px] font-bold text-cyan-700">MT</p>
                    </div>
                </div>
            </div>
            <div className="space-y-2.5">
                {segments.map((item, index) => {
                    const share = total > 0 ? (item.value / total) * 100 : 0;
                    return (
                        <div
                            key={`${item.label}-${index}`}
                            className="group rounded-xl border border-slate-100 bg-slate-50/70 px-3 py-2.5 transition hover:border-cyan-200 hover:bg-white hover:shadow-sm"
                        >
                            <div className="flex items-center gap-2.5">
                                <span className="size-2.5 shrink-0 rounded-full" style={{ backgroundColor: palette[index % palette.length] }} />
                                <div className="min-w-0 flex-1">
                                    <p
                                        className="truncate text-[11px] font-bold text-slate-800"
                                        title={item.description ? `${item.label} · ${item.description}` : item.label}
                                    >
                                        {item.label}
                                    </p>
                                    <p className="text-[9px] text-slate-400">{share.toFixed(1)}% of requirement</p>
                                </div>
                                <strong className="text-[11px] text-slate-900 tabular-nums">{number(item.value)} MT</strong>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
});

const MonthlyConsumptionChart = memo(function MonthlyConsumptionChart({
    items,
    hasData,
    filtered,
    unit,
}: {
    items: GraphItem[];
    hasData: boolean;
    filtered: boolean;
    unit: string;
}) {
    const maximum = Math.max(...items.map((item) => item.value), 1);
    return (
        <div>
            {!hasData && (
                <div className="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[10px] font-semibold text-amber-800">
                    {filtered
                        ? 'No SAP consumption is stored for this material and plant. Showing the recorded zero values.'
                        : 'No positive SAP consumption is currently stored. Showing the last three recorded months.'}
                </div>
            )}
            <div className={`grid grid-cols-3 gap-3 sm:h-56 sm:items-end sm:gap-4 ${items.length > 3 ? 'sm:grid-cols-6' : 'sm:grid-cols-3'}`}>
                {items.map((item) => (
                    <div key={item.label} className="flex min-w-0 flex-col justify-end rounded-xl bg-slate-50 p-2 sm:h-full sm:bg-transparent sm:p-0">
                        <div className="mb-2 truncate text-center text-[10px] font-bold text-slate-700">
                            {number(item.value)} {unit}
                        </div>
                        <div className="relative mx-auto flex h-24 w-full max-w-24 items-end overflow-hidden rounded-t-xl bg-slate-100 sm:h-40">
                            <div
                                className="w-full rounded-t-xl bg-gradient-to-t from-cyan-700 via-cyan-500 to-sky-300 transition-all duration-500"
                                style={{ height: `${item.value > 0 ? Math.max((item.value / maximum) * 100, 6) : 2}%` }}
                            />
                        </div>
                        <p className="mt-2 truncate text-center text-[10px] font-semibold text-slate-500">{item.label}</p>
                    </div>
                ))}
            </div>
        </div>
    );
});

const StockLineGraph = memo(function StockLineGraph({ items }: { items: GraphItem[] }) {
    if (!items.length) return null;
    const visibleItems = items.slice(0, 8);
    const maximum = Math.max(...visibleItems.map((item) => item.value), 1);
    const total = visibleItems.reduce((sum, item) => sum + item.value, 0);
    return (
        <div className="space-y-2.5">
            {visibleItems.map((item, index) => (
                <div
                    key={`${item.label}-${index}`}
                    className="group rounded-xl border border-slate-200/80 bg-white p-3 shadow-[0_3px_12px_rgba(15,23,42,0.035)] transition hover:-translate-y-px hover:border-cyan-200 hover:shadow-md"
                >
                    <div className="mb-2.5 flex items-center gap-2.5">
                        <span className="grid size-6 shrink-0 place-items-center rounded-md bg-slate-100 text-[9px] font-black text-slate-500 transition group-hover:bg-cyan-50 group-hover:text-cyan-700">
                            {String(index + 1).padStart(2, '0')}
                        </span>
                        <span className="min-w-0 flex-1 truncate text-xs font-bold text-slate-800" title={item.label}>
                            {item.label}
                        </span>
                        <div className="shrink-0 text-right">
                            <strong className="block text-xs font-black text-slate-950 tabular-nums">
                                {number(item.value)} <small className="text-[9px] font-bold text-slate-400">MT</small>
                            </strong>
                            <span className="text-[9px] font-semibold text-cyan-700 tabular-nums">
                                {total > 0 ? ((item.value / total) * 100).toFixed(1) : '0.0'}% share
                            </span>
                        </div>
                    </div>
                    <div className="h-2 overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200/60">
                        <div
                            className="h-full rounded-full bg-gradient-to-r from-cyan-700 via-cyan-500 to-teal-400 shadow-[0_0_8px_rgba(6,182,212,0.25)] transition-all duration-500"
                            style={{ width: `${Math.max((item.value / maximum) * 100, 1)}%` }}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
});

const DeliveryTimeline = memo(function DeliveryTimeline({ items }: { items: GraphItem[] }) {
    if (!items.length) return null;

    return (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {items.slice(0, 8).map((item, index) => (
                <div
                    key={`${item.label}-${index}`}
                    className="group relative overflow-hidden rounded-xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-orange-200 hover:shadow-md"
                >
                    <span className={`absolute inset-y-0 left-0 w-1 ${item.color || 'bg-sky-500'}`} />
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="truncate text-xs font-bold text-slate-900" title={item.label}>
                                {item.label}
                            </p>
                            <p className="mt-2 text-xl font-black text-slate-950 tabular-nums">
                                {number(item.value)} <small className="text-[10px] font-bold text-slate-500">MT</small>
                            </p>
                        </div>
                        <span className={`mt-0.5 size-2.5 shrink-0 rounded-full ${item.color || 'bg-sky-500'}`} />
                    </div>
                    <p className="mt-2 line-clamp-2 text-[10px] leading-4 text-slate-500" title={item.description}>
                        {item.description}
                    </p>
                </div>
            ))}
        </div>
    );
});

function EmptyState({ text }: { text: string }) {
    return <div className="rounded-xl border border-dashed p-8 text-center text-sm text-slate-500">{text}</div>;
}

function ChartEmptyState({ title, description, icon = 'alert' }: { title: string; description: string; icon?: 'alert' | 'pie' }) {
    const Icon = icon === 'pie' ? ChartPie : CircleAlert;
    return (
        <div className="grid min-h-52 place-items-center rounded-xl border border-dashed border-slate-200 bg-gradient-to-br from-slate-50 to-white p-6 text-center">
            <div className="max-w-xs">
                <span className="mx-auto grid size-11 place-items-center rounded-xl bg-slate-100 text-slate-500">
                    <Icon className="size-5" />
                </span>
                <p className="mt-3 text-sm font-black text-slate-800">{title}</p>
                <p className="mt-1 text-xs leading-5 text-slate-500">{description}</p>
            </div>
        </div>
    );
}
