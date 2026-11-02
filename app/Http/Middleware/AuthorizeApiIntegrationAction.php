<?php

namespace App\Http\Middleware;

use App\Models\ApiIntegration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeApiIntegrationAction
{
    public function handle(Request $request, Closure $next, string $action = 'manage'): Response
    {
        $integration = $request->route('apiIntegration');
        abort_unless($integration instanceof ApiIntegration, 404);

        $user = $request->user();
        if ($user?->hasRole('Owner')) {
            return $next($request);
        }

        $code = strtolower((string) $integration->code);
        $allowed = match ($code) {
            'employee' => $user?->can('users.manage') ?? false,
            'cust', 'customer' => ($user?->can('users.manage') ?? false)
                || ($user?->can('sales_distribution.customers.manage') ?? false),
            'customer_sales_summary' => $user?->can('sales_distribution.customer_sales_summary.manage') ?? false,
            'customer_collections' => $user?->can('sales_distribution.customer_sales_summary.manage') ?? false,
            'pending_sales' => ($user?->can('users.manage') ?? false)
                || ($user?->can('sales_distribution.pending_sales.manage') ?? false),
            'sales_data' => ($user?->can('users.manage') ?? false)
                || ($user?->can('sales_distribution.daily_sales.manage') ?? false),
            'security' => false,
            default => $user?->can('procurement.api.'.$this->permissionCode($code).'.'.($action === 'run' ? 'run' : 'manage')) ?? false,
        };

        abort_unless($allowed, 403);

        return $next($request);
    }

    private function permissionCode(string $code): string
    {
        return $code === 'mrmcon' ? 'consumption' : $code;
    }
}
