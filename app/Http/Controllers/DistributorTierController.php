<?php

namespace App\Http\Controllers;

use App\Models\DistributorTier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DistributorTierController extends Controller
{
    private function definition(): array
    {
        return ['title' => 'Distributor Tiers', 'fields' => [
            'code' => ['label' => 'Tier code', 'rules' => 'required|max:30'],
            'name' => ['label' => 'Tier name', 'rules' => 'required|max:100'],
            'minimum_annual_sales' => ['label' => 'Minimum annual sales (INR)', 'type' => 'number', 'rules' => 'nullable|numeric|min:0'],
            'maximum_discount_percent' => ['label' => 'Maximum discount (%)', 'type' => 'number', 'rules' => 'nullable|numeric|min:0|max:100'],
            'description' => ['type' => 'textarea', 'rules' => 'nullable|max:255'],
            'status' => ['type' => 'select', 'options' => ['1' => 'Active', '0' => 'Inactive'], 'rules' => 'required'],
        ]];
    }

    public function index(Request $request)
    {
        Gate::authorize('distributor-tiers.view');
        $query = DistributorTier::query();
        if ($search = trim((string) $request->q)) {
            $query->where(fn ($q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
        }
        return view('distributor-tiers.index', [
            'resource' => 'distributor-tiers', 'definition' => $this->definition(),
            'records' => $query->orderBy('minimum_annual_sales')->paginate(config('app.table_page_length'))->withQueryString(),
            'sortableFields' => ['id', 'code', 'name', 'minimum_annual_sales', 'maximum_discount_percent', 'status'],
            'dedicatedRoutes' => true, 'formsInModal' => true,
        ]);
    }

    public function create()
    {
        Gate::authorize('distributor-tiers.create');
        return $this->form(new DistributorTier);
    }

    public function store(Request $request)
    {
        Gate::authorize('distributor-tiers.create');
        $data = $this->validated($request);
        $data['created_by'] = auth()->id();
        DistributorTier::create($data);
        return redirect()->route('admin.distributor-tiers.index')->with('success', 'Distributor tier created.');
    }

    public function edit(DistributorTier $distributorTier)
    {
        Gate::authorize('distributor-tiers.edit');
        return $this->form($distributorTier);
    }

    public function update(Request $request, DistributorTier $distributorTier)
    {
        Gate::authorize('distributor-tiers.edit');
        $data = $this->validated($request, $distributorTier);
        $data['updated_by'] = auth()->id();
        $distributorTier->update($data);
        return redirect()->route('admin.distributor-tiers.index')->with('success', 'Distributor tier updated.');
    }

    public function destroy(DistributorTier $distributorTier)
    {
        Gate::authorize('distributor-tiers.delete');
        abort_if(\Schema::hasColumn('tbl_distributor_profiles', 'distributor_tier_code') && \DB::table('tbl_distributor_profiles')->where('distributor_tier_code', $distributorTier->code)->exists(), 422, 'This distributor tier is currently assigned.');
        $distributorTier->delete();
        return back()->with('success', 'Distributor tier deleted.');
    }

    private function form(DistributorTier $record)
    {
        return view('distributor-tiers.form', ['resource' => 'distributor-tiers', 'record' => $record, 'options' => [], 'definition' => $this->definition(), 'dedicatedRoutes' => true]);
    }

    private function validated(Request $request, ?DistributorTier $record = null): array
    {
        $request->merge([
            'code' => strtolower(trim((string) $request->input('code'))),
            'status' => $request->input('status', $record?->status ?? '1'),
        ]);
        return $request->validate([
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('tbl_distributor_tiers', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:100', Rule::unique('tbl_distributor_tiers', 'name')->ignore($record?->id)],
            'minimum_annual_sales' => ['nullable', 'numeric', 'min:0'],
            'maximum_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
        ]);
    }
}
