<?php

namespace App\Services;

use App\Models\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleSessionInvalidator
{
    public function invalidate(iterable $roleIds): void
    {
        $loginIds = DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
            ->where('model_type', Login::class)
            ->whereIn('role_id', collect($roleIds)->unique()->values())
            ->pluck(config('permission.column_names.model_morph_key', 'model_id'));

        if ($loginIds->isEmpty()) {
            return;
        }

        Login::whereKey($loginIds)->update(['token' => Str::random(60)]);

        $currentLoginId = Auth::id();
        $invalidateCurrentSession = $currentLoginId !== null
            && $loginIds->contains(fn ($loginId) => (int) $loginId === (int) $currentLoginId);

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->whereIn('user_id', $loginIds)
                ->delete();
        }

        // A database session deleted during the current request can otherwise
        // be written back by Laravel when the response is saved. Explicitly
        // logging out prevents an affected administrator from retaining stale
        // permissions after editing their own role.
        if ($invalidateCurrentSession && request()->hasSession()) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }
    }
}
