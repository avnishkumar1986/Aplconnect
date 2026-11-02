<?php

use Illuminate\Support\Facades\Route;
use Modules\SalesDistribution\Http\Controllers\SalesDistributionController;
use Modules\SalesDistribution\Http\Controllers\CustomerController;
use Modules\SalesDistribution\Http\Controllers\PendingSaleController;
use Modules\SalesDistribution\Http\Controllers\DailySaleController;
use Modules\SalesDistribution\Http\Controllers\CustomerSalesSummaryController;
use Modules\SalesDistribution\Http\Controllers\CollectionController;
use Modules\SalesDistribution\Http\Controllers\ProductTargetController;

Route::middleware(['auth', 'module.active:sales-distribution', 'permission:sales_distribution.dashboard.view'])
    ->get('admin/sales-distribution', [SalesDistributionController::class, 'index'])
    ->name('admin.sales-distribution.index');

Route::middleware(['auth', 'module.active:sales-distribution', 'permission:sales_distribution.dashboard.view'])
    ->prefix('admin/sales-distribution')
    ->name('admin.sales-distribution.')
    ->group(function (): void {
        Route::get('customers', [CustomerController::class, 'index'])->middleware('permission:sales_distribution.customers.view')->name('customers.index');
        Route::get('pending-sales', [PendingSaleController::class, 'index'])->middleware('permission:sales_distribution.pending_sales.view')->name('pending-sales.index');
        Route::get('daily-sales', [DailySaleController::class, 'index'])->middleware('permission:sales_distribution.daily_sales.view')->name('daily-sales.index');
        Route::get('customer-sales-summary', [CustomerSalesSummaryController::class, 'index'])->middleware('permission:sales_distribution.customer_sales_summary.view')->name('customer-sales-summary.index');
        Route::get('collections', [CollectionController::class, 'index'])->middleware('permission:sales_distribution.collections.view')->name('collections.index');
        Route::get('product-targets', [ProductTargetController::class, 'index'])->middleware('permission:sales_distribution.product_targets.view')->name('product-targets.index');

        Route::middleware('permission:sales_distribution.customers.manage')->group(function (): void {
            Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
            Route::post('customers/import', [CustomerController::class, 'import'])->name('customers.import');
            Route::patch('customers/bulk', [CustomerController::class, 'bulkAction'])->name('customers.bulk');
            Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
            Route::patch('customers/{customer}/status', [CustomerController::class, 'toggleStatus'])->name('customers.status');
            Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        });

        Route::middleware('permission:sales_distribution.pending_sales.manage')->group(function (): void {
            Route::post('pending-sales', [PendingSaleController::class, 'store'])->name('pending-sales.store');
            Route::patch('pending-sales/bulk', [PendingSaleController::class, 'bulkAction'])->name('pending-sales.bulk');
            Route::put('pending-sales/{pendingSale}', [PendingSaleController::class, 'update'])->name('pending-sales.update');
            Route::patch('pending-sales/{pendingSale}/status', [PendingSaleController::class, 'toggleStatus'])->name('pending-sales.status');
            Route::delete('pending-sales/{pendingSale}', [PendingSaleController::class, 'destroy'])->name('pending-sales.destroy');
        });

        Route::middleware('permission:sales_distribution.daily_sales.manage')->group(function (): void {
            Route::post('daily-sales', [DailySaleController::class, 'store'])->name('daily-sales.store');
            Route::patch('daily-sales/bulk', [DailySaleController::class, 'bulkAction'])->name('daily-sales.bulk');
            Route::put('daily-sales/{sale}', [DailySaleController::class, 'update'])->name('daily-sales.update');
            Route::patch('daily-sales/{sale}/status', [DailySaleController::class, 'toggleStatus'])->name('daily-sales.status');
            Route::delete('daily-sales/{sale}', [DailySaleController::class, 'destroy'])->name('daily-sales.destroy');
        });

        Route::middleware('permission:sales_distribution.customer_sales_summary.manage')->group(function (): void {
            Route::post('customer-sales-summary', [CustomerSalesSummaryController::class, 'store'])->name('customer-sales-summary.store');
            Route::post('customer-sales-summary/import', [CustomerSalesSummaryController::class, 'import'])->name('customer-sales-summary.import');
            Route::patch('customer-sales-summary/bulk', [CustomerSalesSummaryController::class, 'bulkAction'])->name('customer-sales-summary.bulk');
            Route::put('customer-sales-summary/{customerSalesSummary}', [CustomerSalesSummaryController::class, 'update'])->name('customer-sales-summary.update');
            Route::patch('customer-sales-summary/{customerSalesSummary}/status', [CustomerSalesSummaryController::class, 'toggleStatus'])->name('customer-sales-summary.status');
            Route::delete('customer-sales-summary/{customerSalesSummary}', [CustomerSalesSummaryController::class, 'destroy'])->name('customer-sales-summary.destroy');
        });
        Route::middleware('permission:sales_distribution.collections.manage')->group(function (): void {
            Route::post('collections',[CollectionController::class,'store'])->name('collections.store');
            Route::patch('collections/bulk',[CollectionController::class,'bulkAction'])->name('collections.bulk');
            Route::put('collections/{collection}',[CollectionController::class,'update'])->name('collections.update');
            Route::patch('collections/{collection}/status',[CollectionController::class,'toggleStatus'])->name('collections.status');
            Route::delete('collections/{collection}',[CollectionController::class,'destroy'])->name('collections.destroy');
        });
        Route::middleware('permission:sales_distribution.product_targets.manage')->group(function (): void {
            Route::post('product-targets', [ProductTargetController::class, 'store'])->name('product-targets.store');
            Route::post('product-targets/import', [ProductTargetController::class, 'import'])->name('product-targets.import');
            Route::put('product-targets/{productTarget}', [ProductTargetController::class, 'update'])->name('product-targets.update');
            Route::delete('product-targets/{productTarget}', [ProductTargetController::class, 'destroy'])->name('product-targets.destroy');
        });
    });

Route::middleware('auth')->group(function (): void {
    Route::redirect('admin/customers', '/admin/sales-distribution/customers');
    Route::redirect('admin/pending-sales', '/admin/sales-distribution/pending-sales');
    Route::redirect('admin/daily-sales', '/admin/sales-distribution/daily-sales');
    Route::redirect('admin/customer-sales-summary', '/admin/sales-distribution/customer-sales-summary');
    Route::redirect('admin/collections', '/admin/sales-distribution/collections');
    Route::redirect('admin/product-targets', '/admin/sales-distribution/product-targets');
});
