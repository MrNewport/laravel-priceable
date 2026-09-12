<?php

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;

if (getenv('MYSQL_TEST_DATABASE') !== 'package_test') {
    throw new RuntimeException('This smoke test requires the disposable package_test database.');
}

$database = new Capsule;
$database->addConnection([
    'driver' => 'mysql',
    'host' => getenv('MYSQL_TEST_HOST') ?: '127.0.0.1',
    'database' => 'package_test',
    'username' => getenv('MYSQL_TEST_USERNAME') ?: 'root',
    'password' => getenv('MYSQL_TEST_PASSWORD'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);
$database->setAsGlobal();
$container = $database->getContainer();
$container->instance('db', $database->getDatabaseManager());
$container->bind('db.schema', fn () => $database->getConnection()->getSchemaBuilder());
Facade::setFacadeApplication($container);

Schema::create('users', function (Blueprint $table) {
    $table->id();
});
$migrations = [];
foreach (glob(__DIR__.'/../src/database/migrations/*.php') as $file) {
    $migration = require $file;
    $migration->up();
    $migrations[] = $migration;
}
$indexes = $database->getConnection()->select('SHOW INDEX FROM prices');
if (!in_array('prices_priceable_quantity_index', array_column($indexes, 'Key_name'), true)) {
    throw new RuntimeException('The quantity index was not created.');
}
foreach (array_reverse($migrations) as $migration) {
    $migration->down();
}
Schema::drop('users');
echo "MySQL migrations and rollback passed.\n";
