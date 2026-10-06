@extends('layout.app')
@section('title', 'All Materials')
@include('procurement::planning-styles')
@section('content')
    <x-page-header title="All Materials" description="Plant-wise material stock synchronized from SAP.">
        <span class="crud-breadcrumb">{{ $isMaster ? 'Master Control' : 'Procurement' }} <b>/</b> All
            Materials</span></x-page-header>
  
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
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
