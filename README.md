# mrnewport/laravel-priceable

**mrnewport/laravel-priceable** is a Laravel package that provides **quantity-based, multi-scope** pricing for any Eloquent model. It allows you to define tiered pricing, attach custom price lists to specific users, and apply additional “scopes” (like region or channel) without the need for external dependencies.

This README walks you through **installation**, **configuration**, **usage**, **advanced features**, and **testing**.

---

## Table of Contents

1. [Introduction](#introduction)
2. [Key Features](#key-features)
3. [Requirements](#requirements)
4. [Installation](#installation)
5. [Configuration](#configuration)
6. [Migrations](#migrations)
7. [Usage](#usage)
    - [Attaching the `Priceable` Trait](#attaching-the-priceable-trait)
    - [Defining Tiered Pricing](#defining-tiered-pricing)
    - [Price Lists & User-Specific Pricing](#price-lists--user-specific-pricing)
    - [Scopes (Region, Channel, etc.)](#scopes-region-channel-etc)
    - [Retrieving a Price](#retrieving-a-price)
8. [Fallback Logic & Exception Handling](#fallback-logic--exception-handling)
9. [Advanced: Date-Range Pricing](#advanced-date-range-pricing)
10. [Testing](#testing)
11. [License](#license)

---

## Introduction

`MrNewport/LaravelPriceable` offers a complete and robust solution for **dynamic pricing** in your Laravel application. By simply adding a trait to any Eloquent model, you can define:

- **Tiered prices** based on quantity ranges.
- **Multiple price lists** for different groups (e.g., corporate, VIP, or partner).
- **Scopes** for region-, channel-, or any other custom-based pricing.
- **Optional fallback** to a default price if no specialized pricing is found.
- **Date-range validations** for time-limited pricing or promotions.

Everything is **self-contained**, including the test suite, which uses an **in-memory SQLite** database and does **not** rely on your host application’s models or migrations.

---

## Key Features

- **Tiered / Quantity-Based Pricing**: Price changes automatically when buyers hit certain quantity thresholds.
- **Multi-Tenant Support**: Assign price lists to specific users or entire groups.
- **Scopes**: Add region, channel, or any additional dimension to fine-tune pricing.
- **Date-Based Validity**: Start and end dates for promotional or seasonal pricing.
- **Fallback & Exceptions**: Gracefully handle missing specialized prices.
- **Polymorphic**: Attach pricing to **any** Eloquent model with a single trait.
- **Self-Contained Tests**: No external references; everything is tested from within the package.

---

## Requirements

- **PHP**: ^8.0
- **Laravel**: 9 through 13 (current CI covers Laravel 11, 12 and 13)
- **Database**: Any supported by Laravel (tested primarily on MySQL & SQLite).

---

## Installation

1. **Require via Composer**:

    ```bash
    composer require mrnewport/laravel-priceable
    ```

2. **Optional**: Publish the package config and migrations into your Laravel app (if you want to customize them in your project):

    ```bash
    php artisan vendor:publish --provider="MrNewport\LaravelPriceable\Providers\PriceableServiceProvider" --tag=config
    php artisan vendor:publish --provider="MrNewport\LaravelPriceable\Providers\PriceableServiceProvider" --tag=migrations
    ```

3. **Run Migrations** (if you published them or are auto-loading them):

    ```bash
    php artisan migrate
    ```

4. **Configure** (Optional): If you want the package to auto-load its migrations instead of publishing them, set `auto_load_migrations` to `true` in `config/priceable.php`.

---

## Configuration

The package ships with a configuration file located at:

```
src/config/priceable.php
```

When published, it appears in your Laravel app’s `config/priceable.php`. Key settings include:

```php
return [
    'auto_load_migrations' => false,
    'fallback_to_default_price' => true,
    'throw_exception_if_no_price_found' => false,
    'default_currency' => 'USD',
];
```

- `auto_load_migrations`: If `true`, migrations inside `src/database/migrations` will be auto-loaded by the package.
- `fallback_to_default_price`: When a user’s price list doesn’t match, fallback to the “default” price (where `price_list_id` is `null`).
- `throw_exception_if_no_price_found`: If no price is found (even after fallback), throw a `PriceNotFoundException` instead of returning `null`.
- `default_currency`: The default currency used in your price records.

---

## Migrations

All migration files reside in:
```
src/database/migrations/
```

If `auto_load_migrations` is set to `false` (default), you should publish and run them from your main Laravel application. Alternatively, set `auto_load_migrations` to `true` to have them loaded directly from this package.

These migrations create:

1. **price_lists**: Names and descriptions for your custom or default price lists (e.g., “VIP”, “Corporate”).
2. **price_list_user**: A pivot table linking users to price lists.
3. **prices**: Stores tier-based pricing, including quantity ranges, currency, and optional date ranges.
4. **price_scopes**: Key-value pairs (e.g., region=US, channel=B2B) for more granular pricing logic.

---

## Usage

### Attaching the `Priceable` Trait

Any Eloquent model you wish to have dynamic pricing must use the `Priceable` trait:

```php
use MrNewport\LaravelPriceable\Models\Traits\Priceable;

class Product extends Model
{
    use Priceable;

    // Your other model code
}
```

This adds a polymorphic relationship to the `prices` table, letting you define multiple tiered prices for each product (or any other model).

---

### Defining Tiered Pricing

You can define multiple **quantity ranges** by creating records in the `prices` relationship:

```php
$product = Product::create([...]);

// For 1-9 units
$product->prices()->create([
    'min_quantity' => 1,
    'max_quantity' => 9,
    'unit_price'   => 100.00, // $100
]);

// For 10+ units (no upper bound)
$product->prices()->create([
    'min_quantity' => 10,
    'max_quantity' => null,
    'unit_price'   => 80.00,  // $80
]);
```

If someone purchases 5 units, the price is `$100`; for 15 units, `$80`.

---

### Price Lists & User-Specific Pricing

If you want specialized pricing for certain users:

1. **Create a Price List**:
    ```php
    use MrNewport\LaravelPriceable\Models\PriceList;

    $vipList = PriceList::create([
        'name'        => 'VIP',
        'description' => 'VIP Price List',
    ]);
    ```

2. **Attach a user** to that list:
    ```php
    // Assuming $user is an instance of your User model
    $vipList->users()->attach($user->id);
    ```

3. **Add special prices** under that price list:
    ```php
    $product->prices()->create([
        'price_list_id' => $vipList->id,
        'min_quantity'  => 1,
        'max_quantity'  => null,
        'unit_price'    => 60.00,
    ]);
    ```

When you call `$product->priceFor(5, $user)`, the package checks the user’s `VIP` price list first, and if found, uses `$60`.

---

### Scopes (Region, Channel, etc.)

You can attach key-value scopes to any price. For instance, define a `region=US` or `channel=B2B` scope:

```php
use MrNewport\LaravelPriceable\Models\PriceScope;

$scopedPrice = $product->prices()->create([
    'min_quantity' => 1,
    'max_quantity' => null,
    'unit_price'   => 95.00,
]);

// Attach scope region=US
$scopedPrice->scopes()->create([
    'scope_type'  => 'region',
    'scope_value' => 'US',
]);
```

When retrieving the price, pass scopes as an associative array:

```php
$price = $product->priceFor(10, $user, ['region' => 'US']);
```

Scoped prices must match every supplied dimension and must not contain additional restrictions that the caller did not supply. For example, a price restricted to both `region=US` and `channel=B2B` requires both values. Each scope is matched on its own row.

---

### Retrieving a Price

To get a final price, call `priceFor($quantity, $user, $scopes = [])`:

```php
$quantity = 12;
$user     = auth()->user(); // or any user instance
$scopes   = ['region' => 'US', 'channel' => 'B2B'];

$price = $product->priceFor($quantity, $user, $scopes);
```

Alternatively, you can use:

1. **Facade**:
    ```php
    use MrNewport\LaravelPriceable\Facades\PriceableFacade as Priceable;

    $price = Priceable::getPrice($product, 12, $user, ['region' => 'US']);
    ```

2. **Helper**:
    ```php
    use function MrNewport\LaravelPriceable\Support\price_for;

    $price = price_for($product, 12, $user, ['region' => 'US']);
    ```

---

## Fallback Logic & Exception Handling

If a user has no custom price (or no matching scope), the system can **fallback** to a default price (`price_list_id = null`). You can control this behavior via config:

- `fallback_to_default_price`: `true` or `false`.
- `throw_exception_if_no_price_found`: If set to `true`, throws a `PriceNotFoundException` if no price is found after fallback.

This ensures you **never** end up with an undefined or zero price unless you explicitly allow it.

---

## Advanced: Date-Range Pricing

Each price record can optionally have `valid_from` and `valid_to` fields:

```php
$product->prices()->create([
    'min_quantity' => 1,
    'max_quantity' => 9,
    'unit_price'   => 100.00,
    'valid_from'   => now()->startOfMonth(),
    'valid_to'     => now()->endOfMonth(),
]);
```

If the current date/time is outside that range, the system **ignores** this price.

---

## Testing

The tests are **completely self-contained** and use **in-memory SQLite**. No external references to your application’s models or factories.

To run tests:

```bash
composer test
```

### How It Works

- The package includes its own `tests` directory, with a `TestCase` that sets up a **test database** in memory.
- Minimal “test models” (`ProductTestModel`, `UserTestModel`) are defined, along with tables for them.
- All package migrations (`src/database/migrations`) are loaded to test every feature.

This ensures the package is thoroughly verified in isolation.

---

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).

Enjoy using **MrNewport/LaravelPriceable** for your advanced pricing needs!
