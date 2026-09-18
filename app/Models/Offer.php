<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Offer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'price' => 'integer', 'old_price' => 'integer', 'shipping' => 'integer', 'attributes' => 'array', 'raw_metadata' => 'array'];
    }

    public function points()
    {
        return $this->hasMany(PricePoint::class)->orderBy('id');
    }

    public function watch()
    {
        return $this->hasOne(Watch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(CanonicalProduct::class, 'canonical_product_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function productMatch(): HasOne
    {
        return $this->hasOne(OfferMatch::class);
    }

    public function getTotalAttribute(): ?int
    {
        return $this->shipping === null ? null : $this->price + $this->shipping;
    }
}
