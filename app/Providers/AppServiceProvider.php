<?php

namespace App\Providers;

use App\Models\Address;
use App\Models\Contact;
use App\Models\Education;
use App\Models\Login;
use App\Models\ThemeSetting;
use App\Models\UserProfile;
use App\Models\UserType;
use App\Observers\AuditObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Gate::before(fn ($user) => $user->hasRole('Super Admin') ? true : null);
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([Address::class, Contact::class, Education::class, Login::class, UserProfile::class, UserType::class, Role::class, Permission::class] as $model) {
            $model::observe(AuditObserver::class);
        }
        View::composer('layout.app', function ($view): void {
            $view->with('adminTheme', Schema::hasTable('tbl_theme_settings') ? ThemeSetting::current() : null);
        });
    }
}
