<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\TaxRate;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Performans ölçümü için büyük hacimli, tutarlı veri (Aşama 9). Toplu INSERT ile hızlı üretilir;
 * iş servisleri atlanır ama defter ve stok kuralları korunur:
 *  - warehouse_stock = açılış − satışlar (stok hareketlerinin son bakiyesi)
 *  - current_accounts.balance = cari hareketlerin borç − alacak toplamı
 *  - sipariş numaraları işletme/yıl sayacıyla uyumlu
 *
 * Kullanım: (new VolumeSeeder)->seedBusiness($business, ['dealers' => 300, 'products' => 20000, 'orders' => 50000]);
 */
class VolumeSeeder
{
    private const CHUNK = 2000;

    private int $businessId;

    private string $now;

    public function seedBusiness(Business $business, array $sizes = []): array
    {
        $sizes += ['dealers' => 300, 'products' => 20000, 'orders' => 50000, 'months' => 18];
        mt_srand(42); // tekrarlanabilir veri
        $this->businessId = $business->id;
        $this->now = now()->toDateTimeString();
        $warehouseId = BusinessSetting::where('business_id', $business->id)->value('default_warehouse_id');
        $unit = Unit::withoutGlobalScopes()->where('business_id', $business->id)->where('code', 'ADET')->firstOrFail();
        $taxRateId = TaxRate::withoutGlobalScopes()->where('business_id', $business->id)->where('rate', '20.00')->value('id');

        DB::disableQueryLog();
        $categories = $this->categories();
        $brands = $this->insertGetIds('brands', array_map(fn ($i) => ['name' => "Marka {$i}", 'is_active' => 1], range(1, 40)));
        $products = $this->products($sizes['products'], $categories, $brands, $unit->id, $taxRateId);
        $dealers = $this->dealers($sizes['dealers']);
        $stats = $this->orders($sizes['orders'], $sizes['months'], $dealers, $products, $warehouseId, $unit->name);

        return ['categories' => count($categories), 'products' => count($products), 'dealers' => count($dealers)] + $stats;
    }

    /** @return int[] alt kategori kimlikleri */
    private function categories(): array
    {
        $parents = $this->insertGetIds('categories', array_map(fn ($i) => ['name' => "Ana kategori {$i}", 'sort' => $i, 'is_active' => 1], range(1, 15)));
        $children = [];
        foreach ($parents as $p => $parentId) {
            foreach (range(1, 6) as $c) {
                $children[] = ['parent_id' => $parentId, 'name' => 'Alt kategori '.($p + 1).".{$c}", 'sort' => $c, 'is_active' => 1];
            }
        }

        return $this->insertGetIds('categories', $children);
    }

    /** @return array<int, array{id: int, sku: string, name: string, price: string}> */
    private function products(int $count, array $categories, array $brands, int $unitId, int $taxRateId): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $dealer = mt_rand(100, 50000) / 100;
            $rows[] = [
                'sku' => sprintf('HV-%06d', $i), 'barcode' => sprintf('869%010d', $i), 'name' => "Hacim ürünü {$i} ".['Kalem', 'Defter', 'Dosya', 'Klasör', 'Boya'][$i % 5],
                'category_id' => $categories[$i % count($categories)], 'brand_id' => $brands[$i % count($brands)], 'unit_id' => $unitId, 'tax_rate_id' => $taxRateId,
                'pack_quantity' => 1, 'purchase_price' => round($dealer * 0.6, 4), 'list_price' => round($dealer * 1.25, 4), 'dealer_price' => $dealer,
                'price_includes_tax' => 0, 'min_order_qty' => 1, 'order_step' => 1, 'critical_stock_level' => 20, 'is_active' => 1, 'visible_to_dealers' => 1,
            ];
        }
        $ids = $this->insertGetIds('products', $rows);

        return array_map(fn ($id, $row) => ['id' => $id, 'sku' => $row['sku'], 'name' => $row['name'], 'price' => (string) $row['dealer_price']], $ids, $rows);
    }

    /** @return array<int, array{rel: int, account: int, address: array}> */
    private function dealers(int $count): array
    {
        $password = Hash::make('HacimVerisi2026');
        $dealerIds = [];
        foreach (array_chunk(range(1, $count), self::CHUNK) as $chunk) {
            $first = $this->insertRaw('dealers', array_map(fn ($i) => ['legal_name' => "Hacim Bayi {$i} Ltd.", 'city' => 'İstanbul', 'district' => 'Kadıköy',
                'created_at' => $this->now, 'updated_at' => $this->now], $chunk));
            $dealerIds = [...$dealerIds, ...range($first, $first + count($chunk) - 1)];
        }
        $this->insertRaw('dealer_users', array_map(fn ($id, $i) => ['dealer_id' => $id, 'name' => "Bayi Yetkilisi {$i}", 'email' => "hacim{$this->businessId}-{$i}@bayi.test",
            'password' => $password, 'status' => 'active', 'activated_at' => $this->now, 'created_at' => $this->now, 'updated_at' => $this->now], $dealerIds, range(1, $count)));

        $rels = $this->insertGetIds('dealer_relationships', array_map(fn ($id, $i) => ['dealer_id' => $id, 'dealer_code' => sprintf('HB%04d', $i),
            'status' => 'active', 'payment_term_days' => 30, 'approved_at' => $this->now], $dealerIds, range(1, $count)));
        $accounts = $this->insertGetIds('current_accounts', array_map(fn ($rel, $i) => ['dealer_relationship_id' => $rel, 'code' => sprintf('HB%04d', $i), 'balance' => 0],
            $rels, range(1, $count)));
        $address = ['title' => 'Merkez', 'recipient_name' => 'Teslim Alan', 'phone' => '0216 000 00 00', 'city' => 'İstanbul', 'district' => 'Kadıköy', 'address_line' => 'Hacim Cd. 1'];
        $this->insertGetIds('dealer_addresses', array_map(fn ($rel) => $address + ['dealer_relationship_id' => $rel, 'is_default' => 1], $rels));

        return array_map(fn ($rel, $acc) => ['rel' => $rel, 'account' => $acc, 'address' => $address], $rels, $accounts);
    }

    private function orders(int $count, int $months, array $dealers, array $products, int $warehouseId, string $unitName): array
    {
        $start = now()->subMonths($months)->startOfDay()->getTimestamp();
        $span = now()->getTimestamp() - $start;
        $times = [];
        for ($i = 0; $i < $count; $i++) {
            $times[] = $start + mt_rand(0, $span);
        }
        sort($times); // sipariş numaraları zamanla artar

        $opening = 5000;
        $stock = array_fill_keys(array_column($products, 'id'), $opening);
        $this->insertRaw('stock_movements', array_map(fn ($p) => ['warehouse_id' => $warehouseId, 'product_id' => $p['id'], 'type' => 'opening',
            'quantity' => $opening, 'balance_after' => $opening, 'created_at' => Carbon::createFromTimestamp($start - 86400)->toDateTimeString()], $products));

        $counters = [];
        $balances = [];
        $debitsByMonth = [];
        $statusCounts = [];
        $itemTotal = 0;
        $orderBatch = [];

        $flush = function () use (&$orderBatch, &$stock, &$balances, &$debitsByMonth, &$itemTotal, $warehouseId, $dealers) {
            if (! $orderBatch) {
                return;
            }
            $first = $this->insertRaw('orders', array_column($orderBatch, 'order'));
            $items = [];
            $history = [];
            $movements = [];
            $debits = [];
            foreach ($orderBatch as $k => $o) {
                $orderId = $first + $k;
                $created = $o['order']['created_at'];
                $history[] = ['order_id' => $orderId, 'from_status' => null, 'to_status' => 'submitted', 'actor_type' => 'dealer', 'created_at' => $created];
                if ($o['order']['status'] !== 'submitted') {
                    $history[] = ['order_id' => $orderId, 'from_status' => 'submitted', 'to_status' => $o['order']['status'], 'actor_type' => 'business',
                        'created_at' => $o['order']['approved_at'] ?? $created];
                }
                foreach ($o['items'] as $item) {
                    $items[] = $item + ['order_id' => $orderId];
                }
                if ($o['fulfilled']) {
                    $at = $o['order']['approved_at'];
                    foreach ($o['items'] as $idx => $item) {
                        $stock[$item['product_id']] -= (int) $item['quantity_approved'];
                        $movements[] = ['warehouse_id' => $warehouseId, 'product_id' => $item['product_id'], 'type' => 'sale', 'quantity' => -$item['quantity_approved'],
                            'balance_after' => $stock[$item['product_id']], 'reference_type' => 'order', 'reference_id' => $orderId,
                            'actor_type' => 'business', 'source_key' => "order:{$orderId}:sale:{$idx}", 'created_at' => $at];
                    }
                    $account = $dealers[$o['dealer']]['account'];
                    $debits[] = ['current_account_id' => $account, 'type' => 'order_debit', 'debit' => $o['order']['grand_total'], 'credit' => 0,
                        'movement_date' => substr($at, 0, 10), 'due_date' => Carbon::parse($at)->addDays(30)->toDateString(), 'order_id' => $orderId,
                        'actor_type' => 'business', 'source_key' => "order:{$orderId}:debit", 'created_at' => $at];
                    $balances[$account] = bcadd($balances[$account] ?? '0', $o['order']['grand_total'], 2);
                    $month = substr($at, 0, 7);
                    $debitsByMonth[$account][$month] = bcadd($debitsByMonth[$account][$month] ?? '0', $o['order']['grand_total'], 2);
                }
            }
            $this->insertRaw('order_items', $items);
            $this->insertRaw('order_status_history', $history);
            $this->insertRaw('stock_movements', $movements);
            $this->insertRaw('current_account_movements', $debits);
            $itemTotal += count($items);
            $orderBatch = [];
        };

        foreach ($times as $n => $ts) {
            $created = Carbon::createFromTimestamp($ts);
            $year = $created->year;
            $counters[$year] = ($counters[$year] ?? 0) + 1;
            $roll = mt_rand(1, 100);
            // Eski siparişlerin çoğu tamamlanmış; son 10 günün siparişleri daha çok bekleyen/işlemde.
            $recent = $ts > now()->subDays(10)->getTimestamp();
            $status = match (true) {
                $roll <= 5 => 'rejected',
                $roll <= 12 => 'cancelled',
                $recent && $roll <= 40 => 'submitted',
                $recent && $roll <= 55 => 'in_review',
                $recent && $roll <= 75 => 'approved',
                $recent && $roll <= 90 => 'preparing',
                default => 'completed',
            };
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            $fulfilled = in_array($status, ['approved', 'preparing', 'completed'], true);
            $approvedAt = $fulfilled ? $created->copy()->addMinutes(mt_rand(10, 600))->min(now())->toDateTimeString() : null;

            $dealer = mt_rand(0, count($dealers) - 1);
            $lines = [];
            $subtotal = '0';
            $vat = '0';
            foreach (array_rand($products, mt_rand(2, 8)) as $pi) {
                $p = $products[$pi];
                $qty = mt_rand(1, 6) * 2;
                $net = bcmul($p['price'], (string) $qty, 2);
                $lineVat = bcdiv(bcmul($net, '20', 4), '100', 2);
                $subtotal = bcadd($subtotal, $net, 2);
                $vat = bcadd($vat, $lineVat, 2);
                $lines[] = ['product_id' => $p['id'], 'sku' => $p['sku'], 'name' => $p['name'], 'unit_name' => $unitName, 'vat_rate' => 20, 'price_source' => 'dealer',
                    'quantity_requested' => $qty, 'quantity_approved' => $fulfilled ? $qty : null, 'list_unit_price' => $p['price'], 'unit_price' => $p['price'],
                    'line_subtotal' => $net, 'line_discount' => 0, 'line_net' => $net, 'line_vat' => $lineVat, 'line_total' => bcadd($net, $lineVat, 2),
                    'created_at' => $created->toDateTimeString(), 'updated_at' => $created->toDateTimeString()];
            }
            $grand = bcadd($subtotal, $vat, 2);
            $address = $dealers[$dealer]['address'];

            $orderBatch[] = ['dealer' => $dealer, 'fulfilled' => $fulfilled, 'items' => $lines, 'order' => [
                'order_no' => sprintf('SIP-%d-%06d', $year, $counters[$year]), 'dealer_relationship_id' => $dealers[$dealer]['rel'], 'status' => $status,
                'submit_token' => sprintf('%08x-0000-4000-8000-%012d', $this->businessId, $n), 'warehouse_id' => $warehouseId,
                'subtotal' => $subtotal, 'discount_total' => 0, 'net_total' => $subtotal, 'vat_total' => $vat, 'grand_total' => $grand,
                'ship_title' => $address['title'], 'ship_recipient' => $address['recipient_name'], 'ship_phone' => $address['phone'], 'ship_city' => $address['city'],
                'ship_district' => $address['district'], 'ship_address' => $address['address_line'],
                'approved_at' => $approvedAt, 'stock_applied_at' => $approvedAt, 'ledger_applied_at' => $approvedAt,
                'completed_at' => $status === 'completed' ? $approvedAt : null, 'cancelled_at' => $status === 'cancelled' ? $created->toDateTimeString() : null,
                'rejected_reason' => $status === 'rejected' ? 'Stok planlaması' : null, 'cancel_reason' => $status === 'cancelled' ? 'Bayi vazgeçti' : null,
                'created_at' => $created->toDateTimeString(), 'updated_at' => ($approvedAt ?? $created->toDateTimeString()),
            ]];
            if (count($orderBatch) >= 500) {
                $flush();
            }
        }
        $flush();

        // Tahsilat: her bayi her ay borcunun yaklaşık %85'ini ödüyor (son ayınki açık kalıyor).
        $collections = [];
        foreach ($debitsByMonth as $account => $byMonth) {
            foreach ($byMonth as $month => $amount) {
                if ($month === now()->format('Y-m')) {
                    continue;
                }
                $paid = bcdiv(bcmul($amount, '85', 2), '100', 2);
                $collections[] = ['account' => $account, 'amount' => $paid, 'date' => Carbon::parse($month.'-01')->addMonth()->addDays(9)->min(now())->toDateString()];
            }
        }
        foreach (array_chunk($collections, self::CHUNK) as $chunk) {
            $first = $this->insertRaw('collections', array_map(fn ($c) => ['current_account_id' => $c['account'], 'collected_on' => $c['date'], 'amount' => $c['amount'],
                'method' => 'transfer', 'created_at' => $c['date'].' 10:00:00', 'updated_at' => $c['date'].' 10:00:00'], $chunk));
            $this->insertRaw('current_account_movements', array_map(fn ($c, $k) => ['current_account_id' => $c['account'], 'type' => 'collection', 'debit' => 0,
                'credit' => $c['amount'], 'movement_date' => $c['date'], 'collection_id' => $first + $k, 'actor_type' => 'business',
                'source_key' => 'collection:'.($first + $k), 'created_at' => $c['date'].' 10:00:00'], $chunk, array_keys($chunk)));
            foreach ($chunk as $c) {
                $balances[$c['account']] = bcsub($balances[$c['account']] ?? '0', $c['amount'], 2);
            }
        }

        foreach ($balances as $account => $balance) {
            DB::table('current_accounts')->where('id', $account)->update(['balance' => $balance]);
        }
        foreach (array_chunk($stock, self::CHUNK, true) as $chunk) {
            $this->insertRaw('warehouse_stock', array_map(fn ($pid, $qty) => ['warehouse_id' => $warehouseId, 'product_id' => $pid, 'quantity' => $qty,
                'created_at' => $this->now, 'updated_at' => $this->now], array_keys($chunk), $chunk));
        }
        foreach ($counters as $year => $value) {
            DB::table('business_counters')->upsert([['business_id' => $this->businessId, 'key' => "order:{$year}", 'value' => $value]], ['business_id', 'key'], ['value']);
        }

        return ['orders' => $count, 'order_items' => $itemTotal, 'collections' => count($collections), 'statuses' => $statusCounts];
    }

    /** İşletme kimliği ve zaman damgalarıyla toplu ekler, eklenen kimlikleri sırayla döndürür. */
    private function insertGetIds(string $table, array $rows): array
    {
        $ids = [];
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            $first = $this->insertRaw($table, array_map(fn ($r) => $r + ['created_at' => $this->now, 'updated_at' => $this->now], $chunk));
            $ids = [...$ids, ...range($first, $first + count($chunk) - 1)];
        }

        return $ids;
    }

    /**
     * Tek INSERT; InnoDB toplu eklemede kimlikleri ardışık verir (innodb_autoinc_lock_mode ≤ 1 veya
     * tek oturumlu çoklu satır INSERT). İlk kimlik döner.
     */
    private function insertRaw(string $table, array $rows): int
    {
        if (! $rows) {
            return 0;
        }
        // Bayi ve bayi kullanıcısı işletmeden bağımsız (ortak) tablolardır.
        if (! in_array($table, ['dealers', 'dealer_users'], true)) {
            $rows = array_map(fn ($r) => ['business_id' => $this->businessId] + $r, $rows);
        }
        $columns = array_keys(array_merge(...array_map(fn ($r) => array_fill_keys(array_keys($r), true), $rows)));
        $rows = array_map(fn ($r) => array_merge(array_fill_keys($columns, null), $r), $rows);
        foreach (array_chunk($rows, self::CHUNK) as $i => $chunk) {
            DB::table($table)->insert($chunk);
            if ($i === 0) {
                $first = (int) DB::getPdo()->lastInsertId();
            }
        }

        return $first;
    }
}
