<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferMatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['signals' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(CanonicalProduct::class, 'canonical_product_id');
    }
}
