<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\User;
use App\Services\OfferRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_send_security_headers_and_do_not_show_admin_controls(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertDontSee('Actualizează sursele')
            ->assertDontSee('Export CSV')
            ->assertDontSee('Identificare internă din fotografie');
    }

    public function test_internal_routes_require_authentication(): void
    {
        $offer = $this->offer();

        $this->post('/sync')->assertRedirect('/login');
        $this->get('/export')->assertRedirect('/login');
        $this->post('/image')->assertRedirect('/login');
        $this->post("/offers/{$offer->id}/watch", ['threshold' => '10.00'])->assertRedirect('/login');
        $this->post("/offers/{$offer->id}/canonical-product", ['name' => 'Produs'])->assertRedirect('/login');
    }

    public function test_login_has_no_signup_and_valid_credentials_create_session(): void
    {
        $user = User::factory()->create(['password' => bcrypt('a-secure-test-password')]);

        $this->get('/login')->assertOk()->assertSee('Nu există înregistrare publică')->assertDontSee('Creează cont');
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'a-secure-test-password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_public_offer_hides_manual_review_and_watch_forms(): void
    {
        $offer = $this->offer();

        $this->get("/offers/{$offer->id}")
            ->assertOk()
            ->assertDontSee('Creează produs canonic')
            ->assertDontSee('Urmărește costul total');
    }

    private function offer(): Offer
    {
        return app(OfferRecorder::class)->record('libris', [
            'external_id' => 'public-fixture', 'seller' => 'Seller autorizat', 'title' => 'Produs autorizat',
            'url' => 'https://example.com/product', 'ean' => null, 'model' => null, 'sku' => null,
            'price' => 10000, 'shipping' => 1000, 'currency' => 'RON', 'availability' => 'in_stock',
        ]);
    }
}
