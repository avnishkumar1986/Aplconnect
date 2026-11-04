<?php

use Illuminate\Support\Facades\Route;
use Modules\GroupOverview\Http\Controllers\GroupOverviewController;

Route::middleware(['auth', 'module.active:group-overview', 'permission:group_overview.dashboard.view'])->group(function (): void {
    Route::get('admin/group-overview', [GroupOverviewController::class, 'index'])
        ->name('admin.group-overview.index');
    Route::post('admin/group-overview/company-filter', [GroupOverviewController::class, 'updateCompanyFilter'])
        ->name('admin.group-overview.company-filter');
});
