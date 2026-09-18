<?php

namespace App\Services;

use App\Events\PriceThresholdReached;
use App\Models\Offer;
use Illuminate\Support\Facades\DB;

class AlertEvaluator
{
    public function evaluate(Offer $offer): void
    {
        DB::transaction(function () use ($offer) {
            $watch = $offer->watch()->lockForUpdate()->first();
            if (! $watch || $offer->total === null || $offer->availability !== 'in_stock') {
                return;
            }
            $below = $offer->total < $watch->threshold;
            if ($below && ! $watch->below) {
                $watch->episode++;
                DB::table('price_alerts')->insertOrIgnore(['watch_id' => $watch->id, 'episode' => $watch->episode, 'total' => $offer->total, 'created_at' => now()]);
                event(new PriceThresholdReached($watch->id, $watch->episode, $offer->total));
            }
            $watch->below = $below;
            $watch->save();
        });
    }
}
