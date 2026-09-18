<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CanonicalProduct extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['attributes' => 'array', 'aliases' => 'array'];
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(ProductIdentifier::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function supplierOffers(): HasMany
    {
        return $this->hasMany(SupplierOffer::class);
    }
}
