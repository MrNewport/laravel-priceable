<?php

namespace MrNewport\LaravelPriceable\Models\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use MrNewport\LaravelPriceable\Models\Price;
use MrNewport\LaravelPriceable\Services\PriceCalculator;

trait Priceable
{
    /**
     * Polymorphic relationship to the Price model.
     */
    public function prices(): MorphMany
    {
        return $this->morphMany(Price::class, 'priceable');
    }

    /**
     * Get a price, factoring in quantity, user, and any scopes.
     */
    public function priceFor(int $quantity = 1, $user = null, array $scopes = []): ?float
    {
        return app('priceable.calculator')->getPrice($this, $quantity, $user, $scopes);
    }
}
