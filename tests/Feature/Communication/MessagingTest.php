<?php

namespace Tests\Feature\Communication;

use App\Domain\Messaging\MessagingService;
use App\Http\Middleware\RequirePlatformTwoFactor;
use App\Http\Middleware\SetDealerContext;
use App\Models\AppNotification;
use App\Models\Attachment;
use App\Models\BusinessModule;
use App\Models\Conversation;
use App\Models\DealerGroup;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** Bölüm 15 — mesajlaşma, okundu bilgisi, bildirim merkezi, duyurular ve izolasyon. */
class MessagingTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    public function test_dealer_and_business_exchange_messages_with_read_receipts(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);

        $this->actingAs($rel->dealer->user, 'dealer');
        $this->get(route('dealer.messages.create'))->assertOk();
        $this->post(route('dealer.messages.store'), ['subject' => 'Okul sezonu', 'body' => 'Defter stokları ne zaman gelir?'])->assertRedirect();
        $conversation = $this->inTenant($business, fn () => Conversation::sole());

        // Toptancı: okunmamış rozet + bildirim
        $this->flushSession();
        $this->actingAs($business->user, 'business');
        $this->get(route('business.messages.index', ['unread' => 1]))->assertOk()->assertSee('Okul sezonu');
        $this->assertSame(1, $this->inTenant($business, fn () => AppNotification::where('recipient_side', 'business')->count()));

        $this->get(route('business.messages.show', $conversation))->assertOk()->assertSee('Defter stokları ne zaman gelir?');
        // Okunduktan sonra "okunmamış" filtresi boş döner (zil menüsündeki bildirim başlığı ayrıca görünebilir).
        $this->get(route('business.messages.index', ['unread' => 1]))->assertSee('Mesaj yok');
        $this->post(route('business.messages.reply', $conversation), ['body' => 'Gelecek hafta.'])->assertRedirect();

        // Bayi: yanıtı ve bildirimi görür; kendi mesajı "Okundu"
        $this->flushSession();
        $this->actingAs($rel->dealer->user, 'dealer');
        $this->get(route('dealer.messages.show', $conversation))->assertOk()->assertSee('Gelecek hafta.')->assertSee('Okundu');
        $this->assertSame(1, $this->inTenant($business, fn () => AppNotification::where('dealer_relationship_id', $rel->id)->count()));
    }

    public function test_conversations_are_isolated_between_dealers_and_businesses(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $rel1 = $this->orderingDealer($a);
        $rel2 = $this->orderingDealer($a);
        $conversation = $this->inTenant($a, fn () => app(MessagingService::class)
            ->start($rel1, 'Özel konu', 'Gizli içerik', 'dealer', $rel1->dealer->user->id));

        $this->actingAs($rel2->dealer->user, 'dealer');
        $this->get(route('dealer.messages.show', $conversation))->assertNotFound();
        $this->post(route('dealer.messages.reply', $conversation), ['body' => 'Sızma'])->assertNotFound();
        $this->get(route('dealer.messages.index', ['q' => 'Gizli']))->assertDontSee('Özel konu');

        $this->flushSession();
        $this->actingAs($b->user, 'business');
        $this->get(route('business.messages.show', $conversation))->assertNotFound();
        $this->post(route('business.messages.store'), ['dealer_relationship_id' => $rel1->id, 'subject' => 'x', 'body' => 'y'])->assertSessionHasErrors('dealer_relationship_id');

        $this->assertSame(1, Message::withoutBusinessScope()->count());
    }

    public function test_dealer_with_two_wholesalers_sees_only_selected_conversations(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $user = $this->makeDealer();
        $relA = $this->orderingDealer($a, $user);
        $relB = $this->orderingDealer($b, $user);
        $this->inTenant($a, fn () => app(MessagingService::class)->start($relA, 'A konusu', 'x', 'business', $a->user->id));

        $this->actingAs($user, 'dealer')->withSession([SetDealerContext::SESSION_KEY => $relA->id])->get(route('dealer.messages.index'))->assertSee('A konusu');
        $this->withSession([SetDealerContext::SESSION_KEY => $relB->id])->get(route('dealer.messages.index'))->assertDontSee('A konusu');
    }

    public function test_order_conversation_is_reused_and_attachment_is_protected(): void
    {
        Storage::fake('local');
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $other = $this->orderingDealer($business);
        $product = $this->makeProduct($business);
        $this->stockUp($business, $product, '5');
        $order = $this->placeOrder($business, $rel, [$product->id => '1']);

        $this->actingAs($rel->dealer->user, 'dealer');
        $pdf = UploadedFile::fake()->createWithContent('dekont.pdf', "%PDF-1.4\n%x");
        $this->post(route('dealer.messages.store'), ['subject' => 'S', 'body' => 'İlk', 'context' => 'order', 'context_id' => $order->id, 'files' => [$pdf]])->assertRedirect();
        $this->post(route('dealer.messages.store'), ['subject' => 'S', 'body' => 'İkinci', 'context' => 'order', 'context_id' => $order->id])->assertRedirect();

        $conversation = $this->inTenant($business, fn () => Conversation::sole());
        $this->assertSame(2, $this->inTenant($business, fn () => $conversation->messages()->count()));
        $this->get(route('dealer.messages.create', ['context' => 'order', 'id' => $order->id]))->assertRedirect(route('dealer.messages.show', $conversation));

        $attachment = $this->inTenant($business, fn () => Attachment::where('attachable_type', 'message')->sole());
        $this->get(route('dealer.messages.attachment', [$conversation, $attachment]))->assertOk();

        // Başka bayinin siparişine bağlı konuşma açılamaz; ek indirilemez.
        $this->flushSession();
        $this->actingAs($other->dealer->user, 'dealer');
        $this->post(route('dealer.messages.store'), ['subject' => 'S', 'body' => 'x', 'context' => 'order', 'context_id' => $order->id])->assertSessionHasErrors('body');
        $this->get(route('dealer.messages.attachment', [$conversation, $attachment]))->assertNotFound();
    }

    public function test_order_status_creates_dealer_notification_and_open_marks_read(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business);
        $this->stockUp($business, $product, '5');
        $order = $this->placeOrder($business, $rel, [$product->id => '1']);

        $this->actingAs($business->user, 'business')->post(route('business.orders.reject', $order), ['reason' => 'Bölge dışı'])->assertRedirect();

        $this->flushSession();
        $this->actingAs($rel->dealer->user, 'dealer');
        $this->get(route('dealer.dashboard'))->assertSee('Bildirimler (1 okunmamış)', false);
        $notification = $this->inTenant($business, fn () => AppNotification::where('dealer_relationship_id', $rel->id)->sole());
        $this->assertStringContainsString('Reddedildi', $notification->title);

        $this->get(route('dealer.notifications.open', $notification))->assertRedirect(route('dealer.orders.show', $order));
        $this->assertNotNull($notification->fresh()->read_at);
        $this->get(route('dealer.notifications.index'))->assertOk()->assertSee($order->order_no);
    }

    public function test_notifications_of_others_are_not_accessible_and_external_urls_are_ignored(): void
    {
        $business = $this->makeBusiness();
        $rel1 = $this->orderingDealer($business);
        $rel2 = $this->orderingDealer($business);
        [$n1, $evil] = $this->inTenant($business, fn () => [
            AppNotification::create(['recipient_side' => 'dealer', 'dealer_relationship_id' => $rel1->id, 'type' => 'x', 'title' => 'Bayi 1 için', 'url' => url('/bayi')]),
            AppNotification::create(['recipient_side' => 'dealer', 'dealer_relationship_id' => $rel2->id, 'type' => 'x', 'title' => 'Dış', 'url' => 'https://kotu.example/phish']),
        ]);

        $this->actingAs($rel2->dealer->user, 'dealer');
        $this->get(route('dealer.notifications.open', $n1))->assertNotFound();
        $this->get(route('dealer.notifications.index'))->assertDontSee('Bayi 1 için');
        $this->get(route('dealer.notifications.open', $evil))->assertRedirect(route('dealer.catalog'));
    }

    public function test_announcement_targets_group_and_shows_on_dashboard(): void
    {
        $business = $this->makeBusiness();
        $inGroup = $this->orderingDealer($business);
        $outside = $this->orderingDealer($business);
        $group = $this->inTenant($business, fn () => DealerGroup::create(['name' => 'Okul bayileri']));
        $inGroup->forceFill(['dealer_group_id' => $group->id])->saveQuietly();

        $this->actingAs($business->user, 'business')
            ->post(route('business.announcements.store'), ['title' => 'Sezon açılışı', 'body' => 'Yeni katalog yayında.', 'dealer_group_id' => $group->id])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('business.announcements.index'))->assertOk()->assertSee('Sezon açılışı');

        $this->flushSession();
        $this->actingAs($inGroup->dealer->user, 'dealer')->get(route('dealer.dashboard'))->assertSee('Sezon açılışı');
        $this->flushSession();
        $this->actingAs($outside->dealer->user, 'dealer')->get(route('dealer.dashboard'))->assertDontSee('Sezon açılışı');
    }

    public function test_messaging_module_can_be_disabled(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        BusinessModule::create(['business_id' => $business->id, 'module_key' => 'messaging', 'enabled' => false]);

        $this->actingAs($rel->dealer->user, 'dealer')->get(route('dealer.messages.index'))->assertForbidden();
    }

    public function test_communication_pages_render(): void
    {
        $business = $this->makeBusiness();
        $this->orderingDealer($business);
        $this->actingAs($business->user, 'business');

        foreach (['business.messages.index', 'business.messages.create', 'business.notifications.index', 'business.email.settings', 'business.email.logs', 'business.announcements.index'] as $route) {
            $this->get(route($route))->assertOk();
        }

        $this->flushSession();
        $admin = $this->makePlatformAdmin();
        $admin->forceFill(['two_factor_secret' => (new Google2FA)->generateSecretKey(32), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($admin, 'platform')->withSession([RequirePlatformTwoFactor::SESSION_KEY => true])
            ->get(route('platform.email-status'))->assertOk()->assertSee($business->display_name);
    }
}
