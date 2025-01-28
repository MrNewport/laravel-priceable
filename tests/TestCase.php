<?php

namespace MrNewport\LaravelPriceable\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use MrNewport\LaravelPriceable\Providers\PriceableServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Load the package’s migrations from src/database/migrations
        $this->loadMigrationsFrom(__DIR__ . '/../src/database/migrations');
    }

    protected function getEnvironmentSetUp($app)
    {
        // Use in-memory SQLite
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [
            PriceableServiceProvider::class,
        ];
    }
}
