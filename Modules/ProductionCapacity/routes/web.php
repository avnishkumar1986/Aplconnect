<?php

use Illuminate\Support\Facades\Route;
use Modules\ProductionCapacity\Http\Controllers\ProductionCapacityController;

Route::middleware(['auth', 'module.active:production-capacity', 'permission:production_capacity.dashboard.view'])
    ->get('admin/production-capacity', [ProductionCapacityController::class, 'index'])
    ->name('admin.production-capacity.index');
