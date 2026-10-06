<?php
use App\Http\Controllers\{ActionLogController,ApiIntegrationController,AuthController,CompanyController,CrudController,DashboardController,IndiaLocationController,ModuleController,OrganizationStructureController,PermissionController,RoleController,StatusController,ThemeSettingController,UserController,UserTypeController};
use Illuminate\Support\Facades\Route;
use Modules\Procurement\Http\Controllers\ProcurementController;

Route::middleware('guest')->group(function(){
    Route::get('/login',[AuthController::class,'create'])->name('login');
    Route::post('/login',[AuthController::class,'store'])->middleware('throttle:5,1')->name('login.store');
});
Route::middleware('auth')->group(function(){
    Route::post('/logout',[AuthController::class,'destroy'])->name('logout');
    Route::redirect('/','/admin');
    Route::prefix('admin')->name('admin.')->group(function(){
        Route::get('/',DashboardController::class)->name('dashboard');
        Route::resource('roles',RoleController::class)->except('show');
        Route::resource('permissions',PermissionController::class)->only(['index','store','update','destroy']);
        Route::post('users/bulk-action',[UserController::class,'bulkAction'])->name('users.bulk-action');
        Route::resource('users',UserController::class);
        Route::post('users/{user}/profile-image',[UserController::class,'uploadProfileImage'])->name('users.profile-image');
        Route::post('users/{user}/cover-image',[UserController::class,'uploadCoverImage'])->name('users.cover-image');
        Route::post('users/{user}/education/{education}/document',[UserController::class,'uploadEducationDocument'])->name('users.education-document');
        Route::resource('user-types',UserTypeController::class)->except('show');
        Route::resource('companies',CompanyController::class)->except('show');
        Route::get('organization-structure', [OrganizationStructureController::class, 'index'])->name('organization-structure.index');
        Route::post('organization-structure/{type}/bulk-action', [OrganizationStructureController::class, 'bulkAction'])->name('organization-structure.bulk-action');
        Route::post('organization-structure/{type}', [OrganizationStructureController::class, 'store'])->name('organization-structure.store');
        Route::put('organization-structure/{type}/{id}', [OrganizationStructureController::class, 'update'])->whereNumber('id')->name('organization-structure.update');
        Route::delete('organization-structure/{type}/{id}', [OrganizationStructureController::class, 'destroy'])->whereNumber('id')->name('organization-structure.destroy');
        Route::get('vendors', [ProcurementController::class, 'vendors'])->name('vendors.index');
        Route::prefix('india-locations')->name('india-locations.')->group(function () {
            Route::get('states',[IndiaLocationController::class,'states'])->name('states');
            Route::get('districts',[IndiaLocationController::class,'districts'])->name('districts');
            Route::get('cities',[IndiaLocationController::class,'cities'])->name('cities');
            Route::get('postal-codes',[IndiaLocationController::class,'postalCodes'])->name('postal-codes');
        });
        Route::patch('status/{resource}/{id}',StatusController::class)->whereNumber('id')->name('status.toggle');
        Route::get('action-logs',[ActionLogController::class,'index'])->name('action-logs.index');
        Route::get('modules',[ModuleController::class,'index'])->name('modules.index');
        Route::post('modules',[ModuleController::class,'store'])->name('modules.store');
        Route::patch('modules/{module}/status',[ModuleController::class,'toggleStatus'])->name('modules.status');
        Route::delete('modules/{module}',[ModuleController::class,'destroy'])->name('modules.destroy');
        Route::get('theme-settings',[ThemeSettingController::class,'edit'])->name('theme-settings.edit');
        Route::put('theme-settings',[ThemeSettingController::class,'update'])->name('theme-settings.update');
        Route::get('api-integrations/{apiIntegration}/runs',[ApiIntegrationController::class,'runs'])->name('api-integrations.runs');
        Route::post('api-integrations/{apiIntegration}/run',[ApiIntegrationController::class,'run'])->name('api-integrations.run');
        Route::post('api-integrations/{apiIntegration}/procurement-stock-sync',[ApiIntegrationController::class,'procurementStockSync'])->name('api-integrations.procurement-stock-sync');
        Route::resource('api-integrations',ApiIntegrationController::class)->except('show');
        Route::prefix('procurement')->name('procurement.')->group(function () {
            Route::get('/', [ProcurementController::class, 'overview'])->name('overview');
            Route::get('material-balance', [ProcurementController::class, 'materialBalance'])->name('material-balance');
            Route::post('material-balance/import', [ProcurementController::class, 'importMaterialBalance'])->name('material-balance.import');
            Route::get('material-balance/import-preview/{preview}', [ProcurementController::class, 'materialBalanceImportPreview'])->whereUuid('preview')->name('material-balance.import-preview');
            Route::get('material-balance/export/excel', [ProcurementController::class, 'exportMaterialBalanceExcel'])->name('material-balance.export-excel');
            Route::get('material-balance/export/pdf', [ProcurementController::class, 'exportMaterialBalancePdf'])->name('material-balance.export-pdf');
            Route::get('all-materials', [ProcurementController::class, 'materials'])->name('materials');
            Route::get('all-materials/{material}/edit', [ProcurementController::class, 'editMaterial'])->name('materials.edit');
            Route::put('all-materials/{material}', [ProcurementController::class, 'updateMaterial'])->name('materials.update');
            Route::delete('all-materials/{material}', [ProcurementController::class, 'destroyMaterial'])->name('materials.destroy');
            Route::get('purchases', [ProcurementController::class, 'purchases'])->name('purchases');
            Route::get('purchases/create', [ProcurementController::class, 'createPurchase'])->name('purchases.create');
            Route::post('purchases/sync-vendors', [ProcurementController::class, 'syncPurchaseVendors'])->name('purchases.sync-vendors');
            Route::post('purchases', [ProcurementController::class, 'storePurchase'])->name('purchases.store');
            Route::get('purchases/{purchase}/edit', [ProcurementController::class, 'editPurchase'])->whereNumber('purchase')->name('purchases.edit');
            Route::put('purchases/{purchase}', [ProcurementController::class, 'updatePurchase'])->whereNumber('purchase')->name('purchases.update');
            Route::delete('purchases/{purchase}', [ProcurementController::class, 'destroyPurchase'])->whereNumber('purchase')->name('purchases.destroy');
            Route::patch('purchases/{purchase}/delivery-status', [ProcurementController::class, 'updatePurchaseDeliveryStatus'])->whereNumber('purchase')->name('purchases.delivery-status');
            Route::get('daily-consumption', [ProcurementController::class, 'consumptions'])->name('consumptions');
            Route::get('daily-consumption/export', [ProcurementController::class, 'exportConsumptions'])->name('consumptions.export');
            Route::get('daily-consumption/{consumption}', [ProcurementController::class, 'showConsumption'])->whereNumber('consumption')->name('consumptions.show');
            Route::put('daily-consumption/{consumption}/plan', [ProcurementController::class, 'updateConsumptionPlan'])->whereNumber('consumption')->name('consumptions.plan.update');
            Route::post('daily-consumption/{consumption}/mou', [ProcurementController::class, 'storeConsumptionMou'])->whereNumber('consumption')->name('consumptions.mou.store');
            Route::get('daily-consumption/create', [ProcurementController::class, 'createConsumption'])->name('consumptions.create');
            Route::post('daily-consumption', [ProcurementController::class, 'storeConsumption'])->name('consumptions.store');
            Route::get('daily-consumption/{consumption}/edit', [ProcurementController::class, 'editConsumption'])->whereNumber('consumption')->name('consumptions.edit');
            Route::put('daily-consumption/{consumption}', [ProcurementController::class, 'updateConsumption'])->whereNumber('consumption')->name('consumptions.update');
            Route::delete('daily-consumption/{consumption}', [ProcurementController::class, 'destroyConsumption'])->whereNumber('consumption')->name('consumptions.destroy');
        });
        Route::get('materials', [ProcurementController::class, 'materials'])->name('materials.index');
        Route::get('demo/{section}/{page}', function (string $section, string $page) {
            $titles = [
                'posts' => 'Posts', 'pages' => 'Pages', 'procurement' => 'Procurement',
                'master-control' => 'Master Control', 'settings' => 'Settings', 'monitoring' => 'Monitoring',
            ];

            abort_unless(isset($titles[$section]), 404);

            return view('admin-dummy', [
                'sectionTitle' => $titles[$section],
                'pageTitle' => str($page)->replace('-', ' ')->title(),
            ]);
        })->where(['section' => '[a-z-]+', 'page' => '[a-z-]+'])->name('dummy.show');
        Route::post('bulk-action/{resource}', [CrudController::class, 'bulkAction'])->name('crud.bulk-action');
        Route::get('{resource}',[CrudController::class,'index'])->name('crud.index');
        Route::get('{resource}/create',[CrudController::class,'create'])->name('crud.create');
        Route::post('{resource}',[CrudController::class,'store'])->name('crud.store');
        Route::get('{resource}/{id}/edit',[CrudController::class,'edit'])->whereNumber('id')->name('crud.edit');
        Route::put('{resource}/{id}',[CrudController::class,'update'])->whereNumber('id')->name('crud.update');
        Route::delete('{resource}/{id}',[CrudController::class,'destroy'])->whereNumber('id')->name('crud.destroy');
    });
});
