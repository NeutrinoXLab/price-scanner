<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricePoint extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['observed_at' => 'datetime', 'price' => 'integer', 'shipping' => 'integer'];
    }
}
