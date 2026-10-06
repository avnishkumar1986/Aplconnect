<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Designation;
use App\Models\DesignationLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrganizationStructureController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('users.view');

        $search = trim((string) $request->input('q'));
        $departments = Department::query()
            ->withCount('designations')
            ->when($search, fn ($query) => $query->where(fn ($nested) => $nested
                ->where('department_code', 'like', "%{$search}%")
                ->orWhere('department_name', 'like', "%{$search}%")))
            ->orderBy('department_name')->get();
        $levels = DesignationLevel::query()->with('reportsToLevel')->withCount('designations')
            ->orderBy('hierarchy_order')->get();
        $designations = Designation::query()->with(['department', 'level', 'parentDesignation'])
            ->when($search, fn ($query) => $query->where(fn ($nested) => $nested
                ->where('des_id', 'like', "%{$search}%")
                ->orWhere('designation_name', 'like', "%{$search}%")))
            ->orderBy('designation_name')->get();

        return view('organization-structure.index', compact('departments', 'levels', 'designations', 'search'));
    }

    public function store(Request $request, string $type)
    {
        Gate::authorize('users.create');
        $model = $this->model($type);
        $data = $this->validated($request, $type);
        if ($type !== 'levels') {
            $data['created_by'] = auth()->id();
        }
        $model::create($data);

        return back()->with('success', $this->label($type).' created successfully.');
    }

    public function update(Request $request, string $type, int $id)
    {
        Gate::authorize('users.edit');
        $record = $this->model($type)::findOrFail($id);
        $data = $this->validated($request, $type, $record);
        if ($type !== 'levels') {
            $data['updated_by'] = auth()->id();
        }
        $record->update($data);

        return back()->with('success', $this->label($type).' updated successfully.');
    }

    public function destroy(string $type, int $id)
    {
        Gate::authorize('users.delete');
        $record = $this->model($type)::findOrFail($id);
        if ($type === 'departments') {
            abort_if($record->designations()->exists(), 422, 'Remove or reassign this department’s designations first.');
        } elseif ($type === 'levels') {
            abort_if($record->designations()->exists(), 422, 'This level is assigned to one or more designations.');
        } else {
            abort_if($record->employees()->exists() || $record->childDesignations()->exists(), 422, 'This designation is assigned to employees or reporting designations.');
        }
        $record->delete();

        return back()->with('success', $this->label($type).' removed successfully.');
    }

    public function bulkAction(Request $request, string $type)
    {
        Gate::authorize('users.edit');
        $data = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $model = $this->model($type);
        $records = $model::query()->whereKey($data['ids'])->get();
        DB::transaction(function () use ($records, $type, $data): void {
            foreach ($records as $record) {
                if ($data['action'] !== 'delete') {
                    $record->update(['status' => $data['action'] === 'activate']);
                    continue;
                }
                if ($type === 'departments') {
                    abort_if($record->designations()->exists(), 422, 'One or more selected departments still have designations.');
                } elseif ($type === 'levels') {
                    abort_if($record->designations()->exists(), 422, 'One or more selected levels are in use.');
                } else {
                    abort_if($record->employees()->exists() || $record->childDesignations()->exists(), 422, 'One or more selected designations are in use.');
                }
                $record->delete();
            }
        });

        return back()->with('success', $records->count().' record(s) processed successfully.');
    }

    private function validated(Request $request, string $type, $record = null): array
    {
        $id = $record?->id;
        $rules = match ($type) {
            'departments' => [
                'department_code' => ['required', 'string', 'max:50', Rule::unique('tbl_departments')->ignore($id)],
                'department_name' => ['required', 'string', 'max:150'],
                'description' => ['nullable', 'string'],
                'status' => ['required', 'boolean'],
            ],
            'levels' => [
                'level_code' => ['required', 'string', 'max:10', Rule::unique('tbl_designation_levels')->ignore($id)],
                'level_name' => ['required', 'string', 'max:100'],
                'hierarchy_order' => ['required', 'integer', 'min:1', Rule::unique('tbl_designation_levels')->ignore($id)],
                'reports_to_level_id' => ['nullable', 'exists:tbl_designation_levels,id', Rule::notIn(array_filter([$id]))],
                'status' => ['required', 'boolean'],
            ],
            'designations' => [
                'des_id' => ['required', 'string', 'max:50', Rule::unique('tbl_designations')->ignore($id)],
                'designation_name' => ['required', 'string', 'max:150', Rule::unique('tbl_designations')->ignore($id)],
                'department_id' => ['nullable', 'exists:tbl_departments,id'],
                'designation_level_id' => ['nullable', 'exists:tbl_designation_levels,id'],
                'reports_to_designation_id' => ['nullable', 'exists:tbl_designations,id', Rule::notIn(array_filter([$id]))],
                'sitting_place' => ['nullable', 'string', 'max:255'],
                'status' => ['required', 'boolean'],
            ],
            default => abort(404),
        };

        return $request->validate($rules);
    }

    private function model(string $type): string
    {
        return match ($type) {
            'departments' => Department::class,
            'designations' => Designation::class,
            'levels' => DesignationLevel::class,
            default => abort(404),
        };
    }

    private function label(string $type): string
    {
        return match ($type) {
            'departments' => 'Department',
            'designations' => 'Designation',
            'levels' => 'Designation level',
            default => abort(404),
        };
    }
}
