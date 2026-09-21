<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->actingAs(User::factory()->create());
    }

    public function test_manual_offer_uses_the_normal_ingestion_pipeline(): void
    {
        $response = $this->post('/offers/manual', [
            'url' => 'https://shop.example/product-1', 'seller' => 'Distribuitor Real',
            'title' => 'Produs real model ABC-10', 'description' => '<script>alert(1)</script>',
            'price' => '125,50', 'shipping' => '20.00', 'currency' => 'EUR', 'ean' => '4006381333931',
            'model' => 'ABC-10', 'country' => 'DE', 'channel' => 'B2B', 'vat_included' => '1',
            'moq' => 12, 'pack_quantity' => 6, 'availability' => 'in_stock', 'notes' => 'Verificare manuală.',
        ]);

        $offer = Offer::firstOrFail();
        $response->assertRedirect(route('offers.show', $offer));
        $this->assertDatabaseHas('offers', [
            'source' => 'manual', 'seller' => 'Distribuitor Real', 'price' => 12550, 'shipping' => 2000,
            'currency' => 'EUR', 'country' => 'DE', 'channel' => 'B2B', 'moq' => 12, 'pack_quantity' => 6,
        ]);
        $this->assertDatabaseCount('price_points', 1);
        $this->get(route('offers.show', $offer))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_csv_requires_preview_and_reports_valid_and_invalid_rows_before_import(): void
    {
        $csv = "seller,title,url,price,currency,country,channel,ean,shipping,moq,pack_quantity,availability\n".
            "Magazin Valid,Produs Valid,https://example.com/valid,100.00,RON,RO,B2C,4006381333931,15.00,1,2,in_stock\n".
            "Magazin Invalid,Produs Invalid,not-a-url,10.00,RON,RO,B2C,,,,,unknown\n";

        $response = $this->post('/imports/csv/preview', [
            'default_source' => 'manual_csv',
            'catalog' => UploadedFile::fake()->createWithContent('catalog.csv', $csv),
        ]);

        $response->assertOk()->assertViewHas('rows', fn (array $rows): bool => count($rows) === 1)
            ->assertViewHas('importErrors', fn (array $errors): bool => count($errors) === 1)
            ->assertSee('URL produs invalid');
        $this->assertDatabaseCount('offers', 0);

        $token = $response->viewData('token');
        $this->post('/imports/csv', ['token' => $token])->assertRedirect('/')->assertSessionHas('message', 'Import finalizat: 1 noi, 0 actualizate, 0 fără modificări, 1 erori neimportate.');
        $this->assertDatabaseHas('offers', ['source' => 'manual_csv', 'seller' => 'Magazin Valid', 'price' => 10000, 'shipping' => 1500, 'moq' => 1, 'pack_quantity' => 2]);
        $this->post('/imports/csv', ['token' => $token])->assertStatus(422);

        $secondPreview = $this->post('/imports/csv/preview', [
            'default_source' => 'manual_csv',
            'catalog' => UploadedFile::fake()->createWithContent('catalog.csv', $csv),
        ]);
        $this->post('/imports/csv', ['token' => $secondPreview->viewData('token')])
            ->assertSessionHas('message', 'Import finalizat: 0 noi, 0 actualizate, 1 fără modificări, 1 erori neimportate.');
        $this->assertDatabaseCount('offers', 1);
    }

    public function test_csv_preview_is_private_and_bound_to_the_user_who_uploaded_it(): void
    {
        $csv = "seller,title,url,price,currency\nMagazin,Produs,https://example.com/p,10.00,RON\n";
        $preview = $this->post('/imports/csv/preview', [
            'default_source' => 'manual_csv',
            'catalog' => UploadedFile::fake()->createWithContent('catalog.csv', $csv),
        ]);
        $token = $preview->viewData('token');

        $this->actingAs(User::factory()->create());
        $this->post('/imports/csv', ['token' => $token])->assertForbidden();
        $this->assertDatabaseCount('offers', 0);

        auth()->logout();
        $this->get('/imports/csv')->assertRedirect('/login');
        $this->post('/offers/manual')->assertRedirect('/login');
    }

    public function test_manual_offer_validation_rejects_unusable_commercial_data(): void
    {
        $this->post('/offers/manual', [
            'url' => 'javascript:alert(1)', 'seller' => '', 'title' => '', 'price' => 'gratis',
            'currency' => 'BTC', 'country' => 'Romania', 'channel' => 'C2C', 'availability' => 'maybe',
        ])->assertSessionHasErrors(['url', 'seller', 'title', 'price', 'currency', 'country', 'channel', 'availability']);

        $this->assertDatabaseCount('offers', 0);
    }
}
