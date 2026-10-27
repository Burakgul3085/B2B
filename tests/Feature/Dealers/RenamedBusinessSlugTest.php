<?php

namespace Tests\Feature\Dealers;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Kısa adı değiştirilen toptancının eski başvuru, teşekkür ve logo adresleri yenisine kalıcı olarak yönlenir. */
class RenamedBusinessSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_slug_addresses_redirect_permanently_and_the_slug_is_renamed_once(): void
    {
        $business = $this->makeBusiness();
        $business->forceFill(['slug' => 'perilikaya-mah-98140-no-cd-26c'])->save();

        (require database_path('migrations/2026_11_24_000100_rename_yapboz_business_slug.php'))->up();
        $this->assertSame('yapboz-kirtasiye', $business->fresh()->slug);

        $this->get('/basvuru/perilikaya-mah-98140-no-cd-26c')->assertStatus(301)->assertRedirect(route('public.application', 'yapboz-kirtasiye'));
        $this->get('/basvuru/perilikaya-mah-98140-no-cd-26c/tesekkurler')->assertStatus(301)->assertRedirect(route('public.application.thanks', 'yapboz-kirtasiye'));
        $this->get('/logo/perilikaya-mah-98140-no-cd-26c')->assertStatus(301)->assertRedirect(route('public.logo', 'yapboz-kirtasiye'));

        $this->get('/basvuru/yapboz-kirtasiye')->assertOk()->assertSee('Bayilik başvurusu');
        $this->get('/basvuru')->assertRedirect(route('public.application', 'yapboz-kirtasiye'));
        $this->get('/sitemap.xml')->assertSee('/basvuru/yapboz-kirtasiye')->assertDontSee('perilikaya');

        // Hedef ad zaten kullanılıyorsa dokunulmaz
        $other = Business::create(['slug' => 'perilikaya-mah-98140-no-cd-26c', 'display_name' => 'Başka', 'legal_name' => 'Başka Ltd.', 'status' => 'active']);
        (require database_path('migrations/2026_11_24_000100_rename_yapboz_business_slug.php'))->up();
        $this->assertSame('perilikaya-mah-98140-no-cd-26c', $other->fresh()->slug);
    }
}
