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
    | If a specific price isn't found in a user's PriceList(s), we can
    | fallback to the default price (price_list_id = null).
    |
    */
    'fallback_to_default_price' => true,

    /*
    |--------------------------------------------------------------------------
    | Throw Exception If No Price Found
    |--------------------------------------------------------------------------
    |
    | If we can't find a price (and fallback is disabled), do we throw an
    | exception or return null?
    |
    */
    'throw_exception_if_no_price_found' => false,

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    */
    'default_currency' => 'USD',

];
