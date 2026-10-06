@extends('layout.app')
@section('title','Action Logs')
@section('content')
<x-page-header title="Action Logs" description="Review administrative and record-level activity across APL Connect.">
    <span class="crud-breadcrumb">Monitoring <b>/</b> Action Logs</span>
</x-page-header>

<section class="action-log-card">
    <form class="action-log-toolbar" method="GET">
        <label class="action-log-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input name="q" value="{{ request('q') }}" placeholder="Search action log messages…">
        </label>
        <select name="type" onchange="this.form.submit()">
            <option value="">Filter by type</option>
            @foreach(['Created','Updated','Deleted'] as $type)<option value="{{ $type }}" @selected(request('type')===$type)>{{ $type }}</option>@endforeach
        </select>
    </form>
    <div class="action-log-table-wrap">
        <table class="action-log-table crud-table">
            <thead><tr><th>Type</th><th>Title</th><th>Action By</th><th>Created At</th><th>Data</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td><span class="log-type {{ strtolower($log->type) }}">{{ $log->type }}</span></td>
                    <td><strong>{{ $log->title }}</strong><small>{{ $log->ip_address }}</small></td>
                    <td><span class="log-actor">{{ $log->actor?->name ?? 'System' }}</span></td>
                    <td>{{ $log->created_at->format('d M Y, h:i A') }}</td>
                    <td><details class="log-details"><summary>View Details</summary><pre>{{ json_encode($log->data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre></details></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-state">No action logs found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <footer class="crud-footer"><p>Showing {{ $logs->firstItem()??0 }} to {{ $logs->lastItem()??0 }} of {{ $logs->total() }} entries</p><div>{{ $logs->links() }}</div></footer>
</section>
@endsection
