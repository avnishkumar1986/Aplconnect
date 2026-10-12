<?php

namespace App\Http\Controllers;

use App\Models\DistributorType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DistributorTypeController extends Controller
{
    private function definition(): array
    {
        return ['title' => 'Distributor Types', 'fields' => [
            'code' => ['label' => 'Type code', 'rules' => 'required|max:30'],
            'name' => ['label' => 'Type name', 'rules' => 'required|max:100'],
            'description' => ['type' => 'textarea', 'rules' => 'nullable|max:255'],
            'status' => ['type' => 'select', 'options' => ['1' => 'Active', '0' => 'Inactive'], 'rules' => 'required'],
        ]];
    }

    public function index(Request $request)
    {
        Gate::authorize('distributor-types.view');
        $query = DistributorType::query();
        if ($search = trim((string) $request->q)) {
            $query->where(fn ($q) => $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
        }

        return view('distributor-types.index', [
            'resource' => 'distributor-types', 'definition' => $this->definition(),
            'records' => $query->orderBy('name')->paginate(config('app.table_page_length'))->withQueryString(),
            'sortableFields' => ['id', 'code', 'name', 'description', 'status'],
            'dedicatedRoutes' => true, 'formsInModal' => true,
        ]);
    }

    public function create()
    {
        Gate::authorize('distributor-types.create');
        return $this->form(new DistributorType);
    }

    public function store(Request $request)
    {
        Gate::authorize('distributor-types.create');
        $data = $this->validated($request);
        $data['created_by'] = auth()->id();
        DistributorType::create($data);
        return redirect()->route('admin.distributor-types.index')->with('success', 'Distributor type created.');
    }

    public function edit(DistributorType $distributorType)
    {
        Gate::authorize('distributor-types.edit');
        return $this->form($distributorType);
    }

    public function update(Request $request, DistributorType $distributorType)
    {
        Gate::authorize('distributor-types.edit');
        $data = $this->validated($request, $distributorType);
        $data['updated_by'] = auth()->id();
        $distributorType->update($data);
        return redirect()->route('admin.distributor-types.index')->with('success', 'Distributor type updated.');
    }

    public function destroy(DistributorType $distributorType)
    {
        Gate::authorize('distributor-types.delete');
        abort_if(\DB::table('tbl_distributor_profiles')->where('distributor_category', $distributorType->code)->exists(), 422, 'This distributor type is currently assigned.');
        $distributorType->delete();
        return back()->with('success', 'Distributor type deleted.');
    }

    private function form(DistributorType $record)
    {
        return view('distributor-types.form', ['resource' => 'distributor-types', 'record' => $record, 'options' => [], 'definition' => $this->definition(), 'dedicatedRoutes' => true]);
    }

    private function validated(Request $request, ?DistributorType $record = null): array
    {
        $request->merge([
            'code' => strtolower(trim((string) $request->input('code'))),
            'status' => $request->input('status', $record?->status ?? '1'),
        ]);
        return $request->validate([
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('tbl_distributor_types', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:100', Rule::unique('tbl_distributor_types', 'name')->ignore($record?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
        ]);
    }
}
