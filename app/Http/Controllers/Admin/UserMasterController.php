<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Address;
use App\Models\Banking;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Login;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDepartment;
use App\Models\UserDocument;
use App\Models\UserType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserMasterController extends Controller
{
    private const EMPLOYEE_USER_TYPE = 'USR6';

    public function locationOptions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['state', 'district', 'city', 'pincode'])],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:180'],
            'query' => ['nullable', 'string', 'max:100'],
        ]);
        $query = trim((string) ($data['query'] ?? ''));

        $options = match ($data['type']) {
            'state' => DB::table('tbl_india_districts')
                ->when($query !== '', fn ($builder) => $builder->where('state', 'like', "%{$query}%"))
                ->distinct()->orderBy('state')->limit(100)->pluck('state'),
            'district' => DB::table('tbl_india_districts')
                ->when(filled($data['state'] ?? null), fn ($builder) => $builder->where('state', $data['state']))
                ->when($query !== '', fn ($builder) => $builder->where('district_name', 'like', "%{$query}%"))
                ->distinct()->orderBy('district_name')->limit(100)->pluck('district_name'),
            'city' => DB::table('tbl_india_city_pincodes')
                ->when($query !== '', fn ($builder) => $builder->where('city', 'like', "%{$query}%"))
                ->distinct()->orderBy('city')->limit(100)->pluck('city'),
            'pincode' => DB::table('tbl_india_city_pincodes')
                ->when(filled($data['city'] ?? null), fn ($builder) => $builder->where('city', $data['city']))
                ->when($query !== '', fn ($builder) => $builder->where('pin_code', 'like', "%{$query}%"))
                ->distinct()->orderBy('pin_code')->limit(100)->pluck('pin_code'),
        };

        return response()->json($options->map(fn ($value) => ['value' => (string) $value, 'label' => (string) $value])->values());
    }

    public function locationCoordinates(Request $request): JsonResponse
    {
        $data = $request->validate([
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
        ]);

        $base = DB::table('tbl_usraddress')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('status', true);

        $address = null;
        if (filled($data['postal_code'] ?? null)) {
            $address = (clone $base)->where('postal_code', $data['postal_code'])->orderByDesc('is_primary')->latest('updated_at')->first();
        }
        if (! $address && filled($data['city'] ?? null)) {
            $address = (clone $base)
                ->where('city', $data['city'])
                ->when(filled($data['state'] ?? null), fn ($query) => $query->where('state', $data['state']))
                ->orderByDesc('is_primary')->latest('updated_at')->first();
        }
        if (! $address && filled($data['district'] ?? null)) {
            $address = (clone $base)
                ->where('district', $data['district'])
                ->when(filled($data['state'] ?? null), fn ($query) => $query->where('state', $data['state']))
                ->orderByDesc('is_primary')->latest('updated_at')->first();
        }

        if (! $address) {
            return response()->json(['message' => 'No saved map coordinates were found for this location.'], 404);
        }

        return response()->json([
            'latitude' => (string) $address->latitude,
            'longitude' => (string) $address->longitude,
            'google_maps_url' => $address->google_maps_url,
        ]);
    }

    public function sendContactOtp(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['email', 'phone'])],
            'contact_id' => ['nullable', 'integer'],
        ]);
        $channel = $data['channel'];
        $contact = $channel === 'email'
            ? $user->contacts()->whereNotNull('email')->where('status', true)->orderByDesc('is_primary')->first()
            : $user->contacts()->whereKey($data['contact_id'] ?? 0)->whereNotNull('contact_number')->where('status', true)->first();
        $destination = $channel === 'email' ? $contact?->email : $contact?->contact_number;

        if (blank($destination)) {
            return back()->withErrors(['otp' => ucfirst($channel).' is not available for this employee.']);
        }

        $contactKey = $contact?->id ?? 0;
        $rateKey = "user-contact-otp-rate:{$user->id}:{$channel}:{$contactKey}";
        if (Cache::has($rateKey)) {
            return back()->withErrors(['otp' => 'Please wait one minute before requesting another OTP.']);
        }

        $otp = (string) random_int(100000, 999999);
        Cache::put("user-contact-otp:{$user->id}:{$channel}:{$contactKey}", Hash::make($otp), now()->addMinutes(5));
        Cache::put($rateKey, true, now()->addMinute());

        if ($channel === 'email') {
            Mail::raw("Your APL Connect verification OTP is {$otp}. It expires in 5 minutes.", function ($message) use ($destination): void {
                $message->to($destination)->subject('APL Connect contact verification OTP');
            });
        } else {
            $webhook = config('services.sms_otp.webhook_url');
            if (blank($webhook)) {
                Cache::forget("user-contact-otp:{$user->id}:{$channel}:{$contactKey}");
                Cache::forget($rateKey);

                return back()->withErrors(['otp' => 'Phone OTP provider is not configured. Add SMS_OTP_WEBHOOK_URL and token.']);
            }
            Http::withToken((string) config('services.sms_otp.token'))->post($webhook, [
                'to' => $destination,
                'sender' => config('services.sms_otp.sender'),
                'message' => "Your APL Connect verification OTP is {$otp}. It expires in 5 minutes.",
            ])->throw();
        }

        return back()->with('success', 'OTP sent to the employee '.$channel.'.');
    }

    public function verifyContactOtp(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['email', 'phone'])],
            'otp' => ['required', 'digits:6'],
            'contact_id' => ['nullable', 'integer'],
        ]);
        $contact = $data['channel'] === 'email'
            ? $user->contacts()->whereNotNull('email')->where('status', true)->orderByDesc('is_primary')->first()
            : $user->contacts()->whereKey($data['contact_id'] ?? 0)->whereNotNull('contact_number')->where('status', true)->first();
        abort_unless($contact, 422, 'Contact record was not found.');
        $key = "user-contact-otp:{$user->id}:{$data['channel']}:{$contact->id}";
        $hash = Cache::get($key);
        if (! $hash || ! Hash::check($data['otp'], $hash)) {
            return back()->withErrors(['otp' => 'The OTP is invalid or has expired.']);
        }

        $contact->update([
            'is_verified' => true,
            $data['channel'] === 'email' ? 'email_verified_at' : 'phone_verified_at' => now(),
        ]);
        Cache::forget($key);

        return back()->with('success', ucfirst($data['channel']).' verified successfully.');
    }

    public function index(Request $request): Response
    {
        $pageSizeOptions = config('datatable.page_size_options');
        $perPage = (int) $request->integer('per_page', config('datatable.per_page'));
        if (! in_array($perPage, $pageSizeOptions, true)) {
            $perPage = (int) config('datatable.per_page');
        }

        $search = trim((string) $request->string('search'));
        $sort = (string) $request->string('sort', 'created_at');
        $direction = $request->string('direction')->lower()->value() === 'asc' ? 'asc' : 'desc';
        $sortableColumns = ['sno' => 'tbl_users.id', 'name' => 'tbl_users.first_name', 'type' => 'tbl_usertype.name', 'status' => 'tbl_users.status', 'created_at' => 'tbl_users.created_at'];
        if (! array_key_exists($sort, $sortableColumns)) {
            $sort = 'created_at';
        }

        $users = User::query()
            ->leftJoin('tbl_usertype', 'tbl_usertype.uid', '=', 'tbl_users.user_type')
            ->select('tbl_users.*')
            ->where('tbl_users.user_type', 'USR6')
            ->with([
                'userType:id,uid,name', 'primaryEmail:id,user_id,email,is_verified,email_verified_at',
                'login:id,user_id,username,last_login_at,status',
                'addresses' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('id'),
                'contacts' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('id'),
                'educations' => fn ($query) => $query->orderBy('id'),
                'banking:id,user_id,bank_name,account_number,account_type,branch_name,ifsc_code,is_primary,status',
                'documents' => fn ($query) => $query->whereIn('document_type', ['aadhaar', 'pan']),
                'departmentAssignment:id,user_id,company_id,sitting_location,department_id,designation_id,role_id,status',
                'departmentAssignment.company:id,company_name',
                'departmentAssignment.role:id,name',
                'primaryPhoto:id,user_id,file_path,alt_text',
                'roles:id,name',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('tbl_users.unique_id', 'like', "%{$search}%")
                        ->orWhereRaw("CONCAT_WS(' ', tbl_users.first_name, tbl_users.middle_name, tbl_users.last_name) LIKE ?", ["%{$search}%"])
                        ->orWhereHas('primaryEmail', fn ($emailQuery) => $emailQuery->where('email', 'like', "%{$search}%"))
                        ->orWhere('tbl_usertype.name', 'like', "%{$search}%")
                        ->orWhereHas('contacts', fn ($contactQuery) => $contactQuery->where('contact_number', 'like', "%{$search}%"));
                });
            })
            ->orderBy($sortableColumns[$sort], $direction)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('admin/user-master/index', [
            'users' => $users,
            'userTypes' => UserType::query()->where('status', true)->orderBy('name')->get(['uid', 'name']),
            'departments' => collect([
                ['id' => 10, 'department_name' => 'MD Office'],
                ['id' => 20, 'department_name' => 'Sales'],
                ['id' => 30, 'department_name' => 'Marketing'],
            ])->concat(Department::query()->where('status', true)->orderBy('department_name')->get(['id', 'department_name']))
                ->unique('id')->sortBy('department_name')->values(),
            'companies' => Company::query()->where('status', true)->where('record_type', 1)->orderBy('company_name')->get(['company_code', 'company_name']),
            'sittingLocations' => Company::query()->where('status', true)->whereIn('record_type', [2, 3])->orderBy('record_type')->orderBy('company_name')->get(['company_code', 'company_name', 'parent_company_id', 'record_type']),
            'designations' => Designation::query()->where('status', true)->orderBy('designation_name')
                ->get(['des_id as id', 'designation_name', 'department_id']),
            'roles' => Role::query()->where('status', true)->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
            'filters' => ['search' => $search, 'per_page' => $perPage, 'sort' => $sort, 'direction' => $direction],
            'pageSizeOptions' => $pageSizeOptions,
            'stats' => [
                'total' => User::query()->where('user_type', 'USR6')->count(),
                'active' => User::query()->where('user_type', 'USR6')->where('status', true)->count(),
            ],
            'canManage' => auth()->user()?->can('users.manage') ?? false,
        ]);
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('users.manage'), 403);
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:tbl_users,id'],
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])],
        ]);

        if ($validated['action'] === 'delete' && in_array((int) auth()->id(), $validated['ids'], true)) {
            throw ValidationException::withMessages(['bulk_action' => 'Your own account cannot be deleted.']);
        }

        DB::transaction(function () use ($validated): void {
            $users = User::query()->whereIn('id', $validated['ids'])->get();
            foreach ($users as $user) {
                if ($validated['action'] === 'delete') {
                    $user->delete();
                } else {
                    $status = $validated['action'] === 'activate';
                    $user->update(['status' => $status]);
                    $user->login()->update(['status' => $status]);
                    $user->primaryEmail()->update(['status' => $status]);
                }
            }
        });

        return back()->with('success', 'Bulk action completed successfully.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $status = $request->validate(['active' => ['required', 'boolean']])['active'];

        DB::transaction(function () use ($user, $status): void {
            $user->update(['status' => $status]);
            $user->login()->update(['status' => $status]);
            $user->primaryEmail()->update(['status' => $status]);
        });

        return back()->with('success', 'Employee status updated.');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = $request->validated();
            $data['status'] = true;
            $user = User::create([
                'user_type' => self::EMPLOYEE_USER_TYPE,
                'unique_id' => ($data['unique_id'] ?? null) ?: $this->nextUniqueId(),
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? '',
                'last_name' => $data['last_name'],
                'gender' => $data['gender'] ?? null,
                'marital_status' => $data['marital_status'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'anniversary_date' => $data['anniversary_date'] ?? null,
                'status' => $data['status'],
            ]);
            $this->saveIdentityDocuments($user, $data, $request);
            $this->saveDepartment($user, $data);
            Login::create(['usr_type' => self::EMPLOYEE_USER_TYPE, 'user_id' => $user->unique_id, 'username' => trim($data['username']), 'password' => $data['password'], 'status' => $data['status']]);
            $this->saveContact($user, $data);
            $this->saveAddress($user, $data);
            $this->saveEducation($user, $data);
            $this->saveBanking($user, $data);
            $this->syncReferenceIds($user);
            $user->syncRoles([$data['role']]);
        });

        return back()->with('success', 'User created successfully.');
    }

    public function importExcel(Request $request): RedirectResponse
    {
        $validated = $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240']]);
        $rows = $this->spreadsheetRows($validated['file']);
        if ($rows === []) {
            return back()->withErrors(['file' => 'The spreadsheet has no data rows.']);
        }

        $defaultRole = Role::query()->where('status', true)->where('guard_name', 'web')->where('name', 'Viewer')->value('name')
            ?? Role::query()->where('status', true)->where('guard_name', 'web')->value('name');
        if (! $defaultRole) {
            return back()->withErrors(['file' => 'An active role is not configured.']);
        }

        $created = 0;
        $skipped = 0;
        $failures = [];
        foreach ($rows as $rowNumber => $row) {
            $email = strtolower(trim((string) ($row['email'] ?? $row['email_login'] ?? '')));
            $firstName = trim((string) ($row['first_name'] ?? ''));
            $lastName = trim((string) ($row['last_name'] ?? ''));
            $uniqueId = trim((string) ($row['employee_id'] ?? $row['unique_id'] ?? ''));
            if (! $email || ! $firstName || ! $lastName || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failures[] = 'Row '.($rowNumber + 2).': first name, last name and a valid email are required.';

                continue;
            }
            $duplicate = ($uniqueId !== '' && User::query()->where('unique_id', $uniqueId)->exists())
                || Login::query()->where('username', $email)->exists();
            if ($duplicate) {
                $skipped++;

                continue;
            }

            try {
                DB::transaction(function () use ($row, $email, $firstName, $lastName, $uniqueId, $defaultRole): void {
                    $user = User::create([
                        'user_type' => self::EMPLOYEE_USER_TYPE, 'unique_id' => $uniqueId ?: $this->nextUniqueId(),
                        'first_name' => $firstName, 'last_name' => $lastName,
                        'gender' => in_array(strtolower((string) ($row['gender'] ?? '')), ['male', 'female', 'other'], true) ? strtolower((string) $row['gender']) : null,
                        'status' => true,
                    ]);
                    $this->saveDepartment($user, [
                        'company' => $row['company'] ?? null,
                        'sitting_location' => $row['sitting_location'] ?? null,
                        'role' => $row['role'] ?? $defaultRole,
                    ]);
                    $password = (string) ($row['password'] ?? 'Password@123');
                    Login::create(['usr_type' => self::EMPLOYEE_USER_TYPE, 'user_id' => $user->unique_id, 'username' => $email, 'password' => $password, 'status' => true]);
                    $this->saveContact($user, ['email' => $email, 'status' => true, 'contacts' => [['contact_type' => 'mobile', 'contact_value' => $row['mobile'] ?? null, 'is_primary' => true]]]);
                    $this->syncReferenceIds($user);
                    $user->syncRoles([$row['role'] ?? $defaultRole]);
                });
                $created++;
            } catch (\Throwable $exception) {
                $failures[] = 'Row '.($rowNumber + 2).': '.$exception->getMessage();
            }
        }

        $message = "{$created} users imported; {$skipped} duplicates skipped.";
        if ($failures !== []) {
            $message .= ' '.count($failures).' rows failed: '.implode(' ', array_slice($failures, 0, 3));
        }

        return back()->with('success', $message);
    }

    public function downloadImportTemplate(): BinaryFileResponse
    {
        $headers = ['first_name', 'last_name', 'email', 'employee_id', 'password', 'mobile', 'gender', 'company', 'sitting_location', 'role'];
        $example = ['Rajesh', 'Khanna', 'rajesh.khanna@example.com', 'EMP001', 'Password@123', '9876543210', 'male', 'APL Apollo', 'Noida', 'Viewer'];
        $rows = [$headers, $example];
        $sheetRows = '';
        foreach ($rows as $rowIndex => $row) {
            $cells = '';
            foreach ($row as $columnIndex => $value) {
                $column = '';
                for ($number = $columnIndex + 1; $number > 0; $number = intdiv($number - 1, 26)) {
                    $column = chr(65 + (($number - 1) % 26)).$column;
                }
                $style = $rowIndex === 0 ? ' s="1"' : '';
                $cells .= '<c r="'.$column.($rowIndex + 1).'" t="inlineStr"'.$style.'><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
            }
            $sheetRows .= '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
        }

        $path = storage_path('app/user-import-template-'.Str::uuid().'.xlsx');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Users" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0891B2"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf xfId="0"/><xf xfId="0" fontId="1" fillId="2" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="10" width="22" customWidth="1"/></cols><sheetData>'.$sheetRows.'</sheetData><autoFilter ref="A1:J2"/></worksheet>');
        $zip->close();

        return response()->download($path, 'user-import-template.xlsx')->deleteFileAfterSend(true);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $data = $request->validated();
            $data['status'] = $user->status;
            $originalUniqueId = $user->unique_id;
            $user->update([
                'user_type' => self::EMPLOYEE_USER_TYPE,
                'unique_id' => ($data['unique_id'] ?? null) ?: $user->unique_id,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? '',
                'last_name' => $data['last_name'],
                'gender' => $data['gender'] ?? null,
                'marital_status' => $data['marital_status'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'anniversary_date' => $data['anniversary_date'] ?? null,
            ]);
            if ($originalUniqueId !== $user->unique_id) {
                DB::table('tbl_usrcontact')->where('user_id', $originalUniqueId)->update(['user_id' => $user->unique_id]);
                DB::table('tbl_usrdept')->where('user_id', $originalUniqueId)->update(['user_id' => $user->unique_id]);
                DB::table('tbl_usrdocument')->where('user_id', $originalUniqueId)->update(['user_id' => $user->unique_id]);
                DB::table('tbl_usrlogin')->where('user_id', $originalUniqueId)->update(['user_id' => $user->unique_id]);
                DB::table('tbl_usrbanking')->where('user_id', $originalUniqueId)->update(['user_id' => $user->unique_id]);
                $user->unsetRelation('contacts')->unsetRelation('primaryEmail')->unsetRelation('departmentAssignment')->unsetRelation('login');
            }
            $this->saveDepartment($user, $data);
            $this->saveIdentityDocuments($user, $data, $request);

            $username = trim((string) ($data['username'] ?? ''));
            if ($username !== '') {
                $loginData = ['usr_type' => self::EMPLOYEE_USER_TYPE, 'username' => $username, 'status' => $data['status']];
                if (! empty($data['password'])) {
                    $loginData['password'] = $data['password'];
                } elseif (! $user->login) {
                    $loginData['password'] = Str::password(24);
                }
                $user->login()->updateOrCreate(['user_id' => $user->unique_id], $loginData);
            }
            $this->saveContact($user, $data);
            $this->saveAddress($user, $data);
            $this->saveEducation($user, $data);
            $this->saveBanking($user, $data);
            $this->syncReferenceIds($user);
            $user->syncRoles([$data['role']]);
        });

        return back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if(auth()->id() === $user->id, 422, 'You cannot delete your own account.');
        $user->delete();

        return back()->with('success', 'User deleted successfully.');
    }

    /** @param array<string, mixed> $data */
    private function saveIdentityDocuments(User $user, array $data, Request $request): void
    {
        foreach (['aadhaar', 'pan'] as $type) {
            $number = $data[$type.'_number'] ?? null;
            $file = $request->file($type.'_image');
            $document = $user->documents()->where('document_type', $type)->first();

            if (blank($number) && ! $file && ! $document) {
                continue;
            }

            $values = [
                'user_type' => self::EMPLOYEE_USER_TYPE,
                'document_number' => $type === 'pan' && filled($number) ? Str::upper($number) : $number,
                'document_name' => strtoupper($type),
                'status' => true,
            ];

            if ($file) {
                $values += [
                    'file_path' => $file->store('employee-identity/'.$type, 'public'),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ];
            }

            UserDocument::query()->updateOrCreate(
                ['user_id' => $user->unique_id, 'document_type' => $type],
                $values,
            );
        }

        $user->unsetRelation('documents');
    }

    /** @param array<string, mixed> $data */
    private function saveContact(User $user, array $data): void
    {
        $contacts = $data['contacts'] ?? [[
            'contact_type' => $data['contact_type'] ?? 'mobile',
            'contact_value' => $data['contact_value'] ?? null,
            'is_primary' => true,
        ]];

        if ($contacts === []) {
            $contacts = [['contact_type' => 'mobile', 'contact_value' => null, 'is_primary' => true]];
        }

        $primaryIndex = collect($contacts)->search(fn (array $contact): bool => (bool) ($contact['is_primary'] ?? false));
        $primaryIndex = $primaryIndex === false ? 0 : $primaryIndex;

        $user->contacts()->update(['is_primary' => false, 'email' => null]);
        $keptContactIds = [];

        foreach ($contacts as $index => $contactData) {
            $contact = isset($contactData['id'])
                ? $user->contacts()->whereKey($contactData['id'])->first()
                : null;

            $contactValue = filled($contactData['contact_value'] ?? null) ? $contactData['contact_value'] : null;
            $isEmailContact = ($contactData['contact_type'] ?? '') === 'email' || ($contactValue && filter_var($contactValue, FILTER_VALIDATE_EMAIL));

            $values = [
                'usr_type' => self::EMPLOYEE_USER_TYPE,
                'contact_type' => $isEmailContact ? 'email' : ($contactData['contact_type'] ?? 'mobile'),
                'email' => $isEmailContact ? $contactValue : ($index === $primaryIndex ? $data['email'] : null),
                'contact_number' => $isEmailContact ? null : $contactValue,
                'is_primary' => $index === $primaryIndex,
                'status' => $data['status'],
            ];

            if ($contact && $values['email'] !== $contact->email) {
                $values['is_verified'] = false;
                $values['email_verified_at'] = null;
            }
            if ($contact && $values['contact_number'] !== $contact->contact_number) {
                $values['is_verified'] = false;
                $values['phone_verified_at'] = null;
            }

            if ($contact) {
                $contact->update($values);
            } else {
                $contact = $user->contacts()->create($values);
            }
            $keptContactIds[] = $contact->id;
        }
        $user->contacts()->whereNotIn('id', $keptContactIds)->delete();
    }

    /** @param array<string, mixed> $data */
    private function saveAddress(User $user, array $data): void
    {
        if (($data['permanent_same_as_local'] ?? false) === true) {
            foreach (['address_line_1', 'address_line_2', 'city', 'state', 'district', 'postal_code', 'country', 'latitude', 'longitude'] as $field) {
                $data['permanent_'.$field] = $data[$field] ?? null;
            }
        }

        foreach (['local' => '', 'permanent' => 'permanent_'] as $type => $prefix) {
            $addressLine = $data[$prefix.'address_line_1'] ?? null;
            if (blank($addressLine)) {
                $user->addresses()->where('address_type', $type)->delete();

                continue;
            }

            $latitude = filled($data[$prefix.'latitude'] ?? null) ? (string) $data[$prefix.'latitude'] : null;
            $longitude = filled($data[$prefix.'longitude'] ?? null) ? (string) $data[$prefix.'longitude'] : null;

            Address::query()->updateOrCreate(
                ['user_id' => (string) $user->unique_id, 'address_type' => $type],
                [
                    'user_type' => self::EMPLOYEE_USER_TYPE,
                    'address_line_1' => $addressLine,
                    'address_line_2' => $data[$prefix.'address_line_2'] ?? null,
                    'city' => $data[$prefix.'city'] ?? null,
                    'state' => $data[$prefix.'state'] ?? null,
                    'district' => $data[$prefix.'district'] ?? null,
                    'postal_code' => $data[$prefix.'postal_code'] ?? null,
                    'country' => $data[$prefix.'country'] ?? null,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'google_maps_url' => $latitude && $longitude
                        ? "https://www.google.com/maps?q={$latitude},{$longitude}"
                        : null,
                    'is_primary' => $type === 'local',
                    'status' => $data['status'],
                ],
            );
        }
    }

    private function nextUniqueId(): string
    {
        do {
            $uniqueId = 'USR'.Str::upper(Str::random(7));
        } while (User::query()->where('unique_id', $uniqueId)->exists());

        return $uniqueId;
    }

    /** @param array<string, mixed> $data */
    private function saveDepartment(User $user, array $data): void
    {
        $company = filled($data['company'] ?? null)
            ? Company::query()
                ->where('company_code', $data['company'])
                ->orWhere('company_name', $data['company'])
                ->first()
            : null;
        $roleId = filled($data['role'] ?? null)
            ? Role::query()->where('name', $data['role'])->where('guard_name', 'web')->value('id')
            : null;
        $hasEmployment = $company
            || filled($data['sitting_location'] ?? null)
            || filled($data['department_id'] ?? null)
            || filled($data['designation_id'] ?? null)
            || $roleId !== null;

        UserDepartment::query()->updateOrCreate(
            ['user_id' => (string) $user->unique_id],
            [
                'user_type' => self::EMPLOYEE_USER_TYPE,
                'company_id' => $company?->id,
                'sitting_location' => filled($data['sitting_location'] ?? null) ? $data['sitting_location'] : null,
                'department_id' => filled($data['department_id'] ?? null) ? (int) $data['department_id'] : null,
                'designation_id' => filled($data['designation_id'] ?? null) ? (int) $data['designation_id'] : null,
                'role_id' => $roleId,
                'status' => $hasEmployment,
            ],
        );

        $user->unsetRelation('departmentAssignment');
    }

    /** @param array<string, mixed> $data */
    private function saveEducation(User $user, array $data): void
    {
        $keptIds = [];
        foreach ($data['educations'] ?? [] as $educationData) {
            $hasContent = collect($educationData)->except(['id', 'currently_studying', 'document'])->filter(fn ($value) => filled($value))->isNotEmpty();
            if (! $hasContent && empty($educationData['document'])) {
                continue;
            }

            $education = isset($educationData['id']) ? $user->educations()->whereKey($educationData['id'])->first() : null;
            $values = collect($educationData)->only(['education_level', 'degree_name', 'institution_name', 'university_name', 'start_date', 'end_date', 'grade', 'currently_studying'])->all();
            $values['start_date'] = $this->databaseDate($educationData['start_date'] ?? null);
            $values['end_date'] = $this->databaseDate($educationData['end_date'] ?? null);
            $values['user_type'] = self::EMPLOYEE_USER_TYPE;
            $values['board_or_university'] = $educationData['university_name'] ?? null;
            $values['passing_year'] = filled($values['end_date'])
                ? (int) substr((string) $values['end_date'], 0, 4)
                : null;
            $values['status'] = true;
            if (($educationData['currently_studying'] ?? false) === true) {
                $values['end_date'] = null;
            }
            if (! empty($educationData['document'])) {
                $values['document_path'] = $educationData['document']->store('employee-education', 'public');
            }
            $education = $education ? tap($education)->update($values) : $user->educations()->create($values);
            $keptIds[] = $education->id;
        }
        $user->educations()->when($keptIds !== [], fn ($query) => $query->whereNotIn('id', $keptIds))->when($keptIds === [], fn ($query) => $query)->delete();
    }

    private function databaseDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        foreach (['d-m-Y', 'd/m/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function syncReferenceIds(User $user): void
    {
        $user->forceFill([
            'contact_id' => $user->contacts()->orderByDesc('is_primary')->orderBy('id')->value('id'),
            'address_id' => $user->addresses()->orderByDesc('is_primary')->orderBy('id')->value('id'),
            'document_id' => $user->documents()->orderByRaw("document_type = 'aadhaar' desc")->orderBy('id')->value('id'),
            'education_id' => $user->educations()->orderBy('id')->value('id'),
            'banking_id' => DB::table('tbl_usrbanking')->where('user_id', $user->unique_id)->orderBy('id')->value('id'),
        ])->save();
    }

    /** @param array<string, mixed> $data */
    private function saveBanking(User $user, array $data): void
    {
        $hasBanking = collect($data)->only([
            'bank_name', 'account_number', 'account_type', 'branch_name', 'ifsc_code',
        ])->filter(fn ($value) => filled($value))->isNotEmpty();

        if (! $hasBanking) {
            return;
        }

        Banking::query()->updateOrCreate(
            ['user_id' => $user->unique_id, 'is_primary' => true],
            [
                'bank_name' => $data['bank_name'],
                'account_number' => $data['account_number'],
                'account_type' => $data['account_type'] ?? null,
                'branch_name' => $data['branch_name'] ?? null,
                'ifsc_code' => filled($data['ifsc_code'] ?? null) ? Str::upper($data['ifsc_code']) : null,
                'status' => true,
            ],
        );
    }

    /** @return array<int, array<string, string>> */
    private function spreadsheetRows(UploadedFile $file): array
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'xlsx') {
            $handle = fopen($file->getRealPath(), 'rb');
            $raw = [];
            while (($row = fgetcsv($handle)) !== false) {
                $raw[] = $row;
            } fclose($handle);
        } else {
            $zip = new \ZipArchive;
            if ($zip->open($file->getRealPath()) !== true) {
                throw ValidationException::withMessages(['file' => 'Unable to read the Excel file.']);
            }
            $shared = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $strings = simplexml_load_string($xml);
                foreach ($strings->si as $item) {
                    $shared[] = (string) ($item->t ?? $item->r->t);
                }
            }
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            if ($sheetXml === false) {
                throw ValidationException::withMessages(['file' => 'The first worksheet could not be read.']);
            }
            $sheet = simplexml_load_string($sheetXml);
            $raw = [];
            foreach ($sheet->sheetData->row as $row) {
                $values = [];
                foreach ($row->c as $cell) {
                    preg_match('/([A-Z]+)/', (string) $cell['r'], $match);
                    $column = 0;
                    foreach (str_split($match[1] ?? 'A') as $letter) {
                        $column = $column * 26 + ord($letter) - 64;
                    }
                    $value = (string) $cell->v;
                    if ((string) $cell['t'] === 's') {
                        $value = $shared[(int) $value] ?? '';
                    } elseif ((string) $cell['t'] === 'inlineStr') {
                        $value = (string) $cell->is->t;
                    }
                    $values[$column - 1] = $value;
                }
                if ($values !== []) {
                    ksort($values);
                    $raw[] = array_values($values);
                }
            }
        }
        if (count($raw) < 2) {
            return [];
        }
        $headers = array_map(fn ($header) => Str::of((string) $header)->trim()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value(), array_shift($raw));

        return array_values(array_map(function ($row) use ($headers) {
            $row = array_pad($row, count($headers), '');

            return array_combine($headers, array_slice($row, 0, count($headers)));
        }, $raw));
    }
}
