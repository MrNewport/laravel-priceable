<?php

namespace MrNewport\LaravelPriceable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use MrNewport\LaravelPriceable\Models\Traits\Priceable;

class ExactPricedProductTestModel extends Model
{
    use Priceable;

    protected $table = 'exact_products';

    protected $guarded = [];

    public function prices(): MorphMany
    {
        return $this->morphMany(ExactPriceTestModel::class, 'priceable')->where('workspace_id', $this->workspace_id);
    }
}
