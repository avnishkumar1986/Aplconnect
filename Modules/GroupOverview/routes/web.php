<?php

use Illuminate\Support\Facades\Route;
use Modules\GroupOverview\Http\Controllers\GroupOverviewController;

Route::middleware(['auth', 'module.active:group-overview', 'permission:group_overview.dashboard.view'])
    ->get('admin/group-overview', [GroupOverviewController::class, 'index'])
    ->name('admin.group-overview.index');
