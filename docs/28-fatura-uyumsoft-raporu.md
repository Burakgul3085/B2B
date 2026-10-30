# Faturalar: elle hazırlanan e-Fatura / e-Arşiv (Uyumsoft)

Ödeme alınınca fatura **kendiliğinden kesilmez**. Yönetici, ödenen siparişin faturasını Faturalar ekranında hazırlar. Kalemleri, miktarı, fiyatı ve KDV'yi gerekirse değiştirir, sonra kendisi gönderir. Sağlayıcı Uyumsoft'tur. Uyumsoft bilgileri girilene kadar **deneme sürücüsü** çalışır: hiçbir yere göndermez, "DNM" numaralı ve üzerinde "DENEME" yazan PDF üretir.

## Ekranlar

| Ekran | İçerik |
|---|---|
| Satış → Faturalar | Altı sekme: fatura bekleyen (ödenen veya onaylanan, faturası olmayan siparişler; menüde sayısı görünür), iade faturası bekleyen, taslaklar, sorunlu (reddedilen veya sonucu belirsiz), gönderilen, iptal. Bekleyen siparişler seçilip toplu taslak yapılır; taslaklar seçilip toplu gönderilir. |
| Fatura taslağı | Kalemler (ad, miktar, KDV dahil birim fiyat, indirim, KDV oranı; kalem eklenir ve silinir). Canlı toplam ve fark uyarısı. Alıcı bilgileri (bireysel/kurumsal, VKN/TCKN, vergi dairesi, adres, e-posta). Fatura tarihi (en fazla 7 gün geri), açıklama, "müşteriye e-postayla bildir". Önizleme PDF'i. Düğmeler: Taslağı kaydet, Kaydet ve faturayı kes, Taslağı sil. |
| Kesilen fatura | Numara, ETTN, tür (e-Arşiv / e-Fatura), PDF, kalemler. e-Arşiv iptal edilebilir (nedeniyle). Satış faturasından iade faturası hazırlanabilir. |
| Sipariş detayı → Fatura kartı | "Fatura hazırla", "İade faturası hazırla" (iade talebi seçilebilir), siparişin fatura belgeleri. Başka yerde kesilen fatura hâlâ elle (PDF) eklenebilir. Faturalar ekranından kesilen fatura elle değiştirilemez. |
| Yönetim → Fatura Ayarları (yalnızca süper admin) | Sağlayıcı (Deneme / Uyumsoft), ortam (test / canlı), kullanıcı adı ve şifre (şifreli saklanır), gönderici VKN/TCKN, kargo kaleminin KDV oranı, "müşteriye bildir" varsayılanı, "Bağlantıyı dene". |
| Yayın Hazırlığı | "e-Fatura / e-Arşiv (Uyumsoft)" maddesi: deneme sürücüsünde "yapılacak", test ortamında "uyarı", canlıda "tamam". |

## Kurallar

- **Taslak siparişi değiştirmez.** Sipariş tutarı, ödeme, stok ve müşterinin ödediği tutar aynı kalır. Değişen yalnızca faturadır.
- **Tutarlar sunucuda hesaplanır** (`InvoiceMath`). Satır tutarı miktar × KDV dahil fiyat − indirim olarak bulunur; KDV hariç tutar ve KDV bundan ayrılır. Ekrandaki toplam yalnızca gösterimdir.
- **Fark uyarısı:** Fatura toplamı ödenen tutardan farklıysa (iade faturasında iade edilen tutardan) ekranda fark gösterilir. Gönderim için "Farkı biliyorum" onayı gerekir. Toplu gönderim farkı onaylanmamış taslağı atlar.
- **e-Fatura / e-Arşiv:** VKN veya TCKN yazılmış alıcı sağlayıcıdan sorgulanır. e-Fatura mükellefiyse e-Fatura (TEMELFATURA), değilse e-Arşiv kesilir. Kimliksiz bireysel alıcıda 11111111111 kullanılır.
- **Çift fatura olmaz.** Taslak, gönderim başında tek seferlik "gönderiliyor" durumuna geçer. ETTN'yi sistem üretir. Bağlantı koparsa fatura "sonuç belirsiz" kalır ve yeniden gönderilemez. "Sağlayıcıdan sorgula" ile bulunursa kesilmiş sayılır, bulunmazsa taslağa döner. Yönetici sağlayıcı panelinde kontrol edip "taslağa al" da diyebilir.
- **Kesilen fatura değişmez.** e-Arşiv iptal edilebilir; o zaman müşterinin sipariş sayfasındaki fatura kalkar ve yeni taslak hazırlanabilir. e-Fatura buradan iptal edilmez; iptal alıcıyla GİB portalından yürütülür. İade faturası olan satış faturası iptal edilemez.
- **İade faturası** satış faturasına dayanır (UBL BillingReference, tip IADE). Alıcı bilgileri ve kalemler satış faturasından gelir; iade talebi seçilirse yalnızca iade edilen adetler gelir.
- **e-Fatura mükellefi alıcının iadesinde iade faturasını alıcı keser.** Bu siparişler "iade faturası bekleyen" listesine girmez; sipariş sayfasında uyarı çıkar, gelen iade faturası "Elle iade faturası ekle" ile eklenir. Mükellef alıcıya iade faturası gönderilmeye çalışılırsa gönderim durdurulur.
- **Fatura tarihi** bugünden ileri, 7 günden eski ve siparişten (iadede satış faturasından) önce olamaz. Tarihler Türkiye saatine göredir.
- Taslak varken sipariş iptal edilirse satış faturası gönderilemez.
- Kurumsal alıcı 10 haneli VKN ya da şahıs firmasında 11 haneli TCKN ile girilebilir.
- **Müşteri tarafı değişmedi.** Kesilen fatura `store_invoices` (satış) veya `store_credit_notes` (iade) kaydına yazılır ve PDF'i eklenir. Müşteri sipariş sayfasından indirir; istenirse "Faturanız hazır" e-postası gider.
- Her işlem (taslak, değişiklik öncesi/sonrası kalemler, gönderim, ret, iptal) işlem geçmişine yazılır.

## Kod

- `app/Domain/Store/Invoicing/`:
  - `EInvoiceService`: taslak, düzenleme, gönderim, toplu işlemler, sorgu, iptal.
  - `InvoiceMath`, `UblBuilder` (UBL-TR 1.2), `InvoicePdf` (önizleme ve deneme PDF'i), `EInvoiceSettings`.
  - `Providers/`: `EInvoiceProvider` arayüzü, `DenemeProvider`, `UyumsoftProvider`.
- Tablolar: `store_e_invoices`, `store_e_invoice_lines` (migration `2026_11_28_000100`).
- Denetleyiciler: `Store\Admin\InvoiceController`, `Store\Admin\InvoiceSettingsController`.
- Görünümler: `resources/views/store/admin/invoices/`. Alpine bileşenleri: `invoiceEditor`, `pickList`.
- Test: `tests/Feature/Store/StoreEInvoiceTest.php` (11 test).

## Uyumsoft: test ortamında doğrulandı (31.10.2026)

`UyumsoftProvider` Uyumsoft'un WSDL'ine (`https://efatura-test.uyumsoft.com.tr/Services/Integration?singleWsdl`) göre düzeltildi ve Uyumsoft'un ortak test hesabıyla (kullanıcı `Uyumsoft`, VKN 9000068418) uçtan uca denendi:

- Bağlantı (WhoAmI)
- Bireysel e-Arşiv: numara (BOX…), ETTN, PDF
- Sorgu
- e-Arşiv iade faturası
- Kurumsal alıcıya e-Fatura (DRP…)
- e-Arşiv iptali

Düzeltilenler:

- **Kimlik doğrulama:** WS-Security UsernameToken başlığıyla yapılır. Eskiden gövdede `userInfo` gönderiliyordu ve servis bunu "InvalidSecurity" diye reddediyordu.
- **`InvoiceInfo`:**
  - Zorunlu `CreateDateUtc` eklendi.
  - Öğe sırası: Invoice, TargetCustomer, EArchiveInvoiceInfo, Scenario, CreateDateUtc.
  - `InternetSalesInfo` öğeleri öznitelik değil, alt öğedir. `ShipmentInfo` (`SendDate` + `Carier`) bunun içindedir.
- **İptal:** `CancelEArchiveInvoice` isteği `<request InvoiceId CancelDate/>` biçimindedir.
- **Alıcı posta kutusu:** `GetUserAliasses` → `ReceiverboxAliases/@Alias`. Açık olan ve tercihen `urn:mail:` ile başlayan etiket seçilir.
- **Bağlantı denemesi:** Hesabın unvanını ve VKN/TCKN'sini gösterir. Gönderici kimliği hesaptakinden farklıysa uyarır.

Not: Yerel Windows PHP'sinde CA sertifika paketi tanımlı değildir. Gerçek servise yerelden bağlanmak için `php -d curl.cainfo=… -d openssl.cafile=…` gerekir. Sunucuda gerekmez.

## Canlıya geçiş

1. Bağlantıda **web servis kullanıcısı** kullanılır, portal kullanıcısı değil: Kurumsal Portal (edonusum.uyum.com.tr) → Genel İşlemler & Ayarlar → Tanımlama İşlemleri → Kullanıcı Tanımları'nda `…_WebServis` adlı, "Integration & Ledger & Despatch Service Operator" rollü kullanıcı. Şifresi bilinmiyorsa aynı ekranda yeni şifre verilir. Canlı servis: `https://edonusumapi.uyum.com.tr/Services/Integration` (eski `efatura.uyumsoft.com.tr` adresi bu hesabı tanımaz; ikisi de yanlış bilgide "Bu sisteme erişmek için gerekli yetkiniz yok" döner). IP kısıtı Firma Genel Bilgileri → Yetkilendirme ve Güvenlik'tedir; liste boşsa kısıt yoktur.
2. Fatura Ayarları:
   - Sağlayıcı = Uyumsoft, Ortam = Canlı.
   - Web servis kullanıcı adı ve şifresi girilir.
   - Gönderici VKN/TCKN = Uyumsoft hesabındaki kimlik. "Bağlantıyı dene" bunu gösterir ve farklıysa uyarır.
   - "Bağlantıyı dene" çalıştırılır.
3. Mağaza ayarlarında firma unvanı, adres ve vergi dairesi doğru olmalıdır, çünkü faturada satıcı bilgisi olarak bunlar çıkar.
4. Mali müşavirle teyit edin:
   - %0 KDV istisna kodu (şimdilik 351)
   - Kargo kaleminin KDV oranı
   - Bireysel iadede e-Arşiv iade faturası mı, gider pusulası mı kesilecek
   - 11111111111 için tutar sınırı
5. İlk gerçek siparişte faturayı kesip PDF'i ve Uyumsoft portalındaki kaydı kontrol edin.
