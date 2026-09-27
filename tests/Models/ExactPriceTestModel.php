<?php

namespace MrNewport\LaravelPriceable\Tests\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use MrNewport\LaravelPriceable\Models\Price;

class ExactPriceTestModel extends Price
{
    protected $table = 'exact_prices';

    protected $guarded = [];

    protected $fillable = [];

    public function scopes(): HasMany
    {
        return $this->hasMany(ExactPriceScopeTestModel::class, 'price_id');
    }
}
