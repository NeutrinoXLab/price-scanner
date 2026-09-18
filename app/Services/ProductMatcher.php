<?php

namespace App\Services;

use App\Models\CanonicalProduct;

class ProductMatcher
{
    public function __construct(private ProductNormalizer $normalizer, private Matcher $gtins) {}

    public function compare(array $offer, CanonicalProduct $product): array
    {
        $signals = [];
        $score = 0;
        $offerGtin = $this->gtins->gtin((string) ($offer['ean'] ?? ''));
        $identifiers = $product->relationLoaded('identifiers') ? $product->identifiers : $product->identifiers()->get();
        $productGtins = $identifiers->where('type', 'gtin')->pluck('normalized_value')->all();
        if ($offerGtin && in_array($offerGtin, $productGtins, true)) {
            $signals['gtin'] = 'exact';
            $score += 100;
        } elseif ($offerGtin && $productGtins) {
            $signals['gtin'] = 'different';
        }
        $offerModel = $this->normalizer->normalize((string) ($offer['mpn'] ?? $offer['model'] ?? ''));
        $productModel = $this->normalizer->normalize((string) ($product->mpn ?? $product->model ?? ''));
        if ($offerModel !== '' && $productModel !== '' && $offerModel === $productModel) {
            $signals['model'] = 'exact';
            $score += 65;
        }
        $a = $this->normalizer->features((string) ($offer['title'] ?? ''));
        $b = $this->normalizer->features(implode(' ', array_filter([$product->name, ...($product->aliases ?? [])])));
        $union = array_unique([...$a['tokens'], ...$b['tokens']]);
        $similarity = $union ? count(array_intersect($a['tokens'], $b['tokens'])) / count($union) : 0;
        $signals['token_similarity'] = round($similarity, 3);
        $score += (int) round($similarity * 45);
        if ($a['dimensions'] && $b['dimensions']) {
            $same = (bool) array_intersect($a['dimensions'], $b['dimensions']);
            $signals['dimensions'] = $same ? 'same' : 'conflict';
            $score += $same ? 15 : -35;
        }
        if ($a['pack_quantity'] && $b['pack_quantity']) {
            $same = $a['pack_quantity'] === $b['pack_quantity'];
            $signals['pack_quantity'] = $same ? 'same' : 'conflict';
            $score += $same ? 15 : -40;
        }
        $score = max(0, min(100, $score));
        $classification = match (true) {
            ($signals['gtin'] ?? null) === 'exact' => 'exact',
            ($signals['model'] ?? null) === 'exact' && ! in_array('conflict', $signals, true) => 'exact',
            $score >= 70 && ! in_array('conflict', $signals, true) => 'probable',
            $score >= 35 => 'similar',
            default => 'unknown',
        };

        return compact('classification', 'score', 'signals') + ['confidence' => $score];
    }
}
