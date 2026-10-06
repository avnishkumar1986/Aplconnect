@extends('layout.app')
@section('title', 'Edit Supplier Receipt')
@section('content')
<x-page-header title="Edit Supplier Receipt" description="Update the supplier, quantity, expected delivery, or order status."><span class="crud-breadcrumb">Procurement <b>/</b> Supplier Receipts <b>/</b> Edit</span></x-page-header>
@if($errors->any())<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif
<form class="card consumption-record-form" method="POST" action="{{ route('admin.procurement.purchases.update', $record->id) }}">
    @csrf @method('PUT')
    <div class="corporate-form-grid">
        <div class="form-field"><label class="label">Purchase Order Number</label><input class="input bg-slate-50" value="{{ $record->reference }}" readonly aria-readonly="true"><small class="mt-1 block text-xs text-slate-500">Automatically generated and cannot be edited.</small></div>
        <div class="form-field"><label class="label">Vendor</label><select class="input" name="vendor_name" required data-searchable-select data-selected="{{ old('vendor_name', $record->vendor_name) }}">@foreach($vendors as $vendor)<option value="{{ $vendor->vendor_name }}" @selected(old('vendor_name', $record->vendor_name) === $vendor->vendor_name)>{{ $vendor->vendor_code ? $vendor->vendor_code.' - ' : '' }}{{ $vendor->vendor_name }}</option>@endforeach</select></div>
        <div class="form-field"><label class="label">Quantity (MT)</label><input class="input" name="quantity_mt" type="number" min="0.001" step="0.001" value="{{ old('quantity_mt', $record->quantity_mt) }}" required></div>
        <div class="form-field"><label class="label">Expected delivery</label><input class="input" name="expected_delivery_date" type="date" value="{{ old('expected_delivery_date', $record->expected_delivery_date) }}" required></div>
        <div class="form-field"><label class="label">Order status</label><select class="input" name="delivery_status" required>@foreach($deliveryStatuses as $value => $label)<option value="{{ $value }}" @selected(old('delivery_status', $record->delivery_status) === $value)>{{ $label }}</option>@endforeach</select></div>
    </div>
    <div class="consumption-form-actions"><a class="btn-secondary" href="{{ $record->consumption_id ? route('admin.procurement.consumptions.show', $record->consumption_id) : route('admin.procurement.purchases') }}">Cancel</a><button class="btn-primary">Update receipt</button></div>
</form>
@endsection
