# Havale / EFT ile ödeme ve kullanım kılavuzları

22.10.2026 tarihinde üç karar alındı:

- **Desiye göre kargo ücreti olmayacak.** Kargo firmasıyla anlaşma yapılacak. Bir tutar sınırı konacak: sınırın üzerindeki siparişlerde kargo ücretsiz, altındakilerde sabit ücret alınacak. Bu zaten mevcut ayar (Ödeme ve Teslimat → Kargo); ek geliştirme yok.
- **Havale / EFT ile ödeme eklendi** (isteğe bağlı, varsayılan kapalı).
- **Kullanım ve kurulum kılavuzları yazıldı.**

Cloudflare Turnstile yapılmayacak; isteğe bağlı olarak listede duruyor.

## 1. Havale / EFT

### Ayar
**Mağaza yönetimi → Ayarlar → Ödeme ve Teslimat → Havale / EFT:**

- **Banka hesapları:** Her satıra bir hesap yazılır: `Banka | Hesap sahibi | IBAN`. En fazla 5 hesap girilebilir.
  - IBAN, TR biçimi ve kontrol hanesiyle doğrulanır, boşluksuz saklanır.
  - Hesap girilmeden yöntem açılamaz.
- **Ödeme süresi:** 1–7 gün, varsayılan 3 gün.

### Müşteri akışı

1. Ödeme sayfasında "Havale / EFT" seçilir. Yöntem kargo ve mağaza teslimatıyla kullanılabilir, SMS doğrulaması yoktur.
2. Sipariş hemen oluşur:
   - Durumu **Havale bekleniyor** olur.
   - Stok hemen satışa düşer, böylece ürün başkasına satılmaz.
   - Sepet boşaltılır.
3. Sipariş sayfası ve "Siparişiniz alındı" e-postası şunları gösterir:
   - tutar
   - hesaplar (IBAN dörderli gruplanmış)
   - açıklamaya yazılacak **sipariş numarası**
   - son ödeme zamanı

   Sözleşmeler de aynı e-postadadır.

### Yönetim akışı

- Sipariş "Yeni sipariş" bildirimiyle gelir. Bekleyen havaleler şu yerlerde görünür:
  - Siparişler'deki **Havale bekleyen** sekmesi
  - Genel Bakış'taki **Havale bekleyen** sayacı
- Para hesaba geçince yönetici sipariş sayfasında **Havale alındı** der:
  - Sipariş **Hazırlanıyor** durumuna geçer, ödeme durumu "Ödendi" olur.
  - Müşteriye "Ödemeniz alındı" e-postası gider.
  - İşlem geçmişine yazılır.
  - İkinci kez uygulanamaz.
- Ödenmeden kargolama ve fatura ekleme yapılamaz.
- **Süre dolarsa:** `store:expire-transfers` komutu saatte bir çalışır:
  - Süresi geçen siparişleri "Havale / EFT süresi içinde ödeme alınmadı." gerekçesiyle iptal eder.
  - Stok geri girer, kupon hakkı geri verilir.
  - Müşteriye iptal e-postası gider.
- **İptal ve iade:** Havalesi alınmış sipariş doğrudan iptal edilemez. Önce tutar müşterinin hesabına gönderilir, **Para iadesi** ile elle kaydedilir, sonra sipariş iptal edilir.
  - Teslimden sonraki iadelerde müşteriden IBAN istenir; kapıda ödemedeki gibi tutar bu hesaba havale edilir.
  - İptal-İade şablonundaki metin bu yöntemi de kapsayacak şekilde güncellendi. Kayıtlı metin yönetimden yeniden kaydedilince yürürlüğe girer.

### Kod

| Dosya | Değişiklik |
|---|---|
| `app/Domain/Store/Payments/BankTransfer.php` (yeni) | Hesapların ayrıştırılması ve doğrulanması, süre, son ödeme zamanı, IBAN gösterimi |
| `CheckoutService` | Yöntem `transfer`. Stok satışa düşer, `transfer` ödeme kaydı açılır. |
| `OrderService` | `markTransferReceived()`, `expireTransfers()`. İptalde havale kuralı, ödeme durumunun doğru yazılması. |
| `StoreOrder` | Yeni durum `awaiting_transfer` ve sabit `CANCELLABLE`. `refundsToBank()`: iadesi havaleyle yapılan siparişler (kapıda ödeme ve havale). |
| E-posta ve bildirim | `OrderMailer`'a ödeme talimatı, hesap satırları ve "Ödemeniz alındı" eklendi. `AdminNotifier` metni güncellendi. |
| Ekranlar | Ödeme sayfası (`store-checkout.js` dahil), sipariş sayfası, yönetim sipariş sayfası, sipariş listesi, Genel Bakış, Ödeme ve Teslimat |
| Zamanlayıcı | `store:expire-transfers`, saatte bir |

Testler `tests/Feature/Store/StoreTransferTest.php` dosyasında, 7 test:

- sipariş, stok ve hesap bilgisi
- kapalıyken veya hesap yokken reddedilme
- yöneticinin onayı
- süre dolunca iptal
- havalesi alınmış siparişin iptal kuralı
- iadede IBAN zorunluluğu
- ayar doğrulaması

## 2. Kılavuzlar

`docs/kilavuz/` klasörü:

| Dosya | Okuyan |
|---|---|
| [toptanci.md](kilavuz/toptanci.md) | Toptancı paneli: ilk kurulum, ürün, fiyat önceliği, bayiler ve başvurular, sipariş akışı, stok, cari ve tahsilat, raporlar, destek erişimi |
| [bayi.md](kilavuz/bayi.md) | Bayi paneli: giriş, ürün ve fiyat, sipariş verme, sipariş durumları ve iptal, cari ekstre |
| [magaza-yonetimi.md](kilavuz/magaza-yonetimi.md) | Mağaza yönetimi: yöneticiler ve 2FA, yayına hazırlık sırası, katalog ve stok, ödeme ve teslimat ayarları, kart / kapıda / havale sipariş akışları, iadeler, kampanyalar, raporlar |
| [platform.md](kilavuz/platform.md) | Platform yöneticisi: hesap oluşturma, menüler, hata referans kodu, yedekler, destek erişimi |
| [kurulum.md](kilavuz/kurulum.md) | Sunucu kurulumu: alan adları, gerekenler, `.env`, cron ve kuyruk işçisi, güncelleme, yedek ve geri yükleme |
