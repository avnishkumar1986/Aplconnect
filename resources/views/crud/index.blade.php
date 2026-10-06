@extends('layout.app')
@section('title',$definition['title'])
@section('content')
@php
    $useFormModal = !($dedicatedRoutes ?? false) || ($formsInModal ?? false);
    $activeSort = request('sort', 'id');
    $activeDirection = request('direction', 'desc');
    $sortUrl = function ($field) use ($activeSort, $activeDirection) {
        $params = request()->except('page');
        $params['sort'] = $field;
        $params['direction'] = $activeSort === $field && $activeDirection === 'asc' ? 'desc' : 'asc';
        return url()->current().'?'.http_build_query($params);
    };
    $sortIcon = fn ($field) => $activeSort === $field ? ($activeDirection === 'asc' ? '↑' : '↓') : '↕';
    $statusField = collect($definition['fields'])->keys()->first(fn ($field) => strtolower($field) === 'status');
    $bulkEnabled = in_array($resource, ['addresses', 'contacts', 'accounts', 'user-types', 'companies'], true) && $statusField;
    $showIdColumn = !in_array($resource, ['user-types', 'companies'], true);
@endphp
<x-page-header :title="$definition['title']" :description="'Manage '.strtolower($definition['title']).' records.'">
    <span class="crud-breadcrumb">Records <b>/</b> {{ $definition['title'] }}</span>
</x-page-header>

<section class="crud-list-card">
    <div class="crud-toolbar">
        <form class="crud-search" method="GET" data-live-search><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input name="q" value="{{ request('q') }}" placeholder="Search records..." autocomplete="off" aria-label="Search records"><button>Search</button></form>
        @if($bulkEnabled)
            @can($resource.'.edit')
                <form class="table-bulk-actions" method="POST"
                    action="{{ route('admin.crud.bulk-action', $resource) }}" data-table-bulk-form>
                    @csrf
                    <select name="action" required>
                        <option value="">Bulk actions</option>
                        <option value="activate">Activate</option>
                        <option value="deactivate">Deactivate</option>
                    </select>
                    <button type="submit" disabled>Apply</button>
                    <span data-bulk-count>0 selected</span>
                </form>
            @endcan
        @endif
        @can($resource.'.create')
            @if(($dedicatedRoutes??false) && ($formsInModal??false))<a class="crud-add" data-form-modal data-modal-title="Create {{ Str::singular($definition['title']) }}" href="{{ route('admin.'.$resource.'.create') }}"><span>＋</span> Add New {{ Str::singular($definition['title']) }}</a>
            @elseif($dedicatedRoutes??false)<a class="crud-add" href="{{ route('admin.'.$resource.'.create') }}"><span>＋</span> Add New {{ Str::singular($definition['title']) }}</a>
            @else<a class="crud-add" data-form-modal data-modal-size="default" data-modal-title="Create {{ Str::singular($definition['title']) }}" href="{{ route('admin.crud.create',$resource) }}"><span>＋</span> Add New {{ Str::singular($definition['title']) }}</a>@endif
        @endcan
    </div>
    <div class="crud-results" data-crud-results>
    <div class="crud-mobile-note">Swipe horizontally to view all columns</div>
    <div class="crud-table-wrap"><table class="crud-table">
        <thead><tr><th class="check"><input type="checkbox" data-bulk-select-all aria-label="Select all"></th><th>S.NO.</th>@if($showIdColumn)<th><a class="table-sort" data-sort-link href="{{ $sortUrl('id') }}">ID <i>{{ $sortIcon('id') }}</i></a></th>@endif @foreach($definition['fields'] as $name=>$field)@continue($field['table_hidden']??false)<th>@if(in_array($name,$sortableFields??[],true))<a class="table-sort" data-sort-link href="{{ $sortUrl($name) }}">{{ $field['label']??ucwords(str_replace('_',' ',$name)) }} <i>{{ $sortIcon($name) }}</i></a>@else{{ $field['label']??ucwords(str_replace('_',' ',$name)) }}@endif</th>@endforeach<th>View Details</th><th>Action</th></tr></thead>
        <tbody>
        @forelse($records as $record)
            <tr>
                <td class="check"><input type="checkbox" value="{{ $record->id }}" data-bulk-select aria-label="Select record {{ $record->id }}"></td>
                <td class="record-serial">{{ ($records->firstItem() ?? 1) + $loop->index }}</td>
                @if($showIdColumn)<td class="record-id">#{{ $record->id }}</td>@endif
                @foreach($definition['fields'] as $name=>$field)
                    @continue($field['table_hidden']??false)
                    @php $value = $record->{$name}; @endphp
                    <td>
                        @if(strtolower($name)==='status')<span class="status-pill {{ (string)$value==='1'?'active':'inactive' }}">{{ (string)$value==='1'?'Active':'Inactive' }}</span>
                        @elseif(($field['type']??'')==='checkbox'){{ $value?'Yes':'No' }}
                        @elseif(($field['type']??'')==='select' && isset($field['options'][(string)$value])){{ $field['options'][(string)$value] }}
                        @elseif(($field['type']??'')==='model')
                            @php $relationName=$field['relation']??str($name)->beforeLast('_id')->camel()->toString(); @endphp
                            {{ $record->{$relationName}?->{$field['display']} ?? $value ?? '—' }}
                        @elseif($value instanceof \DateTimeInterface){{ $value->format('d M Y') }}
                        @else{{ is_bool($value)?($value?'Yes':'No'):($value??'—') }}@endif
                    </td>
                @endforeach
                @php $editUrl=($dedicatedRoutes??false)?route('admin.'.$resource.'.edit',$record):route('admin.crud.edit',[$resource,$record->id]); $viewUrl=$resource==='users'?route('admin.users.show',$record):$editUrl; $deleteUrl=($dedicatedRoutes??false)?route('admin.'.$resource.'.destroy',$record):route('admin.crud.destroy',[$resource,$record->id]); @endphp
                <td>@can($resource.'.view')<a class="view-detail" @if($useFormModal && $resource!=='users')data-form-modal data-modal-title="View {{ Str::singular($definition['title']) }}"@endif href="{{ $viewUrl }}">View Details</a>@else<span>—</span>@endcan</td>
                <td><div class="row-actions">@can($resource.'.edit')<a class="edit" @if($useFormModal)data-form-modal data-modal-title="Edit {{ Str::singular($definition['title']) }}"@endif href="{{ $editUrl }}" aria-label="Edit record"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="m16.5 3.5 4 4L8 20l-5 1 1-5Z"/></svg></a>@endcan @can($resource.'.delete')<form method="POST" action="{{ $deleteUrl }}" onsubmit="return confirm('Delete this record?')">@csrf @method('DELETE')<button class="delete" aria-label="Delete record"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M6 6l1 15h10l1-15"/></svg></button></form>@endcan</div></td>
            </tr>
        @empty
            <tr><td colspan="{{ collect($definition['fields'])->reject(fn($field)=>$field['table_hidden']??false)->count() + ($showIdColumn ? 5 : 4) }}" class="empty-state">No records found.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <footer class="crud-footer"><p>Showing {{ $records->firstItem()??0 }} to {{ $records->lastItem()??0 }} of {{ $records->total() }} entries</p><nav class="crud-pagination" aria-label="Table pagination">
        @if($records->onFirstPage())<span class="pagination-button disabled">Previous</span>@else<a class="pagination-button" href="{{ $records->previousPageUrl() }}">Previous</a>@endif
        <span class="pagination-count">Page {{ $records->currentPage() }} of {{ max(1,$records->lastPage()) }}</span>
        @if($records->hasMorePages())<a class="pagination-button" href="{{ $records->nextPageUrl() }}">Next</a>@else<span class="pagination-button disabled">Next</span>@endif
    </nav></footer>
    </div>
</section>
@endsection
