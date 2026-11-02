import InputError from '@/components/input-error';
import { MasterDialogHeader } from '@/components/master/master-dialog-header';
import { MasterPageHeader } from '@/components/master/master-page-header';
import { StatusToggle } from '@/components/master/status-toggle';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Boxes, Download, Eye, Pencil, Plus, Search, Trash2, UploadCloud } from 'lucide-react';
import { FormEventHandler, useCallback, useEffect, useState } from 'react';

type Module = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    version: string;
    source: string;
    original_filename: string;
    mime_type: string | null;
    file_size: number;
    checksum_sha256: string;
    manifest: { capabilities?: string[]; service_class?: string } | null;
    service_details: {
        health: { status: string; message: string };
        capabilities: string[];
        module_path: string;
        service_class: string;
        sections: string[];
    } | null;
    installed: boolean;
    status: boolean;
    display_locations: DisplayLocation[];
    uploads_count: number;
    created_at: string;
};
type Page = {
    data: Module[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};
type DisplayLocation = 'sidebar' | 'tabs' | 'dropdown';
type UploadForm = {
    name: string;
    slug: string;
    description: string;
    version: string;
    package: File | null;
    status: boolean;
    display_locations: DisplayLocation[];
};
type EditForm = { name: string; description: string; status: boolean; display_locations: DisplayLocation[] };
const blank: UploadForm = { name: '', slug: '', description: '', version: '1.0.0', package: null, status: true, display_locations: ['sidebar'] };
const displayLocationOptions: Array<{ value: DisplayLocation; label: string }> = [
    { value: 'sidebar', label: 'Sidebar' },
    { value: 'tabs', label: 'Tabs' },
    { value: 'dropdown', label: 'Dropdown' },
];
const displayLocations = (value: unknown): DisplayLocation[] => {
    if (!Array.isArray(value)) return ['sidebar'];

    const locations = value.filter((location): location is DisplayLocation =>
        displayLocationOptions.some((option) => option.value === location),
    );

    return locations.length ? locations : ['sidebar'];
};
const size = (bytes: number) =>
    bytes < 1024 ? `${bytes} B` : bytes < 1048576 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / 1048576).toFixed(1)} MB`;

export default function Modules({
    modules,
    filters,
    pageSizeOptions,
    stats,
    canManage,
}: {
    modules: Page;
    filters: { search: string; per_page: number };
    pageSizeOptions: number[];
    stats: { total: number; active: number };
    canManage: boolean;
}) {
    const [search, setSearch] = useState(filters.search),
        [uploadOpen, setUploadOpen] = useState(false),
        [viewing, setViewing] = useState<Module | null>(null),
        [editing, setEditing] = useState<Module | null>(null);
    const form = useForm<UploadForm>(blank);
    const editForm = useForm<EditForm>({ name: '', description: '', status: true, display_locations: ['sidebar'] });
    const toggleLocation = (locations: DisplayLocation[], location: DisplayLocation) =>
        locations.includes(location) ? locations.filter((item) => item !== location) : [...locations, location];
    const load = useCallback(
        (q = search, per = filters.per_page) =>
            router.get('/admin/modules', { search: q.trim(), per_page: per }, { preserveState: true, replace: true }),
        [filters.per_page, search],
    );
    useEffect(() => {
        if (search === filters.search) return;
        const timer = window.setTimeout(() => load(search), 400);
        return () => window.clearTimeout(timer);
    }, [search, filters.search, load]);
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post('/admin/modules', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setUploadOpen(false);
                form.reset();
            },
        });
    };
    const submitEdit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!editing) return;
        editForm.put(`/admin/modules/${editing.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };
    return (
        <AppLayout breadcrumbs={[{ title: 'Module Registry', href: '/admin/modules' }]}>
            <Head title="Module Registry" />
            <div className="mx-auto w-full max-w-[1680px] space-y-4 p-3 md:p-5 lg:p-6">
                <MasterPageHeader
                    title="Module Management"
                    description={`${stats.total} modules · ${stats.active} active`}
                    icon={<Boxes className="size-5" />}
                    action={
                        canManage && (
                            <Button
                                onClick={() => {
                                    form.setData(blank);
                                    form.clearErrors();
                                    setUploadOpen(true);
                                }}
                                className="h-9 bg-orange-500 px-4 font-semibold text-white hover:bg-orange-600"
                            >
                                <Plus className="size-4" /> Upload module
                            </Button>
                        )
                    }
                />
                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-4 border-b px-5 py-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-brand-plum text-[10px] font-bold tracking-[.16em] uppercase">Module registry</p>
                            <h2 className="font-bold text-slate-900">Available Modules</h2>
                            <p className="mt-0.5 text-xs text-slate-500">Built-in and uploaded application modules.</p>
                        </div>
                        <div className="relative">
                            <Search className="absolute top-3 left-3.5 size-4 text-slate-400" />
                            <Input
                                className="h-10 bg-slate-50 pl-10 md:w-80"
                                placeholder="Search modules..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[900px] text-left text-sm">
                            <thead className="bg-slate-50 text-[10px] tracking-wide text-slate-600 uppercase">
                                <tr>
                                    <th className="px-5 py-4">Module</th>
                                    <th className="px-5 py-4">Version</th>
                                    <th className="px-5 py-4">Source</th>
                                    <th className="px-5 py-4">Module Location</th>
                                    <th className="px-5 py-4">Display location</th>
                                    <th className="px-5 py-4">Status</th>
                                    <th className="px-5 py-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {modules.data.map((module) => (
                                    <tr key={module.id} className="hover:bg-slate-50">
                                        <td className="px-5 py-4">
                                            <div className="font-bold">{module.name}</div>
                                            <div className="mt-1 text-xs text-slate-500">{module.description || 'Application module.'}</div>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex flex-wrap gap-1.5">
                                                {displayLocations(module.display_locations).map((location) => (
                                                    <span
                                                        key={location}
                                                        className="rounded-full bg-cyan-50 px-2 py-1 text-[10px] font-bold text-cyan-700 capitalize"
                                                    >
                                                        {location}
                                                    </span>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-5 py-4">
                                            <span className="rounded-full bg-blue-50 px-2 py-1 text-[10px] font-bold text-blue-700">
                                                v{module.version}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            <span className="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-700">
                                                {module.source === 'service' ? 'Built-in' : 'Upload'}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="font-medium">
                                                {module.source === 'service' ? module.service_details?.module_path : module.original_filename}
                                            </div>
                                            <div className="text-xs text-slate-500">
                                                {module.source === 'service'
                                                    ? (module.service_details?.service_class ?? 'Built-in module')
                                                    : `${size(module.file_size)} · ${module.uploads_count} version${module.uploads_count === 1 ? '' : 's'}`}
                                            </div>
                                        </td>
                                        <td className="px-5 py-4">
                                            <StatusToggle
                                                active={module.status && module.installed}
                                                disabled={!canManage || !module.installed}
                                                label={module.name}
                                                onChange={() =>
                                                    router.patch(
                                                        `/admin/modules/${module.id}/status`,
                                                        { active: !module.status },
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            />
                                            {module.service_details?.health.status === 'missing' && (
                                                <p className="mt-1 text-xs font-semibold text-red-600">Module not present</p>
                                            )}
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex justify-end gap-1">
                                                <Button size="sm" variant="outline" onClick={() => setViewing(module)}>
                                                    <Eye className="size-4" /> View
                                                </Button>
                                                {canManage && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => {
                                                            editForm.setData({
                                                                name: module.name,
                                                                description: module.description ?? '',
                                                                status: module.status,
                                                                display_locations: displayLocations(module.display_locations),
                                                            });
                                                            editForm.clearErrors();
                                                            setEditing(module);
                                                        }}
                                                    >
                                                        <Pencil className="size-4" /> Edit
                                                    </Button>
                                                )}
                                                {canManage && module.source !== 'service' && (
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        className="text-red-500"
                                                        onClick={() =>
                                                            confirm(`Delete ${module.name} and all uploaded versions?`) &&
                                                            router.delete(`/admin/modules/${module.id}`, { preserveScroll: true })
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {!modules.data.length && (
                        <div className="p-12 text-center">
                            <UploadCloud className="mx-auto mb-3 size-8 text-slate-300" />
                            <p className="text-sm text-slate-500">No modules uploaded yet.</p>
                        </div>
                    )}
                    <div className="flex flex-col gap-3 border-t bg-slate-50 px-5 py-3 text-xs sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-2">
                            Show{' '}
                            <Select value={String(filters.per_page)} onValueChange={(v) => load(filters.search, Number(v))}>
                                <SelectTrigger className="h-8 w-20 bg-white">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {pageSizeOptions.map((n) => (
                                        <SelectItem key={n} value={String(n)}>
                                            {n}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>{' '}
                            entries · Showing {modules.from ?? 0}–{modules.to ?? 0} of {modules.total}
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={!modules.prev_page_url}
                                onClick={() => modules.prev_page_url && router.get(modules.prev_page_url)}
                            >
                                Previous
                            </Button>
                            <b>
                                Page {modules.current_page} of {modules.last_page}
                            </b>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={!modules.next_page_url}
                                onClick={() => modules.next_page_url && router.get(modules.next_page_url)}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                </section>
            </div>
            <Dialog open={uploadOpen} onOpenChange={setUploadOpen}>
                <DialogContent className="max-w-[720px] overflow-hidden rounded-2xl bg-white p-0 sm:p-0">
                    <form onSubmit={submit}>
                        <MasterDialogHeader title="Upload module" />
                        <div className="grid gap-4 p-6 sm:grid-cols-2">
                            <div>
                                <Input
                                    autoFocus
                                    placeholder="Module name *"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                />
                                <InputError message={form.errors.name} />
                            </div>
                            <div>
                                <Input
                                    placeholder="Slug (optional)"
                                    value={form.data.slug}
                                    onChange={(e) => form.setData('slug', e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '-'))}
                                />
                                <InputError message={form.errors.slug} />
                            </div>
                            <div>
                                <Input
                                    placeholder="Version, e.g. 1.0.0 *"
                                    value={form.data.version}
                                    onChange={(e) => form.setData('version', e.target.value)}
                                />
                                <InputError message={form.errors.version} />
                            </div>
                            <div>
                                <Input
                                    type="file"
                                    accept=".zip,application/zip"
                                    onChange={(e) => form.setData('package', e.target.files?.[0] ?? null)}
                                />
                                <InputError message={form.errors.package} />
                            </div>
                            <div className="sm:col-span-2">
                                <Textarea
                                    placeholder="Module description"
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                />
                                <InputError message={form.errors.description} />
                            </div>
                            <fieldset className="sm:col-span-2">
                                <legend className="mb-2 text-sm font-semibold text-slate-800">Display location *</legend>
                                <div className="grid gap-2 sm:grid-cols-3">
                                    {displayLocationOptions.map((option) => (
                                        <label
                                            key={option.value}
                                            className="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium hover:bg-slate-50"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={form.data.display_locations.includes(option.value)}
                                                onChange={() =>
                                                    form.setData('display_locations', toggleLocation(form.data.display_locations, option.value))
                                                }
                                                className="size-4 accent-cyan-700"
                                            />
                                            {option.label}
                                        </label>
                                    ))}
                                </div>
                                <InputError message={form.errors.display_locations} />
                            </fieldset>
                            <p className="text-xs text-slate-500 sm:col-span-2">
                                Upload a ZIP package up to 100 MB. Include an optional module.json file in the ZIP root for manifest metadata.
                            </p>
                        </div>
                        <DialogFooter className="border-t bg-slate-50 px-6 py-4">
                            <Button type="button" variant="outline" onClick={() => setUploadOpen(false)}>
                                Cancel
                            </Button>
                            <Button disabled={form.processing} className="bg-brand-teal">
                                {form.processing ? 'Uploading…' : 'Upload module'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog open={!!editing} onOpenChange={(open) => !open && setEditing(null)}>
                <DialogContent className="max-w-[680px] overflow-hidden rounded-2xl bg-white p-0 sm:p-0">
                    {editing && (
                        <form onSubmit={submitEdit}>
                            <MasterDialogHeader title={`Edit ${editing.name}`} />
                            <div className="grid gap-4 p-6">
                                <div>
                                    <Input value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} />
                                    <InputError message={editForm.errors.name} />
                                </div>
                                <div>
                                    <Textarea
                                        value={editForm.data.description}
                                        onChange={(e) => editForm.setData('description', e.target.value)}
                                        placeholder="Module description"
                                    />
                                    <InputError message={editForm.errors.description} />
                                </div>
                                <fieldset>
                                    <legend className="mb-2 text-sm font-semibold text-slate-800">Display location *</legend>
                                    <div className="grid gap-2 sm:grid-cols-3">
                                        {displayLocationOptions.map((option) => (
                                            <label
                                                key={option.value}
                                                className="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium hover:bg-slate-50"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={editForm.data.display_locations.includes(option.value)}
                                                    onChange={() =>
                                                        editForm.setData(
                                                            'display_locations',
                                                            toggleLocation(editForm.data.display_locations, option.value),
                                                        )
                                                    }
                                                    className="size-4 accent-cyan-700"
                                                />
                                                {option.label}
                                            </label>
                                        ))}
                                    </div>
                                    <InputError message={editForm.errors.display_locations} />
                                </fieldset>
                            </div>
                            <DialogFooter className="border-t bg-slate-50 px-6 py-4">
                                <Button type="button" variant="outline" onClick={() => setEditing(null)}>
                                    Cancel
                                </Button>
                                <Button disabled={editForm.processing || editForm.data.display_locations.length === 0} className="bg-brand-teal">
                                    Save changes
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
            <Dialog open={!!viewing} onOpenChange={(open) => !open && setViewing(null)}>
                <DialogContent className="max-w-[680px] overflow-hidden rounded-2xl bg-white p-0 sm:p-0">
                    {viewing && (
                        <>
                            <MasterDialogHeader title={viewing.name} />
                            <div className="grid gap-4 p-6 text-sm sm:grid-cols-2">
                                <div>
                                    <p className="text-xs text-slate-500">Version</p>
                                    <b>v{viewing.version}</b>
                                </div>
                                <div>
                                    <p className="text-xs text-slate-500">Source</p>
                                    <b>{viewing.source === 'service' ? 'Built-in module' : 'Uploaded package'}</b>
                                </div>
                                <div>
                                    <p className="text-xs text-slate-500">Slug</p>
                                    <b>{viewing.slug}</b>
                                </div>
                                <div>
                                    <p className="text-xs text-slate-500">Health</p>
                                    <b
                                        className={
                                            viewing.service_details?.health.status === 'missing'
                                                ? 'text-red-600 capitalize'
                                                : 'text-emerald-700 capitalize'
                                        }
                                    >
                                        {viewing.service_details?.health.status ?? 'Stored'}
                                    </b>
                                </div>
                                <div className="sm:col-span-2">
                                    <p className="text-xs text-slate-500">Description</p>
                                    <p>{viewing.description || 'No description.'}</p>
                                </div>
                                {viewing.service_details && (
                                    <>
                                        <div className="sm:col-span-2">
                                            <p className="text-xs text-slate-500">Module folder</p>
                                            <code className="text-brand-teal font-semibold">{viewing.service_details.module_path}</code>
                                        </div>
                                        <div className="sm:col-span-2">
                                            <p className="mb-2 text-xs text-slate-500">Structure</p>
                                            <div className="flex flex-wrap gap-2">
                                                {viewing.service_details.sections.map((item) => (
                                                    <span
                                                        key={item}
                                                        className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700"
                                                    >
                                                        {item}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    </>
                                )}
                                {viewing.service_details && (
                                    <div className="sm:col-span-2">
                                        <p className="mb-2 text-xs text-slate-500">Capabilities</p>
                                        <div className="flex flex-wrap gap-2">
                                            {viewing.service_details.capabilities.map((item) => (
                                                <span key={item} className="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold text-cyan-800">
                                                    {item}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                            {viewing.source !== 'service' && (
                                <DialogFooter className="border-t bg-slate-50 px-6 py-4">
                                    <Button asChild className="bg-brand-teal">
                                        <a href={`/admin/modules/${viewing.id}/download`}>
                                            <Download className="size-4" /> Download package
                                        </a>
                                    </Button>
                                </DialogFooter>
                            )}
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
