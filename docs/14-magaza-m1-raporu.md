# Mağaza M1 — Tek Toptancı Kilidi ve Mağaza Yönetimi Temeli

> Testler: 260/260. Mimari: [13-magaza-mimarisi.md](13-magaza-mimarisi.md). Bu aşamada mağazanın satış tarafı yok; yönetim paneli, hesaplar ve sınırlar kuruldu.

## 1. Tek toptancı kilidi (§4)

- `CreateBusiness` bir işletme varken yenisini açmaz. Kontrol transaction içinde, tablo kilitlenerek yapılır; aynı anda gelen iki istekten yalnızca biri geçer.
- Platformda "Yeni işletme" düğmeleri işletme varken gizlenir. Adres doğrudan açılırsa listeye geri döner.
- Platform → yeni bayi formunda tek işletme varken toptancı otomatik seçilir.
- Çok işletmeli altyapı (tenant izolasyonu, işletme kapsamlı sorgular, izolasyon testleri) yerinde kaldı.

## 2. Alan adına göre ayrım (§3)

| Alan | Yerel | Ayar |
|---|---|---|
| Mağaza | `magaza.localhost` | `STORE_DOMAIN` |
| Mağaza yönetimi | `yonetim.localhost` | `STORE_ADMIN_DOMAIN` |

- Mağaza rotaları `routes/store.php` dosyasındadır, kendi alan adlarına bağlıdır ve B2B rotalarından önce kaydedilir.
- `UseAreaSession`, mağaza alan adlarında mağaza dışı bir rota eşleşirse 404 döner. `/bayi`, `/toptanci`, `/platform` ve `/basvuru` adresleri bu alan adlarında açılmaz.
- Oturum çerezleri: yönetim için `magaza_yonetim`, mağaza için `magaza`. Çerezler alan adına bağlı olduğu için B2B çerezleri bu alan adlarına hiç gönderilmez.
- `Area` enum'una `StoreAdmin` eklendi; alan, URL öneki yerine alan adından tanınır. Ortak giriş, parola sıfırlama ve "Profil ve Güvenlik" ekranları bu alan için de çalışır.
- Mağaza ana sayfası şimdilik "Çok yakında" sayfasıdır (`noindex`). Vitrin M4'te gelecek.

## 3. Mağaza yöneticileri (§5)

- Tablo: `store_admins` (`is_super`, `is_active`, 2FA, `created_by_type/id`). Guard: `store_admin`.
- Kurallar `App\Domain\Store\Admins\StoreAdminService` sınıfında toplanır:

| | Süper admin | Yönetici | Platform |
|---|---|---|---|
| Yönetici açma | ✓ (yalnızca yönetici) | ✗ | ✓ (süper admin veya yönetici) |
| Bilgi/şifre değiştirme, pasife alma, 2FA sıfırlama | Yalnızca yöneticiler; kendisi ve diğer süper adminler hariç | ✗ | Herkes |

- Formdan `is_super` gönderilse bile süper admin yalnızca yönetici açar.
- Hesaplar silinmez, pasife alınır. Pasife alınan kişinin açık oturumu bir sonraki istekte kapanır; "beni hatırla" belirteci ve sıfırlama bağlantıları geçersiz olur.
- **2FA:** süper admin için zorunludur (ilk girişte kurulum). Yönetici "Profil ve Güvenlik"ten açıp mevcut şifresiyle kapatabilir. Kaybedilen uygulama için süper admin veya platform 2FA'yı sıfırlar.
- **Parolamı unuttum:** bağlantı sistem e-postasıyla gider (platform yöneticisindeki gibi), çünkü mağaza hesapları B2B işletmesinin Gmail'ine bağlı değildir.

## 4. İşlem geçmişi

Ortak `audit_logs` tablosu kullanılır; ayrı bir tablo açılmadı. Mağaza panelindeki **İşlem Geçmişi** iki tür kaydı gösterir: mağaza yöneticisinin yaptığı her işlemi (`actor_type = store_admin`) ve platformun mağaza hesaplarına yaptıklarını (`store.*`). B2B kayıtları görünmez. Ana sayfada son 8 işlem yer alır.

## 5. Ekranlar

- **Mağaza yönetimi:**
  - Giriş (mor tema), 2FA kurulum ve kod ekranı.
  - Genel Bakış: hoş geldin, 2FA önerisi, etkin yönetici sayısı, yol haritası, son işlemler.
  - Yöneticiler (süper admin).
  - İşlem Geçmişi.
  - Profil ve Güvenlik (2FA kartı dahil).
- **Platform:** yeni "Mağaza Yöneticileri" menüsü: liste, yeni hesap, hesap yönetimi.

## 6. Modül sınırı testi (§6)

`tests/Feature/Architecture/StoreBoundaryTest.php` üç şeyi denetler:

1. Mağaza kodu (`app/Domain/Store`, `app/Http/Controllers/Store`, `app/Models/Store`, `resources/views/store`) B2B modellerini ve domain modüllerini kullanmıyor. Yalnızca Auth, Audit, Files, Mail, Support ve `AuditLog`/`AuthToken` serbest.
2. B2B kodu mağaza koduna dokunmuyor.
3. `store_*` tabloları B2B tablolarına yabancı anahtarla bağlanmıyor.

## 7. Testler

| Dosya | Kapsam |
|---|---|
| `Store/StoreAdminAuthTest` (12) | Alan adı, B2B adreslerinin 404 olması, ayrı çerez, giriş, B2B hesaplarının girememesi, süper admin 2FA zorunluluğu, yönetici 2FA aç/kapat, pasif hesap, şifre değişikliği, parolamı unuttum |
| `Store/StoreAdminManagementTest` (7) | Platformun süper admin açması, süper adminin yönetici yönetmesi, yetki sınırları, platformun süper admini yönetmesi, işlem geçmişi filtresi, B2B oturumuyla erişilememe |
| `Architecture/StoreBoundaryTest` (3) | Modül sınırı |
| `Platform/PlatformAdminTest` (+1) | İkinci işletmenin açılamaması |
| `PagesRenderTest` (+2) | Mağaza yönetimi ve platform "Mağaza Yöneticileri" sayfaları; işletme yokken yeni işletme formu |

## 8. M2 gereksinimleri (Katalog)

- `store_categories` (ağaç), `store_brands`, `store_products`, `store_product_variants`, `store_product_images`.
- KDV dahil fiyat, üstü çizili fiyat, KDV oranı, desi/ağırlık, SEO alanları, slug.
- Görseller, B2B'deki `ImageUploader` ile işlenir (kırpma, netleştirme).
- Excel ile içe aktarma.
- Yönetim panelinde "Katalog" menüsü; her değişiklik işlem geçmişine yazılır.
