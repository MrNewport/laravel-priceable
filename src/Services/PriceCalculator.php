<?php

namespace MrNewport\LaravelPriceable\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use MrNewport\LaravelPriceable\Exceptions\PriceNotFoundException;
use MrNewport\LaravelPriceable\Models\Price;

class PriceCalculator
{
    /**
     * Resolve final price for a model, given quantity, user, and scopes.
     */
    public function getPrice(Model $priceableModel, int $quantity = 1, $user = null, array $scopes = []): ?float
    {
        // 1. Gather the user's PriceLists if any
        $priceLists = $this->getUserPriceLists($user);

        // 2. Try each PriceList in order
        foreach ($priceLists as $list) {
            $price = $this->findPrice($priceableModel, $quantity, $list->id, $scopes);
            if ($price) {
                return $price->unit_price;
            }
        }

        // 3. Fallback to default (price_list_id = null)
        $fallbackPrice = $this->findPrice($priceableModel, $quantity, null, $scopes);

        // 4. Handle fallback or exception
        if (!$fallbackPrice) {
            if (
                config('priceable.fallback_to_default_price') === false ||
                config('priceable.throw_exception_if_no_price_found') === true
            ) {
                throw new PriceNotFoundException();
            }
            return null;
        }

        return $fallbackPrice->unit_price;
    }

    /**
     * Get all PriceLists for a user.
     */
    protected function getUserPriceLists($user): Collection
    {
        if (!$user || !method_exists($user, 'priceLists')) {
            return collect();
        }

        return $user->priceLists;
    }

    protected function findPrice(Model $priceableModel, int $quantity, ?int $priceListId, array $scopes): ?Price
    {
        $query = $priceableModel->prices()
            ->where('price_list_id', $priceListId)
            ->where('min_quantity', '<=', $quantity)
            ->where(function ($q) use ($quantity) {
                $q->whereNull('max_quantity')
                    ->orWhere('max_quantity', '>=', $quantity);
            })
            ->where(function ($q) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now());
            });

        // Exclude or include scoped prices properly
        if (empty($scopes)) {
            // If no scopes are passed, exclude all prices that have any scope
            $query->whereDoesntHave('scopes');
        } else {
            // If scopes are passed, ensure we only return prices that match all of them
            $query->has('scopes', '=', count($scopes));
            foreach ($scopes as $type => $value) {
                $query->whereHas('scopes', function ($scopeQuery) use ($type, $value) {
                    $scopeQuery->where('scope_type', $type)
                        ->where('scope_value', $value);
                });
            }
        }

        // Return the best match
        return $query->orderBy('min_quantity', 'desc')->first();
    }

}
