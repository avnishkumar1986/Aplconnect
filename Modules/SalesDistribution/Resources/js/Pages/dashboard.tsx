import ProjectPageHeader from '@/components/project-page-header';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import {
    ArrowUpRight,
    Boxes,
    CalendarDays,
    ChartNoAxesCombined,
    CheckCircle2,
    IndianRupee,
    MapPinned,
    PackageCheck,
    Route,
    Search,
    Truck,
} from 'lucide-react';
import { useMemo, useState } from 'react';

type Dashboard = {
    updatedAt: string;
    kpis: { label: string; value: string; change: string; note: string; tone: string }[];
    salesTrend: number[];
    regions: { name: string; sales: string; achievement: number; growth: string }[];
    fulfilment: { label: string; value: number; percent: number }[];
    actions: { customer: string; issue: string; owner: string; priority: string; due: string }[];
};

const icons = [IndianRupee, Boxes, Truck, PackageCheck];
const toneStyles: Record<string, string> = {
    teal: 'from-cyan-500 to-teal-600 shadow-cyan-500/20',
    blue: 'from-sky-500 to-blue-600 shadow-blue-500/20',
    amber: 'from-amber-400 to-orange-500 shadow-amber-500/20',
    violet: 'from-violet-500 to-indigo-600 shadow-violet-500/20',
};

const productPerformance = [
    { company: 'Apollo Pipes', product: 'PVC pipes', sales: '₹55.0 Cr', plan: '₹59.0 Cr', achievement: 93.2, volume: '4,732.5 t' },
    { company: 'Apollo Pipes', product: 'CPVC pipes & fittings', sales: '₹34.7 Cr', plan: '₹36.6 Cr', achievement: 94.8, volume: '1,650.4 t' },
    { company: 'Apollo Pipes', product: 'Other pipes & fittings', sales: '₹12.3 Cr', plan: '₹12.0 Cr', achievement: 102.6, volume: '864.2 t' },
    { company: 'Kisan Mouldings', product: 'PVC pipes', sales: '₹15.4 Cr', plan: '₹17.8 Cr', achievement: 86.6, volume: '1,696.0 t' },
    { company: 'Kisan Mouldings', product: 'CPVC pipes & fittings', sales: '₹5.7 Cr', plan: '₹6.8 Cr', achievement: 84.0, volume: '327.2 t' },
    { company: 'Mazari', product: 'Premium tile portfolio', sales: '₹8.9 Cr', plan: '₹9.6 Cr', achievement: 92.9, volume: '3.25 L m²' },
];

function TrendChart({ values }: { values: number[] }) {
    const width = 620;
    const height = 190;
    const min = Math.min(...values) - 6;
    const max = Math.max(...values) + 5;
    const points = values
        .map((value, index) => `${(index / (values.length - 1)) * width},${height - ((value - min) / (max - min)) * height}`)
        .join(' ');
    const area = `0,${height} ${points} ${width},${height}`;

    return (
        <div className="relative mt-6 h-52 overflow-hidden">
            <div className="absolute inset-0 flex flex-col justify-between py-1">
                {[0, 1, 2, 3].map((line) => (
                    <div key={line} className="border-t border-dashed border-slate-200" />
                ))}
            </div>
            <svg
                className="relative h-full w-full overflow-visible"
                viewBox={`0 0 ${width} ${height}`}
                preserveAspectRatio="none"
                aria-label="Monthly sales trend"
            >
                <defs>
                    <linearGradient id="salesArea" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="#0891b2" stopOpacity="0.24" />
                        <stop offset="100%" stopColor="#0891b2" stopOpacity="0" />
                    </linearGradient>
                </defs>
                <polygon points={area} fill="url(#salesArea)" />
                <polyline
                    points={points}
                    fill="none"
                    stroke="#0891b2"
                    strokeWidth="3"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    vectorEffect="non-scaling-stroke"
                />
            </svg>
            <div className="absolute inset-x-0 bottom-0 flex justify-between text-[10px] font-medium text-slate-400">
                <span>May</span>
                <span>Jun</span>
                <span>Jul</span>
                <span>Aug</span>
                <span>Sep</span>
                <span>Oct</span>
            </div>
        </div>
    );
}

export default function SalesDistributionDashboard({ dashboard }: { dashboard: Dashboard }) {
    const [company, setCompany] = useState('All companies');
    const [period, setPeriod] = useState('Month to date');
    const [search, setSearch] = useState('');
    const visibleProducts = useMemo(
        () =>
            productPerformance.filter(
                (row) =>
                    (company === 'All companies' || row.company === company) &&
                    `${row.company} ${row.product}`.toLowerCase().includes(search.toLowerCase()),
            ),
        [company, search],
    );

    return (
        <AppLayout>
            <Head title="Sales & Distribution" />
            <div className="mx-auto w-full max-w-[1680px] space-y-4 p-3 md:p-5 lg:p-6">
                <ProjectPageHeader
                    eyebrow="Commercial operations"
                    title="Sales & Distribution"
                    subtitle="A unified view of revenue, order fulfilment and regional distribution performance."
                    icon={<Route className="size-5" />}
                    actions={
                        <>
                            <label className="grid min-w-[190px] flex-1 gap-1.5 text-[9px] font-bold tracking-[0.14em] text-cyan-100/70 uppercase xl:flex-none">
                                Company
                                <select
                                    value={company}
                                    onChange={(event) => setCompany(event.target.value)}
                                    className="h-10 w-full rounded-lg border border-white/15 bg-[#0a6074] px-3 text-xs font-semibold text-white transition outline-none hover:border-white/30 focus:border-orange-300 focus:ring-2 focus:ring-orange-300/20 xl:w-48"
                                >
                                    <option>All companies</option>
                                    <option>Apollo Pipes</option>
                                    <option>Kisan Mouldings</option>
                                    <option>Mazari</option>
                                </select>
                            </label>
                            <div className="min-w-[210px] flex-1 xl:flex-none">
                                <p className="mb-1.5 text-[9px] font-bold tracking-[0.14em] text-cyan-100/70 uppercase">Operating window</p>
                                <div className="grid h-10 grid-cols-2 rounded-lg border border-white/15 bg-[#084f61]/80 p-1">
                                    {['Today', 'Month to date'].map((item) => (
                                        <button
                                            key={item}
                                            type="button"
                                            onClick={() => setPeriod(item)}
                                            className={`rounded-md px-2 text-[11px] font-semibold transition ${period === item ? 'bg-white text-[#075b70] shadow-sm' : 'text-white/65 hover:bg-white/8 hover:text-white'}`}
                                        >
                                            {item}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <div className="flex min-h-14 min-w-[230px] flex-1 items-center gap-3 border-white/15 px-2 xl:flex-none xl:border-l xl:pl-4">
                                <span className="grid size-9 shrink-0 place-items-center rounded-lg border border-white/15 bg-white/10 text-orange-300">
                                    <CalendarDays className="size-4" />
                                </span>
                                <div className="min-w-0">
                                    <p className="text-[9px] font-bold tracking-[0.14em] text-cyan-100/70 uppercase">Reporting status</p>
                                    <div className="mt-1 flex items-center gap-1.5">
                                        <i className="size-1.5 rounded-full bg-emerald-300 shadow-[0_0_0_3px_rgba(110,231,183,0.12)]" />
                                        <p className="truncate text-[11px] font-semibold text-white/90">Live · {dashboard.updatedAt}</p>
                                    </div>
                                </div>
                            </div>
                        </>
                    }
                />

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {dashboard.kpis.map((kpi, index) => {
                        const Icon = icons[index];
                        return (
                            <article
                                key={kpi.label}
                                className="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_8px_30px_rgba(15,23,42,0.05)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_18px_40px_rgba(15,23,42,0.10)]"
                            >
                                <div
                                    className={`absolute -top-12 -right-10 size-36 rounded-full bg-gradient-to-br opacity-[0.08] ${toneStyles[kpi.tone]}`}
                                />
                                <div className="relative flex items-start justify-between">
                                    <div>
                                        <p className="text-xs font-bold tracking-wide text-slate-500 uppercase">{kpi.label}</p>
                                        <p className="mt-4 text-2xl font-black tracking-tight text-slate-950">{kpi.value}</p>
                                    </div>
                                    <span
                                        className={`grid size-12 place-items-center rounded-2xl bg-gradient-to-br text-white shadow-lg transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3 ${toneStyles[kpi.tone]}`}
                                    >
                                        <Icon className="size-6" />
                                    </span>
                                </div>
                                <div className="relative mt-4 flex items-center gap-2 text-xs">
                                    <span className="inline-flex items-center gap-1 font-bold text-cyan-700">
                                        <ArrowUpRight className="size-3.5" />
                                        {kpi.change}
                                    </span>
                                    <span className="text-slate-500">{kpi.note}</span>
                                </div>
                            </article>
                        );
                    })}
                </section>

                <section className="grid gap-5 xl:grid-cols-[1.45fr_1fr]">
                    <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-xs font-bold text-cyan-700 uppercase">Revenue momentum</p>
                                <h2 className="mt-1 text-lg font-bold text-slate-900">Monthly net sales trend</h2>
                            </div>
                            <span className="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700">+9.6% YTD</span>
                        </div>
                        <TrendChart values={dashboard.salesTrend} />
                    </article>

                    <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                        <div className="flex items-center gap-3">
                            <span className="grid size-10 place-items-center rounded-xl bg-cyan-50 text-cyan-700">
                                <MapPinned className="size-5" />
                            </span>
                            <div>
                                <p className="text-xs font-bold text-cyan-700 uppercase">Market coverage</p>
                                <h2 className="text-lg font-bold text-slate-900">Regional performance</h2>
                            </div>
                        </div>
                        <div className="mt-5 space-y-4">
                            {dashboard.regions.map((region) => (
                                <div key={region.name}>
                                    <div className="mb-2 flex items-end justify-between gap-3 text-sm">
                                        <div>
                                            <b className="text-slate-900">{region.name}</b>
                                            <span className="ml-2 text-xs font-semibold text-emerald-600">{region.growth}</span>
                                        </div>
                                        <div className="text-right">
                                            <b>{region.sales}</b>
                                            <span className="ml-2 text-xs text-slate-500">{region.achievement}%</span>
                                        </div>
                                    </div>
                                    <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div
                                            className="h-full rounded-full bg-gradient-to-r from-cyan-500 to-teal-500"
                                            style={{ width: `${region.achievement}%` }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </article>
                </section>

                <section className="grid gap-5 xl:grid-cols-[1fr_1.45fr]">
                    <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                        <div className="flex items-center gap-3">
                            <span className="grid size-10 place-items-center rounded-xl bg-amber-50 text-amber-600">
                                <ChartNoAxesCombined className="size-5" />
                            </span>
                            <div>
                                <p className="text-xs font-bold text-amber-600 uppercase">Order journey</p>
                                <h2 className="text-lg font-bold text-slate-900">Fulfilment funnel</h2>
                            </div>
                        </div>
                        <div className="mt-6 space-y-4">
                            {dashboard.fulfilment.map((item) => (
                                <div key={item.label} className="grid grid-cols-[1fr_auto] items-center gap-3">
                                    <div>
                                        <div className="mb-1.5 flex justify-between text-xs">
                                            <span className="font-semibold text-slate-600">{item.label}</span>
                                            <span className="text-slate-400">{item.percent}%</span>
                                        </div>
                                        <div className="h-2.5 overflow-hidden rounded-full bg-slate-100">
                                            <div
                                                className="h-full rounded-full bg-gradient-to-r from-amber-400 to-orange-500"
                                                style={{ width: `${item.percent}%` }}
                                            />
                                        </div>
                                    </div>
                                    <span className="min-w-10 text-right text-lg font-black text-slate-900">{item.value}</span>
                                </div>
                            ))}
                        </div>
                    </article>

                    <article className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-100 p-5 md:px-6">
                            <div>
                                <p className="text-xs font-bold text-rose-600 uppercase">Attention required</p>
                                <h2 className="mt-1 text-lg font-bold text-slate-900">Priority action queue</h2>
                            </div>
                            <span className="flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">
                                <CheckCircle2 className="size-3.5" />4 open
                            </span>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-left text-sm">
                                <thead className="bg-slate-50 text-[10px] tracking-wider text-slate-500 uppercase">
                                    <tr>
                                        <th className="px-6 py-3">Customer</th>
                                        <th className="px-4 py-3">Issue / request</th>
                                        <th className="px-4 py-3">Owner</th>
                                        <th className="px-4 py-3">Priority</th>
                                        <th className="px-6 py-3 text-right">Due</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {dashboard.actions.map((item) => (
                                        <tr key={`${item.customer}-${item.issue}`} className="transition hover:bg-cyan-50/40">
                                            <td className="px-6 py-4 font-bold text-slate-900">{item.customer}</td>
                                            <td className="px-4 py-4 text-slate-600">{item.issue}</td>
                                            <td className="px-4 py-4 text-slate-600">{item.owner}</td>
                                            <td className="px-4 py-4">
                                                <span
                                                    className={
                                                        item.priority === 'High'
                                                            ? 'rounded-full bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700'
                                                            : 'rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700'
                                                    }
                                                >
                                                    {item.priority}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right font-semibold text-slate-700">{item.due}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </article>
                </section>

                <section className="space-y-3">
                    <div className="flex items-center gap-3">
                        <span className="h-5 w-1 rounded-full bg-gradient-to-b from-cyan-500 to-teal-600" />
                        <h2 className="text-xs font-black tracking-[0.1em] text-slate-800 uppercase">Product performance</h2>
                        <span className="h-px flex-1 bg-slate-200" />
                    </div>
                    <article className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="font-bold text-slate-900">Commercial product mix</p>
                                <p className="mt-1 text-xs text-slate-500">Sales, plan achievement and dispatched volume · {period}</p>
                            </div>
                            <label className="relative block">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Search company or product"
                                    className="h-10 w-full rounded-lg border border-slate-200 bg-slate-50 pr-3 pl-9 text-xs transition outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100 sm:w-64"
                                />
                            </label>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[760px] text-left text-sm">
                                <thead className="bg-slate-50 text-[10px] tracking-wider text-slate-500 uppercase">
                                    <tr>
                                        <th className="px-5 py-3">Company</th>
                                        <th className="px-4 py-3">Product portfolio</th>
                                        <th className="px-4 py-3 text-right">Net sales</th>
                                        <th className="px-4 py-3 text-right">Plan</th>
                                        <th className="px-4 py-3">Achievement</th>
                                        <th className="px-5 py-3 text-right">Volume</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {visibleProducts.map((row) => (
                                        <tr key={`${row.company}-${row.product}`} className="transition hover:bg-cyan-50/40">
                                            <td className="px-5 py-4 font-bold text-slate-900">{row.company}</td>
                                            <td className="px-4 py-4 text-slate-600">{row.product}</td>
                                            <td className="px-4 py-4 text-right font-semibold">{row.sales}</td>
                                            <td className="px-4 py-4 text-right text-slate-600">{row.plan}</td>
                                            <td className="px-4 py-4">
                                                <div className="flex items-center gap-2">
                                                    <div className="h-2 w-20 overflow-hidden rounded-full bg-slate-100">
                                                        <div
                                                            className={`h-full rounded-full ${row.achievement >= 100 ? 'bg-emerald-500' : 'bg-cyan-600'}`}
                                                            style={{ width: `${Math.min(row.achievement, 100)}%` }}
                                                        />
                                                    </div>
                                                    <span className="text-xs font-bold text-slate-700">{row.achievement}%</span>
                                                </div>
                                            </td>
                                            <td className="px-5 py-4 text-right text-slate-600">{row.volume}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {visibleProducts.length === 0 && (
                                <div className="py-12 text-center text-sm text-slate-500">No products match the selected filters.</div>
                            )}
                        </div>
                        <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-[10px] font-semibold text-slate-500">
                            <span>{visibleProducts.length} product groups</span>
                            <span>Management view · dummy data</span>
                        </div>
                    </article>
                </section>
            </div>
        </AppLayout>
    );
}
