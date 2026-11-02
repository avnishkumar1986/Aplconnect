<?php

namespace Modules\SalesDistribution\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\SalesDistribution\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use SplFileObject;

class CustomerController extends Controller
{
    private const USER_TYPE = 'USR4';

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $pageSizeOptions = config('datatable.page_size_options');
        $perPage = (int) $request->integer('per_page', config('datatable.per_page'));
        if (! in_array($perPage, $pageSizeOptions, true)) {
            $perPage = (int) config('datatable.per_page');
        }
        $salesColumns = ['s.record_creation_date', 's.plant_code', 's.gstin', 's.ecc_no', 's.cst_no', 's.lst_no', 's.pan',
            's.sales_organization', 's.sales_district', 's.sales_group', 's.sales_office', 's.payment_terms',
            's.credit_limit', 's.outstanding_amount', 's.credit_utilization_percentage'];
        foreach (range(1, 6) as $level) {
            $salesColumns[] = "s.employee_level_{$level}";
        }
        foreach (range(1, 12) as $month) {
            $suffix = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
            $salesColumns[] = "s.month_{$suffix}_target";
            $salesColumns[] = "s.month_{$suffix}_actual";
        }
        foreach (range(1, 4) as $quarter) {
            $salesColumns[] = "s.quarter_{$quarter}_target";
            $salesColumns[] = "s.quarter_{$quarter}_actual";
        }

        $customers = DB::table('tbl_users as u')
            ->leftJoin('tbl_usrcontact as c', fn ($join) => $join->on('c.user_id', '=', 'u.unique_id')->where('c.is_primary', true))
            ->leftJoin('tbl_usraddress as a', fn ($join) => $join->on('a.user_id', '=', 'u.unique_id')->where('a.is_primary', true))
            ->leftJoin('tbl_customer_sales as s', 's.user_id', '=', 'u.unique_id')
            ->where('u.user_type', self::USER_TYPE)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('u.unique_id', 'like', "%{$search}%")
                ->orWhere('u.name', 'like', "%{$search}%")
                ->orWhere('c.email', 'like', "%{$search}%")
                ->orWhere('c.contact_number', 'like', "%{$search}%")
                ->orWhere('s.gstin', 'like', "%{$search}%")))
            ->select(array_merge(['u.id', 'u.unique_id', 'u.name', 'u.first_name', 'u.middle_name', 'u.last_name', 'u.status', 'u.created_at',
                'c.email', 'c.contact_number', 'a.address_line_1', 'a.city', 'a.state', 'a.district', 'a.postal_code',
            ], $salesColumns))
            ->orderByDesc('u.updated_at')->paginate($perPage)->withQueryString();

        $customerNumbers = $customers->getCollection()->pluck('unique_id');
        $contacts = DB::table('tbl_usrcontact')->whereIn('user_id', $customerNumbers)->where('is_primary', false)->get()->groupBy('user_id');
        $addresses = DB::table('tbl_usraddress')->whereIn('user_id', $customerNumbers)->where('is_primary', false)->get()->groupBy('user_id');
        $customerKeys = $customerNumbers->map(fn ($number): int => (int) $number)->unique()->values()->all();
        $customerKey = fn ($number): string => (string) (int) $number;

        $pendingSales = collect();
        $dailySales = collect();
        $salesSummaries = collect();
        if ($customerKeys !== []) {
            $latestPendingSales = DB::table('tbl_pending_sales')
                ->selectRaw('MAX(id) as id')
                ->whereIn(DB::raw('CAST(customer_number AS UNSIGNED)'), $customerKeys)
                ->groupByRaw('CAST(customer_number AS UNSIGNED)');
            $pendingSales = DB::table('tbl_pending_sales as pending')
                ->joinSub($latestPendingSales, 'latest', fn ($join) => $join->on('latest.id', '=', 'pending.id'))
                ->select('pending.*')
                ->get()
                ->keyBy(fn ($row): string => $customerKey($row->customer_number));

            $latestDailySales = DB::table('tbl_dailysale')
                ->selectRaw('MAX(id) as id')
                ->whereIn(DB::raw('CAST(customer_number AS UNSIGNED)'), $customerKeys)
                ->groupByRaw('CAST(customer_number AS UNSIGNED)');
            $dailySales = DB::table('tbl_dailysale as sale')
                ->joinSub($latestDailySales, 'latest', fn ($join) => $join->on('latest.id', '=', 'sale.id'))
                ->select('sale.*')
                ->get()
                ->keyBy(fn ($row): string => $customerKey($row->customer_number));

            $salesSummaries = DB::table('tbl_customer_sales_summary')
                ->whereIn(DB::raw('CAST(customer_number AS UNSIGNED)'), $customerKeys)
                ->get()
                ->keyBy(fn ($row): string => $customerKey($row->customer_number));
        }

        $customers->getCollection()->transform(function ($customer) use ($contacts, $addresses, $pendingSales, $dailySales, $salesSummaries, $customerKey) {
            $key = $customerKey($customer->unique_id);
            $customer->additional_contacts = ($contacts[$customer->unique_id] ?? collect())->values();
            $customer->additional_addresses = ($addresses[$customer->unique_id] ?? collect())->values();
            $customer->pending_sale = $pendingSales->get($key);
            $customer->daily_sale = $dailySales->get($key);
            $customer->sales_summary = $salesSummaries->get($key);

            return $customer;
        });

        return Inertia::render('modules/salesdistribution/customers/index', [
            'customers' => $customers,
            'employees' => DB::table('tbl_users')
                ->where('user_type', 'USR6')
                ->where('status', true)
                ->orderBy('name')
                ->get(['unique_id', 'name']),
            'plants' => DB::table('tbl_company')
                ->where('record_type', 2)
                ->where('status', true)
                ->orderBy('company_name')
                ->get(['company_code', 'company_name', 'parent_company_id']),
            'filters' => ['search' => $search, 'per_page' => $perPage],
            'stats' => [
                'total' => DB::table('tbl_users')->where('user_type', self::USER_TYPE)->count(),
                'active' => DB::table('tbl_users')->where('user_type', self::USER_TYPE)->where('status', true)->count(),
            ],
            'canManage' => $request->user()?->can('sales_distribution.customers.manage') ?? false,
            'openCreateForm' => $request->boolean('create'),
            'initialFormStep' => match ((string) $request->query('step')) {
                'pending-sales' => 6,
                'daily-sales' => 7,
                'customer-sales-summary' => 8,
                default => 0,
            },
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->save($data);

        return back()->with('success', 'Customer created successfully.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $file = new SplFileObject($request->file('file')->getRealPath());
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $headers = $file->fgetcsv();
        if (! is_array($headers)) {
            return back()->withErrors(['file' => 'The CSV file does not contain a header row.']);
        }

        $headers = array_map(fn ($header) => $this->normalizeImportHeader((string) $header), $headers);
        foreach (['customercode', 'customername'] as $requiredHeader) {
            if (! in_array($requiredHeader, $headers, true)) {
                return back()->withErrors(['file' => 'Required columns are missing: customer_code and customer_name.']);
            }
        }

        $created = $updated = $skipped = 0;
        $batch = [];
        $rowNumber = 0;
        foreach ($file as $values) {
            if ($rowNumber++ === 0) {
                continue;
            }
            if (! is_array($values) || count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $values = array_pad($values, count($headers), null);
            $batch[] = array_combine($headers, array_slice($values, 0, count($headers)));
            if (count($batch) >= 500) {
                [$batchCreated, $batchUpdated, $batchSkipped] = $this->importCustomerBatch($batch, $request->user()?->id);
                $created += $batchCreated;
                $updated += $batchUpdated;
                $skipped += $batchSkipped;
                $batch = [];
            }
        }
        if ($batch !== []) {
            [$batchCreated, $batchUpdated, $batchSkipped] = $this->importCustomerBatch($batch, $request->user()?->id);
            $created += $batchCreated;
            $updated += $batchUpdated;
            $skipped += $batchSkipped;
        }

        return back()->with('success', "Customer import completed: {$created} created, {$updated} updated, {$skipped} skipped.");
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($customer->user_type === self::USER_TYPE, 404);
        $data = $this->validated($request, $customer);
        $this->save($data, $customer);

        return back()->with('success', 'Customer updated successfully.');
    }

    public function toggleStatus(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($customer->user_type === self::USER_TYPE, 404);
        $status = $request->validate(['active' => ['required', 'boolean']])['active'];
        DB::transaction(function () use ($customer, $status): void {
            $customer->update(['status' => $status]);
            foreach (['tbl_usrcontact', 'tbl_usraddress', 'tbl_customer_sales'] as $table) {
                DB::table($table)->where('user_id', $customer->unique_id)->update(['status' => $status, 'updated_at' => now()]);
            }
        });

        return back()->with('success', 'Customer status updated.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', Rule::exists('tbl_users', 'id')->where('user_type', self::USER_TYPE)],
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])],
        ]);

        DB::transaction(function () use ($data): void {
            $customers = Customer::query()->whereIn('id', $data['ids'])->get();
            foreach ($customers as $customer) {
                if ($data['action'] === 'delete') {
                    foreach (['tbl_usrcontact', 'tbl_usraddress', 'tbl_customer_sales'] as $table) {
                        DB::table($table)->where('user_id', $customer->unique_id)->delete();
                    }
                    $customer->delete();

                    continue;
                }

                $status = $data['action'] === 'activate';
                $customer->update(['status' => $status]);
                foreach (['tbl_usrcontact', 'tbl_usraddress', 'tbl_customer_sales'] as $table) {
                    DB::table($table)->where('user_id', $customer->unique_id)->update(['status' => $status, 'updated_at' => now()]);
                }
            }
        });

        return back()->with('success', 'Customer bulk action completed.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        abort_unless($customer->user_type === self::USER_TYPE, 404);
        DB::transaction(function () use ($customer): void {
            foreach (['tbl_usrcontact', 'tbl_usraddress', 'tbl_customer_sales'] as $table) {
                DB::table($table)->where('user_id', $customer->unique_id)->delete();
            }
            $customer->delete();
        });

        return back()->with('success', 'Customer deleted successfully.');
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        $rules = [
            'customer_number' => ['required', 'string', 'max:50', Rule::unique('tbl_users', 'unique_id')->ignore($customer?->id)],
            'first_name' => ['required', 'string', 'max:100'], 'middle_name' => ['nullable', 'string', 'max:100'], 'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'], 'mobile_number' => ['nullable', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:255'], 'city' => ['nullable', 'string', 'max:100'], 'state' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'], 'postal_code' => ['nullable', 'string', 'max:20'],
            'gstin' => ['nullable', 'string', 'max:20'], 'pan' => ['nullable', 'string', 'max:20'],
            'record_creation_date' => ['nullable', 'date'],
            'plant_code' => ['nullable', 'string', 'max:20', Rule::exists('tbl_company', 'company_code')->where(fn ($query) => $query->where('record_type', 2)->where('status', true))],
            'ecc_no' => ['nullable', 'string', 'max:50'], 'cst_no' => ['nullable', 'string', 'max:50'], 'lst_no' => ['nullable', 'string', 'max:50'],
            'sales_organization' => ['nullable', 'string', 'max:20'], 'sales_district' => ['nullable', 'string', 'max:20'],
            'sales_group' => ['nullable', 'string', 'max:20'], 'sales_office' => ['nullable', 'string', 'max:20'], 'payment_terms' => ['nullable', 'string', 'max:100'],
            'credit_limit' => ['nullable', 'numeric'], 'outstanding_amount' => ['nullable', 'numeric'], 'credit_utilization_percentage' => ['nullable', 'numeric'],
            'status' => ['required', 'boolean'],
            'employee_level_1' => ['nullable', 'string', 'max:50', Rule::exists('tbl_users', 'unique_id')->where('user_type', 'USR6')],
            'employee_level_2' => ['nullable', 'string', 'max:50', Rule::exists('tbl_users', 'unique_id')->where('user_type', 'USR6')],
            'employee_level_3' => ['nullable', 'string', 'max:50', Rule::exists('tbl_users', 'unique_id')->where('user_type', 'USR6')],
            'employee_level_4' => ['nullable', 'string', 'max:50', Rule::exists('tbl_users', 'unique_id')->where('user_type', 'USR6')],
            'employee_level_5' => ['nullable', 'string', 'max:50', Rule::exists('tbl_users', 'unique_id')->where('user_type', 'USR6')],
            'employee_level_6' => ['nullable', 'string', 'max:50', Rule::exists('tbl_users', 'unique_id')->where('user_type', 'USR6')],
            'additional_contacts' => ['sometimes', 'array'],
            'additional_contacts.*.contact_type' => ['required', Rule::in(['mobile', 'email'])],
            'additional_contacts.*.value' => ['required', 'string', 'max:255'],
            'additional_addresses' => ['sometimes', 'array'],
            'additional_addresses.*.address_line_1' => ['required', 'string', 'max:255'],
            'additional_addresses.*.city' => ['nullable', 'string', 'max:100'],
            'additional_addresses.*.state' => ['nullable', 'string', 'max:100'],
            'additional_addresses.*.district' => ['nullable', 'string', 'max:100'],
            'additional_addresses.*.postal_code' => ['nullable', 'string', 'max:20'],
            'pending_sale' => ['sometimes', 'array'],
            'pending_sale.sales_document_number' => ['nullable', 'string', 'max:50'], 'pending_sale.plant_code' => ['nullable', 'string', 'max:20'],
            'pending_sale.document_date' => ['nullable', 'date'], 'pending_sale.material_number' => ['nullable', 'string', 'max:100'],
            'pending_sale.external_material_group' => ['nullable', 'string', 'max:100'], 'pending_sale.material_group' => ['nullable', 'string', 'max:100'],
            'pending_sale.order_quantity' => ['nullable', 'numeric'], 'pending_sale.amount' => ['nullable', 'numeric'],
            'pending_sale.open_delivery_amount' => ['nullable', 'numeric'], 'pending_sale.delivered_quantity' => ['nullable', 'numeric'],
            'pending_sale.material_text_1' => ['nullable', 'string', 'max:255'], 'pending_sale.material_text_2' => ['nullable', 'string', 'max:255'], 'pending_sale.material_text_3' => ['nullable', 'string', 'max:255'],
            'daily_sale' => ['sometimes', 'array'],
            'daily_sale.billing_document_number' => ['nullable', 'string', 'max:50'], 'daily_sale.billing_type' => ['nullable', 'string', 'max:20'],
            'daily_sale.billing_date' => ['nullable', 'date'], 'daily_sale.record_creation_date' => ['nullable', 'date'], 'daily_sale.company_code' => ['nullable', 'string', 'max:20'],
            'daily_sale.plant_code' => ['nullable', 'string', 'max:20'], 'daily_sale.material_number' => ['nullable', 'string', 'max:100'], 'daily_sale.material_description' => ['nullable', 'string', 'max:255'],
            'daily_sale.material_group' => ['nullable', 'string', 'max:100'], 'daily_sale.material_group_description' => ['nullable', 'string', 'max:255'],
            'daily_sale.product_hierarchy' => ['nullable', 'string', 'max:100'], 'daily_sale.product_hierarchy_description' => ['nullable', 'string', 'max:255'],
            'daily_sale.external_material_group' => ['nullable', 'string', 'max:100'], 'daily_sale.external_material_group_description' => ['nullable', 'string', 'max:255'],
            'daily_sale.billed_quantity' => ['nullable', 'numeric'], 'daily_sale.billed_quantity_kg' => ['nullable', 'numeric'], 'daily_sale.billed_quantity_liters' => ['nullable', 'numeric'], 'daily_sale.billed_quantity_meters' => ['nullable', 'numeric'],
            'daily_sale.sales_unit' => ['nullable', 'string', 'max:20'], 'daily_sale.base_unit' => ['nullable', 'string', 'max:20'], 'daily_sale.net_value' => ['nullable', 'numeric'], 'daily_sale.tax_amount' => ['nullable', 'numeric'],
            'daily_sale.fiscal_year' => ['nullable', 'string', 'max:4'], 'daily_sale.condition_value' => ['nullable', 'numeric'],
            'sales_summary' => ['sometimes', 'array'],
            'sales_summary.year_to_date_sales' => ['nullable', 'numeric'],
            'sales_summary.last_year_sales' => ['nullable', 'numeric'],
            'sales_summary.pending_sales' => ['nullable', 'numeric'],
            'sales_summary.last_month_sales' => ['nullable', 'numeric'],
            'sales_summary.current_month_sales' => ['nullable', 'numeric'],
            'sales_summary.two_months_ago_sales' => ['nullable', 'numeric'],
            'sales_summary.last_month_outstanding' => ['nullable', 'numeric'],
            'sales_summary.current_month_outstanding' => ['nullable', 'numeric'],
            'sales_summary.two_months_ago_outstanding' => ['nullable', 'numeric'],
            'sales_summary.last_month_overdue' => ['nullable', 'numeric'],
            'sales_summary.current_month_overdue' => ['nullable', 'numeric'],
            'sales_summary.two_months_ago_overdue' => ['nullable', 'numeric'],
            'sales_summary.last_month_payment_received' => ['nullable', 'numeric'],
            'sales_summary.current_month_payment_received' => ['nullable', 'numeric'],
            'sales_summary.two_months_ago_payment_received' => ['nullable', 'numeric'],
            'sales_summary.payment_terms_code' => ['nullable', 'string', 'max:50'],
            'sales_summary.payment_terms_description' => ['nullable', 'string', 'max:255'],
            'sales_summary.credit_limit' => ['nullable', 'numeric'],
        ];
        foreach (range(1, 12) as $month) {
            $suffix = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
            $rules["month_{$suffix}_target"] = ['nullable', 'numeric'];
            $rules["month_{$suffix}_actual"] = ['nullable', 'numeric'];
        }
        foreach (range(1, 4) as $quarter) {
            $rules["quarter_{$quarter}_target"] = ['nullable', 'numeric'];
            $rules["quarter_{$quarter}_actual"] = ['nullable', 'numeric'];
        }

        return $request->validate($rules);
    }

    /** @param array<int, array<string, mixed>> $rows
     *  @return array{int, int, int}
     */
    private function importCustomerBatch(array $rows, ?int $userId): array
    {
        $now = now();
        $prepared = [];
        $skipped = 0;
        foreach ($rows as $row) {
            $customerNumber = trim((string) ($row['customercode'] ?? ''));
            $customerName = trim((string) ($row['customername'] ?? ''));
            if ($customerNumber === '' || $customerName === '') {
                $skipped++;
                continue;
            }
            $prepared[$customerNumber] = ['number' => $customerNumber, 'name' => $customerName, 'row' => $row];
        }
        if ($prepared === []) {
            return [0, 0, $skipped];
        }

        $numbers = array_keys($prepared);
        $existing = DB::table('tbl_users')->whereIn('unique_id', $numbers)->pluck('unique_id')->all();
        $existingLookup = array_fill_keys(array_map('strval', $existing), true);
        $created = count(array_diff($numbers, array_keys($existingLookup)));
        $updated = count($numbers) - $created;

        DB::transaction(function () use ($prepared, $numbers, $now, $userId): void {
            $users = $salesRows = [];
            foreach ($prepared as $item) {
                [$firstName, $middleName, $lastName] = $this->splitImportName($item['name']);
                $row = $item['row'];
                $creditLimit = $this->importNumber($row['creditlimit'] ?? 0);
                $outstanding = $this->importNumber($row['currentmonthoutstanding'] ?? 0);
                $users[] = [
                    'unique_id' => $item['number'], 'user_type' => self::USER_TYPE, 'name' => $item['name'], 'buisness_name' => $item['name'],
                    'first_name' => $firstName, 'middle_name' => $middleName, 'last_name' => $lastName,
                    'status' => true, 'created_by' => $userId, 'updated_by' => $userId,
                    'created_at' => $now, 'updated_at' => $now,
                ];
                $sales = [
                    'user_id' => $item['number'], 'user_type' => self::USER_TYPE,
                    'payment_terms' => trim((string) ($row['zterm'] ?? $row['text'] ?? '')) ?: null,
                    'credit_limit' => $creditLimit, 'outstanding_amount' => $outstanding,
                    'credit_utilization_percentage' => $creditLimit > 0 ? round(($outstanding / $creditLimit) * 100, 3) : 0,
                    'api_payload' => json_encode($row, JSON_THROW_ON_ERROR), 'synced_at' => $now,
                    'deletion_indicator' => false, 'status' => true, 'created_by' => $userId, 'updated_by' => $userId,
                    'created_at' => $now, 'updated_at' => $now,
                ];
                foreach ([0 => 'currentmonthsale', 1 => 'lastmonthsale', 2 => 'last2lastmonthsale'] as $offset => $source) {
                    $month = (int) $now->copy()->subMonthsNoOverflow($offset)->format('m');
                    $sales['month_'.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'_actual'] = $this->importNumber($row[$source] ?? 0);
                }
                $salesRows[] = $sales;
            }

            DB::table('tbl_users')->upsert($users, ['unique_id'], [
                'user_type', 'name', 'buisness_name', 'first_name', 'middle_name', 'last_name', 'status', 'updated_by', 'updated_at',
            ]);

            $existingContacts = DB::table('tbl_usrcontact')->whereIn('user_id', $numbers)->where('is_primary', true)->pluck('user_id')->map(fn ($id) => (string) $id)->all();
            $contactRows = [];
            foreach (array_diff($numbers, $existingContacts) as $number) {
                $contactRows[] = ['user_id' => $number, 'usr_type' => self::USER_TYPE, 'contact_type' => 'primary', 'email' => null,
                    'contact_number' => null, 'is_primary' => true, 'is_verified' => false, 'status' => true,
                    'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now];
            }
            if ($contactRows !== []) DB::table('tbl_usrcontact')->insert($contactRows);

            $existingAddresses = DB::table('tbl_usraddress')->whereIn('user_id', $numbers)->where('is_primary', true)->pluck('user_id')->map(fn ($id) => (string) $id)->all();
            $addressRows = [];
            foreach (array_diff($numbers, $existingAddresses) as $number) {
                $addressRows[] = ['user_id' => $number, 'user_type' => self::USER_TYPE, 'address_type' => 'primary',
                    'address_line_1' => 'Address not provided', 'country' => 'India', 'is_primary' => true, 'status' => true,
                    'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now];
            }
            if ($addressRows !== []) DB::table('tbl_usraddress')->insert($addressRows);

            DB::table('tbl_customer_sales')->upsert($salesRows, ['user_id'], [
                'user_type', 'payment_terms', 'credit_limit', 'outstanding_amount', 'credit_utilization_percentage',
                'api_payload', 'synced_at', 'deletion_indicator', 'status', 'updated_by', 'updated_at',
                ...array_values(array_unique(array_merge(...array_map(fn ($row) => array_values(array_filter(array_keys($row), fn ($key) => str_starts_with($key, 'month_'))), $salesRows)))),
            ]);
        });

        return [$created, $updated, $skipped];
    }

    private function normalizeImportHeader(string $header): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', trim($header, "\xEF\xBB\xBF \t\n\r\0\x0B")));
    }

    /** @return array{string, ?string, ?string} */
    private function splitImportName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [$name];
        if (count($parts) === 1) return [$parts[0], null, null];
        if (count($parts) === 2) return [$parts[0], null, $parts[1]];
        return [array_shift($parts), implode(' ', array_slice($parts, 0, -1)) ?: null, end($parts) ?: null];
    }

    private function importNumber(mixed $value): float
    {
        $number = str_replace([',', ' '], '', trim((string) $value));
        return is_numeric($number) ? (float) $number : 0.0;
    }

    private function save(array $data, ?Customer $customer = null): void
    {
        DB::transaction(function () use ($data, $customer): void {
            $oldNumber = $customer?->unique_id;
            $number = trim($data['customer_number']);
            $name = trim(implode(' ', array_filter([$data['first_name'], $data['middle_name'] ?? null, $data['last_name'] ?? null])));
            $values = ['user_type' => self::USER_TYPE, 'unique_id' => $number, 'name' => $name, 'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?: null, 'last_name' => $data['last_name'] ?: null, 'status' => $data['status']];
            $customer ? $customer->update($values) : $customer = Customer::create($values);
            if ($oldNumber && $oldNumber !== $number) {
                foreach (['tbl_usrcontact', 'tbl_usraddress', 'tbl_customer_sales'] as $table) {
                    DB::table($table)->where('user_id', $oldNumber)->update(['user_id' => $number]);
                }
            }
            DB::table('tbl_usrcontact')->updateOrInsert(['user_id' => $number, 'contact_type' => 'primary'], [
                'usr_type' => self::USER_TYPE, 'email' => $data['email'] ?: null, 'contact_number' => $data['mobile_number'] ?: null,
                'is_primary' => true, 'is_verified' => false, 'status' => $data['status'], 'updated_at' => now(), 'created_at' => now(),
            ]);
            DB::table('tbl_usraddress')->updateOrInsert(['user_id' => $number, 'address_type' => 'primary'], [
                'user_type' => self::USER_TYPE, 'address_line_1' => $data['address'], 'city' => $data['city'] ?: null, 'state' => $data['state'] ?: null,
                'district' => $data['district'] ?: null, 'postal_code' => $data['postal_code'] ?: null, 'country' => 'India', 'is_primary' => true,
                'status' => $data['status'], 'updated_at' => now(), 'created_at' => now(),
            ]);
            DB::table('tbl_usrcontact')->where('user_id', $number)->where('is_primary', false)->delete();
            foreach ($data['additional_contacts'] ?? [] as $index => $contact) {
                DB::table('tbl_usrcontact')->insert([
                    'usr_type' => self::USER_TYPE, 'user_id' => $number, 'contact_type' => $contact['contact_type'],
                    'email' => $contact['contact_type'] === 'email' ? $contact['value'] : null,
                    'contact_number' => $contact['contact_type'] === 'mobile' ? $contact['value'] : null,
                    'is_primary' => false, 'is_verified' => false, 'status' => $data['status'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('tbl_usraddress')->where('user_id', $number)->where('is_primary', false)->delete();
            foreach ($data['additional_addresses'] ?? [] as $index => $address) {
                DB::table('tbl_usraddress')->insert([
                    'user_type' => self::USER_TYPE, 'user_id' => $number, 'address_type' => 'additional_'.($index + 1),
                    'address_line_1' => $address['address_line_1'], 'address_line_2' => null, 'city' => $address['city'] ?: null,
                    'state' => $address['state'] ?: null, 'district' => $address['district'] ?: null, 'postal_code' => $address['postal_code'] ?: null,
                    'country' => 'India', 'is_primary' => false, 'status' => $data['status'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $pending = $data['pending_sale'] ?? [];
            $pending = array_map(fn ($value) => $value === '' ? null : $value, $pending);
            if (! empty($pending['sales_document_number']) && ! empty($pending['material_number'])) {
                DB::table('tbl_pending_sales')->updateOrInsert([
                    'customer_number' => $number, 'plant_code' => $pending['plant_code'] ?: ($data['plant_code'] ?: ''),
                    'sales_document_number' => $pending['sales_document_number'], 'material_number' => $pending['material_number'],
                ], array_merge($pending, ['customer_number' => $number, 'status' => $data['status'], 'updated_at' => now(), 'created_at' => now()]));
            }
            $daily = $data['daily_sale'] ?? [];
            $daily = array_map(fn ($value) => $value === '' ? null : $value, $daily);
            if (! empty($daily['billing_document_number']) && ! empty($daily['material_number'])) {
                DB::table('tbl_dailysale')->updateOrInsert([
                    'billing_document_number' => $daily['billing_document_number'], 'customer_number' => $number,
                    'plant_code' => $daily['plant_code'] ?: ($data['plant_code'] ?: ''), 'material_number' => $daily['material_number'],
                ], array_merge($daily, ['customer_number' => $number, 'customer_name' => $name, 'status' => $data['status'], 'updated_at' => now(), 'created_at' => now()]));
            }
            $summary = $data['sales_summary'] ?? [];
            $summary = array_map(fn ($value) => $value === '' ? null : $value, $summary);
            if (collect($summary)->filter(fn ($value) => $value !== '' && $value !== null)->isNotEmpty()) {
                $existingSummaryId = DB::table('tbl_customer_sales_summary')
                    ->whereRaw('CAST(customer_number AS UNSIGNED) = CAST(? AS UNSIGNED)', [$number])
                    ->value('id');
                $summaryValues = array_merge($summary, ['customer_number' => $number, 'status' => $data['status'], 'updated_at' => now()]);

                if ($existingSummaryId) {
                    DB::table('tbl_customer_sales_summary')->where('id', $existingSummaryId)->update($summaryValues);
                } else {
                    DB::table('tbl_customer_sales_summary')->insert(array_merge($summaryValues, ['created_at' => now()]));
                }
            }
            $sales = [
                'user_type' => self::USER_TYPE, 'record_creation_date' => $data['record_creation_date'] ?: null, 'plant_code' => $data['plant_code'] ?: null,
                'gstin' => $data['gstin'] ?: null, 'ecc_no' => $data['ecc_no'] ?: null, 'cst_no' => $data['cst_no'] ?: null, 'lst_no' => $data['lst_no'] ?: null, 'pan' => $data['pan'] ?: null,
                'sales_organization' => $data['sales_organization'] ?: null, 'sales_district' => $data['sales_district'] ?: null,
                'sales_group' => $data['sales_group'] ?: null, 'sales_office' => $data['sales_office'] ?: null, 'payment_terms' => $data['payment_terms'] ?: null,
                'credit_limit' => $data['credit_limit'] ?? 0, 'outstanding_amount' => $data['outstanding_amount'] ?? 0,
                'credit_utilization_percentage' => $data['credit_utilization_percentage'] ?? 0, 'deletion_indicator' => ! $data['status'],
                'status' => $data['status'], 'synced_at' => now(), 'updated_at' => now(), 'created_at' => now(),
            ];
            foreach (range(1, 6) as $level) {
                $sales["employee_level_{$level}"] = $data["employee_level_{$level}"] ?: null;
            }
            foreach (range(1, 12) as $month) {
                $suffix = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
                $sales["month_{$suffix}_target"] = $data["month_{$suffix}_target"] ?? 0;
                $sales["month_{$suffix}_actual"] = $data["month_{$suffix}_actual"] ?? 0;
            }
            foreach (range(1, 4) as $quarter) {
                $sales["quarter_{$quarter}_target"] = $data["quarter_{$quarter}_target"] ?? 0;
                $sales["quarter_{$quarter}_actual"] = $data["quarter_{$quarter}_actual"] ?? 0;
            }
            DB::table('tbl_customer_sales')->updateOrInsert(['user_id' => $number], $sales);
        });
    }
}
