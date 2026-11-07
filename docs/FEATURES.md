# Priceable feature index

| Public API → return | Behavior / failure | Guide |
| --- | --- | --- |
| `Priceable` trait `prices(): MorphMany`, `priceFor(int $quantity = 1, $user = null, array $scopes = []): ?float` | Unit price; host validates positive qty/ownership/currency. | [Quickstart](HANDBOOK.md#add-prices-to-a-product) |
| `PriceCalculator::getPrice(Model, int = 1, $user = null, array = []): ?float` | First matching user list, then default with same constraints; null/PriceNotFoundException on no match. | [Resolution](HANDBOOK.md#validity-and-missing-prices) |
| `PriceableFacade::getPrice(...)`, `Support\price_for(...)` | Facade resolves priceable.calculator; helper delegates to model priceFor. | [Entry points](HANDBOOK.md#add-prices-to-a-product) |
| `Price::priceable(): MorphTo`, `priceList(): BelongsTo`, `scopes(): HasMany` | Quantity/date/currency attributes; decimal DB storage, float API. | [Models](HANDBOOK.md#models-storage-and-configuration) |
| `PriceList::users(): BelongsToMany`, `prices(): HasMany` | Configured auth user model; user must define inverse ordered priceLists relation. | [Lists](HANDBOOK.md#customer-lists-and-priority) |
| `PriceScope::price(): BelongsTo` | Exact dimension row; duplicate dimensions require host prevention. | [Scopes](HANDBOOK.md#exact-scope-set-matching) |
| Quantity tiers, date windows | Greatest applicable min quantity; inclusive bounds; equal-tier ties unspecified. | [Tiers](HANDBOOK.md#add-prices-to-a-product), [validity](HANDBOOK.md#validity-and-missing-prices) |
| Scope set | Every dimension on separate row, exact row count; no less-specific fallback. | [Scopes](HANDBOOK.md#exact-scope-set-matching) |
| auto_load_migrations, fallback_to_default_price, throw_exception_if_no_price_found, default_currency | Exact current flag semantics and currency limitation. | [Config](HANDBOOK.md#models-storage-and-configuration) |
| PriceableServiceProvider, config/migrations publishing, priceable.calculator binding | Autodiscovery; default no autoload migrations; protected calculator extension methods. | [Install](HANDBOOK.md#install-in-a-fresh-laravel-application), [extension](HANDBOOK.md#models-storage-and-configuration) |
| Four migrations/user-pivot keys/cascade behavior | Review custom keys/retention and immutable accepted quotes. | [Upgrades](HANDBOOK.md#concurrency-upgrades-and-composition) |
| Stow/Fields/Workflow optional composition | Membership/form/review separate from current unit-price resolution. | [Recipes](RECIPES.md) |

Executable examples and assertions are in [PriceableTest](../tests/Unit/PriceableTest.php), [HelperTest](../tests/Unit/HelperTest.php) and [test models](../tests/Models). Run composer test from a package development checkout.
