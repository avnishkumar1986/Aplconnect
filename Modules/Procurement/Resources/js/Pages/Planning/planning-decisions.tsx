import ProcurementPageHeader from '@/components/procurement-page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, CalendarRange, Check, Factory, LockKeyhole, LockOpen, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

type Company = { id: number; company_code: string; company_name: string };
type Plant = { id: number; company_id: number; company_code: string; company_name: string };
type Selection = { plant_id: number; material: string };
type Decision = {
    id: number | null;
    plant_id: number;
    company_name: string;
    company_code: string;
    plant_name: string;
    plant_code: string;
    material: string;
    material_name: string | null;
    planning_month: string | null;
    planning_mode: 'auto' | 'manual' | null;
    locked_at: string | null;
};
type Page<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const formatPlanningDate = (value: string) => {
    const match = /^(\d{4})-(\d{2})(?:-(\d{2}))?/.exec(value);
    if (!match) return value;

    return `${match[3] || '01'}-${match[2]}-${match[1].slice(-2)}`;
};

export default function PlanningDecisions({
    companies,
    plants,
    decisions,
    filters,
    canManage,
    canLock,
    canUnlock,
}: {
    companies: Company[];
    plants: Plant[];
    decisions: Page<Decision>;
    filters: { month: string; company_id: number | null; plant_id: number | null; material: string | null; search: string };
    canManage: boolean;
    canLock: boolean;
    canUnlock: boolean;
}) {
    const [bulkAction, setBulkAction] = useState<'auto' | 'manual' | 'lock' | 'unlock'>('auto');
    const [actionError, setActionError] = useState<string | null>(null);
    const form = useForm({
        company_id: filters.company_id ? String(filters.company_id) : '',
        plant_id: filters.plant_id ? String(filters.plant_id) : 'all',
        selections: [] as Selection[],
        planning_month: filters.month,
        planning_mode: 'auto' as 'auto' | 'manual',
        search: filters.search,
    });
    useEffect(() => {
        const refreshDecisions = () => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['decisions'], preserveScroll: true, preserveState: true });
            }
        };
        const timer = window.setInterval(refreshDecisions, 5000);
        const refreshWhenVisible = () => {
            if (document.visibilityState === 'visible') refreshDecisions();
        };

        window.addEventListener('focus', refreshDecisions);
        document.addEventListener('visibilitychange', refreshWhenVisible);

        return () => {
            window.clearInterval(timer);
            window.removeEventListener('focus', refreshDecisions);
            document.removeEventListener('visibilitychange', refreshWhenVisible);
        };
    }, []);
    const availablePlants = useMemo(() => plants.filter((item) => String(item.company_id) === form.data.company_id), [plants, form.data.company_id]);
    const applyBulk = () =>
        form.post('/admin/procurement/planning-decisions', { preserveScroll: true, onSuccess: () => form.setData('selections', []) });
    const updateBulkLock = (action: 'lock-all' | 'unlock-all') =>
        router.patch(
            `/admin/procurement/planning-decisions/${action}`,
            {
                company_id: form.data.company_id,
                planning_month: form.data.planning_month,
                selections: form.data.selections,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setActionError(null),
                onError: (errors) => setActionError(String(errors.lock ?? 'The selected decisions could not be updated.')),
                onSuccess: () => {
                    setActionError(null);
                    form.setData('selections', []);
                },
            },
        );
    const applySelectedAction = () => {
        if (bulkAction === 'lock') {
            if (confirm('Lock the selected planning decisions?')) updateBulkLock('lock-all');
            return;
        }
        if (bulkAction === 'unlock') {
            if (confirm('Unlock the selected planning decisions?')) updateBulkLock('unlock-all');
            return;
        }

        form.setData('planning_mode', bulkAction);
        form.transform((data) => ({ ...data, planning_mode: bulkAction }));
        applyBulk();
    };
    const setRowDecision = (item: Decision, mode: 'auto' | 'manual') =>
        router.post(
            '/admin/procurement/planning-decisions',
            {
                company_id: form.data.company_id,
                selections: [{ plant_id: item.plant_id, material: item.material }],
                planning_month: form.data.planning_month,
                planning_mode: mode,
            },
            { preserveScroll: true },
        );
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Procurement', href: '/admin/procurement' },
                { title: 'Planning Decisions', href: '/admin/procurement/planning-decisions' },
            ]}
        >
            <Head title="Planning Decisions" />
            <main className="procurement-compact w-full min-w-0 space-y-3 p-2 sm:p-3">
                <ProcurementPageHeader
                    title="Planning Decisions"
                    icon={CalendarRange}
                    compact
                    actions={
                        <div className="grid w-full gap-2 sm:grid-cols-2 xl:w-[min(74vw,1120px)] xl:grid-cols-[minmax(210px,1fr)_minmax(210px,1fr)_minmax(360px,1.65fr)] xl:items-end">
                            <label className="space-y-1 text-[10px] font-bold text-cyan-50">
                                Company
                                <Select
                                    value={form.data.company_id}
                                    onValueChange={(value) =>
                                        router.get(
                                            '/admin/procurement/planning-decisions',
                                            { company_id: value, month: form.data.planning_month },
                                            { preserveState: false, replace: true },
                                        )
                                    }
                                >
                                    <SelectTrigger className="h-10 w-full rounded-lg border-white/30 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm transition focus:ring-2 focus:ring-cyan-300/60">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {companies.map((item) => (
                                            <SelectItem key={item.id} value={String(item.id)}>
                                                {item.company_code} - {item.company_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </label>
                            <label className="space-y-1 text-[10px] font-bold text-cyan-50">
                                Plant
                                <Select
                                    value={form.data.plant_id}
                                    onValueChange={(value) =>
                                        router.get(
                                            '/admin/procurement/planning-decisions',
                                            { company_id: form.data.company_id, plant_id: value, month: form.data.planning_month },
                                            { preserveState: false, replace: true },
                                        )
                                    }
                                >
                                    <SelectTrigger className="h-10 w-full rounded-lg border-white/30 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm transition focus:ring-2 focus:ring-cyan-300/60">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Plants</SelectItem>
                                        {availablePlants.map((item) => (
                                            <SelectItem key={item.id} value={String(item.id)}>
                                                {item.company_code} - {item.company_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </label>
                            <form
                                className="space-y-1 sm:col-span-2 xl:col-span-1"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    router.get(
                                        '/admin/procurement/planning-decisions',
                                        {
                                            company_id: form.data.company_id,
                                            plant_id: form.data.plant_id,
                                            month: form.data.planning_month,
                                            search: form.data.search || undefined,
                                        },
                                        { preserveState: true, replace: true },
                                    );
                                }}
                            >
                                <span className="block text-[10px] font-bold text-cyan-50">Material</span>
                                <div className="flex gap-2">
                                    <div className="relative min-w-0 flex-1">
                                        <Search className="absolute top-3 left-3 size-4 text-slate-400" />
                                        <Input
                                            className="h-10 rounded-lg border-white/30 bg-white pl-9 text-sm text-slate-800 shadow-sm transition focus-visible:ring-2 focus-visible:ring-cyan-300/60"
                                            value={form.data.search}
                                            onChange={(event) => form.setData('search', event.target.value)}
                                            placeholder="Search material"
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        className="h-10 shrink-0 gap-1.5 rounded-lg bg-cyan-500 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-400 hover:shadow-md"
                                    >
                                        <Search className="size-3.5" /> Search
                                    </Button>
                                </div>
                            </form>
                        </div>
                    }
                />

                {actionError && (
                    <div role="alert" className="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600" />
                        <div className="min-w-0 flex-1">
                            <p className="font-bold">Unable to update planning decision</p>
                            <p className="mt-0.5 text-xs text-amber-800">{actionError}</p>
                        </div>
                        <button type="button" onClick={() => setActionError(null)} className="text-xs font-bold text-amber-700 hover:text-amber-950">
                            Dismiss
                        </button>
                    </div>
                )}

                <section className="overflow-hidden rounded-2xl border bg-white shadow-sm">
                    {form.data.selections.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2 border-b bg-slate-50 px-4 py-2.5">
                            <>
                                <Select value={bulkAction} onValueChange={(value: 'auto' | 'manual' | 'lock' | 'unlock') => setBulkAction(value)}>
                                    <SelectTrigger className="h-8 w-36 bg-white text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="auto">Set Auto</SelectItem>
                                        <SelectItem value="manual">Set Manual</SelectItem>
                                        {canLock && <SelectItem value="lock">Lock selected</SelectItem>}
                                        {canUnlock && <SelectItem value="unlock">Unlock selected</SelectItem>}
                                    </SelectContent>
                                </Select>
                                <Button
                                    size="sm"
                                    className="h-8 gap-1 rounded-md bg-cyan-600 px-2.5 text-xs text-white hover:bg-cyan-500"
                                    onClick={applySelectedAction}
                                    disabled={
                                        form.processing ||
                                        ((bulkAction === 'auto' || bulkAction === 'manual') && !canManage) ||
                                        (bulkAction === 'lock' && !canLock) ||
                                        (bulkAction === 'unlock' && !canUnlock)
                                    }
                                >
                                    <Check className="size-3.5" /> Apply ({form.data.selections.length})
                                </Button>
                                <Button size="sm" variant="ghost" className="h-8 text-xs" onClick={() => form.setData('selections', [])}>
                                    Clear selection
                                </Button>
                            </>
                            <span className="ml-auto text-xs text-slate-500">{decisions.total.toLocaleString()} materials</span>
                        </div>
                    )}
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[900px] text-sm">
                            <thead className="bg-slate-50 text-left text-[10px] text-slate-500 uppercase">
                                <tr>
                                    <th className="w-12 px-4 py-3">
                                        <Checkbox
                                            aria-label="Select all materials on this page"
                                            checked={
                                                decisions.data.some((item) => canUnlock || !item.locked_at) &&
                                                decisions.data
                                                    .filter((item) => canUnlock || !item.locked_at)
                                                    .every((item) =>
                                                        form.data.selections.some(
                                                            (selected) => selected.plant_id === item.plant_id && selected.material === item.material,
                                                        ),
                                                    )
                                            }
                                            onCheckedChange={(checked) => {
                                                const pageSelections = decisions.data
                                                    .filter((item) => canUnlock || !item.locked_at)
                                                    .map((item) => ({ plant_id: item.plant_id, material: item.material }));
                                                form.setData(
                                                    'selections',
                                                    checked
                                                        ? [
                                                              ...form.data.selections,
                                                              ...pageSelections.filter(
                                                                  (candidate) =>
                                                                      !form.data.selections.some(
                                                                          (selected) =>
                                                                              selected.plant_id === candidate.plant_id &&
                                                                              selected.material === candidate.material,
                                                                      ),
                                                              ),
                                                          ]
                                                        : form.data.selections.filter(
                                                              (selected) =>
                                                                  !pageSelections.some(
                                                                      (candidate) =>
                                                                          candidate.plant_id === selected.plant_id &&
                                                                          candidate.material === selected.material,
                                                                  ),
                                                          ),
                                                );
                                            }}
                                        />
                                    </th>
                                    <th className="w-16 px-3 py-3 text-right">S.No.</th>
                                    <th className="px-4 py-3">Company</th>
                                    <th className="px-4 py-3">Plant</th>
                                    <th className="px-4 py-3">Material</th>
                                    <th className="px-4 py-3">Date</th>
                                    <th className="px-4 py-3">Decision</th>
                                    <th className="px-4 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {decisions.data.map((item, index) => (
                                    <tr key={`${item.plant_id}-${item.material}`} className="hover:bg-cyan-50/40">
                                        <td className="px-4 py-3">
                                            <Checkbox
                                                aria-label={`Select ${item.material}`}
                                                disabled={Boolean(item.locked_at) && !canUnlock}
                                                checked={form.data.selections.some(
                                                    (selected) => selected.plant_id === item.plant_id && selected.material === item.material,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    form.setData(
                                                        'selections',
                                                        checked
                                                            ? [...form.data.selections, { plant_id: item.plant_id, material: item.material }]
                                                            : form.data.selections.filter(
                                                                  (selected) =>
                                                                      selected.plant_id !== item.plant_id || selected.material !== item.material,
                                                              ),
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-3 text-right text-slate-500">{(decisions.from ?? 1) + index}</td>
                                        <td className="px-4 py-3 font-semibold">
                                            {item.company_code} · {item.company_name}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="inline-flex items-center gap-2">
                                                <Factory className="size-4 text-slate-400" />
                                                {item.plant_code} · {item.plant_name}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <b className="font-mono">{item.material}</b>
                                            <div className="text-xs text-slate-500">{item.material_name}</div>
                                        </td>
                                        <td className="px-4 py-3">{formatPlanningDate(item.planning_month || filters.month)}</td>
                                        <td className="px-4 py-3">
                                            <div
                                                className={`inline-flex rounded-lg border p-0.5 ${item.locked_at ? 'bg-slate-100 opacity-70' : 'bg-white'}`}
                                            >
                                                {(['auto', 'manual'] as const).map((mode) => (
                                                    <button
                                                        key={mode}
                                                        type="button"
                                                        disabled={!canManage || Boolean(item.locked_at)}
                                                        onClick={() => setRowDecision(item, mode)}
                                                        className={`min-w-14 rounded-md px-2 py-1 text-[11px] font-bold capitalize transition ${(item.planning_mode ?? 'auto') === mode ? (mode === 'auto' ? 'bg-cyan-600 text-white shadow-sm' : 'bg-amber-500 text-white shadow-sm') : 'text-slate-500 hover:bg-slate-50'} disabled:cursor-not-allowed`}
                                                    >
                                                        {mode}
                                                    </button>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {canLock && item.id && !item.locked_at && (
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    className="text-slate-500 hover:bg-amber-50 hover:text-amber-700"
                                                    onClick={() =>
                                                        confirm('Lock this planning decision? It cannot be changed afterward.') &&
                                                        router.patch(
                                                            `/admin/procurement/planning-decisions/${item.id}/lock`,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                                onStart: () => setActionError(null),
                                                                onError: (errors) => setActionError(String(errors.lock ?? 'This planning decision could not be locked.')),
                                                            },
                                                        )
                                                    }
                                                    title="Lock decision"
                                                    aria-label={`Lock ${item.material} planning decision`}
                                                >
                                                    <LockKeyhole className="size-4" />
                                                </Button>
                                            )}
                                            {item.locked_at && !canUnlock && (
                                                <span className="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700">
                                                    <LockKeyhole className="size-3.5" /> Locked
                                                </span>
                                            )}
                                            {item.locked_at && canUnlock && item.id && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="h-8 gap-1 px-2 text-[10px] font-bold text-emerald-700 hover:bg-emerald-50"
                                                    onClick={() =>
                                                        router.patch(
                                                            `/admin/procurement/planning-decisions/${item.id}/unlock`,
                                                            {},
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                    title="Unlock decision"
                                                >
                                                    <LockOpen className="size-3.5" /> Unlock
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {decisions.data.length === 0 && (
                                    <tr>
                                        <td colSpan={8} className="px-6 py-14 text-center text-slate-500">
                                            No materials found for this company.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex items-center justify-between border-t bg-slate-50 px-4 py-3 text-xs text-slate-600">
                        <span>
                            Page {decisions.current_page} of {decisions.last_page}
                        </span>
                        <div className="flex gap-2">
                            {decisions.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={decisions.prev_page_url}>Previous</Link>
                                </Button>
                            )}
                            {decisions.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={decisions.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                </section>
            </main>
        </AppLayout>
    );
}
