<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductIdentifier extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['verified' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(CanonicalProduct::class, 'canonical_product_id');
    }
}
