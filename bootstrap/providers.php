<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    Modules\Procurement\Providers\ProcurementServiceProvider::class,
    Modules\Sales\Providers\SalesServiceProvider::class,
];
