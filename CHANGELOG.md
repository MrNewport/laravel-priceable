# Changelog

## 1.1.0

- Support Laravel 12 and 13, and declare the Eloquent database dependency directly.
- Fix prices with multiple scope dimensions: region and channel are matched against separate rows. Partial or mismatched scopes cannot select a more restricted price.
- Give the quantity index a name within MySQL's 64-character limit; this brings the existing Sinstrum migration fix into the shared package. Existing installations do not need an index rename.
- Autoload the documented price_for helper and correct the facade example.
- Add reproducible package tests and Laravel 11/12/13 and MySQL 8.4 CI coverage.
