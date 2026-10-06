<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Contact;
use App\Models\Company;
use App\Models\Education;
use App\Models\Login;
use App\Models\UserProfile;
use App\Models\UserType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class CrudController extends Controller
{
    private function definitions(): array
    {
        return [
            'addresses' => ['title' => 'Addresses', 'model' => Address::class, 'search' => ['address_line_1', 'city', 'state', 'postal_code'], 'fields' => [
                'address_type' => ['label' => 'Type', 'type' => 'select', 'options' => ['1' => 'Current', '2' => 'Permanent', '3' => 'Office', '4' => 'Plant', '5' => 'Subsidiary'], 'rules' => 'required|in:1,2,3,4,5'], 'address_line_1' => ['label' => 'Address line 1', 'rules' => 'required|max:255'], 'address_line_2' => ['label' => 'Address line 2', 'rules' => 'nullable|max:255'], 'city' => ['rules' => 'required|max:100'], 'district' => ['rules' => 'nullable|max:100'], 'state' => ['rules' => 'required|max:100'], 'postal_code' => ['label' => 'Postal code', 'rules' => 'required|max:20'], 'country' => ['rules' => 'required|max:100', 'default' => 'India'], 'status' => $this->statusField()], ],
            'contacts' => ['title' => 'Contacts', 'model' => Contact::class, 'search' => ['contact_value'], 'fields' => ['contact_type' => ['label' => 'Type', 'type' => 'select', 'options' => ['1' => 'Mobile', '2' => 'Telephone', '3' => 'Email', '4' => 'Emergency contact'], 'rules' => 'required|in:1,2,3,4'], 'contact_value' => ['label' => 'Value', 'rules' => 'required|max:255'], 'is_primary' => ['label' => 'Primary', 'type' => 'checkbox', 'rules' => 'boolean'], 'status' => $this->statusField()]],
            'accounts' => ['title' => 'Login accounts', 'model' => Login::class, 'search' => ['username'], 'fields' => ['username' => ['rules' => 'required|max:100'], 'password' => ['type' => 'password', 'rules' => 'nullable|min:8', 'help' => 'Required when creating; leave blank during editing to keep the current password.'], 'status' => ['type' => 'select', 'options' => ['1' => 'Active', '0' => 'Inactive'], 'rules' => 'required|boolean'], 'roles' => ['type' => 'roles', 'rules' => 'array']]],
            'education' => ['title' => 'Education', 'model' => Education::class, 'search' => ['education_level', 'degree_name', 'institution_name'], 'fields' => ['user_id' => ['label' => 'User', 'type' => 'model', 'model' => UserProfile::class, 'display' => 'full_name', 'rules' => 'required|exists:tbl_users,id'], 'education_level' => ['rules' => 'required|max:100'], 'degree_name' => ['rules' => 'required|max:150'], 'institution_name' => ['rules' => 'required|max:255'], 'university_name' => ['rules' => 'nullable|max:255'], 'start_date' => ['type' => 'date', 'rules' => 'nullable|date'], 'end_date' => ['type' => 'date', 'rules' => 'nullable|date|after_or_equal:start_date'], 'is_current' => ['type' => 'checkbox', 'rules' => 'boolean'], 'grade' => ['rules' => 'nullable|max:50'], 'image_path' => ['label' => 'Document path', 'rules' => 'nullable|max:500'], 'uploaded_at' => ['type' => 'datetime-local', 'rules' => 'nullable|date'], 'status' => ['type' => 'select', 'options' => array_combine(['pending', 'verified', 'rejected', 'inactive'], ['Pending', 'Verified', 'Rejected', 'Inactive']), 'rules' => 'required|in:pending,verified,rejected,inactive']]],
        ];
    }

    private function statusField(): array
    {
        return ['type' => 'select', 'options' => ['1' => 'Active', '0' => 'Inactive'], 'rules' => 'required|in:0,1'];
    }

    private function def(string $resource): array
    {
        abort_unless(isset($this->definitions()[$resource]), 404);

        return $this->definitions()[$resource];
    }

    private function authorizeAction(string $r, string $action): void
    {
        Gate::authorize("$r.$action");
    }

    public function index(Request $request, string $resource)
    {
        $this->authorizeAction($resource, 'view');
        $d = $this->def($resource);
        $relations = collect($d['fields'])->filter(fn ($f) => ($f['type'] ?? '') === 'model')->map(fn ($f, $name) => $f['relation'] ?? str($name)->beforeLast('_id')->camel()->toString())->values()->all();
        $q = $d['model']::query()->with($relations);
        if ($s = trim((string) $request->q)) {
            $q->where(fn ($x) => collect($d['search'])->each(fn ($f, $i) => $i ? $x->orWhere($f, 'like', "%$s%") : $x->where($f, 'like', "%$s%")));
        }$sortable = collect($d['fields'])->reject(fn ($f) => ($f['type'] ?? '') === 'roles')->keys()->push('id')->all();
        $sort = in_array($request->sort, $sortable, true) ? $request->sort : 'id';
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';
        $q->orderBy($sort, $direction);

        return view($resource.'.index', ['resource' => $resource, 'definition' => $d, 'records' => $q->paginate(config('app.table_page_length'))->withQueryString(), 'sortableFields' => $sortable]);
    }

    public function create(string $resource)
    {
        $this->authorizeAction($resource, 'create');

        return $this->form($resource, new ($this->def($resource)['model']));
    }

    public function store(Request $request, string $resource)
    {
        $this->authorizeAction($resource, 'create');
        $d = $this->def($resource);
        $data = $this->validated($request, $d, true);
        $data['created_by'] = auth()->id();
        $record = $d['model']::create($data);
        $this->syncRoles($record, $request, $resource);

        return redirect()->route('admin.crud.index', $resource)->with('success', $d['title'].' created.');
    }

    public function edit(string $resource, int $id)
    {
        $this->authorizeAction($resource, 'edit');
        $d = $this->def($resource);

        return $this->form($resource, $d['model']::findOrFail($id));
    }

    public function update(Request $request, string $resource, int $id)
    {
        $this->authorizeAction($resource, 'edit');
        $d = $this->def($resource);
        $record = $d['model']::findOrFail($id);
        $data = $this->validated($request, $d, false);
        $data['updated_by'] = auth()->id();
        $record->update($data);
        $this->syncRoles($record, $request, $resource);

        return redirect()->route('admin.crud.index', $resource)->with('success', $d['title'].' updated.');
    }

    public function destroy(string $resource, int $id)
    {
        $this->authorizeAction($resource, 'delete');
        $d = $this->def($resource);
        $record = $d['model']::findOrFail($id);
        abort_if($resource === 'accounts' && $record->is(auth()->user()), 422, 'You cannot delete your current account.');
        $record->delete();

        return back()->with('success', 'Record deleted.');
    }

    public function bulkAction(Request $request, string $resource)
    {
        $models = [
            'addresses' => [Address::class, 'status'],
            'contacts' => [Contact::class, 'status'],
            'accounts' => [Login::class, 'status'],
            'user-types' => [UserType::class, 'status'],
            'companies' => [Company::class, 'Status'],
        ];
        abort_unless(isset($models[$resource]), 404);
        Gate::authorize("{$resource}.edit");
        $data = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);
        [$model, $statusColumn] = $models[$resource];
        $records = $model::query()->whereKey($data['ids']);
        $count = (clone $records)->count();
        DB::transaction(fn () => $records->update([
            $statusColumn => $data['action'] === 'activate' ? 1 : 0,
        ]));

        return back()->with('success', "{$count} record(s) updated successfully.");
    }

    private function form(string $resource, $record)
    {
        $d = $this->def($resource);
        $options = [];
        foreach ($d['fields'] as $name => $f) {
            if (($f['type'] ?? '') === 'model') {
                $options[$name] = $f['model']::orderBy($f['display'])->get();
            }
        }

        $roles = $resource === 'accounts'
            ? Role::orderBy('name')->get()->map(fn ($role) => ['name' => $role->name, 'selected' => $record->exists && $record->hasRole($role)])->all()
            : [];

        return view($resource.'.form', compact('resource', 'record', 'options', 'roles') + ['definition' => $d]);
    }

    private function validated(Request $request, array $d, bool $creating): array
    {
        $rules = [];
        foreach ($d['fields'] as $name => $f) {
            $rules[$name] = $f['rules'];
        }if (isset($rules['password']) && $creating) {
            $rules['password'] = 'required|min:8';
        }$data = $request->validate($rules);
        foreach ($d['fields'] as $name => $f) {
            if (($f['type'] ?? '') === 'checkbox') {
                $data[$name] = $request->boolean($name);
            }
        }unset($data['roles']);
        if (isset($data['password']) && ! $data['password']) {
            unset($data['password']);
        }

return $data;
    }

    private function syncRoles($record,Request $request,string $resource): void
    {
        if ($resource === 'accounts') {
            $record->syncRoles($request->input('roles',[]));
        }
    }
}
