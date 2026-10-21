# Mağaza M3 — Stok

> Testler: 283/283 (eşzamanlılık dahil). Mimari: [13-magaza-mimarisi.md](13-magaza-mimarisi.md) §8.2. Mağaza stoğu B2B deposundan bağımsızdır.

## 1. Veri modeli

| Tablo / alan | İçerik |
|---|---|
| `store_product_variants.stock_quantity` | Anlık stok. Hareket toplamının önbelleğidir; yalnızca stok servisi değiştirir. `CHECK (>= 0)` |
| `store_product_variants.critical_stock` | Kritik stok seviyesi (isteğe bağlı) |
| `store_stock_movements` | Yalnızca eklenir. Türler: giriş, sayım farkı, düzeltme, satış, iade, ters kayıt. İşaretli miktar, sonraki stok, açıklama, `source_key` (benzersiz), sipariş referansı, `reverses_id` (benzersiz), işlemi yapan |
| `store_stock_holds` | Ödeme sırasında geçici ayırma: sahip (ör. `checkout:…`), adet, bitiş zamanı, bırakılma ve satışa dönüşme zamanı |

## 2. Kurallar (`App\Domain\Store\Inventory\StoreStockService`)

- Stoğu değiştiren tek yer bu servistir. Her hareket, varyant satırı kilitlenerek (`SELECT … FOR UPDATE`) aynı transaction'da hem hareket tablosuna hem anlık stoğa yazılır. Birden çok varyant kimlik sırasıyla kilitlenir.
- **Stok eksiye düşmez.** Hem servis hem veritabanı kısıtı denetler.
- **Hareketler değiştirilemez ve silinemez** (model düzeyinde engel). Elle yapılan giriş, sayım ve düzeltme **ters kayıtla** geri alınır; her hareket bir kez geri alınabilir. Satış ve iade elle geri alınmaz, sipariş üzerinden düzeltilir (M5–M7).
- **Sayım:** Gerçek adet girilir, fark hareket olarak yazılır. Fark yoksa hareket oluşmaz.
- **Düzeltme:** Fire, hasar, kayıp gibi durumlar için. Açıklama zorunludur.
- **Ayırma (yalnızca mağazada):**
  - `hold` sepetteki adetleri 15 dakika ayırır. Aynı sahip yeniden çağırırsa önceki ayırmanın yerini alır.
  - Satılabilir miktar, stoktan etkin ayırmalar düşülerek bulunur.
  - Süresi dolan veya bırakılan ayırma kendiliğinden geçersizdir.
  - `convert` ayırmayı satış hareketine çevirir. Süresi dolmuşsa stok hâlâ yetiyorsa çevrilir; ikinci çağrı yeni hareket yazmaz.
  - Elle azaltma, ayrılmış miktarın altına inemez.
- **Satış:** `sell` ayırmasız satıştır (ör. kapıda ödeme). Yetmeyen bir kalem varsa hiçbir kalem yazılmaz; hata, her kalem için satılabilir adedi taşır.
- **İade / iptal:** `restock`, aynı `source_key` ile ikinci kez uygulanmaz.
- **Silme:** Stok hareketi olan ürün veya seçenek silinemez; yayından kaldırılır ya da satıştan çıkarılır.
- Her elle işlem işlem geçmişine yazılır: giriş, sayım (eski → yeni), düzeltme, ters kayıt.

## 3. Ekranlar

- **Stok Durumu:**
  - Özet: toplam adet, kritik seviyede, stokta yok.
  - Arama, kategori (alt kategoriler dahil) ve durum filtresi.
  - Her satırda stok, ayrılan, satılabilir, kritik seviye; açılır panelde **Stok girişi / Sayım / Düzeltme** formları ve hareket geçmişine bağlantı.
- **Stok Hareketleri:** Tarih, ürün, tür, ±miktar, sonraki stok, açıklama, kim; tür ve tarih filtresi; uygun hareketlerde "Geri al" (onaylı).
- **Ürün düzenleme:** "Stok" kartında her seçeneğin stoğu ve ayrılmış adedi, aynı işlem formları. Seçenek formuna "Kritik stok" alanı eklendi.
- **Ürün listesi:** Toplam stok sütunu; stoksuz ürünler kırmızı.
- **Genel Bakış:** Yayındaki ürün, kritik stok, stokta yok, etkin yönetici. Menüde "Stok Durumu" yanında kritik stok sayısı görünür.
- **Excel ile aktarma:** "Stok" ve "Kritik Stok" sütunları.
  - Yeni üründe stok değeri ilk stok girişi olur; mevcut üründe stok o değere getirilir (sayım).
  - Boş hücre stoğa dokunmaz.
  - Fiyat, KDV ve seçenek gibi zorunlu hücreler boşsa mevcut değer korunur.

## 4. Testler (bu aşamada 11 yeni test)

| Dosya | Kapsam |
|---|---|
| `Store/StoreStockTest` (8) | Giriş, sayım, düzeltme ve eksi stok engeli; ters kayıt (bir kez), satışın elle geri alınamaması, hareketlerin değiştirilememesi ve silinememesi; ayırma, satılabilir miktar, sahibin ayırmayı değiştirmesi, ayrılanın altına inmeme, satışa çevirme ve tekrar çağrı; süresi dolan ve bırakılan ayırma; hep ya da hiç satış ve iadede tekrar engeli; hareketi olan ürünün silinememesi; kritik stok |
| `Store/StoreProductImportTest` (+1) | Stok sütunu: yeni üründe giriş, mevcut üründe sayım, geçersiz değer |
| `Concurrency/StoreStockConcurrencyTest` (2) | İki ayrı PHP süreci aynı son ürünleri aynı anda ayıramaz veya fazla satamaz |
| `PagesRenderTest` | Stok ekranları, ürün düzenlemede ayrılmış adet, genel bakış |

Her testte anlık stok ile hareket toplamının eşitliği de denetlenir.

## 5. M4 gereksinimleri (Vitrin)

- Mağaza alan adında herkese açık sayfalar: ana sayfa (banner'lar, öne çıkanlar, yeni ürünler), kategori ve marka sayfaları, arama ve filtreler, ürün sayfası (seçenek seçimi, satılabilir stoğa göre "Stokta / Tükendi").
- Yalnızca yayındaki ürünler, görünür kategori ve markalar listelenir.
- Herkese açık görsel adresleri (önbelleklenebilir).
- SEO: okunaklı adresler, başlık ve açıklama, Open Graph, yapılandırılmış veri (Product/Offer/BreadcrumbList), `sitemap.xml`, `robots.txt`.
- Mobil öncelikli tasarım; vitrin yönetimi (banner ve sabit sayfalar) mağaza panelinde.
