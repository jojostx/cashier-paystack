<?php

namespace Jojostx\Cashier\Paystack;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CashierServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/paystack.php',
            'paystack'
        );
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootRoutes();
        $this->bootResources();
        $this->bootMigrations();
        $this->bootPublishing();
    }

    /**
     * Boot the package routes.
     *
     * @return void
     */
    protected function bootRoutes()
    {
        if (Cashier::$registersRoutes) {
            Route::group([
                'prefix' => config('paystack.path'),
                'namespace' => 'Jojostx\Cashier\Paystack\Http\Controllers',
                'as' => 'paystack.',
            ], function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
            });
        }
    }

    /**
     * Boot the package resources.
     *
     * @return void
     */
    protected function bootResources()
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'cashier');
    }

    /**
     * Boot the package migrations.
     *
     * @return void
     */
    protected function bootMigrations()
    {
        if (Cashier::$runsMigrations && $this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    /**
     * Boot the package's publishable resources.
     *
     * @return void
     */
    protected function bootPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/paystack.php' => $this->app->configPath('paystack.php'),
            ], 'paystack-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'paystack-migrations');

            $this->publishes([
                __DIR__ . '/../resources/views' => $this->app->resourcePath('views/vendor/paystack'),
            ], 'paystack-views');
        }
    }
}
