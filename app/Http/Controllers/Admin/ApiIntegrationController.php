<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportMaterialsFromApi;
use App\Jobs\ImportApiInChunks;
use App\Jobs\ImportCustomerSalesSummaries;
use App\Jobs\ImportCustomerCollections;
use App\Models\ApiIntegration;
use App\Models\ApiIntegrationLog;
use App\Models\ModulePackage;
use App\Services\Security\ProjectSecurityAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ApiIntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->reconcileStaleRuns();

        $options = config('datatable.page_size_options');
        $perPage = (int) $request->integer('per_page', config('datatable.per_page'));
        if (! in_array($perPage, $options, true)) {
            $perPage = (int) config('datatable.per_page');
        }
        $search = trim((string) $request->string('search'));
        $user = $request->user();
        $isSuperAdmin = $user?->hasRole('Owner') ?? false;
        if ($isSuperAdmin) {
            $allowedCodes = ApiIntegration::query()->pluck('code')->push('security')->unique()->values();
            $manageableCodes = $allowedCodes;
            $runnableCodes = $allowedCodes;
        } else {
            $allowedCodes = collect(['material', 'production', 'quality', 'stock', 'mrmcon', 'vendor'])
                ->filter(fn (string $code): bool => $user?->can('procurement.api.'.$this->permissionCode($code).'.view') ?? false)
                ->values();
            if ($user?->can('users.view')) {
                $allowedCodes->push('employee');
                $allowedCodes->push('cust');
                $allowedCodes->push('pending_sales');
                $allowedCodes->push('sales_data');
                $allowedCodes->push('customer_sales_summary');
                $allowedCodes->push('customer_collections');
            }
            if ($user?->can('sales_distribution.customers.view')) {
                $allowedCodes->push('cust')->push('customer');
            }
            if ($user?->can('sales_distribution.customer_sales_summary.view')) {
                $allowedCodes->push('customer_sales_summary');
                $allowedCodes->push('customer_collections');
            }
            if ($user?->can('sales_distribution.pending_sales.view')) {
                $allowedCodes->push('pending_sales');
            }
            if ($user?->can('sales_distribution.daily_sales.view')) {
                $allowedCodes->push('sales_data');
            }
            $allowedCodes = $allowedCodes->unique()->values();
            $manageableCodes = $allowedCodes
                ->filter(fn (string $code): bool => $this->canManageCode($request, $code))
                ->values();
            $runnableCodes = $allowedCodes
                ->filter(fn (string $code): bool => $this->canRunCode($request, $code))
                ->values();
        }
        if ($perPage < $allowedCodes->count()) {
            $perPage = (int) (collect($options)
                ->first(fn (int $option): bool => $option >= $allowedCodes->count())
                ?? max($options));
        }
        $runnableCodes = ApiIntegration::query()
            ->whereIn('code', $runnableCodes)
            ->get()
            ->filter(fn (ApiIntegration $integration): bool => strtolower($integration->code) === 'security' || $this->hasBackgroundImporter($integration))
            ->pluck('code')
            ->map(fn (string $code): string => strtolower($code))
            ->values();
        $query = ApiIntegration::query()
            ->whereIn('code', $allowedCodes)
            ->with(['module:id,name,slug', 'logs' => fn ($q) => $q
                ->select([
                    'id',
                    'api_integration_id',
                    'run_id',
                    'status',
                    'http_status',
                    'attempt',
                    'duration_ms',
                    'records_received',
                    DB::raw('LEFT(message, 2000) as message'),
                    'created_at',
                ])
                ->latest('id')
                ->limit(20)]);

        $procurementCodes = collect(['material', 'stock', 'vendor', 'mrmcon']);
        $canSeeProcurementSync = $procurementCodes->every(fn (string $code): bool => $allowedCodes->contains($code));
        $canRunProcurementSync = $canSeeProcurementSync
            && $procurementCodes->every(fn (string $code): bool => $runnableCodes->contains($code));
        $procurementIntegrations = $canSeeProcurementSync
            ? ApiIntegration::query()->whereIn('code', $procurementCodes)->get()->keyBy(fn (ApiIntegration $api): string => strtolower($api->code))
            : collect();

        return Inertia::render('admin/api-integrations/index', [
            'integrations' => $query
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('endpoint', 'like', "%{$search}%")->orWhere('target_table', 'like', "%{$search}%")))
                ->orderByDesc('status')->orderBy('name')->paginate($perPage)->withQueryString()
                ->through(function (ApiIntegration $integration): ApiIntegration {
                    $credentialState = $integration->credentialState();
                    $credentials = $credentialState['credentials'];
                    $integration->setAttribute('credential_username', (string) ($credentials['username'] ?? ''));
                    $integration->setAttribute('has_credential_password', filled($credentials['password'] ?? null));
                    $integration->setAttribute('subscription_name', (string) ($credentials['subscription_name'] ?? ''));
                    $integration->setAttribute('has_api_token', filled($credentials['token'] ?? null));
                    $integration->setAttribute('credentials_unreadable', $credentialState['unreadable']);

                    return $integration;
                }),
            'filters' => ['search' => $search, 'per_page' => $perPage], 'pageSizeOptions' => $options,
            'stats' => [
                'total' => ApiIntegration::whereIn('code', $allowedCodes)->count(),
                'active' => ApiIntegration::whereIn('code', $allowedCodes)->where('status', true)->count(),
            ],
            'canManage' => $manageableCodes->isNotEmpty(),
            'manageableCodes' => $manageableCodes,
            'runnableCodes' => $runnableCodes,
            'procurementSync' => $canSeeProcurementSync ? [
                'canRun' => $canRunProcurementSync,
                'steps' => $procurementCodes->values()->map(function (string $code, int $index) use ($procurementIntegrations): array {
                    /** @var ApiIntegration|null $integration */
                    $integration = $procurementIntegrations->get($code);

                    return [
                        'step' => $index + 1,
                        'code' => $code,
                        'name' => $code === 'mrmcon' ? 'Consumption API' : ucfirst($code).' API',
                        'configured' => $integration !== null && filled($integration->endpoint),
                        'active' => (bool) $integration?->status,
                        'lastRunStatus' => $integration?->last_run_status,
                        'lastRunAt' => $integration?->last_run_at?->toIso8601String(),
                    ];
                }),
            ] : null,
            'credentialSourceCode' => (string) config('services.api_integrations.environment_code', 'production'),
            'modules' => ModulePackage::query()
                ->where('status', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->authorizeCode($request, (string) $data['code']);
        ApiIntegration::create($data);

        return back()->with('success', 'API integration created.');
    }

    public function update(Request $request, ApiIntegration $apiIntegration): RedirectResponse
    {
        $this->authorizeCode($request, (string) $apiIntegration->code);
        $data = $this->validated($request, $apiIntegration);

        // Eloquent compares cast values while determining dirty attributes. If
        // the existing value was encrypted with an obsolete APP_KEY, that
        // comparison attempts to decrypt it again and prevents replacement.
        if ($apiIntegration->credentialState()['unreadable']) {
            $apiIntegration->setRawAttributes([
                ...$apiIntegration->getAttributes(),
                'credentials' => null,
            ]);
            $apiIntegration->syncOriginalAttribute('credentials');
        }

        $apiIntegration->update($data);

        return back()->with('success', 'API integration updated.');
    }

    public function toggleStatus(Request $request, ApiIntegration $apiIntegration): RedirectResponse
    {
        $this->authorizeCode($request, (string) $apiIntegration->code);
        $apiIntegration->update(['status' => $request->boolean('active')]);

        return back()->with('success', 'API status updated.');
    }

    public function destroy(Request $request, ApiIntegration $apiIntegration): RedirectResponse
    {
        $this->authorizeCode($request, (string) $apiIntegration->code);
        $apiIntegration->delete();

        return back()->with('success', 'API integration deleted.');
    }

    public function destroyLog(Request $request, ApiIntegration $apiIntegration, ApiIntegrationLog $log): RedirectResponse
    {
        $this->authorizeCode($request, (string) $apiIntegration->code);
        abort_unless((int) $log->api_integration_id === (int) $apiIntegration->id, 404);
        $log->delete();

        return back()->with('success', 'API run log permanently deleted.');
    }

    public function destroyLogs(Request $request, ApiIntegration $apiIntegration): RedirectResponse
    {
        $this->authorizeCode($request, (string) $apiIntegration->code);
        $deleted = $apiIntegration->logs()->delete();

        return back()->with('success', "{$deleted} API run log(s) permanently deleted.");
    }

    public function run(Request $request, ApiIntegration $apiIntegration): RedirectResponse
    {
        $this->authorizeRunCode($request, (string) $apiIntegration->code);
        $this->reconcileStaleRuns();
        $apiIntegration->refresh();
        if (in_array($apiIntegration->last_run_status, ['queued', 'running'], true)) {
            return back()->withErrors(['run' => 'This API import is already running. Wait for it to finish before starting another run.']);
        }
        if (! $apiIntegration->status) {
            return back()->withErrors(['run' => 'Activate the API before running it.']);
        }
        if (! filled($apiIntegration->endpoint)) {
            return back()->withErrors(['run' => 'Endpoint must be configured.']);
        }

        if (strtolower($apiIntegration->code) === 'security') {
            $started = microtime(true);
            $runLog = $apiIntegration->logs()->create([
                'run_id' => (string) Str::uuid(),
                'status' => 'running',
                'attempt' => 1,
                'message' => 'Security audit started.',
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);

            try {
                $scan = app(ProjectSecurityAuditService::class)->run();
                $status = $scan->status === 'completed' ? 'success' : 'warning';
                $runLog->update([
                    'status' => $status,
                    'http_status' => 200,
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                    'records_received' => $scan->total_findings,
                    'message' => "OWASP-aligned audit completed with score {$scan->score}/100 and {$scan->total_findings} finding(s).",
                ]);
                $apiIntegration->update([
                    'last_run_status' => $status,
                    'last_run_at' => $scan->completed_at ?? now(),
                ]);
            } catch (\Throwable $exception) {
                $runLog->update([
                    'status' => 'failed',
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                    'message' => $exception->getMessage(),
                ]);
                $apiIntegration->update(['last_run_status' => 'failed', 'last_run_at' => now()]);
                throw $exception;
            }

            return back()->with('success', "Security audit completed: {$scan->score}/100.");
        }

        if (! $this->hasBackgroundImporter($apiIntegration)) {
            return back()->withErrors(['run' => 'This entry is an environment connection profile. Configure a destination table or a dedicated importer before running it.']);
        }

        $chunkSize = max(1, min(100, (int) ($apiIntegration->chunk_size ?: 100)));
        $chunkOffset = max(0, (int) ($apiIntegration->chunk_offset ?: 0));
        $importMode = in_array($apiIntegration->import_mode, ['direct', 'chunk_all', 'chunk_one'], true)
            ? $apiIntegration->import_mode
            : 'direct';

        $runLog = $apiIntegration->logs()->create([
            'run_id' => (string) Str::uuid(),
            'status' => 'running',
            'attempt' => 1,
            'message' => $importMode === 'direct'
                ? 'Full API import queued in one request.'
                : "Chunk import queued: {$chunkSize} record(s), starting at offset {$chunkOffset}.",
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);
        try {
            if (strtolower((string) $apiIntegration->code) === 'customer_sales_summary') {
                ImportCustomerSalesSummaries::dispatch($apiIntegration->id, auth()->id(), $runLog->id);
            } elseif (strtolower((string) $apiIntegration->code) === 'customer_collections') {
                ImportCustomerCollections::dispatch($apiIntegration->id, auth()->id(), $runLog->id);
            } elseif ($importMode === 'chunk_all') {
                ImportApiInChunks::dispatch($apiIntegration->id, auth()->id(), $runLog->id, $chunkSize, $chunkOffset);
            } else {
                ImportMaterialsFromApi::dispatch(
                    $apiIntegration->id,
                    auth()->id(),
                    null,
                    $runLog->id,
                    $importMode === 'chunk_one' ? $chunkSize : null,
                    $importMode === 'chunk_one' ? $chunkOffset : null,
                );
            }
            $apiIntegration->update(['last_run_status' => 'running', 'last_run_at' => now()]);
        } catch (\Throwable $exception) {
            $runLog->update(['status' => 'failed', 'message' => $exception->getMessage()]);
            $apiIntegration->update(['last_run_status' => 'failed', 'last_run_at' => now()]);
            throw $exception;
        }

        if ($importMode === 'chunk_all') {
            return back()->with('success', "{$apiIntegration->name} import started at offset {$chunkOffset} in chunks of {$chunkSize}. It will stop automatically after the final API page.");
        }
        if ($importMode === 'chunk_one') {
            return back()->with('success', "{$apiIntegration->name} records {$chunkOffset} through ".($chunkOffset + $chunkSize - 1).' were queued for import.');
        }

        return back()->with('success', ucfirst($apiIntegration->code).' API import queued. It is running in the background.');
    }

    private function reconcileStaleRuns(): void
    {
        $queueTable = (string) config('queue.connections.database.table', 'tbl_jobs');

        if (DB::table($queueTable)->where('queue', 'api-imports')->exists()) {
            return;
        }

        ApiIntegrationLog::query()
            ->whereIn('status', ['queued', 'running'])
            ->where('created_at', '<', now()->subMinutes(30))
            ->update([
                'status' => 'failed',
                'message' => 'Run stopped before completion; no queue job remains.',
            ]);

        $staleIntegrationIds = ApiIntegration::query()
            ->whereIn('last_run_status', ['queued', 'running'])
            ->whereNotNull('last_run_at')
            ->where('last_run_at', '<', now()->subMinutes(30))
            ->pluck('id');

        if ($staleIntegrationIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($staleIntegrationIds): void {
            ApiIntegrationLog::query()
                ->whereIn('api_integration_id', $staleIntegrationIds)
                ->whereIn('status', ['queued', 'running'])
                ->update([
                    'status' => 'failed',
                    'message' => 'Run stopped before completion; no progress was recorded for 30 minutes.',
                ]);

            ApiIntegration::query()
                ->whereIn('id', $staleIntegrationIds)
                ->whereIn('last_run_status', ['queued', 'running'])
                ->update(['last_run_status' => 'failed']);
        });
    }

    private function hasBackgroundImporter(ApiIntegration $integration): bool
    {
        if (in_array(strtolower((string) $integration->code), ['material', 'stock', 'mrmcon', 'vendor', 'employee', 'cust', 'customer'], true)) {
            return true;
        }

        return collect($integration->target_table)->filter()->isNotEmpty();
    }

    public function runProcurement(Request $request): RedirectResponse
    {
        $codes = ['material', 'stock', 'vendor', 'mrmcon'];
        foreach ($codes as $code) {
            $this->authorizeRunCode($request, $code);
        }

        $integrations = ApiIntegration::query()
            ->whereIn('code', $codes)
            ->get()
            ->keyBy(fn (ApiIntegration $api): string => strtolower($api->code));

        $missing = collect($codes)->reject(fn (string $code): bool => $integrations->has($code));
        if ($missing->isNotEmpty()) {
            return back()->withErrors(['run' => 'Configure the following APIs before starting procurement sync: '.$missing->map(fn (string $code): string => $code === 'mrmcon' ? 'Consumption' : ucfirst($code))->join(', ').'.']);
        }

        $unavailable = collect($codes)->filter(function (string $code) use ($integrations): bool {
            /** @var ApiIntegration $integration */
            $integration = $integrations->get($code);

            return ! $integration->status || ! filled($integration->endpoint);
        });
        if ($unavailable->isNotEmpty()) {
            return back()->withErrors(['run' => 'Activate and configure an endpoint for: '.$unavailable->map(fn (string $code): string => $code === 'mrmcon' ? 'Consumption' : ucfirst($code))->join(', ').'.']);
        }

        DB::transaction(function () use ($codes, $integrations, $request): void {
            foreach ($codes as $code) {
                /** @var ApiIntegration $integration */
                $integration = $integrations->get($code);
                $runLog = $integration->logs()->create([
                    'run_id' => (string) Str::uuid(),
                    'status' => 'queued',
                    'attempt' => 1,
                    'message' => 'Procurement parallel sync queued.',
                    'created_by' => $request->user()?->id,
                    'created_at' => now(),
                ]);

                ImportMaterialsFromApi::dispatch($integration->id, $request->user()?->id, null, $runLog->id);
                $integration->update(['last_run_status' => 'queued', 'last_run_at' => now()]);
            }
        });

        return back()->with('success', 'Material, Stock, Vendor, and Consumption APIs were queued together. Available queue workers will process them in parallel.');
    }

    private function validated(Request $request, ?ApiIntegration $api = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('tbl_api_integrations', 'name')->ignore($api?->id)], 'code' => ['required', 'alpha_dash', 'max:80', Rule::unique('tbl_api_integrations', 'code')->ignore($api?->id)],
            'module_id' => ['required', 'integer', Rule::exists('tbl_modules', 'id')->where('status', true)],
            'description' => ['nullable', 'string', 'max:255'], 'api_type' => ['required', Rule::in(['sap', 'external'])], 'endpoint' => ['required', 'string', 'max:500'],
            'credential_username' => ['nullable', 'string', 'max:255'], 'credential_password' => ['nullable', 'string', 'max:1000'],
            'subscription_name' => ['nullable', 'string', 'max:255'], 'api_token' => ['nullable', 'string', 'max:2000'],
            'target_table' => ['nullable', 'string', 'max:4000'],
            'import_mode' => ['required', Rule::in(['direct', 'chunk_all', 'chunk_one'])],
            'chunk_size' => ['required', 'integer', 'between:1,100'],
            'chunk_offset' => ['required', 'integer', 'min:0'],
            'retries' => ['required', 'integer', 'between:1,10'], 'retry_delay_seconds' => ['required', 'integer', 'between:0,300'], 'timeout_seconds' => ['required', 'integer', 'between:1,300'], 'status' => ['required', 'boolean'],
        ]);

        $targets = collect(explode(',', (string) ($validated['target_table'] ?? '')))
            ->map(fn (string $table): string => trim($table))
            ->filter()
            ->unique()
            ->values();
        if ($targets->contains(fn (string $table): bool => preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1)) {
            throw ValidationException::withMessages(['target_table' => 'Table names may contain only letters, numbers, and underscores.']);
        }

        $validated['target_table'] = $targets->all();
        $existingCredentials = $api?->credentialState()['credentials'] ?? [];

        if ($validated['api_type'] === 'external') {
            if (filter_var($validated['endpoint'], FILTER_VALIDATE_URL) === false) {
                throw ValidationException::withMessages(['endpoint' => 'External API URL must be a complete valid URL.']);
            }
            $validated['credentials'] = [
                'subscription_name' => filled($validated['subscription_name'] ?? null) ? $validated['subscription_name'] : ($existingCredentials['subscription_name'] ?? ''),
                'token' => filled($validated['api_token'] ?? null) ? $validated['api_token'] : ($existingCredentials['token'] ?? ''),
            ];
            if ($validated['credentials']['subscription_name'] === '' || $validated['credentials']['token'] === '') {
                throw ValidationException::withMessages(['api_token' => 'External API subscription name and token are required.']);
            }
        } else {
            $credentialSourceCode = (string) config('services.api_integrations.environment_code', 'production');
            $credentialHostCodes = ['production', 'quality'];
            if (in_array($validated['code'], $credentialHostCodes, true)) {
                $validated['credentials'] = [
                    'username' => filled($validated['credential_username'] ?? null) ? $validated['credential_username'] : ($existingCredentials['username'] ?? ''),
                    'password' => filled($validated['credential_password'] ?? null) ? $validated['credential_password'] : ($existingCredentials['password'] ?? ''),
                ];
                if ($validated['credentials']['username'] === '' || $validated['credentials']['password'] === '') {
                    throw ValidationException::withMessages(['credential_password' => 'SAP username and password are required on Production and Quality environment APIs.']);
                }
            } else {
                $validated['credentials'] = collect($existingCredentials)->except(['username', 'password'])->all();
            }
        }

        unset($validated['credential_username'], $validated['credential_password'], $validated['subscription_name'], $validated['api_token']);

        return $validated;
    }

    private function authorizeCode(Request $request, string $code): void
    {
        $code = strtolower($code);
        if ($request->user()?->hasRole('Owner')) {
            return;
        }

        if (in_array($code, ['cust', 'customer', 'customer_sales_summary', 'customer_collections'], true)) {
            abort_unless($request->user()?->can('users.manage') || $request->user()?->can('sales_distribution.customers.manage'), 403);

            return;
        }

        if ($code === 'pending_sales') {
            abort_unless($request->user()?->can('users.manage') || $request->user()?->can('sales_distribution.pending_sales.manage'), 403);

            return;
        }

        if ($code === 'sales_data') {
            abort_unless($request->user()?->can('users.manage') || $request->user()?->can('sales_distribution.daily_sales.manage'), 403);

            return;
        }

        if ($code === 'employee') {
            abort_unless($request->user()?->can('users.manage'), 403);

            return;
        }

        if ($code === 'security') {
            abort_unless($request->user()?->hasRole('Owner'), 403);

            return;
        }

        abort_unless(in_array($code, ['material', 'production', 'quality', 'stock', 'mrmcon', 'vendor'], true), 403);
        abort_unless($request->user()?->can('procurement.api.'.$this->permissionCode($code).'.manage'), 403);
    }

    private function authorizeRunCode(Request $request, string $code): void
    {
        $code = strtolower($code);
        if ($request->user()?->hasRole('Owner')) {
            return;
        }

        if ($this->canRunCode($request, $code)) {
            return;
        }

        if (in_array($code, ['employee', 'cust', 'customer', 'pending_sales', 'sales_data', 'customer_sales_summary', 'customer_collections'], true)) {
            abort(403);

            return;
        }

        abort_unless(in_array($code, ['material', 'production', 'quality', 'stock', 'mrmcon', 'vendor'], true), 403);
        abort_unless($request->user()?->can('procurement.api.'.$this->permissionCode($code).'.run'), 403);
    }

    private function canManageCode(Request $request, string $code): bool
    {
        $code = strtolower($code);
        $user = $request->user();

        return match ($code) {
            'employee' => $user?->can('users.manage') ?? false,
            'cust', 'customer' => ($user?->can('users.manage') ?? false) || ($user?->can('sales_distribution.customers.manage') ?? false),
            'customer_sales_summary' => $user?->can('sales_distribution.customer_sales_summary.manage') ?? false,
            'customer_collections' => $user?->can('sales_distribution.customer_sales_summary.manage') ?? false,
            'pending_sales' => ($user?->can('users.manage') ?? false) || ($user?->can('sales_distribution.pending_sales.manage') ?? false),
            'sales_data' => ($user?->can('users.manage') ?? false) || ($user?->can('sales_distribution.daily_sales.manage') ?? false),
            default => $user?->can('procurement.api.'.$this->permissionCode($code).'.manage') ?? false,
        };
    }

    private function canRunCode(Request $request, string $code): bool
    {
        $code = strtolower($code);
        if (in_array($code, ['cust', 'customer', 'customer_sales_summary', 'customer_collections', 'pending_sales', 'sales_data'], true)) {
            return $this->canManageCode($request, $code);
        }

        return $code === 'employee'
            ? ($request->user()?->can('users.manage') ?? false)
            : ($request->user()?->can('procurement.api.'.$this->permissionCode($code).'.run') ?? false);
    }

    private function permissionCode(string $integrationCode): string
    {
        return strtolower($integrationCode) === 'mrmcon' ? 'consumption' : strtolower($integrationCode);
    }
}
