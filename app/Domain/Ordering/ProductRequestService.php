<?php

namespace App\Domain\Ordering;

use App\Domain\Audit\AuditLogger;
use App\Domain\Inventory\Quantity;
use App\Domain\Notifications\Notifier;
use App\Models\DealerRelationship;
use App\Models\ProductRequest;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Stokta olmayan ürün talebi (docs/03-is-kurallari.md §5). Stoğu değiştirmez, ürünü ayırmaz,
 * siparişe dönüşmez; ürün stoğa girince talep "Stok güncellendi" olur ve bayi normal sipariş verir.
 */
class ProductRequestService
{
    public function __construct(private AuditLogger $audit) {}

    public function create(DealerRelationship $relationship, int $productId, string $qty, ?string $note, bool $notify): ProductRequest
    {
        $product = CartService::orderableProducts()->with('unit')->find($productId)
            ?? throw new DomainException('Ürün talep için uygun değil.');

        if (bccomp($qty, '0', 3) <= 0 || (! $product->unit->allows_fraction && ! Quantity::isWhole($qty))) {
            throw new DomainException('Geçerli bir miktar girin.');
        }

        // Aynı ürün için açık talep varsa yenisi açılmaz; mevcut talep güncellenir.
        $request = ProductRequest::where('dealer_relationship_id', $relationship->id)->where('product_id', $product->id)
            ->whereIn('status', ProductRequest::OPEN)->first();

        if ($request) {
            $request->update(['quantity' => $qty, 'note' => $note, 'notify_when_in_stock' => $notify]);
        } else {
            $request = ProductRequest::create([
                'dealer_relationship_id' => $relationship->id, 'product_id' => $product->id,
                'quantity' => $qty, 'note' => $note, 'notify_when_in_stock' => $notify,
            ]);
        }
        $this->audit->log('product_request.saved', $request, [], ['product_id' => $product->id, 'quantity' => $qty]);

        return $request;
    }

    public function respond(ProductRequest $request, string $status, ?string $note, int $userId): void
    {
        if (! array_key_exists($status, ProductRequest::STATUSES) || $status === 'new') {
            throw new DomainException('Geçersiz talep durumu.');
        }
        if ($request->status === 'closed') {
            throw new DomainException('Kapatılmış talep değiştirilemez.');
        }

        $old = $request->status;
        $request->update(['status' => $status, 'response_note' => $note, 'responded_by' => $userId, 'responded_at' => now()]);
        $this->audit->log('product_request.responded', $request, ['status' => $old], ['status' => $status, 'note' => $note]);
    }

    /**
     * Ürüne stok girdiğinde "gelince haber ver" seçili açık talepleri işaretler.
     * Bildirim e-postası Aşama 7 (bildirim altyapısı) ile eklenecek.
     */
    public function onStockIncreased(int $productId): int
    {
        $requests = DB::transaction(function () use ($productId) {
            $requests = ProductRequest::where('product_id', $productId)->whereIn('status', ProductRequest::OPEN)
                ->where('notify_when_in_stock', true)->lockForUpdate()->get();
            ProductRequest::whereKey($requests->modelKeys())->update(['status' => 'stock_updated', 'notified_at' => now(), 'updated_at' => now()]);

            return $requests;
        });

        foreach ($requests as $request) {
            app(Notifier::class)->stockArrived($request->load(['product', 'relationship']));
        }

        return $requests->count();
    }
}
