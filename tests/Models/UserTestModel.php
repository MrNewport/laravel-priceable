<?php

namespace MrNewport\LaravelPriceable\Tests\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use MrNewport\LaravelPriceable\Models\PriceList;

class UserTestModel extends Authenticatable
{
    protected $table = 'users';

    public function priceLists(): BelongsToMany
    {
        // Explicitly define pivot columns to avoid "user_test_model_id" mismatch:
        return $this->belongsToMany(
            PriceList::class,
            'price_list_user', // pivot table
            'user_id',         // foreign key on pivot
            'price_list_id'    // related key on pivot
        );
    }
}

