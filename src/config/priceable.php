<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto-Load Migrations
    |--------------------------------------------------------------------------
    */
    'auto_load_migrations' => false,

    /*
    |--------------------------------------------------------------------------
    | Default Fallback Logic
    |--------------------------------------------------------------------------
    |
    | Compatibility: the default list is always searched after user lists.
    | False throws when no record matches; it does not suppress an existing
    | default record. See the README missing-price policy table.
    |
    */
    'fallback_to_default_price' => true,

    /*
    |--------------------------------------------------------------------------
    | Throw Exception If No Price Found
    |--------------------------------------------------------------------------
    |
    | True throws when no user-list or default record matches. When false,
    | null is returned only if fallback_to_default_price is also true.
    |
    */
    'throw_exception_if_no_price_found' => false,

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | Retained configuration only; the resolver does not filter currency or
    | apply this value to models. The supplied migration defaults to USD.
    | Applications must explicitly set and restrict their allowed currencies.
    |
    */
    'default_currency' => 'USD',

];
