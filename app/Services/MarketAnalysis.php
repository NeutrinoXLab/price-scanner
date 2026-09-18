<?php

namespace App\Services;

use Illuminate\Support\Collection;

class MarketAnalysis
{
    public function summarize(Collection $offers, string $country = 'RO'): array
    {
        $eligible = $offers->filter(fn ($offer) => $offer->country === $country && $offer->availability === 'in_stock');
        $prices = $eligible->map(fn ($offer) => $offer->total ?? $offer->price)->sort()->values();
        $merchants = $eligible->map(fn ($offer) => $offer->merchant_id ? 'merchant:'.$offer->merchant_id : 'seller:'.app(ProductNormalizer::class)->normalize($offer->seller))->unique();

        return [
            'offer_count' => $eligible->count(), 'seller_count' => $merchants->count(),
            'minimum' => $prices->min(), 'maximum' => $prices->max(),
            'average' => $prices->count() ? (int) round($prices->average()) : null,
            'median' => $this->median($prices), 'first_seen' => $offers->min('created_at'),
            'last_seen' => $offers->max('checked_at'),
        ];
    }

    private function median(Collection $values): ?int
    {
        $count = $values->count();
        if (! $count) {
            return null;
        }
        $middle = intdiv($count, 2);

        return $count % 2 ? (int) $values[$middle] : (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }
}
