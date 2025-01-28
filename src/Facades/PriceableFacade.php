<?php

namespace MrNewport\LaravelPriceable\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @method static float|null getPrice(Model $priceableModel, int $quantity = 1, $user = null, array $scopes = [])
 */
class PriceableFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'priceable.calculator';
    }
}
