<?php

namespace Tests\Feature\Ordering;

use App\Domain\Ordering\OrderService;
use App\Models\Attachment;
use App\Models\OrderReturn;
use App\Models\OrderReturnRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** "Yola çıktı" aşaması ve bayinin iade talebi (docs/03-is-kurallari.md §3.1, §3.7). */
class DealerReturnRequestTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    private $tenantBusiness;

    /** Modeli işletme bağlamında yeniden okur. */
    private function db(callable $callback): mixed
    {
        return $this->inTenant($this->tenantBusiness, $callback);
    }

    /** Onaylanmış sipariş: 5 × 10,00 + %20 KDV = 60,00. */
    private function approvedOrder($business, $rel, string $qty = '5'): array
    {
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '100');
        $order = $this->placeOrder($business, $rel, [$product->id => $qty]);
        $this->tenantBusiness = $business;
        $order = $this->db(fn () => app(OrderService::class)->approve($order, [], $this->defaultWarehouse($business)->id, [], $business->user->id)->fresh('items'));

        return [$order, $product];
    }

    public function test_order_can_be_marked_shipped_then_completed_and_cancel_request_returns_to_shipped(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        [$order] = $this->approvedOrder($business, $rel);

        $this->actingAs($business->user, 'business');
        $this->get(route('business.orders.show', $order))->assertOk()->assertSee('Yola çıktı');
        $this->post(route('business.orders.ship', $order), ['shipping_note' => 'Araç 26 ABC 123, sürücü Ali'])->assertSessionHasNoErrors();
        $order = $this->db(fn () => $order->fresh());
        $this->assertSame(['shipped', 'Araç 26 ABC 123, sürücü Ali'], [$order->status, $order->shipping_note]);
        $this->assertNotNull($order->shipped_at);
        $this->post(route('business.orders.ship', $order))->assertSessionHasErrors('order'); // ikinci kez olmaz

        // Bayi yoldaki siparişi görür, iptal talebi gönderebilir; reddedilirse sipariş yine "Yola çıktı"dır.
        $this->actingAs($rel->dealer->user, 'dealer');
        $this->get(route('dealer.orders.show', $order))->assertOk()->assertSee('Yola çıktı')->assertSee('Araç 26 ABC 123, sürücü Ali');
        $this->post(route('dealer.orders.cancel', $order), ['reason' => 'Gerek kalmadı'])->assertSessionHasNoErrors();
        $this->assertSame('cancel_requested', $this->db(fn () => $order->fresh())->status);

        $this->actingAs($business->user, 'business');
        $this->post(route('business.orders.resolve-cancel', $order), ['decision' => 'decline'])->assertSessionHasNoErrors();
        $this->assertSame('shipped', $this->db(fn () => $order->fresh())->status);
        $this->post(route('business.orders.complete', $order))->assertSessionHasNoErrors();
        $this->assertSame('completed', $this->db(fn () => $order->fresh())->status);
    }

    public function test_dealer_requests_return_business_approves_and_receives_with_partial_restock(): void
    {
        Storage::fake('local');
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        [$order, $product] = $this->approvedOrder($business, $rel); // stok 95, borç 60
        $order = $this->inTenant($business, fn () => app(OrderService::class)->complete($order));
        $item = $this->db(fn () => $order->items()->first());

        $this->actingAs($rel->dealer->user, 'dealer');
        $this->get(route('dealer.orders.show', $order))->assertOk()->assertSee('İade talebi oluştur');

        // Hasarlı ürün iadesinde fotoğraf zorunlu; iade edilebilir miktar aşılamaz.
        $this->post(route('dealer.returns.store', $order), ['qty' => [$item->id => '2'], 'reason' => 'damaged'])->assertSessionHasErrors('return');
        $this->post(route('dealer.returns.store', $order), ['qty' => [$item->id => '6'], 'reason' => 'unsold'])->assertSessionHasErrors('return');
        $this->post(route('dealer.returns.store', $order), ['qty' => [$item->id => '3'], 'reason' => 'damaged', 'note' => 'Kutular ezik',
            'photos' => [UploadedFile::fake()->image('ezik.jpg', 200, 200)]])->assertSessionHasNoErrors();

        $request = OrderReturnRequest::withoutGlobalScopes()->sole();
        $itemId2 = $this->db(fn () => $request->items()->value('id'));
        $this->assertSame(['requested', 'damaged', 'Kutular ezik'], [$request->status, $request->reason, $request->dealer_note]);
        $this->assertMatchesRegularExpression('/^IADE-\d{4}-000001$/', $request->request_no);
        $photo = Attachment::withoutGlobalScopes()->where('attachable_type', 'order_return_request')->sole();
        Storage::disk('local')->assertExists($photo->stored_path);
        $this->get(route('dealer.returns.photo', [$request, $photo]))->assertOk();

        // Açık talepteki miktar düşülür: 5 − 3 = 2'den fazlası istenemez.
        $this->post(route('dealer.returns.store', $order), ['qty' => [$item->id => '3'], 'reason' => 'unsold'])->assertSessionHasErrors('return');

        // Toptancı talebi görür, onaylar.
        $this->actingAs($business->user, 'business');
        $this->get(route('business.returns.index'))->assertOk()->assertSee($request->request_no);
        $this->get(route('business.returns.show', $request))->assertOk()->assertSee('Kutular ezik')->assertSee('Teslim al ve cariye işle');
        $this->get(route('business.returns.photo', [$request, $photo]))->assertOk();
        $this->post(route('business.returns.approve', $request), ['business_note' => 'Perşembe günü araçla alınacak'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $this->db(fn () => $request->fresh())->status);

        // Teslim alma: 3 geldi, 1'i tekrar satılabilir. Cari 3 adet için alacak, stoğa yalnızca 1 girer.
        $this->post(route('business.returns.receive', $request), ['received' => [$itemId2 => '4'], 'resellable' => [$itemId2 => '1']])
            ->assertSessionHasErrors('return');
        $this->post(route('business.returns.receive', $request), ['received' => [$itemId2 => '3'], 'resellable' => [$itemId2 => '1']])
            ->assertSessionHasNoErrors();
        $request = $this->db(fn () => $request->fresh());
        $this->assertSame('received', $request->status);
        $this->assertSame('24.00', $this->balance($business, $rel)); // 60 − 3 × 12
        $this->assertSame('96.000', $this->stockQty($business, $product)); // 95 + 1
        $return = OrderReturn::withoutGlobalScopes()->findOrFail($request->order_return_id);
        $this->assertSame(['3.000', '1.000'], [(string) $this->db(fn () => $return->items()->first())->quantity, (string) $this->db(fn () => $return->items()->first())->restocked_quantity]);
        $this->assertSame('completed', $this->db(fn () => $order->fresh())->status);

        // İkinci kez teslim alınamaz, reddedilemez.
        $this->post(route('business.returns.receive', $request), ['received' => [$itemId2 => '3'], 'resellable' => [$itemId2 => '3']])
            ->assertSessionHasErrors('return');
        $this->post(route('business.returns.reject', $request), ['reject_reason' => 'Geç'])->assertSessionHasErrors('return');
        $this->assertSame('24.00', $this->balance($business, $rel));
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.return_request_received']);

        // Bayi sonucu görür; kalan 2 adet için yeni talep açabilir.
        $this->actingAs($rel->dealer->user, 'dealer');
        $this->get(route('dealer.orders.show', $order))->assertOk()->assertSee('Teslim alındı, cariye işlendi')->assertSee('36,00');
        $this->post(route('dealer.returns.store', $order), ['qty' => [$item->id => '2'], 'reason' => 'unsold'])->assertSessionHasNoErrors();
    }

    public function test_reject_withdraw_and_other_dealer_cannot_access(): void
    {
        Storage::fake('local');
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $other = $this->orderingDealer($business);
        [$order] = $this->approvedOrder($business, $rel);

        // Tamamlanmamış siparişte iade talebi açılmaz.
        $this->actingAs($rel->dealer->user, 'dealer');
        $this->post(route('dealer.returns.store', $order), ['qty' => [$order->items->first()->id => '1'], 'reason' => 'unsold'])->assertSessionHasErrors('return');

        $this->inTenant($business, fn () => app(OrderService::class)->complete($order));
        $itemId = $order->items->first()->id;
        $this->post(route('dealer.returns.store', $order), ['qty' => [$itemId => '1'], 'reason' => 'other'])->assertSessionHasErrors('return'); // "Diğer" açıklamasız
        $this->post(route('dealer.returns.store', $order), ['qty' => [$itemId => '1'], 'reason' => 'excess', 'photos' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]])
            ->assertSessionHasErrors('return'); // yalnızca görsel
        $this->post(route('dealer.returns.store', $order), ['qty' => [$itemId => '1'], 'reason' => 'excess'])->assertSessionHasNoErrors();
        $first = OrderReturnRequest::withoutGlobalScopes()->latest('id')->first();

        // Başka bayi talebi göremez, geri çekemez, sipariş için talep açamaz.
        $this->flushSession();
        $this->actingAs($other->dealer->user, 'dealer');
        $this->get(route('dealer.returns.photo', [$first, 1]))->assertNotFound();
        $this->post(route('dealer.returns.cancel', $first))->assertNotFound();
        $this->post(route('dealer.returns.store', $order), ['qty' => [$itemId => '1'], 'reason' => 'excess'])->assertNotFound();
        $this->assertSame('requested', $this->db(fn () => $first->fresh())->status);

        // Sahibi geri çeker; geri çekilen talep yeniden işlenemez.
        $this->flushSession();
        $this->actingAs($rel->dealer->user, 'dealer');
        $this->post(route('dealer.returns.cancel', $first))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $this->db(fn () => $first->fresh())->status);
        $this->actingAs($business->user, 'business');
        $this->post(route('business.returns.approve', $first))->assertSessionHasErrors('return');

        // Toptancı reddeder: gerekçe zorunlu, cari ve stok değişmez.
        $this->actingAs($rel->dealer->user, 'dealer');
        $this->post(route('dealer.returns.store', $order), ['qty' => [$itemId => '2'], 'reason' => 'unsold'])->assertSessionHasNoErrors();
        $second = OrderReturnRequest::withoutGlobalScopes()->latest('id')->first();
        $this->actingAs($business->user, 'business');
        $this->post(route('business.returns.reject', $second), ['reject_reason' => ''])->assertSessionHasErrors('reject_reason');
        $this->post(route('business.returns.reject', $second), ['reject_reason' => 'Ürün açılmış'])->assertSessionHasNoErrors();
        $this->assertSame(['rejected', 'Ürün açılmış'], [$this->db(fn () => $second->fresh())->status, $this->db(fn () => $second->fresh())->reject_reason]);
        $this->assertSame('60.00', $this->balance($business, $rel));
        $this->assertSame(0, OrderReturn::withoutGlobalScopes()->count());

        $this->actingAs($rel->dealer->user, 'dealer');
        $this->get(route('dealer.orders.show', $order))->assertOk()->assertSee('Ürün açılmış');
    }

    public function test_another_business_cannot_see_requests(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        [$order] = $this->approvedOrder($business, $rel);
        $this->inTenant($business, fn () => app(OrderService::class)->complete($order));
        $this->actingAs($rel->dealer->user, 'dealer')
            ->post(route('dealer.returns.store', $order), ['qty' => [$order->items->first()->id => '1'], 'reason' => 'unsold'])->assertSessionHasNoErrors();
        $request = OrderReturnRequest::withoutGlobalScopes()->sole();

        $stranger = $this->makeBusiness();
        $this->actingAs($stranger->user, 'business');
        $this->get(route('business.returns.show', $request))->assertNotFound();
        $this->post(route('business.returns.approve', $request))->assertNotFound();
        $this->assertSame('requested', $this->db(fn () => $request->fresh())->status);
        $this->assertSame($order->id, $request->order_id);
    }
}
