<?php

namespace Modules\SalesDistribution\Providers;

use Illuminate\Support\ServiceProvider;

class SalesDistributionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
