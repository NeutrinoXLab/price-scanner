<?php

namespace App\Http\Controllers;

use App\Models\CanonicalProduct;
use App\Models\Offer;
use App\Models\OfferMatch;
use App\Models\ProductIdentifier;
use App\Services\MarketAnalysis;
use App\Services\Matcher;
use App\Services\OpportunityCalculator;
use App\Services\ProductNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function createFromOffer(Request $request, Offer $offer, ProductNormalizer $normalizer, Matcher $gtins): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255', 'brand' => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:100', 'model' => 'nullable|string|max:100',
            'mpn' => 'nullable|string|max:100', 'category' => 'nullable|string|max:100',
            'aliases' => 'nullable|string|max:1000',
        ]);
        $product = DB::transaction(function () use ($data, $offer, $normalizer, $gtins) {
            $aliases = collect(preg_split('/[\r\n]+/', $data['aliases'] ?? ''))->map(fn ($v) => trim($v))->filter()->values()->all();
            $product = CanonicalProduct::create(array_merge($data, ['normalized_name' => $normalizer->normalize($data['name']), 'aliases' => $aliases]));
            if ($value = $gtins->gtin((string) $offer->ean)) {
                ProductIdentifier::create(['canonical_product_id' => $product->id, 'type' => 'gtin', 'value' => $offer->ean, 'normalized_value' => $value, 'source' => $offer->source, 'verified' => true]);
            }
            $offer->update(['canonical_product_id' => $product->id]);
            OfferMatch::updateOrCreate(['offer_id' => $offer->id], ['canonical_product_id' => $product->id, 'classification' => 'exact', 'confidence' => 100, 'signals' => ['manual' => true], 'review_status' => 'approved', 'reviewed_at' => now()]);

            return $product;
        });

        return redirect()->route('products.show', $product)->with('message', 'Produsul canonic a fost creat, iar oferta a fost legată manual.');
    }

    public function show(CanonicalProduct $product, MarketAnalysis $analysis): View
    {
        $product->load(['identifiers', 'offers.merchant', 'offers.productMatch', 'supplierOffers.supplier']);

        return view('product', ['product' => $product, 'market' => $analysis->summarize($product->offers), 'calculation' => session('calculation')]);
    }

    public function review(Request $request, OfferMatch $match): RedirectResponse
    {
        $data = $request->validate(['decision' => 'required|in:approved,rejected', 'note' => 'nullable|string|max:1000']);
        DB::transaction(function () use ($match, $data) {
            $match->update(['review_status' => $data['decision'], 'review_note' => $data['note'] ?? null, 'reviewed_at' => now()]);
            $match->offer()->update(['canonical_product_id' => $data['decision'] === 'approved' ? $match->canonical_product_id : null]);
        });

        return back()->with('message', 'Decizia de matching a fost salvată.');
    }

    public function opportunity(Request $request, CanonicalProduct $product, OpportunityCalculator $calculator): RedirectResponse
    {
        $data = $request->validate([
            'purchase_price' => 'required|numeric|min:0|max:999999999', 'quantity' => 'required|integer|min:1|max:1000000',
            'inbound_shipping' => 'nullable|numeric|min:0', 'customs_cost' => 'nullable|numeric|min:0',
            'other_costs' => 'nullable|numeric|min:0', 'outbound_shipping' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0', 'currency' => 'required|in:RON,EUR,USD,PLN',
            'customs_included' => 'nullable|boolean', 'vat_included' => 'nullable|boolean',
        ]);
        $money = fn ($key) => isset($data[$key]) && $data[$key] !== '' ? (int) round((float) $data[$key] * 100) : 0;
        $result = $calculator->calculate([
            'purchase_price' => $money('purchase_price'), 'quantity' => $data['quantity'],
            'inbound_shipping' => $money('inbound_shipping'), 'customs_cost' => $money('customs_cost'),
            'other_costs' => $money('other_costs'), 'outbound_shipping' => $money('outbound_shipping'),
            'selling_price' => isset($data['selling_price']) && $data['selling_price'] !== '' ? $money('selling_price') : null,
            'customs_included' => $data['customs_included'] ?? false, 'vat_included' => $data['vat_included'] ?? false,
        ]) + ['currency' => $data['currency']];

        return back()->withInput()->with('calculation', $result);
    }
}
