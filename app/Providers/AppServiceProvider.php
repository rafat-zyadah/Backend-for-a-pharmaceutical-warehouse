<?php

namespace App\Providers;

use App\Support\Distribution\DistributionService;
use App\Support\Geography\RegionService;
use App\Support\Install\SystemInstaller;
use App\Support\MasterData\CompanyService;
use App\Support\MasterData\PharmacyService;
use App\Support\MasterData\ProductService;
use App\Support\Orders\OrderService;
use App\Support\Users\SupervisorContactService;
use App\Support\Users\SupervisorPasswordRecoveryService;
use App\Support\Users\UserService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SystemInstaller::class);
        $this->app->singleton(UserService::class);
        $this->app->singleton(SupervisorContactService::class);
        $this->app->singleton(SupervisorPasswordRecoveryService::class);
        $this->app->singleton(RegionService::class);
        $this->app->singleton(CompanyService::class);
        $this->app->singleton(ProductService::class);
        $this->app->singleton(PharmacyService::class);
        $this->app->singleton(DistributionService::class);
        $this->app->singleton(OrderService::class);
    }

    public function boot(): void
    {
        RateLimiter::for('auth-login', function (Request $request): Limit {
            $key = $request->ip().'|'.(string) $request->input('login');

            return Limit::perMinute(10)->by($key);
        });

        RateLimiter::for('auth-recovery', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
