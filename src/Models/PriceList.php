<?php

namespace MrNewport\LaravelPriceable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends Model
{
    protected $table = 'price_lists';

    protected $fillable = [
        'name',
        'description',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('auth.providers.users.model'),
            'price_list_user'
        );
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }
}
