<?php

namespace Modules\Procurement\Providers;

use Illuminate\Support\ServiceProvider;

class ProcurementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $root = dirname(__DIR__, 2);
        $this->loadViewsFrom($root.'/resources/views', 'procurement');
        $this->loadMigrationsFrom($root.'/database/migrations');
    }
}
