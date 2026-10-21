# Mağaza M4 — Vitrin

> Testler: 294/294. Mimari: [13-magaza-mimarisi.md](13-magaza-mimarisi.md) §12. Müşterinin göreceği mağaza sayfaları ve vitrin yönetimi.

## 1. Yayın kapısı ve önizleme

- Mağaza ayarlarındaki **"Mağazayı aç / kapat"** düğmesiyle yönetilir. Varsayılan durum kapalıdır.
- **Kapalıyken:**
  - Ziyaretçi "Çok yakında" sayfasını görür (HTTP 503 + `Retry-After`; arama motorları dizine eklemez).
  - `robots.txt` her şeyi kapatır, `sitemap.xml` 404 döner.
- **Önizleme:**
  - Mağaza panelindeki "Mağazayı önizle" bağlantısı **imzalı** ve 15 dakika geçerlidir.
  - Açılınca o tarayıcıya 2 saatlik, şifreli bir önizleme çerezi verilir. Kapalı mağaza bu süre içinde gezilebilir.
  - Önizlemede üstte sarı bir uyarı şeridi ve "Önizlemeyi kapat" bağlantısı görünür; sayfalar `noindex`'tir.
- Açma ve kapama işlem geçmişine yazılır.

## 2. Mağaza sayfaları (`magaza.localhost` / `yapbozkirtasiye.com`)

| Adres | İçerik |
|---|---|
| `/` | Banner'lar (otomatik geçiş, mobil görsel desteği), kategori kutuları, öne çıkanlar, yeni ürünler, markalar |
| `/urunler`, `/kategori/{ad}`, `/marka/{ad}`, `/ara?q=` | Ortak liste: marka filtresi (ürün sayılarıyla), fiyat aralığı, "yalnızca stoktakiler", sıralama (önerilen, en yeni, fiyat ↑↓, ada göre), 24'lü sayfalama. Kategori sayfası alt kategorileri de kapsar ve onları kısayol olarak gösterir. |
| `/urun/{ad}` | Görsel galerisi (imleçle büyütme), seçenek seçimi (fiyat, indirim, stok durumu ve ürün kodu seçenekle değişir), "Stokta / Son X adet / Tükendi", açıklama, benzer ürünler |
| `/markalar` | Ürünü olan görünür markalar |
| `/sayfa/{ad}` | Sabit sayfalar |
| `/robots.txt`, `/sitemap.xml` | Arama motoru dosyaları |

Kurallar:
- **Görünürlük:**
  - Yalnızca yayındaki ve en az bir satıştaki seçeneği olan ürünler görünür.
  - Pasif kategori ve markaların sayfaları açılmaz. Üst kategorisi pasif olan alt kategori de açılmaz.
- **Arama:**
  - Ad, marka, stok kodu, barkod ve seçenek değerinde arar.
  - Her kelime eşleşmelidir.
  - Dakikada 60 istekle sınırlıdır.
- **Satılabilir stok:** Ürün sayfasında stoktan ödeme aşamasında ayrılanlar düşülerek gösterilir (M3).
- **Sepet:** "Sepete ekle" düğmesi M5'e kadar pasiftir; altında "Online sipariş çok yakında" notu vardır.
- **Tasarım:** Mobil önceliklidir.
  - Mobilde hamburger menü (kategoriler açılır kapanır), üstte arama, filtreler açılır panelde.
  - Masaüstünde kategori menüsü alt kategorileri açılır listede gösterir.
- **Alt bilgi:** Kısa tanıtım, sosyal medya, kategoriler, sabit sayfalar, iletişim (telefon, WhatsApp, e-posta, adres) ve B2B "Bayi girişi" bağlantısı.

## 3. Görseller

- Herkese açık adresler (`/gorsel/urun|banner|marka/...`) yalnızca yayındaki ürünlerin, etkin banner ve markaların görsellerini sunar. Yanıtlar 7 gün önbelleklenir (`Cache-Control: public`).
- `?w=` ile küçük boyut istenebilir (320, 640, 1000 px). Boyut ilk istekte üretilir ve diske yazılır (`…/w640/dosya.webp`). Ürün kartları 640, galeri 1000, küçük resimler 320 px kullanır. Görsel silinince küçük boyutları da silinir.

## 4. Arama motoru (SEO)

- Her sayfada başlık, açıklama, kanonik adres, `robots` etiketi ve Open Graph etiketleri (ürünlerde görselle).
- Filtrelenmiş, sayfalanmış ve arama sonuç sayfaları `noindex, follow` olur; kanonik adres filtresiz listedir.
- **Yapılandırılmış veri (JSON-LD):**
  - Ürün: `Product`; tek seçenekte `Offer`, çoklu seçenekte `AggregateOffer`. Stok durumu, marka, SKU, 13 haneli barkodda GTIN, görseller.
  - Liste ve ürün sayfaları: `BreadcrumbList`.
  - Ana sayfa: `Store` ve site içi arama (`WebSite` + `SearchAction`).
- `sitemap.xml`: ana sayfa, ürün listesi, markalar, görünür kategoriler, ürünü olan markalar, yayındaki ürünler ve sayfalar (son güncelleme tarihleriyle).
- `robots.txt`:
  - Mağaza açıkken `/ara` ve `/onizleme` hariç açıktır ve site haritasını gösterir.
  - B2B (`localhost`) ve mağaza yönetimi her zaman tamamen kapalıdır (`RobotsController`).
  - Eski statik `public/robots.txt` kaldırıldı; içeriği her şeye açıktı.

## 5. Vitrin yönetimi (Mağaza yönetimi → Vitrin)

- **Banner'lar:**
  - Masaüstü görseli zorunlu (2560 px'e kadar); mobil görsel isteğe bağlı.
  - Başlık, alt başlık, düğme yazısı, bağlantı, yayın tarih aralığı, sıralama, pasif yapma, mobil görseli kaldırma.
  - Bağlantı yalnızca mağaza içi yol (`/…`) veya `https://` adresi olabilir. `javascript:` ve `//başka-site` reddedilir.
- **Sayfalar:**
  - Başlık, sade Markdown içerik, adres, SEO, yayında, alt bilgide göster.
  - İçerikteki ham HTML ve tehlikeli bağlantılar atılır; `<script>` çalışmaz.
- **Mağaza Ayarları:**
  - İletişim bilgileri, duyuru şeridi, kısa tanıtım, ana sayfa SEO başlığı ve açıklaması, sosyal medya (`https` zorunlu).
  - Yayın durumu ve önizleme.
- **Genel Bakış:** Mağazanın açık veya kapalı olduğunu ve önizleme bağlantısını gösterir.

## 6. Testler (bu aşamada 11 yeni test)

| Dosya | Kapsam |
|---|---|
| `Store/StorefrontTest` (8) | Kapalı mağaza ve önizleme (imzasız bağlantı reddi); yalnızca yayındaki ürünler; kategori ağacı ve pasif kategori; filtreler, sıralama, arama ve `noindex`; ürün sayfası seçenekleri, satılabilir stok, JSON-LD ve kanonik adres; görsel boyutlandırma ve yayında olmayan ürün görseline erişememe; ana sayfa, banner tarih aralığı, sayfalarda HTML temizliği, markalar; `robots.txt` ve `sitemap.xml` (B2B ve yönetim dahil) |
| `Store/StorefrontAdminTest` (3) | Banner ekleme, bağlantı doğrulaması, sıralama, mobil görsel, silmede dosya temizliği; sayfa yönetimi; ayarlar, doğrulama, mağazayı açma ve mağazaya yansıması |

## 7. M5 gereksinimleri (Müşteri ve sepet)

- `customer` girişi: üyelik, giriş, parolamı unuttum, hesabım (bilgiler, adresler). Misafir alışveriş.
- Adres defteri: il ve ilçe sabit listeden seçilir (`tr_provinces`, `tr_districts`).
- Sepet: oturum (misafir) ve hesap sepeti, girişte birleştirme; stok ve fiyat sepet görüntülenirken yeniden doğrulanır.
- Ürün sayfası ve kartlardan sepete ekleme; başlıkta sepet ve hesap simgeleri.
- Kupon altyapısı (uygulama M8).
