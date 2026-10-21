# Mağaza M2 — Katalog

> Testler: 272/272. Mimari: [13-magaza-mimarisi.md](13-magaza-mimarisi.md). Mağaza kataloğu B2B ürünlerinden tamamen ayrıdır.

## 1. Veri modeli

| Tablo | İçerik |
|---|---|
| `store_categories` | En fazla 3 seviyeli ağaç (`parent_id`), kısa ad (`slug`), açıklama, SEO alanları, sıra, görünürlük |
| `store_brands` | Ad, kısa ad, logo, görünürlük |
| `store_products` | Ürün grubu: ad, kısa ad, kategori, marka, seçenek adı (`variant_name`, ör. "Renk"), açıklama, SEO, yayında, öne çıkan, ilk yayın tarihi |
| `store_product_variants` | Satılabilir birim: stok kodu (benzersiz), barkod (benzersiz), seçenek değeri, KDV dahil fiyat, üstü çizili fiyat, KDV oranı, desi, ağırlık, satışta |
| `store_product_images` | Ürün görselleri; ilk sıradaki ana görseldir |

Mağaza tabloları B2B tablolarına bağlanmaz (mimari test denetler).

## 2. İş kuralları (`App\Domain\Store\Catalog`)

- **Fiyatlar KDV dahildir.** Türkçe biçimle girilir ("24,90", "1.234,56"). Fazla basamak yuvarlanmaz, reddedilir. Üstü çizili fiyat satış fiyatından yüksek olmalıdır; indirim yüzdesi buradan hesaplanır. KDV oranı %0, %1, %10 veya %20 olabilir.
- **Seçenekler:**
  - Seçenek adı yoksa ürünün tek varyantı vardır.
  - Seçenek adı verilince (ör. Renk) mevcut varyantın değeri de girilir (ör. Mavi); sonra yeni seçenekler eklenir.
  - Aynı üründe aynı seçenek değeri iki kez olamaz; büyük/küçük harf fark etmez.
  - Birden fazla seçeneği olan ürünün seçenek adı kaldırılamaz. Son varyant silinemez.
- **Yayın:**
  - Etkin varyantı olmayan ürün yayına alınamaz.
  - Yayındaki ürünün son etkin varyantı pasif yapılamaz veya silinemez.
  - Toplu yayınlama bu ürünleri atlar.
- **Görseller:**
  - B2B ürün görselleriyle aynı işlemden geçer: beyaz kenar kırpma, 2000 px, %90 kalite, gerekirse netleştirme.
  - Ürün başına en fazla 10 görsel; ilki ana görseldir, "Ana yap" ile değiştirilir.
  - Ürün silinince görsel dosyaları da silinir.
- **Kategoriler:**
  - En fazla 3 seviye.
  - Kategori kendi altına taşınamaz; taşıma, alt ağacın derinliğiyle birlikte denetlenir.
  - Ürünü veya alt kategorisi olan kategori silinemez.
  - Aynı seviyede yukarı/aşağı sıralanır.
- **Markalar:** Ad benzersizdir. Ürünü olan marka silinemez. Logo 480 px'e küçültülür ve kenarı kırpılır.
- **Kısa adlar (adresler):** Türkçe karakterler dönüştürülür ("Şık Çizgili Ödev Defteri" → `sik-cizgili-odev-defteri`). Çakışırsa sonuna `-2`, `-3` eklenir.
- **Ürün silme:** Şimdilik kalıcıdır. Satış başladığında (M5) satışı olan ürünün silinmesi engellenecek.
- **İşlem geçmişi:** Her ekleme, güncelleme, silme, yayınlama, görsel işlemi ve Excel aktarımı kaydedilir. Fiyat değişikliklerinde eski ve yeni değer tutulur.

## 3. Excel ile aktarma

- Şablon: Stok Kodu, Ürün Adı, Ürün Grubu, Seçenek Adı, Seçenek, Barkod, Kategori, Marka, Açıklama, Satış Fiyatı (KDV Dahil), Üstü Çizili Fiyat, KDV Oranı, Desi, Ağırlık (gr), Yayında.
- Her satır bir varyanttır.
  - Stok kodu mağazada varsa o varyant güncellenir, yoksa eklenir.
  - Aynı "Ürün Grubu" değerine sahip satırlar tek ürünün seçenekleridir.
  - Grup değeri mağazadaki bir stok koduysa, satır o ürüne yeni seçenek olarak eklenir.
- Kategori yolu yazılabilir: `Kalemler > Jel Kalem`. Eksik kategori ve markalar, seçenek işaretliyse oluşturulur.
- Güncellemede:
  - Dosyada olmayan sütun değişmez.
  - Sütun var ama hücre boşsa isteğe bağlı alan (barkod, üstü çizili fiyat, desi…) temizlenir.
  - Boş ürün hücreleri (ad, açıklama, kategori, marka) mevcut değeri korur.
- Her satır kendi transaction'ındadır; hatalı satır diğerlerini etkilemez.
- Sonuç ekranında eklenen, güncellenen ve hatalı satır sayıları ile her hatanın satır numarası ve açıklaması gösterilir. Yeniden yükleme ürünleri çoğaltmaz.
- Yeni ürünler, "Yayında" sütununa "Evet" yazılmadıkça taslak eklenir.

## 4. Ekranlar (Mağaza yönetimi → Katalog)

- **Ürünler:**
  - Arama (ad, stok kodu, barkod), kategori filtresi (alt kategoriler dahil), marka ve durum filtresi (yayında, taslak, öne çıkan, görselsiz), sıralama.
  - Seçilenleri toplu yayına alma veya yayından kaldırma.
  - Fiyat aralığı gösterimi.
- **Yeni ürün:** Ürün bilgileri ve ilk seçeneğin fiyatı / stok kodu tek formda. Seçenek adı yazılınca ilk seçeneğin değeri sorulur.
- **Ürün düzenleme:**
  - Bilgiler ve SEO.
  - Fiyat (tek seçenekli) veya açılır kapanır seçenek listesi; hatalar ilgili seçeneğin formunda gösterilir.
  - Yeni seçenek ekleme.
  - Görseller: çoklu yükleme, ana yap, kaldır.
  - Mağaza adresi önizlemesi, silme.
- **Kategoriler:** Ağaç görünümü, alt kategori ekleme, sıralama, düzenleme (üst kategori, açıklama, adres, SEO, görünürlük), silme.
- **Markalar:** Liste, satırda düzenleme, logo yükleme ve kaldırma, silme.
- **Excel ile aktar:** Şablon indirme, yükleme, sonuç raporu.

Katalog işlerini tüm mağaza yöneticileri yapabilir; yalnızca yönetici hesapları süper admine özeldir.

## 5. Testler (bu aşamada 12 yeni test)

| Dosya | Kapsam |
|---|---|
| `Store/StoreCatalogTest` (8) | Ürün + ilk varyant, Türkçe kısa ad, fiyat/indirim; varyant doğrulamaları; seçenek akışı ve yayın kuralları; görsel işleme, sıra, silme; kategori ağacı kuralları; markalar ve logo; toplu yayın; B2B oturumuyla erişilememe |
| `Store/StoreProductImportTest` (4) | Ekleme, gruplama, kategori yolu, satır hataları; yeniden yüklemede güncelleme ve korunan sütunlar; eksik kategori hatası; şablon ve hatalı dosya |
| `PagesRenderTest` | Katalog sayfalarının boş ve dolu hâlleri |

## 6. M3 gereksinimleri (Stok)

- `store_stock_movements`: yalnızca eklenir (giriş, sayım farkı, satış, iade, iptal); varyant bazlı.
- Mal girişi, sayım ve düzeltme (ters kayıt), kritik stok seviyesi ve uyarısı.
- `store_stock_holds`: ödeme sırasında geçici ayırma altyapısı (kullanımı M5–M6'da).
- Ürün listesinde ve düzenleme ekranında stok; Excel aktarımında başlangıç stoğu.
