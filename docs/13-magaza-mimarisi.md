# Mağaza (B2C) Mimarisi ve Tek Toptancı Revizyonu

> Durum: **Onaylandı (M0, 2026-10-21).** `CLAUDE.md` kuralları bu dokümana göre güncellendi.
> Tarih: 2026-10-21. Kararlar müşteriyle yapılan görüşmelere dayanır.

## 1. Amaç ve kapsam

İki istek var:

1. **B2B tek toptancıya iner.** Sistem yalnızca Yapboz Kırtasiye için çalışır. Tek toptancı vardır, bayi sayısı sınırsızdır. Bugünkü toptancı–bayi işleyişi aynen korunur.
2. **Herkese açık bir mağaza eklenir.** Google'da "Yapboz Kırtasiye" aratan son kullanıcılar bu mağazadan alışveriş yapar. Mağazanın kendi yönetim paneli vardır. Mağaza B2B'den **tamamen bağımsızdır**: kendi ürünleri, stoğu, fiyatları, siparişleri ve kullanıcıları olur.

## 2. Kararlar

| # | Konu | Karar |
|---|---|---|
| D1 | İşletme sayısı | Tek toptancı (Yapboz). Platformdan ikinci işletme açılamaz. |
| D2 | Mağaza sayısı | Tek mağaza; sahibi Yapboz. Başka toptancıya mağaza yok. |
| D3 | Mağaza–B2B ilişkisi | Hiçbir veri ortak değil: ürün, stok, fiyat, sipariş ve kullanıcı ayrı. Depo fiziksel olarak tek ama mağaza stoğu mağaza panelinde ayrıca tutulur; iki stok birbirini otomatik düşürmez. |
| D4 | Mağaza yönetimi | Ayrı adreste, ayrı girişle çalışan ayrı panel. B2B toptancı panelinde mağaza yönetilmez ve görünmez. |
| D5 | Mağaza yöneticileri | Platform **süper admin** açar. Süper admin **yönetici** açar. Yöneticiler birbirini ve süper admini silemez. Ayrıntı §5'te. |
| D6 | Girişler | Beş ayrı giriş: müşteri, mağaza yöneticisi, bayi, toptancı, platform. Her birinin oturumu ayrı. |
| D7 | Kart ödemesi | iyzico Ödeme Formu (3D Secure). Altyapı ve başvuru şartları canlıya çıkıştan önce hazır olur. Başvuru yayından sonra yapılır; onay gelince panelden açılır. |
| D8 | Kapıda ödeme | Yalnızca Eskişehir. Teslimatı mağazanın kendi aracı yapar, kargo kullanılmaz. Hangi ilçelerde geçerli olduğu ve tutar üst sınırı panelden değiştirilir. SMS doğrulaması zorunludur. |
| D9 | SMS | Netgsm. Altyapı şimdi kurulur, hesap bilgileri hesap açıldığında panelden girilir. |
| D10 | Fatura | e-Arşiv entegratörü Uyumsoft. API bilgileri gelene kadar manuel fatura (PDF yükleme) kullanılır. |
| D11 | Kargo | Henüz anlaşma yok. Firmadan bağımsız sürücü yapısı kurulur: ilk gün manuel kargo, anlaşma yapılınca firma sürücüsü eklenir. |
| D12 | Bayi perakende alışveriş | Serbest, ama ayrı müşteri hesabıyla. İki sistem ayrı olduğu için mağazada B2B bilgisi hiçbir koşulda görünmez. |
| D13 | Alan adı | `yapbozkirtasiye.com` alınacak (henüz alınmadı). |

## 3. Sistem haritası ve adresler

| Alan | Canlı adres | Yerel geliştirme | Giriş türü (guard) |
|---|---|---|---|
| Mağaza (herkese açık) | `yapbozkirtasiye.com` | `http://magaza.localhost:8000` | `customer` |
| Mağaza yönetimi | `yonetim.yapbozkirtasiye.com` | `http://yonetim.localhost:8000` | `store_admin` |
| Bayi | `b2b.yapbozkirtasiye.com/bayi` | `http://localhost:8000/bayi` | `dealer` |
| Toptancı | `b2b.yapbozkirtasiye.com/toptanci` | `http://localhost:8000/toptanci` | `business` |
| Platform | `b2b.yapbozkirtasiye.com/platform` | `http://localhost:8000/platform` | `platform` |

- Rotalar alan adına bağlanır (`Route::domain`). Adresler ortam ayarlarından okunur: `STORE_DOMAIN`, `STORE_ADMIN_DOMAIN`, `B2B_DOMAIN`.
- Her alanın kendi oturum çerezi vardır. Bugünkü `UseAreaSession` iki yeni alanla genişletilir. Bir alandaki oturum diğerinde hiçbir şey açmaz.
- Mağaza Google'a açıktır. B2B ve mağaza yönetimi `noindex` ile ve `robots.txt` üzerinden kapatılır.
- `*.localhost` adresleri tarayıcıda ek ayar gerektirmeden çalışır.

## 4. B2B tarafındaki değişiklikler (tek toptancı)

Mevcut çok işletmeli altyapı (`BelongsToBusiness`, `SetBusinessContext`, işletme kapsamlı sorgular, izolasyon testleri) **yerinde kalır.** Sökülmesi yüzlerce dosyaya dokunmak ve yazılmış güvenlik testlerini boşa çıkarmak demek. Sistem tek işletmeye kilitlenir:

- Platform: bir işletme varken "Yeni işletme" kapanır. "İşletmeler" ekranı tek işletmenin kartına döner.
- Bayi açarken toptancı seçimi kalkar, Yapboz otomatik seçilir.
- Bayilik başvuru formu doğrudan Yapboz'a gider.
- Bayinin toptancı seçim ekranı fiilen hiç görünmez; kod kalır.
- Hesap modeli (12-revizyonlar §3) değişmez: toptancıyı platform açar, bayiyi platform veya toptancı açar.

## 5. Mağaza yöneticileri ve yetkiler

| İşlem | Süper admin | Yönetici | Platform |
|---|---|---|---|
| Mağazadaki tüm işler (katalog, stok, sipariş, ayarlar…) | ✓ | ✓ | — |
| Yönetici açma | ✓ | ✗ | ✓ |
| Yöneticiyi pasife alma, şifresini sıfırlama | ✓ | ✗ | ✓ |
| Süper admin açma, pasife alma, şifre sıfırlama | ✗ | ✗ | ✓ |
| Kendi e-posta ve şifresini değiştirme | ✓ | ✓ | — |

- Hesaplar silinmez, **pasife alınır.** Böylece işlem geçmişindeki "kim yaptı" bilgisi korunur.
- Aktivasyon bağlantısı yoktur. Hesabı açan kişi e-postayı ve şifreyi belirler, hesap hemen etkindir (B2B ile aynı model). Parolamı unuttum vardır.
- Süper admin için iki adımlı doğrulama (TOTP) zorunludur. Yöneticiler isteğe bağlı açar.
- **İşlem geçmişi:** Fiyat, stok, sipariş durumu, ayarlar ve kullanıcı değişikliklerinin hepsi "kim, ne zaman, eski değer → yeni değer" olarak kaydedilir. Kayıtlar yalnızca eklenir.
- Yönetici sayısı sınırsızdır. Rol sistemi yoktur; tek ayrım süper admin bayrağıdır (`is_super`).

## 6. Modül sınırı

- Kod: `app/Domain/Store/*` (Action ve servis sınıfları), `app/Http/Controllers/Store/{Admin,Front}`, `resources/views/store/{admin,front}`.
- Tablolar `store_` önekiyle başlar ve hiçbiri B2B tablolarına yabancı anahtarla bağlanmaz.
- **Yasak:** Mağaza kodu `App\Models` altındaki B2B modellerini (Product, Order, Dealer…) ve B2B `Domain` modüllerini kullanamaz. B2B de mağaza kodunu kullanamaz. Bir mimari test (`tests/Architecture`) bu yasağı denetler; ihlalde kırılır.
- Ortak kullanılabilecek altyapı: `App\Domain\Support` (Money, Quantity vb.), `App\Domain\Files` (görsel ve belge yükleme), güvenlik başlıkları, e-posta altyapısı.
- Mağaza tek işletmeye ait olduğu için `BelongsToBusiness` kullanmaz. Mağaza verisi zaten tek sahiplidir. Mağaza ile B2B'nin ayrılığı, modül sınırı ve guard ayrımıyla sağlanır.

## 7. Veri modeli (özet)

| Grup | Tablolar |
|---|---|
| Yönetim | `store_admins` (is_super, is_active, 2FA), `store_audit_logs`, `store_settings` |
| Katalog | `store_categories` (ağaç), `store_brands`, `store_products` (ürün grubu, açıklama, SEO), `store_product_variants` (satılabilir birim: SKU, barkod, varyant etiketi, KDV dahil fiyat, üstü çizili fiyat, KDV oranı, desi/ağırlık, aktiflik), `store_product_images` |
| Stok | `store_stock_movements` (yalnızca eklenir: giriş, sayım, satış, iade, iptal), `store_stock_holds` (ödeme sırasında geçici ayırma, bitiş zamanlı) |
| Müşteri | `store_customers`, `store_customer_addresses` |
| Sepet | `store_carts`, `store_cart_items` (misafir: oturum belirteci; üye: müşteri) |
| Sipariş | `store_orders`, `store_order_items` (ad, fiyat ve KDV anlık kopya), `store_order_events` (durum geçmişi) |
| Ödeme | `store_payments` (sürücü, durum, tutar, sağlayıcı kimlikleri, ham yanıt), `store_refunds` |
| Teslimat | `store_shipments` (sürücü: manuel kargo / kendi teslimat / firma), `store_shipment_events` |
| Fatura | `store_invoices` (sürücü: manuel / Uyumsoft, numara, PDF, durum) |
| Kapıda ödeme | `store_cod_blocklist` (telefon ve adres), ilçe izinleri ve sınırlar `store_settings` içinde |
| SMS | `store_sms_messages` (gönderim kaydı), `store_otp_codes` (karma (hash) olarak) |
| Pazarlama | `store_coupons`, `store_campaigns` |
| İçerik | `store_banners`, `store_pages`, `store_legal_documents` (sürümlü), `store_order_consents` (hangi sürüm, ne zaman, hangi IP) |
| Coğrafya | `tr_provinces`, `tr_districts` (sabit liste; adreste il ve ilçe serbest metin değildir) |

Ayrıntılı ER diyagramı M1'de `02-veritabani-er.md` dosyasına eklenir.

## 8. İş kuralları

### 8.1 Fiyat ve KDV
- Mağaza fiyatları **KDV dahil** girilir ve gösterilir. Sipariş kalemi fiyatı, KDV oranını ve KDV tutarını anlık kopya olarak saklar; fiyat sonradan değişse de sipariş değişmez.
- Sepet ve ödeme tutarı **her adımda sunucuda yeniden hesaplanır.** Tarayıcıdan gelen fiyata güvenilmez.

### 8.2 Stok
- Satılabilir stok = stok hareketleri toplamı − etkin ayırmalar.
- Hareketler yalnızca eklenir; düzeltme ters kayıtla yapılır (B2B ile aynı ilke).
- **Ayırma (rezervasyon) yalnızca mağazada vardır.** Kartla ödemede, ödeme adımına geçilince stok 15 dakika ayrılır. Ödeme başarılı olursa ayırma satış hareketine dönüşür. Süre dolarsa veya ödeme başarısız olursa ayırma düşer.
- Ayırma ve satış, ilgili varyant satırı kilitlenerek yapılır (`SELECT … FOR UPDATE`). Aynı son ürünü iki müşteri alamaz. Eşzamanlılık test paketine mağaza senaryoları eklenir.

### 8.3 Sipariş durumları

**Kartla ödeme:**
`ödeme bekleniyor` → `ödendi` → `hazırlanıyor` → `kargoda` veya `yolda` → `teslim edildi`

**Kapıda ödeme:**
`SMS doğrulandı` → (kural varsa `teyit bekliyor`) → `hazırlanıyor` → `yolda` → `teslim edildi + tahsil edildi (nakit/kart)`; alternatif son: `teslim alınmadı` → iptal

**İptal ve iade:**
- Kargoya verilmeden önce iptal edilen siparişte stok ters kayıtla geri girer; kartla ödendiyse iyzico iadesi yapılır.
- Teslimden sonra 14 gün cayma hakkı vardır. İade gelen ürün stok hareketiyle geri girer, tam veya kısmi iade yapılır.

### 8.4 Kapıda ödeme (yalnızca Eskişehir)
- **Bölge:** Teslimat adresinin ili Eskişehir olmalı ve ilçesi panelde izinli olmalı. Varsayılan olarak 14 ilçenin hepsi izinlidir; panelden işaretlenir. Bölge dışında bu seçenek hiç gösterilmez. Sunucu, siparişi oluştururken bölgeyi **ayrıca** denetler.
- **Teslimat:** Mağazanın kendi aracıyla yapılır (sürücü: "Kendi teslimatım"). Kargo firması kullanılmaz.
- **Panelden değiştirilebilen kurallar:**
  - Açık/kapalı
  - İzinli ilçeler
  - **Tutar üst sınırı** (dinamik), isteğe bağlı alt sınır
  - Hizmet bedeli (0 olabilir)
  - Aynı telefon veya hesapta en fazla açık sipariş sayısı (varsayılan 1)
  - "İlk siparişte yönetici teyidi" (açık/kapalı)
- **Güvenlik:**
  1. **SMS doğrulama zorunlu.** 6 haneli kod 3 dakika geçerlidir; en fazla 5 deneme hakkı vardır; yeniden gönderim 60 saniye sonra mümkündür; bir telefona günde en fazla 5 kod gider. Ayrıca IP başına sınır vardır. Kodlar karma (hash) olarak saklanır.
  2. Teslim almayan müşterinin telefonu ve adresi **engel listesine** girer; bu kişiler için kapıda ödeme kapanır, kart ödemesi açık kalır. Engel yönetici tarafından kaldırılabilir.
  3. Ödeme ve kod gönderme uçlarında görünmez robot doğrulaması ve hız sınırı vardır.
  4. SMS sağlayıcısı yapılandırılmadan canlı ortamda kapıda ödeme açılamaz.
- Teslimatta tahsil eden yönetici "tahsil edildi" der ve tahsilat türünü seçer: nakit veya kapıda kart.

### 8.5 Teslimat yöntemleri
- **Kendi teslimatım:** Eskişehir'deki izinli ilçeler için. Kart ve kapıda ödemenin ikisiyle de kullanılabilir. Ücret panelden belirlenir.
- **Kargo:** Tüm Türkiye. Ücret kuralları: sabit, "X TL üzeri ücretsiz", desi tablosu. İlk sürücü manuel kargodur: firma adı, takip numarası ve takip bağlantısı şablonu girilir.

## 9. Dış entegrasyonlar (sürücü yapısı)

Her entegrasyon bir arayüz ve sürücülerden oluşur. Yapılandırma mağaza panelinden yapılır; şifre ve anahtarlar veritabanında şifreli saklanır (B2B'deki Gmail şifresiyle aynı yöntem).

| Arayüz | Sürücüler | Not |
|---|---|---|
| Ödeme | `iyzico`, `kapida` (Eskişehir), `havale` (isteğe bağlı, varsayılan kapalı) | iyzico: Ödeme Formu → token → geri dönüş → sonucun **sunucudan sorgulanması** (`retrieve`) → tutar ve sepet kimliği eşleşmesi. Webhook yedek olarak çalışır. Aynı ödeme iki kez işlenmez. Taksit ve iade desteklenir. Sandbox ve canlı ortam panelden seçilir. |
| SMS | `netgsm`, `log` (geliştirme: kod günlüğe ve panele yazılır) | Netgsm kullanıcı kodu, şifre ve mesaj başlığı panelden girilir. Doğrulama ve bilgilendirme mesajları ticari ileti değildir; kampanya SMS'i kapsam dışıdır (İYS gerektirir). |
| Fatura | `manuel` (PDF yükleme), `uyumsoft` (e-Arşiv) | Uyumsoft'tan e-Arşiv web servisi kullanıcısı ve test ortamı bilgileri gerekir. |
| Teslimat | `manuel_kargo`, `kendi_teslimat`, (ileride) firma veya aracı servis sürücüleri | Kargo anlaşması yapılınca sürücü eklenir. |
| E-posta | İşlem e-postası servisi (SMTP/API), yedek olarak Gmail | Gmail'in günlük gönderim sınırı mağaza trafiğine yetmez. |

## 10. iyzico başvurusu için hazırlanacaklar

Sanal POS başvurusunda istenen bilgiler siteye eklenir. Başvurudan önce iyzico'nun güncel kontrol listesiyle karşılaştırılır.

- SSL (https) ve alan adının şirket adına kayıtlı olması
- **Mesafeli Satış Sözleşmesi** ve **Ön Bilgilendirme Formu**: ödemeden önce onaylatılır, sürüm ve onay kaydı tutulur, sipariş e-postasında gönderilir
- İptal, iade ve cayma koşulları; teslimat koşulları
- Gizlilik ve KVKK aydınlatma metni; çerez politikası ve çerez onayı
- İletişim: ticari ünvan, açık adres, telefon, e-posta, vergi dairesi ve no, MERSİS no
- Hakkımızda
- Alt kısımda iyzico, Visa, Mastercard ve Troy logoları
- Fiyatlı ürünler; çalışan sepet ve sipariş akışı; "yapım aşamasında" sayfa olmaması

Metinlerin içeriğini müşterinin hukukçusu veya mali müşaviri onaylar. Sistem şablonları, yerleri, sürümlemeyi ve onay adımlarını sağlar.

## 11. Güvenlik

- **Fiyat ve tutar** sunucuda hesaplanır (§8.1). **Ödeme** sunucudan doğrulanır (§9).
- Müşteri yalnızca kendi sipariş, adres ve faturasını görür. IDOR taraması mağaza rotalarına genişletilir.
- Mağaza yanıtlarında hiçbir koşulda B2B verisi olmaz; mimari test (§6) ve rota testleri bunu denetler.
- Kayıt, giriş, şifre sıfırlama, sepet, ödeme ve SMS uçlarında hız sınırı ve robot doğrulaması vardır.
- Kart verisi sistemimize hiç gelmez (iyzico Ödeme Formu).
- Mevcut güvenlik başlıkları ve CSP mağazaya uygulanır; iyzico alan adları CSP'ye eklenir.
- Kişisel veri: müşteri hesabını silme talebi vardır. Yasal saklama süresi olan sipariş ve fatura kayıtları anonimleştirilerek saklanır.

## 12. Arama motoru ve performans

- Sayfalar sunucuda Blade ile üretilir. Okunaklı adresler kullanılır: `/kategori/kalemler`, `/urun/faber-castell-kursun-kalem-hb`.
- Ürün sayfalarında yapılandırılmış veri (Product, Offer, BreadcrumbList), Open Graph etiketleri ve kanonik adres bulunur.
- `sitemap.xml` otomatik üretilir; Google Search Console kaydı yapılır.
- Görseller, B2B'de kullanılan işlemden geçer (WEBP, beyaz kenar kırpma, netleştirme) ve farklı boyutlarda sunulur.
- Katalog sayfaları önbelleklenir. Performans testlerine mağaza senaryoları eklenir.
- Tasarım mobil önceliklidir.

## 13. Aşama planı

| Aşama | İçerik |
|---|---|
| **M0** | Bu doküman + `CLAUDE.md` güncellemesi → onay |
| **M1** | B2B'yi tek toptancıya kilitleme (§4); alan adına göre rotalar; `store_admin` girişi; süper admin ve yönetici yönetimi; 2FA; işlem geçmişi; platformda "Mağaza" bölümü; mimari sınır testi |
| **M2** | Katalog: kategori, marka, ürün, varyant, görsel, fiyat, KDV, SEO alanları, Excel içe aktarma |
| **M3** | Stok: giriş, sayım, kritik stok, hareket geçmişi, ayırma altyapısı |
| **M4** | Vitrin: ana sayfa, banner, kategori, marka, arama ve filtre, ürün sayfası, SEO, site haritası |
| **M5** | Müşteri: üyelik, misafir alışveriş, adres (il/ilçe), sepet, kupon altyapısı |
| **M6** | Ödeme ve sipariş: iyzico (sandbox), kapıda ödeme (Eskişehir + Netgsm/log SMS + kurallar + engel listesi), sözleşme onayları, sipariş e-postaları |
| **M7** | Teslimat ve fatura: manuel kargo, kendi teslimatım, iptal/iade, iyzico iadesi, manuel fatura, Uyumsoft sürücüsü (test bilgileri gelince) |
| **M8** | Kampanya ve kupon, raporlar, yönetici bildirimleri |
| **M9** | Yasal sayfalar, çerez onayı, iyzico başvuru kontrol listesi; sunucu, HTTPS, yedek ve geri yükleme provası (eski Aşama 10); alan adı → **yayın** |
| **M10** | Yayın sonrası: iyzico başvurusu → canlı kart ödemesi; Netgsm hesabı; kargo firması sürücüsü |

Her aşama B2B'deki düzende ilerler: testler, aşama raporu, push.

## 14. Dışarıdan beklenenler

| Konu | Kimden | Ne zaman gerekir |
|---|---|---|
| `yapbozkirtasiye.com` alan adı | Müşteri | M9 (yayın) |
| Netgsm hesabı (kullanıcı kodu, şifre, onaylı mesaj başlığı) | Müşteri | Kapıda ödemenin canlıda açılması için; geliştirmede gerekmez |
| Uyumsoft e-Arşiv API kullanıcısı ve test ortamı | Müşteri / mali müşavir | M7'de otomatik fatura için; o zamana kadar manuel fatura |
| Yasal metinlerin içeriği ve şirket bilgileri | Müşteri / hukukçu | M9 |
| İşlem e-postası servisi hesabı | Müşteri (öneri M9'da sunulur) | M9 |
| iyzico üye işyeri başvurusu | Müşteri | Yayından sonra |
| Kargo firması anlaşması | Müşteri | İsteğe bağlı, yayından sonra |

## 15. `CLAUDE.md` değişiklikleri (uygulandı)

"Değişmez kurallar" şöyle güncellendi:

- Sistem **tek toptancı** içindir (Yapboz). Çok işletmeli altyapı korunur ama ikinci işletme açılamaz.
- **Beş giriş alanı:** B2B'de `/platform`, `/toptanci`, `/bayi`; mağazada `yonetim` alt alan adı (`store_admin`) ve mağaza alan adı (`customer`). Ortak `users` tablosu yoktur.
- **Mağaza ayrı bir modüldür** (`app/Domain/Store`, `store_*` tabloları). B2B ile veri paylaşmaz; iki taraf birbirinin kodunu kullanamaz.
- Stok ve cari hareketleri yalnızca eklenir. **Rezervasyon ve kargo yalnızca mağazada vardır**; B2B'de yoktur.
- Her işletmede tek toptancı kullanıcısı ve her bayide tek kullanıcı vardır; rol sistemi yoktur. **İstisna:** mağaza yönetiminde süper admin ve sınırsız sayıda yönetici bulunur.
- Mağazada fiyat, tutar ve ödeme sonucu her zaman sunucuda hesaplanır ve doğrulanır.
