@extends('layout.app')
@section('title', 'Purchase Entry')
@section('content')
    <x-page-header title="Purchase Entry" description="Manage planned purchases and delivery dates.">
        <span class="crud-breadcrumb">Procurement <b>/</b> Purchases</span></x-page-header>
    <div class="datatable-page-actions" data-datatable-toolbar-for="purchases-datatable">
        @can('procurement.purchases.create')
            <a class="crud-add" data-form-modal data-modal-size="large" data-modal-title="Add Purchase" href="{{ route('admin.procurement.purchases.create') }}">＋ Add Purchase</a>
        @endcan
    </div>
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="table nowrap min-w-[1100px]" id="purchases-datatable" data-simple-datatable data-responsive-control data-sno data-order-column="2" data-search-placeholder="Search purchases and stock...">
                <thead>
                    <tr>
                        <th aria-label="Details"></th>
                        <th>S.NO.</th>
                        <th>Date</th>
                        <th>Company</th>
                        <th>Plant</th>
                        <th>Vendor</th>
                        <th>Material</th>
                        <th>Quantity MT</th>
                        <th>Current Stock MT</th>
                        <th>Delivery</th>
                        <th>Status</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entries as $entry)
                        <tr>
                            <td></td>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $entry->purchase_date }}</td>
                            <td>{{ $entry->company_name }}</td>
                            <td>{{ $entry->plant_name }}</td>
                            <td>{{ $entry->vendor_name }}</td>
                            <td>{{ $materials[$entry->material] ?? $entry->material }}</td>
                            <td>{{ number_format($entry->quantity_mt, 3) }}</td>
                            <td>{{ $entry->current_stock_mt === null ? 'N/A' : number_format($entry->current_stock_mt, 3) }}</td>
                            <td>{{ $entry->expected_delivery_date }}</td>
                            <td>
                                @can('procurement.purchases.create')
                                <form method="POST" action="{{ route('admin.procurement.purchases.delivery-status', $entry->id) }}">@csrf @method('PATCH')
                                    <select class="delivery-flag delivery-flag-{{ $entry->delivery_status }}" name="delivery_status" onchange="this.form.submit()" aria-label="Delivery status">
                                        @foreach($deliveryStatuses as $value => $label)<option value="{{ $value }}" @selected($entry->delivery_status === $value)>{{ $label }}</option>@endforeach
                                    </select>
                                </form>
                                @else<span class="delivery-flag delivery-flag-{{ $entry->delivery_status }}">{{ $deliveryStatuses[$entry->delivery_status] ?? ucfirst($entry->delivery_status) }}</span>@endcan
                            </td>
                            <td>{{ $entry->reference ?: '—' }}</td>
                    </tr>@endforeach
                </tbody>
            </table>
        </div>
    </div>
    <style>
        .delivery-flag{display:inline-flex;min-width:118px;border:1px solid transparent;border-radius:999px;padding:6px 10px;font-size:11px;font-weight:800;line-height:1;color:#334155}.delivery-flag-planned{background:#eef2ff;border-color:#c7d2fe;color:#4338ca}.delivery-flag-in_transit{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}.delivery-flag-partially_received{background:#fffbeb;border-color:#fde68a;color:#a16207}.delivery-flag-received{background:#ecfdf5;border-color:#a7f3d0;color:#047857}.delivery-flag-cancelled{background:#fef2f2;border-color:#fecaca;color:#b91c1c}
    </style>
@endsection
