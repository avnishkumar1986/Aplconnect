<?php

namespace Modules\SalesDistribution\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\SalesDistribution\Models\Sale;

class DailySaleController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $perPage = (int) config('datatable.per_page', 50);
        $sales = Sale::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('billing_document_number', 'like', "%{$search}%")
                ->orWhere('customer_number', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('material_number', 'like', "%{$search}%")
                ->orWhere('material_description', 'like', "%{$search}%")
                ->orWhere('plant_code', 'like', "%{$search}%")))
            ->orderByDesc('billing_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $stats = Cache::remember('sales-distribution.daily-sales.stats', now()->addMinutes(10), fn (): array => [
            'total' => Sale::count(),
            'active' => Sale::where('status', true)->count(),
            'netValue' => (float) Sale::sum('net_value'),
            'taxAmount' => (float) Sale::sum('tax_amount'),
        ]);
        return Inertia::render('modules/salesdistribution/daily-sales/index', [
            'dailySales' => $sales,
            'filters' => ['search' => $search, 'per_page' => $perPage],
            'stats' => $stats,
            'canManage' => $request->user()?->can('sales_distribution.daily_sales.manage') ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Sale::create($this->validated($request));
        $this->forgetStats();

        return back()->with('success', 'Daily sale created successfully.');
    }

    public function update(Request $request, Sale $sale): RedirectResponse
    {
        $sale->update($this->validated($request, $sale));
        $this->forgetStats();

        return back()->with('success', 'Daily sale updated successfully.');
    }

    public function toggleStatus(Request $request, Sale $sale): RedirectResponse
    {
        $sale->update(['status' => $request->validate(['active' => ['required', 'boolean']])['active']]);
        $this->forgetStats();

        return back()->with('success', 'Daily sale status updated.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', Rule::exists('tbl_dailysale', 'id')],
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])],
        ]);
        $query = Sale::whereIn('id', $data['ids']);
        $data['action'] === 'delete'
            ? $query->delete()
            : $query->update(['status' => $data['action'] === 'activate', 'updated_at' => now()]);
        $this->forgetStats();

        return back()->with('success', 'Daily sales bulk action completed.');
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        $sale->delete();
        $this->forgetStats();

        return back()->with('success', 'Daily sale deleted successfully.');
    }

    private function validated(Request $request, ?Sale $sale = null): array
    {
        return $request->validate([
            'billing_document_number' => ['required', 'string', 'max:50'],
            'billing_type' => ['nullable', 'string', 'max:20'],
            'billing_date' => ['nullable', 'date'],
            'record_creation_date' => ['nullable', 'date'],
            'company_code' => ['nullable', 'string', 'max:20'],
            'plant_code' => ['required', 'string', 'max:20'],
            'customer_number' => ['required', 'string', 'max:50'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'material_number' => ['required', 'string', 'max:100', Rule::unique('tbl_dailysale', 'material_number')
                ->where(fn ($query) => $query
                    ->where('billing_document_number', $request->input('billing_document_number'))
                    ->where('customer_number', $request->input('customer_number'))
                    ->where('plant_code', $request->input('plant_code')))
                ->ignore($sale?->id)],
            'material_description' => ['nullable', 'string', 'max:255'],
            'material_group' => ['nullable', 'string', 'max:100'],
            'material_group_description' => ['nullable', 'string', 'max:255'],
            'product_hierarchy' => ['nullable', 'string', 'max:100'],
            'product_hierarchy_description' => ['nullable', 'string', 'max:255'],
            'external_material_group' => ['nullable', 'string', 'max:100'],
            'external_material_group_description' => ['nullable', 'string', 'max:255'],
            'billed_quantity' => ['required', 'numeric'],
            'billed_quantity_kg' => ['required', 'numeric'],
            'billed_quantity_liters' => ['required', 'numeric'],
            'billed_quantity_meters' => ['required', 'numeric'],
            'sales_unit' => ['nullable', 'string', 'max:20'],
            'base_unit' => ['nullable', 'string', 'max:20'],
            'net_value' => ['required', 'numeric'],
            'tax_amount' => ['required', 'numeric'],
            'fiscal_year' => ['nullable', 'string', 'max:4'],
            'condition_value' => ['required', 'numeric'],
            'status' => ['required', 'boolean'],
        ]);
    }

    private function forgetStats(): void { Cache::forget('sales-distribution.daily-sales.stats'); }
}
