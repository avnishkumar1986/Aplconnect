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
