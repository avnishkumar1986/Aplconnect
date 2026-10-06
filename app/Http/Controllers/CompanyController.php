<?php

namespace App\Http\Controllers;

use App\Models\{Address, Company, Contact};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    private function definition(): array
    {
        return ['title' => 'Companies & Plants', 'fields' => [
            'company_code' => ['label' => 'Code', 'rules' => 'required|string|max:50'],
            'company_name' => ['label' => 'Company / Plant name', 'rules' => 'required|string|max:255'],
            'record_type' => ['label' => 'Record type', 'type' => 'select', 'options' => ['1' => 'Company', '2' => 'Plant'], 'rules' => 'required|in:1,2'],
            'parent_company_id' => ['label' => 'Parent company', 'type' => 'model', 'model' => Company::class, 'relation' => 'parent', 'display' => 'company_name', 'rules' => 'nullable|exists:tbl_company,company_code'],
            'address_id' => ['label' => 'Address', 'type' => 'model', 'model' => Address::class, 'relation' => 'address', 'display' => 'full_address', 'order_by' => 'address_line_1', 'table_hidden' => true, 'rules' => 'nullable|exists:tbl_addresses,id'],
            'contact_id' => ['label' => 'Contact', 'type' => 'model', 'model' => Contact::class, 'relation' => 'contact', 'display' => 'contact_value', 'table_hidden' => true, 'rules' => 'nullable|exists:tbl_contacts,id'],
            'email' => ['label' => 'Email', 'form_hidden' => true, 'computed' => true, 'rules' => 'nullable'],
            'gstin' => ['label' => 'GSTIN', 'table_hidden' => true, 'rules' => 'nullable|string|max:30'],
            'pan' => ['label' => 'PAN', 'table_hidden' => true, 'rules' => 'nullable|string|max:20'],
            'Status' => ['label' => 'Status', 'type' => 'select', 'options' => ['1' => 'Active', '0' => 'Inactive'], 'rules' => 'nullable|in:0,1'],
        ]];
    }

    public function index(Request $request)
    {
        Gate::authorize('companies.view');
        $definition = $this->definition();
        $query = Company::with(['parent', 'address', 'contact', 'contacts']);
        if ($search = trim((string) $request->q)) $query->where(fn ($q) => $q->where('company_code', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%"));
        $sortable = array_merge(['id'], array_keys(array_filter($definition['fields'], fn ($field) => ! ($field['table_hidden'] ?? false) && ! ($field['computed'] ?? false))));
        $sort = in_array($request->sort, $sortable, true) ? $request->sort : 'id';
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';
        return view('companies.index', ['resource'=>'companies','definition'=>$definition,'records'=>$query->orderBy($sort,$direction)->paginate(config('app.table_page_length'))->withQueryString(),'sortableFields'=>$sortable,'dedicatedRoutes'=>true,'formsInModal'=>true]);
    }

    public function create() { Gate::authorize('companies.create'); return $this->form(new Company); }
    public function edit(Company $company) { Gate::authorize('companies.edit'); return $this->form($company); }

    public function store(Request $request)
    {
        Gate::authorize('companies.create');
        $data = $this->validated($request);
        DB::transaction(function () use ($request, $data) {
            $data['Status'] = 1;
            $data['Created_by'] = auth()->id();
            $company = Company::create($data);
            $this->createRelatedRecords($request, $data, $company);
            $company->update([
                'contact_id' => $data['contact_id'],
                'address_id' => $data['address_id'],
            ]);
        });
        return redirect()->route('admin.companies.index')->with('success', 'Company or plant created.');
    }

    public function update(Request $request, Company $company)
    {
        Gate::authorize('companies.edit');
        $data = $this->validated($request, $company);
        DB::transaction(function () use ($request, $company, $data) {
            $data['Updated_by'] = auth()->id();
            $company->update($data);
            $this->createRelatedRecords($request, $data, $company);
            $company->update([
                'contact_id' => $data['contact_id'],
                'address_id' => $data['address_id'],
            ]);
        });
        return redirect()->route('admin.companies.index')->with('success', 'Company or plant updated.');
    }

    public function destroy(Company $company)
    {
        Gate::authorize('companies.delete');
        abort_if($company->children()->exists(), 422, 'Remove or reassign child plants before deleting this company.');
        $company->delete();
        return back()->with('success', 'Company or plant deleted.');
    }

    private function form(Company $record)
    {
        $definition = $this->definition(); $options = [];
        foreach ($definition['fields'] as $name=>$field) if (($field['type']??'')==='model') $options[$name] = $field['model']::when($name==='parent_company_id', fn($q)=>$q->where('record_type',1)->when($record->exists,fn($x)=>$x->whereKeyNot($record->id)))->orderBy($field['order_by']??$field['display'])->get();
        $record->loadMissing(['contacts', 'addresses']);
        $formContacts = old('contacts', $record->exists
            ? $record->contacts->map(fn (Contact $contact) => [
                'id' => $contact->id,
                'type' => (string) $contact->contact_type,
                'value' => $contact->contact_value,
                'is_primary' => (bool) $contact->is_primary,
            ])->values()->all()
            : []);
        $formAddresses = old('addresses', $record->exists
            ? $record->addresses->map(fn (Address $address) => [
                'id' => $address->id,
                'type' => (string) $address->address_type,
                'line_1' => $address->address_line_1,
                'line_2' => $address->address_line_2,
                'city' => $address->city,
                'district' => $address->district,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country' => $address->country,
            ])->values()->all()
            : []);
        $formContacts = $formContacts ?: [['type'=>'1','value'=>'','is_primary'=>1]];
        $formAddresses = $formAddresses ?: [['type'=>'1','line_1'=>'','line_2'=>'','city'=>'','district'=>'','state'=>'','postal_code'=>'','country'=>'India']];
        return view('companies.form', compact('record', 'definition', 'options', 'formContacts', 'formAddresses') + ['resource'=>'companies']);
    }

    private function validated(Request $request, ?Company $company=null): array
    {
        $rules = collect($this->definition()['fields'])->mapWithKeys(fn($field,$name)=>[$name=>$field['rules']])->all();
        $rules['company_code'] = ['required','string','max:50',Rule::unique('tbl_company','company_code')->ignore($company?->id)];
        $rules += [
            'contacts' => ['nullable', 'array'],
            'contacts.*.id' => ['nullable', 'integer', 'exists:tbl_contacts,id'],
            'contacts.*.type' => ['nullable', 'required_with:contacts.*.value', 'in:1,2,3,4'],
            'contacts.*.value' => ['nullable', 'string', 'max:255'],
            'contacts.*.is_primary' => ['nullable', 'boolean'],
            'addresses' => ['nullable', 'array'],
            'addresses.*.id' => ['nullable', 'integer', 'exists:tbl_addresses,id'],
            'addresses.*.type' => ['nullable', 'required_with:addresses.*.line_1', 'in:1,2,3,4,5'],
            'addresses.*.line_1' => ['nullable', 'string', 'max:255'],
            'addresses.*.line_2' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['nullable', 'required_with:addresses.*.line_1', 'string', 'max:100'],
            'addresses.*.district' => ['nullable', 'string', 'max:100'],
            'addresses.*.state' => ['nullable', 'required_with:addresses.*.line_1', 'string', 'max:100'],
            'addresses.*.postal_code' => ['nullable', 'required_with:addresses.*.line_1', 'string', 'max:20'],
            'addresses.*.country' => ['nullable', 'required_with:addresses.*.line_1', 'string', 'max:100'],
        ];
        return Arr::only($request->validate($rules), array_keys($this->definition()['fields']));
    }

    private function createRelatedRecords(Request $request, array &$data, Company $company): void
    {
        $contactIds = $company->contacts()->pluck('id')->all();
        $contactIds = array_merge($contactIds, array_filter([
            $data['contact_id'] ?? null,
            $company->contact_id,
        ]));
        $editableContactIds = $contactIds;
        foreach ($request->input('contacts', []) as $contact) {
            if (blank($contact['value'] ?? null)) continue;
            $contactData = [
                'employee_id' => '0',
                'company_code' => $company->id,
                'contact_type' => $contact['type'],
                'contact_value' => $contact['value'],
                'is_primary' => (bool) ($contact['is_primary'] ?? false),
                'status' => 1,
            ];
            $contactId = (int) ($contact['id'] ?? 0);
            if ($contactId && in_array($contactId, $editableContactIds, true)) {
                Contact::whereKey($contactId)->update($contactData + ['updated_by' => auth()->id()]);
                $contactIds[] = $contactId;
            } else {
                $contactIds[] = Contact::create($contactData + ['created_by' => auth()->id()])->id;
            }
        }
        $contactIds = array_values(array_unique($contactIds));
        if ($contactIds) {
            Contact::whereIn('id', $contactIds)->update([
                'company_code' => $company->id,
                'updated_by' => auth()->id(),
            ]);
        }
        $addressIds = $company->addresses()->pluck('id')->all();
        $addressIds = array_merge($addressIds, array_filter([
            $data['address_id'] ?? null,
            $company->address_id,
        ]));
        $editableAddressIds = $addressIds;
        foreach ($request->input('addresses', []) as $address) {
            if (blank($address['line_1'] ?? null)) continue;
            $addressData = [
                'employee_id' => 0,
                'company_code' => $company->id,
                'address_type' => $address['type'],
                'address_line_1' => $address['line_1'],
                'address_line_2' => $address['line_2'] ?? null,
                'city' => $address['city'],
                'district' => $address['district'] ?? null,
                'state' => $address['state'],
                'postal_code' => $address['postal_code'],
                'country' => $address['country'] ?? 'India',
                'status' => 1,
            ];
            $addressId = (int) ($address['id'] ?? 0);
            if ($addressId && in_array($addressId, $editableAddressIds, true)) {
                Address::whereKey($addressId)->update($addressData + ['updated_by' => auth()->id()]);
                $addressIds[] = $addressId;
            } else {
                $addressIds[] = Address::create($addressData + ['created_by' => auth()->id()])->id;
            }
        }
        $addressIds = array_values(array_unique($addressIds));
        if ($addressIds) {
            Address::whereIn('id', $addressIds)->update([
                'company_code' => $company->id,
                'updated_by' => auth()->id(),
            ]);
        }
        $data['contact_id'] = $data['contact_id'] ?? ($contactIds[0] ?? null);
        $data['address_id'] = $data['address_id'] ?? ($addressIds[0] ?? null);
    }
}
