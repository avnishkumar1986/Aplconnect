<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function index()
    {
        Gate::authorize('sales.customers.view');

        $customers = UserProfile::with(['distributorProfile', 'contacts', 'emailContacts'])
            ->whereHas('userType', fn ($query) => $query->where('type_name', 'like', '%Distributor%'))
            ->latest('id')
            ->get()
            ->map(function (UserProfile $distributor): array {
                $phone = $distributor->contacts->first(fn ($contact) => in_array((string) $contact->contact_type, ['1', '2', '4'], true));
                $email = $distributor->emailContacts->first();
                $association = (string) ($distributor->distributorProfile?->company_association ?: 'APL + KPL');
                return [
                    'id' => $distributor->id,
                    'code' => (string) $distributor->code,
                    'business' => $distributor->business_name ?: $distributor->distributorProfile?->business_name ?: $distributor->full_name,
                    'contact' => $distributor->full_name,
                    'phone' => $phone?->contact_value ?: '—',
                    'email' => $email?->contact_value ?: '—',
                    'company' => $association === '1000' ? 'APL' : ($association === '5000' ? 'KPL' : $association),
                    'category' => str($distributor->distributor_category ?: $distributor->distributorProfile?->distributor_category ?: '—')->headline()->toString(),
                    'credit_limit' => (float) ($distributor->distributorProfile?->credit_limit ?: 0),
                    'outstanding' => 0,
                    'status' => (bool) $distributor->status,
                ];
            });

        return view('sales::customers.index', compact('customers'));
    }

    public function create()
    {
        Gate::authorize('sales.customers.create');

        return redirect()->route('admin.distributors.create');
    }

    public function createTargets()
    {
        Gate::authorize('sales.customers.edit');

        $customers = UserProfile::query()
            ->with('distributorProfile')
            ->whereHas('userType', fn ($query) => $query->where('type_name', 'like', '%Distributor%'))
            ->where('status', true)
            ->orderBy('first_name')
            ->get();

        return view('sales::customers.targets-form', compact('customers'));
    }

    public function storeTargets(Request $request)
    {
        Gate::authorize('sales.customers.edit');

        $currentYear = now()->year;
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:tbl_users,id'],
            'target_year' => ['required', 'integer', 'min:'.($currentYear - 10), 'max:'.($currentYear + 10)],
            'yearly_target' => ['required', 'numeric', 'min:0.01', 'max:9999999999999999'],
            'monthly_targets' => ['required', 'array', 'size:12'],
            'monthly_targets.*' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
        ]);

        $customer = UserProfile::query()
            ->whereKey($data['customer_id'])
            ->whereHas('userType', fn ($query) => $query->where('type_name', 'like', '%Distributor%'))
            ->firstOrFail();

        $annualPaise = (int) round((float) $data['yearly_target'] * 100);
        $monthlyPaise = collect(range(1, 12))->sum(
            fn ($month) => (int) round((float) ($data['monthly_targets'][$month] ?? 0) * 100)
        );
        if ($annualPaise !== $monthlyPaise) {
            throw ValidationException::withMessages([
                'yearly_target' => 'The total of all monthly targets must equal the annual target.',
            ]);
        }

        DB::transaction(function () use ($customer, $data): void {
            $periods = [0 => $data['yearly_target']];
            foreach (range(1, 12) as $month) {
                $periods[$month] = $data['monthly_targets'][$month];
            }

            foreach ($periods as $month => $amount) {
                DB::table('tbl_distributor_targets')->updateOrInsert(
                    ['user_id' => $customer->id, 'target_year' => $data['target_year'], 'target_month' => $month],
                    [
                        'target_amount' => $amount,
                        'currency' => 'INR',
                        'created_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        });

        return redirect()->route('admin.sales.customers.index')->with('success', 'Distributor targets saved successfully.');
    }

    public function show(UserProfile $customer)
    {
        Gate::authorize('sales.customers.view');

        $customer->load([
            'userType',
            'company',
            'sittingLocation',
            'designation.parentDesignation',
            'department',
            'reportingTo.designation',
            'contact',
            'address',
            'contacts',
            'addresses',
            'distributorProfile.tier',
            'distributorSubsidiaries',
            'distributorTargets',
            'education',
            'login.roles',
            'profileImage',
            'coverImage',
            'createdBy.profile',
            'updatedBy.profile',
        ]);

        abort_unless(
            str_contains(strtolower((string) $customer->userType?->type_name), 'distributor'),
            404
        );

        return view('users.show', ['user' => $customer, 'salesCustomerProfile' => true]);
    }
}
