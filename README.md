# Laravel Priceable

Quantity tiers, ordered customer price lists, validity windows and exact scope-set unit-price resolution for Eloquent models. The nullable float result is a current quote input; currency policy, accepted snapshots and accounting stay in the host.

```sh
composer require mrnewport/laravel-priceable:^1.2
```

If your Composer registry has not indexed the GitHub tag, add its VCS source and repeat the requirement:

```sh
composer config repositories.laravel-priceable vcs https://github.com/MrNewport/laravel-priceable
composer require mrnewport/laravel-priceable:^1.2
```

Migrations are not auto-loaded by default. Publish the provider-specific `config` and `migrations` tags or enable `priceable.auto_load_migrations`, then migrate. Read the handbook for user-key compatibility, currency selection and exact fallback-flag behavior.

Start with the [handbook](docs/HANDBOOK.md) for fresh Laravel 12/13 installation and the complete feature/operational guide. The [feature index](docs/FEATURES.md) links every public API, return contract, failure mode and executable reference. The [recipes](docs/RECIPES.md) show serious standalone and optional cross-package application use.

Laravel's own PHP runtime requirements apply. Package dependency constraints and current test coverage are described in the handbook. Run `composer test` in a development checkout; optional browser/renderer checks are documented with their requirements. Installing this package does not automatically integrate another package or authorize application records.
