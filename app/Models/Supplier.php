<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['b2b_registration_required' => 'boolean'];
    }

    public function offers(): HasMany
    {
        return $this->hasMany(SupplierOffer::class);
    }
}
