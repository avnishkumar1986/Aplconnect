import InputError from '@/components/input-error';
import { MasterDialogHeader } from '@/components/master/master-dialog-header';
import { MasterPageHeader } from '@/components/master/master-page-header';
import { StatusToggle } from '@/components/master/status-toggle';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Building2, Check, ChevronDown, ChevronLeft, ChevronRight, Edit3, MapPin, Phone, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

type Company = {
    id: number;
    company_code: string;
    company_name: string;
    record_type: number;
    parent_company_id: string | null;
    address_id: number | null;
    contact_id: number | null;
    gstin: string | null;
    pan: string | null;
    status: boolean;
    created_at: string;
    address: Address | null;
    contact: Contact | null;
};
type Address = {
    address_line_1: string;
    address_line_2: string | null;
    city: string | null;
    state: string | null;
    district: string | null;
    postal_code: string | null;
    country: string | null;
    latitude: number | null;
    longitude: number | null;
    google_maps_url: string | null;
};
type Contact = { email: string | null; contact_number: string | null };
type Page = {
    data: Company[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};
type Form = {
    company_code: string;
    company_name: string;
    usr_type: string;
    parent_company_id: string;
    gstin: string;
    pan: string;
    status: boolean;
    address_line_1: string;
    address_line_2: string;
    city: string;
    state: string;
    district: string;
    postal_code: string;
    country: string;
    latitude: string;
    longitude: string;
    google_maps_url: string;
    email: string;
    contact_number: string;
};
const blank: Form = {
    company_code: '',
    company_name: '',
    usr_type: 'USR10',
    parent_company_id: '',
    gstin: '',
    pan: '',
    status: true,
    address_line_1: '',
    address_line_2: '',
    city: '',
    state: '',
    district: '',
    postal_code: '',
    country: 'India',
    latitude: '',
    longitude: '',
    google_maps_url: '',
    email: '',
    contact_number: '',
};
const types: Record<number, string> = { 1: 'Company', 2: 'Plant', 3: 'Head Office' };
const formatAddress = (address: Address | null) =>
    address
        ? [
              address.address_line_1,
              address.address_line_2,
              address.city,
              address.district,
              address.state,
              address.postal_code,
              address.country,
          ]
              .filter(Boolean)
              .join(', ')
        : '—';
const wizardSteps = [
    { label: 'Company', icon: Building2 },
    { label: 'Address', icon: MapPin },
    { label: 'Contact', icon: Phone },
];

export default function Companies({
    companies,
    parentCompanies,
    filters,
    pageSizeOptions,
    stats,
    canManage,
}: {
    companies: Page;
    parentCompanies: { company_code: string; company_name: string }[];
    filters: { search: string; per_page: number };
    pageSizeOptions: number[];
    stats: { total: number; active: number };
    canManage: boolean;
}) {
    const parentCompanyNames = new Map(parentCompanies.map((company) => [company.company_code, company.company_name]));
    const [search, setSearch] = useState(filters.search),
        [open, setOpen] = useState(false),
        [step, setStep] = useState(0),
        [addressTab, setAddressTab] = useState<'address' | 'map'>('address'),
        [geocoding, setGeocoding] = useState(false),
        [geocodeError, setGeocodeError] = useState(''),
        [editing, setEditing] = useState<Company | null>(null),
        [selected, setSelected] = useState<number[]>([]),
        [action, setAction] = useState('');
    const form = useForm<Form>(blank);
    const mapPlaceQuery = [
        form.data.company_name,
        form.data.address_line_1,
        form.data.address_line_2,
        form.data.city,
        form.data.district,
        form.data.state,
        form.data.postal_code,
        form.data.country,
    ]
        .filter(Boolean)
        .join(', ');
    const mapQuery = mapPlaceQuery || [form.data.latitude, form.data.longitude].filter(Boolean).join(',');
    const storedEmbedUrl = form.data.google_maps_url.includes('/maps/embed?') ? form.data.google_maps_url : '';
    const mapPreviewUrl = storedEmbedUrl || `https://maps.google.com/maps?output=embed&z=17&q=${encodeURIComponent(mapQuery)}`;
    const load = (q = search, per = filters.per_page) =>
        router.get('/admin/companies', { search: q, per_page: per }, { preserveState: true, replace: true });
    const create = () => {
        setEditing(null);
        form.setData(blank);
        form.clearErrors();
        setStep(0);
        setAddressTab('address');
        setGeocodeError('');
        setOpen(true);
    };
    const edit = (c: Company) => {
        setEditing(c);
        form.setData({
            company_code: c.company_code,
            company_name: c.company_name,
            usr_type: c.record_type === 2 ? 'USR9' : c.record_type === 3 ? 'USR11' : 'USR10',
            parent_company_id: c.parent_company_id ?? '',
            gstin: c.gstin ?? '',
            pan: c.pan ?? '',
            status: c.status,
            address_line_1: c.address?.address_line_1 ?? '',
            address_line_2: c.address?.address_line_2 ?? '',
            city: c.address?.city ?? '',
            state: c.address?.state ?? '',
            district: c.address?.district ?? '',
            postal_code: c.address?.postal_code ?? '',
            country: c.address?.country ?? 'India',
            latitude: c.address?.latitude == null ? '' : String(c.address.latitude),
            longitude: c.address?.longitude == null ? '' : String(c.address.longitude),
            google_maps_url: c.address?.google_maps_url ?? '',
            email: c.contact?.email ?? '',
            contact_number: c.contact?.contact_number ?? '',
        });
        form.clearErrors();
        setStep(0);
        setAddressTab('address');
        setGeocodeError('');
        setOpen(true);
    };
    const submit = () => {
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onError: (errors: Partial<Record<keyof Form, string>>) => {
                const fields = Object.keys(errors) as Array<keyof Form>;
                const companyFields: Array<keyof Form> = [
                    'company_code',
                    'company_name',
                    'usr_type',
                    'parent_company_id',
                    'gstin',
                    'pan',
                    'status',
                ];
                const addressFields: Array<keyof Form> = [
                    'address_line_1',
                    'address_line_2',
                    'city',
                    'state',
                    'district',
                    'postal_code',
                    'country',
                    'latitude',
                    'longitude',
                    'google_maps_url',
                ];

                if (fields.some((field) => companyFields.includes(field))) setStep(0);
                else if (fields.some((field) => addressFields.includes(field))) {
                    setStep(1);
                    setAddressTab('address');
                } else setStep(2);
            },
        };
        if (editing) form.put(`/admin/companies/${editing.id}`, options);
        else form.post('/admin/companies', options);
    };
    const next = () => {
        form.clearErrors();
        if (step === 0) {
            if (!form.data.company_code.trim()) form.setError('company_code', 'Company code is required.');
            if (!form.data.company_name.trim()) form.setError('company_name', 'Company name is required.');
            if (!form.data.company_code.trim() || !form.data.company_name.trim()) return;
        }
        setStep((current) => Math.min(2, current + 1));
    };
    const fetchLocation = async () => {
        const uniqueParts = (parts: string[]) =>
            parts
                .map((part) => part.trim())
                .filter((part, index, values) => part && values.findIndex((value) => value.toLowerCase() === part.toLowerCase()) === index)
                .join(', ');
        const queries = [
            uniqueParts([
                form.data.address_line_1,
                form.data.address_line_2,
                form.data.city,
                form.data.district,
                form.data.state,
                form.data.postal_code,
                form.data.country,
            ]),
            uniqueParts([form.data.city, form.data.district, form.data.state, form.data.country]),
            uniqueParts([form.data.city, form.data.state, form.data.country]),
            uniqueParts([form.data.postal_code, form.data.state, form.data.country]),
            uniqueParts([form.data.postal_code, form.data.country]),
        ].filter((query, index, values) => query && values.indexOf(query) === index);
        if (!queries.length) {
            setGeocodeError('Complete the address before fetching its location.');
            return;
        }
        setGeocoding(true);
        setGeocodeError('');
        try {
            let location: { lat: string; lon: string } | undefined;
            for (const [index, query] of queries.entries()) {
                if (index > 0) await new Promise((resolve) => window.setTimeout(resolve, 1000));
                const response = await fetch(
                    `https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=in&q=${encodeURIComponent(query)}`,
                    { headers: { 'Accept-Language': 'en' } },
                );
                if (!response.ok) throw new Error('Location service is unavailable.');
                const result = (await response.json()) as Array<{ lat: string; lon: string }>;
                if (result[0]) {
                    location = result[0];
                    break;
                }
            }
            if (!location) {
                setGeocodeError('No map location was found for this address.');
                return;
            }
            form.setData({
                ...form.data,
                latitude: location.lat,
                longitude: location.lon,
                google_maps_url: `https://www.google.com/maps/place//@${location.lat},${location.lon},17z`,
            });
            setAddressTab('map');
        } catch {
            setGeocodeError('Unable to fetch the location. Please try again.');
        } finally {
            setGeocoding(false);
        }
    };
    const fetchAddressFromMapLink = async () => {
        const source = form.data.google_maps_url.replace(/&amp;/g, '&');
        const embeddedSource = source.match(/src=["'](https?:\/\/[^"']+\/maps\/embed\?[^"']+)["']/i)?.[1];
        const directMatch =
            source.match(/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/) ?? source.match(/[?&](?:q|query)=(-?\d+(?:\.\d+)?)(?:,|%2C)(-?\d+(?:\.\d+)?)/i);
        const embedLongitudeFirst = source.match(/!2d(-?\d+(?:\.\d+)?).*?!3d(-?\d+(?:\.\d+)?)/);
        const embedLatitudeFirst = source.match(/!3d(-?\d+(?:\.\d+)?).*?!2d(-?\d+(?:\.\d+)?)/);
        const latitude = directMatch?.[1] ?? embedLongitudeFirst?.[2] ?? embedLatitudeFirst?.[1];
        const longitude = directMatch?.[2] ?? embedLongitudeFirst?.[1] ?? embedLatitudeFirst?.[2];
        if (!latitude || !longitude) {
            setGeocodeError('Paste a Google Maps link containing latitude and longitude.');
            return;
        }
        const coordinateUrl = `https://www.google.com/maps/place//@${latitude},${longitude},17z`;
        const storedMapUrl = embeddedSource ?? (source.includes('/maps/embed?') ? source.trim() : coordinateUrl);
        setGeocoding(true);
        setGeocodeError('');
        try {
            const response = await fetch(
                `https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=18&addressdetails=1&lat=${encodeURIComponent(latitude)}&lon=${encodeURIComponent(longitude)}`,
                { headers: { 'Accept-Language': 'en' } },
            );
            if (!response.ok) throw new Error('Reverse geocoding failed.');
            const result = (await response.json()) as {
                address?: {
                    house_number?: string;
                    road?: string;
                    neighbourhood?: string;
                    suburb?: string;
                    city?: string;
                    town?: string;
                    village?: string;
                    county?: string;
                    state_district?: string;
                    state?: string;
                    postcode?: string;
                    country?: string;
                };
            };
            const address = result.address ?? {};
            const city = address.city ?? address.town ?? address.village ?? address.suburb ?? '';
            const district = address.state_district ?? address.county ?? '';
            const line1 = [address.house_number, address.road].filter(Boolean).join(' ') || address.neighbourhood || address.suburb || city;
            const line2 = [address.neighbourhood, address.suburb]
                .filter((value, index, values) => value && value !== line1 && values.indexOf(value) === index)
                .join(', ');
            form.setData({
                ...form.data,
                latitude,
                longitude,
                google_maps_url: storedMapUrl,
                address_line_1: line1 || form.data.address_line_1,
                address_line_2: line2 || form.data.address_line_2,
                city: city || form.data.city,
                district: district || form.data.district,
                state: address.state || form.data.state,
                postal_code: address.postcode || form.data.postal_code,
                country: address.country || form.data.country,
            });
        } catch {
            form.setData({ ...form.data, latitude, longitude, google_maps_url: storedMapUrl });
            setGeocodeError('Coordinates loaded, but the address could not be fetched.');
        } finally {
            setGeocoding(false);
        }
    };
    const bulk = () => {
        if (!action || !selected.length) return;
        if (action === 'delete' && !confirm('Delete selected companies?')) return;
        router.patch('/admin/companies/bulk', { ids: selected, action }, { preserveScroll: true, onSuccess: () => setSelected([]) });
    };
    return (
        <AppLayout breadcrumbs={[{ title: 'Company Master', href: '/admin/companies' }]}>
            <Head title="Company Master" />
            <div className="mx-auto w-full max-w-[1680px] space-y-4 p-3 md:p-5 lg:p-6">
                <MasterPageHeader
                    title="Company Master"
                    description={`${stats.total} companies · ${stats.active} active`}
                    action={
                        canManage ? (
                            <Button onClick={create} className="bg-brand-teal">
                                <Plus className="size-4" /> Add company
                            </Button>
                        ) : undefined
                    }
                />
                <section className="overflow-hidden rounded-xl border bg-white shadow-sm">
                    <div className="flex flex-col gap-3 border-b px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="font-bold">Companies</h2>
                            <p className="text-xs text-slate-500">Manage companies, plants and head offices.</p>
                        </div>
                        <div className="relative">
                            <Search className="absolute top-2.5 left-3 size-4 text-slate-400" />
                            <Input
                                className="pl-9 sm:w-80"
                                placeholder="Search companies"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && load()}
                            />
                        </div>
                    </div>
                    {selected.length > 0 && (
                        <div className="flex items-center gap-3 border-b bg-cyan-50 px-5 py-2">
                            <b>{selected.length} selected</b>
                            <Select value={action} onValueChange={setAction}>
                                <SelectTrigger className="h-8 w-40 bg-white">
                                    <SelectValue placeholder="Bulk action" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="activate">Activate</SelectItem>
                                    <SelectItem value="deactivate">Deactivate</SelectItem>
                                    <SelectItem value="delete">Delete</SelectItem>
                                </SelectContent>
                            </Select>
                            <Button size="sm" onClick={bulk}>
                                Apply
                            </Button>
                        </div>
                    )}
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1100px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500 uppercase">
                                <tr>
                                    <th className="px-4 py-3">
                                        <Checkbox
                                            checked={companies.data.length > 0 && companies.data.every((c) => selected.includes(c.id))}
                                            onCheckedChange={(v) => setSelected(v ? companies.data.map((c) => c.id) : [])}
                                        />
                                    </th>
                                    <th className="px-4 py-3">S.No.</th>
                                    <th className="px-4 py-3">Code</th>
                                    <th className="px-4 py-3">Company</th>
                                    <th className="px-4 py-3">Type</th>
                                    <th className="px-4 py-3">Parent</th>
                                    <th className="px-4 py-3">Address</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {companies.data.map((c, index) => (
                                    <tr key={c.id} className="hover:bg-cyan-50/30">
                                        <td className="px-4 py-3">
                                            <Checkbox
                                                checked={selected.includes(c.id)}
                                                onCheckedChange={(v) => setSelected(v ? [...selected, c.id] : selected.filter((id) => id !== c.id))}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-xs text-slate-500">
                                            {(companies.current_page - 1) * companies.per_page + index + 1}
                                        </td>
                                        <td className="px-4 py-3 font-mono font-bold">{c.company_code}</td>
                                        <td className="px-4 py-3 font-semibold">{c.company_name}</td>
                                        <td className="px-4 py-3">{types[c.record_type]}</td>
                                        <td className="px-4 py-3">
                                            {c.parent_company_id ? parentCompanyNames.get(c.parent_company_id) || c.parent_company_id : '—'}
                                        </td>
                                        <td className="max-w-md px-4 py-3 text-xs leading-5 text-slate-600" title={formatAddress(c.address)}>
                                            {formatAddress(c.address)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusToggle
                                                active={c.status}
                                                disabled={!canManage}
                                                label={c.company_name}
                                                onChange={() =>
                                                    router.patch(`/admin/companies/${c.id}/status`, { active: !c.status }, { preserveScroll: true })
                                                }
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-1">
                                                {canManage && (
                                                    <>
                                                        <Button size="icon" variant="ghost" onClick={() => edit(c)}>
                                                            <Edit3 className="size-4" />
                                                        </Button>
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            className="text-red-500"
                                                            onClick={() =>
                                                                confirm(`Delete ${c.company_name}?`) && router.delete(`/admin/companies/${c.id}`)
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
                            </tbody>
                        </table>
                    </div>
                    {!companies.data.length && <div className="p-10 text-center text-sm text-slate-500">No companies found.</div>}
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
                            entries · {companies.from ?? 0}–{companies.to ?? 0} of {companies.total}
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={!companies.prev_page_url}
                                onClick={() => companies.prev_page_url && router.get(companies.prev_page_url)}
                            >
                                Previous
                            </Button>
                            <b>
                                Page {companies.current_page} of {companies.last_page}
                            </b>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={!companies.next_page_url}
                                onClick={() => companies.next_page_url && router.get(companies.next_page_url)}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                </section>
            </div>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-w-[760px] overflow-hidden rounded-2xl bg-white p-0 sm:p-0 [&>button:last-child]:top-5 [&>button:last-child]:right-5 [&>button:last-child]:z-50 [&>button:last-child]:grid [&>button:last-child]:size-8 [&>button:last-child]:place-items-center [&>button:last-child]:rounded-full [&>button:last-child]:bg-white/15 [&>button:last-child]:text-white [&>button:last-child]:opacity-100 [&>button:last-child]:shadow-sm [&>button:last-child]:hover:bg-white/25">
                    <form onSubmit={(event) => event.preventDefault()}>
                        <MasterDialogHeader title={editing ? 'Edit company' : 'Create company'} />
                        <div className="border-b bg-slate-50/80 px-4 py-3 sm:px-7">
                            <ol className="grid grid-cols-3" aria-label="Company setup progress">
                                {wizardSteps.map(({ label, icon: Icon }, index) => {
                                    const completed = index < step;
                                    const active = index === step;
                                    return (
                                        <li key={label} className="relative">
                                            {index < wizardSteps.length - 1 && (
                                                <div
                                                    className="absolute top-[18px] left-[calc(50%+1.5rem)] h-0.5 w-[calc(100%-3rem)] bg-slate-200"
                                                    aria-hidden="true"
                                                >
                                                    <div
                                                        className={`h-full bg-emerald-500 transition-all duration-300 ${completed ? 'w-full' : 'w-0'}`}
                                                    />
                                                </div>
                                            )}
                                            <button
                                                type="button"
                                                disabled={index > step}
                                                onClick={() => index <= step && setStep(index)}
                                                className="group relative z-10 flex w-full flex-col items-center text-center disabled:cursor-default"
                                                aria-current={active ? 'step' : undefined}
                                            >
                                                <span
                                                    className={`flex size-9 items-center justify-center rounded-full border-2 shadow-sm transition-all duration-200 ${
                                                        completed
                                                            ? 'border-emerald-500 bg-emerald-500 text-white'
                                                            : active
                                                              ? 'border-brand-teal bg-brand-teal text-white ring-4 ring-cyan-100'
                                                              : 'border-slate-200 bg-white text-slate-400'
                                                    }`}
                                                >
                                                    {completed ? <Check className="size-5" strokeWidth={2.5} /> : <Icon className="size-4.5" />}
                                                </span>
                                                <span
                                                    className={`mt-1.5 text-xs font-bold sm:text-sm ${active ? 'text-slate-900' : completed ? 'text-emerald-700' : 'text-slate-500'}`}
                                                >
                                                    {label}
                                                </span>
                                            </button>
                                        </li>
                                    );
                                })}
                            </ol>
                        </div>
                        <div className="max-h-[65vh] overflow-y-auto px-5 py-5">
                            {step === 0 && (
                                <fieldset className="form-fieldset">
                                    <legend>Company information</legend>
                                    <div className="grid gap-3 py-3 sm:grid-cols-2">
                                        <div>
                                            <Input
                                                autoFocus
                                                placeholder="Company code"
                                                value={form.data.company_code}
                                                onChange={(e) => form.setData('company_code', e.target.value.toUpperCase())}
                                            />
                                            <InputError message={form.errors.company_code} />
                                        </div>
                                        <div>
                                            <Input
                                                placeholder="Company name"
                                                value={form.data.company_name}
                                                onChange={(e) => form.setData('company_name', e.target.value)}
                                            />
                                            <InputError message={form.errors.company_name} />
                                        </div>
                                        <div>
                                            <Select value={form.data.usr_type} onValueChange={(v) => form.setData('usr_type', v)}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="USR10">Company</SelectItem>
                                                    <SelectItem value="USR9">Plant</SelectItem>
                                                    <SelectItem value="USR11">Head Office</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError message={form.errors.usr_type} />
                                        </div>
                                        <div>
                                            <Select
                                                value={form.data.parent_company_id || undefined}
                                                onValueChange={(v) => form.setData('parent_company_id', v)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Parent company (optional)" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {parentCompanies
                                                        .filter((p) => p.company_code !== editing?.company_code)
                                                        .map((p) => (
                                                            <SelectItem key={p.company_code} value={p.company_code}>
                                                                {p.company_name}
                                                            </SelectItem>
                                                        ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={form.errors.parent_company_id} />
                                        </div>
                                        <div>
                                            <Input
                                                placeholder="GSTIN (15 characters)"
                                                maxLength={15}
                                                value={form.data.gstin}
                                                onChange={(e) => form.setData('gstin', e.target.value.toUpperCase())}
                                            />
                                            <InputError message={form.errors.gstin} />
                                        </div>
                                        <div>
                                            <Input
                                                placeholder="PAN (10 characters)"
                                                maxLength={10}
                                                value={form.data.pan}
                                                onChange={(e) => form.setData('pan', e.target.value.toUpperCase())}
                                            />
                                            <InputError message={form.errors.pan} />
                                        </div>
                                    </div>
                                </fieldset>
                            )}
                            {step === 1 && (
                                <fieldset className="form-fieldset">
                                    <legend>Primary address</legend>
                                    <div className="mt-2 flex rounded-lg bg-slate-100 p-1" role="tablist" aria-label="Address details">
                                        {(['address', 'map'] as const).map((tab) => (
                                            <button
                                                key={tab}
                                                type="button"
                                                role="tab"
                                                aria-selected={addressTab === tab}
                                                onClick={() => setAddressTab(tab)}
                                                className={`flex-1 rounded-md px-4 py-2 text-sm font-semibold capitalize transition ${addressTab === tab ? 'text-brand-teal bg-white shadow-sm' : 'text-slate-500 hover:text-slate-800'}`}
                                            >
                                                {tab}
                                            </button>
                                        ))}
                                    </div>
                                    {addressTab === 'address' && (
                                        <div className="grid gap-3 py-3 sm:grid-cols-2">
                                            <div className="sm:col-span-2">
                                                <Input
                                                    autoFocus
                                                    placeholder="Address line 1 (optional)"
                                                    value={form.data.address_line_1}
                                                    onChange={(e) => form.setData('address_line_1', e.target.value)}
                                                />
                                                <InputError message={form.errors.address_line_1} />
                                            </div>
                                            <div className="sm:col-span-2">
                                                <Input
                                                    placeholder="Address line 2 (optional)"
                                                    value={form.data.address_line_2}
                                                    onChange={(e) => form.setData('address_line_2', e.target.value)}
                                                />
                                                <InputError message={form.errors.address_line_2} />
                                            </div>
                                            <div>
                                                <AsyncLocationSelect
                                                    type="state"
                                                    placeholder="Select state (optional)"
                                                    value={form.data.state}
                                                    onValueChange={(value) =>
                                                        form.setData({ ...form.data, state: value, district: '', city: '', postal_code: '' })
                                                    }
                                                />
                                                <InputError message={form.errors.state} />
                                            </div>
                                            <div>
                                                <AsyncLocationSelect
                                                    type="district"
                                                    state={form.data.state}
                                                    placeholder="Select district (optional)"
                                                    value={form.data.district}
                                                    onValueChange={(value) =>
                                                        form.setData({ ...form.data, district: value, city: '', postal_code: '' })
                                                    }
                                                />
                                                <InputError message={form.errors.district} />
                                            </div>
                                            <div>
                                                <AsyncLocationSelect
                                                    type="city"
                                                    state={form.data.state}
                                                    placeholder="Select city (optional)"
                                                    value={form.data.city}
                                                    onValueChange={(value) => form.setData({ ...form.data, city: value, postal_code: '' })}
                                                />
                                                <InputError message={form.errors.city} />
                                            </div>
                                            <div>
                                                <AsyncLocationSelect
                                                    type="pincode"
                                                    city={form.data.city}
                                                    placeholder="Select postal code (optional)"
                                                    value={form.data.postal_code}
                                                    onValueChange={(value) => form.setData('postal_code', value)}
                                                />
                                                <InputError message={form.errors.postal_code} />
                                            </div>
                                            <div>
                                                <Input
                                                    placeholder="Country (optional)"
                                                    value={form.data.country}
                                                    onChange={(e) => form.setData('country', e.target.value)}
                                                />
                                                <InputError message={form.errors.country} />
                                            </div>
                                            <div className="flex flex-col items-start justify-end gap-1">
                                                <Button type="button" variant="outline" disabled={geocoding} onClick={() => void fetchLocation()}>
                                                    <MapPin className="size-4" /> {geocoding ? 'Fetching location…' : 'Fetch location & map'}
                                                </Button>
                                                {geocodeError && <p className="text-xs text-red-600">{geocodeError}</p>}
                                            </div>
                                        </div>
                                    )}
                                    {addressTab === 'map' && (
                                        <div className="grid gap-3 py-3 sm:grid-cols-2">
                                            <div className="flex flex-col gap-2 sm:col-span-2 sm:flex-row">
                                                <Input
                                                    className="flex-1"
                                                    placeholder="Paste Google Maps URL or embed HTML"
                                                    value={form.data.google_maps_url}
                                                    onChange={(event) => form.setData('google_maps_url', event.target.value)}
                                                />
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={geocoding}
                                                    onClick={() => void fetchAddressFromMapLink()}
                                                >
                                                    <MapPin className="size-4" /> {geocoding ? 'Fetching address…' : 'Fetch address & show'}
                                                </Button>
                                            </div>
                                            {geocodeError && <p className="text-xs text-red-600 sm:col-span-2">{geocodeError}</p>}
                                            <InputError message={form.errors.google_maps_url} />
                                            <div>
                                                <Input
                                                    type="number"
                                                    step="any"
                                                    placeholder="Latitude (optional)"
                                                    value={form.data.latitude}
                                                    onChange={(e) => form.setData('latitude', e.target.value)}
                                                />
                                                <InputError message={form.errors.latitude} />
                                            </div>
                                            <div>
                                                <Input
                                                    type="number"
                                                    step="any"
                                                    placeholder="Longitude (optional)"
                                                    value={form.data.longitude}
                                                    onChange={(e) => form.setData('longitude', e.target.value)}
                                                />
                                                <InputError message={form.errors.longitude} />
                                            </div>
                                            <div className="overflow-hidden rounded-xl border bg-slate-50 sm:col-span-2">
                                                {form.data.latitude && form.data.longitude ? (
                                                    <div>
                                                        <iframe
                                                            key={`${mapQuery}:${form.data.latitude},${form.data.longitude}`}
                                                            title="Company location map"
                                                            className="h-72 w-full border-0"
                                                            loading="lazy"
                                                            allowFullScreen
                                                            referrerPolicy="no-referrer-when-downgrade"
                                                            src={mapPreviewUrl}
                                                        />
                                                        <a
                                                            className="text-brand-teal flex items-center justify-center gap-2 border-t bg-white px-4 py-2 text-sm font-semibold hover:underline"
                                                            href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(mapQuery)}`}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            <MapPin className="size-4" /> Open in Google Maps
                                                        </a>
                                                    </div>
                                                ) : (
                                                    <div className="flex h-72 items-center justify-center px-6 text-center text-sm text-slate-500">
                                                        Enter latitude and longitude to preview the company location.
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </fieldset>
                            )}
                            {step === 2 && (
                                <fieldset className="form-fieldset">
                                    <legend>Primary contact</legend>
                                    <div className="grid gap-3 py-3 sm:grid-cols-2">
                                        <div>
                                            <Input
                                                autoFocus
                                                type="email"
                                                placeholder="Email address (optional)"
                                                value={form.data.email}
                                                onChange={(e) => form.setData('email', e.target.value)}
                                            />
                                            <InputError message={form.errors.email} />
                                        </div>
                                        <div>
                                            <Input
                                                type="tel"
                                                placeholder="Contact number (optional)"
                                                value={form.data.contact_number}
                                                onChange={(e) => form.setData('contact_number', e.target.value)}
                                            />
                                            <InputError message={form.errors.contact_number} />
                                        </div>
                                    </div>
                                </fieldset>
                            )}
                        </div>
                        <DialogFooter className="gap-2 border-t bg-slate-50 px-5 py-4">
                            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                                Cancel
                            </Button>
                            <div className="flex flex-1 justify-end gap-2">
                                {step > 0 && (
                                    <Button type="button" variant="outline" onClick={() => setStep((current) => current - 1)}>
                                        <ChevronLeft className="size-4" /> Back
                                    </Button>
                                )}
                                {step < 2 ? (
                                    <Button type="button" className="bg-brand-teal" onClick={next}>
                                        Next <ChevronRight className="size-4" />
                                    </Button>
                                ) : (
                                    <Button type="button" disabled={form.processing} className="bg-brand-teal" onClick={submit}>
                                        {form.processing ? 'Saving…' : editing ? 'Update company' : 'Create company'}
                                    </Button>
                                )}
                            </div>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

function AsyncLocationSelect({
    type,
    value,
    onValueChange,
    placeholder,
    state = '',
    city = '',
}: {
    type: 'state' | 'district' | 'city' | 'pincode';
    value: string;
    onValueChange: (value: string) => void;
    placeholder: string;
    state?: string;
    city?: string;
}) {
    const [query, setQuery] = useState(value);
    const [options, setOptions] = useState<Array<{ value: string; label: string }>>([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const disabled = (type === 'district' && !state) || (type === 'city' && !state) || (type === 'pincode' && !city);
    const manualValue = query.trim();
    const hasExactOption = options.some(
        (option) => option.value.toLowerCase() === manualValue.toLowerCase() || option.label.toLowerCase() === manualValue.toLowerCase(),
    );
    const canUseManualValue = manualValue !== '' && manualValue !== value && !hasExactOption;

    useEffect(() => setQuery(value), [value]);
    useEffect(() => {
        if (!open || disabled) return;
        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setLoading(true);
            try {
                const params = new URLSearchParams({ type, query: query === value ? '' : query });
                if (state) params.set('state', state);
                if (city) params.set('city', city);
                const response = await fetch(`/admin/user-master/location-options?${params.toString()}`, {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) throw new Error('Unable to load location options.');
                setOptions((await response.json()) as Array<{ value: string; label: string }>);
            } catch (error) {
                if (!(error instanceof DOMException && error.name === 'AbortError')) setOptions([]);
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 250);
        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [open, query, value, type, state, city, disabled]);

    return (
        <div className={`relative ${open ? 'z-[70]' : 'z-0'}`}>
            <div className="group relative">
                <Search className="group-focus-within:text-brand-teal pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    role="combobox"
                    aria-expanded={open}
                    aria-autocomplete="list"
                    aria-label={placeholder}
                    autoComplete="off"
                    value={query}
                    disabled={disabled}
                    placeholder={disabled ? (type === 'pincode' ? 'Choose a city first' : 'Choose a state first') : placeholder}
                    className="focus:border-brand-teal focus:ring-brand-teal/15 h-10 w-full rounded-lg border border-slate-300 bg-white pr-10 pl-10 text-sm outline-none focus:ring-3 disabled:cursor-not-allowed disabled:bg-slate-50"
                    onFocus={(event) => {
                        setOpen(true);
                        event.currentTarget.select();
                    }}
                    onBlur={() =>
                        window.setTimeout(() => {
                            setOpen(false);
                            setQuery(value);
                        }, 150)
                    }
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setOpen(true);
                    }}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter' && options[0]) {
                            event.preventDefault();
                            onValueChange(options[0].value);
                            setQuery(options[0].label);
                            setOpen(false);
                        }
                        if (event.key === 'Escape') setOpen(false);
                    }}
                />
                <ChevronDown className="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 text-slate-500" />
            </div>
            {open && !disabled && (
                <div className="absolute z-[80] mt-1.5 max-h-56 w-full overflow-y-auto rounded-lg border bg-white p-1.5 shadow-xl">
                    {loading ? (
                        <p className="px-3 py-2 text-sm text-slate-500">Loading…</p>
                    ) : (
                        <>
                            {canUseManualValue && (
                                <button
                                    type="button"
                                    className="text-brand-teal hover:bg-brand-aqua/15 mb-1 flex min-h-9 w-full items-center rounded-md border border-dashed border-cyan-200 px-3 py-2 text-left text-sm font-semibold"
                                    onMouseDown={(event) => event.preventDefault()}
                                    onClick={() => {
                                        onValueChange(manualValue);
                                        setQuery(manualValue);
                                        setOpen(false);
                                    }}
                                >
                                    Use “{manualValue}” manually
                                </button>
                            )}
                            {options.map((option) => (
                            <button
                                type="button"
                                key={option.value}
                                className="hover:bg-brand-aqua/15 flex min-h-9 w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm"
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => {
                                    onValueChange(option.value);
                                    setQuery(option.label);
                                    setOpen(false);
                                }}
                            >
                                {option.label}
                                {option.value === value && <Check className="text-brand-teal size-4" />}
                            </button>
                            ))}
                            {!options.length && !canUseManualValue && <p className="px-3 py-2 text-sm text-slate-500">No records found</p>}
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
