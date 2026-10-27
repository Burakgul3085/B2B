# Aşama 1 — Analiz ve Mimari

> Kurumsal B2B Toptan Ticaret ve Bayi Yönetim Platformu
> Durum: **Onaylandı** (2026-10-15) — K1–K14 önerilen şekliyle kabul edildi, K12 tek kullanıcı olarak netleşti.

Bu doküman kod yazmadan önce alınan mimari kararları içerir. Veritabanı tasarımı [02-veritabani-er.md](02-veritabani-er.md), stok-sipariş-cari kuralları [03-is-kurallari.md](03-is-kurallari.md) içindedir.

---

## 1. Mevcut ortamın incelenmesi

| Konu | Bulgu | Sonuç |
|---|---|---|
| Proje klasörü (`Desktop/B2B`) | Boş. Mevcut kod, bağımlılık veya yapılandırma yok. | Sıfırdan kurulum; bozulacak çalışan altyapı yok. |
| Git deposu | Klasörün kendi deposu **yok**. Üstteki depo kullanıcı ana dizini (`C:\Users\90542`) — hiç commit yok, ~200 alakasız dosya stage'de. | Bu projeye **ayrı bir git deposu** açılmalı (`Desktop/B2B` içinde `git init`). Ana dizindeki depoya commit atılmamalı. |
| PHP | 8.5.1 CLI, Composer 2.9.3. Gerekli eklentiler mevcut: `pdo_mysql`, `bcmath`, `intl`, `mbstring`, `openssl`, `gd`, `fileinfo`, `zip`. | Laravel geliştirme için yeterli. |
| MySQL | Yerel MySQL/MariaDB **yok** (XAMPP klasöründe `mysql/bin` boş). | Yerel MySQL 8 kurulmalı. Eşzamanlılık testleri (`SELECT … FOR UPDATE`) SQLite ile yapılamaz. |
| Node.js | 22.14 / npm 10.9 | CSS/JS derlemesi yerelde yapılıp derlenmiş dosyalar sunucuya gönderilir; sunucuda Node gerekmez. |
| Docker | Yok | Gerekli değil. |
| Sunucu (Hostinger KVM 2) | **Henüz erişilmedi.** Hostinger'in yayımladığı nominal değer: 2 vCPU, 8 GB RAM, 100 GB NVMe. | Gerçek PHP/MySQL sürümü, RAM ve disk Aşama 10'dan önce SSH ile ölçülecek (`nproc`, `free -m`, `df -h`, `mysql --version`). Kapasite garantisi verilmez. |

## 2. Referans analizi (keep2b.com)

Erişilen sayfalar: ana tanıtım sayfası ve `sunum.keep2b.com` bayi demo paneli (ana sayfa, ürün listesi).

**Gözlemler**

- Keep2B bir ERP'nin (Opak) bayi kanalıdır; stok, cari ve fatura ERP'den okunur. Bizim ürünümüz ERP'siz, **kendi kaydını tutan** bir sistemdir — bu temel fark tasarımın her yerini etkiler (stok ve cari bizde asıl kayıttır).
- Bayi paneli üst menülü, arama odaklı: büyük arama çubuğu (ad / stok kodu / barkod), sepet ikonu, sağ üstte **cari bakiye** gösterimi.
- Katalog: sol filtre (kategori + marka, sayaçlı), "Sadece stoktakiler" kutusu, sıralama, grid/liste görünümü. Ürün kartında marka, ad, stok kodu, fiyat (+KDV %20 etiketi), birim seçici (Adet/Koli), miktar adımı (−/+), "Sepete Ekle".
- Demo katalog ~76.000 ürün içeriyor → katalog arama ve filtreleme baştan indeksli tasarlanmalı.
- Tanıtılan ama **bizim kapsamımızda olmayan** özellikler: sanal POS ile online ödeme, ödeme şekline göre fiyat (peşin/vadeli/taksit), ERP entegrasyonu, bayi alt kullanıcıları, kampanya motoru. Bunları kopyalamıyoruz.
- Alınacak iyi fikirler: sipariş anında tutarın dondurulması, "stok gelince haber ver", ürün sayfasında "bu üründen geçmiş alımlarınız", ekstreyi Excel'e indirme, koli/paket katı.

**Farklılaşma:** Daha sade bayi ana sayfası (banner/kampanya karuseli yerine "son siparişler + cari durum + hızlı tekrar sipariş"), daha net sipariş durumu takibi, toptancı tarafında tek ekrandan sipariş onayı ve stok yetersizliği uyarısı.

## 3. Teknoloji kararı

**Karar: Laravel (güncel kararlı sürüm) + Blade + Alpine.js + Tailwind CSS + MySQL 8.**

Gerekçe — sade PHP yerine framework seçmemizin nedeni güvenlik ve tutarlılık:

| İhtiyaç | Laravel'de hazır karşılığı |
|---|---|
| Üç ayrı oturum alanı | Çoklu `guard` (platform / business / dealer) |
| CSRF, parola hashleme (bcrypt/argon2), oturum yenileme | Yerleşik |
| Parametreli sorgular | Eloquent / Query Builder |
| Migration, seed | Yerleşik |
| Yetkilendirme | Policy + Gate |
| Kuyruk (e-posta, büyük dışa aktarma) | `database` sürücüsü — Redis gerektirmez |
| Zamanlayıcı (vade hatırlatma, yedek) | Scheduler + tek cron satırı |
| Test | PHPUnit/Pest, HTTP testleri |

| Katman | Seçim | Not |
|---|---|---|
| Arayüz | Blade + Alpine.js | SPA yok. Sunucu tarafı render; filtreler URL'de tutulur (paylaşılabilir rapor linkleri). Livewire/React eklenmiyor — gereksiz ağırlık. |
| CSS | Tailwind (yerelde derlenir) | Ortak bileşen kütüphanesi Blade component olarak. |
| Grafik | Chart.js | Hafif; çizgi, çubuk, yığılmış, halka, alan grafiklerinin hepsini karşılıyor. |
| Excel | OpenSpout | Akış (streaming) yazar — büyük raporda bellek sabit kalır. |
| PDF | Dompdf | Satır sınırı ile (ör. 3.000). Üstü kuyruğa alınır veya Excel önerilir. |
| E-posta | **PHPMailer** (gereksinim gereği) | Laravel'in kendi mailer'ı yerine işletme bazlı `MailService` üzerinden. |
| Para hesabı | `DECIMAL` + `bcmath` (brick/math) | Float kullanılmaz. |
| Kuyruk/önbellek | `database` | Ölçüm sonrası gerekirse Redis eklenir. |
| Sunucu | Nginx + PHP-FPM + MySQL 8 + Supervisor (kuyruk) + cron + Certbot | |

## 4. Kod yapısı (modüler)

```
app/
  Models/                 ← Eloquent modelleri (Laravel standardı)
  Domain/                 ← iş kuralları; HTTP'den bağımsız
    Tenancy/              (TenantContext, BelongsToBusiness trait, global scope)
    Platform/             (işletme, lisans, modül, destek erişimi)
    Catalog/              (ürün, kategori, marka, görsel, varyant, içe aktarma)
    Inventory/            (depo, stok, hareket, sayım, transfer)
    Dealers/              (başvuru, bayi, ilişki, grup, adres)
    Pricing/              (PriceResolver, fiyat listesi, indirim kuralı)
    Ordering/             (sepet, sipariş, durum makinesi, ürün talebi)
    Ledger/               (cari hesap, hareket, tahsilat, yaşlandırma)
    Messaging/            (konuşma, mesaj, ek, bildirim, duyuru)
    Mail/                 (PHPMailer servisi, şablon, log, yeniden deneme)
    Reporting/            (Report tanımı, filtre, ekran + dışa aktarma)
    Audit/
  Http/Controllers/{Platform,Business,Dealer,Public}/
  Http/Middleware/        (AreaSession, ResolveTenant, EnsureDealerActive, …)
resources/views/{platform,business,dealer,public,emails,components}/
database/migrations/  database/seeders/  tests/{Feature,Unit,Concurrency}/
```

Her iş işlemi tek bir **Action** sınıfıdır (`ApproveOrder`, `CancelOrder`, `RecordCollection`, `AdjustStock` …). Controller yalnızca doğrular ve Action'ı çağırır; transaction, kilit ve denetim kaydı Action içinde. Böylece aynı kural ekrandan, içe aktarmadan veya testten çağrıldığında aynı çalışır.

## 5. Üç giriş alanı

| | Platform yönetimi | Toptancı paneli | Bayi paneli |
|---|---|---|---|
| URL | `/platform/*` | `/toptanci/*` | `/bayi/*` |
| Giriş | `/platform/login` | `/toptanci/login` | `/bayi/login` |
| Kullanıcı tablosu | `platform_admins` | `business_users` | `dealer_users` |
| Guard | `platform` | `business` | `dealer` |
| Oturum çerezi | `b2b_platform` (path `/platform`) | `b2b_business` (path `/toptanci`) | `b2b_dealer` (path `/bayi`) |
| 2FA | **Zorunlu** (TOTP) | Her girişte e-postaya 4 haneli kod | Her girişte e-postaya 4 haneli kod |
| Görsel ayrım | Koyu grafit tema, "PLATFORM" etiketi | Lacivert, işletme logosu | Lacivert, **bağlı olduğu toptancının** logosu/rengi |
| Menü | Sol menü (işletmeler, lisans, sistem) | Sol menü, gruplu | Üst menü, katalog odaklı |

**Ayrım nasıl garanti edilir**

1. Üç kullanıcı ayrı tablolarda — bir toptancı hesabı teknik olarak bayi guard'ında var olamaz; çapraz giriş imkânsızdır, rol kontrolüne bağlı değildir.
2. Her alanın oturum çerezi kendi yoluna (`path`) bağlıdır. Tarayıcı `/bayi` isteğine toptancı çerezini hiç göndermez. Bir alandan çıkış diğerini etkilemez.
3. Her rota grubu yalnızca kendi guard'ının `auth` middleware'ini kullanır. URL değiştirmek, diğer alanın oturumunu üretmez → giriş sayfasına yönlendirilir.
4. Giriş sonrası yönlendirme yalnızca o alanın kendi iç yollarına yapılır (`intended` URL aynı önek ile başlamıyorsa yok sayılır — açık yönlendirme engeli).
5. Giriş denemesi sınırı: e-posta+IP başına 5 deneme / 15 dk, IP başına 30 / 15 dk. Başarısız ve başarılı girişler `audit_logs`'a yazılır.
6. Askıya alınmış işletmenin kullanıcıları ve bayileri giriş yapamaz (her istekte kontrol, sadece girişte değil).

**Herkese açık sayfalar (oturumsuz)**

- `/basvuru/{isletme-slug}` — toptancıya özel bayilik başvuru formu (işletme logosu ile).
- `/bayi/aktivasyon/{token}`, `/toptanci/aktivasyon/{token}` — parola belirleme.
- Her alan için ayrı parola sıfırlama.
- `/` — yalnızca kısa kurumsal karşılama ve iki giriş bağlantısı (toptancı / bayi). Platform girişi buraya konmaz. Ürün vitrini yoktur.

**Bayi birden fazla toptancıyla çalışırsa:** Giriş sonrası tek aktif ilişki varsa doğrudan o toptancının paneline girer; birden fazlaysa "Çalıştığınız toptancıyı seçin" ekranı gelir. Seçilen ilişki oturumda tutulur, üst barda değiştirilebilir. Sepet, fiyat, sipariş, cari, mesaj tamamen seçili ilişkiye bağlıdır.

## 6. Çoklu işletme (tenant) izolasyonu

**Model:** Tek veritabanı, her işletmeye ait tabloda `business_id` sütunu.

> Keep2B her müşteriye ayrı veritabanı veriyor. KVM 2 üzerinde onlarca ayrı veritabanının migration/yedek yönetimi tek kişilik ekip için gereksiz operasyon yükü. Tek veritabanı + aşağıdaki çok katmanlı koruma yeterli ve test edilebilir. İleride büyük bir müşteri ayrı kurulum isterse kod aynen ayrı sunucuya kurulabilir.

**Katmanlar**

1. **TenantContext:** İstek başında oturumdan belirlenir — toptancı için `business_users.business_id`, bayi için oturumdaki ve her istekte doğrulanan `dealer_relationship_id`. URL'den veya formdan **asla** alınmaz.
2. **Global scope:** İşletmeye ait her model `BelongsToBusiness` trait'i taşır: tüm sorgulara otomatik `WHERE business_id = ?` eklenir, kayıt oluştururken `business_id` otomatik atanır. Bağlam yokken sorgu **hata fırlatır** (sessizce tüm veriyi döndürmez).
3. **Route model binding kapsamlı:** `/toptanci/siparisler/123` başka işletmenin siparişiyse 404 döner (403 bile değil — kaydın varlığı sızdırılmaz).
4. **Bayi sahiplik kontrolü:** Bayi tarafında ek olarak `dealer_relationship_id = aktif ilişki` şartı (Policy).
5. **Veritabanı seviyesi:** Kritik alt tablolarda bileşik yabancı anahtar — ör. `orders (business_id, dealer_relationship_id)` → `dealer_relationships (business_id, id)`. Kod hatası olsa bile farklı işletmenin bayisine sipariş yazılamaz.
6. **Kuyruk işleri** `business_id`'yi açıkça taşır ve iş başında bağlamı kurar.
7. **Platform kodu** kapsamı yalnızca açık `withoutBusinessScope()` çağrısıyla kaldırabilir; bu yalnızca platform modülünde izinli ve ticari tablolarda kullanılmaz.
8. **Otomatik test:** Tüm toptancı ve bayi rotaları B işletmesinin kayıt ID'leriyle, A işletmesi oturumuyla taranır; hepsinin 404 dönmesi beklenir. Yeni rota eklendiğinde test otomatik kapsar.

**Platform yöneticisinin ticari veriye erişimi:** Varsayılan olarak yok. Platform paneli yalnızca işletme kartı, lisans, kullanım sayaçları (ürün/bayi/sipariş **adedi**, tutar değil) ve teknik durumu görür. Teknik destek gerektiğinde toptancı kendi ayarlarından süreli (ör. 24 saat) **destek erişimi** verir; bu erişimle yapılan her görüntüleme `audit_logs`'a yazılır ve toptancı listeden görebilir/iptal edebilir.

## 7. Güvenlik özeti

| Konu | Uygulama |
|---|---|
| Parola | Argon2id/bcrypt; en az 10 karakter; sızdırılmış parola kontrolü yok (dış servis çağrısı gerektirir) |
| Hesap açma | Parola e-postayla gönderilmez; 72 saat geçerli tek kullanımlık aktivasyon linki, veritabanında yalnızca hash'i |
| CSRF / XSS / SQLi | Laravel CSRF token; Blade otomatik kaçış, kullanıcı HTML'i kabul edilmez (mesaj ve açıklamalar düz metin); yalnızca parametreli sorgu |
| Oturum | `HttpOnly`, `Secure`, `SameSite=Lax`, girişte oturum ID yenileme, 2 saat hareketsizlik |
| Dosya yükleme | Uzantı + MIME (fileinfo) + boyut kontrolü; görseller yeniden kodlanır (EXIF temizlenir); dosyalar `public` dışında saklanır, yetki kontrollü controller ile sunulur; rastgele dosya adı |
| E-posta uygulama şifresi | Ayrı anahtarla (`MAIL_SECRET_KEY`, `.env`) AES-256-GCM şifreli; arayüzde, logda, hata mesajında asla gösterilmez |
| Dışa aktarma | CSV/Excel formül enjeksiyonu engeli (`= + - @ \t \r` ile başlayan metin hücreleri `'` ile kaçırılır); dosya linkleri süreli ve sahibine özel |
| Denetim kaydı | Sipariş onay/ret/iptal, stok düzeltme, cari düzeltme, tahsilat, fiyat değişikliği, bayi onay/askı, işletme oluşturma/askı, destek erişimi |
| Hata yönetimi | Kullanıcıya genel mesaj + referans kodu; ayrıntı yalnızca log'da; üretimde `APP_DEBUG=false` |
| HTTPS | Certbot; HSTS |
| Yedek | Günlük `mysqldump` + yüklenen dosyalar, 14 gün saklama, sunucu dışı kopya; ayda bir geri yükleme denemesi |

## 8. Tasarım sistemi

**Renkler**

| Token | Değer | Kullanım |
|---|---|---|
| `primary` | `#1E3A8A` (lacivert) | Birincil buton, aktif menü |
| `primary-dark` | `#172554` | Bayi üst menü zemini |
| `ink` | `#0F172A` | Ana metin |
| `muted` | `#64748B` | İkincil metin |
| `line` | `#E2E8F0` | Kenarlık |
| `surface` | `#F8FAFC` | Sayfa zemini |
| `success` / `warning` / `danger` / `info` | `#15803D` / `#B45309` / `#B91C1C` / `#0369A1` | Durum etiketleri (hepsi beyaz zeminde ≥ 4.5:1 kontrast) |

Toptancı kendi logosunu ve tek bir **vurgu rengini** seçebilir; vurgu rengi yalnızca bayi panelinin başlık şeridinde ve e-posta başlığında kullanılır. Kontrast yetersizse sistem varsayılan laciverde döner.

**Tipografi:** Inter (yerel barındırılır), 14px tablo, 15px gövde, tek ölçek (12/14/15/18/24/30).

**Ortak bileşenler:** sayfa başlığı (başlık + tek birincil işlem), KPI kartı, veri tablosu (sıralama, sayfalama, sütun seçimi, mobilde kart görünümü), filtre çubuğu (temel filtreler görünür, "Gelişmiş filtreler" açılır), durum etiketi, form bölümü, onay modalı (geri alınamaz işlemlerde işlem adını yazdıran), boş durum (ne yapılabileceğini söyleyen bir buton), toast, yükleniyor iskeleti.

**Sayfa kuralı:** Her sayfanın tek ana amacı ve en fazla bir birincil butonu var. Dashboard: üstte 4 KPI → 1–2 grafik → "Bekleyen işler" → son siparişler. Diğer metrikler raporlarda.

## 9. Navigasyon

**Toptancı (sol menü, gruplu, tek seviye):**

| Grup | Öğeler |
|---|---|
| — | Dashboard |
| Satış | Siparişler · Ürün Talepleri |
| Katalog | Ürünler · Kategori ve Markalar · Fiyatlandırma |
| Stok | Stok Durumu · Stok Hareketleri · Depolar ve Sayım |
| Bayiler | Bayiler · Başvurular |
| Finans | Cari Hesaplar · Tahsilatlar |
| İletişim | Mesajlar · Duyurular |
| — | Raporlar |
| — (alt) | Ayarlar (İşletme, E-posta, KDV ve Birimler, Destek erişimi) · Profil ve Güvenlik |

Menü rozetleri: onay bekleyen sipariş, yeni başvuru, yeni talep, okunmamış mesaj sayısı.

**Bayi (üst menü):** Ana Sayfa · Katalog · Siparişlerim · Taleplerim · Cari Hesap · Mesajlar · Raporlar. Sağda: arama (ad / kod / barkod), bildirim, sepet (adet), hesap menüsü (Sevk Adresleri, İşletme Profili, Güvenlik, Toptancı Değiştir, Çıkış). Cari bakiye üst barda görünür.

**Platform (sol menü):** Genel Durum · İşletmeler · Lisanslar ve Modüller · E-posta Altyapısı · Hata Kayıtları · Yedekler · Denetim Kayıtları · Sistem Ayarları · Yöneticiler.

> Uygulamada: "Sistem Ayarları" ekranı yapılmadı; sistem ayarları `.env` ve `config/b2b.php` üzerindendir (docs/25). Platform yöneticisi panelden değil, sunucuda `php artisan platform:create-admin` komutuyla oluşturulur (Aşama 2 kararı).

## 10. Gereksinimlerde netleştirilen noktalar ve önerilen kararlar

Aşağıdakiler gereksinim metninde açık bırakılmış ya da birbiriyle gerilim içinde olan noktalardır. Her biri için önerilen varsayılan yazılmıştır; onayınızla kesinleşir.

| # | Konu | Önerilen karar |
|---|---|---|
| K1 | "Taslak" sipariş durumu | Kullanılmaz. Sepet zaten taslaktır; sipariş "Toptancıya iletildi" ile doğar. |
| K2 | "Kısmi karşılandı" | Toptancı onay ekranında yetersiz stoklu kalemlerin miktarını düşürerek (veya kalemi 0 yaparak) onaylayabilir. Sipariş "Onaylandı" olur, üzerinde **Kısmi** işareti ve bayiye açıklama gider. Kalan miktar otomatik sipariş/talep oluşturmaz. |
| K3 | Hangi depodan düşülür | Her işletmenin bir **varsayılan sevk deposu** var; bayiye gösterilen stok ve onayda düşülen stok bu depodur. Toptancı onay ekranında siparişin deposunu değiştirebilir (sipariş tek depodan çıkar). |
| K4 | Bayiye stok nasıl gösterilir | İşletme ayarı: "Adet göster" veya "Stokta / Sınırlı / Yok". Varsayılan: Stokta / Sınırlı / Yok. |
| K5 | Cari borç ne zaman oluşur | İşletme ayarı; varsayılan **sipariş onayında**, stok düşümüyle aynı transaction'da. Alternatif: "Tamamlandı" durumunda. |
| K6 | Fiyat kurallarının önceliği | Bkz. [03 §4](03-is-kurallari.md#4-fiyatlandırma). İndirimler **yığılmaz**; en spesifik tek kural uygulanır. |
| K7 | Stok değeri (dashboard) | "Alış fiyatı × miktar" olarak etiketlenir. FIFO/ağırlıklı ortalama maliyet v1'de yok; kârlılık bu yüzden **gösterilmez**. |
| K8 | Varyantlar | Her varyant kendi SKU'su, stoku ve fiyatı olan ayrı üründür; ortak bir "varyant grubu" altında toplanır (renk/boyut). Ayrı varyant-stok matrisi kurulmaz. |
| K9 | Toptancı e-postası yapılandırılmamışsa | Bildirim uygulama içinde oluşur, e-posta "yapılandırılmadı" diye loglanır. Bayi aktivasyon linki toptancı ekranında kopyalanabilir olarak gösterilir. Platform e-postası toptancı adına **kullanılmaz**; yalnızca platform kendi e-postalarında (işletme sahibi daveti, platform parola sıfırlama) kullanılır. |
| K10 | Gmail kısıtları | Uygulama şifresi için Google hesabında 2 adımlı doğrulama açık olmalı; bazı Workspace hesaplarında yönetici kapatmış olabilir; Gmail günlük gönderim sınırları vardır (kişisel hesaplarda ~500 alıcı/gün). Bunlar ayar ekranında kısa not olarak yazılır. Gönderen adres, kimlik doğrulanan hesap olmak zorundadır. |
| K11 | Bayi hesabı çoklu toptancı | Başvuru e-postası mevcut bir bayi kullanıcısına aitse yeni hesap açılmaz; mevcut bayiye yeni toptancı ilişkisi eklenir ve bayiye bilgi e-postası gider. |
| K12 | Kullanıcı sayısı | **Onaylandı:** Her toptancı işletmesinde tek kullanıcı, her bayide tek kullanıcı. Rol sistemi ve ek kullanıcı daveti yok. |
| K13 | Duyurular | Bayi dashboard'undaki "yeni duyurular" ve "işletme duyuruları" e-postası için basit bir Duyurular sayfası eklenir (gereksinimdeki 15 modül listesinde yoktu). |
| K14 | Fiyatlandırma menüsü | Bölüm 8 gereği eklenir (15 modül listesinde ayrı başlık yoktu); Katalog grubu altında. |

## 11. Aşama planı

| Aşama | Kapsam | Çıkış ölçütü |
|---|---|---|
| 1 | Bu doküman seti | Kararların onayı |
| 2 | Laravel kurulumu, migration, 3 guard, tenant scope, platform → işletme oluşturma, ortak UI | Üç alana giriş + çapraz erişim testleri yeşil |
| 3 | Ürün, kategori, marka, görsel, depo, stok hareketi, sayım, transfer, CSV/Excel içe aktarma | Stok hareket testleri, içe aktarma hata raporu |
| 4 | Başvuru → onay → aktivasyon, bayi ilişkisi, grup, fiyat listesi, adres | Başvurudan ilk girişe uçtan uca test |
| 5 | Katalog, sepet, sipariş, onay/ret/iptal, ürün talebi | Eşzamanlı onay testi (fazla satış yok) |
| 6 | Cari, tahsilat, ekstre, vade | Bakiye = hareket toplamı tutarlılık testi |
| 7 | Mesajlaşma, PHPMailer, şablonlar, bildirim | Gmail bağlantı testi, başarısız e-postanın siparişi etkilememesi |
| 8 | Dashboard'lar, rapor merkezi, Excel/PDF | Ekran ve dosya toplamlarının eşitliği testi |
| 9 | Güvenlik ve negatif testler, performans ölçümü | Kabul listesi |
| 10 | Sunucu, HTTPS, yedek + geri yükleme, dokümantasyon | Üretim kontrol listesi |
