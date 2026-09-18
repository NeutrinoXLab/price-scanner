<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Watch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['below' => 'boolean', 'threshold' => 'integer'];
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }
}
