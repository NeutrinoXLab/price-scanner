<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOffer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['vat_included' => 'boolean', 'ships_to_romania' => 'boolean', 'raw_metadata' => 'array', 'last_verified_at' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(CanonicalProduct::class, 'canonical_product_id');
    }
}
