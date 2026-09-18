<?php

namespace Tests\Unit;

use App\Models\CanonicalProduct;
use App\Models\Merchant;
use App\Models\Offer;
use App\Models\ProductIdentifier;
use App\Services\MarketAnalysis;
use App\Services\OpportunityCalculator;
use App\Services\ProductMatcher;
use App\Services\ProductNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_romanian_english_normalization_preserves_product_features(): void
    {
        $normalizer = app(ProductNormalizer::class);
        $this->assertSame('set 10 organizator haine stivuibil 26x32cm alb', $normalizer->normalize('Set 10 organizatoare tricouri stivuibile 26 × 32 cm, alb'));
        $features = $normalizer->features('Stackable clothes organizer 10 pcs 26x32cm');
        $this->assertSame(10, $features['pack_quantity']);
        $this->assertContains('26x32cm', $features['dimensions']);
    }

    public function test_exact_gtin_and_different_gtin_can_still_be_probable(): void
    {
        $product = CanonicalProduct::create(['name' => 'Set 10 organizatoare haine stivuibile 26x32cm', 'normalized_name' => 'set 10 organizator haine stivuibil 26x32cm', 'aliases' => ['Stackable clothes organizer 10 pcs 26x32cm']]);
        ProductIdentifier::create(['canonical_product_id' => $product->id, 'type' => 'gtin', 'value' => '4006381333931', 'normalized_value' => '04006381333931']);
        $matcher = app(ProductMatcher::class);
        $this->assertSame('exact', $matcher->compare(['title' => $product->name, 'ean' => '4006381333931'], $product)['classification']);
        $result = $matcher->compare(['title' => 'Stackable Clothes Organizer 10pcs 26x32cm', 'ean' => '9638507412340'], $product);
        $this->assertContains($result['classification'], ['probable', 'similar']);
        $this->assertSame('different', $result['signals']['gtin']);
    }

    public function test_market_deduplicates_merchant_and_calculates_median(): void
    {
        $merchant = Merchant::create(['name' => 'Seller SRL', 'normalized_name' => 'seller srl']);
        $offers = collect([10000, 12000, 20000])->map(fn ($price, $i) => new Offer(['merchant_id' => $merchant->id, 'seller' => $i === 2 ? 'Seller S.R.L.' : 'Seller SRL', 'price' => $price, 'shipping' => 0, 'currency' => 'RON', 'country' => 'RO', 'availability' => 'in_stock', 'checked_at' => now()]));
        $analysis = app(MarketAnalysis::class)->summarize($offers);
        $this->assertSame(1, $analysis['seller_count']);
        $this->assertSame(12000, $analysis['median']);
        $this->assertSame(20000, $analysis['maximum']);
    }

    public function test_opportunity_calculation_is_transparent(): void
    {
        $result = app(OpportunityCalculator::class)->calculate(['purchase_price' => 10000, 'quantity' => 10, 'inbound_shipping' => 10000, 'customs_cost' => 5000, 'other_costs' => 5000, 'outbound_shipping' => 1500, 'selling_price' => 20000]);
        $this->assertSame(12000, $result['landed_unit_cost']);
        $this->assertSame(13500, $result['break_even_price']);
        $this->assertSame(6500, $result['gross_margin_amount']);
        $this->assertSame(32.5, $result['gross_margin_percent']);
        $this->assertContains('TVA neconfirmat', $result['excluded_costs']);
    }
}
