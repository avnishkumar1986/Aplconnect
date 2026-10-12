<?php

namespace App\Http\Controllers;

use App\Models\{Address, Contact, Education, Login, UserProfile, UserType};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __invoke()
    {
        Gate::authorize('dashboard.view');

        $login = auth()->user();
        $profile = UserProfile::with(['userType', 'distributorProfile'])->where('login_id', $login->id)->first();
        $isDistributor = $login->hasRole('Distributor')
            || str_contains(strtolower((string) $profile?->userType?->type_name), 'distributor');

        if ($isDistributor) {
            return view('distributor-dashboard', [
                'organization' => $profile?->business_name
                    ?: $profile?->distributorProfile?->business_name
                    ?: $profile?->full_name
                    ?: 'Demo Distribution Company',
                'performance' => [
                    'target' => 12500000,
                    'actual' => 9840000,
                    'outstanding' => 2660000,
                    'pending_orders' => 18,
                ],
                'quarters' => [
                    ['label' => 'Quarter 1', 'target' => 3000000, 'actual' => 2760000],
                    ['label' => 'Quarter 2', 'target' => 3200000, 'actual' => 2480000],
                    ['label' => 'Quarter 3', 'target' => 3100000, 'actual' => 2710000],
                    ['label' => 'Quarter 4', 'target' => 3200000, 'actual' => 1890000],
                ],
                'months' => [
                    ['month' => 'April', 'target' => 1000000, 'actual' => 920000],
                    ['month' => 'May', 'target' => 1000000, 'actual' => 880000],
                    ['month' => 'June', 'target' => 1000000, 'actual' => 960000],
                    ['month' => 'July', 'target' => 1050000, 'actual' => 790000],
                    ['month' => 'August', 'target' => 1050000, 'actual' => 850000],
                    ['month' => 'September', 'target' => 1100000, 'actual' => 840000],
                    ['month' => 'October', 'target' => 1050000, 'actual' => 930000],
                    ['month' => 'November', 'target' => 1000000, 'actual' => 890000],
                    ['month' => 'December', 'target' => 1050000, 'actual' => 890000],
                    ['month' => 'January', 'target' => 1050000, 'actual' => 810000],
                    ['month' => 'February', 'target' => 1000000, 'actual' => 650000],
                    ['month' => 'March', 'target' => 1100000, 'actual' => 430000],
                ],
            ]);
        }

        $companyIds = collect();
        if (Schema::hasTable('model_has_roles') && Schema::hasTable('role_company')) {
            $roleIds = DB::table('model_has_roles')
                ->where('model_type', get_class(auth()->user()))
                ->where('model_id', auth()->id())
                ->pluck('role_id');
            $companyIds = DB::table('role_company')->whereIn('role_id', $roleIds)->pluck('company_id');
        }

        $companyCodes = $companyIds->isEmpty()
            ? collect()
            : DB::table('tbl_company')->whereIn('id', $companyIds)->pluck('company_code');

        $users = UserProfile::query()->when($companyCodes->isNotEmpty(), fn ($query) =>
            $query->whereHas('employmentDepartment', fn ($employment) => $employment->whereIn('company_id', $companyCodes))
        );
        $userIds = (clone $users)->select('tbl_users.id');

        return view('dashboard', ['stats' => [
            'Users' => (clone $users)->count(),
            'Accounts' => Login::query()->when($companyCodes->isNotEmpty(), fn ($query) =>
                $query->whereHas('profile.employmentDepartment', fn ($employment) => $employment->whereIn('company_id', $companyCodes))
            )->count(),
            'User types' => UserType::count(),
            'Addresses' => Address::query()->when($companyCodes->isNotEmpty(), fn ($query) => $query->whereIn('employee_id', $userIds))->count(),
            'Contacts' => Contact::query()->when($companyCodes->isNotEmpty(), fn ($query) => $query->whereIn('employee_id', $userIds))->count(),
            'Education' => Education::query()->when($companyCodes->isNotEmpty(), fn ($query) => $query->whereIn('user_id', $userIds))->count(),
        ]]);
    }
}
