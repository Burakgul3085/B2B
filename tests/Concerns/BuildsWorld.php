<?php

namespace Tests\Concerns;

use App\Domain\Messaging\MessagingService;
use App\Domain\Ordering\CartService;
use App\Models\Announcement;
use App\Models\AppNotification;
use App\Models\Attachment;
use App\Models\Brand;
use App\Models\Business;
use App\Models\Category;
use App\Models\CollectionEntry;
use App\Models\CurrentAccount;
use App\Models\DealerApplication;
use App\Models\DealerGroup;
use App\Models\DealerProductPrice;
use App\Models\DealerRelationship;
use App\Models\DiscountRule;
use App\Models\EmailLog;
use App\Models\ImportJob;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\ProductImage;
use App\Models\ProductRequest;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\VariantGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Bir işletme için rotalarda geçen her model türünden birer kayıt kurar (güvenlik taramaları için).
 * Dönen dizi: rota parametresi => kayıt kimliği; çakışan adlar alan önekiyle ayrılır.
 */
trait BuildsWorld
{
    use CreatesOrders;

    /** @return array{rel: DealerRelationship, ids: array<string, int>} */
    protected function buildWorld(Business $business): array
    {
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business);
        $this->stockUp($business, $product, '100');
        $order = $this->placeOrder($business, $rel, [$product->id => '1']);

        $ids = $this->inTenant($business, function () use ($business, $rel, $product, $order) {
            $user = $business->user;
            $list = PriceList::create(['name' => 'Liste '.Str::random(4)]);
            $application = DealerApplication::create([
                'legal_name' => 'Başvuran', 'contact_name' => 'B', 'phone' => '1234567', 'email' => Str::random(6).'@b.test',
                'city' => 'İstanbul', 'district' => 'Kadıköy', 'address' => 'Adres', 'consent_text_version' => 'v1', 'consent_at' => now(),
            ]);
            $conversation = app(MessagingService::class)->start($rel, 'Konu', 'Merhaba', 'business', $user->id);
            $count = StockCount::create(['warehouse_id' => $this->defaultWarehouse($business)->id, 'created_by' => $user->id]);
            $account = CurrentAccount::where('dealer_relationship_id', $rel->id)->sole();
            $cartItem = app(CartService::class)->add($rel, $product->id, '1');

            return [
                'product' => $product->id,
                'image' => ProductImage::create(['product_id' => $product->id, 'path' => 'yok.webp', 'is_primary' => true])->id,
                'category' => Category::create(['name' => 'Kategori '.Str::random(4)])->id,
                'brand' => Brand::create(['name' => 'Marka '.Str::random(4)])->id,
                'variantGroup' => VariantGroup::create(['name' => 'Varyant', 'attribute_names' => ['Renk']])->id,
                'unit' => Unit::create(['code' => 'U'.Str::upper(Str::random(4)), 'name' => 'Birim'])->id,
                'taxRate' => TaxRate::create(['name' => 'Özel', 'rate' => (string) ((int) TaxRate::max('rate') + 1).'.00'])->id,
                'warehouse' => $this->defaultWarehouse($business)->id,
                'group' => DealerGroup::create(['name' => 'Grup '.Str::random(4)])->id,
                'dealer' => $rel->id,
                'address' => $rel->addresses()->value('id'),
                'price' => DealerProductPrice::create(['dealer_relationship_id' => $rel->id, 'product_id' => $product->id, 'price' => '6'])->id,
                'account' => $account->id,
                'collection' => CollectionEntry::create(['current_account_id' => $account->id, 'collected_on' => now()->toDateString(), 'amount' => '1', 'method' => 'cash'])->id,
                'list' => $list->id,
                'item' => PriceListItem::create(['price_list_id' => $list->id, 'product_id' => $product->id, 'price' => '4'])->id,
                'rule' => DiscountRule::create(['name' => 'Kural', 'scope' => 'product', 'product_id' => $product->id, 'target' => 'all', 'percent' => '5'])->id,
                'order' => $order->id,
                'productRequest' => ProductRequest::create(['dealer_relationship_id' => $rel->id, 'product_id' => $product->id, 'quantity' => '1'])->id,
                'application' => $application->id,
                'application_attachment' => $this->attachment('dealer_application', $application->id),
                'conversation' => $conversation->id,
                'message_attachment' => $this->attachment('message', $conversation->messages()->value('id')),
                'announcement' => $announcementId = Announcement::create(['title' => 'Duyuru', 'body' => 'Metin', 'published_at' => now(), 'created_by' => $user->id])->id,
                'announcement_attachment' => $this->attachment('announcement', $announcementId),
                'count' => $count->id,
                'line' => StockCountLine::create(['stock_count_id' => $count->id, 'product_id' => $product->id, 'counted_qty' => '1'])->id,
                'job' => ImportJob::create(['type' => 'products', 'original_name' => 'x.xlsx', 'created_by' => $user->id])->id,
                'log' => EmailLog::create(['template_key' => 'order_submitted', 'to_email' => 'x@x.test', 'subject' => 'S', 'payload' => [], 'status' => 'failed'])->id,
                'business_notification' => AppNotification::create(['recipient_side' => 'business', 'type' => 'x', 'title' => 'T', 'url' => '/toptanci'])->id,
                'dealer_notification' => AppNotification::create(['recipient_side' => 'dealer', 'dealer_relationship_id' => $rel->id, 'type' => 'x', 'title' => 'T', 'url' => '/bayi'])->id,
                'cart_item' => $cartItem->id,
                'dealerUser' => $rel->dealer->user->id,
            ];
        });

        return ['rel' => $rel, 'ids' => $ids];
    }

    private function attachment(string $type, int $id): int
    {
        return Attachment::create([
            'attachable_type' => $type, 'attachable_id' => $id, 'stored_path' => 'yok.pdf', 'original_name' => 'belge.pdf',
            'mime' => 'application/pdf', 'size' => 10, 'sha256' => str_repeat('a', 64),
        ])->id;
    }

    /** İşletmeye ait bütün satırların özeti: herhangi bir değişiklik özeti değiştirir. */
    protected function businessFingerprint(int $businessId): array
    {
        $tables = collect(DB::select('SELECT table_name AS t FROM information_schema.columns WHERE table_schema = DATABASE() AND column_name = ?', ['business_id']))
            ->pluck('t')->reject(fn ($t) => $t === 'audit_logs')->sort()->values();

        return $tables->mapWithKeys(fn ($t) => [$t => md5(json_encode(DB::table($t)->where('business_id', $businessId)->orderBy(Schema::hasColumn($t, 'id') ? 'id' : 'business_id')->get()))])->all();
    }

    /** Rota parametresi için kimlik (aynı adlı parametreler alana ve rotaya göre ayrılır). */
    protected function routeParam(array $ids, string $routeName, string $param): ?int
    {
        $dealerSide = str_starts_with($routeName, 'dealer.');

        return match ($param) {
            'attachment' => match (true) {
                str_contains($routeName, 'applications') => $ids['application_attachment'],
                str_contains($routeName, 'announcements') => $ids['announcement_attachment'],
                default => $ids['message_attachment'],
            },
            'notification' => $dealerSide ? $ids['dealer_notification'] : $ids['business_notification'],
            'item' => $dealerSide ? $ids['cart_item'] : $ids['item'],
            default => $ids[$param] ?? null,
        };
    }
}
