@extends('layout.app')
@section('title', $integration->name.' Logs')
@section('content')
<x-page-header :title="$integration->name.' Logs'" description="Run history is retained for 24 hours.">
    <a class="btn-secondary inline-flex items-center gap-2" href="{{ route('admin.api-integrations.index') }}">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        <span>Back</span>
    </a>
</x-page-header>
<div class="card overflow-hidden p-0">
    <div class="overflow-x-auto">
        <table class="table nowrap min-w-[980px]" id="api-runs-datatable">
            <thead><tr><th aria-label="Details"></th><th>S.NO.</th><th>Run</th><th>Status</th><th>Progress</th><th>Started</th><th>Completed</th><th>HTTP</th><th>Received</th><th>Saved</th><th>Error / attempts</th></tr></thead>
            <tbody>
            @foreach($runs as $run)
                <tr>
                    <td></td><td>{{ $loop->iteration }}</td>
                    <td><code>{{ $run->run_uuid }}</code></td>
                    <td>
                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold
                            {{ $run->status === 'success' ? 'bg-emerald-50 text-emerald-700' : ($run->status === 'failed' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700') }}">
                            @if(in_array($run->status, ['queued', 'running'], true))
                                <span class="h-2 w-2 animate-pulse rounded-full bg-amber-500"></span>
                            @endif
                            {{ str($run->status)->title() }}
                        </span>
                    </td>
                    <td>
                        @if($run->materials_total)
                            <strong>{{ number_format($run->materials_processed) }} / {{ number_format($run->materials_total) }}</strong>
                            <small class="mt-1 block text-slate-500">{{ number_format(($run->materials_processed / $run->materials_total) * 100, 1) }}%</small>
                        @else
                            —
                        @endif
                    </td>
                    <td data-order="{{ $run->started_at?->timestamp ?? 0 }}">{{ $run->started_at?->format('d M Y H:i:s') }}</td>
                    <td data-order="{{ $run->completed_at?->timestamp ?? 0 }}">{{ $run->completed_at?->format('d M Y H:i:s') ?? '—' }}</td>
                    <td>{{ $run->http_status ?? '—' }}</td>
                    <td>{{ $run->records_received }}</td>
                    <td>{{ $run->records_saved }}</td>
                    <td><strong>{{ $run->error_message ?: '—' }}</strong>@foreach($run->attempts as $attempt)<small class="mt-1 block text-slate-500">Attempt {{ $attempt->attempt_number }} · {{ $attempt->status }} · HTTP {{ $attempt->http_status ?? '—' }} · {{ $attempt->duration_ms ?? 0 }}ms {{ $attempt->error_message }}</small>@endforeach</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@if($runs->contains(fn ($run) => in_array($run->status, ['queued', 'running'], true)))
    @push('scripts')
        <script>
            window.setTimeout(() => window.location.reload(), 5000);
        </script>
    @endpush
@endif
