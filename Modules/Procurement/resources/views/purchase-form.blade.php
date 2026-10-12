@extends('layout.app')
@section('title', 'Add Purchase')
@section('content')
    <x-page-header title="Add Purchase" description="Record a planned material purchase and its expected delivery.">
        <span class="crud-breadcrumb">Procurement <b>/</b> Purchases <b>/</b> Add</span></x-page-header>
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>
    @endif
    <style>
        .purchase-manual-card{padding:18px}.purchase-manual-head{margin:-18px -18px 16px;padding:16px 18px;border-bottom:1px solid #dbe5ef;background:#fff}.purchase-manual-head h3{margin:0;color:#0d294d;font-size:14px;font-weight:800}.purchase-manual-head p{margin:8px 0 0;color:#55708f;font-size:12px}.purchase-context-grid,.purchase-order-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.purchase-context-grid{padding-bottom:16px}.purchase-order-grid{padding:16px;border:1px solid #d8e3ee;border-radius:8px;background:#f6f9fc}.purchase-manual-actions{display:flex;justify-content:flex-end;gap:10px;padding-top:16px}@media(max-width:1000px){.purchase-context-grid,.purchase-order-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:640px){.purchase-context-grid,.purchase-order-grid{grid-template-columns:1fr}.purchase-manual-card{padding:14px}.purchase-manual-head{margin:-14px -14px 14px}.purchase-manual-actions{flex-direction:column-reverse}.purchase-manual-actions .btn-primary,.purchase-manual-actions .btn-secondary{width:100%;justify-content:center}}
    </style>
    <form class="card purchase-manual-card" method="POST" action="{{ route('admin.procurement.purchases.store') }}" data-procurement-form
        data-stock-map="{{ json_encode($stockMap) }}">
        @csrf
        <input type="hidden" name="submission_token" value="{{ old('submission_token', $token) }}">
        <input type="hidden" name="reference" value="">
        <header class="purchase-manual-head">
            <div>
                <h3>▾ Create purchase order manually</h3>
                <p>Select the purchasing context, supplier and delivery details. The purchase order reference is generated automatically.</p>
            </div>
        </header>
        <div class="purchase-context-grid">
            <div class="form-field"><label class="label">Company</label><select class="input" name="company_id" required
                    data-company data-searchable-select>
                    <option value="">— Select —</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->company_code }}" @selected(old('company_id', $prefill['company_code'] ?? $companies->first()?->company_code) == $company->company_code)>
                            {{ $company->company_code }} - {{ $company->company_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field"><label class="label">Plant</label><select class="input" name="plant_id" required
                    data-plant data-searchable-select data-selected="{{ old('plant_id', $prefill['plant_code'] ?? null) }}">
                    <option value="">— Select company first —</option>
                    @foreach ($plants as $plant)
                        <option value="{{ $plant->company_code }}" data-company-code="{{ $plant->parent_company_code }}"
                            data-company-id="{{ $plant->parent_company_code }}">{{ $plant->company_code }} -
                            {{ $plant->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field"><label class="label">Material Group</label><select class="input" name="material_group"
                    required data-material-group data-searchable-select>
                    <option value="">— Select group —</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group }}" @selected(old('material_group', $prefill['material_group'] ?? null) === $group)>{{ $group }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field"><label class="label">Material</label><select class="input" name="material" required
                    data-material data-purchase-material data-searchable-select data-material-search
                    data-selected="{{ old('material', $prefill['material'] ?? null) }}" disabled>
                    <option value="">— Select material group first —</option>
                    @foreach ($materials as $material)
                        <option value="{{ $material->matnr }}" data-group="{{ $material->matkl }}"
                            data-search-terms="{{ $material->matnr }} {{ $material->maktx }} {{ $material->matkl }}">{{ $material->matnr }}
                            - {{ $material->maktx }}{{ $material->matkl ? ' · '.$material->matkl : '' }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="purchase-order-grid">
            <div class="form-field"><label class="label">Vendor *</label><select id="purchase-vendor" class="input"
                    name="vendor_name" required data-vendor data-local-vendor-select data-searchable-select data-selected="{{ old('vendor_name') }}"
                    disabled>
                    <option value="">— Select material first —</option>
                    @foreach ($vendors as $vendor)
                        <option value="{{ $vendor->vendor_name }}" data-material="{{ $vendor->material_code }}"
                            data-purchase-orders="{{ $vendor->purchase_orders }}">{{ $vendor->vendor_code }} -
                            {{ $vendor->vendor_name }}</option>
                    @endforeach
                    @foreach($materials->pluck('matnr')->unique() as $localMaterial)
                        <option value="__other__" data-material="{{ $localMaterial }}">Other / Local vendor</option>
                    @endforeach
                </select></div>
            <div class="form-field" data-local-vendor-field hidden><label class="label">Local vendor name *</label><input class="input" name="local_vendor_name" value="{{ old('local_vendor_name') }}" maxlength="255" placeholder="Enter vendor name"></div>
            <div class="form-field"><label class="label">Quantity (MT) *</label><input class="input" name="quantity_mt"
                    type="number" min="0.001" step="0.001"
                    value="{{ old('quantity_mt', $prefill['quantity_mt'] ?? null) }}" required></div>
            <div class="form-field"><label class="label">Expected delivery *</label><input class="input"
                    name="expected_delivery_date" type="date" min="{{ now()->toDateString() }}"
                    value="{{ old('expected_delivery_date') }}" required></div>
            <div class="form-field"><label class="label">Order status *</label><select class="input" name="delivery_status" required>
                    @foreach($deliveryStatuses as $value => $label)<option value="{{ $value }}" @selected(old('delivery_status', 'planned') === $value)>{{ $label }}</option>@endforeach
                </select></div>
        </div>
        <div class="purchase-manual-actions"><a class="btn-secondary"
                href="{{ route('admin.procurement.overview') }}">Cancel</a><button class="btn-primary">Save manual purchase order</button></div>
    </form>
@endsection

@push('scripts')
    <script defer
        src="{{ asset('js/procurement-purchase-order.js') }}?v={{ filemtime(public_path('js/procurement-purchase-order.js')) }}">
    </script>
    <script>
        const initialiseLocalVendor = () => document.querySelectorAll('[data-local-vendor-select]').forEach(select => {
            const form = select.closest('form');
            const field = form?.querySelector('[data-local-vendor-field]');
            const input = field?.querySelector('input');
            const toggle = () => {
                const local = select.value === '__other__';
                if (field) field.hidden = !local;
                if (input) { input.required = local; if (!local) input.value = ''; }
            };
            select.addEventListener('change', toggle);
            window.jQuery?.(select).on('select2:select select2:clear', toggle);
            toggle();
        });
        document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', initialiseLocalVendor, { once:true }) : initialiseLocalVendor();
    </script>
@endpush
