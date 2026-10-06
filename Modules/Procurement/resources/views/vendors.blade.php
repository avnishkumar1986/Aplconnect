@extends('layout.app')
@section('title', 'Vendor Master')
@include('procurement::planning-styles')
@push('styles')
<style>
.vendor-summary{display:grid;grid-template-columns:repeat(4,minmax(130px,1fr)) minmax(210px,1.35fr);gap:10px;margin-bottom:16px}.vendor-stat{position:relative;display:grid;grid-template-columns:38px minmax(0,1fr);align-items:center;gap:11px;overflow:hidden;border:1px solid #d7e1ed;border-radius:8px;background:#fff;padding:13px 15px;box-shadow:0 2px 7px rgba(24,56,92,.04)}.vendor-stat:before{content:'';position:absolute;inset:0 auto 0 0;width:3px;background:#2c597f}.vendor-stat-icon{display:grid!important;width:38px;height:38px;place-items:center;border:1px solid #c9e3e6;border-radius:10px;background:#edf8f9;color:var(--theme-primary,var(--apl-teal,#159aa6))}.vendor-stat-icon svg{width:19px;height:19px}.vendor-stat-copy>span{display:block;color:#71849b;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.08em}.vendor-stat strong{display:block;margin-top:5px;color:#18385c;font-size:18px;line-height:1.1}.vendor-stat small{display:block;margin-top:4px;color:#71849b;font-size:10px}.vendor-tree{display:grid;gap:8px}.vendor-node{border:1px solid #d7e1ed;border-radius:8px;background:#fff;overflow:hidden;box-shadow:0 1px 3px rgba(24,56,92,.03);transition:border-color .15s,box-shadow .15s}.vendor-node:hover{border-color:#b9cce0;box-shadow:0 3px 10px rgba(24,56,92,.06)}.vendor-node summary{display:grid;grid-template-columns:52px 64px minmax(300px,1.25fr) minmax(280px,1fr);align-items:center;gap:0;cursor:pointer;list-style:none;padding:14px 18px 14px 0;color:#18385c;position:relative}.vendor-node summary::-webkit-details-marker{display:none}.vendor-node summary:before{display:none!important}.vendor-node[open]>summary{background:#f6f9fc;border-bottom:1px solid #d7e1ed}.vendor-flat-head{display:grid;grid-template-columns:52px 64px minmax(300px,1.25fr) minmax(280px,1fr);align-items:center;gap:0;background:#e8eff8;border:1px solid #cad8e7;border-radius:7px;padding:11px 18px 11px 0;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.09em;color:#516f8e}.vendor-flat-head>span:first-child,.vendor-flat-head>span:nth-child(2){text-align:center}.vendor-disclosure{display:block;width:28px;height:28px;margin:auto;border:1px solid #bad5df;border-radius:50%;background-color:#f3fbfc;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%230d7d87' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m9 18 6-6-6-6'/%3E%3C/svg%3E");background-position:center;background-repeat:no-repeat;background-size:14px;box-shadow:0 1px 3px rgba(24,66,87,.08);transition:.15s}.vendor-node summary:hover .vendor-disclosure{border-color:var(--apl-teal);background-color:#e7f7f8}.vendor-node[open]>summary .vendor-disclosure{border-color:var(--apl-teal);background-color:var(--apl-teal);background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E")}.vendor-serial{text-align:center;color:#607089;font-size:12px;font-weight:650;font-variant-numeric:tabular-nums}.vendor-context{display:flex;align-items:flex-start;gap:10px;padding-right:18px}.vendor-material-code{font-size:12px;font-weight:800;line-height:1.4}.vendor-material-code small{display:block;color:#71849b;font-size:10px;font-weight:500;margin-top:2px}.vendor-path{display:flex;flex-wrap:wrap;gap:5px;margin-top:7px;color:#58738f;font-size:9px}.vendor-path span{display:inline-flex;align-items:center;border:1px solid #d8e2ec;border-radius:4px;background:#f7f9fc;padding:3px 6px}.vendor-list{display:flex;flex-wrap:wrap;gap:7px;align-content:flex-start}.vendor-chip{display:inline-flex;align-items:center;gap:7px;border:1px solid #cad9e7;border-radius:6px;background:#fff;padding:6px 9px;font-size:10px;font-weight:700}.vendor-chip:before{content:attr(data-initial);display:grid;place-items:center;width:20px;height:20px;border-radius:5px;background:#e7eff8;color:#244e73;font-size:9px;font-weight:800}.vendor-chip small{font-weight:500;color:#71849b}.vendor-more{display:inline-flex;align-items:center;border-radius:6px;background:#eef3f8;padding:6px 9px;color:#58738f;font-size:10px;font-weight:700}.vendor-node-body{padding:14px;background:#fbfcfe}.vendor-tree-actions{display:flex;gap:7px;align-items:center}.vendor-tree .planning-table{border-radius:6px;overflow:hidden}.vendor-tree .planning-table td{background:#fff}@media(max-width:1000px){.vendor-summary{grid-template-columns:repeat(2,1fr)}.vendor-node summary,.vendor-flat-head{grid-template-columns:52px 64px minmax(0,1fr)}.vendor-flat-head span:last-child,.vendor-list{display:none}}@media(max-width:600px){.vendor-summary{grid-template-columns:1fr}.vendor-node summary,.vendor-flat-head{grid-template-columns:48px 48px minmax(0,1fr)}.vendor-node summary{padding-right:10px}.vendor-context{padding-right:0}.vendor-path span{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}}
</style>
@endpush

@section('content')
<div class="planning-page">
    <x-page-header title="Vendor Master" description="SAP vendors grouped by company, plant, material group and material.">
        <span class="crud-breadcrumb">Master Control <b>/</b> Vendors</span>
    </x-page-header>

    <div class="vendor-summary">
        <div class="vendor-stat"><span class="vendor-stat-icon"><x-app-icon name="company" /></span><div class="vendor-stat-copy"><span>Companies</span><strong>{{ number_format($summary->companies) }}</strong><small>Active vendor coverage</small></div></div>
        <div class="vendor-stat"><span class="vendor-stat-icon"><x-app-icon name="plant" /></span><div class="vendor-stat-copy"><span>Plants</span><strong>{{ number_format($summary->plants) }}</strong><small>Mapped locations</small></div></div>
        <div class="vendor-stat"><span class="vendor-stat-icon"><x-app-icon name="materials" /></span><div class="vendor-stat-copy"><span>Materials</span><strong>{{ number_format($summary->materials) }}</strong><small>With vendor mapping</small></div></div>
        <div class="vendor-stat"><span class="vendor-stat-icon"><x-app-icon name="users" /></span><div class="vendor-stat-copy"><span>Vendors</span><strong>{{ number_format($summary->vendors) }}</strong><small>Unique vendor codes</small></div></div>
        <div class="vendor-stat"><span class="vendor-stat-icon"><x-app-icon name="settings" /></span><div class="vendor-stat-copy"><span>Last synchronized</span><strong style="font-size:13px">{{ $summary->last_synced_at ? \Illuminate\Support\Carbon::parse($summary->last_synced_at)->format('d M Y, H:i') : 'Not synchronized' }}</strong><small>SAP Vendor API</small></div></div>
    </div>

    <form class="planning-toolbar" method="GET" data-vendor-filters>
        <div class="planning-filter" style="grid-template-columns:repeat(4,minmax(150px,1fr)) minmax(180px,1.2fr) auto auto">
            <div class="planning-field"><label for="vendor-company">Company</label>
                <select id="vendor-company" name="company" data-company data-searchable-select>
                    <option value="">All permitted companies</option>
                    @foreach($companies as $company)<option value="{{ $company->company_code }}" @selected((string) request('company', $companies->count() === 1 ? $companies->first()?->company_code : '') === (string) $company->company_code)>{{ $company->company_code }} - {{ $company->company_name }}</option>@endforeach
                </select>
            </div>
            <div class="planning-field"><label for="vendor-plant">Plant</label>
                <select id="vendor-plant" name="plant" data-plant data-selected="{{ request('plant') }}" data-searchable-select>
                    <option value="">All plants</option>
                    @foreach($plants as $plant)<option value="{{ $plant->company_code }}" data-company="{{ $plant->parent_company_code }}" @selected(request('plant') == $plant->company_code)>{{ $plant->company_code }} - {{ $plant->name }}</option>@endforeach
                </select>
            </div>
            <div class="planning-field"><label for="vendor-group">Material Group</label>
                <select id="vendor-group" name="material_group" data-group data-selected="{{ request('material_group') }}" data-searchable-select>
                    <option value="">All groups</option>
                    @foreach($groups as $group)<option value="{{ $group }}" @selected(request('material_group') == $group)>{{ $group }}</option>@endforeach
                </select>
            </div>
            <div class="planning-field"><label for="vendor-material">Material</label>
                <select id="vendor-material" name="material" data-material data-selected="{{ request('material') }}" data-searchable-select>
                    <option value="">All materials</option>
                    @foreach($materials as $material)<option value="{{ $material->matnr }}" data-plant="{{ $material->plant_code }}" data-group="{{ $material->matkl }}" @selected(request('material') == $material->matnr)>{{ $material->matnr }} - {{ $material->maktx }}</option>@endforeach
                </select>
            </div>
            <div class="planning-field"><label for="vendor-search">Search</label><input id="vendor-search" name="search" value="{{ request('search') }}" placeholder="Vendor, PO or material..."></div>
            <button class="planning-btn planning-btn-primary" type="submit">Apply</button>
            <a class="planning-btn" href="{{ route('admin.vendors.index') }}">Reset</a>
        </div>
    </form>

    <section class="planning-panel">
        <div class="planning-panel-head">
            <div><div class="planning-eyebrow">Vendor Registry</div><h2>{{ number_format($vendors->total()) }} grouped vendor mappings</h2></div>
            <div class="vendor-tree-actions"><button class="planning-btn" type="button" data-expand-vendors>Expand all</button><button class="planning-btn" type="button" data-collapse-vendors>Collapse all</button></div>
        </div>
        <div class="vendor-tree" data-vendor-tree>
            <div class="vendor-flat-head"><span aria-hidden="true"></span><span>S.NO.</span><span>Company / Plant / Material Group / Material</span><span>Mapped Vendors</span></div>
            @forelse($vendors->getCollection()->groupBy(fn($vendor) => implode('|', [$vendor->company_code, $vendor->plant_code, $vendor->material_group, $vendor->material_code])) as $materialVendors)
                @php($material = $materialVendors->first())
                <details class="vendor-node" @if(request('material') == $material->material_code) open @endif>
                    <summary>
                        <span class="vendor-disclosure" aria-hidden="true"></span>
                        <span class="vendor-serial">{{ ($vendors->firstItem() ?? 1) + $loop->index }}</span>
                        <div class="vendor-context"><div><div class="vendor-material-code">{{ $material->material_code }}<small>{{ $material->material_name ?: 'Description unavailable' }}</small></div><div class="vendor-path"><span>Company · {{ $material->company_code ?: 'Unmapped' }} · {{ $material->company_name ?: '—' }}</span><span>Plant · {{ $material->plant_code ?: 'Unmapped' }} · {{ $material->plant_name ?: '—' }}</span><span>Group · {{ $material->material_group ?: 'Unmapped' }}</span></div></div></div>
                        <div class="vendor-list">
                            @foreach($materialVendors->take(4) as $vendor)<span class="vendor-chip" data-initial="{{ str($vendor->vendor_name ?: $vendor->vendor_code ?: 'V')->substr(0, 1)->upper() }}">{{ $vendor->vendor_name ?: 'Unnamed vendor' }} @if($vendor->vendor_code)<small>{{ $vendor->vendor_code }}</small>@endif</span>@endforeach
                            @if($materialVendors->count() > 4)<span class="vendor-more">+{{ $materialVendors->count() - 4 }} more</span>@endif
                        </div>
                    </summary>
                    <div class="vendor-node-body planning-scroll"><table class="planning-table">
                        <thead><tr><th>Vendor</th><th>Vendor Code</th><th>Purchase Orders</th><th class="planning-number">Quantity (MT)</th><th>Last Synced</th><th>Status</th></tr></thead>
                        <tbody>@foreach($materialVendors as $vendor)<tr>
                            <td>{{ $vendor->vendor_name ?: 'Unnamed vendor' }}</td><td>{{ $vendor->vendor_code ?: '—' }}</td>
                            <td>{{ number_format($vendor->purchase_order_count) }}</td><td class="planning-number">{{ number_format((float)$vendor->total_quantity_mt, 3) }}</td>
                            <td>{{ $vendor->last_synced_at ? \Illuminate\Support\Carbon::parse($vendor->last_synced_at)->format('d M Y H:i') : '—' }}</td>
                            <td><span class="delivery-flag {{ $vendor->status ? 'delivery-flag-received' : 'delivery-flag-cancelled' }}">{{ $vendor->status ? 'Active' : 'Inactive' }}</span></td>
                        </tr>@endforeach</tbody>
                    </table></div>
                </details>
            @empty
                <div class="planning-table planning-empty">No vendor mappings found. Run the Vendor API synchronization to populate this view.</div>
            @endforelse
        </div>
        @if($vendors->hasPages())<div class="mt-4">{{ $vendors->links() }}</div>@endif
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-vendor-filters]');
    if (!form) return;
    const company = form.querySelector('[data-company]');
    const plant = form.querySelector('[data-plant]');
    const group = form.querySelector('[data-group]');
    const material = form.querySelector('[data-material]');
    const filterOptions = (select, predicate) => {
        [...select.options].forEach((option, index) => { if (index) option.hidden = option.disabled = !predicate(option); });
        if (select.selectedOptions[0]?.disabled) select.value = '';
        window.jQuery?.(select).trigger('change.select2');
    };
    const refresh = () => {
        filterOptions(plant, option => !company.value || option.dataset.company === company.value);
        filterOptions(material, option => (!plant.value || option.dataset.plant === plant.value) && (!group.value || option.dataset.group === group.value));
    };
    company.addEventListener('change', refresh); plant.addEventListener('change', refresh); group.addEventListener('change', refresh); refresh();
    document.querySelector('[data-expand-vendors]')?.addEventListener('click', () => document.querySelectorAll('[data-vendor-tree] details').forEach(node => node.open = true));
    document.querySelector('[data-collapse-vendors]')?.addEventListener('click', () => document.querySelectorAll('[data-vendor-tree] details').forEach(node => node.open = false));
});
</script>
@endpush
