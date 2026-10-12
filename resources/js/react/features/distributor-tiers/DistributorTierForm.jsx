import { useState } from 'react';
import { Shell, Text, Select, firstError } from '../shared/Controls';

export default function DistributorTierForm(p) {
    const [form, setForm] = useState(p.values);
    const [saving, setSaving] = useState(false);
    const update = (name) => (event) => setForm({ ...form, [name]: event.target.value });
    const error = (name) => firstError(p.errors, name);

    return <Shell {...p} title={p.editing ? 'Edit distributor tier' : 'Create distributor tier'} submitting={saving} onSubmit={() => setSaving(true)}>
        <Text label="Tier code" name="code" value={form.code ?? ''} onChange={update('code')} error={error('code')} help="Use a short unique code, for example gold." required />
        <Text label="Tier name" name="name" value={form.name ?? ''} onChange={update('name')} error={error('name')} required />
        <Text label="Minimum annual sales (INR)" name="minimum_annual_sales" type="number" value={form.minimum_annual_sales ?? ''} onChange={update('minimum_annual_sales')} error={error('minimum_annual_sales')} />
        <Text label="Maximum discount (%)" name="maximum_discount_percent" type="number" value={form.maximum_discount_percent ?? ''} onChange={update('maximum_discount_percent')} error={error('maximum_discount_percent')} />
        <div className="form-field form-field-wide"><label className="label" htmlFor="description">Description</label><textarea className="input" id="description" name="description" rows="4" value={form.description ?? ''} onChange={update('description')} />{error('description') && <p className="mt-1 text-sm text-red-600">{error('description')}</p>}</div>
        <Select label="Status" name="status" value={String(form.status ?? '1')} onChange={update('status')} error={error('status')} options={p.options?.status ?? [{ value: '1', label: 'Active' }, { value: '0', label: 'Inactive' }]} required />
    </Shell>;
}
