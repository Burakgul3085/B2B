# Mağaza: müşteri iptali, fotoğraflı iade, iade kargosu

## Müşterinin kendi iptali (kargoya verilmeden önce)

Sipariş sayfasında (üye hesabından veya misafir sipariş bağlantısından) "Siparişi iptal et". Sipariş "Onay bekliyor", "Havale bekleniyor", "Ödendi" veya "Hazırlanıyor" durumundaysa görünür; kargoya verilince kalkar. Müşteri nedeni seçer (`StoreOrder::CANCEL_REASONS`) ve isterse açıklama yazar.

| Ödeme | Sonuç |
|---|---|
| Kart (ödenmiş) | Sipariş iptal edilir, tutar iyzico'dan karta iade edilir (iade alınamazsa iptal olmaz), stok geri döner. |
| Kapıda ödeme, ödenmemiş havale | Sipariş hemen iptal edilir, stok geri döner. |
| Havale (ödenmiş) | Para elle iade edileceği için **iptal talebi** kaydedilir (IBAN ve hesap sahibi zorunlu). Sipariş açık kalır; yönetim sipariş sayfasında talebi ve IBAN'ı görür, parayı gönderip "Para iadesi" ile kaydeder, sonra iptal eder. |

Yöneticilere e-posta gider (Yönetici bildirimleri → sipariş). "Hazırlanıyor" durumundaki iptalde e-postada paketlenmiş ürünlerin rafa konması hatırlatılır.

## İade talebi

- **Fotoğraf:** En fazla 5 (JPG, PNG, WEBP; her biri en fazla 10 MB). "Hasarlı geldi", "Yanlış ürün", "Açıklamadaki gibi değil" nedenlerinde en az 1 zorunlu. Fotoğraflar yeniden kodlanır, özel diskte (`store/returns`) tutulur; yönetimde ve müşterinin sipariş sayfasında 1 saatlik imzalı bağlantıyla gösterilir.
- **Akış:** Talep alındı → Onaylandı (kargo bekleniyor) → Kargoya verildi → Ürün teslim alındı → Para iadesi yapıldı. Ret ve "Süresi doldu" kapanır; bu taleplerdeki adetler yeniden iade istenebilir.
- **İade kargosu:** Onay e-postasında ve sipariş sayfasında iade adresi, iade numarası ve kargolama son günü yazar. Müşteri şubeden **gönderici ödemeli** gönderir, sonra sipariş sayfasına kargo firması ve takip numarasını girer ("Kargoya verdim"). Vazgeçme / diğer nedenlerde kargo ücreti müşteriye aittir. Mağazadan kaynaklı nedenlerde müşteri ödediği kargo ücretini yazar; bu tutar iade tutarına eklenir (sipariş tutarıyla sınırlı).
- **Süre:** Onaydan sonra 7. günde kargo bilgisi yoksa hatırlatma e-postası, 14. günde talep "Süresi doldu" olur (`store:return-reminders`, her gün 10:00).
- **Yönetim:** "Teslim alındı, iade et" tek adımda ürünleri teslim alır (sağlam adetler stoğa döner) ve parayı iade eder (kartta iyzico, kapıda ödemede havale bilgisiyle kayıt). Tutar sunucuda hesaplanır, düşürülebilir. Para iadesi başarısız olursa ürün teslim alınmış kalır ve iade ayrıca yeniden denenir. "Yalnızca teslim al" seçeneği de vardır.
- Panelde İadeler rozeti yeni ve kargodaki talepleri sayar; panoda "Kargodaki iade" satırı vardır.

## Yasal not

İade kargo ücretinin müşteriye ait olması, mesafeli satış sözleşmesi ve ön bilgilendirme formundaki koşullara bağlıdır. Sözleşme metinlerinin bu kurala uygun olduğu bir uzmana teyit ettirilmelidir.

## Teknik

- Migration: `2026_12_01_000100_add_store_return_photos_and_cancel_requests` (`store_return_photos`; `store_returns.cargo_*`, `shipped_at`, `reminded_at`; `store_orders.cancel_request*`, `cancel_refund_*`).
- Kod: `OrderService::cancelByCustomer`, `ReturnService::ship / receiveAndRefund / remindAndExpire / shippingInstructions`, `Front\ReturnController`, `Admin\ReturnController::receiveAndRefund / photo`.
- Testler: `tests/Feature/Store/StoreFulfillmentTest.php`.
