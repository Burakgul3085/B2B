<?php

namespace Tests\Feature\Dealers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** Bayi logosu: Profil ve Güvenlik'ten yüklenir, başlıkta baş harfin yerine görünür; her bayi yalnızca kendi logosunu görür. */
class DealerLogoTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    public function test_dealer_uploads_logo_shown_in_header_and_removes_it(): void
    {
        Storage::fake('local');
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $other = $this->orderingDealer($business);
        $dealer = $rel->dealer;

        $this->actingAs($dealer->user, 'dealer')->get(route('dealer.account'))->assertOk()->assertSee('Bayi logosu')->assertDontSee(route('dealer.logo').'?v=', false);
        $this->get(route('dealer.logo'))->assertNotFound();

        $img = imagecreatetruecolor(300, 300);
        ob_start();
        imagepng($img);
        $png = UploadedFile::fake()->createWithContent('logo.png', (string) ob_get_clean());

        $this->post(route('dealer.logo.update'), ['logo' => $png])->assertSessionHasNoErrors();
        $path = $dealer->fresh()->logo_path;
        Storage::disk('local')->assertExists($path);
        $this->get(route('dealer.home'))->assertOk()->assertSee(route('dealer.logo').'?v=', false);
        $this->get(route('dealer.logo'))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->assertDatabaseHas('audit_logs', ['action' => 'dealer.logo_updated', 'actor_type' => 'dealer', 'actor_id' => $dealer->user->id]);

        // Başka bayi bu logoyu göremez: rota yalnızca oturumdaki bayinin logosunu verir.
        $this->flushSession();
        $this->actingAs($other->dealer->user, 'dealer')->get(route('dealer.logo'))->assertNotFound();
        $this->flushSession();

        $this->actingAs($dealer->user, 'dealer')->delete(route('dealer.logo.destroy'))->assertSessionHasNoErrors();
        $this->assertNull($dealer->fresh()->logo_path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_logo_must_be_an_image(): void
    {
        Storage::fake('local');
        $rel = $this->orderingDealer($this->makeBusiness());

        $this->actingAs($rel->dealer->user, 'dealer')
            ->post(route('dealer.logo.update'), ['logo' => UploadedFile::fake()->createWithContent('logo.png', 'not an image')])
            ->assertSessionHasErrors('logo');
        $this->assertNull($rel->dealer->fresh()->logo_path);
    }
}
