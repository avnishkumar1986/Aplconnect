@extends('layout.app')
@section('title', 'Add Purchase')
@section('content')
<x-page-header title="Add Purchase" description="Record a planned material purchase and its expected delivery."><span class="crud-breadcrumb">Procurement <b>/</b> Purchases <b>/</b> Add</span></x-page-header>
@if($errors->any())<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif
<form class="card" method="POST" action="{{ route('admin.procurement.purchases.store') }}" data-procurement-form data-stock-map="{{ json_encode($stockMap) }}">
@csrf
<input type="hidden" name="submission_token" value="{{ old('submission_token', $token) }}">
<div class="corporate-form-grid">
<div class="form-field"><label class="label">Company</label><select class="input" name="company_id" required data-company data-searchable-select><option value="">— Select —</option>@foreach($companies as $company)<option value="{{ $company->company_code }}" @selected(old('company_id', $prefill['company_code'] ?? $companies->first()?->company_code) == $company->company_code)>{{ $company->company_code }} - {{ $company->company_name }}</option>@endforeach</select></div>
<div class="form-field"><label class="label">Plant</label><select class="input" name="plant_id" required data-plant data-searchable-select data-selected="{{ old('plant_id', $prefill['plant_code'] ?? null) }}"><option value="">— Select company first —</option>@foreach($plants as $plant)<option value="{{ $plant->company_code }}" data-company-code="{{ $plant->parent_company_code }}" data-company-id="{{ $plant->parent_company_code }}">{{ $plant->company_code }} - {{ $plant->name }}</option>@endforeach</select></div>
<div class="form-field"><label class="label">Material Group</label><select class="input" name="material_group" required data-material-group data-searchable-select><option value="">— Select group —</option>@foreach($groups as $group)<option value="{{ $group }}" @selected(old('material_group', $prefill['material_group'] ?? null) === $group)>{{ $group }}</option>@endforeach</select></div>
<div class="form-field"><label class="label">Material</label><select class="input" name="material" required data-material data-purchase-material data-searchable-select data-selected="{{ old('material', $prefill['material'] ?? null) }}" disabled><option value="">— Select material group first —</option>@foreach($materials as $material)<option value="{{ $material->matnr }}" data-group="{{ $material->matkl }}">{{ $material->matnr }} - {{ $material->maktx }}</option>@endforeach</select></div>
<div class="form-field"><label class="label">Vendor</label><select id="purchase-vendor" class="input" name="vendor_name" required data-vendor data-searchable-select data-selected="{{ old('vendor_name') }}" onchange="document.getElementById('purchase-po').value=(this.selectedOptions[0]?.dataset.purchaseOrders || '').split(',')[0].trim()" disabled><option value="">— Select material first —</option>@foreach($vendors as $vendor)<option value="{{ $vendor->vendor_name }}" data-material="{{ $vendor->material_code }}" data-purchase-orders="{{ $vendor->purchase_orders }}">{{ $vendor->vendor_code }} - {{ $vendor->vendor_name }}</option>@endforeach</select></div>
<div class="form-field"><label class="label">SAP Purchase Order</label><input id="purchase-po" class="input" name="reference" data-single-vendor-purchase-order value="{{ old('reference') }}" placeholder="Select a vendor first" readonly required></div>
<div class="form-field"><label class="label">Quantity (MT)</label><input class="input" name="quantity_mt" type="number" min="0.001" step="0.001" value="{{ old('quantity_mt', $prefill['quantity_mt'] ?? null) }}" required></div>
<div class="form-field"><label class="label">Current stock (MT)</label><input class="input bg-slate-50" readonly data-current-stock placeholder="Select plant and material"></div>
<div class="form-field"><label class="label">Expected delivery</label><input class="input" name="expected_delivery_date" type="date" min="{{ now()->toDateString() }}" value="{{ old('expected_delivery_date') }}" required></div>
<input type="hidden" name="delivery_status" value="planned">
<div class="form-field form-field-wide"><label class="label">Remarks</label><textarea class="input" name="remarks" maxlength="1000">{{ old('remarks') }}</textarea></div>
</div>
<div class="mt-5 flex gap-3"><a class="btn-secondary" href="{{ route('admin.procurement.purchases') }}">Cancel</a><button class="btn-primary">Save purchase</button></div>
</form>
@endsection

@push('scripts')
<script defer src="{{ asset('js/procurement-purchase-order.js') }}?v={{ filemtime(public_path('js/procurement-purchase-order.js')) }}"></script>
<script>
setTimeout(() => {
    const form = document.querySelector('form[data-procurement-form]');
    const vendor = form?.querySelector('[data-vendor]');
    const purchaseOrder = form?.querySelector('[data-single-vendor-purchase-order]');
    if (!vendor || !purchaseOrder || purchaseOrder.dataset.singlePoReady === 'true') return;
    purchaseOrder.dataset.singlePoReady = 'true';
    const refreshPurchaseOrders = () => {
        const values = [...new Set((vendor.selectedOptions[0]?.dataset.purchaseOrders || '')
            .split(',').map(value => value.trim()).filter(Boolean))];
        purchaseOrder.value = values[0] || '';
        purchaseOrder.placeholder = vendor.value ? 'No SAP purchase order found' : 'Select a vendor first';
    };
    vendor.addEventListener('change', refreshPurchaseOrders);
    window.jQuery?.(vendor).on('select2:select select2:clear', refreshPurchaseOrders);
    refreshPurchaseOrders();
}, 0);
</script>
@endpush
