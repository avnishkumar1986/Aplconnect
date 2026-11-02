import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

type Permission = { id: number; name: string };
type Company = { id: number; company_code: string; company_name: string };
type Department = { id: number; department_name: string };
type Designation = { id: number; designation_name: string; department_id: number | null };
type Role = { id: number; name: string; company_ids: number[]; department_id: number | null; designation_id: number | null; permissions: string[] };
const actions = ['view', 'create', 'edit', 'delete', 'unlock', 'run', 'all_companies'] as const;
type Action = (typeof actions)[number];
const actionLabels: Record<Action, string> = {
    view: 'View',
    create: 'Create',
    edit: 'Edit',
    delete: 'Delete',
    unlock: 'Unlock',
    run: 'Run',
    all_companies: 'All Companies',
};
const matrixCheckboxClass =
    'size-4 border-slate-300 data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=indeterminate]:border-primary data-[state=indeterminate]:bg-primary';
const permissionAction = (name: string): Action | 'manage' | null => {
    const suffix = name.split('.').at(-1)?.toLowerCase().replaceAll('-', '_');
    if (suffix === 'manage') return 'manage';
    if (suffix === 'allcompanies') return 'all_companies';
    return actions.find((action) => action === suffix) ?? null;
};
const moduleName = (name: string) => {
    const parts = name.split('.');
    if (permissionAction(name)) parts.pop();
    return parts.join('.');
};
const titleCase = (value: string) => value.replace(/[._-]+/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase());
const categoryDetails = {
    general: { label: 'General Access', description: 'Core navigation and dashboard access', order: 1 },
    group_overview: { label: 'Group Overview', description: 'Group-level dashboard access', order: 2 },
    production_capacity: { label: 'Production & Capacity', description: 'Production output, utilization, and capacity performance', order: 3 },
    procurement: { label: 'Procurement', description: 'Materials, inventory, purchasing, and planning operations', order: 4 },
    api: { label: 'API Integrations', description: 'External data connections for materials, production, quality, stock, and vendors', order: 5 },
    sales: { label: 'Sales', description: 'Customers, orders, dispatches, and sales operations', order: 6 },
    sales_distribution: { label: 'Sales & Distribution', description: 'Sales planning, fulfilment, dispatch, and regional distribution', order: 7 },
    support: { label: 'Support', description: 'Support tickets, internal notes, reporting, and service operations', order: 8 },
    corporate_chat: { label: 'Corporate Chat', description: 'Internal messaging and collaboration controls', order: 9 },
    administration: { label: 'Administration & Security', description: 'Users, roles, master data, and system controls', order: 10 },
} as const;
type PermissionCategory = keyof typeof categoryDetails;
const permissionCategory = (module: string): PermissionCategory => {
    if (module === 'dashboard') return 'general';
    if (module === 'group_overview' || module.startsWith('group_overview.')) return 'group_overview';
    if (module === 'production_capacity' || module.startsWith('production_capacity.')) return 'production_capacity';
    if (module.split('.').includes('api')) return 'api';
    if (module.startsWith('procurement.')) return 'procurement';
    if (module.startsWith('sales.')) return 'sales';
    if (module === 'sales_distribution' || module.startsWith('sales_distribution.')) return 'sales_distribution';
    if (module === 'support' || module.startsWith('support.')) return 'support';
    if (module === 'corporate_chat' || module.startsWith('corporate_chat.')) return 'corporate_chat';
    return 'administration';
};
const professionalModuleName = (module: string) => {
    const category = permissionCategory(module);
    const scopedName =
        ['group_overview', 'production_capacity', 'procurement', 'sales', 'sales_distribution', 'support', 'corporate_chat'].includes(category) &&
        module.includes('.')
            ? module.split('.').slice(1).join('.')
            : category === 'api'
              ? module
                    .split('.')
                    .filter((part) => !['procurement', 'sales', 'api'].includes(part))
                    .join('.')
              : module;
    return titleCase(scopedName)
        .replace(/\bApi\b/g, 'API')
        .replace(/\bMou\b/g, 'MOU');
};

export default function RoleForm({
    role,
    permissions,
    companies,
    departments,
    designations,
}: {
    role: Role | null;
    permissions: Permission[];
    companies: Company[];
    departments: Department[];
    designations: Designation[];
}) {
    const isEditing = role !== null;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Roles & permissions', href: '/admin/roles' },
        { title: isEditing ? 'Edit role' : 'Create role', href: isEditing ? `/admin/roles/${role.id}/edit` : '/admin/roles/create' },
    ];
    const { data, setData, post, put, processing, errors } = useForm({
        name: role?.name ?? '',
        company_ids: role?.company_ids ?? ([] as number[]),
        department_id: role?.department_id ? String(role.department_id) : '',
        designation_id: role?.designation_id ? String(role.designation_id) : '',
        permissions: role?.permissions ?? ([] as string[]),
    });
    const [openCategory, setOpenCategory] = useState<PermissionCategory | null>('general');
    const rows = Array.from(new Set(permissions.map((permission) => moduleName(permission.name)))).map((module) => ({
        module,
        cells: Object.fromEntries(
            actions.map((action) => [
                action,
                permissions.filter((permission) => {
                    const parsed = permissionAction(permission.name);
                    return (
                        moduleName(permission.name) === module &&
                        (parsed === action || (parsed === 'manage' && ['create', 'edit', 'delete'].includes(action)))
                    );
                }),
            ]),
        ) as Record<Action, Permission[]>,
    }));
    const groupedRows = Object.entries(categoryDetails)
        .map(([key, details]) => ({
            key: key as PermissionCategory,
            ...details,
            rows: rows
                .filter((row) => permissionCategory(row.module) === key)
                .sort((left, right) => professionalModuleName(left.module).localeCompare(professionalModuleName(right.module))),
        }))
        .filter((group) => group.rows.length > 0)
        .sort((left, right) => left.order - right.order);
    const availablePermissions = permissions.map((permission) => permission.name);
    const hiddenAssignedPermissions = data.permissions.filter((permission) => !availablePermissions.includes(permission));
    const selectedAvailableCount = availablePermissions.filter((permission) => data.permissions.includes(permission)).length;
    const allSelected = availablePermissions.length > 0 && availablePermissions.every((permission) => data.permissions.includes(permission));
    const selectedCompanies = companies.filter((company) => data.company_ids.includes(company.id));
    const filteredDesignations = designations.filter(
        (designation) => !data.department_id || designation.department_id === null || String(designation.department_id) === data.department_id,
    );
    const setPermissionNames = (names: string[], checked: boolean) =>
        setData(
            'permissions',
            checked ? Array.from(new Set([...data.permissions, ...names])) : data.permissions.filter((permission) => !names.includes(permission)),
        );
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        if (isEditing) put(`/admin/roles/${role.id}`);
        else post('/admin/roles');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isEditing ? `Edit ${role.name}` : 'Create role'} />
            <form onSubmit={submit} className="p-4 sm:p-5 md:p-6">
                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="grid gap-x-4 gap-y-3 border-b p-4 md:grid-cols-2">
                        <div>
                            <Label htmlFor="role-name" className="mb-1.5 text-xs font-semibold">
                                Role Name
                            </Label>
                            <Input
                                id="role-name"
                                autoFocus
                                value={data.name}
                                disabled={role?.name.toLowerCase() === 'owner'}
                                onChange={(event) => setData('name', event.target.value)}
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div>
                            <Label className="mb-1.5 text-xs font-semibold">Companies</Label>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button type="button" variant="outline" className="w-full justify-between bg-white font-normal">
                                        <span className="truncate">
                                            {selectedCompanies.length
                                                ? selectedCompanies.map((company) => `${company.company_code} - ${company.company_name}`).join(', ')
                                                : 'Default — All companies'}
                                        </span>
                                        <ChevronDown className="size-4 shrink-0 opacity-50" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start" className="max-h-72 w-[var(--radix-dropdown-menu-trigger-width)] overflow-y-auto">
                                    {companies.map((company) => (
                                        <DropdownMenuCheckboxItem
                                            key={company.id}
                                            checked={data.company_ids.includes(company.id)}
                                            onSelect={(event) => event.preventDefault()}
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'company_ids',
                                                    checked ? [...data.company_ids, company.id] : data.company_ids.filter((id) => id !== company.id),
                                                )
                                            }
                                        >
                                            {company.company_code} - {company.company_name}
                                        </DropdownMenuCheckboxItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <InputError message={errors.company_ids} />
                        </div>
                        <div>
                            <Label className="mb-1.5 text-xs font-semibold">Department</Label>
                            <Select
                                value={data.department_id || 'all'}
                                onValueChange={(value) => {
                                    setData('department_id', value === 'all' ? '' : value);
                                    setData('designation_id', '');
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Default — All departments</SelectItem>
                                    {departments.map((department) => (
                                        <SelectItem key={department.id} value={String(department.id)}>
                                            {department.department_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.department_id} />
                        </div>
                        <div>
                            <Label className="mb-1.5 text-xs font-semibold">Designation</Label>
                            <Select
                                value={data.designation_id || 'all'}
                                onValueChange={(value) => setData('designation_id', value === 'all' ? '' : value)}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Default — All designations</SelectItem>
                                    {filteredDesignations.map((designation) => (
                                        <SelectItem key={designation.id} value={String(designation.id)}>
                                            {designation.designation_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.designation_id} />
                        </div>
                    </div>
                    <div className="space-y-3 bg-slate-50/60 p-4">
                        <div className="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 className="text-sm font-bold text-slate-900">Role permissions</h2>
                                <p className="mt-0.5 text-xs text-slate-500">Control access by business area and permitted action.</p>
                            </div>
                            <div className="flex items-center gap-3">
                                <span className="bg-primary/10 text-primary rounded-full px-2.5 py-1 text-[11px] font-bold">
                                    {selectedAvailableCount} of {availablePermissions.length} selected
                                </span>
                                <label className="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700">
                                    <Checkbox
                                        className={matrixCheckboxClass}
                                        checked={allSelected || (data.permissions.length > 0 ? 'indeterminate' : false)}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'permissions',
                                                checked ? [...hiddenAssignedPermissions, ...availablePermissions] : hiddenAssignedPermissions,
                                            )
                                        }
                                    />
                                    Select all permissions
                                </label>
                            </div>
                        </div>
                        <div className="space-y-2.5">
                            {groupedRows.map((group) => {
                                const groupPermissions = Array.from(
                                    new Set(
                                        group.rows.flatMap((row) =>
                                            actions.flatMap((action) => row.cells[action].map((permission) => permission.name)),
                                        ),
                                    ),
                                );
                                const groupSelected = groupPermissions.every((permission) => data.permissions.includes(permission));
                                const groupPartial = groupPermissions.some((permission) => data.permissions.includes(permission));
                                const selectedCount = groupPermissions.filter((permission) => data.permissions.includes(permission)).length;
                                return (
                                    <section
                                        key={group.key}
                                        className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md"
                                    >
                                        <div
                                            className={`flex items-center gap-3 px-4 py-3 ${openCategory === group.key ? 'bg-primary/5 border-b border-slate-200' : ''}`}
                                        >
                                            <Checkbox
                                                className={matrixCheckboxClass}
                                                checked={groupSelected || (groupPartial ? 'indeterminate' : false)}
                                                onCheckedChange={(checked) => setPermissionNames(groupPermissions, Boolean(checked))}
                                                aria-label={`Select all ${group.label} permissions`}
                                            />
                                            <button
                                                type="button"
                                                className="flex min-w-0 flex-1 items-center justify-between gap-4 text-left"
                                                onClick={() => setOpenCategory((current) => (current === group.key ? null : group.key))}
                                                aria-expanded={openCategory === group.key}
                                            >
                                                <span className="min-w-0">
                                                    <span className="block text-xs font-bold tracking-wide text-slate-900 uppercase">
                                                        {group.label}
                                                    </span>
                                                    <span className="mt-0.5 block truncate text-[11px] text-slate-500">{group.description}</span>
                                                </span>
                                                <span className="flex shrink-0 items-center gap-3">
                                                    <span className="hidden text-[11px] font-medium text-slate-500 sm:inline">
                                                        {selectedCount}/{groupPermissions.length} permissions
                                                    </span>
                                                    <span className="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600">
                                                        {group.rows.length} {group.rows.length === 1 ? 'module' : 'modules'}
                                                    </span>
                                                    <ChevronDown
                                                        className={`size-4 text-slate-500 transition-transform ${openCategory === group.key ? 'rotate-180' : ''}`}
                                                    />
                                                </span>
                                            </button>
                                        </div>
                                        {openCategory === group.key && (
                                            <div className="overflow-x-auto">
                                                <table className="w-full min-w-[900px] table-fixed text-xs">
                                                    <thead className="bg-slate-50 text-left text-[10px] font-bold tracking-wide text-slate-500 uppercase">
                                                        <tr>
                                                            <th className="w-16 px-3 py-2.5 text-center">S.No.</th>
                                                            <th className="w-[30%] px-3 py-2.5">Permission module</th>
                                                            {actions.map((action) => (
                                                                <th key={action} className="px-3 py-2.5">
                                                                    {actionLabels[action]}
                                                                </th>
                                                            ))}
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-slate-100">
                                                        {group.rows.map((row) => {
                                                            const rowPermissions = Array.from(
                                                                new Set(
                                                                    actions.flatMap((action) =>
                                                                        row.cells[action].map((permission) => permission.name),
                                                                    ),
                                                                ),
                                                            );
                                                            const rowSelected =
                                                                rowPermissions.length > 0 &&
                                                                rowPermissions.every((permission) => data.permissions.includes(permission));
                                                            const rowPartial = rowPermissions.some((permission) =>
                                                                data.permissions.includes(permission),
                                                            );
                                                            return (
                                                                <tr key={row.module} className="hover:bg-slate-50/80">
                                                                    <td className="px-3 py-3 text-center font-medium text-slate-400">
                                                                        {rows.findIndex((item) => item.module === row.module) + 1}
                                                                    </td>
                                                                    <td className="px-3 py-3">
                                                                        <label className="flex items-center gap-2 font-semibold text-slate-700">
                                                                            <Checkbox
                                                                                className={matrixCheckboxClass}
                                                                                checked={rowSelected || (rowPartial ? 'indeterminate' : false)}
                                                                                onCheckedChange={(checked) =>
                                                                                    setPermissionNames(rowPermissions, Boolean(checked))
                                                                                }
                                                                            />
                                                                            {professionalModuleName(row.module)}
                                                                        </label>
                                                                    </td>
                                                                    {actions.map((action) => {
                                                                        const names = row.cells[action].map((permission) => permission.name);
                                                                        return names.length ? (
                                                                            <td key={action} className="px-3 py-3">
                                                                                <label className="flex items-center gap-2 text-slate-600">
                                                                                    <Checkbox
                                                                                        className={matrixCheckboxClass}
                                                                                        checked={names.every((name) =>
                                                                                            data.permissions.includes(name),
                                                                                        )}
                                                                                        onCheckedChange={(checked) =>
                                                                                            setPermissionNames(names, Boolean(checked))
                                                                                        }
                                                                                    />
                                                                                    {actionLabels[action]}
                                                                                </label>
                                                                            </td>
                                                                        ) : (
                                                                            <td key={action} className="px-3 py-3 text-slate-300">
                                                                                —
                                                                            </td>
                                                                        );
                                                                    })}
                                                                </tr>
                                                            );
                                                        })}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                    </section>
                                );
                            })}
                            {!rows.length && (
                                <p className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                                    No active permissions are configured.
                                </p>
                            )}
                        </div>
                        <InputError message={errors.permissions} />
                    </div>
                    <div className="flex gap-2 border-t px-4 py-3">
                        <Button type="button" variant="outline" onClick={() => router.visit('/admin/roles')}>
                            Cancel
                        </Button>
                        <Button disabled={processing}>{processing ? 'Saving…' : 'Save role'}</Button>
                    </div>
                </section>
            </form>
        </AppLayout>
    );
}
