# Aşama 2 Raporu — Temel Altyapı

> Tarih: 2026-10-15 · Durum: **Tamamlandı** · Testler: 48/48 geçti

## Yapılanlar

| Konu | Sonuç |
|---|---|
| Ortam | Yerel MySQL 8.4.11 kuruldu (`C:\tools\mysql84`), `b2b` ve `b2b_test` veritabanları, ayrı uygulama kullanıcısı. Laravel 13.36, PHP 8.5. |
| Bağımlılıklar | PHPMailer 7, OpenSpout 5, Dompdf 3, brick/math, google2fa + bacon-qr-code; Alpine.js, Chart.js, Inter (yerel font, CDN yok). |
| Veritabanı | `platform_admins`, `businesses`, `business_settings`, `licenses`, `business_modules`, `business_users` (işletme başına UNIQUE), `dealers`, `dealer_users` (bayi başına UNIQUE), `dealer_groups`, `dealer_relationships` (bileşik FK ile tenant koruması), `auth_tokens`, `support_access_grants`, `audit_logs`, `system_settings`, `error_events`. |
| Üç giriş alanı | Ayrı tablolar, ayrı guard'lar, URL önekine bağlı ayrı oturum çerezleri (`b2b_platform` → `/platform`, `b2b_business` → `/toptanci`, `b2b_dealer` → `/bayi`). |
| Kimlik doğrulama | Giriş, çıkış, giriş denemesi sınırı (e-posta+IP 5/15 dk, IP 30/15 dk), açık yönlendirme engeli, tek kullanımlık aktivasyon ve parola sıfırlama bağlantıları (yalnızca SHA-256 özeti saklanır), parola değişikliğinde diğer oturumların kapanması. |
| Platform 2FA | Zorunlu TOTP; kurulum + her girişte kod; aynı kodun tekrar kullanılması engelli. |
| Tenant izolasyonu | `TenantContext` + `BelongsToBusiness` global scope. Bağlam yoksa sorgu hata verir; başka işletmeye kayıt yazma ve kaydın işletmesini değiştirme engelli. Bayi tarafında seçili ilişki her istekte yeniden doğrulanır. |
| Askıya alma | İşletme askıya alınınca kullanıcısının ve bayilerinin erişimi bir sonraki istekte kesilir. |
| Platform paneli | Genel durum, işletme listesi/oluşturma/düzenleme, durum değiştirme (gerekçe zorunlu), lisans dönemleri, modül aç/kapa, aktivasyon bağlantısını yeniden gönderme, denetim kayıtları. Yalnızca kullanım **adetleri** görünür, ticari tutar görünmez. |
| Toptancı paneli | Dashboard (yalnızca gerçek veri: aktif bayi sayısı + kurulum adımları), işletme ayarları (iletişim, vurgu rengi, stok gösterimi, cari borç zamanı, vade, fiyat gösterimi), profil ve güvenlik. |
| Bayi paneli | Toptancı seçimi (çoklu toptancı), ana sayfa (boş durum + toptancı iletişim bilgileri), profil ve güvenlik. |
| Tasarım sistemi | Tailwind token'ları, ortak Blade bileşenleri: `button`, `field`, `card`, `badge`, `kpi`, `page-header`, `empty-state`, `flash`, `icon`; `layouts.auth`, `layouts.panel` (platform: koyu grafit sol menü; toptancı: beyaz sol menü; bayi: lacivert üst menü). Mobilde açılır menü. |
| Güvenlik başlıkları | `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HTTPS'te HSTS. Oturum verisi şifreli. |
| Denetim kaydı | Giriş/başarısız giriş, 2FA, parola değişikliği/sıfırlama, aktivasyon, işletme oluşturma/güncelleme/durum, lisans, modül. Hassas alanlar maskeli. |

## Test sonuçları (48 test, 200+ doğrulama)

- **Alan ayrımı:** her alanın kendi giriş sayfası ve çerezi; toptancı bilgileriyle bayi/platform girişi reddedilir; oturum açmış toptancı URL değiştirerek bayi/platform paneline giremez (ve tersi); alan dışı `intended` adresine yönlendirilmez.
- **Hesap durumu:** askıdaki işletme girişi; askıya alınınca oturumun düşmesi; aktivasyon bekleyen hesap; bayi ilişkisi veya toptancısı askıya alınınca erişim kaybı; devre dışı bayi.
- **Tenant izolasyonu:** bağlamsız sorgu hatası; yalnızca aktif işletmenin satırları; başka işletmenin ID'si ile `find` boş döner; başka işletmeye kayıt oluşturma ve işletme değiştirme engeli; veritabanı bileşik FK'nin başka işletmenin bayi grubunu reddetmesi; URL parametresiyle bağlam değiştirilememesi.
- **Bayi toptancı seçimi:** tek toptancıda doğrudan giriş; çoklu toptancıda seçim; başka bayinin ilişkisini seçememe (404); oturuma sahte ilişki ID'si konursa yok sayılması.
- **Platform:** 2FA kurulmadan panel kapalı; 2FA kurulum ve sonraki girişte kod; işletme oluşturma (tek kullanıcı, aktivasyon bekliyor, parola yok, ayarlar, lisans, denetim kaydı); aynı işletmeye ikinci kullanıcı veritabanında reddedilir; askıya alma gerekçesi zorunlu.
- **Aktivasyon ve parola:** tek kullanımlık bağlantı; yalnızca hash saklanması; bir alanın token'ı diğer alanda çalışmaz; süresi dolmuş bağlantı; zayıf parola; "parolamı unuttum" yanıtının hesap varlığını sızdırmaması; mevcut parola doğrulaması.
- **Sayfa render:** üç panelin tüm sayfaları açılıyor ve her panel yalnızca kendi menüsünü gösteriyor; vurgu rengine stil enjeksiyonu reddediliyor.

## Bu aşamada alınan uygulama kararları

| Konu | Karar |
|---|---|
| Hesap güvenliği e-postaları | Aktivasyon (toptancı yetkilisi) ve tüm alanların parola sıfırlama e-postaları **platformun sistem göndericisi** (`SYSTEM_MAIL_*`) ile gider. Bunlar toptancı adına ticari yazışma değil, hesap güvenliği e-postasıdır (K9 ile çelişmez). Bayilere giden ticari e-postalar Aşama 7'de toptancının kendi Gmail ayarıyla gidecek. |
| E-posta gönderilemezse aktivasyon bağlantısı | Platform yöneticisine **yalnızca e-posta gönderilemediğinde, bir kez** gösterilir ve `platform.activation_link_shown` olarak denetim kaydına yazılır. |
| Platform yöneticisi oluşturma | Yalnızca sunucuda `php artisan platform:create-admin` ile. |
| Modeller | `app/Models` (Laravel standardı); iş kuralları `app/Domain`. |

## Bilinen eksikler / sonraki aşamalara bırakılanlar

- **Sistem e-postası yapılandırılmadı.** Yerelde içerik log'a yazılıyor. Üretimde `SYSTEM_MAIL_*` değerleri girilmeli (Aşama 10).
- **Tarayıcıda görsel kontrol yapılmadı.** Uygulama içi tarayıcı yerel sunucuya erişemiyor; sayfalar otomatik testle doğrulandı. `php artisan serve` ile elle kontrol edilebilir.
- **Destek erişimi (support access) arayüzü** tablosu hazır, ekranı Aşama 9'da güvenlik çalışmasıyla eklenecek.
- **Platform: e-posta altyapısı, hata kayıtları, yedekler ekranları** ilgili altyapı (Aşama 7 ve 10) ile eklenecek; menüde şimdilik yok.
- İşletme logosu yükleme, güvenli dosya yükleme altyapısıyla birlikte Aşama 3'te.
- Lisans sınırlarının (max bayi/ürün) uygulanması, ilgili kayıtların oluşturulduğu Aşama 3–4'te.

## Aşama 3 gereksinimleri (Ürün ve stok)

- Migration: `categories`, `brands`, `units`, `tax_rates`, `variant_groups`, `products`, `product_images`, `warehouses`, `warehouse_stock`, `stock_movements`, `stock_counts`, `stock_count_lines`, `import_jobs`, `import_errors`; `business_settings.default_warehouse_id` FK.
- Güvenli dosya yükleme servisi (görsel yeniden kodlama, private disk, yetkili indirme) + işletme logosu.
- Stok Action'ları: giriş, artırma, azaltma, transfer, sayım, manuel düzeltme — `FOR UPDATE`, `source_key` UNIQUE, `CHECK quantity >= 0`.
- Excel/CSV içe aktarma (OpenSpout), satır bazlı hata raporu, formül enjeksiyonu önlemi.
- Testler: stok = hareket toplamı değişmezi, negatif stok engeli, transfer atomikliği, sayım farkının uygulama anında hesaplanması, SKU/barkod benzersizliği, başka işletmenin ürününe erişim (404).
