<?php

namespace App\Http\Middleware;

use App\Models\ModulePackage;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $moduleNavigation = function (): array {
            static $navigation;

            if (isset($navigation)) {
                return $navigation;
            }

            if (! Schema::hasTable('tbl_modules')) {
                return $navigation = collect(['procurement', 'sales'])
                    ->filter(fn (string $slug): bool => is_file(base_path('Modules/'.ucfirst($slug).'/routes/web.php')))
                    ->mapWithKeys(fn (string $slug): array => [$slug => ['sidebar']])
                    ->all();
            }

            return $navigation = ModulePackage::query()
                ->where('status', true)
                ->get(['slug', 'display_locations'])
                ->filter(fn (ModulePackage $module): bool => match ($module->slug) {
                    'group-overview' => is_file(base_path('Modules/GroupOverview/routes/web.php')),
                    'procurement' => is_file(base_path('Modules/Procurement/routes/web.php')),
                    'production-capacity' => is_file(base_path('Modules/ProductionCapacity/routes/web.php')),
                    'sales' => is_file(base_path('Modules/Sales/routes/web.php')),
                    'sales-distribution' => is_file(base_path('Modules/SalesDistribution/routes/web.php')),
                    'corporate-chat' => is_file(base_path('Modules/CorporateChat/routes/web.php')),
                    'support' => is_file(base_path('Modules/Support/routes/web.php')),
                    default => true,
                })
                ->mapWithKeys(fn (ModulePackage $module): array => [$module->slug => $module->display_locations])
                ->all();
        };

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'roles' => fn (): array => $request->user()?->getRoleNames()->values()->all() ?? [],
                'permissions' => fn (): array => $request->user()?->getAllPermissions()->pluck('name')->values()->all() ?? [],
                'homeUrl' => fn (): string => $this->homeUrl($request),
            ],
            'notifications' => fn (): array => $request->user() && Schema::hasTable('notifications') ? [
                'unreadCount' => $request->user()->unreadNotifications()->count(),
                'items' => $request->user()->notifications()->latest()->limit(8)->get()->map(fn ($notification): array => [
                    'id' => $notification->id,
                    'title' => data_get($notification->data, 'title', 'Notification'),
                    'message' => data_get($notification->data, 'message', ''),
                    'ticketNumber' => data_get($notification->data, 'ticket_number'),
                    'read' => $notification->read_at !== null,
                    'createdAt' => $notification->created_at?->diffForHumans(),
                ])->values()->all(),
            ] : ['unreadCount' => 0, 'items' => []],
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
            ],
            'datatable' => [
                'perPage' => config('datatable.per_page'),
                'pageSizeOptions' => config('datatable.page_size_options'),
            ],
            'activeModules' => fn (): array => array_keys($moduleNavigation()),
            'moduleDisplayLocations' => $moduleNavigation,
        ]);
    }

    private function homeUrl(Request $request): string
    {
        $user = $request->user();

        if (! $user) {
            return '/dashboard';
        }

        return match (true) {
            $user->can('group_overview.dashboard.view') => '/admin/group-overview',
            $user->can('production_capacity.dashboard.view') => '/admin/production-capacity',
            $user->can('sales.overview.view') => '/admin/sales',
            $user->can('sales_distribution.dashboard.view') => '/admin/sales-distribution',
            $user->can('procurement.dashboard.view') => '/admin/procurement/dashboard',
            $user->can('procurement.planning_decisions.view') => '/admin/procurement/planning-decisions',
            $user->can('procurement.materials_balance.view') => '/admin/procurement/all-balance-sheet',
            $user->can('procurement.consumption.view') => '/admin/procurement/daily-consumption',
            $user->can('procurement.rm_inventories.view') => '/admin/procurement/balance-sheet',
            $user->can('dashboard.view') => '/dashboard',
            $user->can('users.view') => '/admin/users',
            $user->can('roles.view') => '/admin/roles',
            default => '/settings/profile',
        };
    }
}
