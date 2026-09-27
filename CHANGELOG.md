# Changelog

## 1.2.0 — 2026-09-26

- Add `PriceCalculator::resolvePrice()` and `priceRecordFor()` to return the selected saved `Price` (or application subclass) without evaluating or converting its amount.
- Add an explicit `DateTimeInterface` selection clock through `findPriceAt()`, preserving the null-clock legacy `findPrice()` extension hook and float wrappers.
- Document currency, timezone, storage precision, scope collation and list/tier ordering boundaries, including the unchanged legacy fallback behavior.
- No schema, stored-data, dependency or default-policy changes. Existing `findPrice()` overrides must move shared restrictions into the relation or new hook before using explicit-clock selection.

## 1.1.1 — 2026-09-12

Fix the namespaced helper guard so an application-level `price_for` function cannot suppress the package helper. Requiring the helper file again is safe. Revalidated on Laravel 12 and 13.31 with a clean production consumer.

## 1.1.0

- Support Laravel 12 and 13, and declare the Eloquent database dependency directly.
- Fix prices with multiple scope dimensions: region and channel are matched against separate rows. Partial or mismatched scopes cannot select a more restricted price.
- Give the quantity index a name within MySQL's 64-character limit; this brings the existing Sinstrum migration fix into the shared package. Existing installations do not need an index rename.
- Autoload the documented price_for helper and correct the facade example.
- Add reproducible package tests and Laravel 11/12/13 and MySQL 8.4 CI coverage.
