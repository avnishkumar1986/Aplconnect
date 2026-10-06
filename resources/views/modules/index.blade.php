@extends('layout.app')
@section('title','Module Upload')
@section('content')
<x-page-header title="Module Upload" description="Upload and manage packaged APL Connect modules.">
    <span class="crud-breadcrumb">Modules <b>/</b> Upload</span>
</x-page-header>

<div class="module-layout">
    <div><div id="react-modules-form"></div><script id="react-modules-form-props" type="application/json">{!! json_encode(['action'=>route('admin.modules.store'),'values'=>old(),'errors'=>$errors->toArray()],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script></div>
    <section class="module-list-card">
        <header><div><p class="module-eyebrow">Module registry</p><h2>Available Modules</h2><p>{{ $modules->total() }} module{{ $modules->total()===1?'':'s' }} detected in this workspace</p></div>
            <form method="GET"><input name="q" value="{{ request('q') }}" placeholder="Search modules…"></form>
        </header>
        <div class="module-table-wrap crud-table-wrap"><table class="module-table crud-table">
            <thead><tr><th>Module</th><th>Version</th><th>Source</th><th>Package / Folder</th><th>Status</th><th>Detected</th><th>Action</th></tr></thead>
            <tbody>@forelse($modules as $module)<tr>
                <td><strong>{{ $module->name }}</strong><small>{{ $module->description ?: 'No description provided' }}</small></td>
                <td><span class="module-version">v{{ $module->version }}</span></td>
                <td><span class="module-source {{ $module->source_type }}">{{ $module->source_type === 'discovered' ? 'Folder' : 'Upload' }}</span></td>
                <td>{{ $module->original_filename }}<small>{{ number_format($module->file_size / 1024, 1) }} KB</small></td>
                <td><form method="POST" action="{{ route('admin.modules.status',$module) }}">@csrf @method('PATCH')
                    <button type="submit" class="status-toggle {{ $module->status === 'active' ? 'active' : 'inactive' }}"
                        data-status-url="{{ route('admin.modules.status', $module) }}"
                        aria-pressed="{{ $module->status === 'active' ? 'true' : 'false' }}"
                        title="{{ $module->status === 'active' ? 'Click to deactivate module' : 'Click to activate module' }}"
                        aria-label="{{ $module->status === 'active' ? 'Disable' : 'Enable' }} module">
                        <span class="status-switch"><i></i></span><span class="status-toggle-label">{{ ucfirst($module->status) }}</span>
                    </button>
                </form></td>
                <td>{{ $module->created_at->format('d M Y') }}</td>
                <td><div class="module-actions">
                    <button type="button" class="module-view" data-module-view
                        data-name="{{ $module->name }}"
                        data-version="{{ $module->version }}"
                        data-source="{{ $module->source_type === 'discovered' ? 'Local folder' : 'Uploaded ZIP' }}"
                        data-path="{{ $module->source_type === 'discovered' ? base_path($module->stored_path) : storage_path('app/'.$module->stored_path) }}"
                        data-description="{{ $module->description }}"
                        data-status="{{ ucfirst($module->status) }}">View</button>
                </div></td>
            </tr>@empty<tr><td colspan="7" class="empty-state">No modules detected or uploaded yet.</td></tr>@endforelse</tbody>
        </table></div>
        <footer class="crud-footer"><p>Showing {{ $modules->firstItem()??0 }} to {{ $modules->lastItem()??0 }} of {{ $modules->total() }} entries</p><div>{{ $modules->links() }}</div></footer>
    </section>
</div>

<dialog class="module-view-dialog" data-module-dialog>
    <header><div><p>Module details</p><h3 data-detail-name></h3></div><button type="button" data-module-close aria-label="Close">×</button></header>
    <div class="module-detail-grid">
        <div><span>Version</span><strong data-detail-version></strong></div>
        <div><span>Status</span><strong data-detail-status></strong></div>
        <div><span>Source</span><strong data-detail-source></strong></div>
        <div class="module-detail-wide"><span>Folder / package location</span><code data-detail-path></code></div>
        <div class="module-detail-wide"><span>Description</span><p data-detail-description></p></div>
    </div>
    <footer><button type="button" class="btn-secondary" data-module-close>Close</button></footer>
</dialog>
@endsection
