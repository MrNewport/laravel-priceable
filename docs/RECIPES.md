# Priceable recipes

## Wholesale contract with region and channel

Add the trait to a saved Product and an explicitly ordered priceLists relation to User. Create a contract list, attach only eligible users, then create a tier with scope rows region=US and channel=B2B. Price it with both trusted dimensions:

```php
$price = $product->priceFor(24, $buyer, ['region' => $shippingRegion, 'channel' => 'B2B']);
if ($price === null) {
    throw new DomainException('No applicable quote is available.');
}
```

Derive shippingRegion from the authorized reviewed destination and buyer from the session. A posted list ID or channel is not proof of entitlement. Apply your explicit list priority; the first matching list wins, not the lowest unit amount. Ensure the contract data uses one intended currency because the lookup has no currency selector.

## Time-limited promotion

```php
$product->prices()->create([
    'min_quantity' => 10, 'max_quantity' => 49,
    'unit_price' => '39.9500', 'currency' => 'USD',
    'valid_from' => now()->startOfDay(), 'valid_to' => now()->endOfDay(),
]);
```

Both date bounds are inclusive. Avoid equal-minimum overlapping candidates whose order is unspecified. Validate price precision, quantity ranges and timezone before saving. Freeze the clock in tests to check the instant before, at and after the window. A future invoice should use the accepted quote snapshot, not this changing current-time lookup.

## Quote Stow lines without moving pricing into baskets

```php
foreach ($basket->basketItems()->with('stowable')->get() as $line) {
    $product = $line->stowable;
    if ($product === null) {
        throw new DomainException('An item is no longer available.');
    }
    $unitPrice = $product->priceFor($line->quantity, $buyer, $trustedScopes);
    if ($unitPrice === null) {
        throw new DomainException('This item needs a reviewed quote.');
    }
    // Host money service rounds/converts under its explicit currency rule,
    // then persists the accepted line snapshot with quantity/context/revision.
}
```

Resolve the basket through its owner and use the host concurrency policy. Stow options are untrusted configuration references, not authoritative prices. The Priceable float is an input to an explicit money/rounding boundary; it does not supply tax, totals, inventory reservations, billing or payment execution. Failed quoting should leave the basket intact for review.

## Required contract-only pricing

Do not set fallback_to_default_price=false expecting found defaults to be excluded: the current release still returns them. Use an application pricing policy or a tested calculator extension that intentionally restricts eligible candidates, bound to priceable.calculator. Preserve the existing method signature and explicit no-price behavior. See [source-backed resolution rules](HANDBOOK.md#validity-and-missing-prices) and [tests](../tests/Unit/PriceableTest.php).
