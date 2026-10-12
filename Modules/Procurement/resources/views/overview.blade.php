@extends('layout.app')
@section('title', 'Procurement Overview')
@section('content')
    <section class="procurement-toolbar">
        <div>
            <div class="procurement-toolbar-eyebrow">Procurement Intelligence</div>
            <p>Materials, purchasing and plant consumption workspace.</p>
        </div>
        <form class="procurement-toolbar-filters" method="GET" action="{{ route('admin.procurement.overview') }}">
            <select name="plant" aria-label="Select plant" onchange="this.form.submit()">
                <option value="">All plants</option>
                @foreach ($plants as $plant)
                    <option value="{{ $plant->company_code }}" @selected($selectedPlant === (string) $plant->company_code)>
                        {{ $plant->company_code }} - {{ $plant->name }}
                    </option>
                @endforeach
            </select>
            <select name="material" aria-label="Select material" data-searchable-select onchange="this.form.submit()">
                @forelse($materials as $material)
                    <option value="{{ $material->code }}" @selected($selectedMaterial === $material->code)>
                        {{ $material->code }} - {{ $material->name }}
                    </option>
                @empty
                    <option value="">No materials available</option>
                @endforelse
            </select>
            <span>Updated {{ now()->format('d M Y, h:i A') }}</span>
        </form>
    </section>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([['Companies', $stats['companies'], 'company'], ['Plants', $stats['plants'], 'plant'], ['Active purchases', $stats['purchases'], 'active-purchases'], ['Purchased MT', number_format($stats['purchase_mt'], 3), 'purchased'], ['Planned consumption MT', number_format($stats['planned_consumption_mt'], 3), 'consumed']] as [$label, $value, $icon])
            <article class="card procurement-overview-stat">
                <span class="procurement-overview-icon"><x-app-icon :name="$icon" /></span>
                <div><p class="text-sm text-slate-500">{{ $label }}</p><strong class="mt-1 block text-3xl">{{ $value }}</strong></div>
            </article>
        @endforeach
    </div>
    <div class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @can('procurement.overview.view')
            <a class="card procurement-overview-action" href="{{ route('admin.procurement.material-balance') }}">
                <span class="procurement-overview-icon"><x-app-icon name="balance" /></span><div><h2 class="font-semibold">Material Balance</h2>
                <p class="mt-2 text-sm text-slate-500">Plan stock, incoming orders and consumption by month and plant.</p></div><span class="procurement-action-arrow">→</span>
            </a>
        @endcan
        @can('procurement.all_materials.view')
            <a class="card procurement-overview-action" href="{{ route('admin.procurement.materials') }}">
                <span class="procurement-overview-icon"><x-app-icon name="materials" /></span><div><h2 class="font-semibold">All Materials</h2>
                <p class="mt-2 text-sm text-slate-500">Review purchased, consumed and projected balance quantities.</p></div><span class="procurement-action-arrow">→</span>
            </a>
        @endcan
        @can('procurement.daily_consumption.view')
            <a class="card procurement-overview-action" href="{{ route('admin.procurement.consumptions') }}">
                <span class="procurement-overview-icon"><x-app-icon name="daily-consumption" /></span><div><h2 class="font-semibold">Daily Consumption</h2>
                <p class="mt-2 text-sm text-slate-500">Capture plant-wise material usage with revision history.</p></div><span class="procurement-action-arrow">→</span>
            </a>
        @endcan
    </div>
    @php
        $chartMax = max(1, $chart['months']->max(fn ($month) => max($month['purchases'], $month['consumption'])));
        $plantMax = max(1, $chart['plants']->max('total') ?? 0);
    @endphp
    <section class="procurement-chart-grid" aria-label="Procurement analytics">
        <article class="procurement-chart-card">
            <header><div><span>Procurement outlook</span><h2>Purchases vs planned consumption</h2><p>Six-month quantities from active database records (MT)</p></div><div class="procurement-chart-tools"><div class="procurement-chart-legend"><span><i class="purchases"></i>Purchases</span><span><i class="consumption"></i>Planned consumption</span></div></div></header>
            <div class="procurement-column-chart" role="img" aria-label="Monthly purchases and planned consumption bar chart">
                @foreach($chart['months'] as $month)
                    <div class="procurement-chart-month">
                        <div class="procurement-chart-bars">
                            <i class="purchases" style="height:{{ max($month['purchases'] > 0 ? 4 : 0, ($month['purchases'] / $chartMax) * 100) }}%" title="{{ $month['label'] }} purchases: {{ number_format($month['purchases'],3) }} MT"><b>{{ $month['purchases'] > 0 ? number_format($month['purchases'],1) : '' }}</b></i>
                            <i class="consumption" style="height:{{ max($month['consumption'] > 0 ? 4 : 0, ($month['consumption'] / $chartMax) * 100) }}%" title="{{ $month['label'] }} consumption: {{ number_format($month['consumption'],3) }} MT"><b>{{ $month['consumption'] > 0 ? number_format($month['consumption'],1) : '' }}</b></i>
                        </div>
                        <small>{{ $month['label'] }}</small>
                    </div>
                @endforeach
            </div>
        </article>
        <article class="procurement-chart-card procurement-plant-chart">
            <header><div><span>Plant distribution</span><h2>Planned consumption by plant</h2><p>Active consumption quantities (MT)</p></div></header>
            <div class="procurement-plant-bars">
                @forelse($chart['plants'] as $index => $plant)
                    <div class="procurement-plant-row"><div><strong>{{ $plant['name'] }}</strong><small>{{ $plant['code'] }}</small></div><span><i style="width:{{ ($plant['total'] / $plantMax) * 100 }}%"></i></span><b>{{ number_format($plant['total'],1) }}</b></div>
                @empty
                    <div class="procurement-chart-empty">No active plant consumption records are available.</div>
                @endforelse
            </div>
        </article>
    </section>
@endsection
@push('styles')
<style>
.procurement-toolbar{display:flex;min-height:80px;align-items:center;justify-content:space-between;gap:24px;margin-bottom:20px;padding:16px 20px;border:1px solid #dce6f1;border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(15,42,76,.055)}.procurement-toolbar>div{flex:1 1 auto;min-width:260px}.procurement-toolbar-eyebrow{color:var(--theme-primary,#bd2ca6);font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.procurement-toolbar>div>p{margin:4px 0 0;color:#64748b;font-size:12px}.procurement-toolbar-filters{display:flex;flex:0 1 auto;align-items:center;justify-content:flex-end;gap:10px;flex-wrap:nowrap}.procurement-toolbar-filters select{width:220px;height:42px;border:1px solid #cad8e7;border-radius:9px;background:#fff;padding:0 34px 0 12px;color:#102a4c;font-size:13px;font-weight:700;outline:0}.procurement-toolbar-filters select[name=material]{width:360px}.procurement-toolbar-filters select:focus{border-color:var(--theme-primary,#bd2ca6);box-shadow:0 0 0 3px color-mix(in srgb,var(--theme-primary,#bd2ca6) 12%,transparent)}.procurement-toolbar-filters .select2-container{width:360px!important;min-width:260px}.procurement-toolbar-filters .select2-selection--single{height:42px!important;border-color:#cad8e7!important;border-radius:9px!important}.procurement-toolbar-filters .select2-selection__rendered{line-height:40px!important;color:#102a4c!important;font-size:13px;font-weight:700}.procurement-toolbar-filters .select2-selection__arrow{height:40px!important}.procurement-toolbar-filters>span{color:#64748b;font-size:12px;white-space:nowrap}@media(max-width:1200px){.procurement-toolbar{align-items:flex-start;flex-direction:column}.procurement-toolbar>div{min-width:0}.procurement-toolbar-filters{width:100%;justify-content:flex-start}}@media(max-width:760px){.procurement-toolbar-filters{align-items:stretch;flex-direction:column}.procurement-toolbar-filters select,.procurement-toolbar-filters .select2-container{width:100%!important;min-width:0}.procurement-toolbar-filters>span{width:100%}}
.procurement-chart-grid{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(340px,.9fr);gap:18px;margin-top:22px}.procurement-chart-card{min-width:0;border:1px solid #e3dbe1;border-radius:14px;background:#fff;padding:20px;box-shadow:0 8px 24px rgba(65,35,57,.045)}.procurement-chart-card>header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:20px}.procurement-chart-card>header span{color:var(--theme-primary,#bd2ca6);font-size:9px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.procurement-chart-card h2{margin:3px 0 0;color:var(--theme-sidebar-bg,#2b1828);font-size:16px;font-weight:800}.procurement-chart-card header p{margin:4px 0 0;color:#786c75;font-size:10px}.procurement-chart-legend{display:flex;gap:13px;white-space:nowrap}.procurement-chart-legend span{display:flex;align-items:center;gap:6px;color:#6f636c!important;font-size:10px!important;letter-spacing:0!important;text-transform:none!important}.procurement-chart-legend i{width:9px;height:9px;border-radius:3px}.procurement-chart-legend .purchases,.procurement-chart-bars .purchases{background:var(--theme-secondary,#f36b2f)}.procurement-chart-legend .consumption,.procurement-chart-bars .consumption{background:var(--theme-primary,#bd2ca6)}.procurement-column-chart{display:grid;height:255px;grid-template-columns:repeat(6,minmax(54px,1fr));gap:12px;padding:24px 8px 0;border-bottom:1px solid #e5dde3;background:repeating-linear-gradient(to bottom,#fff 0,#fff 50px,#f1ebef 51px)}.procurement-chart-month{display:grid;min-width:0;grid-template-rows:1fr 30px;gap:7px}.procurement-chart-bars{display:flex;height:100%;align-items:flex-end;justify-content:center;gap:7px}.procurement-chart-bars>i{position:relative;width:min(26px,36%);min-height:0;border-radius:6px 6px 2px 2px;transition:filter .18s,transform .18s}.procurement-chart-bars>i:hover{filter:saturate(1.15);transform:translateY(-2px)}.procurement-chart-bars b{position:absolute;bottom:calc(100% + 4px);left:50%;color:#5d5059;font-size:8px;font-style:normal;transform:translateX(-50%);white-space:nowrap}.procurement-chart-month>small{overflow:hidden;color:#756a72;font-size:9px;font-weight:700;text-align:center;text-overflow:ellipsis;white-space:nowrap}.procurement-plant-bars{display:grid;gap:13px}.procurement-plant-row{display:grid;grid-template-columns:minmax(105px,1fr) minmax(100px,1.5fr) 58px;align-items:center;gap:10px}.procurement-plant-row>div{min-width:0}.procurement-plant-row strong{display:block;overflow:hidden;color:#4b3f48;font-size:10px;text-overflow:ellipsis;white-space:nowrap}.procurement-plant-row small{display:block;color:#897d85;font-size:8px}.procurement-plant-row>span{height:8px;overflow:hidden;border-radius:999px;background:#f1eaef}.procurement-plant-row>span i{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,var(--theme-primary,#bd2ca6),var(--theme-secondary,#f36b2f))}.procurement-plant-row>b{color:#4f424b;font-size:10px;text-align:right}.procurement-chart-empty{display:grid;min-height:210px;place-items:center;border:1px dashed #ded4dc;border-radius:10px;color:#81747d;font-size:11px;text-align:center}.procurement-plant-chart>header span{color:var(--theme-secondary,#f36b2f)}@media(max-width:1050px){.procurement-chart-grid{grid-template-columns:1fr}}@media(max-width:640px){.procurement-chart-card{padding:15px}.procurement-chart-card>header{align-items:flex-start;flex-direction:column}.procurement-column-chart{height:230px;gap:5px;overflow-x:auto}.procurement-chart-month{min-width:48px}.procurement-chart-legend{flex-wrap:wrap}.procurement-plant-row{grid-template-columns:minmax(92px,1fr) minmax(90px,1.25fr) 52px}}
.procurement-chart-tools{display:grid;justify-items:end;gap:10px}.procurement-chart-tools form{display:flex;align-items:center;gap:8px}.procurement-chart-tools form label{color:#766a73;font-size:9px;font-weight:800;text-transform:uppercase}.procurement-chart-tools form select{width:min(300px,28vw);height:38px;border:1px solid #d8cdd5;border-radius:8px;background:#fff;padding:0 32px 0 10px;color:#4f424b;font-size:11px;font-weight:700;outline:0}.procurement-chart-tools form select:focus{border-color:var(--theme-primary,#bd2ca6);box-shadow:0 0 0 3px color-mix(in srgb,var(--theme-primary,#bd2ca6) 12%,transparent)}.procurement-chart-tools .select2-container{width:min(300px,28vw)!important}.procurement-chart-tools .select2-selection--single{height:38px!important;border-color:#d8cdd5!important;border-radius:8px!important}.procurement-chart-tools .select2-selection__rendered{line-height:36px!important;font-size:11px;font-weight:700}.procurement-chart-tools .select2-selection__arrow{height:36px!important}@media(max-width:640px){.procurement-chart-tools{width:100%;justify-items:stretch}.procurement-chart-tools form{align-items:stretch;flex-direction:column}.procurement-chart-tools form select,.procurement-chart-tools .select2-container{width:100%!important;font-size:16px}}
</style>
@endpush
