<?php

namespace Database\Seeders;

use App\Domain\Dealers\DealerOnboarding;
use App\Domain\Inventory\StockService;
use App\Models\CurrentAccount;
use App\Models\DealerApplication;
use App\Models\DealerGroup;
use App\Models\DiscountRule;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Domain\Platform\ProvisionBusinessDefaults;
use App\Domain\Tenancy\TenantContext;
use App\Models\Brand;
use App\Models\Business;
use App\Models\BusinessModule;
use App\Models\BusinessUser;
use App\Models\Category;
use App\Models\Dealer;
use App\Models\DealerRelationship;
use App\Models\DealerUser;
use App\Models\PlatformAdmin;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\VariantGroup;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Yalnızca yerel geliştirme içindir (DatabaseSeeder local ortam dışında çağırmaz).
 * Tüm hesapların parolası: DEV_PASSWORD. Platform yöneticisi ilk girişte 2FA kurar.
 */
class DevelopmentSeeder extends Seeder
{
    public const DEV_PASSWORD = 'Gelistirme2026';

    public function run(): void
    {
        abort_unless(app()->environment('local', 'testing'), 403, 'Geliştirme verisi yalnızca local ortamda yüklenir.');

        PlatformAdmin::create([
            'name' => 'Platform Yöneticisi', 'email' => 'platform@b2b.test', 'password' => self::DEV_PASSWORD, 'is_active' => true,
        ]);

        $akgun = $this->business('akgun-kirtasiye', 'Akgün Kırtasiye', 'Akgün Kırtasiye Toptan Tic. Ltd. Şti.', 'yonetici@akgun.test');
        $other = $this->business('demo-toptan', 'Demo Toptan', 'Demo Toptan Ticaret A.Ş.', 'yonetici@demotoptan.test');

        // Onur Kırtasiye iki toptancıyla çalışıyor (çoklu toptancı senaryosu).
        $onur = Dealer::create(['legal_name' => 'Onur Kırtasiye', 'phone' => '0212 000 00 00', 'city' => 'İstanbul', 'district' => 'Kadıköy']);
        DealerUser::create([
            'dealer_id' => $onur->id, 'name' => 'Onur Yılmaz', 'email' => 'onur@onurkirtasiye.test',
            'password' => self::DEV_PASSWORD, 'status' => 'active', 'activated_at' => now(),
        ]);

        $tenant = app(TenantContext::class);
        $akgunRel = $tenant->runAs($akgun->id, fn () => $this->relationship($onur->id, 'B0001'));
        $tenant->runAs($other->id, fn () => $this->relationship($onur->id, 'DT-001'));

        $tenant->runAs($akgun->id, function () use ($akgun, $akgunRel) {
            $this->sampleCatalog($akgun);
            $this->samplePricing($akgunRel);
            DealerApplication::create([
                'legal_name' => 'Deniz Kitabevi', 'contact_name' => 'Deniz Kaya', 'phone' => '0232 444 55 66', 'email' => 'deniz@denizkitabevi.test',
                'city' => 'İzmir', 'district' => 'Bornova', 'address' => 'Kazımdirik Mah. 372. Sk. No:5', 'business_type' => 'bookstore',
                'activity_area' => 'Okul ve kırtasiye', 'note' => 'Okul sezonu için çalışmak istiyoruz.',
                'consent_text_version' => DealerOnboarding::CONSENT_VERSION, 'consent_at' => now(), 'ip' => '127.0.0.1',
            ]);
        });
    }

    private function relationship(int $dealerId, string $code): DealerRelationship
    {
        $rel = DealerRelationship::create(['dealer_id' => $dealerId, 'dealer_code' => $code, 'status' => 'active', 'approved_at' => now()]);
        CurrentAccount::create(['dealer_relationship_id' => $rel->id, 'code' => $code]);
        $rel->addresses()->create([
            'title' => 'Merkez Mağaza', 'recipient_name' => 'Onur Yılmaz', 'phone' => '0212 000 00 00',
            'city' => 'İstanbul', 'district' => 'Kadıköy', 'address_line' => 'Moda Cd. No:12', 'is_default' => true,
        ]);

        return $rel;
    }

    /** Örnek grup fiyat listesi ve indirim: önizleme ekranında öncelik kurallarını denemek için. */
    private function samplePricing(DealerRelationship $rel): void
    {
        $list = PriceList::create(['name' => 'A Grubu 2026']);
        foreach (Product::whereIn('sku', ['TK-1010-M', 'TK-1010-S'])->get() as $product) {
            PriceListItem::create(['price_list_id' => $list->id, 'product_id' => $product->id, 'price' => '6.90']);
        }
        $group = DealerGroup::create(['name' => 'A Grubu', 'price_list_id' => $list->id]);
        $rel->update(['dealer_group_id' => $group->id]);

        DiscountRule::create([
            'name' => 'Defter kampanyası', 'scope' => 'category', 'category_id' => Category::where('name', 'Defter')->value('id'),
            'target' => 'all', 'percent' => '10',
        ]);
    }

    /** Akgün Kırtasiye için örnek katalog ve stok (stok, hareketlerle oluşturulur). */
    private function sampleCatalog(Business $business): void
    {
        $unit = fn (string $code) => Unit::where('code', $code)->value('id');
        $tax = TaxRate::where('rate', '20.00')->value('id');
        $tax10 = TaxRate::where('rate', '10.00')->value('id');
        $warehouse = Warehouse::find($business->settings()->value('default_warehouse_id'));
        $stock = app(StockService::class);

        $categories = [];
        foreach (['Kalem' => ['Tükenmez Kalem', 'Kurşun Kalem', 'Jel Kalem'], 'Defter' => ['Spiralli Defter', 'Karton Kapak Defter'], 'Ofis' => ['Zımba', 'Dosya']] as $main => $subs) {
            $parent = Category::create(['name' => $main]);
            foreach ($subs as $sub) {
                $categories[$sub] = Category::create(['name' => $sub, 'parent_id' => $parent->id])->id;
            }
        }
        $brands = collect(['Faber-Castell', 'Pensan', 'Gıpta', 'Noki', 'Mas'])->mapWithKeys(fn ($b) => [$b => Brand::create(['name' => $b])->id]);
        $gel = VariantGroup::create(['name' => 'Jel Kalem 0.7', 'attribute_names' => ['Renk']]);

        $products = [
            ['TK-1010-M', 'Tükenmez Kalem 1.0 Mavi', 'Tükenmez Kalem', 'Pensan', 'ADET', 4.20, 8.50, 7.25, $tax, 50, 50, 500, 1200],
            ['TK-1010-S', 'Tükenmez Kalem 1.0 Siyah', 'Tükenmez Kalem', 'Pensan', 'ADET', 4.20, 8.50, 7.25, $tax, 50, 50, 500, 350],
            ['KK-HB-12', 'Kurşun Kalem HB 12\'li Kutu', 'Kurşun Kalem', 'Faber-Castell', 'PAKET', 28.00, 55.00, 47.50, $tax, 12, 12, 24, 96],
            ['JK-07-M', 'Jel Kalem 0.7 Mavi', 'Jel Kalem', 'Faber-Castell', 'ADET', 9.10, 18.00, 15.50, $tax, 24, 24, 100, 480, ['Renk' => 'Mavi']],
            ['JK-07-K', 'Jel Kalem 0.7 Kırmızı', 'Jel Kalem', 'Faber-Castell', 'ADET', 9.10, 18.00, 15.50, $tax, 24, 24, 100, 60, ['Renk' => 'Kırmızı']],
            ['JK-07-Y', 'Jel Kalem 0.7 Yeşil', 'Jel Kalem', 'Faber-Castell', 'ADET', 9.10, 18.00, 15.50, $tax, 24, 24, 100, 0, ['Renk' => 'Yeşil']],
            ['SD-A4-100', 'Spiralli Defter A4 100 Yaprak Kareli', 'Spiralli Defter', 'Gıpta', 'ADET', 32.00, 62.00, 54.00, $tax10, 20, 10, 100, 640],
            ['KD-A5-60', 'Karton Kapak Defter A5 60 Yaprak Çizgili', 'Karton Kapak Defter', 'Gıpta', 'ADET', 11.50, 22.00, 19.00, $tax10, 40, 20, 200, 150],
            ['ZM-10', 'Zımba Makinesi No:10', 'Zımba', 'Mas', 'ADET', 45.00, 89.90, 78.00, $tax, 12, 6, 12, 8],
            ['DS-TEL-M', 'Telli Dosya Mavi', 'Dosya', 'Noki', 'PAKET', 38.00, 72.00, 63.00, $tax, 10, 5, 20, 0],
        ];

        foreach ($products as $row) {
            [$sku, $name, $cat, $brand, $unitCode, $purchase, $list, $dealer, $taxId, $pack, $step, $critical, $qty] = $row;
            $product = Product::create([
                'sku' => $sku, 'name' => $name, 'category_id' => $categories[$cat], 'brand_id' => $brands[$brand],
                'unit_id' => $unit($unitCode), 'tax_rate_id' => $taxId, 'purchase_price' => $purchase, 'list_price' => $list,
                'dealer_price' => $dealer, 'pack_quantity' => $pack, 'min_order_qty' => $step, 'order_step' => $step,
                'critical_stock_level' => $critical, 'variant_group_id' => isset($row[13]) ? $gel->id : null, 'variant_values' => $row[13] ?? null,
            ]);
            if ($qty > 0) {
                $stock->adjust($product->load('unit'), $warehouse, 'opening', (string) $qty, 'Açılış stoğu');
            }
        }
    }

    private function business(string $slug, string $name, string $legal, string $email): Business
    {
        $business = Business::create([
            'slug' => $slug, 'display_name' => $name, 'legal_name' => $legal, 'status' => 'active',
            'city' => 'İstanbul', 'email' => "iletisim@{$slug}.test",
        ]);
        $business->settings()->create([]);
        app(ProvisionBusinessDefaults::class)->handle($business);
        $business->licenses()->create(['plan' => 'Standart', 'starts_on' => now()->subMonth()->toDateString()]);
        foreach (array_keys(BusinessModule::AVAILABLE) as $key) {
            $business->modules()->create(['module_key' => $key, 'enabled' => true]);
        }

        BusinessUser::create([
            'business_id' => $business->id, 'name' => "{$name} Yöneticisi", 'email' => $email,
            'password' => self::DEV_PASSWORD, 'status' => 'active', 'activated_at' => now(),
        ]);

        return $business;
    }
}
