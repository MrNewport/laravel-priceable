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
    - [Resolving an Exact Saved Record](#resolving-an-exact-saved-record)
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
- **User Price Lists**: Assign price lists to users; application relations enforce tenant ownership.
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
- `fallback_to_default_price`: Retains the legacy missing-price policy described below. Setting this to `false` does not suppress an existing default price.
- `throw_exception_if_no_price_found`: If no price is found (even after fallback), throw a `PriceNotFoundException` instead of returning `null`.
- `default_currency`: Retained configuration key; the resolver and model do not apply it. The supplied migration defaults the currency column to `USD`. Explicitly set and restrict currency in your application.

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

### Resolving an Exact Saved Record

Use `priceRecordFor()` when the caller needs the selected row's identity, currency,
or amount representation without the legacy float return conversion:

```php
$price = $product->priceRecordFor(
    quantity: 1,
    scopes: ['currency' => 'USD', 'room' => '101'],
    effectiveAt: new DateTimeImmutable('2026-12-01 12:00:00', new DateTimeZone('UTC')),
);

$savedAmount = $price?->getRawOriginal('unit_price');
```

The service equivalent is `PriceCalculator::resolvePrice()`, with the same
arguments after the product. Both return `?Price`, including an application-owned
subclass supplied by the product's `prices()` relationship. They retain quantity
tiers, user price-list order, exact scope matching, fallback policy, and inclusive
`valid_from`/`valid_to` boundaries. A null clock uses the existing current-time
selection and protected `findPrice()` extension hook. `priceFor()` and `getPrice()`
continue to return `?float` for existing callers.

An explicit-clock call uses the new protected `findPriceAt()` hook. Existing
application overrides of `findPrice()` do not govern that new path: put shared
ownership restrictions in the model's relation, or implement them in
`findPriceAt()` before exposing explicit-clock selection.

The `currency` scope in the example matches a `price_scopes` row, not the price's
`currency` column. Setting `default_currency` does not filter either API. Restrict
the relation to an allowed currency, or validate the selected record before
using it. Scope string comparison follows the database's configured collation;
exact scope matching means the same complete set of dimensions, not a promise
of case-sensitive comparison on every database.

Price lists are tried in their loaded relation order; define that order in your
user's `priceLists()` relationship. Within each list, the greatest matching
`min_quantity` wins, with inclusive minimum and maximum bounds. Equal-minimum
overlaps have no additional tie-break guarantee. Reject or order ambiguous
overlaps in the application rather than relying on insertion order.

The clock must use the same timezone convention as the stored validity timestamps.
It is bound using the database connection's timestamp format; no timezone
conversion or additional fractional-second precision is supplied. Pass a clock
already normalized to your stored convention.
The resolver does not round, convert currencies, cast amounts, write rows, or lock
the selected record. Exact money storage and casts remain the application's
responsibility: a value already stored as floating point cannot recover precision
by selecting its record. Applications can override `prices()` with a scoped
`MorphMany` relation to a `Price` subclass and override that model's `scopes()`
relation for private tables. Tenant authorization, currency eligibility, overlapping
rate policy, immutable quote snapshots, and transaction boundaries remain with the
application. Selecting a user price list does not itself enforce tenant access.

The record API does not evaluate the amount accessor. Reading `$price->unit_price`
afterward still invokes that model's configured casts/accessors; use
`getRawOriginal('unit_price')` for its raw saved representation. The supplied
schema uses `DECIMAL(12,4)`; SQLite numeric affinity may nevertheless return a
floating-point value. The exact-string tests use a custom text-backed relation
to prove preservation, not to claim that this API changes database storage.

## Fallback Logic & Exception Handling

Both APIs first try each user price list, then the default list
(`price_list_id = null`) using the same quantity, date and scope filters. A
matching default is returned even when `fallback_to_default_price` is `false`;
this existing behavior is preserved for compatibility.

Only when no record matches does configuration determine the result:

| `fallback_to_default_price` | `throw_exception_if_no_price_found` | No matching record |
| --- | --- | --- |
| `true` | `false` | `null` |
| `true` | `true` | `PriceNotFoundException` |
| `false` | either | `PriceNotFoundException` |

A saved zero price is a valid match. Amount validation remains with the host.

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

The Laravel 11 compatibility job runs historical framework code in a disposable CI runner. Its advisory install block is disabled for that job only; Laravel 12/13 jobs and consumer Composer settings keep their normal security checks. No application is deployed from this compatibility job.

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
