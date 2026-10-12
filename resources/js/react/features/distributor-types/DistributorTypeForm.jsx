import { useState } from 'react';
import { Shell, Text, Select, firstError } from '../shared/Controls';

export default function DistributorTypeForm(p) {
    const [form, setForm] = useState(p.values);
    const [saving, setSaving] = useState(false);
    const update = (name) => (event) => setForm({ ...form, [name]: event.target.value });
    const error = (name) => firstError(p.errors, name);

    return <Shell {...p} title={p.editing ? 'Edit distributor type' : 'Create distributor type'} submitting={saving} onSubmit={() => setSaving(true)}>
        <Text label="Type code" name="code" value={form.code ?? ''} onChange={update('code')} error={error('code')} help="Use a short unique code, for example regional." required />
        <Text label="Type name" name="name" value={form.name ?? ''} onChange={update('name')} error={error('name')} required />
        <div className="form-field form-field-wide"><label className="label" htmlFor="description">Description</label><textarea className="input" id="description" name="description" rows="4" value={form.description ?? ''} onChange={update('description')} />{error('description') && <p className="mt-1 text-sm text-red-600">{error('description')}</p>}</div>
        <Select label="Status" name="status" value={String(form.status ?? '1')} onChange={update('status')} error={error('status')} options={p.options?.status ?? [{ value: '1', label: 'Active' }, { value: '0', label: 'Inactive' }]} required />
    </Shell>;
}
