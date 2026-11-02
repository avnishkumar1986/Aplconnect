import ProjectPageHeader from '@/components/project-page-header';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { AlertTriangle, CalendarDays, Factory, Gauge, Layers3, PackageCheck, TimerReset } from 'lucide-react';
import { useMemo, useState } from 'react';

type Period = 'day' | 'month';

const scorecard = [
    { company: 'Apollo Pipes', unit: 'tonnes', output: 360, plan: 380, achieved: 94.7, utilization: 85.7, rejection: 2.2, unused: 50, energy: 710 },
    {
        company: 'Kisan Mouldings',
        unit: 'tonnes',
        output: 116.2,
        plan: 130,
        achieved: 89.4,
        utilization: 70.4,
        rejection: 4.1,
        unused: 67.8,
        energy: 810,
    },
    { company: 'Mazzini', unit: 'm²', output: 16198, plan: 19000, achieved: 85.3, utilization: 77.1, rejection: 5.4, unused: 3.7, energy: 3.6 },
];

const dayValues = {
    pipe: '476 t',
    tile: '16,198 m²',
    availability: '84.7%',
    belowPlan: '3 / 3',
};

const monthValues = {
    pipe: '11,842 t',
    tile: '3.72 L m²',
    availability: '82.9%',
    belowPlan: '2 / 3',
};

const categoryDetails = [
    {
        company: 'Apollo Pipes',
        plant: 'Dadri',
        category: 'PVC pipes',
        unit: 't',
        output: 238.4,
        productionMtd: 96.2,
        productionYtd: 94.8,
        capacityMtd: 87.5,
        capacityYtd: 85.9,
    },
    {
        company: 'Apollo Pipes',
        plant: 'Sikandrabad',
        category: 'CPVC pipes & fittings',
        unit: 't',
        output: 79.6,
        productionMtd: 101.3,
        productionYtd: 98.1,
        capacityMtd: 89.2,
        capacityYtd: 86.4,
    },
    {
        company: 'Apollo Pipes',
        plant: 'Dadri',
        category: 'HDPE pipes',
        unit: 't',
        output: 42,
        productionMtd: 83.4,
        productionYtd: 88.7,
        capacityMtd: 71.5,
        capacityYtd: 76.2,
    },
    {
        company: 'Kisan Mouldings',
        plant: 'Tumkur',
        category: 'PVC pipes',
        unit: 't',
        output: 76.2,
        productionMtd: 91.6,
        productionYtd: 90.1,
        capacityMtd: 73.4,
        capacityYtd: 75.8,
    },
    {
        company: 'Kisan Mouldings',
        plant: 'Raipur',
        category: 'Fittings',
        unit: 't',
        output: 40,
        productionMtd: 85.8,
        productionYtd: 89.5,
        capacityMtd: 67.2,
        capacityYtd: 70.3,
    },
    {
        company: 'Mazzini',
        plant: 'Morbi',
        category: 'Floor tiles',
        unit: 'm²',
        output: 10420,
        productionMtd: 87.1,
        productionYtd: 91.6,
        capacityMtd: 79.8,
        capacityYtd: 81.2,
    },
    {
        company: 'Mazzini',
        plant: 'Morbi',
        category: 'Wall tiles',
        unit: 'm²',
        output: 5778,
        productionMtd: 82.4,
        productionYtd: 86.9,
        capacityMtd: 72.3,
        capacityYtd: 75.6,
    },
];

const achievementTone = (value: number) =>
    value >= 100 ? 'bg-emerald-50 text-emerald-700' : value >= 90 ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700';

function ComparisonChart() {
    return (
        <div className="mt-5 grid grid-cols-3 gap-5 sm:gap-8" aria-label="Plan achievement and capacity utilization chart">
            {scorecard.map((row) => (
                <div key={row.company} className="flex min-w-0 flex-col items-center">
                    <div className="flex h-44 items-end gap-2 sm:gap-3">
                        <div className="flex flex-col items-center gap-1">
                            <span className="text-[10px] font-bold text-slate-600">{row.achieved}%</span>
                            <div
                                className="w-7 rounded-t-md bg-gradient-to-t from-blue-800 to-blue-500 sm:w-10"
                                style={{ height: `${row.achieved * 1.3}px` }}
                            />
                        </div>
                        <div className="flex flex-col items-center gap-1">
                            <span className="text-[10px] font-bold text-cyan-700">{row.utilization}%</span>
                            <div
                                className="w-7 rounded-t-md bg-gradient-to-t from-cyan-700 to-cyan-400 sm:w-10"
                                style={{ height: `${row.utilization * 1.3}px` }}
                            />
                        </div>
                    </div>
                    <p className="mt-2 truncate text-center text-[10px] font-semibold text-slate-600 sm:text-xs">{row.company}</p>
                </div>
            ))}
        </div>
    );
}

function LostHoursChart() {
    const max = Math.max(...scorecard.map((row) => row.unused));

    return (
        <div className="mt-5 flex h-52 items-end justify-around gap-4" aria-label="Unused available line hours chart">
            {scorecard.map((row) => (
                <div key={row.company} className="flex min-w-0 flex-1 flex-col items-center">
                    <span className="mb-1 text-[10px] font-bold text-slate-600">{row.unused}</span>
                    <div
                        className="w-12 max-w-full rounded-t-md bg-gradient-to-t from-amber-600 to-orange-400 shadow-sm sm:w-16"
                        style={{ height: `${Math.max(12, (row.unused / max) * 145)}px` }}
                    />
                    <p className="mt-2 w-full truncate text-center text-[10px] font-semibold text-slate-600 sm:text-xs">{row.company}</p>
                </div>
            ))}
        </div>
    );
}

export default function ProductionCapacityDashboard() {
    const [period, setPeriod] = useState<Period>('day');
    const [company, setCompany] = useState('All companies');
    const [category, setCategory] = useState('All categories');
    const values = period === 'day' ? dayValues : monthValues;
    const visibleRows = useMemo(() => scorecard.filter((row) => company === 'All companies' || row.company === company), [company]);
    const categoryOptions = useMemo(() => [...new Set(categoryDetails.map((row) => row.category))], []);
    const visibleCategoryRows = useMemo(
        () =>
            categoryDetails.filter(
                (row) => (company === 'All companies' || row.company === company) && (category === 'All categories' || row.category === category),
            ),
        [category, company],
    );

    const kpis = [
        {
            label: 'Good pipe output',
            value: values.pipe,
            note: 'Finished, accepted output',
            icon: PackageCheck,
            tone: 'text-blue-700 bg-blue-50 ring-blue-100',
        },
        {
            label: 'Good tile output',
            value: values.tile,
            note: 'Finished, accepted output',
            icon: Factory,
            tone: 'text-cyan-700 bg-cyan-50 ring-cyan-100',
        },
        {
            label: 'Line-hour availability used',
            value: values.availability,
            note: 'Across operating lines',
            icon: Gauge,
            tone: 'text-violet-700 bg-violet-50 ring-violet-100',
        },
        {
            label: 'Companies below plan',
            value: values.belowPlan,
            note: 'Requires production attention',
            icon: AlertTriangle,
            tone: 'text-amber-700 bg-amber-50 ring-amber-100',
        },
    ];

    return (
        <AppLayout>
            <Head title="Production & Capacity" />
            <main className="mx-auto flex w-full max-w-[1680px] flex-1 flex-col gap-3 p-3 sm:p-4 md:p-5 lg:p-6">
                <ProjectPageHeader
                    eyebrow="Management view"
                    title="Production & capacity"
                    subtitle="Good output, plan achievement and lost capacity"
                    icon={<Factory className="size-4.5" />}
                    compact
                    actions={
                        <>
                            <label className="min-w-40 text-[9px] font-bold tracking-wider text-white/65 uppercase">
                                Company
                                <select
                                    value={company}
                                    onChange={(event) => setCompany(event.target.value)}
                                    className="mt-1 h-8 w-full rounded-md border border-white/20 bg-white px-2.5 text-xs font-semibold text-slate-800 outline-none focus:ring-2 focus:ring-orange-300"
                                >
                                    <option>All companies</option>
                                    {scorecard.map((row) => (
                                        <option key={row.company}>{row.company}</option>
                                    ))}
                                </select>
                            </label>
                            <div className="border-l border-white/15 pl-3 text-right">
                                <p className="text-xs font-bold">26 October 2026</p>
                                <p className="mt-0.5 text-[9px] text-white/60">Daily cut-off · 08:30 AM</p>
                                <p className="text-[9px] text-cyan-100">Reporting through 25 Oct</p>
                            </div>
                        </>
                    }
                />

                <section className="rounded-lg border border-blue-100 bg-blue-50/70 px-3 py-2 text-xs text-blue-900">
                    <span className="font-bold tracking-wide uppercase">Illustrative sample:</span> no live data connected. Pipe output is in tonnes;
                    tile output is in square metres, so physical totals remain separate.
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p className="text-[10px] font-bold tracking-[0.16em] text-slate-400 uppercase">Production period</p>
                            <h2 className="mt-0.5 text-base font-bold text-slate-900">Output at a glance</h2>
                        </div>
                        <div className="flex flex-wrap items-end gap-2">
                            <label className="text-[9px] font-bold tracking-wide text-slate-500 uppercase">
                                Product category
                                <select
                                    value={category}
                                    onChange={(event) => setCategory(event.target.value)}
                                    className="mt-1 block h-8 min-w-40 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-blue-300"
                                >
                                    <option>All categories</option>
                                    {categoryOptions.map((option) => (
                                        <option key={option}>{option}</option>
                                    ))}
                                </select>
                            </label>
                            <div className="flex rounded-lg bg-slate-100 p-1" role="group" aria-label="Production period">
                                <button
                                    onClick={() => setPeriod('day')}
                                    className={`rounded-md px-3 py-1.5 text-xs font-semibold transition ${period === 'day' ? 'bg-blue-700 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'}`}
                                >
                                    25 October
                                </button>
                                <button
                                    onClick={() => setPeriod('month')}
                                    className={`rounded-md px-3 py-1.5 text-xs font-semibold transition ${period === 'month' ? 'bg-blue-700 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'}`}
                                >
                                    1–25 October
                                </button>
                            </div>
                        </div>
                    </div>
                    <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {kpis.map(({ label, value, note, icon: Icon, tone }) => (
                            <article
                                key={label}
                                className="rounded-xl border border-slate-200 p-3.5 transition hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <div className={`grid size-8 place-items-center rounded-lg ring-1 ${tone}`}>
                                    <Icon className="size-4" />
                                </div>
                                <p className="mt-3 text-xs font-semibold text-slate-500">{label}</p>
                                <p className="mt-1 text-2xl font-bold tracking-tight text-slate-950">{value}</p>
                                <p className="mt-1 text-[10px] text-slate-400">{note}</p>
                            </article>
                        ))}
                    </div>
                </section>

                <section>
                    <div className="mb-2 flex items-center gap-2">
                        <TimerReset className="size-4 text-blue-700" />
                        <h2 className="text-base font-bold text-slate-900">Plan, capacity and lost time</h2>
                    </div>
                    <div className="grid gap-3 xl:grid-cols-2">
                        <article className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <h3 className="text-sm font-bold text-slate-900">Plan achievement and capacity utilization</h3>
                            <p className="mt-0.5 text-[10px] text-slate-400">Percentage by operating company</p>
                            <ComparisonChart />
                            <div className="mt-3 flex justify-center gap-5 text-[10px] font-semibold text-slate-500">
                                <span>
                                    <i className="mr-1 inline-block size-2 rounded-sm bg-blue-700" />
                                    Plan achieved
                                </span>
                                <span>
                                    <i className="mr-1 inline-block size-2 rounded-sm bg-cyan-500" />
                                    Capacity utilized
                                </span>
                            </div>
                        </article>
                        <article className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <h3 className="text-sm font-bold text-slate-900">Unused available line hours</h3>
                            <p className="mt-0.5 text-[10px] text-slate-400">Lost production opportunity by company</p>
                            <LostHoursChart />
                        </article>
                    </div>
                </section>

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
                        <div>
                            <h2 className="text-base font-bold text-slate-900">Production scorecard</h2>
                            <p className="text-[10px] text-slate-400">Output and process measures · 25 October 2026</p>
                        </div>
                        <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-500">
                            <CalendarDays className="size-3" />
                            Daily snapshot
                        </span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[900px] text-left text-xs">
                            <thead className="bg-slate-50 text-[10px] tracking-wide text-slate-500 uppercase">
                                <tr>
                                    {[
                                        'Company',
                                        'Output unit',
                                        'Good output',
                                        'Plan',
                                        'Plan achieved',
                                        'Utilisation',
                                        'Rejection',
                                        'Unused line hours',
                                        'kWh / output unit',
                                    ].map((heading) => (
                                        <th key={heading} className="px-4 py-2.5 font-bold">
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {visibleRows.map((row) => (
                                    <tr key={row.company} className="hover:bg-blue-50/40">
                                        <td className="px-4 py-3 font-bold text-slate-900">{row.company}</td>
                                        <td className="px-4 py-3 text-slate-500">{row.unit}</td>
                                        <td className="px-4 py-3 font-semibold">{row.output.toLocaleString()}</td>
                                        <td className="px-4 py-3">{row.plan.toLocaleString()}</td>
                                        <td className="px-4 py-3">
                                            <span className="rounded-full bg-blue-50 px-2 py-1 font-bold text-blue-700">{row.achieved}%</span>
                                        </td>
                                        <td className="px-4 py-3 font-semibold text-cyan-700">{row.utilization}%</td>
                                        <td className="px-4 py-3 text-amber-700">{row.rejection}%</td>
                                        <td className="px-4 py-3">{row.unused}</td>
                                        <td className="px-4 py-3">{row.energy}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p className="border-t border-slate-100 bg-slate-50/70 px-4 py-2.5 text-[10px] leading-4 text-slate-500">
                        Drill-down dimensions: plant, line, shift, product and production order. Downtime reasons and capacity definitions require
                        plant-owner validation before live integration.
                    </p>
                </section>

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                        <div className="flex items-center gap-2.5">
                            <span className="grid size-8 place-items-center rounded-lg bg-blue-50 text-blue-700 ring-1 ring-blue-100">
                                <Layers3 className="size-4" />
                            </span>
                            <div>
                                <h2 className="text-base font-bold text-slate-900">Category-wise production detail</h2>
                                <p className="text-[10px] text-slate-400">Plant and category performance · MTD and YTD</p>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2 text-[9px] font-semibold">
                            <span className="rounded-full bg-emerald-50 px-2 py-1 text-emerald-700">≥100% On/above plan</span>
                            <span className="rounded-full bg-blue-50 px-2 py-1 text-blue-700">90–99.9% Watch</span>
                            <span className="rounded-full bg-amber-50 px-2 py-1 text-amber-700">&lt;90% Attention</span>
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[960px] text-left text-xs">
                            <thead className="bg-slate-50 text-[10px] tracking-wide text-slate-500 uppercase">
                                <tr>
                                    {[
                                        'Company',
                                        'Plant',
                                        'Category',
                                        'Good output',
                                        'Production MTD',
                                        'Production YTD',
                                        'Capacity MTD',
                                        'Capacity YTD',
                                    ].map((heading) => (
                                        <th key={heading} className="px-4 py-2.5 font-bold">
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {visibleCategoryRows.map((row) => (
                                    <tr key={`${row.company}-${row.plant}-${row.category}`} className="hover:bg-blue-50/40">
                                        <td className="px-4 py-3 font-bold text-slate-900">{row.company}</td>
                                        <td className="px-4 py-3 text-slate-500">{row.plant}</td>
                                        <td className="px-4 py-3 font-semibold text-slate-700">{row.category}</td>
                                        <td className="px-4 py-3">
                                            {row.output.toLocaleString()} {row.unit}
                                        </td>
                                        {[row.productionMtd, row.productionYtd, row.capacityMtd, row.capacityYtd].map((value, index) => (
                                            <td key={index} className="px-4 py-3">
                                                <span
                                                    className={`inline-flex min-w-14 justify-center rounded-full px-2 py-1 font-bold ${achievementTone(value)}`}
                                                >
                                                    {value}%
                                                </span>
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {visibleCategoryRows.length === 0 && (
                        <p className="px-4 py-8 text-center text-xs text-slate-500">No category data matches the selected company.</p>
                    )}
                </section>
            </main>
        </AppLayout>
    );
}
