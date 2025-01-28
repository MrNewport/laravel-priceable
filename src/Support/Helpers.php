<?php

namespace MrNewport\LaravelPriceable\Support;

use Illuminate\Database\Eloquent\Model;

if (!function_exists('price_for')) {
    function price_for(Model $model, int $quantity = 1, $user = null, array $scopes = []): ?float
    {
        return $model->priceFor($quantity, $user, $scopes);
    }
}
