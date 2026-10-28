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
- **Kesilen fatura değişmez.** e-Arşiv iptal edilebilir; o zaman müşterinin sipariş sayfasındaki fatura kalkar ve yeni taslak hazırlanabilir. e-Fatura sistemden iptal edilmez; iade faturası kesilir. İade faturası olan satış faturası iptal edilemez.
- **İade faturası** satış faturasına dayanır (UBL BillingReference, tip IADE). İade talebi seçilirse iade edilen adetler gelir, seçilmezse satış faturasının kalemleri gelir.
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

## Uyumsoft: bilgiler gelince yapılacaklar

`UyumsoftProvider` Uyumsoft'un SOAP Integration servisine göre yazıldı ama gerçek hesapla **denenmedi**. Kullandığı işlemler:
- WhoAmI
- IsEInvoiceUser
- GetUserAliasses
- SendInvoice
- GetOutboxInvoice
- GetOutboxInvoicePdf
- CancelEArchiveInvoice

Bilgiler gelince sırayla:

1. Fatura Ayarları'nda Sağlayıcı = Uyumsoft, Ortam = Test seçin. Kullanıcı adı, şifre ve gönderici VKN/TCKN'yi girin, ardından "Bağlantıyı dene".
2. Test ortamında üç fatura deneyin: bireysel e-Arşiv, kurumsal e-Fatura mükellefi ve iade faturası. Uyumsoft'un yanıt alanlarını (fatura numarası, PDF, iptal) kontrol edin; gerekirse `UyumsoftProvider`'da işlem ve alan adlarını düzeltin.
3. Mali müşavirle teyit edin:
   - Seri ve numaralandırma (numarayı Uyumsoft verir)
   - %0 KDV istisna kodu (şimdilik 351)
   - Kargo kaleminin KDV oranı
   - İade faturası belge türü
4. Ortam = Canlı.
