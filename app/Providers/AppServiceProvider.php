<?php

namespace App\Providers;

use App\Repositories\Central\AuthRepository;
use App\Repositories\Central\Contracts\AuthRepositoryInterface;
use App\Repositories\Central\Contracts\TenantRepositoryInterface;
use App\Repositories\Central\TenantRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AuthRepositoryInterface::class, AuthRepository::class);
        $this->app->bind(TenantRepositoryInterface::class, TenantRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
