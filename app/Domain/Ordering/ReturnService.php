<?php

namespace App\Domain\Ordering;

use App\Domain\Audit\AuditLogger;
use App\Domain\Inventory\Quantity;
use App\Domain\Inventory\StockService;
use App\Domain\Ledger\LedgerService;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Tamamlanmış sipariş iadesi (docs/03-is-kurallari.md §3.6): kalem bazında satılan − daha önce iade edilen
 * miktarı aşamaz. Tek transaction: iade kaydı + return_in stok hareketi + return_credit cari alacak.
 * Tutar, siparişteki birim fiyat, indirim ve KDV ile hesaplanır. Siparişin durumu değişmez.
 */
class ReturnService
{
    public function __construct(private StockService $stock, private LedgerService $ledger, private AuditLogger $audit) {}

    /** @return array<int, string> kalem ID'si → iade edilebilir kalan miktar */
    public static function returnable(Order $order): array
    {
        $returned = OrderReturnItem::whereIn('order_item_id', $order->items->modelKeys())
            ->selectRaw('order_item_id, SUM(quantity) q')->groupBy('order_item_id')->pluck('q', 'order_item_id');

        return $order->items->mapWithKeys(fn ($i) => [
            $i->id => bcsub((string) ($i->quantity_approved ?? '0'), (string) ($returned[$i->id] ?? '0'), 3),
        ])->all();
    }

    /** @param  array<int, string>  $quantities  kalem ID'si → iade miktarı */
    public function create(Order $order, array $quantities, ?string $note, int $userId): OrderReturn
    {
        return DB::transaction(function () use ($order, $quantities, $note, $userId) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'completed') {
                throw new DomainException('İade yalnızca tamamlanmış siparişler için kaydedilir. Henüz tamamlanmamış sipariş için iptal kullanın.');
            }

            $order->load('items.product.unit');
            $returnable = self::returnable($order);
            $lines = [];

            foreach ($order->items as $item) {
                $raw = $quantities[$item->id] ?? null;
                if ($raw === null || trim((string) $raw) === '' || trim((string) $raw) === '0') {
                    continue;
                }
                $qty = Quantity::parse((string) $raw);
                if ($qty === null || bccomp($qty, '0', 3) <= 0) {
                    throw new DomainException("\"{$item->name}\" için geçerli bir iade miktarı girin.");
                }
                if (bccomp($qty, $returnable[$item->id], 3) > 0) {
                    throw new DomainException("\"{$item->name}\" için en fazla ".Quantity::format($returnable[$item->id]).' iade edilebilir.');
                }
                if (! $item->product->unit->allows_fraction && ! Quantity::isWhole($qty)) {
                    throw new DomainException("\"{$item->name}\" için küsuratlı iade girilemez.");
                }
                $lines[] = ['item' => $item, 'qty' => $qty, 'amounts' => LineCalculator::line((string) $item->unit_price, $qty, (string) $item->discount_percent, (string) $item->vat_rate)];
            }

            if ($lines === []) {
                throw new DomainException('En az bir kalem için iade miktarı girin.');
            }

            $totals = LineCalculator::totals(array_column($lines, 'amounts'));
            $return = OrderReturn::create([
                'order_id' => $order->id, 'note' => $note, 'created_by' => $userId,
                'net_total' => $totals['net_total'], 'vat_total' => $totals['vat_total'], 'grand_total' => $totals['grand_total'],
            ]);

            foreach ($lines as $l) {
                $return->items()->create([
                    'order_item_id' => $l['item']->id, 'quantity' => $l['qty'],
                    'line_net' => $l['amounts']['line_net'], 'line_vat' => $l['amounts']['line_vat'], 'line_total' => $l['amounts']['line_total'],
                ]);
            }

            $this->stock->applyMany($order->warehouse_id, 'return_in', array_map(fn ($l) => [
                'product' => $l['item']->product, 'qty' => $l['qty'], 'source_key' => "return:{$return->id}:{$l['item']->id}",
            ], $lines), ['reference_type' => 'order_return', 'reference_id' => $return->id]);

            if ($order->ledger_applied_at !== null) {
                $this->ledger->postReturnCredit($return, $order);
            }

            $this->audit->log('order.returned', $return, [], ['order_id' => $order->id, 'grand_total' => (string) $return->grand_total]);

            return $return;
        });
    }
}
