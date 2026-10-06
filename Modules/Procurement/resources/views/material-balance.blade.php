@extends('layout.app')
@section('title', 'Material Balance Summary')
@section('content')
<x-page-header title="Material Balance Summary" description="Plant-wise stock, buying and consumption plan based on current procurement data.">
    <span class="crud-breadcrumb">Procurement <b>/</b> Material Balance</span>
</x-page-header>

@if(session('success'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-700">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif

<form method="GET" action="{{ route('admin.procurement.material-balance') }}" class="card mb-5 balance-filter-card" aria-label="Material balance filters">
    <div class="balance-filter-grid">
        <div class="form-field">
            <label class="label" for="balance-company">Company</label>
            <select class="input" id="balance-company" name="company" data-searchable-select>
                <option value="">All companies</option>
                @foreach($companies as $company)<option value="{{ $company->id }}" @selected((string) $company->id === $selectedCompany)>{{ $company->company_code }} - {{ $company->company_name }}</option>@endforeach
            </select>
        </div>
        <div class="form-field">
            <label class="label" for="balance-plant">Plant</label>
            <select class="input" id="balance-plant" name="plant" data-selected="{{ $selectedPlant }}" data-searchable-select>
                <option value="">All plants</option>
                @foreach($filterPlants as $plant)<option value="{{ $plant->id }}" data-company="{{ $plant->company_id }}" @selected((string) $plant->id === $selectedPlant)>{{ $plant->company_code }} - {{ $plant->name }}</option>@endforeach
            </select>
        </div>
        <div class="form-field">
            <label class="label" for="balance-material">Material</label>
            <select class="input" id="balance-material" name="material" data-selected="{{ $selectedMaterial }}" data-searchable-select>
                @foreach($filterMaterials as $material)<option value="{{ $material->matnr }}" @selected($material->matnr === $selectedMaterial)>{{ $material->matnr }} - {{ $material->maktx }}</option>@endforeach
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
    $fmt = fn($value, $decimals = 1) => $value === null ? 'N/A' : number_format((float) $value, $decimals);
@endphp

<section class="balance-report-page" aria-label="Material balance report">
<header class="balance-report-head"><div class="balance-report-actions"><a class="btn-secondary balance-export-btn" title="Export Excel" aria-label="Export Excel" href="{{ route('admin.procurement.material-balance.export-excel', $filterQuery) }}"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z"/><path d="M14 3v6h6M8 13l4 4m0-4-4 4"/></svg><span class="sr-only">Export Excel</span></a><a class="btn-secondary balance-export-btn" title="Export PDF" aria-label="Export PDF" href="{{ route('admin.procurement.material-balance.export-pdf', $filterQuery) }}"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z"/><path d="M14 3v6h6M8 15h8M8 11h3"/></svg><span class="sr-only">Export PDF</span></a></div><p>{{ $months->first()->format('M Y') }} – {{ $months->last()->format('M Y') }}</p></header>
<section class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <article class="card balance-summary-card balance-summary-material"><span class="balance-summary-icon"><x-app-icon name="materials" /></span><div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Material</p><strong class="mt-2 block text-lg">{{ $selectedMaterial }}</strong><span class="text-sm text-slate-500">{{ $selected?->maktx }}</span></div></article>
    <article class="card balance-summary-card balance-summary-horizon"><span class="balance-summary-icon"><x-app-icon name="balance" /></span><div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Planning horizon</p><strong class="mt-2 block text-lg">{{ $months->first()->format('M Y') }} – {{ $months->last()->format('M Y') }}</strong><span class="text-sm text-slate-500">{{ $horizon }}-month planning view</span></div></article>
    <article class="card balance-summary-card balance-summary-plants"><span class="balance-summary-icon"><x-app-icon name="plant" /></span><div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Plants</p><strong class="mt-2 block text-2xl">{{ $plants->count() }}</strong><span class="text-sm text-slate-500">Plants included in planning</span></div></article>
    <article class="card balance-summary-card balance-summary-orders"><span class="balance-summary-icon"><x-app-icon name="purchase-entry" /></span><div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Manual POs</p><strong class="mt-2 block text-2xl">{{ collect($matrix)->flatten(1)->sum(fn($cell) => $cell['orders']->count()) }}</strong><span class="text-sm text-slate-500">Planned PO entries in this horizon</span></div></article>
</section>

<div class="balance-shell">
    <div class="balance-scroll">
        <table class="balance-table">
            <thead>
                <tr><th colspan="2" class="balance-label balance-title">Material Balance Summary - {{ $selected?->maktx ?: $selectedMaterial }}</th>@foreach($months as $month)<th colspan="{{ $plants->count() * 2 }}" class="balance-month">{{ $month->format('M-y') }}</th>@endforeach</tr>
                <tr><th colspan="2" class="balance-label balance-grade">{{ $selected?->category_name ?: 'Material Category' }}</th>@foreach($months as $month)@foreach($plants as $index => $plant)<th class="balance-plant tone-{{ $index % 5 }}">{{ $plant->name }}</th><th class="balance-plant tone-{{ $index % 5 }}">Remarks</th>@endforeach @endforeach</tr>
            </thead>
            <tbody>
                <tr><th class="balance-label">O/s</th><th class="balance-detail">{{ now()->format('d.m.Y') }}</th>@foreach($months as $month)@foreach($plants as $index => $plant)@php($cell=$matrix[$month->format('Y-m')][$plant->id])<td class="number tone-{{ $index % 5 }}">{{ $fmt($cell['opening']) }}</td><td class="note tone-{{ $index % 5 }}"></td>@endforeach @endforeach</tr>
                <tr><th class="balance-label">Cover for No. Of Days</th><th class="balance-detail"></th>@foreach($months as $month)@foreach($plants as $index => $plant)@php($cell=$matrix[$month->format('Y-m')][$plant->id])<td class="number tone-{{ $index % 5 }}">{{ $cell['cover_days'] === null ? '—' : $fmt($cell['cover_days'],0) }}</td><td class="note tone-{{ $index % 5 }}"></td>@endforeach @endforeach</tr>
                <tr><th class="balance-label">To Buy from RIL / Chemplast / DCW / Local</th><th class="balance-detail">RIL - AT Dadri, DCW / CP at Tumkur / Raipur</th>@foreach($months as $month)@foreach($plants as $index => $plant)@php($cell=$matrix[$month->format('Y-m')][$plant->id])<td class="number tone-{{ $index % 5 }}">{{ $fmt($cell['to_buy']) }}</td><td class="note tone-{{ $index % 5 }}"></td>@endforeach @endforeach</tr>
                @php($receiptVendors=collect($matrix)->flatten(1)->flatMap(fn($c)=>$c['orders'])->map(fn($o)=>$o->vendor_name?:$o->vendor_code)->filter()->unique()->sort()->values())
                @forelse($receiptVendors as $vendorIndex=>$vendorName)
                <tr class="orders-row"><th class="balance-label">{{ $vendorIndex===0?'Supplier receipts':'' }}</th><th class="balance-detail">{{ $vendorName }}</th>@foreach($months as $month)@foreach($plants as $index=>$plant)@php($vendorOrders=$matrix[$month->format('Y-m')][$plant->id]['orders']->filter(fn($o)=>($o->vendor_name?:$o->vendor_code)===$vendorName)->values())<td class="number tone-{{ $index%5 }}">{{ $vendorOrders->isNotEmpty()?$fmt($vendorOrders->sum('mt_quantity')):'' }}</td><td class="note tone-{{ $index%5 }}">@foreach($vendorOrders as $order)<div class="order-card">{{ $order->source }}@if($order->purchase_order) · PO {{ $order->purchase_order }}@if($order->purchase_order_item)/{{ $order->purchase_order_item }}@endif @endif<br><span class="balance-order-stage">{{ $order->delivery_status_label ?: 'Planned' }}</span><br>ETA {{ \Illuminate\Support\Carbon::parse($order->delivery_date)->format('d-M-Y') }}</div>@endforeach</td>@endforeach @endforeach</tr>
                @empty
                <tr class="orders-row"><th class="balance-label">Supplier receipts</th><th class="balance-detail">No supplier receipt</th>@foreach($months as $month)@foreach($plants as $index=>$plant)<td class="number tone-{{ $index%5 }}"></td><td class="note tone-{{ $index%5 }}"></td>@endforeach @endforeach</tr>
                @endforelse
                @php($totalRows=['sap_buying'=>'Buying / Imports','total_buying'=>'Total Buying','availability'=>'Total Availability','daily_consumption'=>'Expected Average Consp/day','working_days'=>'Working Days','expected_consumption'=>'Total Expected Consp (MT)','gross_shortage'=>'Gross Shortage Before POs (MT)','closing'=>'Closing Stock','extra_required'=>'Remaining Shortage (MT)'])
                @foreach($totalRows as $key=>$label)<tr class="{{ in_array($key,['sap_buying','total_buying','availability','expected_consumption'])?'metric-total':'' }} {{ $key==='closing'?'metric-closing':'' }}"><th colspan="2" class="balance-label">{{ $label }}</th>@foreach($months as $month)@foreach($plants as $index=>$plant)@php($cell=$matrix[$month->format('Y-m')][$plant->id])<td class="number {{ ($key==='closing'&&$cell[$key]<0)||($key==='extra_required'&&$cell[$key]>0)?'negative':'' }} tone-{{ $index%5 }} {{ in_array($key,['daily_consumption','working_days'])?'highlight':'' }}">{{ $fmt($cell[$key],$key==='daily_consumption'?3:1) }}</td><td class="note tone-{{ $index%5 }}"></td>@endforeach @endforeach</tr>@endforeach
            </tbody>
        </table>
    </div>
</div>

<section class="mou-card">
    <header class="mou-card-head"><div><span>Supplier commitments</span><h2>MOU Lifting Plan</h2></div><p>Monthly committed quantities across the planning horizon</p></header>
    <div class="mou-scroll"><table>
            <thead><tr><th class="mou-supplier">Supplier</th>@foreach($months as $month)<th class="mou-number">{{ $month->format('M Y') }}</th>@endforeach<th class="mou-number mou-horizon">Horizon total</th></tr></thead>
            <tbody>
                    @forelse($mouPlanning->pluck('vendor_name')->unique()->values() as $vendorName)
                    <tr>
                        <td>{{ $vendorName }}</td>
                        @foreach($months as $month)<td class="mou-number">{{ $fmt($mouPlanning->where('vendor_name', $vendorName)->where('month_key', $month->format('Y-m'))->sum('quantity_mt')) }}</td>@endforeach
                        <td class="mou-number mou-horizon">{{ $fmt($mouPlanning->where('vendor_name', $vendorName)->sum('quantity_mt')) }}</td>
                    </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $months->count() + 2 }}">No MOU vendor plan available</td>
                        </tr>
                    @endforelse
                    <tr class="mou-total">
                        <th>Total Qty.</th>
                        @foreach($months as $month)<th class="mou-number">{{ $fmt($mouPlanning->where('month_key', $month->format('Y-m'))->sum('quantity_mt')) }}</th>@endforeach
                        <th class="mou-number mou-horizon">{{ $fmt($mouPlanning->sum('quantity_mt')) }}</th>
                    </tr>
            </tbody>
    </table></div>
</section>
</section>

@if($previewId)
<section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4"><div><h2 class="font-bold text-slate-900">Uploaded Excel preview</h2><p class="text-sm text-slate-500">The first worksheet is displayed with its original cell formatting.</p></div><span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-bold text-cyan-700">Read-only preview</span></header>
    <iframe class="h-[72vh] w-full bg-white" title="Uploaded material balance preview" sandbox src="{{ route('admin.procurement.material-balance.import-preview', $previewId) }}"></iframe>
</section>
@endif

<style>
.balance-filter-card{padding:14px 16px!important}.balance-filter-grid{display:grid;grid-template-columns:repeat(3,minmax(180px,1fr)) auto;gap:10px 12px;align-items:end}.balance-filter-grid .form-field{min-width:0}.balance-filter-grid .label{margin-bottom:5px;font-size:11px}.balance-filter-grid .input,.balance-filter-grid .select2-selection{min-height:34px!important;height:34px!important;font-size:12px}.balance-filter-grid .select2-selection__rendered{line-height:32px!important}.balance-filter-grid .select2-selection__arrow{height:32px!important}.balance-filter-actions{grid-column:auto;display:flex;justify-content:flex-end;align-items:center;gap:8px;padding-bottom:0;white-space:nowrap}.balance-filter-actions .btn-secondary,.balance-filter-actions .btn-primary{min-height:34px;padding:7px 12px;font-size:11px}
.balance-order-stage{display:inline-block;margin-top:3px;padding:2px 6px;border:1px solid #b9dfe5;border-radius:999px;background:#eef9fa;color:#126978;font-size:9px;font-weight:800;line-height:1.3}
.balance-report-page{width:100%;padding:18px;border:1px solid var(--apl-border,#dbe4ee);border-radius:18px;background:var(--theme-canvas,#f8fafc);box-shadow:0 18px 46px rgba(15,23,42,.07)}.balance-report-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:-18px -18px 18px;padding:12px 20px;border-bottom:1px solid var(--apl-border,#dbe4ee);border-radius:18px 18px 0 0;background:var(--theme-card,#fff)}.balance-report-actions{display:flex;flex-wrap:wrap;align-items:center;gap:8px}.balance-report-actions .btn-secondary{min-height:34px;padding:7px 13px;font-size:11px}.balance-report-head p{margin:0;color:#64748b;font-size:12px;font-weight:700}
.balance-report-actions .balance-export-btn{display:grid!important;width:40px!important;height:40px!important;min-height:40px!important;padding:0!important;place-items:center}.balance-export-btn svg{width:18px;height:18px;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.balance-summary-card{display:grid!important;grid-template-columns:44px minmax(0,1fr);align-items:center;gap:14px;min-height:120px;padding:18px 20px!important;border-left:0!important}.balance-summary-icon{display:grid;width:44px;height:44px;place-items:center;border:1px solid color-mix(in srgb,var(--theme-primary,var(--apl-teal,#159aa6)) 20%,#d3e0e8);border-radius:12px;background:color-mix(in srgb,var(--theme-primary,var(--apl-teal,#159aa6)) 9%,#fff);color:var(--theme-primary,var(--apl-teal,#159aa6))}.balance-summary-icon svg{width:21px;height:21px}.balance-summary-card p,.balance-summary-card strong,.balance-summary-card span{position:relative}.balance-summary-material{box-shadow:inset 0 3px 0 #159aa6,0 8px 24px rgba(23,43,69,.055)!important}.balance-summary-horizon{box-shadow:inset 0 3px 0 #4f70b5,0 8px 24px rgba(23,43,69,.055)!important}.balance-summary-plants{box-shadow:inset 0 3px 0 #25936f,0 8px 24px rgba(23,43,69,.055)!important}.balance-summary-orders{box-shadow:inset 0 3px 0 #c88a2c,0 8px 24px rgba(23,43,69,.055)!important}
.balance-shell{overflow:hidden;border:1px solid var(--apl-border,#dbe4ee);border-radius:14px;background:var(--theme-card,#fff);box-shadow:0 8px 24px rgba(15,23,42,.05)}
.balance-scroll{overflow-x:auto;overflow-y:visible}.balance-table{border-collapse:separate;border-spacing:0;min-width:max-content;width:100%;font-size:12px;color:#24324a}
.balance-table th,.balance-table td{border-right:1px solid #cbd5e1;border-bottom:1px solid #cbd5e1;padding:9px 10px;min-width:88px;vertical-align:middle}
.balance-table thead{position:sticky;top:0;z-index:20}.balance-table thead th{font-weight:800;text-transform:uppercase;letter-spacing:.035em}
.balance-label{position:sticky;left:0;z-index:12;min-width:190px!important;max-width:190px;background:#fff;text-align:left}.balance-detail{position:sticky;left:190px;z-index:11;min-width:190px!important;max-width:190px;background:#fff;text-align:left;box-shadow:2px 0 0 #cbd5e1}.balance-label[colspan="2"]{min-width:380px!important;max-width:380px!important}
.balance-title{z-index:30!important;background:var(--theme-primary,var(--apl-teal,#159aa6))!important;color:#fff!important}.balance-month{background:var(--theme-primary,var(--apl-teal,#159aa6));color:#fff;font-size:14px;text-align:center}.balance-plant{background:#eaf7f8;background:color-mix(in srgb,var(--theme-primary,var(--apl-teal,#159aa6)) 12%,var(--theme-card,#fff));text-align:center;color:#172033}.balance-sub{text-align:center;font-size:10px}
.balance-grade{background:#e2f3f4!important;background:color-mix(in srgb,var(--theme-primary,var(--apl-teal,#159aa6)) 18%,var(--theme-card,#fff))!important;color:#16343a!important}
.tone-0,.tone-1,.tone-2,.tone-3,.tone-4{background:#f2fafa;background:color-mix(in srgb,var(--theme-primary,var(--apl-teal,#159aa6)) 7%,var(--theme-card,#fff))}
.number{text-align:right;font-variant-numeric:tabular-nums;font-weight:800}.note{min-width:165px;max-width:220px;color:#475569;white-space:normal}.highlight{background:#ddf2f3!important;background:color-mix(in srgb,var(--theme-primary,var(--apl-teal,#159aa6)) 18%,var(--theme-card,#fff))!important;color:#16343a}
.metric-opening .balance-label{background:#ecfeff}.metric-buy .balance-label{background:#eaf7f8}.metric-total .balance-label{font-weight:900;background:var(--theme-card,#f8fafc)}.metric-closing th,.metric-closing td{border-top:2px solid var(--theme-primary,var(--apl-teal,#159aa6));border-bottom:2px solid var(--theme-primary,var(--apl-teal,#159aa6));font-size:14px;font-weight:900}.metric-closing .balance-label{background:var(--theme-primary,var(--apl-teal,#159aa6));color:#fff}.negative{color:#be123c}.positive{color:#047857}
.orders-row td{height:132px}.order-card{padding:0 0 8px;margin:0 0 8px;border-bottom:1px dashed rgba(71,85,105,.35)}.order-card:last-child,.order-qty:last-child{margin-bottom:0;border-bottom:0}.order-card strong,.order-card span{display:block}.order-card span{margin-top:2px;font-size:10px}.order-qty{min-height:47px;padding-top:2px;margin-bottom:8px;border-bottom:1px dashed rgba(71,85,105,.35)}
.mou-card{flex:0 0 auto;align-self:flex-start!important;margin-top:18px;width:820px!important;max-width:100%;overflow:hidden;border:1px solid #d7e1ec;border-radius:14px;background:#fff;box-shadow:0 10px 28px rgba(15,23,42,.06)}
.mou-card-head{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:16px 18px;border-bottom:1px solid #dbe4ee;background:linear-gradient(135deg,#f8fbff,#eefafa)}
.mou-card-head span{display:block;margin-bottom:3px;color:var(--theme-primary,var(--apl-teal,#159aa6));font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.mou-card-head h2{margin:0;color:#0f2747;font-size:17px}.mou-card-head p{margin:0;color:#64748b;font-size:12px}
.mou-scroll{width:100%;overflow-x:auto}.mou-card table{width:100%;min-width:720px;border-collapse:collapse;table-layout:auto;color:#24324a}.mou-card th{background:#edf7f8;background:color-mix(in srgb,var(--theme-primary,var(--apl-teal,#159aa6)) 10%,var(--theme-card,#fff));color:#334155;font-size:11px;letter-spacing:.03em;text-transform:uppercase}.mou-card td,.mou-card th{padding:12px 16px;border-right:1px solid #dbe4ee;border-bottom:1px solid #dbe4ee;white-space:nowrap}.mou-card tr:last-child td,.mou-card tr:last-child th{border-bottom:0}.mou-card td:last-child,.mou-card th:last-child{border-right:0}.mou-supplier,.mou-card td:first-child{min-width:320px;text-align:left}.mou-number{min-width:120px;text-align:right;font-variant-numeric:tabular-nums}.mou-horizon{background:#e5f5f6!important;background:color-mix(in srgb,var(--theme-primary,var(--apl-teal,#159aa6)) 15%,var(--theme-card,#fff))!important;color:var(--theme-primary,var(--apl-teal-dark,#0f766e))!important;font-weight:800}.mou-total{font-weight:900;background:var(--theme-card,#f8fafc)}
@media(max-width:860px){.mou-card{width:100%!important}.mou-card-head{align-items:flex-start;flex-direction:column;gap:6px}.mou-card td,.mou-card th{padding:10px 12px}.mou-supplier,.mou-card td:first-child{min-width:240px}}
@media(max-width:1250px){.balance-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.balance-filter-actions{grid-column:2}}
@media(max-width:768px){.balance-report-page{padding:10px;border-radius:12px}.balance-report-head{align-items:flex-start;flex-direction:column;margin:-10px -10px 12px;padding:13px 14px;border-radius:12px 12px 0 0}.balance-filter-grid{grid-template-columns:1fr}.balance-filter-actions{grid-column:1;justify-content:flex-start}.balance-label{min-width:150px!important;max-width:150px}.balance-detail{left:150px;min-width:150px!important;max-width:150px}.balance-label[colspan="2"]{min-width:300px!important;max-width:300px!important}.balance-table th,.balance-table td{padding:7px 8px}.note{min-width:140px}}
</style>
@endsection

@push('scripts')
<script defer src="{{ asset('js/material-balance-filters.js') }}?v={{ filemtime(public_path('js/material-balance-filters.js')) }}"></script>
@endpush
