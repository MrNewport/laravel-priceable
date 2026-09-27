<?php

namespace MrNewport\LaravelPriceable\Services;

use DateTimeInterface;
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
        return $this->resolvePrice($priceableModel, $quantity, $user, $scopes)?->unit_price;
    }

    /**
     * Resolve the saved price record without converting its amount to a float.
     *
     * The effective clock uses the same timezone as the stored validity timestamps.
     * A null clock retains the existing current-time and findPrice override behavior.
     */
    public function resolvePrice(Model $priceableModel, int $quantity = 1, $user = null, array $scopes = [], ?DateTimeInterface $effectiveAt = null): ?Price
    {
        $resolve = fn (?int $listId) => $effectiveAt === null
            ? $this->findPrice($priceableModel, $quantity, $listId, $scopes)
            : $this->findPriceAt($priceableModel, $quantity, $listId, $scopes, $effectiveAt);
        // 1. Gather the user's PriceLists if any
        $priceLists = $this->getUserPriceLists($user);

        // 2. Try each PriceList in order
        foreach ($priceLists as $list) {
            $price = $resolve($list->id);
            if ($price) {
                return $price;
            }
        }

        // 3. Fallback to default (price_list_id = null)
        $fallbackPrice = $resolve(null);

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

        return $fallbackPrice;
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
        return $this->findPriceAt($priceableModel, $quantity, $priceListId, $scopes, now());
    }

    /** Resolve at an explicit clock without changing the legacy protected hook signature. */
    protected function findPriceAt(Model $priceableModel, int $quantity, ?int $priceListId, array $scopes, DateTimeInterface $effectiveAt): ?Price
    {
        $query = $priceableModel->prices()
            ->where('price_list_id', $priceListId)
            ->where('min_quantity', '<=', $quantity)
            ->where(function ($q) use ($quantity) {
                $q->whereNull('max_quantity')
                    ->orWhere('max_quantity', '>=', $quantity);
            })
            ->where(function ($q) use ($effectiveAt) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', $effectiveAt);
            })
            ->where(function ($q) use ($effectiveAt) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $effectiveAt);
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
