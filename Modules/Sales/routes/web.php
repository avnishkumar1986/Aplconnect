<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\SalesController;
use Modules\Sales\Http\Controllers\CustomerController;

Route::middleware(['web', 'auth'])
    ->prefix('admin/sales')
    ->name('admin.sales.')
    ->group(function (): void {
        Route::get('/', SalesController::class)->name('overview');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::get('/customers/targets/create', [CustomerController::class, 'createTargets'])->name('customers.targets.create');
        Route::post('/customers/targets', [CustomerController::class, 'storeTargets'])->name('customers.targets.store');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    });
