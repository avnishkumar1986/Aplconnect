<?php

namespace Modules\Sales\Providers;

use Illuminate\Support\ServiceProvider;

class SalesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $root = dirname(__DIR__, 2);
        $this->loadRoutesFrom($root.'/routes/web.php');
        $this->loadViewsFrom($root.'/resources/views', 'sales');
        $this->loadMigrationsFrom($root.'/database/migrations');
    }
}
