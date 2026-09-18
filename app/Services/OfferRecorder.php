<?php

namespace App\Services;

use App\Models\CanonicalProduct;
use App\Models\Merchant;
use App\Models\Offer;
use App\Models\OfferMatch;
use Illuminate\Support\Facades\DB;

class OfferRecorder
{
    public function __construct(private ProductNormalizer $normalizer, private ProductMatcher $matcher) {}

    public function record(string $source, array $data): Offer
    {
        return DB::transaction(function () use ($source, $data) {
            $merchant = Merchant::firstOrCreate(
                ['normalized_name' => $this->normalizer->normalize($data['seller'])],
                ['name' => $data['seller'], 'country' => $data['country'] ?? 'RO']
            );
            $offer = Offer::firstOrNew(['source' => $source, 'external_id' => $data['external_id'], 'seller' => $data['seller']]);
            $offer->fill($data + [
                'merchant_id' => $merchant->id,
                'normalized_title' => $this->normalizer->normalize($data['title']),
                'country' => $data['country'] ?? 'RO',
                'raw_metadata' => $data['raw_metadata'] ?? $data,
                'checked_at' => now(),
            ]);
            $offer->save();
            $last = $offer->points()->reorder()->latest('id')->first();
            if (! $last || $last->price !== $offer->price || $last->shipping !== $offer->shipping || $last->availability !== $offer->availability) {
                $offer->points()->create(['price' => $offer->price, 'shipping' => $offer->shipping, 'currency' => $offer->currency, 'availability' => $offer->availability, 'observed_at' => now()]);
            }
            $this->match($offer);
            app(AlertEvaluator::class)->evaluate($offer);

            return $offer;
        });
    }

    private function match(Offer $offer): void
    {
        $best = null;
        foreach (CanonicalProduct::with('identifiers')->limit(200)->get() as $product) {
            $result = $this->matcher->compare($offer->toArray(), $product);
            if ($best === null || $result['confidence'] > $best['result']['confidence']) {
                $best = compact('product', 'result');
            }
        }
        if (! $best || $best['result']['classification'] === 'unknown') {
            return;
        }
        if ($best['result']['classification'] === 'exact') {
            $offer->canonical_product_id = $best['product']->id;
            $offer->save();
        }
        OfferMatch::updateOrCreate(['offer_id' => $offer->id], [
            'canonical_product_id' => $best['product']->id,
            'classification' => $best['result']['classification'],
            'confidence' => $best['result']['confidence'],
            'signals' => $best['result']['signals'],
            'review_status' => $best['result']['classification'] === 'exact' ? 'automatic' : 'pending',
        ]);
    }
}
