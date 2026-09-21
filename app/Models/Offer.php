<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Offer extends Model
{
    protected $fillable = [
        'canonical_product_id', 'merchant_id', 'source', 'external_id', 'seller', 'title', 'description',
        'category', 'normalized_title', 'url', 'image_url', 'ean', 'brand', 'model', 'mpn', 'sku',
        'price', 'old_price', 'shipping', 'currency', 'country', 'channel', 'vat_included', 'moq',
        'pack_quantity', 'availability', 'attributes', 'raw_metadata', 'notes', 'access_method',
        'source_updated_at', 'checked_at',
    ];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'source_updated_at' => 'datetime', 'price' => 'integer', 'old_price' => 'integer', 'shipping' => 'integer', 'vat_included' => 'boolean', 'moq' => 'integer', 'pack_quantity' => 'integer', 'attributes' => 'array', 'raw_metadata' => 'array'];
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
