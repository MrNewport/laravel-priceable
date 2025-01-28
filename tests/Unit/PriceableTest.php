<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use MrNewport\LaravelPriceable\Tests\TestCase;
use MrNewport\LaravelPriceable\Tests\Models\UserTestModel;
use MrNewport\LaravelPriceable\Tests\Models\ProductTestModel;
use MrNewport\LaravelPriceable\Models\PriceList;
use MrNewport\LaravelPriceable\Models\PriceScope;
use MrNewport\LaravelPriceable\Exceptions\PriceNotFoundException;

beforeEach(function () {
    // Create minimal tables for test
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('email')->nullable();
        $table->timestamps();
    });

    Schema::create('test_products', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });
});

test('it returns a default price', function () {
    $product = ProductTestModel::create(['name' => 'Test Product']);
    $product->prices()->create([
        'min_quantity' => 1,
        'max_quantity' => 10,
        'unit_price'   => 100.00,
    ]);

    expect($product->priceFor(5))->toBe(100.00);
});

test('it returns correct tiered price', function () {
    $product = ProductTestModel::create(['name' => 'Test Product']);

    $product->prices()->create(['min_quantity' => 1,  'max_quantity' => 9, 'unit_price' => 100]);
    $product->prices()->create(['min_quantity' => 10, 'max_quantity' => 19, 'unit_price' => 90]);
    $product->prices()->create(['min_quantity' => 20, 'max_quantity' => null, 'unit_price' => 80]);

    expect($product->priceFor(5))->toBe(100.0);
    expect($product->priceFor(10))->toBe(90.0);
    expect($product->priceFor(50))->toBe(80.0);
});

test('user-specific price overrides default', function () {
    $user = UserTestModel::create();

    $vipList = PriceList::create(['name' => 'VIP']);
    $vipList->users()->attach($user->id);

    $product = ProductTestModel::create(['name' => 'Test Product']);
    // default
    $product->prices()->create(['min_quantity' => 1, 'max_quantity' => null, 'unit_price' => 100]);

    // vip
    $product->prices()->create([
        'price_list_id' => $vipList->id,
        'min_quantity'  => 1,
        'max_quantity'  => null,
        'unit_price'    => 80,
    ]);

    expect($product->priceFor(2, $user))->toBe(80.0);
    expect($product->priceFor(2))->toBe(100.0);
});

test('it uses scope (e.g. region=US)', function () {
    $product = ProductTestModel::create(['name' => 'Test Product']);

    // default
    $defaultPrice = $product->prices()->create([
        'min_quantity' => 1,
        'max_quantity' => null,
        'unit_price'   => 100,
    ]);

    // region-specific
    $scopedPrice = $product->prices()->create([
        'min_quantity' => 1,
        'max_quantity' => null,
        'unit_price'   => 90,
    ]);

    PriceScope::create([
        'price_id'   => $scopedPrice->id,
        'scope_type' => 'region',
        'scope_value'=> 'US',
    ]);

    expect($product->priceFor(1))->toBe(100.0);                 // no scope => 100
    expect($product->priceFor(1, null, ['region'=>'US']))->toBe(90.0);  // scope => 90
});

test('it throws exception if configured and no price found', function () {
    config()->set('priceable.fallback_to_default_price', false);
    config()->set('priceable.throw_exception_if_no_price_found', true);

    $product = ProductTestModel::create();

    $product->priceFor(5);
})->throws(PriceNotFoundException::class);
