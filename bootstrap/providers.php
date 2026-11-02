<?php

use App\Providers\AppServiceProvider;
use SocialiteProviders\Manager\ServiceProvider;
use Modules\SalesDistribution\Providers\SalesDistributionServiceProvider;

return [
    AppServiceProvider::class,
    ServiceProvider::class,
    SalesDistributionServiceProvider::class,
];
