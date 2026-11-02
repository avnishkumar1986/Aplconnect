<?php

namespace Modules\SalesDistribution\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\SalesDistribution\Models\PendingSale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PendingSaleController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $perPage = (int) config('datatable.per_page', 50);
        $matchingCustomerNumbers = $search === '' ? [] : DB::table('tbl_users')->where('user_type','USR4')->where('name','like',"%{$search}%")->limit(1000)->pluck('unique_id')->all();
        $sales = PendingSale::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('customer_number', 'like', "%{$search}%")
                ->when($matchingCustomerNumbers !== [], fn($query) => $query->orWhereIn('customer_number',$matchingCustomerNumbers))
                ->orWhere('sales_document_number', 'like', "%{$search}%")
                ->orWhere('material_number', 'like', "%{$search}%")
                ->orWhere('material_group', 'like', "%{$search}%")
                ->orWhere('plant_code', 'like', "%{$search}%")))
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
        $names=DB::table('tbl_users')->where('user_type','USR4')->whereIn('unique_id',$sales->getCollection()->pluck('customer_number'))->pluck('name','unique_id');
        $sales->getCollection()->transform(function(PendingSale $sale) use($names){$sale->setAttribute('customer_name',$names->get($sale->customer_number));return $sale;});
        $stats=Cache::remember('sales-distribution.pending-sales.stats',now()->addMinutes(10),fn()=>['total'=>PendingSale::count(),'active'=>PendingSale::where('status',true)->count(),'amount'=>(float)PendingSale::sum('amount'),'openAmount'=>(float)PendingSale::sum('open_delivery_amount')]);

        return Inertia::render('modules/salesdistribution/pending-sales/index', [
            'pendingSales' => $sales,
            'filters' => ['search' => $search, 'per_page' => $perPage],
            'stats' => $stats,
            'canManage' => $request->user()?->can('sales_distribution.pending_sales.manage') ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        PendingSale::create($this->validated($request));
        $this->forgetStats();

        return back()->with('success', 'Pending sale created successfully.');
    }

    public function update(Request $request, PendingSale $pendingSale): RedirectResponse
    {
        $pendingSale->update($this->validated($request, $pendingSale));
        $this->forgetStats();

        return back()->with('success', 'Pending sale updated successfully.');
    }

    public function toggleStatus(Request $request, PendingSale $pendingSale): RedirectResponse
    {
        $pendingSale->update(['status' => $request->validate(['active' => ['required', 'boolean']])['active']]);
        $this->forgetStats();

        return back()->with('success', 'Pending sale status updated.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', Rule::exists('tbl_pending_sales', 'id')],
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])],
        ]);
        $query = PendingSale::whereIn('id', $data['ids']);
        $data['action'] === 'delete'
            ? $query->delete()
            : $query->update(['status' => $data['action'] === 'activate', 'updated_at' => now()]);
        $this->forgetStats();

        return back()->with('success', 'Pending order bulk action completed.');
    }

    public function destroy(PendingSale $pendingSale): RedirectResponse
    {
        $pendingSale->delete();
        $this->forgetStats();

        return back()->with('success', 'Pending sale deleted successfully.');
    }

    private function validated(Request $request, ?PendingSale $pendingSale = null): array
    {
        return $request->validate([
            'customer_number' => ['required', 'string', 'max:50'],
            'plant_code' => ['required', 'string', 'max:20'],
            'sales_document_number' => ['required', 'string', 'max:50'],
            'document_date' => ['nullable', 'date'],
            'external_material_group' => ['nullable', 'string', 'max:100'],
            'material_group' => ['nullable', 'string', 'max:100'],
            'material_number' => ['required', 'string', 'max:100', Rule::unique('tbl_pending_sales', 'material_number')
                ->where(fn ($query) => $query
                    ->where('customer_number', $request->input('customer_number'))
                    ->where('plant_code', $request->input('plant_code'))
                    ->where('sales_document_number', $request->input('sales_document_number')))
                ->ignore($pendingSale?->id)],
            'order_quantity' => ['required', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'open_delivery_amount' => ['required', 'numeric', 'min:0'],
            'delivered_quantity' => ['required', 'numeric', 'min:0'],
            'material_text_1' => ['nullable', 'string', 'max:255'],
            'material_text_2' => ['nullable', 'string', 'max:255'],
            'material_text_3' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
        ]);
    }
    private function forgetStats(): void { Cache::forget('sales-distribution.pending-sales.stats'); }
}
