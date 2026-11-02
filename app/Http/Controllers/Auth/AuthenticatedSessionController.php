<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\UserLocation;
use App\Services\Auth\AuthenticationService;
use App\Support\DeviceLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly AuthenticationService $authenticationService) {}

    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $this->authenticationService->login($request);

        if ($request->filled(['latitude', 'longitude'])) {
            $location = UserLocation::query()
                ->where('user_id', $request->user()->getKey())
                ->latest('id')
                ->firstOrNew(['user_id' => $request->user()->getKey()]);

            $location->fill([
                ...$request->safe()->only(['latitude', 'longitude', 'location_name']),
                'device_name' => DeviceLabel::fromUserAgent($request->userAgent()),
                'user_agent' => $request->userAgent(),
                'captured_at' => now(),
            ])->save();
        }

        $user = $request->user();
        $defaultDestination = match (true) {
            $user->can('group_overview.dashboard.view') && Route::has('admin.group-overview.index') => route('admin.group-overview.index', absolute: false),
            $user->can('production_capacity.dashboard.view') && Route::has('admin.production-capacity.index') => route('admin.production-capacity.index', absolute: false),
            $user->can('sales.overview.view') => '/admin/sales',
            $user->can('sales_distribution.dashboard.view') => route('admin.sales-distribution.index', absolute: false),
            $user->can('procurement.dashboard.view') => route('admin.procurement.dashboard', absolute: false),
            $user->can('procurement.planning_decisions.view') => route('admin.procurement.planning-decisions.index', absolute: false),
            $user->can('procurement.materials_balance.view') => route('admin.procurement.all-balance-sheet', absolute: false),
            $user->can('procurement.consumption.view') => route('admin.procurement.daily-consumption.index', absolute: false),
            $user->can('procurement.rm_inventories.view') => route('admin.procurement.balance-sheet', absolute: false),
            $user->can('dashboard.view') => route('dashboard', absolute: false),
            $user->can('users.view') => route('admin.users.index', absolute: false),
            $user->can('roles.view') => route('admin.roles.index', absolute: false),
            default => '/settings/profile',
        };

        // Do not reuse a protected URL saved in the session before login. A role
        // change can make that URL invalid and would immediately produce a 403.
        $request->session()->forget('url.intended');

        return redirect()->to($defaultDestination);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->authenticationService->logout($request);

        return redirect('/');
    }
}
