@extends('layout.app')
@section('title', 'Monthly Material Plan')
@include('procurement::planning-styles')
@section('content')
@php
    $closing = $record->projected_closing_mt;
    $cover = $record->stock_cover_days;
    $grossShortage = $record->current_stock_mt === null ? null : max(0, round((float) $record->quantity_mt - (float) $record->current_stock_mt, 3));
    $fmt = fn($value, $decimals = 3) => $value === null ? 'N/A' : number_format((float) $value, $decimals);
    $planningDate = \Illuminate\Support\Carbon::parse($record->consumption_date);
    $planningMonthStart = ($record->period_type ?? 'monthly') === 'datewise' ? $planningDate->copy() : $planningDate->copy()->startOfMonth();
    $planningMonthEnd = ($record->period_type ?? 'monthly') === 'datewise' && $record->effective_to ? \Illuminate\Support\Carbon::parse($record->effective_to) : $planningDate->copy()->endOfMonth();
@endphp
<div class="planning-page">
    <div class="planning-topline">
        <div>
            <div class="planning-eyebrow">Procurement / Daily Consumption / Monthly Plan</div>
            <h2>{{ $record->material }} <span style="font-size:12px;font-weight:400">· {{ $record->plant_code }} —
                    {{ $record->plant_name }}</span></h2>
            <p class="planning-help">{{ $planningMonthStart->format('d M Y') }}
                to {{ $planningMonthEnd->format('d M Y') }} ·
                Version {{ $version }} · {{ $record->company_name }}{{ $record->status ? '' : ' · Inactive' }}</p>
        </div><a class="planning-btn" href="{{ route('admin.procurement.consumptions') }}">← Supply Planning</a>
    </div>
    @if($errors->any())
    <div class="planning-alert">{{ $errors->first() }}</div>@endif
    <div class="planning-grid">
        <article class="planning-stat">
            <p>Opening stock</p><strong>{{ $fmt($record->current_stock_mt) }}</strong><small>MT · stock sync</small>
        </article>
        <article class="planning-stat">
            <p>Total required</p>
            <strong>{{ $fmt($record->quantity_mt) }}</strong><small>{{ $fmt($record->daily_consumption_mt) }} MT/day ×
                {{ $record->day_count }} days</small>
        </article>
        <article
            class="planning-stat {{ $closing !== null && $closing < 0 ? 'planning-stat-short' : 'planning-stat-good' }}">
            <p>Projected closing</p><strong>{{ $fmt($closing) }}</strong><small>MT · after planned
                buying/imports</small>
        </article>
        <article
            class="planning-stat {{ $grossShortage > 0 ? 'planning-stat-short' : 'planning-stat-good' }}">
            <p>Gross shortage</p><strong>{{ $fmt($grossShortage) }}</strong><small>MT · before mapped purchase orders</small>
        </article>
        <article class="planning-stat planning-stat-buy">
            <p>Remaining shortage</p><strong>{{ $fmt($record->extra_required_mt) }}</strong><small>MT · suggested additional buy</small>
        </article>
        <article class="planning-stat">
            <p>Stock cover</p><strong>{{ $cover === null ? '—' : $fmt($cover, 1) }}</strong><small>Days</small>
        </article>
    </div>
    <section class="planning-panel">
        <header class="planning-panel-head">
            <div>
                <div class="planning-eyebrow">Monthly Planning Parameters</div>
                <h3>Plan Inputs</h3>
                <p class="planning-help">Review synchronized stock and maintain consumption assumptions for this period.
                </p>
            </div><span class="planning-version">Version {{ $version }}</span>
        </header>
        <form method="POST" action="{{ route('admin.procurement.consumptions.plan.update', $record->id) }}">@csrf
            @method('PUT')<input type="hidden" name="version" value="{{ $version }}">
            <fieldset @disabled(!$record->status || !auth()->user()->can('procurement.daily_consumption.edit'))>
                <div class="planning-inputs">
                    <div class="planning-field"><label for="plan-stock">Opening stock from sync (MT)</label><input
                            id="plan-stock" value="{{ $fmt($record->current_stock_mt) }}" readonly><small>Maintained by
                            stock synchronization.</small></div>
                    <div class="planning-field"><label for="plan-daily">Daily consumption (MT/day) *</label><input
                            id="plan-daily" type="number" name="daily_consumption_mt" min="0" max="999999999"
                            step="0.000001" value="{{ old('daily_consumption_mt', $record->daily_consumption_mt) }}"
                            required><small>Average planned consumption for one working day.</small></div>
                    <div class="planning-field"><label for="plan-days">Working days *</label><input id="plan-days"
                            type="number" name="day_count" min="1" max="31"
                            value="{{ old('day_count', $record->day_count) }}" required><small>Allowed range: 1 to 31
                            days.</small></div>
                    <div class="planning-field"><label for="plan-remarks">Remarks</label><input id="plan-remarks"
                            name="remarks" maxlength="1000" value="{{ old('remarks', $record->remarks) }}"
                            placeholder="Add an optional planning note"><small>Include assumptions or operational
                            context.</small></div>
                </div>
                @can('procurement.daily_consumption.edit')@if($record->status)
                    <div class="planning-save"><button class="planning-btn planning-btn-primary">Save plan inputs</button>
                </div>@endif @endcan
            </fieldset>
        </form>
    </section>
    <details class="planning-panel" id="calculation-history" style="scroll-margin-top:80px">
        <summary>Current Month Calculation</summary>
        <div class="planning-scroll">
            <p class="planning-help" style="margin-bottom:12px">Live stock and shortage calculation for the selected planning month.</p>
            <table class="planning-table" id="supplier-receipts-datatable" data-simple-datatable data-sno data-search-placeholder="Search supplier receipts..." data-order-column="5">
                <thead>
                    <tr>
                        <th>S.No.</th>
                        <th>Status</th>
                        <th>Shortage calculated for</th>
                        <th>Calculated at</th>
                        <th>Daily consumption</th>
                        <th>Working days</th>
                        <th>Opening stock</th>
                        <th>Total required</th>
                        <th>Closing stock</th>
                        <th>Gross shortage</th>
                        <th>Remaining shortage</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td><strong>Current</strong></td>
                        <td><strong>{{ \Illuminate\Support\Carbon::parse($record->consumption_date)->format('M Y') }}</strong><br><span class="planning-muted">{{ \Illuminate\Support\Carbon::parse($record->consumption_date)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($record->consumption_date)->endOfMonth()->format('d M Y') }}</span></td>
                        <td>{{ $record->stock_calculated_at ?? now() }}</td>
                        <td class="planning-number">{{ $fmt($record->daily_consumption_mt) }}</td>
                        <td>{{ $record->day_count }}</td>
                        <td class="planning-number">{{ $fmt($record->current_stock_mt) }}</td>
                        <td class="planning-number">{{ $fmt($record->quantity_mt) }}</td>
                        <td class="planning-number">{{ $fmt($record->projected_closing_mt) }}</td>
                        <td class="planning-number"><strong>{{ $fmt($grossShortage) }}</strong></td>
                        <td class="planning-number"><strong>{{ $fmt($record->extra_required_mt) }}</strong></td>
                        <td>Live calculation including this plan's valid supplier receipts</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </details>
    @can('procurement.purchases.create')@if($record->status)
        <details class="planning-panel" open>
            <summary>Create purchase order manually</summary>
            <div>
                <p class="planning-help" style="margin-bottom:12px">The remaining shortage is suggested automatically, but no purchase order is created automatically. Select the supplier/PO and save the required quantity manually for MATNR <strong>{{ $record->material }}</strong>.</p>
                <form method="POST" action="{{ route('admin.procurement.purchases.store') }}" data-procurement-form>
                    @csrf
                    <input type="hidden" name="submission_token" value="{{ old('submission_token', $purchaseToken) }}">
                    <input type="hidden" name="return_to_consumption" value="{{ $record->id }}">
                    <input type="hidden" name="company_id" value="{{ $record->company_code }}">
                    <input type="hidden" name="plant_id" value="{{ $record->plant_code }}">
                    <input type="hidden" name="material_group" value="{{ $record->material_group }}">
                    <input type="hidden" name="material" value="{{ $record->material }}" data-purchase-material>
                    <div class="planning-inputs">
                        <div class="planning-field"><label for="receipt-vendor">Vendor *</label><select id="receipt-vendor" name="vendor_name" required data-searchable-select data-selected="{{ old('vendor_name') }}"><option value="">Select vendor</option>@foreach($vendors as $vendor)<option value="{{ $vendor->vendor_name }}">{{ $vendor->vendor_code ? $vendor->vendor_code.' - ' : '' }}{{ $vendor->vendor_name }}</option>@endforeach</select><small>{{ $vendors->isEmpty() ? 'No active vendors found.' : 'All active manual and API-synced vendors.' }}</small></div>
                        <div class="planning-field"><label for="receipt-po">Purchase Order Number</label><input id="receipt-po" name="reference" value="{{ old('reference', $purchaseReference) }}" readonly aria-readonly="true"><small>Unique reference generated automatically; editing is not permitted.</small></div>
                        <div class="planning-field"><label for="receipt-qty">Quantity (MT) *</label><input id="receipt-qty" name="quantity_mt" type="number" min="0.001" step="0.001" value="{{ old('quantity_mt', $record->extra_required_mt) }}" required></div>
                        <div class="planning-field"><label for="receipt-delivery">Expected delivery *</label><input id="receipt-delivery" name="expected_delivery_date" type="date" min="{{ $planningMonthStart->toDateString() }}" max="{{ $planningMonthEnd->toDateString() }}" value="{{ old('expected_delivery_date') }}" required><small>Choose a date from {{ $planningMonthStart->format('d M Y') }} to {{ $planningMonthEnd->format('d M Y') }} only.</small></div>
                        <div class="planning-field"><label for="receipt-status">Order status *</label><select id="receipt-status" name="delivery_status" required><option value="planned" @selected(old('delivery_status', 'planned') === 'planned')>Planned</option><option value="in_transit" @selected(old('delivery_status') === 'in_transit')>In Transit</option><option value="partially_received" @selected(old('delivery_status') === 'partially_received')>Partially Received</option><option value="received" @selected(old('delivery_status') === 'received')>Received</option><option value="cancelled" @selected(old('delivery_status') === 'cancelled')>Cancelled</option></select></div>
                    </div>
                    <div class="planning-save"><button class="planning-btn planning-btn-primary">Save manual purchase order</button></div>
                </form>
            </div>
    </details>@endif @endcan
    <section class="planning-panel">
        <header class="planning-panel-head">
            <div>
                <h3>Supplier receipts</h3>
                <p class="planning-help">Purchase orders planned for {{ \Illuminate\Support\Carbon::parse($record->consumption_date)->format('M Y') }}. ETA is shown separately and does not determine the planned month.</p>
            </div>
        </header>
        <div class="planning-scroll">
            <table class="planning-table">
                <thead>
                    <tr>
                        <th>Supplier / PO</th>
                        <th>Planned for</th>
                        <th>Source</th>
                        <th>Expected MT</th>
                        <th>ETA</th>
                        <th>Stage / received MT</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($receipts as $receipt)
                        <tr>
                            <td>{{ $receipt->vendor_name }}
                                <div class="planning-muted">{{ $receipt->reference ?: 'Number pending' }}</div>
                            </td>
                            <td><strong>{{ $planningMonthStart->format('d M') }} – {{ $planningMonthEnd->format('d M Y') }}</strong></td>
                            <td>{{ ($receipt->source_type ?? 'manual') === 'api' ? 'API purchase order' : 'Manual purchase order' }}</td>
                            <td class="planning-number">{{ $fmt($receipt->quantity_mt) }}</td>
                            <td>{{ $receipt->expected_delivery_date }}</td>
                            <td><span class="delivery-flag delivery-flag-{{ $receipt->delivery_status }}">{{ ($receipt->source_type ?? 'manual') === 'api' ? ($receipt->release_state ?: 'API synchronized') : ($deliveryStatuses[$receipt->delivery_status] ?? ucfirst(str_replace('_', ' ', $receipt->delivery_status))) }}</span> / —</td>
                            <td><div class="planning-actions">
                                @if(($receipt->source_type ?? 'manual') === 'manual') @can('procurement.purchases.create')
                                    <a class="planning-btn" data-form-modal data-modal-title="Edit Supplier Receipt" data-modal-size="standard" href="{{ route('admin.procurement.purchases.edit', $receipt->id) }}">Edit</a>
                                    <form method="POST" action="{{ route('admin.procurement.purchases.destroy', $receipt->id) }}" onsubmit="return confirm('Delete this supplier receipt? The shortage will be recalculated.')">@csrf @method('DELETE')<button class="planning-btn" type="submit" style="border-color:#fecaca;color:#b91c1c">Delete</button></form>
                                @endcan @else <span class="planning-muted">Managed by API</span> @endif
                            </div></td>
                    </tr>@endforeach
                    @if($receipts->isEmpty())
                        <tr>
                            <td colspan="7" class="planning-empty">
                                {{ auth()->user()->can('procurement.purchases.view') ? 'No supplier receipts available for this period.' : 'Purchase viewing permission is required.' }}
                            </td>
                    </tr>@endif
                </tbody>
            </table>
        </div>
    </section>
    <section class="planning-panel" id="mou-lifting" style="scroll-margin-top:80px">
        <header class="planning-panel-head">
            <div>
                <div class="planning-eyebrow">Supplier Commitment</div>
                <h3>MOU Lifting Plan</h3>
                <p class="planning-help">Commitments for {{ \Illuminate\Support\Carbon::parse($record->consumption_date)->format('M Y') }}. Manual POs are matched by this planned month and supplier; MOU quantities do not affect stock until a PO is created.</p>
            </div><span class="planning-version">Reference Plan</span>
        </header>
        @can('procurement.daily_consumption.edit')@if($record->status)
            <form method="POST" action="{{ route('admin.procurement.consumptions.mou.store', $record->id) }}">@csrf<div
                    class="planning-inputs">
                    <div class="planning-field"><label for="mou-dealer">Dealer *</label><select id="mou-dealer"
                            name="vendor_name" required>
                            <option value="">Select vendor</option>@foreach($vendors as $vendor)
                                <option value="{{ $vendor->vendor_name }}" @selected(old('vendor_name') === $vendor->vendor_name)>
                            {{ $vendor->vendor_code ? $vendor->vendor_code.' - ' : '' }}{{ $vendor->vendor_name }}</option>@endforeach
                        </select><small>Approved vendors mapped to MATNR {{ $record->material }}.</small></div>
                    <div class="planning-field"><label for="mou-qty">Planned quantity (MT) *</label><input id="mou-qty"
                            name="quantity_mt" type="number" min="0.001" step="0.001" value="{{ old('quantity_mt') }}"
                            placeholder="0.000" required></div>
                    <div class="planning-field"><label for="mou-remarks">Remarks</label><input id="mou-remarks"
                            name="remarks" maxlength="1000" placeholder="Add an optional note"></div>
                    <div class="planning-save" style="align-items:center"><button class="planning-btn planning-btn-primary"
                            @disabled($vendors->isEmpty())>Save lifting plan</button></div>
                </div>
        </form>@endif @endcan
        <div class="planning-scroll" style="margin-top:14px">
            <table class="planning-table">
                <thead>
                    <tr>
                        <th>Planned month</th>
                        <th>Dealer</th>
                        <th>MOU commitment (MT)</th>
                        <th>Manual PO quantity (MT)</th>
                        <th>Balance to order (MT)</th>
                        <th>Mapped POs</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>@forelse($mouPlans as $plan)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($record->consumption_date)->format('M Y') }}</td>
                        <td>{{ $plan->vendor_name }}</td>
                        <td class="planning-number">{{ $fmt($plan->quantity_mt) }}</td>
                        <td class="planning-number">{{ $fmt($plan->ordered_quantity_mt ?? 0) }}</td>
                        <td class="planning-number">{{ $fmt(max(0, (float) $plan->quantity_mt - (float) ($plan->ordered_quantity_mt ?? 0))) }}</td>
                        <td>{{ $plan->purchase_orders ?: 'No PO created' }}</td>
                        <td>{{ $plan->remarks ?: '—' }}</td>
                </tr>@empty<tr>
                        <td colspan="7" class="planning-empty">No lifting plan has been saved.</td>
                    </tr>@endforelse @if($mouPlans->isNotEmpty())
                        <tr>
                            <td></td>
                            <td><strong>Total</strong></td>
                            <td class="planning-number"><strong>{{ $fmt($mouPlans->sum('quantity_mt')) }}</strong></td>
                            <td class="planning-number"><strong>{{ $fmt($mouPlans->sum(fn ($plan) => (float) ($plan->ordered_quantity_mt ?? 0))) }}</strong></td>
                            <td class="planning-number"><strong>{{ $fmt($mouPlans->sum(fn ($plan) => max(0, (float) $plan->quantity_mt - (float) ($plan->ordered_quantity_mt ?? 0)))) }}</strong></td>
                            <td></td>
                            <td></td>
                    </tr>@endif
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script defer src="{{ asset('js/procurement-purchase-order.js') }}?v={{ filemtime(public_path('js/procurement-purchase-order.js')) }}"></script>
@endpush
