@extends('layout.app')
@section('title', 'All Materials')
@include('procurement::planning-styles')
@section('content')
    <x-page-header title="All Materials" description="Plant-wise material stock synchronized from SAP.">
        <span class="crud-breadcrumb">{{ $isMaster ? 'Master Control' : 'Procurement' }} <b>/</b> All
            Materials</span>
    </x-page-header>

    @if ($isMaster)
        @can('procurement.all_materials.all_companies')
        <div class="flex flex-wrap justify-end gap-2 mb-4">
            <form method="POST" action="{{ route('admin.procurement.materials.dump-procurement') }}">
                @csrf
                <button type="submit" class="btn-secondary" title="Download structure and active status 1 procurement data">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14" />
                    </svg>
                    Download Active Procurement Dump
                </button>
            </form>
            <form method="POST" action="{{ route('admin.procurement.materials.truncate-procurement') }}"
                onsubmit="return confirm('This will deactivate all active procurement daily consumption and purchase records by setting status to 0. No database rows will be deleted. Continue?')">
                @csrf
                <button type="submit" class="btn-danger" title="Deactivate active procurement transaction data">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13M10 11v5m4-5v5" />
                    </svg>
                    Clear Procurement Data
                </button>
            </form>
        </div>
        @endcan
    @endif
  
    <div class="card overflow-hidden p-0">
        <div class="materials-table-wrap overflow-x-auto">
            <table class="table nowrap min-w-[1000px]" id="materials-datatable" data-actions="{{ $isMaster ? 1 : 0 }}"
                data-show-remarks="{{ request()->boolean('show_remarks') ? 1 : 0 }}"
                data-source="{{ $isMaster ? route('admin.materials.index', array_merge(request()->only('company_id', 'plant_id', 'material_group', 'material', 'show_remarks'), ['datatable' => 1, 'context' => 'master'])) : route('admin.procurement.materials', array_merge(request()->only('company_id', 'plant_id', 'material_group', 'material', 'show_remarks'), ['datatable' => 1])) }}">
                <thead>
                    <tr>
                        <th aria-label="Details"></th>
                        <th>S.No</th>
                        <th>Material</th>
                        <th>Type</th>
                        <th>Group</th>
                        <th>Unit</th>
                        <th>Plant Name</th>
                        <th>Current Stock</th>
                        <th>Stock Value</th>
                        <th>Status</th>
                        @if (request()->boolean('show_remarks'))
                            <th>Remarks</th>
                            @endif @if ($isMaster)
                                <th>Actions</th>
                            @endif
                    </tr>
                </thead>
                <tbody>
                
                
                </tbody>
            </table>
        </div>
    </div>
@endsection
