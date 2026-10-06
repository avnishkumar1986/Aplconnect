import { useEffect, useMemo, useRef, useState } from 'react';
import { csrfToken } from '../shared/Controls';

function BulkCheckbox({ checked, indeterminate, onChange, label }) {
    const ref = useRef(null);

    useEffect(() => {
        if (ref.current) ref.current.indeterminate = indeterminate;
    }, [indeterminate]);

    return <label className="role-permission-check role-permission-bulk">
        <input ref={ref} type="checkbox" checked={checked} onChange={event => onChange(event.target.checked)} />
        <span>{label}</span>
    </label>;
}

export default function RoleForm(p) {
    const [name, setName] = useState(p.name ?? '');
    const [companyIds, setCompanyIds] = useState(p.companyIds ?? []);
    const [departmentId, setDepartmentId] = useState(p.departmentId ?? '');
    const [designationId, setDesignationId] = useState(p.designationId ?? '');
    const [selectedPermissions, setSelectedPermissions] = useState(() => new Set([
        ...p.permissions.filter(permission => permission.selected).map(permission => permission.name),
        'dashboard.view',
    ]));
    const [saving, setSaving] = useState(false);
    const designations = useMemo(() => p.designations.filter(item => !departmentId || !item.departmentId || item.departmentId === departmentId), [p.designations, departmentId]);
    const permissionActions = useMemo(() => {
        const standard = ['view', 'create', 'edit', 'delete'];
        const additional = p.permissions
            .map(permission => permission.name.split('.').at(-1))
            .filter(action => !standard.includes(action));
        return [...standard, ...new Set(additional)].sort((left, right) => {
            const leftIndex = standard.indexOf(left);
            const rightIndex = standard.indexOf(right);
            if (leftIndex !== -1 || rightIndex !== -1) return (leftIndex === -1 ? 99 : leftIndex) - (rightIndex === -1 ? 99 : rightIndex);
            return left.localeCompare(right);
        });
    }, [p.permissions]);
    const permissionMatrix = useMemo(() => {
        const modules = {};
        p.permissions.forEach(permission => {
            const parts = permission.name.split('.');
            const action = parts.length > 1 ? parts.pop() : 'view';
            const module = parts.join('-');
            modules[module] ??= {};
            modules[module][action] = permission;
        });
        return Object.entries(modules).sort(([left], [right]) => left.localeCompare(right));
    }, [p.permissions]);
    const error = field => p.errors?.[field]?.[0];
    const toggleCompany = value => setCompanyIds(current => current.includes(value) ? current.filter(id => id !== value) : [...current, value]);
    const companySummary = companyIds.length ? p.companies.filter(item => companyIds.includes(item.value)).map(item => item.label).join(', ') : 'All companies';
    const editablePermissions = useMemo(() => p.permissions.filter(permission => permission.name !== 'dashboard.view'), [p.permissions]);
    const selectionState = permissions => {
        const selectedCount = permissions.filter(permission => selectedPermissions.has(permission.name)).length;
        return { checked: permissions.length > 0 && selectedCount === permissions.length, indeterminate: selectedCount > 0 && selectedCount < permissions.length };
    };
    const setPermissions = (permissions, checked) => setSelectedPermissions(current => {
        const next = new Set(current);
        permissions.forEach(permission => checked ? next.add(permission.name) : next.delete(permission.name));
        next.add('dashboard.view');
        return next;
    });
    const togglePermission = (permission, checked) => setSelectedPermissions(current => {
        const next = new Set(current);
        const parts = permission.name.split('.');
        const action = parts.pop();
        const module = parts.join('.');
        if (checked) {
            next.add(permission.name);
            if (action !== 'view') next.add(`${module}.view`);
        } else {
            next.delete(permission.name);
            if (action === 'view') permissionActions.slice(1).forEach(item => next.delete(`${module}.${item}`));
        }
        next.add('dashboard.view');
        return next;
    });

    return <>
        <h1 className="mb-6 text-3xl font-bold">{p.editing ? 'Edit role' : 'Create role'}</h1>
        <form className="card" method="POST" action={p.action} onSubmit={() => setSaving(true)}>
            <input type="hidden" name="_token" value={csrfToken()} />
            {p.editing && <input type="hidden" name="_method" value="PUT" />}
            <div className="corporate-form-grid">
                <div className="form-field"><label className="label" htmlFor="role-name">Role name</label><input className="input" id="role-name" name="name" value={name} onChange={event => setName(event.target.value)} required />{error('name') && <p className="mt-1 text-sm text-red-600">{error('name')}</p>}</div>
                <div className="form-field"><label className="label">Companies</label><details className="role-company-dropdown"><summary>{companySummary}</summary><div className="role-company-options">{p.companies.map(item => <label key={item.value}><input type="checkbox" name="company_ids[]" value={item.value} checked={companyIds.includes(item.value)} onChange={event => { toggleCompany(item.value); event.currentTarget.closest('details')?.removeAttribute('open'); }} /><span>{item.label}</span></label>)}</div></details>{error('company_ids') && <p className="mt-1 text-sm text-red-600">{error('company_ids')}</p>}</div>
                <div className="form-field"><label className="label" htmlFor="role-department">Department</label><select className="input" id="role-department" name="department_id" value={departmentId} onChange={event => { setDepartmentId(event.target.value); setDesignationId(''); }}><option value="">All departments</option>{p.departments.map(item => <option key={item.value} value={item.value}>{item.label}</option>)}</select>{error('department_id') && <p className="mt-1 text-sm text-red-600">{error('department_id')}</p>}</div>
                <div className="form-field"><label className="label" htmlFor="role-designation">Designation</label><select className="input" id="role-designation" name="designation_id" value={designationId} onChange={event => setDesignationId(event.target.value)}><option value="">All designations</option>{designations.map(item => <option key={item.value} value={item.value}>{item.label}</option>)}</select>{error('designation_id') && <p className="mt-1 text-sm text-red-600">{error('designation_id')}</p>}</div>
            </div>
            <h2 className="mb-3 mt-6 font-bold">Permissions</h2>
            <div className="wizard-record-table-wrap">
                <table className="wizard-record-table role-permission-table">
                    <thead><tr>
                        <th><BulkCheckbox {...selectionState(editablePermissions)} onChange={checked => setPermissions(editablePermissions, checked)} label="Select all" /></th>
                        {permissionActions.map(action => {
                            const permissions = editablePermissions.filter(permission => permission.name.endsWith(`.${action}`));
                            return <th key={action}><BulkCheckbox {...selectionState(permissions)} onChange={checked => setPermissions(permissions, checked)} label={action.replaceAll('_', ' ')} /></th>;
                        })}
                    </tr></thead>
                    <tbody>{permissionMatrix.map(([module, actions]) => <tr key={module}>
                        <td><BulkCheckbox {...selectionState(Object.values(actions).filter(permission => permission.name !== 'dashboard.view'))} onChange={checked => setPermissions(Object.values(actions).filter(permission => permission.name !== 'dashboard.view'), checked)} label={module.replaceAll('-', ' ').replace(/\b\w/g, letter => letter.toUpperCase())} /></td>
                        {permissionActions.map(action => <td key={action}>
                            {actions[action] ? <label className="role-permission-check" title={actions[action].name}><input type="checkbox" name="permissions[]" value={actions[action].name} checked={selectedPermissions.has(actions[action].name)} onChange={event => togglePermission(actions[action], event.target.checked)} disabled={actions[action].name === 'dashboard.view'} />{actions[action].name === 'dashboard.view' && <input type="hidden" name="permissions[]" value="dashboard.view" />}<span>{action.replaceAll('_', ' ')}</span></label> : <span className="text-slate-300">—</span>}
                        </td>)}
                    </tr>)}</tbody>
                </table>
            </div>
            <div className="mt-6 flex gap-3"><a className="btn-secondary" href={p.cancelUrl}>Cancel</a><button className="btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save role'}</button></div>
        </form>
    </>;
}
