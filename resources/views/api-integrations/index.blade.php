@extends('layout.app')
@section('title', 'API Integrations')
@section('content')
    <x-page-header title="API Integrations"
        description="Configure external endpoints and review their latest synchronization status.">
        <span class="crud-breadcrumb">Settings <b>/</b> API Integrations</span></x-page-header>
    <div class="datatable-page-actions" data-datatable-toolbar-for="api-integrations-datatable">
        @can('api-integrations.create')
            <a class="crud-add" data-form-modal data-modal-size="large" data-modal-title="Create API integration" href="{{ route('admin.api-integrations.create') }}">＋ Add API</a>
        @endcan
    </div>
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="table nowrap min-w-[1050px]" id="api-integrations-datatable">
                <thead>
                    <tr>
                        <th aria-label="Details"></th>
                        <th>S.NO.</th>
                        <th>Name</th>
                        <th>Endpoint</th>
                        <th>Method</th>
                        <th>Authentication</th>
                        <th>Save to table</th>
                        <th>Retries</th>
                        <th>Status</th>
                        <th>Last run</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($integrations as $integration)
                        @php($lastRun = $integration->runs->first())
                        <tr>
                            <td></td>
                            <td>{{ $loop->iteration }}</td>
                            <td><strong>{{ $integration->name }}</strong><small
                                    class="block text-slate-400">{{ $integration->code }}{{ $integration->code === $currentEnvironmentCode ? ' · current environment' : '' }}</small></td>
                            <td class="max-w-sm break-all">{{ $integration->endpoint_path ?: '—' }}
                            </td>
                            <td>{{ $integration->http_method }}</td>
                            <td>{{ str($integration->auth_type)->replace('_', ' ')->title() }}<small
                                    class="block text-slate-400">{{ $integration->credential_env_key ?: 'No credential' }}</small>
                            </td>
                            <td>{{ $integration->tbl_name ?: 'Raw payload only' }}</td>
                            <td>{{ $integration->retry_count }} × {{ $integration->retry_delay_seconds }}s</td>
                            <td>
                                @can('api-integrations.edit')
                                    <button type="button" class="status-toggle {{ $integration->status ? 'active' : 'inactive' }}"
                                        data-status-url="{{ route('admin.status.toggle', ['api-integrations', $integration->id]) }}"
                                        aria-pressed="{{ $integration->status ? 'true' : 'false' }}"
                                        aria-label="{{ $integration->status ? 'Deactivate' : 'Activate' }} integration">
                                        <span class="status-switch"><i></i></span>
                                        <span class="status-toggle-label">{{ $integration->status ? 'Active' : 'Inactive' }}</span>
                                    </button>
                                @else
                                    <span class="{{ $integration->status ? 'text-emerald-700' : 'text-slate-400' }}">{{ $integration->status ? 'Active' : 'Inactive' }}</span>
                                @endcan
                            </td>
                            <td>{{ $lastRun ? str($lastRun->status)->title() : 'Never' }}<small
                                    class="block text-slate-400">{{ $lastRun?->started_at?->format('d M Y H:i') }}</small>
                            </td>
                            <td>
                                <div class="flex gap-2">
                                    @can('api-integrations.edit')
                                        <form method="POST" action="{{ route('admin.api-integrations.run', $integration) }}">
                                            @csrf<button class="btn-primary inline-flex items-center gap-1.5" @disabled(!$integration->status) title="Run synchronization">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m8 5 11 7-11 7Z"/></svg>
                                                <span>Run</span>
                                            </button></form>
                                        @endcan @can('api-integrations.view')
                                        <a class="btn-secondary inline-flex items-center gap-1.5"
                                            href="{{ route('admin.api-integrations.runs', $integration) }}" title="View run logs">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>
                                            <span>Logs</span>
                                        </a>
                                        @endcan @can('api-integrations.edit')
                                        <a class="btn-secondary inline-flex items-center gap-1.5" data-form-modal data-modal-size="large" data-modal-title="Edit API integration"
                                            href="{{ route('admin.api-integrations.edit', $integration) }}" title="Edit API integration">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="m16.5 3.5 4 4L8 20l-5 1 1-5Z"/></svg>
                                            <span>Edit</span>
                                        </a>
                                        @endcan @can('api-integrations.delete')
                                        <form method="POST"
                                            action="{{ route('admin.api-integrations.destroy', $integration) }}"
                                            onsubmit="return confirm('Delete this API configuration and its logs?')">@csrf
                                            @method('DELETE')<button class="btn-danger inline-flex items-center gap-1.5" title="Delete API integration">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 15h10l1-15"/></svg>
                                                <span>Delete</span>
                                            </button></form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
