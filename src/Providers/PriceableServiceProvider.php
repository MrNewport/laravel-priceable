<?php

namespace MrNewport\LaravelPriceable\Providers;

use Illuminate\Support\ServiceProvider;
use MrNewport\LaravelPriceable\Services\PriceCalculator;
use MrNewport\LaravelPriceable\Facades\PriceableFacade;

class PriceableServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/priceable.php' => config_path('priceable.php'),
        ], 'config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'migrations');

        // Auto-load migrations if enabled
        if (config('priceable.auto_load_migrations', false)) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    /**
     * Register any application services.
     */
    public function register()
    {
        // Merge our package config
        $this->mergeConfigFrom(__DIR__ . '/../config/priceable.php', 'priceable');

        // Register a singleton PriceCalculator
        $this->app->singleton('priceable.calculator', function () {
            return new PriceCalculator();
        });

        // Alias the facade for easy usage
        $this->app->alias('priceable.calculator', PriceableFacade::class);
    }
}
