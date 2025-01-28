<?php

namespace MrNewport\LaravelPriceable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use MrNewport\LaravelPriceable\Models\Traits\Priceable;

class ProductTestModel extends Model
{
    protected $table = 'test_products';

    protected $fillable = ['name'];

    use Priceable;
}
