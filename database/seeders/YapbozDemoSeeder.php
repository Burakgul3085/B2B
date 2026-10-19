<?php

namespace Database\Seeders;

use App\Domain\Branding\PuzzleLogo;
use App\Domain\Catalog\ProductService;
use App\Domain\Dealers\DealerOnboarding;
use App\Domain\Files\ImageUploader;
use App\Domain\Inventory\StockService;
use App\Domain\Ledger\LedgerService;
use App\Domain\Messaging\MessagingService;
use App\Domain\Notifications\Notifier;
use App\Domain\Ordering\CartService;
use App\Domain\Ordering\OrderService;
use App\Domain\Ordering\ProductRequestService;
use App\Domain\Ordering\ReturnService;
use App\Domain\Platform\CreateBusiness;
use App\Domain\Tenancy\TenantContext;
use App\Models\Announcement;
use App\Models\Brand;
use App\Models\Business;
use App\Models\Category;
use App\Models\CurrentAccount;
use App\Models\DealerApplication;
use App\Models\DealerGroup;
use App\Models\DealerProductPrice;
use App\Models\DealerRelationship;
use App\Models\DiscountRule;
use App\Models\Order;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\VariantGroup;
use App\Models\Warehouse;
use Database\Seeders\Support\DemoImages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Müşteri sunumu için tek toptancı (Yapboz Kırtasiye B2B) ve tek bayi (Onur Kırtasiye) yükler.
 * Görselli katalog, fiyat listesi, siparişler (her durumdan), cari, mesaj, talep ve duyuru içerir.
 * Tüm iş kayıtları gerçek servislerle oluşturulur (stok ve cari hareketleri tutarlıdır).
 *
 * Çalıştırma: php artisan db:seed --class=YapbozDemoSeeder
 */
class YapbozDemoSeeder extends Seeder
{
    private const PASSWORD = '123456';

    public function run(): void
    {
        abort_unless(app()->environment('local', 'testing'), 403, 'Demo verisi yalnızca local ortamda yüklenir.');

        if (Business::where('slug', 'yapboz-kirtasiye')->exists()) {
            $this->command?->warn('Yapboz Kırtasiye zaten var; demo verisi tekrar yüklenmedi.');

            return;
        }

        $business = app(CreateBusiness::class)->handle(
            [
                'slug' => 'yapboz-kirtasiye', 'display_name' => 'Yapboz Kırtasiye B2B',
                'legal_name' => 'Yapboz Kırtasiye Toptan Ticaret Ltd. Şti.', 'email' => 'burakgul3085@gmail.com',
                'phone' => '0212 555 40 40', 'city' => 'İstanbul', 'district' => 'Bayrampaşa',
                'address' => 'Kocatepe Mah. Paşa Cd. No:18', 'tax_office' => 'Bayrampaşa', 'tax_number' => '9876543210',
            ],
            ['name' => 'Yapboz Kırtasiye Yöneticisi', 'email' => 'burakgul3085@gmail.com', 'password' => self::PASSWORD],
            ['plan' => 'Kurumsal', 'starts_on' => now()->subMonths(2)->toDateString(), 'note' => 'Demo lisansı'],
        )['business'];

        $images = new DemoImages;
        $business->update(['logo_path' => app(ImageUploader::class)->store($images->upload(PuzzleLogo::png(PuzzleLogo::withWordmark('Yapboz', 'Kırtasiye B2B')), 'logo.png'), 'businesses/'.$business->id.'/logo', 480)]);

        app(TenantContext::class)->runAs($business->id, function () use ($business, $images) {
            DB::transaction(function () use ($business, $images) {
                $this->catalog($business, $images);
                $rel = $this->dealer();
                $this->pricing($rel);
                $this->orders($business, $rel);
                $this->ledger($business, $rel);
                $this->communication($business, $rel);
            });
        });

        $this->call([StorefrontDemoSeeder::class, BrandLogoDemoSeeder::class]);

        $this->command?->info('Yapboz Kırtasiye B2B ve Onur Kırtasiye demo verisi yüklendi.');
    }

    /** Görselli katalog: 4 ana kategori, 28 ürün, iki depo, açılış stoğu. */
    private function catalog(Business $business, DemoImages $images): void
    {
        $stock = app(StockService::class);
        $productService = app(ProductService::class);
        $main = Warehouse::find($business->settings()->value('default_warehouse_id'));
        $branch = Warehouse::create(['code' => 'SUBE', 'name' => 'Anadolu Yakası Depo', 'address' => 'Ümraniye, İstanbul']);

        $unit = fn (string $code) => Unit::where('code', $code)->value('id');
        $tax20 = TaxRate::where('rate', '20.00')->value('id');
        $tax10 = TaxRate::where('rate', '10.00')->value('id');

        $categories = [];
        $tree = [
            'Kalemler' => ['Tükenmez Kalem', 'Jel Kalem', 'Kurşun Kalem', 'Keçeli ve Fosforlu'],
            'Defterler' => ['Spiralli Defter', 'Karton Kapak Defter'],
            'Okul Malzemeleri' => ['Silgi ve Kalemtıraş', 'Cetvel ve Makas', 'Yapıştırıcı', 'Boya'],
            'Ofis Malzemeleri' => ['Zımba', 'Dosya', 'Not Kağıdı', 'Fotokopi Kağıdı'],
        ];
        foreach ($tree as $parent => $children) {
            $parentId = Category::create(['name' => $parent])->id;
            foreach ($children as $child) {
                $categories[$child] = Category::create(['name' => $child, 'parent_id' => $parentId])->id;
            }
        }

        $brands = collect(['Pensan', 'Faber-Castell', 'Gıpta', 'Keskin Color', 'Noki', 'Mas', 'Pritt', 'Stabilo', 'Copier Bond'])
            ->mapWithKeys(fn ($b) => [$b => Brand::create(['name' => $b])->id]);
        $variants = [
            'tk' => VariantGroup::create(['name' => 'Tükenmez Kalem 1.0', 'attribute_names' => ['Renk']])->id,
            'jk' => VariantGroup::create(['name' => 'Jel Kalem 0.7', 'attribute_names' => ['Renk']])->id,
            'fs' => VariantGroup::create(['name' => 'Fosforlu Kalem', 'attribute_names' => ['Renk']])->id,
        ];

        $rainbow = '#ef4444,#f97316,#facc15,#22c55e,#06b6d4,#3b82f6,#8b5cf6,#ec4899,#78350f,#111827,#ffffff,#64748b';

        // [sku, ad, kategori, marka, birim, alış, liste, bayi, kdv, koli, adım, kritik, merkez stok, şube stok, görsel, renkler, varyant]
        $rows = [
            ['TK-10-MAV', 'Tükenmez Kalem 1.0 mm Mavi', 'Tükenmez Kalem', 'Pensan', 'ADET', 4.20, 9.50, 7.90, $tax20, 50, 50, 500, 2400, 600, 'pen', ['main' => '#2563eb', 'bg' => '#dbeafe'], ['tk', 'Mavi']],
            ['TK-10-SYH', 'Tükenmez Kalem 1.0 mm Siyah', 'Tükenmez Kalem', 'Pensan', 'ADET', 4.20, 9.50, 7.90, $tax20, 50, 50, 500, 1800, 400, 'pen', ['main' => '#111827', 'bg' => '#e5e7eb'], ['tk', 'Siyah']],
            ['TK-10-KRM', 'Tükenmez Kalem 1.0 mm Kırmızı', 'Tükenmez Kalem', 'Pensan', 'ADET', 4.20, 9.50, 7.90, $tax20, 50, 50, 300, 260, 0, 'pen', ['main' => '#dc2626', 'bg' => '#fee2e2'], ['tk', 'Kırmızı']],
            ['JK-07-MAV', 'Jel Kalem 0.7 mm Mavi', 'Jel Kalem', 'Faber-Castell', 'ADET', 9.10, 19.00, 16.20, $tax20, 24, 12, 120, 720, 120, 'gel', ['main' => '#2563eb', 'accent' => '#1e3a8a', 'bg' => '#dbeafe'], ['jk', 'Mavi']],
            ['JK-07-YSL', 'Jel Kalem 0.7 mm Yeşil', 'Jel Kalem', 'Faber-Castell', 'ADET', 9.10, 19.00, 16.20, $tax20, 24, 12, 120, 96, 0, 'gel', ['main' => '#16a34a', 'accent' => '#14532d', 'bg' => '#dcfce7'], ['jk', 'Yeşil']],
            ['JK-07-MOR', 'Jel Kalem 0.7 mm Mor', 'Jel Kalem', 'Faber-Castell', 'ADET', 9.10, 19.00, 16.20, $tax20, 24, 12, 120, 0, 0, 'gel', ['main' => '#7c3aed', 'accent' => '#4c1d95', 'bg' => '#ede9fe'], ['jk', 'Mor']],
            ['KK-HB-12', 'Kurşun Kalem HB 12\'li Kutu', 'Kurşun Kalem', 'Faber-Castell', 'PAKET', 28.00, 59.00, 49.90, $tax20, 12, 6, 36, 300, 60, 'pencilbox', ['main' => '#facc15', 'accent' => '#1e3a8a', 'bg' => '#e0e7ff'], null],
            ['KK-2B-01', 'Kurşun Kalem 2B Silgili', 'Kurşun Kalem', 'Faber-Castell', 'ADET', 3.10, 7.00, 5.80, $tax20, 72, 36, 300, 1440, 360, 'pencil', ['main' => '#facc15', 'accent' => '#a16207', 'bg' => '#fef9c3'], null],
            ['KC-12-SET', 'Keçeli Kalem 12 Renk Set', 'Keçeli ve Fosforlu', 'Keskin Color', 'PAKET', 34.00, 72.00, 61.00, $tax20, 12, 6, 30, 180, 40, 'markers', ['set' => '#ef4444,#f97316,#22c55e,#3b82f6,#a855f7', 'bg' => '#fce7f3'], null],
            ['FS-SARI', 'Fosforlu Kalem Sarı', 'Keçeli ve Fosforlu', 'Stabilo', 'ADET', 11.50, 24.00, 20.40, $tax20, 10, 10, 100, 540, 100, 'highlighter', ['main' => '#fde047', 'accent' => '#ca8a04', 'bg' => '#fefce8'], ['fs', 'Sarı']],
            ['FS-PEMBE', 'Fosforlu Kalem Pembe', 'Keçeli ve Fosforlu', 'Stabilo', 'ADET', 11.50, 24.00, 20.40, $tax20, 10, 10, 100, 70, 0, 'highlighter', ['main' => '#f472b6', 'accent' => '#be185d', 'bg' => '#fdf2f8'], ['fs', 'Pembe']],
            ['FS-YESIL', 'Fosforlu Kalem Yeşil', 'Keçeli ve Fosforlu', 'Stabilo', 'ADET', 11.50, 24.00, 20.40, $tax20, 10, 10, 100, 320, 0, 'highlighter', ['main' => '#4ade80', 'accent' => '#15803d', 'bg' => '#f0fdf4'], ['fs', 'Yeşil']],
            ['SD-A4-100', 'Spiralli Defter A4 100 Yaprak Kareli', 'Spiralli Defter', 'Gıpta', 'ADET', 32.00, 65.00, 55.00, $tax10, 20, 10, 100, 900, 200, 'notebook', ['main' => '#2563eb', 'accent' => '#1e3a8a', 'bg' => '#e0f2fe', 'label' => 'A4 · 100'], null],
            ['SD-A5-80', 'Spiralli Defter A5 80 Yaprak Çizgili', 'Spiralli Defter', 'Gıpta', 'ADET', 21.00, 44.00, 37.50, $tax10, 30, 10, 100, 640, 150, 'notebook', ['main' => '#e11d48', 'accent' => '#881337', 'bg' => '#ffe4e6', 'label' => 'A5 · 80'], null],
            ['KD-A5-60', 'Karton Kapak Defter A5 60 Yaprak Çizgili', 'Karton Kapak Defter', 'Gıpta', 'ADET', 11.50, 24.00, 20.00, $tax10, 40, 20, 200, 1200, 300, 'notebook', ['main' => '#16a34a', 'accent' => '#14532d', 'bg' => '#dcfce7', 'label' => 'A5 · 60', 'spiral' => false], null],
            ['KD-A4-40', 'Karton Kapak Defter A4 40 Yaprak Kareli', 'Karton Kapak Defter', 'Gıpta', 'ADET', 14.00, 29.00, 24.50, $tax10, 40, 20, 200, 150, 0, 'notebook', ['main' => '#ea580c', 'accent' => '#7c2d12', 'bg' => '#ffedd5', 'label' => 'A4 · 40', 'spiral' => false], null],
            ['SG-BYZ-01', 'Silgi Beyaz Büyük Boy', 'Silgi ve Kalemtıraş', 'Faber-Castell', 'ADET', 3.80, 8.50, 7.00, $tax20, 40, 20, 200, 1600, 400, 'eraser', ['main' => '#2563eb', 'accent' => '#1e3a8a', 'bg' => '#e0e7ff'], null],
            ['KT-HZN-01', 'Hazneli Kalemtıraş', 'Silgi ve Kalemtıraş', 'Faber-Castell', 'ADET', 6.40, 14.00, 11.80, $tax20, 24, 12, 100, 480, 0, 'sharpener', ['main' => '#0ea5e9', 'accent' => '#075985', 'bg' => '#e0f2fe'], null],
            ['CT-30-SFF', 'Cetvel 30 cm Şeffaf', 'Cetvel ve Makas', 'Noki', 'ADET', 5.20, 12.00, 9.90, $tax20, 50, 25, 100, 800, 150, 'ruler', ['main' => '#38bdf8', 'accent' => '#0c4a6e', 'bg' => '#e0f2fe'], null],
            ['MK-13-OKL', 'Okul Makası 13 cm', 'Cetvel ve Makas', 'Mas', 'ADET', 12.00, 26.00, 21.90, $tax20, 24, 12, 60, 45, 0, 'scissors', ['main' => '#f97316', 'bg' => '#ffedd5'], null],
            ['YS-21-STK', 'Yapıştırıcı Stick 21 gr', 'Yapıştırıcı', 'Pritt', 'ADET', 14.50, 31.00, 26.00, $tax20, 24, 12, 100, 600, 120, 'glue', ['main' => '#7c3aed', 'accent' => '#4c1d95', 'bg' => '#ede9fe', 'label' => '21 gr'], null],
            ['KB-12-UZN', 'Kuru Boya 12 Renk Uzun', 'Boya', 'Faber-Castell', 'PAKET', 39.00, 82.00, 69.00, $tax20, 12, 6, 30, 240, 60, 'pencilbox', ['main' => '#ef4444', 'accent' => '#7c2d12', 'bg' => '#fef3c7', 'box' => '#ea580c', 'label' => '12 Renk', 'pencils' => '#ef4444,#f59e0b,#22c55e,#3b82f6,#8b5cf6,#ec4899'], null],
            ['SB-12-TBL', 'Sulu Boya 12 Renk Tablet', 'Boya', 'Faber-Castell', 'PAKET', 42.00, 89.00, 75.00, $tax20, 12, 6, 30, 22, 0, 'paint', ['set' => $rainbow, 'bg' => '#fef3c7'], null],
            ['ZM-10-KRM', 'Zımba Makinesi No:10 Kırmızı', 'Zımba', 'Mas', 'ADET', 45.00, 95.00, 81.00, $tax20, 12, 6, 24, 96, 24, 'stapler', ['main' => '#dc2626', 'bg' => '#fee2e2'], null],
            ['DS-TEL-MAV', 'Telli Dosya Mavi 50\'li', 'Dosya', 'Noki', 'PAKET', 38.00, 78.00, 66.00, $tax20, 10, 5, 20, 140, 0, 'folder', ['main' => '#3b82f6', 'accent' => '#1d4ed8', 'bg' => '#dbeafe'], null],
            ['DS-TEL-KRM', 'Telli Dosya Kırmızı 50\'li', 'Dosya', 'Noki', 'PAKET', 38.00, 78.00, 66.00, $tax20, 10, 5, 20, 0, 0, 'folder', ['main' => '#ef4444', 'accent' => '#b91c1c', 'bg' => '#fee2e2'], null],
            ['YN-76-3RN', 'Yapışkan Not Kağıdı 76×76 3 Renk', 'Not Kağıdı', 'Noki', 'PAKET', 16.00, 34.00, 28.50, $tax20, 24, 12, 60, 360, 80, 'sticky', ['set' => '#fde047,#f9a8d4,#93c5fd', 'bg' => '#f1f5f9'], null],
            ['FK-A4-500', 'Fotokopi Kağıdı A4 80 g 500 Yaprak', 'Fotokopi Kağıdı', 'Copier Bond', 'PAKET', 92.00, 165.00, 145.00, $tax20, 5, 5, 100, 1250, 300, 'paper', ['main' => '#0f766e', 'accent' => '#134e4a', 'bg' => '#ccfbf1'], null],
        ];

        foreach ($rows as $row) {
            [$sku, $name, $cat, $brand, $unitCode, $purchase, $list, $dealer, $taxId, $pack, $step, $critical, $qtyMain, $qtyBranch, $shape, $colors, $variant] = $row;

            $product = Product::create([
                'sku' => $sku, 'name' => $name, 'category_id' => $categories[$cat], 'brand_id' => $brands[$brand],
                'unit_id' => $unit($unitCode), 'tax_rate_id' => $taxId, 'purchase_price' => $purchase, 'list_price' => $list,
                'dealer_price' => $dealer, 'pack_quantity' => $pack, 'min_order_qty' => $step, 'order_step' => $step,
                'critical_stock_level' => $critical, 'barcode' => '869'.str_pad((string) crc32($sku) % 10_000_000_000, 10, '0', STR_PAD_LEFT),
                'description' => "{$brand} {$name}. Koli içi {$pack} {$unitCode}; sipariş {$step} {$unitCode} ve katları ile verilir.",
                'variant_group_id' => $variant ? $variants[$variant[0]] : null,
                'variant_values' => $variant ? ['Renk' => $variant[1]] : null,
            ]);

            $productService->addImage($product, $images->product($shape, $colors));

            $product->load('unit');
            if ($qtyMain > 0) {
                $stock->adjust($product, $main, 'opening', (string) $qtyMain, 'Açılış stoğu');
            }
            if ($qtyBranch > 0) {
                $stock->adjust($product, $branch, 'opening', (string) $qtyBranch, 'Açılış stoğu');
            }
        }
    }

    private function dealer(): DealerRelationship
    {
        $group = DealerGroup::create(['name' => 'Altın Bayiler']);

        $rel = app(DealerOnboarding::class)->createDirect(
            [
                'legal_name' => 'Onur Kırtasiye', 'email' => 'burakgul3086@gmail.com', 'phone' => '0216 345 12 12',
                'tax_office' => 'Kadıköy', 'tax_number' => '1234567890',
                'city' => 'İstanbul', 'district' => 'Kadıköy', 'address' => 'Caferağa Mah. Moda Cd. No:42',
            ],
            'Onur Kırtasiye Yetkilisi',
            null,
            ['dealer_code' => 'B0001', 'dealer_group_id' => $group->id, 'payment_term_days' => 30, 'can_edit_addresses' => true],
            self::PASSWORD,
        )['relationship'];

        $rel->addresses()->create([
            'title' => 'Ataşehir Şube', 'recipient_name' => 'Şube Sorumlusu', 'phone' => '0216 456 78 90',
            'city' => 'İstanbul', 'district' => 'Ataşehir', 'address_line' => 'Barbaros Mah. Begonya Sk. No:7', 'is_default' => false,
        ]);

        return $rel;
    }

    /** Grup fiyat listesi, bayiye özel fiyat ve iki indirim kuralı. */
    private function pricing(DealerRelationship $rel): void
    {
        $list = PriceList::create(['name' => 'Altın Bayi Fiyatları 2026']);
        foreach (['TK-10-MAV' => 7.40, 'TK-10-SYH' => 7.40, 'TK-10-KRM' => 7.40, 'FK-A4-500' => 139.00, 'KK-HB-12' => 46.50] as $sku => $price) {
            PriceListItem::create(['price_list_id' => $list->id, 'product_id' => $this->product($sku), 'price' => $price]);
        }
        $rel->group->update(['price_list_id' => $list->id]);

        DealerProductPrice::create(['dealer_relationship_id' => $rel->id, 'product_id' => $this->product('SD-A4-100'), 'price' => 51.00]);

        DiscountRule::create([
            'name' => 'Okul sezonu defter kampanyası', 'scope' => 'category', 'category_id' => Category::where('name', 'Karton Kapak Defter')->value('id'),
            'target' => 'all', 'percent' => '10', 'starts_on' => now()->subDays(10)->toDateString(), 'ends_on' => now()->addMonth()->toDateString(),
        ]);
        DiscountRule::create([
            'name' => 'Altın bayilere boya indirimi', 'scope' => 'category', 'category_id' => Category::where('name', 'Boya')->value('id'),
            'target' => 'group', 'dealer_group_id' => $rel->dealer_group_id, 'percent' => '5',
        ]);
    }

    /** Her durumdan sipariş: tamamlanan (+iade), hazırlanan, onaylanan, incelenen, bekleyen, reddedilen, iptal edilen. */
    private function orders(Business $business, DealerRelationship $rel): void
    {
        $carts = app(CartService::class);
        $orders = app(OrderService::class);
        $notifier = app(Notifier::class);
        $warehouse = (int) $business->settings()->value('default_warehouse_id');
        $userId = $business->user->id;
        $addresses = $rel->addresses()->orderByDesc('is_default')->pluck('id');

        $place = function (array $lines, ?string $note, int $daysAgo, int $address = 0) use ($carts, $orders, $rel, $addresses, $notifier): Order {
            foreach ($lines as $sku => $qty) {
                $carts->add($rel, $this->product($sku), (string) $qty);
            }
            $order = $orders->submit($rel, $addresses[$address], $note, (string) Str::uuid());
            $notifier->orderSubmitted($order);
            $order->getConnection()->table('orders')->where('id', $order->id)->update(['created_at' => now()->subDays($daysAgo)->setTime(10, 15)]);

            return $order->refresh();
        };
        $approve = fn (Order $o, int $days) => $orders->approve($o, [], $warehouse, ['planned_on' => now()->addDays($days)->toDateString()], $userId);

        // 1) Tamamlandı + kısmi iade
        $done = $place(['SD-A4-100' => 100, 'KD-A5-60' => 200, 'TK-10-MAV' => 500, 'KK-HB-12' => 24], 'Eylül okul sezonu ilk sipariş', 34);
        $approve($done, -30);
        $orders->markPreparing($done->refresh());
        $orders->complete($done->refresh());
        $notifier->orderStatusChanged($done->refresh());
        $item = $done->items()->whereHas('product', fn ($q) => $q->where('sku', 'KD-A5-60'))->first();
        app(ReturnService::class)->create($done->refresh(), [$item->id => '20'], 'Kapakları hasarlı gelen defterler', $userId);

        // 2) Tamamlandı
        $done2 = $place(['FK-A4-500' => 50, 'YN-76-3RN' => 24, 'ZM-10-KRM' => 12], 'Ofis müşterileri için', 21, 1);
        $approve($done2, -18);
        $orders->complete($done2->refresh());

        // 3) Hazırlanıyor
        $prep = $place(['JK-07-MAV' => 48, 'FS-SARI' => 40, 'SG-BYZ-01' => 100, 'CT-30-SFF' => 50], null, 6);
        $approve($prep, 2);
        $orders->markPreparing($prep->refresh());
        $notifier->orderStatusChanged($prep->refresh());

        // 4) Onaylandı (kısmi onay: kuru boyanın bir kısmı sonraki sevkiyatta)
        $partial = $place(['KB-12-UZN' => 24, 'SB-12-TBL' => 12, 'YS-21-STK' => 48], 'Boya ürünleri acil', 3);
        $kb = $partial->items()->whereHas('product', fn ($q) => $q->where('sku', 'KB-12-UZN'))->value('id');
        $orders->approve($partial, [$kb => '18'], $warehouse, ['planned_on' => now()->addDays(3)->toDateString()], $userId);
        $notifier->orderStatusChanged($partial->refresh(), 'Kuru boyanın 18 paketi bu sevkiyatta; kalan 6 paket için yeni sipariş verebilirsiniz.');

        // 5) İnceleniyor
        $review = $place(['TK-10-SYH' => 300, 'TK-10-KRM' => 100, 'KK-2B-01' => 360], null, 1);
        $orders->markInReview($review);

        // 6) Onay bekliyor
        $place(['SD-A5-80' => 60, 'KC-12-SET' => 12, 'FS-PEMBE' => 20], 'Mümkünse cuma teslim', 0, 1);

        // 7) Reddedildi
        $rejected = $place(['MK-13-OKL' => 36], null, 12);
        $notifier->orderStatusChanged($orders->reject($rejected, 'Makas fiyatlarımız güncelleniyor; yeni fiyat listesiyle tekrar sipariş verebilirsiniz.'));

        // 8) Bayi iptal etti
        $cancelled = $place(['FK-A4-500' => 20], null, 9);
        $notifier->orderCancelledByDealer($orders->cancelByDealer($cancelled, 'Yanlışlıkla iki kez sipariş verildi.'));

        // Açık sepet
        $carts->add($rel, $this->product('KT-HZN-01'), '24');
        $carts->add($rel, $this->product('CT-30-SFF'), '25');
    }

    private function ledger(Business $business, DealerRelationship $rel): void
    {
        $ledger = app(LedgerService::class);
        $account = CurrentAccount::where('dealer_relationship_id', $rel->id)->first();
        $userId = $business->user->id;

        $ledger->setOpening($account, '4850.00', now()->subDays(60)->toDateString(), 'Önceki sistemden devir bakiyesi');
        $ledger->recordCollection($account, ['collected_on' => now()->subDays(25)->toDateString(), 'amount' => '4850.00', 'method' => 'transfer', 'description' => 'Havale - devir bakiyesi kapanışı'], $userId);
        $ledger->recordCollection($account, ['collected_on' => now()->subDays(12)->toDateString(), 'amount' => '15000.00', 'method' => 'transfer', 'description' => 'EFT dekont no 778451'], $userId);
        $ledger->recordCollection($account, ['collected_on' => now()->subDays(2)->toDateString(), 'amount' => '2500.00', 'method' => 'cash', 'description' => 'Elden tahsilat'], $userId);
    }

    private function communication(Business $business, DealerRelationship $rel): void
    {
        $messages = app(MessagingService::class);
        $dealerUserId = $rel->dealer->user->id;
        $userId = $business->user->id;

        $c = $messages->start($rel, 'Okul sezonu fiyat listesi', 'Merhaba, eylül dönemi için defter ve boya ürünlerinde koli bazlı ek iskonto yapabiliyor musunuz?', 'dealer', $dealerUserId);
        $messages->reply($c, 'business', $userId, 'Merhaba Onur Bey, karton kapak defterlerde %10 kampanya başladı; altın bayilerimize boyalarda ek %5 uyguluyoruz. Koli siparişlerinde ayrıca konuşabiliriz.');
        $messages->reply($c, 'dealer', $dealerUserId, 'Teşekkürler, bu hafta toplu sipariş geçeceğim.');

        $c2 = $messages->start($rel, 'Teslimat günü', 'Hazırlanan siparişiniz perşembe günü Ataşehir şubenize teslim edilecektir.', 'business', $userId);
        $messages->markRead($c2, 'dealer');

        $requests = app(ProductRequestService::class);
        $requests->create($rel, $this->product('JK-07-MOR'), '48', 'Okul açılışı için mor jel kalem lazım.', true);
        $open = $requests->create($rel, $this->product('DS-TEL-KRM'), '20', null, true);
        $open->update(['created_at' => now()->subDays(4)]);
        $requests->respond($open->refresh(), 'available', 'Tedarikçiye sipariş verildi, gelecek hafta stokta.', $userId);
        app(Notifier::class)->requestUpdated($open->refresh());

        $announcement = Announcement::create([
            'title' => 'Okul sezonu kampanyası başladı',
            'body' => "Karton kapak defterlerde %10 indirim ay sonuna kadar geçerlidir.\nAltın bayilerimize boya ürünlerinde ek %5 indirim uygulanır.",
            'send_email' => false, 'published_at' => now()->subDays(5), 'created_by' => $userId,
        ]);
        app(Notifier::class)->announcement($announcement);

        DealerApplication::create([
            'legal_name' => 'Deniz Kitabevi', 'contact_name' => 'Deniz Kaya', 'phone' => '0232 444 55 66', 'email' => 'deniz@denizkitabevi.com.tr',
            'city' => 'İzmir', 'district' => 'Bornova', 'address' => 'Kazımdirik Mah. 372. Sk. No:5', 'business_type' => 'bookstore',
            'activity_area' => 'Okul ve kırtasiye', 'note' => 'Okul sezonu için sizinle çalışmak istiyoruz.',
            'consent_text_version' => DealerOnboarding::CONSENT_VERSION, 'consent_at' => now(), 'ip' => '127.0.0.1',
        ]);
    }

    private function product(string $sku): int
    {
        return (int) Product::where('sku', $sku)->value('id');
    }
}
