@extends('layout.app')
@section('title', 'Material Balance Summary')
@section('content')
    <x-page-header title="Material Balance Summary"
        description="Plant-wise stock, buying and consumption plan based on current procurement data.">
        <span class="crud-breadcrumb">Procurement <b>/</b> Material Balance</span>
    </x-page-header>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('admin.procurement.material-balance') }}" class="card mb-5 balance-filter-card"
        aria-label="Material balance filters">
        <div class="balance-filter-grid">
            <div class="form-field">
                <label class="label" for="balance-company">Company</label>
                <select class="input" id="balance-company" name="company" data-searchable-select>
                    <option value="">All companies</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}" @selected((string) $company->id === $selectedCompany)>{{ $company->company_code }} -
                            {{ $company->company_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="label" for="balance-plant">Plant</label>
                <select class="input" id="balance-plant" name="plant" data-selected="{{ $selectedPlant }}"
                    data-searchable-select>
                    <option value="">All plants</option>
                    @foreach ($filterPlants as $plant)
                        <option value="{{ $plant->id }}" data-company="{{ $plant->company_id }}"
                            @selected((string) $plant->id === $selectedPlant)>{{ $plant->company_code }} - {{ $plant->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="label" for="balance-material">Material</label>
                <select class="input" id="balance-material" name="material" data-selected="{{ $selectedMaterial }}"
                    data-searchable-select data-material-search>
                    @foreach ($filterMaterials as $material)
                        <option value="{{ $material->matnr }}"
                            data-search-terms="{{ $material->matnr }} {{ $material->maktx }} {{ $material->category_name }}"
                            @selected($material->matnr === $selectedMaterial)>{{ $material->matnr }} -
                            {{ $material->maktx }}{{ $material->category_name ? ' · ' . $material->category_name : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="balance-filter-actions">
                <a class="btn-secondary" href="{{ route('admin.procurement.material-balance') }}">Reset filters</a>
                <button class="btn-primary" type="submit">Apply filters</button>
            </div>
        </div>
    </form>

    @php
        $filterQuery = [
            'company' => $selectedCompany,
            'plant' => $selectedPlant,
            'material' => $selectedMaterial,
        ];
        $selected = $materials->firstWhere('matnr', $selectedMaterial);
        $fmt = fn($value, $decimals = 3) => $value === null ? 'N/A' : number_format((float) $value, $decimals);
    @endphp

    <section class="balance-report-page" aria-label="Material balance report">
        <header class="balance-report-head">
            <div class="balance-report-actions"><a class="btn-secondary balance-export-btn" title="Export Excel"
                    aria-label="Export Excel"
                    href="{{ route('admin.procurement.material-balance.export-excel', $filterQuery) }}"><svg
                        viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z" />
                        <path d="M14 3v6h6M8 13l4 4m0-4-4 4" />
                    </svg><span class="sr-only">Export Excel</span></a><a class="btn-secondary balance-export-btn"
                    title="Export PDF" aria-label="Export PDF"
                    href="{{ route('admin.procurement.material-balance.export-pdf', $filterQuery) }}"><svg
                        viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z" />
                        <path d="M14 3v6h6M8 15h8M8 11h3" />
                    </svg><span class="sr-only">Export PDF</span></a></div>
            <p>{{ $months->first()->format('F Y') }} – {{ $months->last()->format('F Y') }}</p>
        </header>
        <section class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="card balance-summary-card balance-summary-material"><span
                    class="balance-summary-icon"><x-app-icon name="materials" /></span>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Material</p><strong
                        class="mt-2 block text-lg">{{ $selectedMaterial }}</strong><span
                        class="text-sm text-slate-500">{{ $selected?->maktx }}</span>
                </div>
            </article>
            <article class="card balance-summary-card balance-summary-horizon"><span
                    class="balance-summary-icon"><x-app-icon name="balance" /></span>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Planning horizon</p><strong
                        class="mt-2 block text-lg">{{ $months->first()->format('F Y') }} –
                        {{ $months->last()->format('F Y') }}</strong><span
                        class="text-sm text-slate-500">{{ $horizon }}-month planning view</span>
                </div>
            </article>
            <article class="card balance-summary-card balance-summary-plants"><span class="balance-summary-icon"><x-app-icon
                        name="plant" /></span>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Plants</p><strong
                        class="mt-2 block text-2xl">{{ $plants->count() }}</strong><span
                        class="text-sm text-slate-500">Plants included in planning</span>
                </div>
            </article>
            <article class="card balance-summary-card balance-summary-orders"><span class="balance-summary-icon"><x-app-icon
                        name="purchase-entry" /></span>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Manual POs</p><strong
                        class="mt-2 block text-2xl">{{ collect($matrix)->flatten(1)->sum(fn($cell) => $cell['orders']->count()) }}</strong><span
                        class="text-sm text-slate-500">Planned PO entries in this horizon</span>
                </div>
            </article>
        </section>

        <div class="balance-shell">
            <div class="balance-scroll">
                <table class="balance-table">
                    <thead>
                        <tr>
                            <th colspan="2" class="balance-label balance-title">Material Balance Summary -
                                {{ $selected?->maktx ?: $selectedMaterial }}</th>
                            @foreach ($months as $month)
                                <th colspan="{{ $plants->count() * 2 }}" class="balance-month">{{ $month->format('F Y') }}
                                </th>
                            @endforeach
                        </tr>
                        <tr>
                            <th colspan="2" class="balance-label balance-grade">
                                {{ $selected?->category_name ?: 'Material Category' }}</th>
                            @foreach ($months as $month)
                                @foreach ($plants as $index => $plant)
                                    <th class="balance-plant tone-{{ $index % 5 }}">{{ $plant->name }}</th>
                                    <th class="balance-plant tone-{{ $index % 5 }}">Remarks</th>
                                @endforeach
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th class="balance-label">O/s</th>
                            <th class="balance-detail">{{ now()->format('d.m.Y') }}</th>
                            @foreach ($months as $month)
                                @foreach ($plants as $index => $plant)
                                    @php($cell = $matrix[$month->format('Y-m')][$plant->id])
                                    <td class="number tone-{{ $index % 5 }}">{{ $fmt($cell['opening']) }}<strong class="balance-unit">(mtn)</strong></td>
                                    <td class="note tone-{{ $index % 5 }}">@include('procurement::partials.balance-remark-editor', ['remarkKey' => 'opening'])</td>
                                @endforeach
                            @endforeach
                        </tr>
                        <tr>
                            <th class="balance-label">Cover for No. Of Days</th>
                            <th class="balance-detail"></th>
                            @foreach ($months as $month)
                                @foreach ($plants as $index => $plant)
                                    @php($cell = $matrix[$month->format('Y-m')][$plant->id])
                                    <td class="number tone-{{ $index % 5 }}">
                                        @if($cell['cover_days'] === null)—@else{{ $fmt($cell['cover_days'], 1) }}<strong class="balance-unit">(days)</strong>@endif</td>
                                    <td class="note tone-{{ $index % 5 }}">@include('procurement::partials.balance-remark-editor', ['remarkKey' => 'cover_days'])</td>
                                @endforeach
                            @endforeach
                        </tr>
                        <tr>
                            <th class="balance-label">To Buy from RIL / Chemplast / DCW / Local</th>
                            <th class="balance-detail">RIL - AT Dadri, DCW / CP at Tumkur / Raipur</th>
                            @foreach ($months as $month)
                                @foreach ($plants as $index => $plant)
                                    @php($cell = $matrix[$month->format('Y-m')][$plant->id])
                                    <td class="number tone-{{ $index % 5 }}">{{ $fmt($cell['to_buy']) }}<strong class="balance-unit">(mtn)</strong></td>
                                    <td class="note tone-{{ $index % 5 }}">@include('procurement::partials.balance-remark-editor', ['remarkKey' => 'to_buy'])</td>
                                @endforeach
                            @endforeach
                        </tr>
                        @php($orderKey = fn($o) => implode('|', [$o->source, $o->vendor_name ?: $o->vendor_code, $o->purchase_order, $o->purchase_order_item, $o->delivery_date, $o->mt_quantity]))
                        @php($receiptOrders = collect($matrix)->flatten(1)->flatMap(fn($c) => $c['orders'])->sortBy(fn($o) => sprintf('%s|%s|%s', $o->vendor_name ?: $o->vendor_code, $o->delivery_date, $o->purchase_order))->values())
                        @forelse($receiptOrders as $orderIndex=>$receiptOrder)
                            @php($vendorName = $receiptOrder->vendor_name ?: $receiptOrder->vendor_code)
                            @php($previousVendor = $orderIndex > 0 ? ($receiptOrders[$orderIndex - 1]->vendor_name ?: $receiptOrders[$orderIndex - 1]->vendor_code) : null)
                            @php($firstVendorRow = $orderIndex === 0 || $previousVendor !== $vendorName)
                            <tr class="orders-row">
                                @if ($orderIndex === 0)
                                    <th class="balance-label" rowspan="{{ $receiptOrders->count() }}">Supplier receipts
                                    </th>
                                    @endif @if ($firstVendorRow)
                                        <th class="balance-detail"
                                            rowspan="{{ $receiptOrders->filter(fn($o) => ($o->vendor_name ?: $o->vendor_code) === $vendorName)->count() }}">
                                            {{ $vendorName }}</th>
                                        @endif @foreach ($months as $month)
                                            @foreach ($plants as $index => $plant)
                                                @php($cell = $matrix[$month->format('Y-m')][$plant->id])
                                                @php($cellOrder = $cell['orders']->first(fn($o) => $orderKey($o) === $orderKey($receiptOrder)))
                                                @php($receiptRemarkKey = 'receipt:'.sha1($orderKey($receiptOrder)))<td class="number tone-{{ $index % 5 }}">
                                                    @if($cellOrder){{ $fmt($cellOrder->mt_quantity) }}<strong class="balance-unit">(mtn)</strong>@endif</td>
                                                <td class="note tone-{{ $index % 5 }}">
                                                    @if ($cellOrder)
                                                        <div class="order-card">{{ $cellOrder->source }}@if ($cellOrder->purchase_order)
                                                                · PO {{ $cellOrder->purchase_order }}@if ($cellOrder->purchase_order_item)
                                                                    /{{ $cellOrder->purchase_order_item }}
                                                                @endif
                                                            @endif
                                                            <br>
                                                            <span
                                                                class="balance-order-stage">{{ $cellOrder->delivery_status_label ?: 'Planned' }}</span><br>ETA
                                                            {{ \Illuminate\Support\Carbon::parse($cellOrder->delivery_date)->format('d.m.Y') }}
                                                        </div>
                                                    @endif
                                                    @include('procurement::partials.balance-remark-editor', ['remarkKey' => $receiptRemarkKey])
                                                </td>
                                            @endforeach
                                        @endforeach
                            </tr>
                            @empty
                                <tr class="orders-row">
                                    <th class="balance-label">Supplier receipts</th>
                                    <th class="balance-detail">No supplier receipt</th>
                                    @foreach ($months as $month)
                                        @foreach ($plants as $index => $plant)
                                            <td class="number tone-{{ $index % 5 }}"></td>
                                            @php($cell = $matrix[$month->format('Y-m')][$plant->id])
                                            <td class="note tone-{{ $index % 5 }}">@include('procurement::partials.balance-remark-editor', ['remarkKey' => 'supplier_receipts'])</td>
                                        @endforeach
                                    @endforeach
                                </tr>
                            @endforelse
                            @php($totalRows = ['sap_buying' => 'Buying / Imports', 'total_buying' => 'Total Buying', 'availability' => 'Total Availability', 'daily_consumption' => 'Expected Average Consp/day', 'working_days' => 'Days', 'expected_consumption' => 'Total Expected Consp (MT)', 'gross_shortage' => 'Gross Shortage Before POs (MT)', 'closing' => 'Closing Stock', 'extra_required' => 'Remaining Shortage (MT)'])
                            @foreach ($totalRows as $key => $label)
                                <tr
                                    class="{{ in_array($key, ['sap_buying', 'total_buying', 'availability', 'expected_consumption']) ? 'metric-total' : '' }} {{ $key === 'closing' ? 'metric-closing' : '' }}">
                                    <th colspan="2" class="balance-label">{{ $label }}</th>
                                    @foreach ($months as $month)
                                        @foreach ($plants as $index => $plant)
                                            @php($cell = $matrix[$month->format('Y-m')][$plant->id])
                                            @php($unit = $key === 'working_days' ? 'days' : ($key === 'daily_consumption' ? 'mtn/day' : 'mtn'))
                                            <td
                                                class="number {{ is_numeric($cell[$key]) && $cell[$key] < 0 ? 'negative' : '' }} tone-{{ $index % 5 }} {{ in_array($key, ['daily_consumption', 'working_days']) ? 'highlight' : '' }}">
                                                {{ $fmt($cell[$key], $key === 'working_days' ? 0 : ($key === 'daily_consumption' ? 4 : 3)) }}<strong class="balance-unit">({{ $unit }})</strong></td>
                                            <td class="note tone-{{ $index % 5 }}">@include('procurement::partials.balance-remark-editor', ['remarkKey' => $key])</td>
                                        @endforeach
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <section class="mou-card">
                <header class="mou-card-head">
                    <div><span>Supplier commitments</span>
                        <h2>MOU Lifting Plan</h2>
                    </div>
                    <p>Monthly committed quantities across the planning horizon</p>
                </header>
                <div class="mou-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th class="mou-supplier">Supplier</th>
                                @foreach ($months as $month)
                                    <th class="mou-number">{{ $month->format('F Y') }}</th>
                                @endforeach
                                <th class="mou-number mou-horizon">Horizon total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($mouPlanning->pluck('vendor_name')->unique()->values() as $vendorName)
                                <tr>
                                    <td>{{ $vendorName }}</td>
                                    @foreach ($months as $month)
                                        <td class="mou-number">
                                            {{ $fmt($mouPlanning->where('vendor_name', $vendorName)->where('month_key', $month->format('Y-m'))->sum('quantity_mt')) }}
                                        </td>
                                    @endforeach
                                    <td class="mou-number mou-horizon">
                                        {{ $fmt($mouPlanning->where('vendor_name', $vendorName)->sum('quantity_mt')) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $months->count() + 2 }}">No MOU vendor plan available</td>
                                </tr>
                            @endforelse
                            <tr class="mou-total">
                                <th>Total Qty.</th>
                                @foreach ($months as $month)
                                    <th class="mou-number">
                                        {{ $fmt($mouPlanning->where('month_key', $month->format('Y-m'))->sum('quantity_mt')) }}
                                    </th>
                                @endforeach
                                <th class="mou-number mou-horizon">{{ $fmt($mouPlanning->sum('quantity_mt')) }}</th>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>

        @if ($previewId)
            <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                    <div>
                        <h2 class="font-bold text-slate-900">Uploaded Excel preview</h2>
                        <p class="text-sm text-slate-500">The first worksheet is displayed with its original cell formatting.
                        </p>
                    </div><span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-bold text-cyan-700">Read-only
                        preview</span>
                </header>
                <iframe class="h-[72vh] w-full bg-white" title="Uploaded material balance preview" sandbox
                    src="{{ route('admin.procurement.material-balance.import-preview', $previewId) }}"></iframe>
            </section>
        @endif

        <style>
            .balance-filter-card {
                padding: 14px 16px !important
            }

            .balance-filter-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(180px, 1fr)) auto;
                gap: 10px 12px;
                align-items: end
            }

            .balance-filter-grid .form-field {
                min-width: 0
            }

            .balance-filter-grid .label {
                margin-bottom: 5px;
                font-size: 11px
            }

            .balance-filter-grid .input,
            .balance-filter-grid .select2-selection {
                min-height: 34px !important;
                height: 34px !important;
                font-size: 12px
            }

            .balance-filter-grid .select2-selection__rendered {
                line-height: 32px !important
            }

            .balance-filter-grid .select2-selection__arrow {
                height: 32px !important
            }

            .balance-filter-actions {
                grid-column: auto;
                display: flex;
                justify-content: flex-end;
                align-items: center;
                gap: 8px;
                padding-bottom: 0;
                white-space: nowrap
            }

            .balance-filter-actions .btn-secondary,
            .balance-filter-actions .btn-primary {
                min-height: 34px;
                padding: 7px 12px;
                font-size: 11px
            }

            .balance-order-stage {
                display: inline-block;
                margin-top: 3px;
                padding: 2px 6px;
                border: 1px solid #b9dfe5;
                border-radius: 999px;
                background: #eef9fa;
                color: #126978;
                font-size: 9px;
                font-weight: 800;
                line-height: 1.3
            }

            .balance-report-page {
                width: 100%;
                padding: 18px;
                border: 1px solid var(--apl-border, #dbe4ee);
                border-radius: 18px;
                background: var(--theme-canvas, #f8fafc);
                box-shadow: 0 18px 46px rgba(15, 23, 42, .07)
            }

            .balance-report-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                margin: -18px -18px 18px;
                padding: 12px 20px;
                border-bottom: 1px solid var(--apl-border, #dbe4ee);
                border-radius: 18px 18px 0 0;
                background: var(--theme-card, #fff)
            }

            .balance-report-actions {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 8px
            }

            .balance-report-actions .btn-secondary {
                min-height: 34px;
                padding: 7px 13px;
                font-size: 11px
            }

            .balance-report-head p {
                margin: 0;
                color: #64748b;
                font-size: 12px;
                font-weight: 700
            }

            .balance-report-actions .balance-export-btn {
                display: grid !important;
                width: 40px !important;
                height: 40px !important;
                min-height: 40px !important;
                padding: 0 !important;
                place-items: center
            }

            .balance-export-btn svg {
                width: 18px;
                height: 18px;
                stroke: currentColor;
                stroke-width: 1.8;
                stroke-linecap: round;
                stroke-linejoin: round
            }

            .balance-summary-card {
                position: relative;
                display: block !important;
                min-height: 105px;
                overflow: hidden;
                padding: 16px 88px 15px 16px !important;
                border: 1px solid #e3dbe1 !important;
                border-left: 0 !important;
                border-radius: 12px !important;
                background: #fff !important
            }

            .balance-summary-icon {
                position: absolute;
                top: 50%;
                right: 18px;
                display: grid;
                width: 56px;
                height: 56px;
                place-items: center;
                border: 1px solid color-mix(in srgb, var(--theme-primary, #bd2ca6) 24%, #fff);
                border-radius: 50%;
                background: color-mix(in srgb, var(--theme-primary, var(--apl-teal, #159aa6)) 9%, #fff);
                color: var(--theme-primary, var(--apl-teal, #159aa6));
                box-shadow: 0 6px 16px color-mix(in srgb, var(--theme-primary, #bd2ca6) 12%, transparent);
                transform: translateY(-50%)
            }

            .balance-summary-icon svg {
                width: 23px;
                height: 23px
            }

            .balance-summary-card>div p,
            .balance-summary-card>div strong,
            .balance-summary-card>div span {
                position: relative
            }

            .balance-summary-material {
                box-shadow: inset 0 3px 0 var(--theme-primary, #bd2ca6), 0 8px 24px rgba(65, 35, 57, .055) !important
            }

            .balance-summary-horizon {
                box-shadow: inset 0 3px 0 var(--theme-secondary, #f36b2f), 0 8px 24px rgba(65, 35, 57, .055) !important
            }

            .balance-summary-plants {
                box-shadow: inset 0 3px 0 var(--theme-primary, #bd2ca6), 0 8px 24px rgba(65, 35, 57, .055) !important
            }

            .balance-summary-orders {
                box-shadow: inset 0 3px 0 var(--theme-secondary, #f36b2f), 0 8px 24px rgba(65, 35, 57, .055) !important
            }

            .balance-summary-horizon .balance-summary-icon,
            .balance-summary-orders .balance-summary-icon {
                border-color: color-mix(in srgb, var(--theme-secondary, #f36b2f) 25%, #fff);
                background: color-mix(in srgb, var(--theme-secondary, #f36b2f) 9%, #fff);
                color: var(--theme-secondary, #f36b2f);
                box-shadow: 0 6px 16px color-mix(in srgb, var(--theme-secondary, #f36b2f) 12%, transparent)
            }

            .balance-shell {
                overflow: hidden;
                border: 1px solid var(--apl-border, #dbe4ee);
                border-radius: 14px;
                background: var(--theme-card, #fff);
                box-shadow: 0 8px 24px rgba(15, 23, 42, .05)
            }

            .balance-scroll {
                overflow-x: auto;
                overflow-y: visible
            }

            .balance-table {
                border-collapse: separate;
                border-spacing: 0;
                min-width: max-content;
                width: 100%;
                font-size: 12px;
                color: #24324a
            }

            .balance-table th,
            .balance-table td {
                border-right: 1px solid #cbd5e1;
                border-bottom: 1px solid #cbd5e1;
                padding: 9px 10px;
                min-width: 88px;
                vertical-align: middle
            }

            .balance-table thead {
                position: sticky;
                top: 0;
                z-index: 20
            }

            .balance-table thead th {
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: .035em
            }

            .balance-label {
                position: sticky;
                left: 0;
                z-index: 12;
                min-width: 190px !important;
                max-width: 190px;
                background: #fff;
                text-align: left
            }

            .balance-detail {
                position: sticky;
                left: 190px;
                z-index: 11;
                min-width: 190px !important;
                max-width: 190px;
                background: #fff;
                text-align: left;

                box-shadow:2px 0 0 #cbd5e1}.balance-label[colspan="2"] {
                    min-width: 380px !important;
                    max-width: 380px !important
                }

                .balance-title {
                    z-index: 30 !important;
                    background: #2b1828 !important;
                    color: #fff7fc !important
                }

                .balance-month {
                    background: #2b1828 !important;
                    color: #fff7fc !important;
                    font-size: 14px;
                    text-align: center
                }

                .balance-plant {
                    background: #3a2236 !important;
                    text-align: center;
                    color: #fff7fc !important
                }

                .balance-sub {
                    text-align: center;
                    font-size: 10px
                }

                .balance-grade {
                    background: #3a2236 !important;
                    color: #fff7fc !important
                }

                .tone-0,
                .tone-1,
                .tone-2,
                .tone-3,
                .tone-4 {
                    background: #f2fafa;
                    background: color-mix(in srgb, var(--theme-primary, var(--apl-teal, #159aa6)) 7%, var(--theme-card, #fff))
                }

                .number {
                    text-align: right;
                    font-variant-numeric: tabular-nums;
                    font-weight: 800
                }

                .balance-unit {
                    display: inline-block;
                    margin-left: 8px;
                    white-space: nowrap;
                    font-size: .8em;
                    font-weight: 400 !important;
                    letter-spacing: .02em
                }

                .note {
                    min-width: 165px;
                    max-width: 220px;
                    color: #475569;
                    white-space: normal
                }

                .balance-remarks-editor summary {
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                    cursor: pointer;
                    color: var(--theme-primary, var(--apl-teal, #159aa6));
                    font-size: 11px;
                    font-weight: 800;
                    list-style: none
                }

                .balance-remarks-editor summary svg {
                    width: 14px;
                    height: 14px;
                    stroke: currentColor;
                    stroke-width: 2;
                    stroke-linecap: round;
                    stroke-linejoin: round
                }

                .balance-remarks-editor summary::-webkit-details-marker {
                    display: none
                }

                .balance-remarks-editor form {
                    display: grid;
                    gap: 6px;
                    margin-top: 8px
                }

                .balance-remarks-editor textarea {
                    width: 100%;
                    min-width: 180px;
                    resize: vertical;
                    padding: 8px;
                    border: 1px solid #cbd5e1;
                    border-radius: 8px;
                    background: #fff;
                    color: #172033;
                    font: inherit
                }

                .balance-remarks-editor button {
                    justify-self: end;
                    padding: 6px 12px;
                    border: 0;
                    border-radius: 7px;
                    background: var(--theme-primary, var(--apl-teal, #159aa6));
                    color: #fff;
                    font-size: 11px;
                    font-weight: 800;
                    cursor: pointer
                }

                .balance-remarks-text {
                    margin: 7px 0 0;
                    color: #334155;
                    line-height: 1.45;
                    white-space: pre-wrap
                }

                .highlight {
                    background: #ddf2f3 !important;
                    background: color-mix(in srgb, var(--theme-primary, var(--apl-teal, #159aa6)) 18%, var(--theme-card, #fff)) !important;
                    color: #16343a
                }

                .metric-opening .balance-label {
                    background: #ecfeff
                }

                .metric-buy .balance-label {
                    background: #eaf7f8
                }

                .metric-total .balance-label {
                    font-weight: 900;
                    background: var(--theme-card, #f8fafc)
                }

                .metric-closing th,
                .metric-closing td {
                    border-top: 2px solid var(--theme-primary, var(--apl-teal, #159aa6));
                    border-bottom: 2px solid var(--theme-primary, var(--apl-teal, #159aa6));
                    font-size: 14px;
                    font-weight: 900
                }

                .metric-closing .balance-label {
                    background: var(--theme-primary, var(--apl-teal, #159aa6));
                    color: #fff
                }

                .balance-table td.number.negative,
                .balance-table td.number.negative strong {
                    color: #dc2626 !important;
                    background: #fff1f2 !important;
                    font-weight: 900 !important
                }

                .balance-table td.number.negative .balance-unit {
                    font-size: .8em;
                    font-weight: 400 !important
                }

                .positive {
                    color: #047857
                }

                .orders-row td {
                    height: 132px
                }

                .order-card {
                    padding: 0 0 8px;
                    margin: 0 0 8px;
                    border-bottom: 1px dashed rgba(71, 85, 105, .35)
                }

                .order-card:last-child,
                .order-qty:last-child {
                    margin-bottom: 0;
                    border-bottom: 0
                }

                .order-card strong {
                    display: block
                }

                .order-card .balance-order-stage {
                    display: inline-flex;
                    width: auto;
                    max-width: 100%;
                    margin-top: 2px;
                    font-size: 10px
                }

                .order-qty {
                    min-height: 47px;
                    padding-top: 2px;
                    margin-bottom: 8px;
                    border-bottom: 1px dashed rgba(71, 85, 105, .35)
                }

                .mou-card {
                    flex: 0 0 auto;
                    align-self: flex-start !important;
                    margin-top: 18px;
                    width: 820px !important;
                    max-width: 100%;
                    overflow: hidden;
                    border: 1px solid #d7e1ec;
                    border-radius: 14px;
                    background: #fff;
                    box-shadow: 0 10px 28px rgba(15, 23, 42, .06)
                }

                .mou-card-head {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 20px;
                    padding: 16px 18px;
                    border-bottom: 1px solid #dbe4ee;
                    background: linear-gradient(135deg, #f8fbff, #eefafa)
                }

                .mou-card-head span {
                    display: block;
                    margin-bottom: 3px;
                    color: var(--theme-primary, var(--apl-teal, #159aa6));
                    font-size: 10px;
                    font-weight: 800;
                    letter-spacing: .12em;
                    text-transform: uppercase
                }

                .mou-card-head h2 {
                    margin: 0;
                    color: #0f2747;
                    font-size: 17px
                }

                .mou-card-head p {
                    margin: 0;
                    color: #64748b;
                    font-size: 12px
                }

                .mou-scroll {
                    width: 100%;
                    overflow-x: auto
                }

                .mou-card table {
                    width: 100%;
                    min-width: 720px;
                    border-collapse: collapse;
                    table-layout: auto;
                    color: #24324a
                }

                .mou-card th {
                    background: #edf7f8;
                    background: color-mix(in srgb, var(--theme-primary, var(--apl-teal, #159aa6)) 10%, var(--theme-card, #fff));
                    color: #334155;
                    font-size: 11px;
                    letter-spacing: .03em;
                    text-transform: uppercase
                }

                .mou-card td,
                .mou-card th {
                    padding: 12px 16px;
                    border-right: 1px solid #dbe4ee;
                    border-bottom: 1px solid #dbe4ee;
                    white-space: nowrap
                }

                .mou-card tr:last-child td,
                .mou-card tr:last-child th {
                    border-bottom: 0
                }

                .mou-card td:last-child,
                .mou-card th:last-child {
                    border-right: 0
                }

                .mou-supplier,
                .mou-card td:first-child {
                    min-width: 320px;
                    text-align: left
                }

                .mou-number {
                    min-width: 120px;
                    text-align: right;
                    font-variant-numeric: tabular-nums
                }

                .mou-horizon {
                    background: #e5f5f6 !important;
                    background: color-mix(in srgb, var(--theme-primary, var(--apl-teal, #159aa6)) 15%, var(--theme-card, #fff)) !important;
                    color: var(--theme-primary, var(--apl-teal-dark, #0f766e)) !important;
                    font-weight: 800
                }

                .mou-total {
                    font-weight: 900;
                    background: var(--theme-card, #f8fafc)
                }

                @media(max-width:860px) {
                    .mou-card {
                        width: 100% !important
                    }

                    .mou-card-head {
                        align-items: flex-start;
                        flex-direction: column;
                        gap: 6px
                    }

                    .mou-card td,
                    .mou-card th {
                        padding: 10px 12px
                    }

                    .mou-supplier,
                    .mou-card td:first-child {
                        min-width: 240px
                    }
                }

                @media(max-width:1250px) {
                    .balance-filter-grid {
                        grid-template-columns: repeat(2, minmax(0, 1fr))
                    }

                    .balance-filter-actions {
                        grid-column: 2
                    }
                }

                @media(max-width:768px) {
                    .balance-report-page {
                        padding: 10px;
                        border-radius: 12px
                    }

                    .balance-report-head {
                        align-items: flex-start;
                        flex-direction: column;
                        margin: -10px -10px 12px;
                        padding: 13px 14px;

                        border-radius:12px 12px 0 0}.balance-filter-grid{grid-template-columns:1fr}.balance-filter-actions{grid-column:1;justify-content:flex-start}.balance-label{min-width:150px!important;max-width:150px}.balance-detail{left:150px;min-width:150px!important;max-width:150px}.balance-label[colspan="2"] {
                            min-width: 300px !important;
                            max-width: 300px !important
                        }

                        .balance-table th,
                        .balance-table td {
                            padding: 7px 8px
                        }

                        .note {
                            min-width: 140px
                        }
                    }
        </style>
    @endsection

    @push('scripts')
        <script>
            // Keep an open balance sheet synchronized with consumption edits
            // completed in another tab/window.
            window.addEventListener('storage', function (event) {
                if (event.key === 'procurement-calculations-updated' && event.newValue) {
                    window.location.reload();
                }
            });

            window.addEventListener('pageshow', function (event) {
                if (event.persisted) window.location.reload();
            });

            // Some browsers keep a background tab frozen and can miss a
            // storage event. Recheck the calculation revision when the sheet
            // becomes visible again and reload only when it changed.
            let visibleCalculationRevision = localStorage.getItem('procurement-calculations-updated');
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState !== 'visible') return;
                const latestRevision = localStorage.getItem('procurement-calculations-updated');
                if (latestRevision && latestRevision !== visibleCalculationRevision) {
                    visibleCalculationRevision = latestRevision;
                    window.location.reload();
                }
            });
        </script>
        <script defer
            src="{{ asset('js/material-balance-filters.js') }}?v={{ filemtime(public_path('js/material-balance-filters.js')) }}">
        </script>
    @endpush
