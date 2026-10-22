# Yayın öncesi eksikler raporu

Tüm sistem (B2B ve mağaza) dokümanlarla karşılaştırılarak tarandı ve yayın öncesi 8 eksik çıkarıldı. Desiye göre kargo ücreti (5. madde) bilerek ertelendi (docs/YAPILACAKLAR.md). Diğer yedi madde tamamlandı. Sunucu kurulumu ve yayın bu çalışmanın dışında.

## 1. Türkçe hata sayfaları ve referans kodu

- `resources/views/errors/` altında 403, 404, 419, 429, 500 ve 503 sayfaları var. Diğer 4xx/5xx kodları ortak sayfaya düşer. Laravel'in İngilizce sayfaları artık görünmez.
- Sayfa, hangi alanda açıldığına göre doğru yere döner:
  - Mağaza alan adında "Mağazaya dön".
  - Mağaza yönetiminde ve `/platform`, `/toptanci`, `/bayi` altında ilgili panelin ana sayfası.
- Hata sayfası veritabanına ve oturuma dokunmaz. Derlenmiş stil yoksa da açılır. Koyu tema tercihine (`tema` çerezi) uyar.
- 500 sayfasında ayrıntı gösterilmez, yalnızca bir **referans kodu** gösterilir (ör. `H12`). Kod, Platform → Hata Kayıtları'ndaki kaydın numarasıdır. Müşteri bu kodu iletirse arama kutusuna `H12` yazılarak kayıt doğrudan bulunur.
- 403 sayfasında uygulamanın verdiği Türkçe gerekçe gösterilir, varsayılan İngilizce metin gösterilmez. 419 (oturum süresi doldu) ve 429 (çok fazla istek) hata kaydına yazılmaz.
- Test: `tests/Feature/ErrorPagesTest.php`.

## 2. Türkçe doğrulama mesajları

- `lang/tr/validation.php` dosyası, Laravel'in bütün doğrulama kurallarını kapsayacak şekilde tamamlandı. Önceden eksik kurallarda İngilizce mesaj çıkıyordu.
- Mevcut alan adları (`attributes`) korundu.
- Test: `tests/Feature/TranslationCoverageTest.php`. İngilizce ve Türkçe dosyaların anahtarlarını karşılaştırır. Laravel güncellemesiyle yeni bir kural gelirse test bunu söyler.

## 3. Sözleşmeler sipariş e-postasıyla gönderiliyor

- Sipariş alındı e-postasının sonuna şu iki belge eklendi:
  - Ön Bilgilendirme Formu.
  - Mesafeli Satış Sözleşmesi.
- Metinler, müşterinin onayladığı sürüme göre siparişin bilgileriyle doldurulur. Yasal olarak sözleşmenin kalıcı veri saklayıcısıyla iletilmesi gerekir.
- Test: `tests/Feature/Store/StoreOrderEmailTest.php`.

## 4. iyzico bildirim adresi (webhook)

- Yeni rota: `POST /odeme/iyzico/bildirim` (mağaza alan adı). CSRF dışında tutulur, istek sınırı dakikada 120'dir.
- Gelen token için normal dönüş akışı aynen çalışır: ödeme iyzico'dan **sunucu tarafında sorgulanır**, tutar ve sepet eşleşmesi kontrol edilir, aynı ödeme iki kez işlenmez.
- Müşteri ödemeden sonra sayfayı kapatsa da sipariş hemen sonuçlanır ve stok ayırması satışa dönüşür.
- Yanıt her zaman `{"status":"ok"}` olur, böylece iyzico'ya iç bilgi sızdırılmaz.
- Bildirim adresi Ödeme ve Teslimat ekranında gösterilir. iyzico panelinde Ayarlar → Bildirim Ayarları bölümüne girilmesi gerekir.

## 6. Kapıda ödeme engeli adrese de uygulanıyor

- Teslim alınmayan siparişte engel artık telefonla birlikte **teslimat adresine** de yazılır. Başka bir numarayla aynı adrese kapıda ödemeli sipariş verilemez, kartla ödeme açık kalır.
- Adres karşılaştırması ilçe, mahalle ve açık adresin sadeleştirilmiş özetiyle yapılır (`CodPolicy::addressKey`):
  - Türkçe karakterler ve büyük/küçük harf farkı yok sayılır.
  - Noktalama yok sayılır.
  - "Mah./Mahallesi", "Cad.", "Sok./Sk.", "Blv.", "No", "D./Daire", "Kat", "Apt." gibi yazım farkları yok sayılır.
- Engel listesinde adres de görünür. Sipariş detayında, adres engelliyse uyarı çıkar.
- Yeni migration: `2026_11_09_000100_add_address_to_store_cod_blocklist`.
- Test: `StoreOrderAdminTest::test_not_delivered_blocks_the_address_for_other_phones_too`.

## 7. `.env.example` ve günlük dosyaları

- `LOG_STACK=daily` ve `LOG_DAILY_DAYS=14` ayarlandı: günlük dosyası her gün ayrı tutulur, sınırsız büyümez.
- Eksik ayarlar açıklamalarıyla eklendi:
  - `BRAND_NAME`, `BRAND_TAGLINE`
  - `SYSTEM_MAIL_*`
  - `STORE_NAME`, `STORE_BOT_PROTECTION`
  - `BACKUP_MYSQLDUMP`, `BACKUP_KEEP`
  - üretim notları (`APP_ENV`, `APP_DEBUG`, `SESSION_SECURE_COOKIE`)

## 8. Mağaza performans ölçümü

`tests/Performance/StorePerformanceTest.php` (`--group=performance`) şu veriyle ölçüldü:

- 10.000 ürün (2.000'i üç seçenekli)
- 50 kategori
- 5.000 müşteri
- 30.000 sipariş

Geliştirme bilgisayarındaki sonuçlar:

| Alan | Sayfa | Süre (ms) | Sorgu |
|---|---|---:|---:|
| Vitrin | Ana sayfa | 87 | 11 |
| Vitrin | Tüm ürünler | 81 | 8 |
| Vitrin | Tüm ürünler (fiyat sırası, 20. sayfa) | 164 | 8 |
| Vitrin | Kategori | 64 | 12 |
| Vitrin | Arama | 184 | 8 |
| Vitrin | Ürün detayı | 32 | 11 |
| Vitrin | Site haritası | 488 | 4 |
| Müşteri | Hesabım (sipariş geçmişi) | 22 | 6 |
| Yönetim | Panel | 57 | 25 |
| Yönetim | Siparişler | 48 | 6 |
| Yönetim | Siparişler (arama) | 60 | 6 |
| Yönetim | Sipariş detayı | 31 | 18 |
| Yönetim | Ürünler | 48 | 11 |
| Yönetim | Ürünler (arama) | 100 | 11 |
| Yönetim | Stok | 136 | 11 |
| Yönetim | Raporlar (1 yıl) | 391 | 12 |

Bütün sayfalar sınırın (1,5 sn ve 40 sorgu; raporlarda 3 sn) çok altında kaldı. Ölçüm sunucuda yeniden yapılacak (Aşama 10).

## Doküman düzeltmeleri

- **docs/13:**
  - Ödeme sırasındaki stok ayırma süresi 15 değil, 20 dakikadır.
  - Veri modeli tablosu gerçek tablolarla değiştirildi:
    - Teslimat için `store_carriers` ve sipariş alanları.
    - Kampanya ve kupon için `store_discounts`.
  - Webhook ve adres engeli işlendi.
- **docs/01:** "Sistem Ayarları" ekranı yapılmadı; ayarlar `.env` ve `config/b2b.php` üzerindendir. Platform yöneticisi panelden değil, `php artisan platform:create-admin` komutuyla oluşturulur.
- **docs/GELISTIRME.md:** şu konular eklendi:
  - yedekleme komutu ve `BACKUP_MYSQLDUMP`
  - `npm run denetim:alpine`
  - günlük dosyaları
  - mağaza performans ölçümü
