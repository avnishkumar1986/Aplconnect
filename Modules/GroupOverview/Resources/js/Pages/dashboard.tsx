import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import {
    Banknote,
    BriefcaseBusiness,
    Building2,
    ChartPie,
    CircleAlert,
    CircleDollarSign,
    Factory,
    IndianRupee,
    Landmark,
    ShieldCheck,
    ShoppingCart,
    TrendingUp,
    Truck,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { DataTable, type DataTableColumn } from '../Components/data-table';
import { DashboardPanel, DepartmentCard, MetricCard, SectionHeading } from '../Components/group-overview-cards';
import { LineTrendChart, PlanComparisonChart } from '../Components/overview-charts';

const kpis = [
    {
        label: 'Yesterday sales',
        icon: IndianRupee,
        value: '₹ 0 Cr',
        note: 'Previous day net sales',
        trend: 'up',
    },
    {
        label: 'Yesterday collection',
        icon: TrendingUp,
        value: '₹ 0 Cr',
        note: 'Previous day collections',
        trend: 'up',
    },
    {
        label: 'Net sales (MTD)',
        icon: IndianRupee,
        value: '₹ 137.2 Cr',
        note: '₹8.4 Cr ahead of plan',
        trend: 'up',
    },
    {
        label: 'Collections (MTD)',
        icon: TrendingUp,
        value: '₹ 131.8 Cr',
        note: '96.1% of net sales',
        trend: 'up',
    },
    {
        label: 'Total receivables',
        value: '₹ 0 Cr',
        note: 'Current outstanding amount',
        trend: 'neutral',
        icon: CircleDollarSign,
    },
    {
        label: 'Net sales (YTD)',
        icon: IndianRupee,
        value: '₹ 1,284.6 Cr',
        note: '6.8% growth vs prior year',
        trend: 'up',
    },
    {
        label: 'Collections (YTD)',
        icon: TrendingUp,
        value: '₹ 1,219.3 Cr',
        note: '94.9% of net sales',
        trend: 'up',
    },
    {
        label: 'Receivables > 60 days',
        value: '₹ 21.5 Cr',
        note: '6.2% of total receivables',
        trend: 'down',
        icon: CircleAlert,
    },
    {
        label: 'Inventory value',
        value: '₹ 122.6 Cr',
        note: '28 days of cover',
        trend: 'neutral',
        icon: Building2,
    },
] as const;

type CompanyRow = {
    code: string;
    company: string;
    mtdSales: number;
    monthlyTarget: number;
    monthlyAchievement: number | null;
    mtdCollections: number;
    overdue: number;
    activeCustomers: number;
};
const formatScorecardAmount = (valueInCrores: number, unit: 'Lk' | 'Cr') => {
    const value = unit === 'Lk' ? valueInCrores * 100 : valueInCrores;

    return `₹${value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${unit}`;
};

const companyColumns = (unit: 'Lk' | 'Cr'): DataTableColumn<CompanyRow>[] => [
    {
        key: 'company',
        label: 'Company',
        value: (row) => row.company,
        render: (row) => <span className="font-bold">{row.company}</span>,
    },
    {
        key: 'mtdSales',
        label: 'MTD sales',
        value: (row) => row.mtdSales,
        render: (row) => formatScorecardAmount(row.mtdSales, unit),
    },
    {
        key: 'monthlyTarget',
        label: 'Monthly target',
        value: (row) => row.monthlyTarget,
        render: (row) => formatScorecardAmount(row.monthlyTarget, unit),
    },
    {
        key: 'plan',
        label: 'Monthly achieved',
        value: (row) => row.monthlyAchievement ?? -1,
        render: (row) => (
            <span className="text-brand-teal font-bold">
                {row.monthlyAchievement === null
                    ? '—'
                    : `${row.monthlyAchievement.toLocaleString('en-IN', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`}
            </span>
        ),
    },
    {
        key: 'mtdCollections',
        label: 'MTD collections',
        value: (row) => row.mtdCollections,
        render: (row) => formatScorecardAmount(row.mtdCollections, unit),
    },
    {
        key: 'overdue',
        label: 'Receivables > 60 days',
        value: (row) => row.overdue,
        render: (row) => formatScorecardAmount(row.overdue, unit),
    },
    {
        key: 'activeCustomers',
        label: 'Active customers',
        value: (row) => row.activeCustomers,
        render: (row) => <span className="font-bold">{row.activeCustomers.toLocaleString('en-IN')}</span>,
    },
];

const departments = [
    {
        name: 'Sales & distribution',
        metric: '93.0% of plan',
        detail: '₹137.2 Cr MTD · strongest region North',
        icon: TrendingUp,
    },
    {
        name: 'Production & capacity',
        metric: 'Apollo 91.1% · Kisan 83.9% · Trico 89.9%',
        detail: 'Capacity gain led by optimized production plan',
        icon: Factory,
    },
    {
        name: 'Finance & treasury',
        metric: '₹177.5 Cr debt',
        detail: '₹24.7 Cr uninvoiced exposure',
        icon: Landmark,
    },
    {
        name: 'Receivables & credit',
        metric: '₹95.8 Cr outstanding',
        detail: 'Overdue exposure remains within guardrail',
        icon: Banknote,
    },
    {
        name: 'Costing & profitability',
        metric: '14.7% contribution',
        detail: 'Margin expansion is progressing',
        icon: ChartPie,
    },
    {
        name: 'Purchasing',
        metric: '4 materials below cover',
        detail: 'Two materials require action this week',
        icon: ShoppingCart,
    },
    {
        name: 'Inventory & logistics',
        metric: '97.1% delivery proof',
        detail: '12 files are awaiting POD closure',
        icon: Truck,
    },
    {
        name: 'Quality & service',
        metric: '6 complaints overdue',
        detail: 'Customer response plan active',
        icon: ShieldCheck,
    },
    {
        name: 'People & productivity',
        metric: '92.2% attendance',
        detail: 'Daily absentee risk trending lower',
        icon: Users,
    },
    {
        name: 'Projects & investments',
        metric: '₹25.5 Cr committed',
        detail: 'Capital program is on approved budget',
        icon: BriefcaseBusiness,
    },
    {
        name: 'Investor & strategy',
        metric: '6.5% net margin',
        detail: 'Ahead of the current board plan',
        icon: CircleDollarSign,
    },
];

function formatAmount(value: number, unit: 'Lk' | 'Cr'): string {
    const convertedValue = value / (unit === 'Cr' ? 10_000_000 : 100_000);

    return `₹\u00A0${convertedValue.toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}\u00A0${unit}`;
}

export default function GroupOverview({
    netSales = { mtd: 0, ytd: 0, yesterday: 0 },
    collections = { mtd: 0, ytd: 0, yesterday: 0 },
    receivablesOverdue = 0,
    totalReceivables = 0,
    inventory = { value: 0, zrawValue: 0, yrawValue: 0, daysOfCover: null },
    performance = { year: new Date().getFullYear(), yearlyTarget: 0, targetFiscalYear: '', monthlyTrend: [], dailyTrend: [], planComparison: [] },
    companyScorecard = [],
    salesMeta = { salesDateTime: null, collectionsDateTime: null, receivablesDateTime: null, inventoryDateTime: null, apiSyncDateTime: null, salesDataDate: null, yesterdaySalesDate: null, collectionsDataDate: null, yesterdayCollectionsDate: null, receivablesDataDate: null, inventoryDataDate: null },
    companies: companyOptions = [],
    filters = { company: null },
}: {
    netSales?: { mtd: number; ytd: number; yesterday: number };
    collections?: { mtd: number; ytd: number; yesterday: number };
    receivablesOverdue?: number;
    totalReceivables?: number;
    inventory?: { value: number; zrawValue: number; yrawValue: number; daysOfCover: number | null };
    performance?: {
        year: number;
        yearlyTarget: number;
        targetFiscalYear: string;
        monthlyTrend: Array<{ date: string; sales: number | null; collections: number | null; target: number }>;
        dailyTrend: Array<{ date: string; sales: number | null; collections: number | null; target: number }>;
        planComparison: Array<{ name: string; actual: number; plan: number }>;
    };
    companyScorecard?: CompanyRow[];
    salesMeta?: {
        salesDateTime: string | null;
        collectionsDateTime: string | null;
        receivablesDateTime: string | null;
        inventoryDateTime: string | null;
        apiSyncDateTime: string | null;
        salesDataDate: string | null;
        yesterdaySalesDate: string | null;
        collectionsDataDate: string | null;
        yesterdayCollectionsDate: string | null;
        receivablesDataDate: string | null;
        inventoryDataDate: string | null;
    };
    companies?: { code: string; name: string }[];
    filters?: { company: string | null };
}) {
    useEffect(() => {
        const refreshNetSales = () => {
            if (document.visibilityState === 'visible') {
                router.reload({
                    only: [
                        'netSales',
                        'collections',
                        'receivablesOverdue',
                        'totalReceivables',
                        'inventory',
                        'salesMeta',
                        'performance',
                        'companyScorecard',
                    ],
                    preserveScroll: true,
                    preserveState: true,
                });
            }
        };
        const interval = window.setInterval(refreshNetSales, 60_000);

        return () => window.clearInterval(interval);
    }, []);

    const selectedCompany = filters.company ?? '';
    const [displayUnit, setDisplayUnit] = useState<'Lk' | 'Cr'>('Cr');
    const selectedCompanyName = companyOptions.find(({ code }) => code === selectedCompany)?.name ?? 'All companies';
    const salesScopeNote = selectedCompany ? selectedCompanyName : 'All companies combined';
    const liveKpis = kpis.map((kpi) => {
        if (kpi.label === 'Net sales (MTD)' || kpi.label === 'Net sales (YTD)') {
            return {
                ...kpi,
                value: formatAmount(kpi.label.endsWith('(MTD)') ? netSales.mtd : netSales.ytd, displayUnit),
                note: salesScopeNote,
                asOf: salesMeta.salesDataDate,
                lastSync: salesMeta.salesDateTime,
                trend: 'up' as const,
            };
        }

        if (kpi.label === 'Collections (MTD)' || kpi.label === 'Collections (YTD)') {
            return {
                ...kpi,
                value: formatAmount(kpi.label.endsWith('(MTD)') ? collections.mtd : collections.ytd, displayUnit),
                note: selectedCompanyName,
                asOf: salesMeta.collectionsDataDate,
                lastSync: salesMeta.collectionsDateTime,
                trend: 'up' as const,
            };
        }

        if (kpi.label === 'Yesterday collection') {
            return {
                ...kpi,
                value: formatAmount(collections.yesterday, displayUnit),
                note: `${selectedCompanyName} — Previous day collections`,
                asOf: salesMeta.yesterdayCollectionsDate,
                lastSync: salesMeta.collectionsDateTime,
                trend: 'up' as const,
            };
        }

        if (kpi.label === 'Receivables > 60 days') {
            return {
                ...kpi,
                value: formatAmount(receivablesOverdue, displayUnit),
                note: `${selectedCompanyName} — Outstanding for more than 60 days`,
                asOf: salesMeta.receivablesDataDate,
                lastSync: salesMeta.receivablesDateTime,
            };
        }

        if (kpi.label === 'Yesterday sales') {
            return {
                ...kpi,
                value: formatAmount(netSales.yesterday, displayUnit),
                note: `${selectedCompanyName} — Previous day net sales`,
                asOf: salesMeta.yesterdaySalesDate,
                lastSync: salesMeta.salesDateTime,
            };
        }

        if (kpi.label === 'Total receivables') {
            return {
                ...kpi,
                value: formatAmount(totalReceivables, displayUnit),
                note: `${selectedCompanyName} — Current outstanding amount`,
                asOf: salesMeta.receivablesDataDate,
                lastSync: salesMeta.receivablesDateTime,
            };
        }

        if (kpi.label === 'Inventory value') {
            return {
                ...kpi,
                value: formatAmount(inventory.value, displayUnit),
                note:
                    inventory.daysOfCover === null
                        ? `ZRAW ${formatAmount(inventory.zrawValue, displayUnit)} · YRAW ${formatAmount(inventory.yrawValue, displayUnit)}\n${selectedCompanyName} — Cover unavailable`
                        : `ZRAW ${formatAmount(inventory.zrawValue, displayUnit)} · YRAW ${formatAmount(inventory.yrawValue, displayUnit)}\n${inventory.daysOfCover.toLocaleString('en-IN', { maximumFractionDigits: 1 })} days of cover`,
                asOf: salesMeta.inventoryDataDate,
                lastSync: salesMeta.inventoryDateTime,
            };
        }

        return kpi;
    });
    const salesKpis = liveKpis.filter((kpi) => kpi.label.toLowerCase().includes('sales'));
    const collectionKpis = liveKpis.filter((kpi) => kpi.label.toLowerCase().includes('collection'));
    const operationalKpis = liveKpis.filter(
        (kpi) => !kpi.label.toLowerCase().includes('sales') && !kpi.label.toLowerCase().includes('collection'),
    );

    return (
        <AppLayout>
            <Head title="Group Overview" />
            <div className="bg-background text-foreground min-h-full px-3 py-4 sm:px-5 sm:py-5 lg:px-7 lg:py-6">
                <main className="mx-auto w-full max-w-[1480px] space-y-5 sm:space-y-6">
                    <header className="relative flex flex-col justify-between gap-3 overflow-hidden rounded-2xl bg-[linear-gradient(115deg,#023047_0%,#075b6d_58%,#219ebc_100%)] px-5 py-4 text-white shadow-[0_18px_45px_-24px_rgba(2,48,71,0.9)] sm:px-7 lg:flex-row lg:items-center">
                        <div className="absolute inset-0 bg-[linear-gradient(rgba(142,202,230,0.08)_1px,transparent_1px),linear-gradient(90deg,rgba(142,202,230,0.08)_1px,transparent_1px)] bg-[size:28px_28px]" />
                        <div className="border-brand-gold/20 absolute -top-20 right-[38%] size-52 rounded-full border-[22px]" />
                        <div className="relative z-10">
                            <p className="text-brand-gold mb-1 text-[10px] font-bold tracking-[0.18em] uppercase">Management cockpit</p>
                            <h1 className="text-2xl font-black tracking-[-0.035em] sm:text-[2rem]">Group overview</h1>
                        </div>
                        <div className="relative z-10 flex w-full gap-3 rounded-xl border border-white/15 bg-white/[0.08] p-3 backdrop-blur-sm lg:w-auto">
                            <label className="grid gap-1.5 text-[10px] font-bold tracking-wide text-white/65 uppercase" htmlFor="company-filter">
                                Company
                                <select
                                    id="company-filter"
                                    value={selectedCompany}
                                    onChange={(event) => {
                                        const company = event.target.value;

                                        router.post('/admin/group-overview/company-filter', { company }, {
                                            only: [
                                                'filters',
                                                'netSales',
                                                'collections',
                                                'receivablesOverdue',
                                                'totalReceivables',
                                                'inventory',
                                                'salesMeta',
                                                'performance',
                                                'companyScorecard',
                                            ],
                                            preserveScroll: true,
                                            preserveState: false,
                                            replace: true,
                                        });
                                    }}
                                    className="text-brand-plum focus:ring-brand-gold/40 h-9 min-w-48 rounded-lg border border-white/30 bg-white px-3 text-xs font-bold shadow-sm transition outline-none focus:ring-2"
                                >
                                    <option value="">All companies</option>
                                    {companyOptions.map((company) => (
                                        <option key={company.code} value={company.code}>
                                            {company.name} ({company.code})
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="grid gap-1.5 text-[10px] font-bold tracking-wide text-white/65 uppercase" htmlFor="unit-filter">
                                Display unit
                                <select
                                    id="unit-filter"
                                    value={displayUnit}
                                    onChange={(event) => setDisplayUnit(event.target.value as 'Lk' | 'Cr')}
                                    className="text-brand-plum focus:ring-brand-gold/40 h-9 min-w-32 rounded-lg border border-white/30 bg-white px-3 text-xs font-bold shadow-sm transition outline-none focus:ring-2"
                                >
                                    <option value="Cr">Crore (Cr)</option>
                                    <option value="Lk">Lakh (Lk)</option>
                                </select>
                            </label>
                        </div>
                    </header>

                    <section aria-label="Key performance indicators" className="space-y-4">
                        <div className="space-y-2">
                            <p className="text-muted-foreground text-[10px] font-extrabold tracking-[0.12em] uppercase">Sales performance</p>
                            <div className="grid grid-cols-1 gap-3 min-[460px]:grid-cols-2 lg:grid-cols-3">
                                {salesKpis.map((kpi) => (
                                    <MetricCard key={kpi.label} {...kpi} />
                                ))}
                            </div>
                        </div>
                        <div className="space-y-2">
                            <p className="text-muted-foreground text-[10px] font-extrabold tracking-[0.12em] uppercase">Collections performance</p>
                            <div className="grid grid-cols-1 gap-3 min-[460px]:grid-cols-2 lg:grid-cols-3">
                                {collectionKpis.map((kpi) => (
                                    <MetricCard key={kpi.label} {...kpi} />
                                ))}
                            </div>
                        </div>
                        <div className="space-y-2">
                            <p className="text-muted-foreground text-[10px] font-extrabold tracking-[0.12em] uppercase">Working capital & actions</p>
                            <div className="grid grid-cols-1 gap-3 min-[460px]:grid-cols-2 lg:grid-cols-3">
                                {operationalKpis.map((kpi) => (
                                    <MetricCard key={kpi.label} {...kpi} />
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="space-y-3">
                        <SectionHeading>Performance at a glance</SectionHeading>
                        <div className="grid gap-4 xl:grid-cols-2">
                            <DashboardPanel
                                title="Daily sales, collections and target · crore"
                                meta={`${selectedCompanyName} · ${new Date().toLocaleString('en-IN', { month: 'long', year: 'numeric' })}`}
                                metaClassName="text-foreground text-base font-black tracking-tight"
                            >
                                <LineTrendChart
                                    labels={performance.dailyTrend.map(({ date }) => date)}
                                    sales={performance.dailyTrend.map(({ sales }) => sales)}
                                    collections={performance.dailyTrend.map(({ collections }) => collections)}
                                    target={performance.dailyTrend.map(({ target }) => target)}
                                />
                            </DashboardPanel>
                            <DashboardPanel title={`Month-to-date sales versus target · ${displayUnit === 'Lk' ? 'lakh' : 'crore'}`} meta="Actual / Target">
                                <PlanComparisonChart rows={performance.planComparison} unit={displayUnit} />
                            </DashboardPanel>
                        </div>
                    </section>

                    <section className="space-y-3">
                        <SectionHeading>Company scorecard</SectionHeading>
                        <div className="border-border bg-card overflow-hidden rounded-2xl border shadow-[0_10px_30px_-22px_rgba(2,48,71,0.45)]">
                            <div className="border-border border-b px-4 py-3">
                                <h3 className="text-xs font-bold">
                                    Operating companies <span className="text-muted-foreground ml-1 font-normal">· {selectedCompanyName}</span>
                                </h3>
                            </div>
                            <DataTable
                                rows={companyScorecard}
                                columns={companyColumns(displayUnit)}
                                searchPlaceholder="Search companies"
                                initialPageSize={5}
                            />
                        </div>
                    </section>

                    <section className="space-y-3 pb-6">
                        <SectionHeading>Department snapshot</SectionHeading>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {departments.map((department) => (
                                <DepartmentCard key={department.name} {...department} />
                            ))}
                        </div>
                    </section>
                </main>
            </div>
        </AppLayout>
    );
}
