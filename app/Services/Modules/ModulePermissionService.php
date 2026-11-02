<?php

namespace App\Services\Modules;

use App\Models\ModulePackage;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class ModulePermissionService
{
    public function sync(ModulePackage $module): void
    {
        if (! $module->status) {
            return;
        }

        $prefix = $this->prefix($module->slug);
        $manifestPermissions = collect($module->manifest['permissions'] ?? [])
            ->map(fn (mixed $permission): ?string => is_string($permission)
                ? trim($permission)
                : (is_array($permission) ? trim((string) ($permission['name'] ?? '')) : null))
            ->filter(fn (?string $permission): bool => filled($permission))
            ->values();

        DB::transaction(function () use ($prefix, $manifestPermissions): void {
            foreach ($manifestPermissions as $name) {
                Permission::query()->updateOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['status' => true],
                );
            }

            Permission::query()
                ->where('guard_name', 'web')
                ->where(fn ($query) => $query
                    ->where('name', $prefix)
                    ->orWhere('name', 'like', $prefix.'.%'))
                ->update(['status' => true]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function prefix(string $slug): string
    {
        return Str::of($slug)->replace('-', '_')->lower()->toString();
    }
}
