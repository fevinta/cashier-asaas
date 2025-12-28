<?php

declare(strict_types=1);

namespace FernandoHS\CashierAsaas;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CashierAsaasServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerMigrations();
        $this->registerPublishing();
        $this->registerRoutes();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/cashier-asaas.php',
            'cashier-asaas'
        );
    }

    protected function registerMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/cashier-asaas.php' => config_path('cashier-asaas.php'),
            ], 'cashier-asaas-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'cashier-asaas-migrations');
        }
    }

    protected function registerRoutes(): void
    {
        if (Cashier::$registersRoutes) {
            Route::group([
                'prefix' => config('cashier-asaas.webhook_path', 'asaas/webhook'),
                'as' => 'cashier.',
            ], function () {
                Route::post('/', [Http\Controllers\WebhookController::class, 'handleWebhook'])
                    ->name('webhook');
            });
        }
    }
}
