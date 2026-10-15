# Aşama 3 Raporu — Ürün ve Stok

> Tarih: 2026-10-16 · Durum: **Tamamlandı** · Testler: 81/81 geçti (2'si gerçek çok süreçli eşzamanlılık testi)

## Yapılanlar

### Veritabanı
`units`, `tax_rates`, `categories` (2 seviye), `brands`, `variant_groups`, `products`, `product_images`, `warehouses`, `warehouse_stock`, `stock_movements`, `stock_counts`, `stock_count_lines`, `import_jobs`, `import_errors`.

- Ürünün kategori, marka, birim, KDV ve varyant grubu **bileşik yabancı anahtarla** aynı işletmeye bağlı: kod hatası olsa bile başka işletmenin kategorisi ürüne yazılamaz.
- `UNIQUE (business_id, sku)` ve `UNIQUE (business_id, barcode)`: kod işletme içinde benzersiz, farklı işletmeler aynı kodu kullanabilir.
- `warehouse_stock`: `CHECK (quantity >= 0)`; `stock_movements`: `CHECK (quantity <> 0)`, `source_key UNIQUE`.
- Ürün fiyat ve miktar alanlarında `CHECK` kısıtları (negatif fiyat, sıfır sipariş adımı veritabanına giremez).
- `business_settings.default_warehouse_id` bileşik FK ile aynı işletmenin deposuna bağlı.
- Yeni işletme açıldığında otomatik: **Merkez Depo** (varsayılan sevk deposu), birimler (Adet, Paket, Koli, Düzine, Kg, Metre), KDV %0/%1/%10/%20.

### Stok servisi (`app/Domain/Inventory/StockService.php`) — stoğu değiştiren tek yer
| Kural | Uygulama |
|---|---|
| S1 anlık stok = hareket toplamı | Her değişiklik aynı transaction'da hareket + stok güncellemesi; testlerde tüm veritabanı üzerinde doğrulanıyor |
| S2 negatif stok yok | Satır `FOR UPDATE` kilidi + `CHECK` kısıtı |
| S3 hareketler silinmez | Model `update`/`delete` engelli; düzeltme ters hareketle |
| S4 transfer atomik | Çıkış ve giriş tek transaction, ortak `transfer_uuid`; kaynak yetersizse hiçbiri yazılmaz |
| S5 sayım farkı uygulama anında | Taslak sırasında olan satışlar doğru farkı bozmaz |
| S6 sabit kilit sırası | Çok kalemde `product_id` artan sırada kilitleme |
| Tekrar koruması | `source_key` UNIQUE |
| Birim kuralı | Adet/Koli gibi birimlerde küsurat reddedilir; Kg/Metre kabul eder |
| Sipariş onayına hazır | `applyMany()`: önce tüm kalemleri kilitler ve kontrol eder, biri yetersizse hiçbir hareket yazılmaz |

Hareket türleri: ilk stok girişi, stok girişi, stok çıkışı, iade girişi, transfer çıkış/giriş, sayım farkı, manuel düzeltme (açıklama zorunlu), satış ve satış iptali (Aşama 5'te sipariş akışı kullanacak). **Rezervasyon, fire, hasarlı ürün türü yok.**

### Ürün yönetimi
- Liste: arama (ad, kod, barkod), kategori (alt kategorileri kapsar), marka, durum (aktif/pasif/arşiv), stok (kritik/stokta yok) filtreleri; sıralama; sayfalama; görsel önizleme; toplu aktif/pasif.
- Form: bölümlere ayrılmış (temel bilgiler, fiyat ve KDV, birim ve sipariş kuralları, varyant, satış durumu). Kurallar: minimum sipariş miktarı artış adımının katı olmalı; tam sayılı birimde küsurat yok.
- Detay: depo bazında stok, son hareketler, ürün geçmişi (eski → yeni fiyat dahil), varyant kardeşleri, kopyalama (pasif kopya, barkod ve görsel kopyalanmaz), arşivleme.
- Toplu fiyat güncelleme: filtreye uyan ürünlerde liste/bayi/alış fiyatına yüzde veya tutar; sonuç 0'ın altına inmez; onay kutusu zorunlu; her değişiklik ürün geçmişine yazılır.
- Görseller: en fazla 8 görsel, 5 MB; içerik dosyanın kendisinden doğrulanır, GD ile **WEBP olarak yeniden kodlanır** (meta veri ve gömülü içerik atılır), web kökü dışında saklanır, yalnızca sahibi işletmeye sunulur. Laravel'in yerel diski doğrudan sunma özelliği kapatıldı.
- Kategori ve markalar: tek sayfada sekmeli; ürünü olan kategori/marka silinemez, pasif yapılır.
- KDV ve birimler: aktif üründe kullanılan KDV oranı pasif yapılamaz; oran değişikliği yeni oran eklenerek yapılır (geçmiş korunur).

### Depo, stok ve sayım ekranları
Stok durumu (depo seçimli, kritik/stokta yok filtreleri), stok hareketi formu (ürün arama kutusuyla), depolar arası transfer, değiştirilemez hareket geçmişi (ürün/depo/tür/tarih filtresi), depo yönetimi (varsayılan sevk deposu seçimi; varsayılan depo pasif yapılamaz), stok sayımı (kod veya barkodla satır ekleme, barkod okuyucuyla kullanılabilir; uygula/iptal).

### Excel/CSV içe aktarma
- Şablon indirilebilir (.xlsx). `.xlsx` ve `.csv` (`;` veya `,` ayraç otomatik), en fazla 10 MB / 5.000 satır.
- Ürün kodu varsa **günceller** (yalnızca dolu sütunlar), yoksa **oluşturur**. Ürün formuyla aynı doğrulama.
- Her satır kendi transaction'ında; hatalı satır atlanır, hata satır numarası ve sütunuyla raporlanır. Dosya içi tekrar eden kod yakalanır.
- `Ana > Alt` kategori yazımı; eksik kategori/markalar isteğe bağlı otomatik oluşturulur.
- Türkçe sayı biçimi (`1.234,56`) desteklenir; fazla ondalık **yuvarlanmaz, reddedilir**.
- Başlangıç stoğu yalnızca yeni ürünlerde, varsayılan depoya ilk stok girişi olarak yazılır.
- Platform yöneticisi modülü kapatırsa ekran 403 döner.

### Dashboard (gerçek veriler)
Aktif ürün, sevk deposunda stokta olmayan ürün, **stok değeri (alış fiyatı × miktar — etiketli; alış fiyatı olmayan ürün sayısı ayrıca belirtilir)**, aktif bayi, kritik stok listesi, kurulum adımları.

### Diğer
- Türkçe doğrulama mesajları (`lang/tr/validation.php`) — önceden eksikti.
- Rota model bağlama işletme bağlamından **sonra** çalışacak şekilde sıralandı: başka işletmenin kaydına URL ile erişim 404.
- Geliştirme verisi: Akgün Kırtasiye'ye 10 örnek ürün, kategori, marka, varyant grubu ve açılış stoğu.

## Testler (bu aşamada eklenen 33 test)

- **Stok servisi (12):** hareket toplamı değişmezi; yetersiz stokta hiçbir şey yazılmaması; veritabanı `CHECK`'inin kod atlansa bile negatif stoğu reddetmesi; küsurat kuralı; manuel düzeltmede açıklama zorunluluğu; ilk stok girişinin tekliği; aynı `source_key`'in iki kez uygulanamaması; hareketlerin değiştirilemezliği; transfer atomikliği; pasif depo; çok kalemde biri yetersizse hiçbirinin uygulanmaması; sayım farkının uygulama anında hesaplanması ve sayımın iki kez uygulanamaması.
- **Eşzamanlılık (2, gerçek ayrı PHP süreçleri):** stok 10 iken iki paralel 7'lik satış → yalnızca biri başarılı, stok 3, tek hareket. Aynı işlem paralel iki kez gönderilirse → yalnızca bir kez uygulanır.
- **Ürün yönetimi (12):** oluşturma; SKU/barkod işletme içinde benzersiz, işletmeler arası serbest; başka işletmenin kategorisi reddedilir; sipariş kuralı, negatif ve geçersiz fiyat doğrulaması; fiyat değişikliğinin geçmişe yazılması; toplu fiyatın 0'ın altına inmemesi ve onay zorunluluğu; toplu işlemlerin başka işletmenin ürününe dokunamaması; arşivleme; kopyalama; başka işletmenin ürününe tüm uçlarda 404 (ürün arama dahil); görselin yeniden kodlanması ve yalnızca sahibine sunulması; görsel kılığındaki PHP dosyasının reddi.
- **İçe aktarma (6):** ekle + güncelle + 4 farklı satır hatası tek dosyada; otomatik oluşturma kapalıyken eksik kategori; ürün kodu sütunu olmayan dosya; başka işletmenin aynı kodlu ürününe dokunmama; modül kapatılınca 403; şablon indirme.
- **Sayfa render (1):** 14 katalog/stok sayfasının boş ve dolu veriyle açılması.

## Bu aşamada alınan kararlar

| Konu | Karar |
|---|---|
| Ondalık girişi | `1.234,56` ve `1234.56` kabul edilir. Yalnızca nokta varsa ondalık ayırıcı sayılır (`1.250` = 1,25). İçe aktarmada fazla ondalık reddedilir. |
| Varyant değerleri | Grubun özellik adıyla anahtarlanır: `{"Renk": "Mavi"}`. Grup en fazla 3 özellik. |
| Ürün silme | Yok. Arşivleme var; arşivdeki ürün düzenlenemez, bayiye görünmez, geçmişi korunur. |
| Görsel biçimi | Tüm görseller en uzun kenar 1600 px WEBP'ye dönüştürülür. |
| İçe aktarma | Senkron (5.000 satır sınırı). KVM 2 üzerinde süre ölçülüp gerekirse kuyruğa alınacak. |

## Bilinen eksikler

- **Tarayıcıda görsel kontrol yapılmadı** (uygulama içi tarayıcı yerel sunucuya erişemiyor). Sayfalar otomatik testle doğrulandı; `php artisan serve` ile `yonetici@akgun.test` hesabıyla elle denenebilir.
- İşletme logosu yükleme: görsel altyapısı hazır, ayarlar ekranına Aşama 4'te (başvuru sayfasında da kullanılacağı için) eklenecek.
- Lisans ürün sınırının (`max_products`) uygulanması Aşama 4 ile birlikte.
- Excel'e **dışa aktarma** (ürün/stok listesi) raporlama altyapısıyla Aşama 8'de; formül enjeksiyonu önlemi orada uygulanacak.

## Aşama 4 gereksinimleri (Bayilik)

- Migration: `dealer_applications`, `dealer_addresses`, `price_lists`, `price_list_items`, `dealer_product_prices`, `discount_rules`, `current_accounts` (ilişki açılışında oluşturulacağı için), `attachments`.
- Herkese açık başvuru sayfası `/basvuru/{slug}` (işletme logosu, aydınlatma metni onayı, honeypot, rate limit, belge eki).
- Başvuru yönetimi (6 durum), onay → bayi hesabı/ilişki/cari/sepet + aktivasyon; mevcut bayi e-postası ise ilişki ekleme (K11).
- Bayi listesi, bayi grubu, bayi askıya alma, aktivasyon bağlantısını yeniden gönderme.
- Fiyat listeleri, bayiye özel fiyat, ürün/kategori indirimi ve `PriceResolver` (K6 önceliği) + testleri.
- Bayi sevk adresleri.
