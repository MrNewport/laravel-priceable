# Laravel Priceable handbook

Priceable selects a unit price from quantity tiers, user price lists, validity windows and an exact set of scope dimensions. It returns a nullable float. The host owns authorization, currency selection, accepted quote snapshots, arithmetic, tax, checkout and billing.

## Install in a fresh Laravel application

```sh
composer create-project laravel/laravel pricing-app '^12.0'
cd pricing-app
composer require mrnewport/laravel-priceable:^1.2
php artisan vendor:publish --provider='MrNewport\LaravelPriceable\Providers\PriceableServiceProvider' --tag=config
php artisan vendor:publish --provider='MrNewport\LaravelPriceable\Providers\PriceableServiceProvider' --tag=migrations
php artisan migrate
```

Use `'^13.0'` and its supported PHP runtime for Laravel 13. The package declares Laravel 9–13/PHP ^8.0, while current CI covers Laravel 11–13; Laravel's own PHP floor applies. If a registry has not indexed the GitHub tag, add `composer config repositories.priceable vcs https://github.com/MrNewport/laravel-priceable` and repeat the requirement.

The provider auto-discovers and binds singleton `priceable.calculator`. Migrations are **not automatically loaded by default**. Publish them as above, or set `priceable.auto_load_migrations = true` before migration; avoid duplicate published/package migration registration. Review the published migration because the users pivot assumes a `users` table with bigint IDs. Product polymorphic IDs also use integer morph columns. UUID/custom-user schemas require a reviewed compatible migration; changing a model class alone does not change database columns. No routes, UI or frontend build are required.

## Add prices to a product

```php
class Product extends \Illuminate\Database\Eloquent\Model
{
    use \MrNewport\LaravelPriceable\Models\Traits\Priceable;
}

$product->prices()->create([
    'min_quantity' => 1, 'max_quantity' => 9,
    'unit_price' => '100.0000', 'currency' => 'USD',
]);
$product->prices()->create([
    'min_quantity' => 10, 'max_quantity' => null,
    'unit_price' => '80.0000', 'currency' => 'USD',
]);
$product->priceFor(5);  // 100.0
$product->priceFor(12); // 80.0 (unit price, not line total)
```

Use positive integer quantities validated in the host. The calculator signature takes int but does not enforce a positive-domain quantity or clamp it. Choose nonoverlapping tiers and validate min/max consistency before saving. A null max has no upper bound. Where several applicable tiers overlap, greatest min_quantity wins; equal min_quantity ties have no explicit secondary ordering, so do not rely on insertion order as a pricing policy.

`Priceable::prices(): MorphMany` exposes saved Price rows. `priceFor(int $quantity = 1, $user = null, array $scopes = []): ?float` delegates to the container calculator. Alternate entry points share that behavior:

```php
use MrNewport\LaravelPriceable\Facades\PriceableFacade;
use MrNewport\LaravelPriceable\Services\PriceCalculator;
use function MrNewport\LaravelPriceable\Support\price_for;

$amount = PriceableFacade::getPrice($product, 12, $user, ['region' => 'US']);
$amount = app('priceable.calculator')->getPrice($product, 12, $user, ['region' => 'US']);
$amount = price_for($product, 12, $user, ['region' => 'US']);
```

The helper is Composer-autoloaded and calls the model's priceFor, so the host model must implement it (normally through the trait). The calculator's public signature is `getPrice(Model, int = 1, $user = null, array = []): ?float`.

## Customer lists and priority

Create PriceList and link users:

```php
use MrNewport\LaravelPriceable\Models\PriceList;

$contract = PriceList::create(['name' => 'Contract A', 'description' => 'Reviewed account terms']);
$contract->users()->attach($user->getKey());
$product->prices()->create([
    'price_list_id' => $contract->id, 'min_quantity' => 1,
    'unit_price' => '70.0000', 'currency' => 'USD',
]);
```

The user must expose a priceLists relation; the package does not add one automatically:

```php
public function priceLists(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
{
    return $this->belongsToMany(
        \MrNewport\LaravelPriceable\Models\PriceList::class,
        'price_list_user', 'user_id', 'price_list_id'
    )->orderBy('price_lists.id');
}
```

The calculator accesses `$user->priceLists` in its existing collection order and returns the first list with an applicable price. It does not search all lists for the cheapest amount. Define an explicit application priority/order and refresh a previously loaded relation when membership changes. A null user or an object without a priceLists method contributes no custom lists. PriceList::users() uses `auth.providers.users.model`; multi-provider applications must review that binding. Lists do not establish tenancy or grant access to products: authorize the user/product/scope before pricing.

## Exact scope-set matching

Scopes live on PriceScope rows. Each dimension is matched on its own row and the total count must equal the supplied dimension count:

```php
$price = $product->prices()->create([
    'min_quantity' => 1, 'unit_price' => '75.0000', 'currency' => 'USD',
]);
$price->scopes()->createMany([
    ['scope_type' => 'region', 'scope_value' => 'US'],
    ['scope_type' => 'channel', 'scope_value' => 'B2B'],
]);
$product->priceFor(1, null, ['region' => 'US', 'channel' => 'B2B']); // 75.0
$product->priceFor(1, null, ['region' => 'US']); // this price does not match
$product->priceFor(1); // this scoped price does not match
```

The empty scope set only accepts unscoped prices. A supplied nonempty set does not fall back to a less-restricted unscoped price: the default-list query uses the same exact scope set. Extra dimensions on either side prevent this candidate from matching. Keys/order in the associative caller array do not change dimension matching. Database collation governs string comparison; canonicalize permitted region/channel values in the host. Prevent duplicate scope dimensions when creating prices because the package migration has no unique dimension constraint and row-count equality is intentional.

## Validity and missing prices

A candidate must satisfy inclusive bounds: min_quantity ≤ quantity; max_quantity null or ≥ quantity; valid_from null or ≤ now(); valid_to null or ≥ now(). Both current-time conditions are computed during query construction. Store a consistent timezone and test exact boundary behavior. There is no as-of argument; for deterministic tests use Carbon's frozen clock. For historical quotes retain the original accepted amount and context instead of calling a current-price function later.

The actual lookup order is:

1. Each user price list in collection order, with quantity/date/exact scopes.
2. Default-list price (`price_list_id = null`) with the same filters.
3. If no default exists, throw PriceNotFoundException when `fallback_to_default_price === false` **or** `throw_exception_if_no_price_found === true`; otherwise return null.

`fallback_to_default_price=false` does not suppress a **found** default in this release; it changes no-price failure behavior. `throw_exception_if_no_price_found=true` likewise acts only when no candidate exists. Do not use those settings to implement a contractual “customer list only” rule. Validate that requirement in a host pricing service or extend/rebind the calculator's tested abstraction deliberately. Check null explicitly; a legitimate zero unit price is not missing.

## Models, storage and configuration

| Model / table | Attributes and relationships |
| --- | --- |
| Price / prices | priceable_type/id, nullable price_list_id, min/max_quantity, decimal(12,4) unit_price, currency (DB default USD), nullable valid_from/to, timestamps. `priceable(): MorphTo`, `priceList(): BelongsTo`, `scopes(): HasMany`. |
| PriceList / price_lists | Unique name, description, timestamps. `users(): BelongsToMany`, `prices(): HasMany`. |
| PriceScope / price_scopes | price_id, scope_type, scope_value, timestamps. `price(): BelongsTo`. |
| price_list_user | user/list IDs, unique pair, no pivot timestamps in package migration. |

Models declare fillable attributes but no monetary/date casts beyond normal Eloquent behavior. The public calculator converts the selected unit_price to its declared float return type. Exact cents/decimal accounting and line-total rounding require a host rule; returning a float does not provide a money object. Currency is stored metadata but **not filtered by the calculator**. default_currency is configuration metadata, while the bundled database default is USD; set currency explicitly when creating records and separate/validate your price universe before using multiple currencies. The API does not convert exchange rates.

Configuration: auto_load_migrations=false, fallback_to_default_price=true, throw_exception_if_no_price_found=false, default_currency=USD. Use Laravel config cache after deployment changes. Provider publishing tags are generic config/migrations, so specify the provider when publishing. The facade resolves priceable.calculator; bind a compatible calculator implementation there to extend supported policy while reusing the trait/entry points. Protected getUserPriceLists/findPrice are the existing extension methods; preserve quantity/date/exact-scope semantics with tests.

## Concurrency, upgrades and composition

Price selection is a read query and creates no quote lock or transaction. At order acceptance, authorize the product and customer's list entitlement, resolve trusted scopes, obtain the current quote under your revision/transaction policy and save immutable amount/currency/quantity/context. Do not trust a posted float or rerun today's rules to reinterpret a saved invoice. Price-list changes and catalog writes need host audit/version/concurrency rules.

Before upgrading test saved rows, null/zero prices, list priority, overlapping tiers, exact multi-scope rows, validity bounds, currency assumptions and user-key compatibility. Preserve accepted quotes and ownership. Migrations create tables and cascade list→prices and price→scopes on hard deletion; review retention before deleting contracts. [RECIPES.md](RECIPES.md) gives wholesale, promotion and Stow quotation patterns; [FEATURES.md](FEATURES.md) maps all public APIs. Run `composer test` for isolated tests.

Stow can supply integer line quantities and membership; Priceable can resolve their applicable current unit prices. The host still owns checkout and snapshots. Fields can validate administrator inputs, while Flow/Workflow can approve a price change before publication. None is required for standalone Priceable.
