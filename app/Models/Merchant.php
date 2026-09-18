<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['aliases' => 'array'];
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
