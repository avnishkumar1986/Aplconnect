<?php

namespace App\Http\Controllers;

use App\Models\{Address, Company, Contact, Department, Designation, DistributorProfile, Education, EmployeeDepartment, Login, UserDocument, UserImage, UserProfile, UserType};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private function definition(): array
    {
        return [
            'title' => 'Users',
            'search' => ['first_name', 'last_name'],
            'fields' => [
                'type_code' => ['label' => 'User type', 'type' => 'model', 'model' => UserType::class, 'relation' => 'userType', 'display' => 'type_name', 'option_value' => 'type_code', 'rules' => 'required|exists:tbl_usertypes,type_code'],
                'code' => ['label' => 'User ID', 'rules' => 'required|integer|min:1'],
                'company_id' => ['label' => 'Company', 'type' => 'model', 'model' => Company::class, 'relation' => 'company', 'display' => 'company_name', 'option_value' => 'company_code', 'rules' => 'required|exists:tbl_company,company_code'],
                'sitting_location_id' => ['label' => 'Sitting location', 'type' => 'model', 'model' => Company::class, 'relation' => 'sittingLocation', 'display' => 'company_name', 'rules' => 'required|exists:tbl_company,id'],
                'designation_id' => ['label' => 'Designation', 'type' => 'model', 'model' => Designation::class, 'relation' => 'designation', 'display' => 'designation_name', 'rules' => 'nullable|exists:tbl_designations,id'],
                'department_id' => ['label' => 'Department', 'type' => 'model', 'model' => Department::class, 'relation' => 'department', 'display' => 'department_name', 'rules' => 'nullable|exists:tbl_departments,id'],
                'first_name' => ['rules' => 'required|max:100'],
                'last_name' => ['rules' => 'required|max:100'],
                'gst_number' => ['label' => 'GSTIN', 'rules' => 'nullable|string|size:15'],
                'pan_number' => ['label' => 'PAN number', 'rules' => 'nullable|string|size:10'],
                'gender' => ['type' => 'select', 'options' => ['' => '— Select —', '1' => 'Male', '2' => 'Female', '3' => 'Other', '4' => 'Prefer not to say'], 'rules' => 'nullable|in:1,2,3,4'],
                'is_verified' => ['label' => 'Verification status', 'type' => 'select', 'options' => ['1' => 'Verified', '0' => 'Not verified'], 'rules' => 'required|in:0,1'],
                'date_of_birth' => ['type' => 'date', 'rules' => 'nullable|date'],
                'marital_status' => ['type' => 'select', 'options' => ['' => '— Select —', '1' => 'Single', '2' => 'Married', '3' => 'Divorced', '4' => 'Widowed'], 'rules' => 'nullable|in:1,2,3,4'],
                'anniversary_date' => ['type' => 'date', 'rules' => 'nullable|date'],
                'contact_id' => ['label' => 'Contact', 'type' => 'model', 'model' => Contact::class, 'relation' => 'contact', 'display' => 'contact_value', 'table_hidden' => true, 'rules' => 'nullable|exists:tbl_contacts,id'],
                'address_id' => ['label' => 'Address', 'type' => 'model', 'model' => Address::class, 'relation' => 'address', 'display' => 'address_line_1', 'table_hidden' => true, 'rules' => 'nullable|exists:tbl_addresses,id'],
                'login_id' => ['label' => 'Login account', 'type' => 'model', 'model' => Login::class, 'relation' => 'login', 'display' => 'username', 'table_hidden' => true, 'rules' => 'nullable|exists:tbl_login,id'],
                'status' => ['type' => 'select', 'options' => ['1' => 'Active', '0' => 'Inactive'], 'default' => '1', 'rules' => 'required|in:0,1'],
            ],
        ];
    }

    public function index(Request $request)
    {
        Gate::authorize('users.view');
        $definition = $this->definition();
        $query = UserProfile::with([
            'employmentDepartment', 'distributorProfile', 'userType', 'company', 'sittingLocation', 'designation.parentDesignation', 'department', 'reportingTo.designation',
            'contact', 'contacts', 'emailContacts', 'address', 'primaryEducation', 'login', 'profileImage',
            'createdBy.profile', 'updatedBy.profile',
        ]);

        return view('users.index', [
            'resource' => 'users',
            'definition' => $definition,
            'records' => $query->orderBy('id')->get(),
            'sortableFields' => array_merge(['id'], array_keys($definition['fields'])),
            'options' => $this->options($definition),
            'dedicatedRoutes' => true,
            'formsInModal' => true,
        ]);
    }

    public function create()
    {
        Gate::authorize('users.create');
        return $this->form(new UserProfile());
    }

    public function show(UserProfile $user)
    {
        Gate::authorize('users.view');
        $user->load([
            'company',
            'sittingLocation',
            'designation.parentDesignation',
            'department',
            'reportingTo.designation',
            'contact',
            'address',
            'contacts',
            'addresses',
            'education',
            'login.roles',
            'profileImage',
            'coverImage',
            'createdBy.profile',
            'updatedBy.profile',
        ]);

        return view('users.show', compact('user'));
    }

    public function uploadProfileImage(Request $request, UserProfile $user)
    {
        Gate::authorize('users.edit');
        $request->validate(['profile_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $file = $request->file('profile_image');
        $path = $file->store("users/{$user->id}/profile", 'public');
        $image = $user->profileImage;
        if ($image?->file_path) Storage::disk('public')->delete($image->file_path);
        UserImage::updateOrCreate(['user_id'=>$user->id,'image_type'=>'profileimg'], ['file_path'=>$path,'original_name'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType(),'file_size'=>$file->getSize(),'created_by'=>$image?->created_by??auth()->id(),'updated_by'=>auth()->id()]);

        return back()->with('success', 'Profile image updated.');
    }

    public function uploadCoverImage(Request $request, UserProfile $user)
    {
        Gate::authorize('users.edit');
        $request->validate(['cover_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192']]);
        $file = $request->file('cover_image');
        $path = $file->store("users/{$user->id}/cover", 'public');
        $image = $user->coverImage;
        if ($image?->file_path) Storage::disk('public')->delete($image->file_path);
        UserImage::updateOrCreate(['user_id'=>$user->id,'image_type'=>'wallimage'], ['file_path'=>$path,'original_name'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType(),'file_size'=>$file->getSize(),'created_by'=>$image?->created_by??auth()->id(),'updated_by'=>auth()->id()]);

        return back()->with('success', 'Wall image updated.');
    }

    public function uploadEducationDocument(Request $request, UserProfile $user, Education $education)
    {
        Gate::authorize('users.edit');
        abort_unless((int) $education->user_id === (int) $user->id, 404);
        $request->validate(['education_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240']]);
        $path = $request->file('education_document')->store("users/{$user->id}/education", 'public');
        if ($education->image_path) Storage::disk('public')->delete($education->image_path);
        $education->update(['image_path' => $path, 'uploaded_at' => now(), 'updated_by' => auth()->id()]);

        return back()->with('success', 'Education document uploaded.');
    }

    public function store(Request $request)
    {
        Gate::authorize('users.create');
        $data = $this->validated($request);
        $isDistributor = $this->isDistributorTypeCode($data['type_code']);
        DB::transaction(function () use ($request, $data, $isDistributor) {
            $login = Login::create([
                'username' => $data['username'],
                'password' => $data['password'],
                'status' => (bool) $data['status'],
                'created_by' => auth()->id(),
            ]);
            $login->syncRoles([Role::findById((int) $data['role_id'], 'web')]);
            unset($data['username'], $data['password']);
            $data['login_id'] = $login->id;

            $createdContacts = collect();
            if (! ($data['contact_id'] ?? null)) {
                $createdContacts = collect($request->input('contacts', []))
                    ->filter(fn ($contact) => filled($contact['value'] ?? null))
                    ->map(fn ($contact) => Contact::create([
                        'employee_id' => '0',
                        'company_code' => $data['company_id'] ?? 0,
                        'contact_type' => $contact['type'],
                        'contact_value' => $contact['value'],
                        'is_primary' => (bool) ($contact['is_primary'] ?? false),
                        'status' => 1,
                        'created_by' => auth()->id(),
                    ]));
                $data['contact_id'] = $createdContacts->firstWhere('is_primary', true)?->id
                    ?? $createdContacts->first()?->id;
            }

            $data['created_by'] = auth()->id();
            $employmentData = Arr::only($data, ['company_id', 'sitting_location_id', 'department_id', 'designation_id', 'role_id', 'reporting_to_user_id', 'status']);
            $data['business_name'] = $isDistributor ? $data['distributor_business_name'] : null;
            $data['legal_business_name'] = $isDistributor ? $data['distributor_legal_business_name'] : null;
            $userData = Arr::except($data, ['company_id', 'sitting_location_id', 'department_id', 'designation_id', 'role_id', 'reporting_to_user_id', 'distributor_business_name', 'distributor_legal_business_name', 'distributor_category', 'distributor_contact_person_user_id']);
            $userData['distributor_category'] = $isDistributor ? $data['distributor_category'] : null;
            $user = UserProfile::create($userData);
            $this->syncDistributorProfile($user, $data, $isDistributor);
            if (! $isDistributor) {
                $this->syncEmploymentDepartment($user, $employmentData);
            }
            $createdContacts->each(fn (Contact $contact) => $contact->update([
                'employee_id' => (string) $user->id,
                'updated_by' => auth()->id(),
            ]));
            $this->syncSupplierDocuments($request, $user);

            if ($data['contact_id'] ?? null) {
                Contact::whereKey($data['contact_id'])->update([
                    'employee_id' => (string) $user->id,
                    'company_code' => $data['company_id'] ?? 0,
                    'updated_by' => auth()->id(),
                ]);
            }

            if (! ($data['address_id'] ?? null)) {
                $createdAddresses = collect($request->input('addresses', []))
                    ->filter(fn ($address) => filled($address['line_1'] ?? null))
                    ->map(fn ($address) => Address::create([
                        'employee_id' => (string) $user->id,
                        'company_code' => $data['company_id'] ?? 0,
                        'address_type' => $address['type'],
                        'is_current_permanent_same' => ($address['type'] ?? null) === '1'
                            && (bool) ($address['is_current_permanent_same'] ?? false),
                        'address_line_1' => $address['line_1'],
                        'address_line_2' => $address['line_2'] ?? null,
                        'city' => $address['city'],
                        'district' => $address['district'] ?? null,
                        'state' => $address['state'],
                        'postal_code' => $address['postal_code'],
                        'country' => $address['country'] ?? 'India',
                        'latitude' => $address['latitude'] ?? null,
                        'longitude' => $address['longitude'] ?? null,
                        'status' => 1,
                        'created_by' => auth()->id(),
                    ]));
                $data['address_id'] = $createdAddresses->first()?->id;
                $user->update(['address_id' => $data['address_id']]);
            }

            if ($data['address_id'] ?? null) {
                Address::whereKey($data['address_id'])->update([
                    'employee_id' => (string) $user->id,
                    'company_code' => $data['company_id'] ?? 0,
                    'updated_by' => auth()->id(),
                ]);
            }

            if ($request->filled('new_education_level')) {
                $educationDocumentPath = $request->hasFile('new_education_document')
                    ? $request->file('new_education_document')->store("users/{$user->id}/education", 'public')
                    : null;
                $education = Education::create([
                    'user_id' => $user->id,
                    'education_level' => $request->input('new_education_level'),
                    'degree_name' => $request->input('new_degree_name'),
                    'institution_name' => $request->input('new_institution_name'),
                    'university_name' => $request->input('new_university_name'),
                    'start_date' => $request->input('new_education_start_date'),
                    'end_date' => $request->input('new_education_end_date'),
                    'is_current' => $request->boolean('new_education_is_current'),
                    'grade' => $request->input('new_education_grade'),
                    'image_path' => $educationDocumentPath,
                    'uploaded_at' => $educationDocumentPath ? now() : null,
                    'status' => 'pending',
                    'created_by' => auth()->id(),
                ]);
                $user->update(['education_id' => $education->id]);
            }
            $this->createAdditionalEducation($request, $user);
        });

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(UserProfile $user)
    {
        Gate::authorize('users.edit');
        return $this->form($user);
    }

    public function update(Request $request, UserProfile $user)
    {
        Gate::authorize('users.edit');
        $data = $this->validated($request, $user);
        $isDistributor = $this->isDistributorTypeCode($data['type_code']);
        $login = $user->login ?: new Login(['created_by' => auth()->id()]);
        $login->username = $data['username'];
        $login->status = (bool) $data['status'];
        $login->updated_by = auth()->id();
        if (filled($data['password'] ?? null)) $login->password = $data['password'];
        $login->save();
        $login->syncRoles([Role::findById((int) $data['role_id'], 'web')]);
        unset($data['username'], $data['password']);
        $data['login_id'] = $login->id;
        $data['updated_by'] = auth()->id();
        $employmentData = Arr::only($data, ['company_id', 'sitting_location_id', 'department_id', 'designation_id', 'role_id', 'reporting_to_user_id', 'status']);
        $data['business_name'] = $isDistributor ? $data['distributor_business_name'] : null;
        $data['legal_business_name'] = $isDistributor ? $data['distributor_legal_business_name'] : null;
        $userData = Arr::except($data, ['company_id', 'sitting_location_id', 'department_id', 'designation_id', 'role_id', 'reporting_to_user_id', 'distributor_business_name', 'distributor_legal_business_name', 'distributor_category', 'distributor_contact_person_user_id']);
        $userData['distributor_category'] = $isDistributor ? $data['distributor_category'] : null;
        $user->update($userData);
        $this->syncDistributorProfile($user, $data, $isDistributor);
        if ($isDistributor) {
            $user->employmentDepartment?->delete();
        } else {
            $this->syncEmploymentDepartment($user, $employmentData);
        }
        $this->syncSupplierDocuments($request, $user);
        $this->syncUserContacts($user, collect($request->input('contacts', [])), $data['company_id'] ?? '0');
        $this->syncUserAddresses($user, collect($request->input('addresses', [])), $data['company_id'] ?? '0');

        if ($request->filled('new_education_level') || $request->hasFile('new_education_document')) {
            $education = $user->primaryEducation ?: new Education([
                'user_id' => $user->id,
                'status' => 'pending',
                'created_by' => auth()->id(),
            ]);
            $education->fill([
                'education_level' => $request->input('new_education_level', $education->education_level),
                'degree_name' => $request->input('new_degree_name', $education->degree_name),
                'institution_name' => $request->input('new_institution_name', $education->institution_name),
                'university_name' => $request->input('new_university_name', $education->university_name),
                'start_date' => $request->input('new_education_start_date', $education->start_date),
                'end_date' => $request->input('new_education_end_date', $education->end_date),
                'is_current' => $request->boolean('new_education_is_current'),
                'grade' => $request->input('new_education_grade', $education->grade),
                'updated_by' => auth()->id(),
            ]);
            if ($request->hasFile('new_education_document')) {
                if ($education->image_path) Storage::disk('public')->delete($education->image_path);
                $education->image_path = $request->file('new_education_document')->store("users/{$user->id}/education", 'public');
                $education->uploaded_at = now();
            }
            $education->save();
            if (! $user->education_id) $user->update(['education_id' => $education->id]);
        }
        $this->createAdditionalEducation($request, $user);

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(UserProfile $user)
    {
        Gate::authorize('users.delete');
        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    public function bulkAction(Request $request)
    {
        Gate::authorize('users.edit');
        $validated = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate'])],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct', 'exists:tbl_users,id'],
        ]);

        $currentProfileId = UserProfile::where('login_id', auth()->id())->value('id');
        $users = UserProfile::whereIn('id', $validated['user_ids'])
            ->when($currentProfileId, fn ($query) => $query->whereKeyNot($currentProfileId))
            ->get();
        $status = $validated['action'] === 'activate' ? '1' : '0';

        DB::transaction(fn () => $users->each(function (UserProfile $user) use ($status): void {
            $user->update(['status' => $status, 'updated_by' => auth()->id()]);
            $user->employmentDepartment()->update(['status' => $status, 'updated_by' => auth()->id()]);
        }));

        return back()->with('success', $users->count().' user(s) '.$validated['action'].'d.');
    }

    private function form(UserProfile $record)
    {
        if ($record->exists) {
            $record->load(['employmentDepartment', 'distributorProfile', 'userType', 'login.roles', 'primaryEducation', 'contacts', 'addresses', 'education', 'documents']);
        }
        $definition = $this->definition();
        $options = $this->options($definition);
        $supplierOptions = Schema::hasTable('tbl_vendors')
            ? DB::table('tbl_vendors')
                ->whereNotNull('vendor_code')
                ->where('vendor_code', '<>', '')
                ->select('vendor_code', DB::raw('MAX(vendor_name) as vendor_name'))
                ->groupBy('vendor_code')
                ->orderBy('vendor_name')
                ->orderBy('vendor_code')
                ->get()
                ->map(fn ($vendor) => [
                    'value' => (string) $vendor->vendor_code,
                    'label' => trim(($vendor->vendor_name ?: 'Unnamed supplier').' · '.$vendor->vendor_code),
                ])
                ->values()
                ->all()
            : [];
        $materialGroupOptions = Schema::hasTable('tbl_materials')
            ? DB::table('tbl_materials')
                ->whereNotNull('matkl')
                ->where('matkl', '<>', '')
                ->distinct()
                ->orderBy('matkl')
                ->pluck('matkl')
                ->map(fn ($group) => ['value' => (string) $group, 'label' => (string) $group])
                ->values()
                ->all()
            : [];
        return view('users.form', [
            'resource' => 'users',
            'record' => $record,
            'options' => $options,
            'definition' => $definition,
            'dedicatedRoutes' => true,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'supplierOptions' => $supplierOptions,
            'materialGroupOptions' => $materialGroupOptions,
            'supplierDocuments' => $record->exists
                ? $record->documents->mapWithKeys(fn ($document) => [
                    $document->document_type => asset('storage/'.$document->file_path),
                ])->all()
                : [],
        ]);
    }

    private function options(array $definition): array
    {
        $options = [];
        foreach ($definition['fields'] as $name => $field) {
            if (($field['type'] ?? '') === 'model') {
                $options[$name] = $field['model']::query()
                    ->when($name === 'company_id', fn ($query) => $query
                        ->where('record_type', 1)
                        ->where('Status', 1))
                    ->when($name === 'sitting_location_id', fn ($query) => $query
                        ->where('record_type', '<>', 1)
                        ->where('Status', 1))
                    ->orderBy($field['display'])
                    ->get();
            }
        }

        return $options;
    }

    private function validated(Request $request, ?UserProfile $user = null): array
    {
        $request->merge([
            'gst_number' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('gst_number'))),
            'pan_number' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('pan_number'))),
        ]);
        $rules = collect($this->definition()['fields'])->mapWithKeys(fn ($field, $name) => [$name => $field['rules']])->all();
        $isDistributor = $this->isDistributorTypeCode((string) $request->input('type_code'));
        $rules['company_id'] = [$isDistributor ? 'nullable' : 'required', Rule::exists('tbl_company', 'company_code')
            ->where(fn ($query) => $query->where('record_type', 1)->where('Status', 1))];
        $rules['sitting_location_id'] = [$isDistributor ? 'nullable' : 'required', Rule::exists('tbl_company', 'id')
            ->where(fn ($query) => $query->where('record_type', '<>', 1)->where('Status', 1))];
        $rules['login_id'] = ['nullable', 'exists:tbl_login,id', Rule::unique('tbl_users', 'login_id')->ignore($user?->id)];
        $rules['username'] = ['required', 'string', 'max:100', Rule::unique('tbl_login', 'username')->ignore($user?->login_id)];
        $rules['password'] = [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'];
        $rules['role_id'] = ['required', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')];
        $rules['code'] = [
            'required',
            'integer',
            'min:1',
            Rule::unique('tbl_users', 'code')
                ->where(fn ($query) => $query->where('type_code', $request->input('type_code')))
                ->ignore($user?->id),
        ];
        $rules['first_name'] = ['required', 'string', 'max:100'];
        $rules['last_name'] = ['required', 'string', 'max:100'];
        $rules['distributor_business_name'] = [$isDistributor ? 'required' : 'nullable', 'string', 'max:255'];
        $rules['distributor_legal_business_name'] = [$isDistributor ? 'required' : 'nullable', 'string', 'max:255'];
        $rules['distributor_category'] = [$isDistributor ? 'required' : 'nullable', Rule::in(['regional', 'exclusive', 'stockist', 'national', 'other'])];
        $rules['gst_number'] = [$isDistributor ? 'required' : 'nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'];
        $rules['pan_number'] = [$isDistributor ? 'required' : 'nullable', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'];
        $rules['distributor_contact_person_user_id'] = ['nullable', 'integer', Rule::exists('tbl_users', 'id')->where('status', '1')];
        $rules['distributor_company_association'] = [$isDistributor ? 'required' : 'nullable', 'string', 'max:30', 'exists:tbl_company,company_code'];
        $rules['distributor_applicable_plants'] = ['nullable', 'array'];
        $rules['distributor_applicable_plants.*'] = ['integer', 'distinct', 'exists:tbl_company,id'];
        $rules['distributor_material_groups'] = ['nullable', 'array'];
        $rules['distributor_material_groups.*'] = ['string', 'distinct', 'max:50'];
        $rules['distributor_credit_limit'] = ['nullable', 'numeric', 'min:0'];
        $rules['distributor_credit_period_days'] = ['nullable', 'integer', 'min:0', 'max:3650'];
        $rules['distributor_payment_terms'] = ['nullable', 'string', 'max:100'];
        $rules['distributor_price_list'] = ['nullable', 'string', 'max:100'];
        $rules['distributor_currency'] = [$isDistributor ? 'required' : 'nullable', 'string', 'size:3'];
        $rules['distributor_tax_classification'] = ['nullable', 'string', 'max:100'];
        $rules['distributor_bank_name'] = [$isDistributor ? 'required' : 'nullable', 'string', 'max:150'];
        $rules['distributor_account_holder_name'] = [$isDistributor ? 'required' : 'nullable', 'string', 'max:255'];
        $rules['distributor_account_number'] = [$isDistributor ? 'required' : 'nullable', 'string', 'max:50'];
        $rules['distributor_ifsc_code'] = [$isDistributor ? 'required' : 'nullable', 'string', 'max:20', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'];
        $rules['distributor_bank_branch'] = ['nullable', 'string', 'max:150'];
        $rules['distributor_cancelled_cheque'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'];

        $rules += [
            'contacts' => ['nullable', 'array'],
            'contacts.*.type' => ['nullable', 'required_with:contacts.*.value', 'in:1,2,3,4'],
            'contacts.*.id' => ['nullable', 'integer', 'exists:tbl_contacts,id'],
            'contacts.*.value' => ['nullable', 'string', 'max:255'],
            'contacts.*.is_primary' => ['nullable', 'boolean'],
            'addresses' => ['nullable', 'array'],
            'addresses.*.type' => ['nullable', 'required_with:addresses.*.line_1', 'in:1,2,3,4,5'],
            'addresses.*.is_current_permanent_same' => ['nullable', 'boolean'],
            'addresses.*.id' => ['nullable', 'integer', 'exists:tbl_addresses,id'],
            'addresses.*.line_1' => ['nullable', 'string', 'max:255'],
            'addresses.*.line_2' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['nullable', 'required_with:addresses.*.line_1', 'string', 'max:100'],
            'addresses.*.district' => ['nullable', 'string', 'max:100'],
            'addresses.*.state' => ['nullable', 'required_with:addresses.*.line_1', 'string', 'max:100'],
            'addresses.*.postal_code' => ['nullable', 'required_with:addresses.*.line_1', 'string', 'max:20'],
            'addresses.*.country' => ['nullable', 'required_with:addresses.*.line_1', 'string', 'max:100'],
            'addresses.*.latitude' => ['nullable', 'required_with:addresses.*.line_1', 'numeric', 'between:-90,90'],
            'addresses.*.longitude' => ['nullable', 'required_with:addresses.*.line_1', 'numeric', 'between:-180,180'],
            'new_education_level' => ['nullable', 'string', 'max:100'],
            'new_degree_name' => ['nullable', 'required_with:new_education_level', 'string', 'max:150'],
            'new_institution_name' => ['nullable', 'required_with:new_education_level', 'string', 'max:255'],
            'new_university_name' => ['nullable', 'string', 'max:255'],
            'new_education_start_date' => ['nullable', 'date'],
            'new_education_end_date' => ['nullable', 'date', 'after_or_equal:new_education_start_date'],
            'new_education_is_current' => ['nullable', 'boolean'],
            'new_education_grade' => ['nullable', 'string', 'max:50'],
            'new_education_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'additional_education' => ['nullable', 'array'],
            'additional_education.*.id' => ['nullable', 'integer', 'exists:tbl_education,id'],
            'additional_education.*.level' => ['nullable', 'string', 'max:100'],
            'additional_education.*.degree_name' => ['nullable', 'required_with:additional_education.*.level', 'string', 'max:150'],
            'additional_education.*.institution_name' => ['nullable', 'required_with:additional_education.*.level', 'string', 'max:255'],
            'additional_education.*.university_name' => ['nullable', 'string', 'max:255'],
            'additional_education.*.start_date' => ['nullable', 'date'],
            'additional_education.*.end_date' => ['nullable', 'date', 'after_or_equal:additional_education.*.start_date'],
            'additional_education.*.is_current' => ['nullable', 'boolean'],
            'additional_education.*.grade' => ['nullable', 'string', 'max:50'],
            'additional_education.*.document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'supplier_gst_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'supplier_pan_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];

        $validated = $request->validate($rules);
        $isEmployee = $this->isEmployeeTypeCode((string) $request->input('type_code'));
        $addresses = collect($request->input('addresses', []))->filter(fn ($row) => filled($row['line_1'] ?? null));
        $contacts = collect($request->input('contacts', []))->filter(fn ($row) => filled($row['value'] ?? null));
        $phoneCount = $contacts->whereIn('type', ['1', '2'])->count();
        $emailCount = $contacts->where('type', '3')->count();
        $emergencyCount = $contacts->where('type', '4')->count();
        $limitErrors = [];
        if ($addresses->count() > ($isEmployee ? 2 : 1)) {
            $limitErrors['addresses.0.line_1'] = $isEmployee ? 'Employees can have a maximum of two addresses.' : 'This user type can have only one address.';
        }
        if ($phoneCount > ($isEmployee ? 2 : 1)) {
            $limitErrors['contacts.0.value'] = $isEmployee ? 'Employees can have a maximum of two phone contacts.' : 'This user type can have only one phone contact.';
        }
        if ($emailCount > 1) {
            $limitErrors['contacts.0.value'] = 'Only one email contact is allowed.';
        }
        if ($emergencyCount > 1) {
            $limitErrors['contacts.0.value'] = 'Only one emergency contact is allowed.';
        }
        if ($limitErrors) {
            throw ValidationException::withMessages($limitErrors);
        }

        $result = array_merge(
            Arr::only($validated, array_keys($this->definition()['fields'])),
            Arr::only($validated, [
                'username', 'password', 'role_id', 'distributor_business_name',
                'distributor_legal_business_name', 'distributor_category',
                'distributor_contact_person_user_id',
                'distributor_company_association', 'distributor_applicable_plants',
                'distributor_material_groups', 'distributor_credit_limit',
                'distributor_credit_period_days', 'distributor_payment_terms',
                'distributor_price_list', 'distributor_currency',
                'distributor_tax_classification', 'distributor_bank_name',
                'distributor_account_holder_name', 'distributor_account_number',
                'distributor_ifsc_code', 'distributor_bank_branch',
            ]),
        );

        return $result;
    }

    private function syncEmploymentDepartment(UserProfile $user, array $data): void
    {
        EmployeeDepartment::updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_id' => $data['company_id'],
                'sitting_location_id' => $data['sitting_location_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'designation_id' => $data['designation_id'] ?? null,
                'role_id' => $data['role_id'] ?? null,
                'reporting_to_user_id' => $data['reporting_to_user_id'] ?? $user->reporting_to_user_id,
                'status' => $data['status'] ?? '1',
                'created_by' => $user->employmentDepartment?->created_by ?? auth()->id(),
                'updated_by' => auth()->id(),
            ],
        );
    }

    private function isDistributorTypeCode(string $typeCode): bool
    {
        return UserType::query()
            ->where('type_code', $typeCode)
            ->where('type_name', 'like', '%Distributor%')
            ->exists();
    }

    private function isEmployeeTypeCode(string $typeCode): bool
    {
        return UserType::query()
            ->where('type_code', $typeCode)
            ->where('type_name', 'like', '%Employee%')
            ->exists();
    }

    private function syncDistributorProfile(UserProfile $user, array $data, bool $isDistributor): void
    {
        if (! $isDistributor) {
            $user->distributorProfile?->delete();
            return;
        }

        $profile = DistributorProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'business_name' => $data['distributor_business_name'],
                'legal_business_name' => $data['distributor_legal_business_name'],
                'distributor_category' => $data['distributor_category'],
                'contact_person_user_id' => null,
                'company_association' => $data['distributor_company_association'],
                'applicable_plants' => $data['distributor_applicable_plants'] ?? [],
                'material_groups' => $data['distributor_material_groups'] ?? [],
                'credit_limit' => $data['distributor_credit_limit'] ?? null,
                'credit_period_days' => $data['distributor_credit_period_days'] ?? null,
                'payment_terms' => $data['distributor_payment_terms'] ?? null,
                'price_list' => $data['distributor_price_list'] ?? null,
                'currency' => strtoupper($data['distributor_currency'] ?? 'INR'),
                'tax_classification' => $data['distributor_tax_classification'] ?? null,
                'bank_name' => $data['distributor_bank_name'],
                'account_holder_name' => $data['distributor_account_holder_name'],
                'account_number' => $data['distributor_account_number'],
                'ifsc_code' => strtoupper($data['distributor_ifsc_code']),
                'bank_branch' => $data['distributor_bank_branch'] ?? null,
                'created_by' => $user->distributorProfile?->created_by ?? auth()->id(),
                'updated_by' => auth()->id(),
            ],
        );

        if (request()->hasFile('distributor_cancelled_cheque')) {
            if ($profile->cancelled_cheque_path) {
                Storage::disk('public')->delete($profile->cancelled_cheque_path);
            }
            $profile->update([
                'cancelled_cheque_path' => request()->file('distributor_cancelled_cheque')
                    ->store("users/{$user->id}/distributor", 'public'),
                'updated_by' => auth()->id(),
            ]);
        }
    }

    private function syncSupplierDocuments(Request $request, UserProfile $user): void
    {
        foreach (['gst' => 'supplier_gst_document', 'pan' => 'supplier_pan_document'] as $type => $field) {
            if (! $request->hasFile($field)) continue;

            $file = $request->file($field);
            $existing = UserDocument::query()
                ->where('user_id', $user->id)
                ->where('document_type', $type)
                ->first();
            if ($existing?->file_path) Storage::disk('public')->delete($existing->file_path);
            $path = $file->store("users/{$user->id}/supplier-documents", 'public');

            UserDocument::updateOrCreate(
                ['user_id' => $user->id, 'document_type' => $type],
                [
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'created_by' => $existing?->created_by ?? auth()->id(),
                    'updated_by' => auth()->id(),
                ],
            );
        }
    }

    private function syncUserContacts(UserProfile $user, $rows, string $companyCode): void
    {
        $saved = $rows->filter(fn ($row) => filled($row['value'] ?? null))->map(function ($row) use ($user, $companyCode) {
            $contact = filled($row['id'] ?? null)
                ? $user->contacts()->whereKey($row['id'])->firstOrFail()
                : new Contact(['created_by' => auth()->id(), 'status' => 1]);
            $contact->fill([
                'employee_id' => (string) $user->id,
                'company_code' => $companyCode,
                'contact_type' => $row['type'],
                'contact_value' => $row['value'],
                'is_primary' => (bool) ($row['is_primary'] ?? false),
                'updated_by' => auth()->id(),
            ])->save();
            return $contact;
        });
        $primary = $saved->firstWhere('is_primary', true) ?? $saved->first();
        if ($primary && (int) $user->contact_id !== (int) $primary->id) $user->update(['contact_id' => $primary->id]);
    }

    private function syncUserAddresses(UserProfile $user, $rows, string $companyCode): void
    {
        $saved = $rows->filter(fn ($row) => filled($row['line_1'] ?? null))->map(function ($row) use ($user, $companyCode) {
            $address = filled($row['id'] ?? null)
                ? $user->addresses()->whereKey($row['id'])->firstOrFail()
                : new Address(['created_by' => auth()->id(), 'status' => 1]);
            $address->fill([
                'employee_id' => (string) $user->id,
                'company_code' => $companyCode,
                'address_type' => $row['type'],
                'is_current_permanent_same' => ($row['type'] ?? null) === '1'
                    && (bool) ($row['is_current_permanent_same'] ?? false),
                'address_line_1' => $row['line_1'],
                'address_line_2' => $row['line_2'] ?? null,
                'city' => $row['city'],
                'district' => $row['district'] ?? null,
                'state' => $row['state'],
                'postal_code' => $row['postal_code'],
                'country' => $row['country'] ?? 'India',
                'latitude' => $row['latitude'] ?? null,
                'longitude' => $row['longitude'] ?? null,
                'updated_by' => auth()->id(),
            ])->save();
            return $address;
        });
        $primary = $saved->first();
        if ($primary && (int) $user->address_id !== (int) $primary->id) $user->update(['address_id' => $primary->id]);
        $savedIds = $saved->pluck('id')->filter()->all();
        $user->addresses()->when($savedIds, fn ($query) => $query->whereNotIn('id', $savedIds))->delete();
    }

    private function createAdditionalEducation(Request $request, UserProfile $user): void
    {
        foreach ($request->input('additional_education', []) as $index => $row) {
            if (! filled($row['level'] ?? null)) continue;

            $document = $request->file("additional_education.$index.document");
            $path = $document?->store("users/{$user->id}/education", 'public');
            $education = filled($row['id'] ?? null)
                ? $user->education()->whereKey($row['id'])->firstOrFail()
                : new Education(['user_id' => $user->id, 'status' => 'pending', 'created_by' => auth()->id()]);
            $education->fill([
                'education_level' => $row['level'],
                'degree_name' => $row['degree_name'] ?? null,
                'institution_name' => $row['institution_name'] ?? null,
                'university_name' => $row['university_name'] ?? null,
                'start_date' => $row['start_date'] ?? null,
                'end_date' => $row['end_date'] ?? null,
                'is_current' => (bool) ($row['is_current'] ?? false),
                'grade' => $row['grade'] ?? null,
                'updated_by' => auth()->id(),
            ]);
            if ($path) {
                if ($education->image_path) Storage::disk('public')->delete($education->image_path);
                $education->image_path = $path;
                $education->uploaded_at = now();
            }
            $education->save();
            if (! $user->education_id) $user->update(['education_id' => $education->id]);
        }
    }
}
