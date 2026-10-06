@extends('layout.app')
@section('title', 'Procurement Overview')
@section('content')
    <x-page-header title="Procurement" description="Materials, purchasing and plant consumption workspace."><span
            class="crud-breadcrumb">Procurement <b>/</b> Overview</span></x-page-header>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([['Companies', $stats['companies'], 'company'], ['Plants', $stats['plants'], 'plant'], ['Active purchases', $stats['purchases'], 'active-purchases'], ['Purchased MT', number_format($stats['purchase_mt'], 3), 'purchased'], ['Consumed MT', number_format($stats['consumption_mt'], 3), 'consumed']] as [$label, $value, $icon])
            <article class="card procurement-overview-stat">
                <span class="procurement-overview-icon"><x-app-icon :name="$icon" /></span>
                <div><p class="text-sm text-slate-500">{{ $label }}</p><strong class="mt-1 block text-3xl">{{ $value }}</strong></div>
            </article>
        @endforeach
    </div>
    <div class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
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
        @can('procurement.purchases.view')
            <a class="card procurement-overview-action" href="{{ route('admin.procurement.purchases') }}">
                <span class="procurement-overview-icon"><x-app-icon name="purchase-entry" /></span><div><h2 class="font-semibold">Purchase Entry</h2>
                <p class="mt-2 text-sm text-slate-500">Record supplier purchases and expected delivery dates.</p></div><span class="procurement-action-arrow">→</span>
            </a>
        @endcan
        @can('procurement.daily_consumption.view')
            <a class="card procurement-overview-action" href="{{ route('admin.procurement.consumptions') }}">
                <span class="procurement-overview-icon"><x-app-icon name="daily-consumption" /></span><div><h2 class="font-semibold">Daily Consumption</h2>
                <p class="mt-2 text-sm text-slate-500">Capture plant-wise material usage with revision history.</p></div><span class="procurement-action-arrow">→</span>
            </a>
        @endcan
    </div>
@endsection
