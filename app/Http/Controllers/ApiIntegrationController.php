<?php

namespace App\Http\Controllers;

use App\Jobs\SyncApiIntegration;
use App\Models\ApiIntegration;
use App\Models\ApiSyncRun;
use App\Services\ProcurementStockSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class ApiIntegrationController extends Controller
{
    public function index()
    {
        Gate::authorize('api-integrations.view');

        return view('api-integrations.index', [
            'integrations' => ApiIntegration::withCount('runs')->with(['runs' => fn ($query) => $query->latest('started_at')->limit(1)])->orderBy('name')->get(),
            'currentEnvironmentCode' => ApiIntegration::currentEnvironmentCode(),
            'currentBaseUrl' => ApiIntegration::currentEnvironmentProfile()?->base_url,
        ]);
    }

    public function create()
    {
        Gate::authorize('api-integrations.create');
        return view('api-integrations.form', ['integration' => new ApiIntegration]);
    }

    public function store(Request $request)
    {
        Gate::authorize('api-integrations.create');
        ApiIntegration::create($this->validated($request));
        return redirect()->route('admin.api-integrations.index')->with('success', 'API integration created.');
    }

    public function edit(ApiIntegration $apiIntegration)
    {
        Gate::authorize('api-integrations.edit');
        return view('api-integrations.form', ['integration' => $apiIntegration]);
    }

    public function update(Request $request, ApiIntegration $apiIntegration)
    {
        Gate::authorize('api-integrations.edit');
        $apiIntegration->update($this->validated($request, $apiIntegration));
        return redirect()->route('admin.api-integrations.index')->with('success', 'API integration updated.');
    }

    public function destroy(ApiIntegration $apiIntegration)
    {
        Gate::authorize('api-integrations.delete');
        $apiIntegration->delete();
        return back()->with('success', 'API integration deleted.');
    }

    public function runs(ApiIntegration $apiIntegration)
    {
        Gate::authorize('api-integrations.view');
        ApiSyncRun::purgeExpired();
        return view('api-integrations.runs', [
            'integration' => $apiIntegration,
            'runs' => $apiIntegration->runs()->with('attempts')->latest('started_at')->get(),
        ]);
    }

    public function run(ApiIntegration $apiIntegration)
    {
        Gate::authorize('api-integrations.edit');
        abort_unless($apiIntegration->status, 422, 'Activate this API before running it.');
        abort_if(
            $apiIntegration->runs()->whereIn('status', ['queued', 'running'])
                ->where('started_at', '>=', now()->subHour())->exists(),
            409,
            'This API already has a synchronization in progress.'
        );
        $run = ApiSyncRun::create(['api_integration_id' => $apiIntegration->id, 'run_uuid' => (string) Str::uuid(), 'status' => 'queued', 'started_at' => now(), 'triggered_by' => auth()->id()]);
        SyncApiIntegration::dispatch($run->id)->onQueue('api-sync');

        return redirect()->route('admin.api-integrations.runs', $apiIntegration)
            ->with('success', 'API synchronization queued. You may continue working while it runs in the background.');
    }

    public function procurementStockSync(ApiIntegration $apiIntegration, ProcurementStockSyncService $stockSync)
    {
        Gate::authorize('api-integrations.edit');
        abort_unless($apiIntegration->code === 'stock', 404);

        $stockSync->sync();
        return back()->with('success', 'Procurement stock synchronization queued. You may continue working while it runs in the background.');
    }

    private function validated(Request $request, ?ApiIntegration $integration = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('api_integrations', 'code')->ignore($integration?->id)],
            'name' => ['required', 'string', 'max:100'],
            'endpoint_path' => ['nullable', 'string', 'max:500'],
            'tbl_name' => ['nullable', 'regex:/^[A-Za-z0-9_]+$/', 'max:64'],
            'http_method' => ['required', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'auth_type' => ['required', Rule::in(['none', 'basic', 'bearer', 'api_key'])],
            'credential_env_key' => ['nullable', 'required_if:auth_type,bearer,api_key', 'regex:/^[A-Z][A-Z0-9_]*$/', 'max:100'],
            'username_env_key' => ['nullable', 'required_if:auth_type,basic', 'regex:/^[A-Z][A-Z0-9_]*$/', 'max:100'],
            'password_env_key' => ['nullable', 'required_if:auth_type,basic', 'regex:/^[A-Z][A-Z0-9_]*$/', 'max:100'],
            'timeout_seconds' => ['required', 'integer', 'between:1,300'],
            'retry_count' => ['required', 'integer', 'between:0,10'],
            'retry_delay_seconds' => ['required', 'integer', 'between:0,3600'],
            'verify_ssl' => ['required', 'boolean'],
            'status' => ['required', 'boolean'],
        ]);
        $data['endpoint_path'] = filled($data['endpoint_path'] ?? null) ? '/'.ltrim($data['endpoint_path'], '/') : null;
        if (filled($data['tbl_name'] ?? null) && ! DB::table('information_schema.tables')
            ->where('table_schema', config('database.connections.mysql.database'))
            ->where('table_name', $data['tbl_name'])
            ->exists()) {
            throw ValidationException::withMessages(['tbl_name' => 'This table does not exist in the application database.']);
        }
        return $data;
    }
}
