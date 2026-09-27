<?php

namespace MrNewport\LaravelPriceable\Tests\Models;

use Illuminate\Database\Eloquent\Relations\MorphMany;

class CurrencyPricedProductTestModel extends ExactPricedProductTestModel
{
    public function prices(): MorphMany
    {
        return parent::prices()->where('currency', 'USD');
    }
}
