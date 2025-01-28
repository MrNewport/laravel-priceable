<?php

namespace MrNewport\LaravelPriceable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceScope extends Model
{
    protected $table = 'price_scopes';

    protected $fillable = [
        'price_id',
        'scope_type',
        'scope_value',
    ];

    public function price(): BelongsTo
    {
        return $this->belongsTo(Price::class);
    }
}
