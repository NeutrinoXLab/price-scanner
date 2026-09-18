<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Models\User;
use App\Models\Watch;
use App\Services\OfferRecorder;
use App\Services\Providers\CsvFeedProvider;
use App\Services\SourceSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_public_home_is_available_outside_localhost(): void
    {
        auth()->logout();

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.10'])->get('/')
            ->assertOk()
            ->assertSee('PRICE COMPARISON SERVICE')
            ->assertSee('NOVELION S.R.L.')
            ->assertSee('sursele comerciale sunt în curs de conectare');
    }

    public function test_punctuation_query_does_not_match_everything(): void
    {
        $this->offer();
        $this->get('/?q=!!!')->assertOk()->assertSee('0 rezultate');
    }

    public function test_import_requires_approval_and_records_provenance(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'scanner-test-');
        file_put_contents($file, $this->csv());
        try {
            $this->artisan('scanner:import', ['source' => 'libris', 'file' => $file])->assertExitCode(1);
            $this->artisan('scanner:import', ['source' => 'libris', 'file' => $file, '--approved' => true])->assertExitCode(0);
            $this->assertDatabaseHas('offers', ['access_method' => 'local_file']);
            $this->get('/offers/'.Offer::first()->id)->assertSee('Data indică importul');
        } finally {
            unlink($file);
        }
    }

    private function row(array $changes = []): array
    {
        return array_replace(['external_id' => 'fixture-1', 'seller' => 'Test seller', 'title' => 'Test Product', 'url' => 'https://example.com/item', 'ean' => '4006381333931', 'model' => 'TEST-1', 'sku' => 'T1', 'price' => 10000, 'shipping' => 1000, 'currency' => 'RON', 'availability' => 'in_stock'], $changes);
    }

    private function offer(array $changes = []): Offer
    {
        return app(OfferRecorder::class)->record('libris', $this->row($changes));
    }

    private function csv(): string
    {
        return "external_id,seller,title,url,price,shipping,currency,availability,ean,model,sku\nfixture-1,Test seller,Test Product,https://example.com/item,100.00,10.00,RON,in_stock,4006381333931,TEST-1,T1\n";
    }

    public function test_home_is_honest_without_sources(): void
    {
        $this->get('/')->assertOk()->assertSee('Neactivat')->assertSee('Catalogul comercial nu este încă populat');
        $this->get('/?q=test')->assertOk()->assertSee('Nu există încă oferte autorizate');
    }

    public function test_exact_and_similar_are_separate_and_unknown_shipping_is_not_zero(): void
    {
        $this->offer();
        $this->offer(['external_id' => '2', 'price' => 5000, 'shipping' => null]);
        $this->get('/?q=4006381333931')->assertOk()->assertSee('GTIN identic')->assertSee('110,00 lei')->assertSee('Necunoscută');
        $this->get('/?q=Test')->assertOk()->assertSee('Similar')->assertDontSee('Cel mai mic total confirmat');
    }

    public function test_history_deduplicates_consecutive_states_but_keeps_return_to_old_price(): void
    {
        $o = $this->offer();
        $this->travel(1)->hours();
        $this->offer();
        $this->assertSame(1, $o->points()->count());
        $this->offer(['price' => 9000]);
        $this->offer(['price' => 10000]);
        $this->assertSame(3, $o->points()->count());
        $this->get('/offers/'.$o->id)->assertOk()->assertSee('90,00')->assertSee('<svg', false);
    }

    public function test_alerts_trigger_once_per_crossing_and_only_for_known_total_in_stock(): void
    {
        $o = $this->offer();
        Watch::create(['offer_id' => $o->id, 'threshold' => 10000]);
        $this->offer(['price' => 8000, 'shipping' => null]);
        $this->offer(['price' => 8000, 'availability' => 'out_of_stock']);
        $this->assertDatabaseCount('price_alerts', 0);
        $this->offer(['price' => 9000]);
        $this->assertDatabaseCount('price_alerts', 0);
        $this->offer(['price' => 8000]);
        $this->offer(['price' => 7000]);
        $this->assertDatabaseCount('price_alerts', 1);
        $this->offer();
        $this->offer(['price' => 8000]);
        $this->assertDatabaseCount('price_alerts', 2);
    }

    public function test_sellers_are_separate(): void
    {
        $this->offer();
        $this->offer(['seller' => 'Other seller']);
        $this->assertDatabaseCount('offers', 2);
    }

    public function test_watch_validation_read_and_delete(): void
    {
        $o = $this->offer();
        $this->post('/offers/'.$o->id.'/watch', ['threshold' => '120.00'])->assertRedirect();
        $this->assertDatabaseCount('price_alerts', 1);
        $this->post('/offers/'.$o->id.'/watch', ['threshold' => 'oops'])->assertSessionHasErrors('threshold');
        $this->post('/alerts/1/read')->assertRedirect();
        $this->assertNotNull(DB::table('price_alerts')->first()->read_at);
        $this->delete('/watches/1')->assertRedirect();
        $this->assertDatabaseCount('price_alerts', 0);
    }

    public function test_feed_money_and_schema_are_strict(): void
    {
        $p = new CsvFeedProvider('https://example.com/feed');
        $this->assertSame(10000, $p->parse($this->csv())[0]['price']);
        $this->assertSame(1234, CsvFeedProvider::money('12,34'));
        $this->assertNull(CsvFeedProvider::money(''));
        $this->expectException(\RuntimeException::class);
        $p->parse(str_replace('100.00', 'FREE', $this->csv()));
    }

    public function test_disabled_source_never_calls_network(): void
    {
        Http::fake();
        app(SourceSync::class)->run('emag');
        Http::assertNothingSent();
        $this->assertDatabaseHas('source_states', ['source' => 'emag', 'status' => 'disabled']);
    }

    public function test_source_failure_isolated_from_other_source_and_rate_limited(): void
    {
        config(['scanner.sources.libris' => ['name' => 'Libris', 'url' => 'https://example.com/bad', 'approved' => true], 'scanner.sources.carturesti' => ['name' => 'Carturesti', 'url' => 'https://example.com/good', 'approved' => true]]);
        Http::fake(['example.com/bad' => Http::response('', 429), 'example.com/good' => Http::response($this->csv(), 200, ['ETag' => 'v1'])]);
        $sync = app(SourceSync::class);
        $sync->run('libris');
        $sync->run('carturesti');
        $sync->run('carturesti');
        Http::assertSentCount(2);
        $this->assertDatabaseHas('source_states', ['source' => 'libris', 'status' => 'error']);
        $this->assertDatabaseHas('source_states', ['source' => 'carturesti', 'status' => 'ok']);
        $this->assertDatabaseCount('offers', 1);
    }

    public function test_304_refreshes_timestamp_without_duplicate_history(): void
    {
        config(['scanner.sources.libris' => ['name' => 'Libris', 'url' => 'https://example.com/feed', 'approved' => true]]);
        Http::fake(['example.com/feed' => Http::sequence()->push($this->csv(), 200, ['ETag' => 'v1'])->push('', 304)]);
        $sync = app(SourceSync::class);
        $sync->run('libris');
        $old = Offer::first()->checked_at;
        $this->travel(7)->hours();
        $sync->run('libris');
        $this->assertTrue(Offer::first()->checked_at->gt($old));
        $this->assertDatabaseCount('price_points', 1);
        Http::assertSent(fn ($r) => $r->hasHeader('If-None-Match', 'v1'));
    }

    public function test_partial_invalid_catalog_does_not_overwrite_existing_data(): void
    {
        $this->offer();
        config(['scanner.sources.libris' => ['name' => 'Libris', 'url' => 'https://example.com/feed', 'approved' => true]]);
        Http::fake(['*' => Http::response($this->csv()."bad,row\n")]);
        app(SourceSync::class)->run('libris');
        $this->assertDatabaseCount('price_points', 1);
        $this->assertDatabaseHas('source_states', ['status' => 'error']);
    }

    public function test_export_escapes_spreadsheet_formulas(): void
    {
        $this->offer(['title' => '=1+1']);
        $response = $this->get('/export')->assertOk();
        $this->assertStringContainsString("'=1+1", $response->streamedContent());
    }

    public function test_upload_without_ocr_is_explicit_and_bad_file_rejected(): void
    {
        config(['scanner.ocr_binary' => null]);
        $this->post('/image', ['image' => UploadedFile::fake()->image('label.png')])->assertRedirect('/')->assertSessionHas('message', fn ($m) => str_contains($m, 'neconfigurat'));
        $this->post('/image', ['image' => UploadedFile::fake()->create('payload.php', 1, 'application/x-php')])->assertSessionHasErrors('image');
    }

    public function test_ingestion_is_idempotent_and_deduplicates_merchant(): void
    {
        $first = $this->offer();
        $second = $this->offer(['price' => 9000]);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('offers', 1);
        $this->assertDatabaseCount('merchants', 1);
        $this->assertDatabaseCount('price_points', 2);
    }

    public function test_canonical_product_market_supplier_and_opportunity_flow(): void
    {
        $offer = $this->offer(['title' => 'Set 10 organizatoare haine stivuibile 26x32cm']);
        $this->post("/offers/{$offer->id}/canonical-product", ['name' => 'Organizator haine stivuibil set 10', 'aliases' => "Stackable clothes organizer 10 pcs\nSeparatoare haine 10 buc"])->assertRedirect();
        $product = $offer->fresh()->product;
        $this->assertNotNull($product);
        $supplier = Supplier::create(['company' => 'Distribuitor Test', 'normalized_company' => 'distribuitor test', 'country' => 'RO', 'type' => 'distributor']);
        SupplierOffer::create(['supplier_id' => $supplier->id, 'canonical_product_id' => $product->id, 'external_id' => 'b2b-1', 'source_url' => 'https://example.com/b2b-1', 'unit_price' => 5000, 'currency' => 'RON', 'moq' => 10, 'stock_state' => 'in_stock', 'last_verified_at' => now()]);
        $this->get("/products/{$product->id}")->assertOk()->assertSee('Selleri RO')->assertSee('Distribuitor Test')->assertSee('MOQ 10');
        $this->post("/products/{$product->id}/opportunity", ['purchase_price' => '50.00', 'quantity' => 10, 'inbound_shipping' => '100.00', 'selling_price' => '100.00', 'currency' => 'RON'])->assertRedirect()->assertSessionHas('calculation', fn ($value) => $value['landed_unit_cost'] === 6000);
    }

    public function test_supported_foreign_currency_is_preserved(): void
    {
        $csv = str_replace(',RON,', ',EUR,', $this->csv());
        $row = (new CsvFeedProvider('https://example.com/feed'))->parse($csv)[0];
        $this->assertSame('EUR', $row['currency']);
        app(OfferRecorder::class)->record('libris', $row);
        $this->assertDatabaseHas('offers', ['currency' => 'EUR']);
        $this->assertDatabaseHas('price_points', ['currency' => 'EUR']);
    }

    public function test_probable_match_with_different_gtin_requires_manual_review(): void
    {
        $seed = $this->offer(['title' => 'Set 10 organizatoare haine stivuibile 26x32cm']);
        $this->post("/offers/{$seed->id}/canonical-product", ['name' => 'Set 10 organizatoare haine stivuibile 26x32cm', 'aliases' => 'Stackable clothes organizer 10 pcs 26x32cm']);
        $candidate = $this->offer(['external_id' => 'other-ean', 'ean' => '5901234123457', 'title' => 'Stackable Clothes Organizer 10pcs 26x32cm']);
        $match = $candidate->fresh()->productMatch;
        $this->assertNotNull($match);
        $this->assertContains($match->classification, ['probable', 'similar']);
        $this->assertSame('pending', $match->review_status);
        $this->assertNull($candidate->fresh()->canonical_product_id);
        $this->post("/matches/{$match->id}/review", ['decision' => 'approved', 'note' => 'Caracteristici verificate manual.'])->assertRedirect();
        $this->assertNotNull($candidate->fresh()->canonical_product_id);
    }

    public function test_unauthenticated_internal_mutation_redirects_to_login(): void
    {
        $offer = $this->offer();
        auth()->logout();

        $this->post("/offers/{$offer->id}/canonical-product", ['name' => 'Blocked'])->assertRedirect('/login');
        $this->assertDatabaseCount('canonical_products', 0);
    }
}
