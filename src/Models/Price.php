<?php

namespace MrNewport\LaravelPriceable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Price extends Model
{
    protected $table = 'prices';

    protected $fillable = [
        'priceable_type',
        'priceable_id',
        'price_list_id',
        'min_quantity',
        'max_quantity',
        'unit_price',
        'currency',
        'valid_from',
        'valid_to',
    ];

    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * SCOPE: region, channel, or any other dimension.
     */
    public function scopes(): HasMany
    {
        return $this->hasMany(PriceScope::class);
    }
}
