import InputError from '@/components/input-error';
import {
    MasterDialogHeader,
    masterDialogContentClass,
    masterDialogFooterClass,
    masterDialogFormClass,
} from '@/components/master/master-dialog-header';
import { MasterPageHeader } from '@/components/master/master-page-header';
import { StatusToggle } from '@/components/master/status-toggle';
import { RowExpandButton } from '@/components/row-expand-button';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Building2,
    Check,
    ChevronLeft,
    ChevronRight,
    Edit3,
    Mail,
    MapPin,
    Phone,
    Plus,
    Search,
    Trash2,
    Upload,
    UsersRound,
    WalletCards,
} from 'lucide-react';
import { FormEvent, Fragment, ReactNode, useEffect, useRef, useState } from 'react';

type AdditionalContact = { contact_type: 'mobile' | 'email'; value: string };
type AdditionalAddress = { address_line_1: string; city: string; state: string; district: string; postal_code: string };
type Customer = Record<string, unknown> & {
    id: number;
    unique_id: string;
    name: string;
    status: boolean;
    plant_code?: string | null;
    additional_contacts?: Array<{ contact_type?: string; email?: string | null; contact_number?: string | null }>;
    additional_addresses?: Array<Partial<AdditionalAddress>>;
    pending_sale?: Record<string, unknown>;
    daily_sale?: Record<string, unknown>;
    sales_summary?: Record<string, unknown>;
};
type Page = { data: Customer[]; current_page: number; last_page: number; total: number; from: number | null; to: number | null };
type MonthNumber = '01' | '02' | '03' | '04' | '05' | '06' | '07' | '08' | '09' | '10' | '11' | '12';
type MonthField = `month_${MonthNumber}_${'target' | 'actual'}`;
type QuarterField = `quarter_${1 | 2 | 3 | 4}_${'target' | 'actual'}`;
type EmployeeLevelField = `employee_level_${1 | 2 | 3 | 4 | 5 | 6}`;
type FormData = {
    customer_number: string;
    first_name: string;
    middle_name: string;
    last_name: string;
    email: string;
    mobile_number: string;
    address: string;
    city: string;
    state: string;
    district: string;
    postal_code: string;
    gstin: string;
    pan: string;
    sales_organization: string;
    sales_district: string;
    sales_group: string;
    sales_office: string;
    payment_terms: string;
    record_creation_date: string;
    plant_code: string;
    ecc_no: string;
    cst_no: string;
    lst_no: string;
    credit_limit: string;
    outstanding_amount: string;
    credit_utilization_percentage: string;
    status: boolean;
    additional_contacts: AdditionalContact[];
    additional_addresses: AdditionalAddress[];
    pending_sale: Record<string, string>;
    daily_sale: Record<string, string>;
    sales_summary: Record<string, string>;
} & Record<MonthField | QuarterField | EmployeeLevelField, string>;
const months = Array.from({ length: 12 }, (_, index) => String(index + 1).padStart(2, '0') as MonthNumber);
const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const monthlyKeys = months.flatMap((month) => [`month_${month}_target`, `month_${month}_actual`] as MonthField[]);
const quarterlyKeys = Array.from(
    { length: 4 },
    (_, index) => [`quarter_${index + 1}_target`, `quarter_${index + 1}_actual`] as QuarterField[],
).flat();
const employeeLevelKeys = Array.from({ length: 6 }, (_, index) => `employee_level_${index + 1}` as EmployeeLevelField);
const pendingFields = [
    ['sales_document_number', 'Sales document'],
    ['plant_code', 'Plant code'],
    ['document_date', 'Document date', 'date'],
    ['material_number', 'Material number'],
    ['external_material_group', 'External material group'],
    ['material_group', 'Material group'],
    ['order_quantity', 'Order quantity', 'number'],
    ['amount', 'Amount', 'number'],
    ['open_delivery_amount', 'Open delivery amount', 'number'],
    ['delivered_quantity', 'Delivered quantity', 'number'],
    ['material_text_1', 'Material text 1'],
    ['material_text_2', 'Material text 2'],
    ['material_text_3', 'Material text 3'],
] as const;
const dailyFields = [
    ['billing_document_number', 'Billing document'],
    ['billing_type', 'Billing type'],
    ['billing_date', 'Billing date', 'date'],
    ['record_creation_date', 'Record creation date', 'date'],
    ['company_code', 'Company code'],
    ['plant_code', 'Plant code'],
    ['material_number', 'Material number'],
    ['material_description', 'Material description'],
    ['material_group', 'Material group'],
    ['material_group_description', 'Material group description'],
    ['product_hierarchy', 'Product hierarchy'],
    ['product_hierarchy_description', 'Product hierarchy description'],
    ['external_material_group', 'External material group'],
    ['external_material_group_description', 'External group description'],
    ['billed_quantity', 'Billed quantity', 'number'],
    ['billed_quantity_kg', 'Quantity kg', 'number'],
    ['billed_quantity_liters', 'Quantity liters', 'number'],
    ['billed_quantity_meters', 'Quantity meters', 'number'],
    ['sales_unit', 'Sales unit'],
    ['base_unit', 'Base unit'],
    ['net_value', 'Net value', 'number'],
    ['tax_amount', 'Tax amount', 'number'],
    ['fiscal_year', 'Fiscal year'],
    ['condition_value', 'Condition value', 'number'],
] as const;
const summaryFields = [
    ['year_to_date_sales', 'Year-to-date sales'],
    ['last_year_sales', 'Last year sales'],
    ['pending_sales', 'Pending orders'],
    ['last_month_sales', 'Last month sales'],
    ['current_month_sales', 'Current month sales'],
    ['two_months_ago_sales', 'Two months ago sales'],
    ['last_month_outstanding', 'Last month outstanding'],
    ['current_month_outstanding', 'Current month outstanding'],
    ['two_months_ago_outstanding', 'Two months ago outstanding'],
    ['last_month_overdue', 'Last month overdue'],
    ['current_month_overdue', 'Current month overdue'],
    ['two_months_ago_overdue', 'Two months ago overdue'],
    ['last_month_payment_received', 'Last month payment'],
    ['current_month_payment_received', 'Current month payment'],
    ['two_months_ago_payment_received', 'Two months ago payment'],
    ['payment_terms_code', 'Payment terms code'],
    ['payment_terms_description', 'Payment terms description'],
    ['credit_limit', 'Credit limit'],
] as const;
const blankRecord = (fields: readonly (readonly [string, string, string?])[]) =>
    Object.fromEntries(fields.map(([key, , type]) => [key, type === 'number' ? '0' : '']));
const empty = {
    customer_number: '',
    first_name: '',
    middle_name: '',
    last_name: '',
    email: '',
    mobile_number: '',
    address: '',
    city: '',
    state: '',
    district: '',
    postal_code: '',
    record_creation_date: '',
    plant_code: '',
    gstin: '',
    ecc_no: '',
    cst_no: '',
    lst_no: '',
    pan: '',
    sales_organization: '',
    sales_district: '',
    sales_group: '',
    sales_office: '',
    payment_terms: '',
    credit_limit: '0',
    outstanding_amount: '0',
    credit_utilization_percentage: '0',
    status: true,
    additional_contacts: [],
    additional_addresses: [],
    pending_sale: blankRecord(pendingFields),
    daily_sale: blankRecord(dailyFields),
    sales_summary: Object.fromEntries(summaryFields.map(([key]) => [key, key.includes('terms') ? '' : '0'])),
    ...Object.fromEntries([...employeeLevelKeys, ...monthlyKeys, ...quarterlyKeys].map((key) => [key, key.startsWith('employee_level') ? '' : '0'])),
} as FormData;
const fields: Array<[keyof FormData, string, string?]> = [
    ['customer_number', 'Customer number'],
    ['first_name', 'First name'],
    ['middle_name', 'Middle name'],
    ['last_name', 'Last name'],
    ['email', 'Email', 'email'],
    ['mobile_number', 'Mobile number'],
    ['address', 'Address'],
    ['city', 'City'],
    ['state', 'State'],
    ['district', 'District / sales district'],
    ['postal_code', 'Postal code'],
    ['gstin', 'GSTIN'],
    ['pan', 'PAN'],
    ['record_creation_date', 'Record creation date', 'date'],
    ['plant_code', 'Plant code'],
    ['ecc_no', 'Excise control code'],
    ['cst_no', 'Central sales tax number'],
    ['lst_no', 'Local sales tax number'],
    ['sales_organization', 'Sales organization'],
    ['sales_district', 'Sales district code'],
    ['sales_group', 'Sales group'],
    ['sales_office', 'Sales office'],
    ['payment_terms', 'Payment terms'],
    ['credit_limit', 'Credit limit', 'number'],
    ['outstanding_amount', 'Outstanding amount', 'number'],
    ['credit_utilization_percentage', 'Credit utilization %', 'number'],
    ...employeeLevelKeys.map((key, index) => [key, `Employee hierarchy level ${index + 1}`] as [keyof FormData, string]),
    ...monthlyKeys.map(
        (key) =>
            [
                key,
                key
                    .replace(/month_(\d+)_/, 'Month $1 ')
                    .replace('target', 'target')
                    .replace('actual', 'actual'),
                'number',
            ] as [keyof FormData, string, string],
    ),
    ...quarterlyKeys.map(
        (key) =>
            [
                key,
                key
                    .replace(/quarter_(\d+)_/, 'Quarter $1 ')
                    .replace('target', 'target')
                    .replace('actual', 'actual'),
                'number',
            ] as [keyof FormData, string, string],
    ),
];
const customerSteps = [
    'Customer details',
    'Contact',
    'Address & map',
    'Sales & tax',
    'Credit & hierarchy',
    'Targets',
    'Pending Order',
    'Daily Sales',
    'Sales Summary',
] as const;
const customerSections: Array<Array<{ title: string; description?: string; keys: Array<keyof FormData> }>> = [
    [
        {
            title: 'Customer information',
            description: 'Primary customer identity maintained in SAP.',
            keys: ['first_name', 'middle_name', 'last_name', 'customer_number'],
        },
    ],
    [{ title: 'Primary contact', description: 'Communication details for this customer.', keys: ['mobile_number', 'email'] }],
    [
        {
            title: 'Registered address',
            description: 'Primary billing or business address.',
            keys: ['address', 'city', 'district', 'state', 'postal_code'],
        },
    ],
    [
        {
            title: 'SAP record details',
            description: 'Customer master and tax registration data.',
            keys: ['record_creation_date', 'plant_code', 'gstin', 'pan', 'ecc_no', 'cst_no', 'lst_no'],
        },
        {
            title: 'Sales assignment',
            description: 'SAP sales-area classification.',
            keys: ['sales_organization', 'sales_district', 'sales_group', 'sales_office', 'payment_terms'],
        },
    ],
    [
        {
            title: 'Credit information',
            description: 'Credit exposure and current utilization.',
            keys: ['credit_limit', 'outstanding_amount', 'credit_utilization_percentage'],
        },
        { title: 'Employee hierarchy', description: 'SAP employee hierarchy responsible for this customer.', keys: employeeLevelKeys },
    ],
    [
        { title: 'Monthly targets and actuals', description: 'January through December sales performance.', keys: monthlyKeys },
        { title: 'Quarterly targets and actuals', description: 'Quarter-level sales totals.', keys: quarterlyKeys },
    ],
    [{ title: 'Pending Order record', description: 'Create or update the latest pending-order record for this customer.', keys: [] }],
    [{ title: 'Daily Sales record', description: 'Create or update the latest daily-sale record for this customer.', keys: [] }],
    [
        {
            title: 'Customer Sales Summary',
            description: 'Maintain the customer-level sales, outstanding, overdue, payment and credit summary.',
            keys: [],
        },
    ],
];
const fieldByKey = new Map(fields.map((field) => [field[0], field]));
const money = (value: unknown) => Number(value ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
const text = (value: unknown, fallback = '—') => String(value ?? '').trim() || fallback;
const totalFor = (customer: Customer, keys: readonly string[]) => keys.reduce((total, key) => total + Number(customer[key] ?? 0), 0);

function SummaryCard({ title, icon, items }: { title: string; icon: ReactNode; items: Array<[string, string]> }) {
    return (
        <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="flex items-center gap-2 border-b border-slate-100 bg-gradient-to-r from-cyan-50/80 to-white px-4 py-3 text-sm font-bold text-slate-800">
                <span className="text-brand-teal">{icon}</span>
                {title}
            </div>
            <dl className="divide-y divide-slate-100 px-4">
                {items.map(([label, value]) => (
                    <div key={label} className="grid grid-cols-[110px_minmax(0,1fr)] gap-3 py-2.5 text-xs">
                        <dt className="font-medium text-slate-500">{label}</dt>
                        <dd className="min-w-0 font-semibold break-words text-slate-800">{value}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

type CustomerFilters = { search: string; per_page: number };
type EmployeeOption = { unique_id: string; name: string };
type PlantOption = { company_code: string; company_name: string; parent_company_id: string | null };

export default function Customers({
    customers,
    employees,
    plants,
    filters,
    stats,
    canManage,
    openCreateForm = false,
    initialFormStep = 0,
}: {
    customers: Page;
    employees: EmployeeOption[];
    plants: PlantOption[];
    filters: CustomerFilters;
    stats: { total: number; active: number };
    canManage: boolean;
    openCreateForm?: boolean;
    initialFormStep?: number;
}) {
    const [search, setSearch] = useState(filters.search);
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Customer | null>(null);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const [bulkAction, setBulkAction] = useState('');
    const [formStep, setFormStep] = useState(0);
    const [plantSearch, setPlantSearch] = useState('');
    const [plantDropdownOpen, setPlantDropdownOpen] = useState(false);
    const [importing, setImporting] = useState(false);
    const importInput = useRef<HTMLInputElement>(null);
    const form = useForm<FormData>(empty);
    const employeeNames = new Map(employees.map((employee) => [String(employee.unique_id), employee.name]));
    const employeeLabel = (value: unknown) => {
        const id = String(value ?? '').trim();
        return id ? `${employeeNames.get(id) ?? 'Employee'} (${id})` : '—';
    };
    const plantLabel = (plant: PlantOption) => `${plant.company_name} (${plant.company_code})`;
    const plantNeedle = plantSearch.replace(/[()]/g, ' ').replace(/\s+/g, ' ').trim().toLowerCase();
    const filteredPlants = plants.filter((plant) => `${plant.company_name} ${plant.company_code}`.toLowerCase().includes(plantNeedle));
    const showCreate = () => {
        setEditing(null);
        setFormStep(0);
        setPlantSearch('');
        form.setData(empty);
        form.clearErrors();
        setOpen(true);
    };
    useEffect(() => {
        if (!openCreateForm) return;
        setEditing(null);
        setFormStep(initialFormStep);
        setPlantSearch('');
        form.setData(empty);
        form.clearErrors();
        setOpen(true);
        // The Inertia form object is intentionally excluded because its identity changes after setData.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [openCreateForm, initialFormStep]);
    const showEdit = (customer: Customer) => {
        setEditing(customer);
        const currentPlant = plants.find((plant) => plant.company_code === String(customer.plant_code ?? ''));
        setPlantSearch(currentPlant ? plantLabel(currentPlant) : String(customer.plant_code ?? ''));
        form.setData(
            Object.fromEntries(
                Object.keys(empty).map((key) => {
                    if (key === 'additional_contacts')
                        return [
                            key,
                            (customer.additional_contacts ?? []).map((contact) => ({
                                contact_type: contact.contact_type === 'email' ? 'email' : 'mobile',
                                value: contact.email || contact.contact_number || '',
                            })),
                        ];
                    if (key === 'additional_addresses')
                        return [
                            key,
                            (customer.additional_addresses ?? []).map((address) => ({
                                address_line_1: address.address_line_1 || '',
                                city: address.city || '',
                                state: address.state || '',
                                district: address.district || '',
                                postal_code: address.postal_code || '',
                            })),
                        ];
                    if (key === 'pending_sale')
                        return [
                            key,
                            Object.fromEntries(
                                pendingFields.map(([field, , type]) => [
                                    field,
                                    String(customer.pending_sale?.[field] ?? (type === 'number' ? '0' : '')),
                                ]),
                            ),
                        ];
                    if (key === 'daily_sale')
                        return [
                            key,
                            Object.fromEntries(
                                dailyFields.map(([field, , type]) => [field, String(customer.daily_sale?.[field] ?? (type === 'number' ? '0' : ''))]),
                            ),
                        ];
                    if (key === 'sales_summary')
                        return [
                            key,
                            Object.fromEntries(
                                summaryFields.map(([field]) => [
                                    field,
                                    String(customer.sales_summary?.[field] ?? (field.includes('terms') ? '' : '0')),
                                ]),
                            ),
                        ];
                    const mapping: Record<string, string> = {
                        customer_number: 'unique_id',
                        mobile_number: 'contact_number',
                        address: 'address_line_1',
                    };
                    const value = customer[mapping[key] ?? key];
                    return [key, key === 'status' ? Boolean(value) : String(value ?? '')];
                }),
            ) as FormData,
        );
        setFormStep(0);
        form.clearErrors();
        setOpen(true);
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        if (editing) form.put(`/admin/sales-distribution/customers/${editing.id}`, options);
        else form.post('/admin/sales-distribution/customers', options);
    };
    const load = (page = 1) =>
        router.get('/admin/sales-distribution/customers', { search, per_page: filters.per_page, page }, { preserveState: true, replace: true });
    const allSelected = customers.data.length > 0 && customers.data.every((customer) => selectedIds.includes(customer.id));
    const runBulkAction = () => {
        if (!bulkAction || selectedIds.length === 0) return;
        if (bulkAction === 'delete' && !confirm(`Delete ${selectedIds.length} selected customers?`)) return;
        router.patch(
            '/admin/sales-distribution/customers/bulk',
            { ids: selectedIds, action: bulkAction },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedIds([]);
                    setBulkAction('');
                },
            },
        );
    };
    const importCustomers = (file?: File) => {
        if (!file) return;
        const data = new FormData();
        data.append('file', file);
        setImporting(true);
        router.post('/admin/sales-distribution/customers/import', data, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setImporting(false);
                if (importInput.current) importInput.current.value = '';
            },
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Sales & Distribution', href: '/admin/sales-distribution' },
                { title: 'Customers', href: '/admin/sales-distribution/customers' },
            ]}
        >
            <Head title="Customers" />
            <div className="mx-auto w-full max-w-[1680px] space-y-4 p-3 md:p-5 lg:p-6">
                <MasterPageHeader
                    title="Customer Master"
                    description={`${stats.total} customers · ${stats.active} active`}
                    icon={<UsersRound className="size-5" />}
                    action={
                        <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    load();
                                }}
                                className="relative w-full sm:w-72"
                            >
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-cyan-100" />
                                <Input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Search customers…"
                                    className="h-9 border-cyan-300/40 bg-cyan-500/20 pr-3 pl-9 text-white shadow-sm placeholder:text-cyan-100/80 focus-visible:border-cyan-200 focus-visible:ring-cyan-300/40"
                                />
                            </form>
                            {canManage && (
                                <>
                                    <input
                                        ref={importInput}
                                        type="file"
                                        accept=".csv,text/csv"
                                        className="hidden"
                                        onChange={(event) => importCustomers(event.target.files?.[0])}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={importing}
                                        onClick={() => importInput.current?.click()}
                                        className="h-9 border-cyan-200 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                                    >
                                        <Upload className="size-4" /> {importing ? 'Importing…' : 'Import CSV'}
                                    </Button>
                                </>
                            )}
                            {canManage && (
                                <Button type="button" onClick={showCreate} className="h-9 bg-orange-500 text-white hover:bg-orange-600">
                                    <Plus className="size-4" /> Add customer
                                </Button>
                            )}
                        </div>
                    }
                />
                <section className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_12px_35px_-22px_rgba(15,23,42,0.35)]">
                    {canManage && selectedIds.length > 0 && (
                        <div className="flex flex-wrap items-center gap-3 border-b border-cyan-100 bg-cyan-50 px-4 py-3">
                            <span className="text-brand-teal text-xs font-bold">{selectedIds.length} selected</span>
                            <Select value={bulkAction} onValueChange={setBulkAction}>
                                <SelectTrigger className="h-9 w-44 bg-white">
                                    <SelectValue placeholder="Bulk action" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="activate">Mark active</SelectItem>
                                    <SelectItem value="deactivate">Mark inactive</SelectItem>
                                    <SelectItem value="delete">Delete selected</SelectItem>
                                </SelectContent>
                            </Select>
                            <Button size="sm" variant="outline" disabled={!bulkAction} onClick={runBulkAction}>
                                Apply
                            </Button>
                            <Button size="sm" variant="ghost" onClick={() => setSelectedIds([])}>
                                Clear
                            </Button>
                        </div>
                    )}
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1180px] table-fixed text-left text-sm">
                            <thead className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold tracking-[0.08em] text-slate-500 uppercase backdrop-blur">
                                <tr>
                                    <th className="w-14 px-4 py-3.5">
                                        <Checkbox
                                            checked={allSelected ? true : selectedIds.length > 0 ? 'indeterminate' : false}
                                            onCheckedChange={(checked) =>
                                                setSelectedIds(checked === true ? customers.data.map((customer) => customer.id) : [])
                                            }
                                            aria-label="Select all customers"
                                        />
                                    </th>
                                    <th className="w-16 px-3 py-3.5">S.No.</th>
                                    <th className="w-[22%] px-4 py-3.5">Customer</th>
                                    <th className="w-[19%] px-4 py-3.5">Contact</th>
                                    <th className="w-[25%] px-4 py-3.5">Location</th>
                                    <th className="w-[12%] px-4 py-3.5">Sales</th>
                                    <th className="w-[12%] px-4 py-3.5">Credit</th>
                                    <th className="w-28 px-4 py-3.5">Status</th>
                                    <th className="w-24 px-4 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {customers.data.map((customer, index) => (
                                    <Fragment key={customer.id}>
                                        <tr
                                            className={`group bg-white transition-colors hover:bg-cyan-50/35 ${expandedId === customer.id ? 'bg-cyan-50/40' : ''}`}
                                        >
                                            <td className="px-4 py-3.5">
                                                <Checkbox
                                                    checked={selectedIds.includes(customer.id)}
                                                    onCheckedChange={(checked) =>
                                                        setSelectedIds((current) =>
                                                            checked === true
                                                                ? [...new Set([...current, customer.id])]
                                                                : current.filter((id) => id !== customer.id),
                                                        )
                                                    }
                                                    aria-label={`Select ${customer.name}`}
                                                />
                                            </td>
                                            <td className="px-3 py-3.5 text-xs font-bold text-slate-400 tabular-nums">
                                                {(customers.current_page - 1) * filters.per_page + index + 1}
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="flex min-w-0 items-center gap-2.5">
                                                    <RowExpandButton
                                                        expanded={expandedId === customer.id}
                                                        onClick={() => setExpandedId((current) => (current === customer.id ? null : customer.id))}
                                                        label={`${expandedId === customer.id ? 'Hide' : 'Show'} ${customer.name} summary`}
                                                        className="size-8"
                                                    />
                                                    <div className="min-w-0">
                                                        <div className="truncate font-semibold text-slate-900" title={text(customer.name)}>
                                                            {text(customer.name)}
                                                        </div>
                                                        <div className="mt-1 inline-flex rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[10px] font-semibold tracking-wide text-slate-600">
                                                            {text(customer.unique_id)}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="flex min-w-0 items-center gap-2 text-xs text-slate-700">
                                                    <Phone className="text-brand-teal size-3.5 shrink-0" />
                                                    <span className="truncate">{text(customer.contact_number)}</span>
                                                </div>
                                                <div className="mt-1.5 flex min-w-0 items-center gap-2 text-xs text-slate-500">
                                                    <Mail className="size-3.5 shrink-0" />
                                                    <span className="truncate" title={text(customer.email)}>
                                                        {text(customer.email)}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="flex min-w-0 items-center gap-2 text-xs font-medium text-slate-700">
                                                    <MapPin className="text-brand-teal size-3.5 shrink-0" />
                                                    <span className="truncate">
                                                        {text(customer.city)}, {text(customer.state)}
                                                    </span>
                                                </div>
                                                <div
                                                    className="mt-1.5 truncate pl-5.5 text-xs text-slate-500"
                                                    title={text(customer.address_line_1, '')}
                                                >
                                                    {text(customer.address_line_1)}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-2 text-xs font-semibold text-slate-700">
                                                    <Building2 className="text-brand-teal size-3.5" />
                                                    {text(customer.sales_organization)}
                                                </div>
                                                <div className="mt-1.5 truncate pl-5.5 text-xs text-slate-500" title={text(customer.sales_office)}>
                                                    {text(customer.sales_office)}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-2 text-xs font-semibold text-slate-800">
                                                    <WalletCards className="text-brand-teal size-3.5" />₹{money(customer.credit_limit)}
                                                </div>
                                                <div className="mt-1.5 pl-5.5 text-[11px] text-slate-500">
                                                    Due ₹{money(customer.outstanding_amount)}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <StatusToggle
                                                    active={customer.status}
                                                    disabled={!canManage}
                                                    label={customer.name}
                                                    onChange={() =>
                                                        router.patch(
                                                            `/admin/sales-distribution/customers/${customer.id}/status`,
                                                            { active: !customer.status },
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="flex justify-end gap-1">
                                                    {canManage && (
                                                        <>
                                                            <Button
                                                                size="icon"
                                                                variant="ghost"
                                                                className="size-8 rounded-lg text-slate-500 hover:bg-cyan-100 hover:text-cyan-700"
                                                                title="Edit customer"
                                                                onClick={() => showEdit(customer)}
                                                            >
                                                                <Edit3 className="size-4" />
                                                            </Button>
                                                            <Button
                                                                size="icon"
                                                                variant="ghost"
                                                                className="size-8 rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600"
                                                                title="Delete customer"
                                                                onClick={() =>
                                                                    confirm(`Delete ${customer.name}?`) &&
                                                                    router.delete(`/admin/sales-distribution/customers/${customer.id}`, {
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
                                        {expandedId === customer.id && (
                                            <tr className="bg-slate-50/80">
                                                <td colSpan={9} className="border-l-2 border-cyan-500 px-5 py-5">
                                                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                                        <SummaryCard
                                                            title="Customer & contact"
                                                            icon={<UsersRound className="size-4" />}
                                                            items={[
                                                                ['Customer number', text(customer.unique_id)],
                                                                ['Phone', text(customer.contact_number)],
                                                                ['Email', text(customer.email)],
                                                                ['Created', text(customer.record_creation_date)],
                                                            ]}
                                                        />
                                                        <SummaryCard
                                                            title="Address & tax"
                                                            icon={<MapPin className="size-4" />}
                                                            items={[
                                                                ['Address', text(customer.address_line_1)],
                                                                [
                                                                    'Location',
                                                                    [customer.city, customer.district, customer.state, customer.postal_code]
                                                                        .filter(Boolean)
                                                                        .join(', ') || '—',
                                                                ],
                                                                ['GSTIN', text(customer.gstin)],
                                                                ['PAN', text(customer.pan)],
                                                            ]}
                                                        />
                                                        <SummaryCard
                                                            title="Sales assignment"
                                                            icon={<Building2 className="size-4" />}
                                                            items={[
                                                                ['Plant', text(customer.plant_code)],
                                                                ['Organization', text(customer.sales_organization)],
                                                                [
                                                                    'District / office',
                                                                    `${text(customer.sales_district)} / ${text(customer.sales_office)}`,
                                                                ],
                                                                ['Payment terms', text(customer.payment_terms)],
                                                            ]}
                                                        />
                                                        <SummaryCard
                                                            title="Credit & performance"
                                                            icon={<WalletCards className="size-4" />}
                                                            items={[
                                                                ['Credit limit', `₹${money(customer.credit_limit)}`],
                                                                ['Outstanding', `₹${money(customer.outstanding_amount)}`],
                                                                [
                                                                    'Monthly target / actual',
                                                                    `₹${money(
                                                                        totalFor(
                                                                            customer,
                                                                            monthlyKeys.filter((key) => key.endsWith('_target')),
                                                                        ),
                                                                    )} / ₹${money(
                                                                        totalFor(
                                                                            customer,
                                                                            monthlyKeys.filter((key) => key.endsWith('_actual')),
                                                                        ),
                                                                    )}`,
                                                                ],
                                                                ['Utilization', `${money(customer.credit_utilization_percentage)}%`],
                                                            ]}
                                                        />
                                                    </div>
                                                    <div className="mt-4 flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3">
                                                        <span className="mr-1 text-[10px] font-bold tracking-wide text-slate-500 uppercase">
                                                            Employee hierarchy
                                                        </span>
                                                        {employeeLevelKeys.map((key, level) => (
                                                            <span
                                                                key={key}
                                                                className="rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700"
                                                            >
                                                                L{level + 1}: {employeeLabel(customer[key])}
                                                            </span>
                                                        ))}
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </Fragment>
                                ))}
                                {customers.data.length === 0 && (
                                    <tr>
                                        <td colSpan={9} className="px-4 py-20 text-center">
                                            <div className="mx-auto grid size-12 place-items-center rounded-full bg-slate-100 text-slate-400">
                                                <UsersRound className="size-5" />
                                            </div>
                                            <p className="mt-3 font-semibold text-slate-700">No customers found</p>
                                            <p className="mt-1 text-xs text-slate-500">Try adjusting your search criteria.</p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex items-center justify-between border-t border-slate-200 bg-slate-50/60 px-4 py-3 text-xs text-slate-500">
                        <span>
                            Showing{' '}
                            <strong className="text-slate-700">
                                {customers.from ?? 0}–{customers.to ?? 0}
                            </strong>{' '}
                            of <strong className="text-slate-700">{customers.total}</strong> customers
                        </span>
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                className="bg-white"
                                disabled={customers.current_page <= 1}
                                onClick={() => load(customers.current_page - 1)}
                            >
                                <ChevronLeft className="size-4" /> Previous
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                className="bg-white"
                                disabled={customers.current_page >= customers.last_page}
                                onClick={() => load(customers.current_page + 1)}
                            >
                                Next <ChevronRight className="size-4" />
                            </Button>
                        </div>
                    </div>
                </section>
            </div>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className={masterDialogContentClass}>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (formStep === customerSteps.length - 1) submit(event);
                        }}
                        className={masterDialogFormClass}
                    >
                        <MasterDialogHeader compact title={editing ? 'Edit customer' : 'Create customer'} />
                        <div className="shrink-0 border-b border-slate-200 bg-white px-3 sm:px-6">
                            <nav className="overflow-x-auto" aria-label="Customer form sections">
                                <ol className="flex min-w-max gap-1 pt-2">
                                    {customerSteps.map((step, index) => {
                                        const isComplete = index < formStep;
                                        const isActive = index === formStep;
                                        const canOpen = editing || index <= formStep;

                                        return (
                                            <li key={step}>
                                                <button
                                                    type="button"
                                                    onClick={() => canOpen && setFormStep(index)}
                                                    disabled={!canOpen}
                                                    aria-current={isActive ? 'step' : undefined}
                                                    className={`group relative flex h-14 min-w-36 items-center gap-2.5 rounded-t-lg border-x border-t px-3 text-left transition-all ${isActive ? 'border-slate-200 bg-slate-50 text-slate-950 shadow-[0_-2px_8px_rgba(15,23,42,0.04)]' : isComplete ? 'border-transparent text-slate-700 hover:bg-cyan-50' : 'cursor-not-allowed border-transparent text-slate-400'}`}
                                                >
                                                    <span
                                                        className={`grid size-7 shrink-0 place-items-center rounded-md text-[11px] font-black transition-colors ${isActive ? 'bg-brand-plum text-white' : isComplete ? 'bg-brand-teal text-white' : 'bg-slate-100 text-slate-400'}`}
                                                    >
                                                        {isComplete ? <Check className="size-3.5" strokeWidth={3} /> : index + 1}
                                                    </span>
                                                    <span>
                                                        <span className="block text-[9px] font-bold tracking-[0.08em] text-slate-400 uppercase">
                                                            Section {index + 1}
                                                        </span>
                                                        <span className="mt-0.5 block max-w-28 truncate text-[11px] font-bold">{step}</span>
                                                    </span>
                                                    {isActive && (
                                                        <span className="bg-brand-teal absolute right-2 bottom-0 left-2 h-0.5 rounded-full" />
                                                    )}
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ol>
                            </nav>
                        </div>
                        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-slate-100/60 px-3 pt-4 pb-6 sm:px-6 sm:pt-5 sm:pb-7">
                            <div className="mx-auto grid max-w-7xl gap-5 pb-3">
                                {customerSections[formStep].map((section) => (
                                    <fieldset
                                        key={section.title}
                                        className="form-fieldset !m-0 !rounded-xl !border-slate-200 !bg-white !px-5 !pb-5 shadow-sm sm:!px-6 sm:!pb-6"
                                    >
                                        <legend className="px-2 text-sm font-extrabold tracking-tight text-slate-900">{section.title}</legend>
                                        {section.description && (
                                            <div className="mb-5 flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                                                <p className="text-xs leading-5 text-slate-500">{section.description}</p>
                                                <span className="hidden shrink-0 rounded-full bg-cyan-50 px-2.5 py-1 text-[9px] font-bold tracking-wide text-cyan-700 uppercase sm:inline-flex">
                                                    Customer master
                                                </span>
                                            </div>
                                        )}
                                        {section.title === 'Pending Order record' ? (
                                            <div className="space-y-4">
                                                <div className="rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-xs text-cyan-800">
                                                    Customer number is linked automatically from Customer Details. Complete Sales document and
                                                    Material number to save this record.
                                                </div>
                                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                                    {pendingFields.map(([key, label, type = 'text']) => (
                                                        <div key={key}>
                                                            <Label className="mb-1.5 block text-[10px] font-bold tracking-wide text-slate-500 uppercase">
                                                                {label}
                                                            </Label>
                                                            <Input
                                                                type={type}
                                                                step={type === 'number' ? '0.001' : undefined}
                                                                value={form.data.pending_sale[key]}
                                                                onChange={(event) =>
                                                                    form.setData('pending_sale', {
                                                                        ...form.data.pending_sale,
                                                                        [key]: event.target.value,
                                                                    })
                                                                }
                                                                className="h-10"
                                                            />
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        ) : section.title === 'Daily Sales record' ? (
                                            <div className="space-y-4">
                                                <div className="rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-xs text-cyan-800">
                                                    Customer number and customer name are linked automatically. Complete Billing document and Material
                                                    number to save this record.
                                                </div>
                                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                                    {dailyFields.map(([key, label, type = 'text']) => (
                                                        <div key={key}>
                                                            <Label className="mb-1.5 block text-[10px] font-bold tracking-wide text-slate-500 uppercase">
                                                                {label}
                                                            </Label>
                                                            <Input
                                                                type={type}
                                                                step={type === 'number' ? '0.001' : undefined}
                                                                value={form.data.daily_sale[key]}
                                                                onChange={(event) =>
                                                                    form.setData('daily_sale', { ...form.data.daily_sale, [key]: event.target.value })
                                                                }
                                                                placeholder={label}
                                                                className="h-10"
                                                            />
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        ) : section.title === 'Customer Sales Summary' ? (
                                            <div className="space-y-4">
                                                <div className="rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-xs text-cyan-800">
                                                    This summary is stored once per customer and updates the existing record when present.
                                                </div>
                                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                                    {summaryFields.map(([key, label]) => (
                                                        <div key={key}>
                                                            <Label className="mb-1.5 block text-[10px] font-bold tracking-wide text-slate-500 uppercase">
                                                                {label}
                                                            </Label>
                                                            <Input
                                                                type={key.includes('terms') ? 'text' : 'number'}
                                                                step="0.001"
                                                                value={form.data.sales_summary[key]}
                                                                onChange={(event) =>
                                                                    form.setData('sales_summary', {
                                                                        ...form.data.sales_summary,
                                                                        [key]: event.target.value,
                                                                    })
                                                                }
                                                                placeholder={label}
                                                                className="h-10"
                                                            />
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        ) : section.title === 'Primary contact' ? (
                                            <div className="space-y-4">
                                                <div className="overflow-hidden rounded-xl border border-cyan-300 bg-white shadow-sm">
                                                    <div className="flex items-center justify-between border-b border-cyan-100 bg-cyan-50 px-4 py-3">
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-[10px] font-bold tracking-wider text-cyan-700 uppercase">
                                                                Primary contact
                                                            </span>
                                                            <span className="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-700">
                                                                Customer communication
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center gap-2 text-xs font-semibold text-slate-700">
                                                            <span className="relative h-5 w-9 rounded-full bg-cyan-600 after:absolute after:top-0.5 after:right-0.5 after:size-4 after:rounded-full after:bg-white" />
                                                            Primary
                                                        </div>
                                                    </div>
                                                    <div className="grid gap-4 p-4 md:grid-cols-2">
                                                        <div>
                                                            <Label
                                                                htmlFor="mobile_number"
                                                                className="mb-1.5 block text-[10px] font-bold tracking-wide text-slate-500 uppercase"
                                                            >
                                                                Contact type
                                                            </Label>
                                                            <div className="relative">
                                                                <Phone className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                                                <Input value="Mobile" readOnly className="h-10 bg-slate-50 pl-10" />
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <Label
                                                                htmlFor="mobile_number"
                                                                className="mb-1.5 block text-[10px] font-bold tracking-wide text-slate-500 uppercase"
                                                            >
                                                                Mobile number
                                                            </Label>
                                                            <Input
                                                                id="mobile_number"
                                                                value={form.data.mobile_number}
                                                                onChange={(event) => form.setData('mobile_number', event.target.value)}
                                                                placeholder="Enter mobile number"
                                                                className="h-10"
                                                            />
                                                            <InputError message={form.errors.mobile_number} />
                                                        </div>
                                                    </div>
                                                </div>
                                                <div className="overflow-hidden rounded-xl border border-cyan-300 bg-white shadow-sm">
                                                    <div className="flex items-center justify-between border-b border-cyan-100 bg-cyan-50 px-4 py-3">
                                                        <div className="flex items-center gap-2">
                                                            <Mail className="size-4 text-cyan-700" />
                                                            <span className="text-sm font-bold text-slate-800">Email</span>
                                                        </div>
                                                        <span className="text-xs font-semibold text-cyan-700">Primary email</span>
                                                    </div>
                                                    <div className="grid gap-4 p-4 md:grid-cols-2">
                                                        <div>
                                                            <Label className="mb-1.5 block text-[10px] font-bold tracking-wide text-slate-500 uppercase">
                                                                Contact type
                                                            </Label>
                                                            <div className="relative">
                                                                <Mail className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                                                <Input value="Email" readOnly className="h-10 bg-slate-50 pl-10" />
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <Label
                                                                htmlFor="email"
                                                                className="mb-1.5 block text-[10px] font-bold tracking-wide text-slate-500 uppercase"
                                                            >
                                                                Email address
                                                            </Label>
                                                            <Input
                                                                id="email"
                                                                type="email"
                                                                value={form.data.email}
                                                                onChange={(event) => form.setData('email', event.target.value)}
                                                                placeholder="Enter email address"
                                                                className="h-10"
                                                            />
                                                            <InputError message={form.errors.email} />
                                                        </div>
                                                    </div>
                                                </div>
                                                {form.data.additional_contacts.map((contact, index) => (
                                                    <div
                                                        key={index}
                                                        className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                                                    >
                                                        <div className="flex items-center justify-between border-b bg-slate-50 px-4 py-2.5">
                                                            <span className="text-xs font-bold text-slate-700">Additional contact {index + 1}</span>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="ghost"
                                                                className="text-red-600"
                                                                onClick={() =>
                                                                    form.setData(
                                                                        'additional_contacts',
                                                                        form.data.additional_contacts.filter((_, i) => i !== index),
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="size-4" /> Remove
                                                            </Button>
                                                        </div>
                                                        <div className="grid gap-4 p-4 md:grid-cols-2">
                                                            <div>
                                                                <Label className="mb-1.5 block text-[10px] font-bold tracking-wide text-slate-500 uppercase">
                                                                    Contact type
                                                                </Label>
                                                                <Select
                                                                    value={contact.contact_type}
                                                                    onValueChange={(value: 'mobile' | 'email') =>
                                                                        form.setData(
                                                                            'additional_contacts',
                                                                            form.data.additional_contacts.map((item, i) =>
                                                                                i === index ? { ...item, contact_type: value } : item,
                                                                            ),
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger className="h-10">
                                                                        <SelectValue />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        <SelectItem value="mobile">Mobile</SelectItem>
                                                                        <SelectItem value="email">Email</SelectItem>
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                            <div>
                                                                <Label className="mb-1.5 block text-[10px] font-bold tracking-wide text-slate-500 uppercase">
                                                                    {contact.contact_type === 'email' ? 'Email address' : 'Mobile number'}
                                                                </Label>
                                                                <Input
                                                                    type={contact.contact_type === 'email' ? 'email' : 'text'}
                                                                    value={contact.value}
                                                                    onChange={(event) =>
                                                                        form.setData(
                                                                            'additional_contacts',
                                                                            form.data.additional_contacts.map((item, i) =>
                                                                                i === index ? { ...item, value: event.target.value } : item,
                                                                            ),
                                                                        )
                                                                    }
                                                                    placeholder={`Enter ${contact.contact_type}`}
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}
                                                <div className="flex flex-wrap gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() =>
                                                            form.setData('additional_contacts', [
                                                                ...form.data.additional_contacts,
                                                                { contact_type: 'mobile', value: '' },
                                                            ])
                                                        }
                                                    >
                                                        <Plus className="size-4" /> Add contact number
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() =>
                                                            form.setData('additional_contacts', [
                                                                ...form.data.additional_contacts,
                                                                { contact_type: 'email', value: '' },
                                                            ])
                                                        }
                                                    >
                                                        <Mail className="size-4" /> Add email
                                                    </Button>
                                                </div>
                                            </div>
                                        ) : section.title === 'Registered address' ? (
                                            <div className="space-y-4">
                                                <div className="rounded-xl border border-slate-200 bg-slate-50 p-2">
                                                    <button
                                                        type="button"
                                                        className="rounded-lg border border-cyan-500 bg-white px-5 py-2 text-left shadow-sm"
                                                    >
                                                        <span className="block text-[10px] font-bold tracking-wider text-cyan-700 uppercase">
                                                            Address 1
                                                        </span>
                                                        <span className="block text-sm font-bold text-slate-900">Registered / Primary</span>
                                                        <span className="block text-xs text-slate-500">Customer business address</span>
                                                    </button>
                                                </div>
                                                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                                                    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                                        <div className="grid gap-3 sm:grid-cols-2">
                                                            <div className="sm:col-span-2">
                                                                <Label
                                                                    htmlFor="address"
                                                                    className="mb-1.5 block text-[10px] font-bold tracking-wide text-slate-500 uppercase"
                                                                >
                                                                    Address line
                                                                </Label>
                                                                <Input
                                                                    id="address"
                                                                    value={form.data.address}
                                                                    onChange={(event) => form.setData('address', event.target.value)}
                                                                    placeholder="Enter registered address"
                                                                    className="h-10"
                                                                />
                                                                <InputError message={form.errors.address} />
                                                            </div>
                                                            {(
                                                                [
                                                                    ['state', 'State'],
                                                                    ['district', 'District'],
                                                                    ['city', 'City'],
                                                                    ['postal_code', 'Postal code'],
                                                                ] as const
                                                            ).map(([key, label]) => (
                                                                <div key={key}>
                                                                    <Label
                                                                        htmlFor={key}
                                                                        className="mb-1.5 block text-[10px] font-bold tracking-wide text-slate-500 uppercase"
                                                                    >
                                                                        {label}
                                                                    </Label>
                                                                    <Input
                                                                        id={key}
                                                                        value={form.data[key]}
                                                                        onChange={(event) => form.setData(key, event.target.value)}
                                                                        placeholder={`Enter ${label.toLowerCase()}`}
                                                                        className="h-10"
                                                                    />
                                                                    <InputError message={form.errors[key]} />
                                                                </div>
                                                            ))}
                                                            <div>
                                                                <Label className="mb-1.5 block text-[10px] font-bold tracking-wide text-slate-500 uppercase">
                                                                    Country
                                                                </Label>
                                                                <Input value="India" readOnly className="h-10 bg-slate-50" />
                                                            </div>
                                                            <div className="flex items-end">
                                                                <Button type="button" variant="outline" className="h-10 w-full">
                                                                    <MapPin className="size-4" /> Fetch map location
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div className="grid min-h-64 place-items-center rounded-xl border border-slate-200 bg-slate-100 p-6 text-center shadow-inner">
                                                        <div>
                                                            <span className="mx-auto grid size-14 place-items-center rounded-full bg-cyan-100 text-cyan-600">
                                                                <MapPin className="size-6" />
                                                            </span>
                                                            <p className="mt-4 font-bold text-slate-900">Registered address map</p>
                                                            <p className="mt-1 text-xs leading-5 text-slate-500">
                                                                Complete the address and select Fetch map location.
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                {form.data.additional_addresses.map((address, index) => (
                                                    <div key={index} className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                                        <div className="mb-4 flex items-center justify-between">
                                                            <div>
                                                                <span className="text-[10px] font-bold tracking-wider text-cyan-700 uppercase">
                                                                    Address {index + 2}
                                                                </span>
                                                                <p className="font-bold text-slate-900">Additional address</p>
                                                            </div>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="ghost"
                                                                className="text-red-600"
                                                                onClick={() =>
                                                                    form.setData(
                                                                        'additional_addresses',
                                                                        form.data.additional_addresses.filter((_, i) => i !== index),
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="size-4" /> Remove
                                                            </Button>
                                                        </div>
                                                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                                            <div className="sm:col-span-2 lg:col-span-3">
                                                                <Label className="mb-1.5 block text-[10px] font-bold tracking-wide text-slate-500 uppercase">
                                                                    Address line
                                                                </Label>
                                                                <Input
                                                                    value={address.address_line_1}
                                                                    onChange={(event) =>
                                                                        form.setData(
                                                                            'additional_addresses',
                                                                            form.data.additional_addresses.map((item, i) =>
                                                                                i === index ? { ...item, address_line_1: event.target.value } : item,
                                                                            ),
                                                                        )
                                                                    }
                                                                    placeholder="Enter address"
                                                                />
                                                            </div>
                                                            {(['state', 'district', 'city', 'postal_code'] as const).map((key) => (
                                                                <div key={key}>
                                                                    <Label className="mb-1.5 block text-[10px] font-bold tracking-wide text-slate-500 uppercase">
                                                                        {key.replace('_', ' ')}
                                                                    </Label>
                                                                    <Input
                                                                        value={address[key]}
                                                                        onChange={(event) =>
                                                                            form.setData(
                                                                                'additional_addresses',
                                                                                form.data.additional_addresses.map((item, i) =>
                                                                                    i === index ? { ...item, [key]: event.target.value } : item,
                                                                                ),
                                                                            )
                                                                        }
                                                                    />
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                ))}
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        form.setData('additional_addresses', [
                                                            ...form.data.additional_addresses,
                                                            { address_line_1: '', city: '', state: '', district: '', postal_code: '' },
                                                        ])
                                                    }
                                                >
                                                    <Plus className="size-4" /> Add another address
                                                </Button>
                                            </div>
                                        ) : section.title === 'Monthly targets and actuals' ? (
                                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                                {months.map((month, index) => {
                                                    const targetKey = `month_${month}_target` as MonthField;
                                                    const actualKey = `month_${month}_actual` as MonthField;
                                                    return (
                                                        <div
                                                            key={month}
                                                            className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md"
                                                        >
                                                            <div className="flex items-center justify-between border-b border-slate-100 bg-gradient-to-r from-cyan-50 to-white px-3.5 py-2.5">
                                                                <span className="text-xs font-bold text-slate-800">{monthNames[index]}</span>
                                                                <span className="rounded-md bg-cyan-100 px-2 py-0.5 text-[10px] font-bold text-cyan-700">
                                                                    {month}
                                                                </span>
                                                            </div>
                                                            <div className="grid grid-cols-2 gap-2.5 p-3">
                                                                {(
                                                                    [
                                                                        [targetKey, 'Target'],
                                                                        [actualKey, 'Actual'],
                                                                    ] as const
                                                                ).map(([key, label]) => (
                                                                    <div key={key}>
                                                                        <Label
                                                                            htmlFor={key}
                                                                            className="mb-1.5 block text-[10px] font-bold tracking-wide text-slate-500 uppercase"
                                                                        >
                                                                            {label}
                                                                        </Label>
                                                                        <Input
                                                                            id={key}
                                                                            type="number"
                                                                            step="0.01"
                                                                            value={String(form.data[key])}
                                                                            onChange={(event) => form.setData(key, event.target.value)}
                                                                            className="h-9 border-slate-200 bg-slate-50 text-right font-medium shadow-none focus-visible:border-cyan-500 focus-visible:bg-white focus-visible:ring-cyan-500/20"
                                                                        />
                                                                        <InputError message={form.errors[key]} />
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        ) : section.title === 'Quarterly targets and actuals' ? (
                                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                                {[1, 2, 3, 4].map((quarter) => {
                                                    const targetKey = `quarter_${quarter}_target` as QuarterField;
                                                    const actualKey = `quarter_${quarter}_actual` as QuarterField;
                                                    return (
                                                        <div key={quarter} className="rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
                                                            <div className="mb-3 flex items-center justify-between">
                                                                <span className="text-sm font-bold text-slate-800">Quarter {quarter}</span>
                                                                <span className="rounded-lg bg-violet-50 px-2 py-1 text-[10px] font-bold text-violet-700">
                                                                    Q{quarter}
                                                                </span>
                                                            </div>
                                                            <div className="grid grid-cols-2 gap-2.5">
                                                                {(
                                                                    [
                                                                        [targetKey, 'Target'],
                                                                        [actualKey, 'Actual'],
                                                                    ] as const
                                                                ).map(([key, label]) => (
                                                                    <div key={key}>
                                                                        <Label
                                                                            htmlFor={key}
                                                                            className="mb-1.5 block text-[10px] font-bold tracking-wide text-slate-500 uppercase"
                                                                        >
                                                                            {label}
                                                                        </Label>
                                                                        <Input
                                                                            id={key}
                                                                            type="number"
                                                                            step="0.01"
                                                                            value={String(form.data[key])}
                                                                            onChange={(event) => form.setData(key, event.target.value)}
                                                                            className="h-9 border-slate-200 bg-slate-50 text-right font-medium shadow-none focus-visible:border-cyan-500 focus-visible:bg-white"
                                                                        />
                                                                        <InputError message={form.errors[key]} />
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        ) : (
                                            <div className="grid gap-x-5 gap-y-4 md:grid-cols-2 xl:grid-cols-12">
                                                {section.keys.map((key) => {
                                                    const field = fieldByKey.get(key)!;
                                                    const [, label, type] = field;
                                                    return (
                                                        <div
                                                            key={key}
                                                            className={key === 'address' ? 'md:col-span-2 xl:col-span-12' : 'xl:col-span-4'}
                                                        >
                                                            <Label
                                                                htmlFor={key}
                                                                className="mb-1.5 block text-[10px] font-bold tracking-wide text-slate-500 uppercase"
                                                            >
                                                                {label}
                                                            </Label>
                                                            {key === 'plant_code' ? (
                                                                <div className="relative">
                                                                    <Search className="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-slate-400" />
                                                                    <Input
                                                                        id={key}
                                                                        role="combobox"
                                                                        aria-expanded={plantDropdownOpen}
                                                                        aria-controls="plant-options"
                                                                        autoComplete="off"
                                                                        value={plantSearch}
                                                                        onFocus={() => setPlantDropdownOpen(true)}
                                                                        onBlur={() => setPlantDropdownOpen(false)}
                                                                        onChange={(event) => {
                                                                            setPlantSearch(event.target.value);
                                                                            form.setData('plant_code', '');
                                                                            setPlantDropdownOpen(true);
                                                                        }}
                                                                        className="h-10 border-slate-300 bg-white pr-10 pl-10 shadow-sm focus-visible:border-cyan-500 focus-visible:ring-cyan-500/20"
                                                                    />
                                                                    {plantDropdownOpen && (
                                                                        <div
                                                                            id="plant-options"
                                                                            role="listbox"
                                                                            className="absolute z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-md border border-slate-200 bg-white p-1 shadow-xl"
                                                                        >
                                                                            {filteredPlants.length ? (
                                                                                filteredPlants.map((plant) => (
                                                                                    <button
                                                                                        key={plant.company_code}
                                                                                        type="button"
                                                                                        role="option"
                                                                                        aria-selected={form.data.plant_code === plant.company_code}
                                                                                        onMouseDown={(event) => event.preventDefault()}
                                                                                        onClick={() => {
                                                                                            form.setData('plant_code', plant.company_code);
                                                                                            setPlantSearch(plantLabel(plant));
                                                                                            setPlantDropdownOpen(false);
                                                                                        }}
                                                                                        className="flex w-full items-center justify-between rounded px-3 py-2 text-left text-sm hover:bg-cyan-50 focus:bg-cyan-50 focus:outline-none"
                                                                                    >
                                                                                        <span className="font-semibold text-slate-800">
                                                                                            {plant.company_name}
                                                                                        </span>
                                                                                        <span className="font-mono text-xs text-slate-500">
                                                                                            {plant.company_code}
                                                                                        </span>
                                                                                    </button>
                                                                                ))
                                                                            ) : (
                                                                                <p className="px-3 py-3 text-sm text-slate-500">No plants found.</p>
                                                                            )}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            ) : employeeLevelKeys.includes(key as EmployeeLevelField) ? (
                                                                <Select
                                                                    value={String(form.data[key] || 'none')}
                                                                    onValueChange={(value) =>
                                                                        form.setData(key, (value === 'none' ? '' : value) as never)
                                                                    }
                                                                >
                                                                    <SelectTrigger id={key} className="h-10 border-slate-300 bg-white shadow-sm">
                                                                        <SelectValue placeholder="Select employee" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        <SelectItem value="none">Not assigned</SelectItem>
                                                                        {employees.map((employee) => (
                                                                            <SelectItem key={employee.unique_id} value={String(employee.unique_id)}>
                                                                                {employee.name} ({employee.unique_id})
                                                                            </SelectItem>
                                                                        ))}
                                                                    </SelectContent>
                                                                </Select>
                                                            ) : (
                                                                <Input
                                                                    id={key}
                                                                    type={type ?? 'text'}
                                                                    step={type === 'number' ? '0.001' : undefined}
                                                                    placeholder={label}
                                                                    value={String(form.data[key])}
                                                                    onChange={(event) => form.setData(key, event.target.value as never)}
                                                                    className="h-10 border-slate-300 bg-white shadow-sm focus-visible:border-cyan-500 focus-visible:ring-cyan-500/20"
                                                                />
                                                            )}
                                                            <InputError message={form.errors[key]} />
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </fieldset>
                                ))}
                            </div>
                        </div>
                        <DialogFooter className={masterDialogFooterClass}>
                            <div className="mr-auto hidden text-left sm:block">
                                <p className="text-[9px] font-bold tracking-[0.08em] text-slate-400 uppercase">Current section</p>
                                <p className="text-xs font-bold text-slate-700">
                                    {formStep + 1} of {customerSteps.length} · {customerSteps[formStep]}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                                className="h-10 w-full border-slate-300 bg-white px-5 font-semibold sm:w-auto"
                            >
                                Cancel
                            </Button>
                            <div className="flex w-full gap-2 sm:w-auto">
                                {formStep > 0 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-10 flex-1 px-5 font-semibold sm:flex-none"
                                        onClick={() => setFormStep((step) => step - 1)}
                                    >
                                        <ChevronLeft className="size-4" /> Back
                                    </Button>
                                )}
                                {formStep < customerSteps.length - 1 ? (
                                    <Button
                                        type="button"
                                        onClick={() => setFormStep((step) => Math.min(step + 1, customerSteps.length - 1))}
                                        className="bg-brand-teal hover:bg-brand-plum h-10 flex-1 px-6 font-semibold text-white shadow-sm sm:flex-none"
                                    >
                                        Next <ChevronRight className="size-4" />
                                    </Button>
                                ) : (
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                        className="bg-brand-teal hover:bg-brand-plum h-10 flex-1 px-6 font-semibold text-white shadow-sm sm:flex-none"
                                    >
                                        {form.processing ? 'Saving…' : editing ? 'Save changes' : 'Save customer'}
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
