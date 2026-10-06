<?php

namespace App\Http\Controllers;

use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class UserTypeController extends Controller
{
    private function definition(): array
    {
        return ['title' => 'User Types', 'fields' => [
            'type_code' => ['label' => 'Type code', 'form_hidden' => true],
            'type_name' => ['label' => 'Type name', 'rules' => 'required|max:100'],
            'description' => ['type' => 'textarea', 'rules' => 'nullable|max:255'],
            'status' => ['type' => 'select', 'options' => ['1' => 'Active', '0' => 'Inactive'], 'form_hidden' => true],
        ]];
    }

    public function index(Request $request)
    {
        Gate::authorize('user-types.view');
        $query = UserType::query();
        if ($search = trim((string) $request->q)) {
            $query->where(fn ($q) => $q->where('type_code', 'like', "%{$search}%")->orWhere('type_name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
        }
        $sortable = ['id', 'type_code', 'type_name', 'description', 'status'];
        $sort = in_array($request->sort, $sortable, true) ? $request->sort : 'id';
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';

        return view('user-types.index', ['resource' => 'user-types', 'definition' => $this->definition(), 'records' => $query->orderBy($sort, $direction)->paginate(config('app.table_page_length'))->withQueryString(), 'sortableFields' => $sortable, 'dedicatedRoutes' => true, 'formsInModal' => true]);
    }

    public function create()
    {
        Gate::authorize('user-types.create');

        return $this->form(new UserType);
    }

    public function store(Request $request)
    {
        Gate::authorize('user-types.create');
        $data = $this->validated($request);
        $data['type_code'] = $this->generateTypeCode($data['type_name']);
        $data['status'] = '1';
        $data['created_by'] = auth()->id();
        UserType::create($data);

        return redirect()->route('admin.user-types.index')->with('success', 'User type created.');
    }

    public function edit(UserType $userType)
    {
        Gate::authorize('user-types.edit');

        return $this->form($userType);
    }

    public function update(Request $request, UserType $userType)
    {
        Gate::authorize('user-types.edit');
        $data = $this->validated($request, $userType);
        $data['updated_by'] = auth()->id();
        $userType->update($data);

        return redirect()->route('admin.user-types.index')->with('success', 'User type updated.');
    }

    public function destroy(UserType $userType)
    {
        Gate::authorize('user-types.delete');
        abort_if($userType->users()->exists(), 422, 'This user type is assigned to one or more users.');
        $userType->delete();

        return back()->with('success', 'User type deleted.');
    }

    private function form(UserType $record)
    {
        return view('user-types.form', ['resource' => 'user-types', 'record' => $record, 'options' => [], 'definition' => $this->definition(), 'dedicatedRoutes' => true]);
    }

    private function validated(Request $request, ?UserType $userType = null): array
    {
        return $request->validate(['type_name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:255']]);
    }

    private function generateTypeCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, '_'));
        $base = Str::limit($base ?: 'TYPE', 24, '');
        $code = $base;
        $suffix = 2;
        while (UserType::where('type_code', $code)->exists()) {
            $code = Str::limit($base, 25 - strlen((string) $suffix), '').'_'.$suffix++;
        }

return $code;
    }
}
