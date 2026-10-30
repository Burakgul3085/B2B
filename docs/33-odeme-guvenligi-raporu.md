# Ödeme güvenliği (mağaza + B2B)

Amaç: internet kopması, yavaş bağlantı, çift tıklama, geri tuşu, iki sekme gibi durumlarda müşteriden **iki kez para
çekilmemesi**, çekilirse **otomatik iade edilmesi** ve "ödeme bekleniyor" durumunda kalan siparişin kendiliğinden
sonuçlanması.

## Mağaza

| Durum | Davranış |
|---|---|
| Çift tıklama / geri tuşuyla aynı formu yeniden gönderme | Formda tek kullanımlık `submit_token` var (`store_orders.submit_token`, benzersiz). Aynı belirteçle ikinci gönderim yeni sipariş açmaz; ödeme bekleyen siparişin iyzico sayfasına, sonuçlanmışsa siparişe yönlendirir. Buton tıklanınca kilitlenir. |
| Aynı sepetle yeni ödeme denemesi | Önce açık deneme iyzico'dan sorgulanır: ödenmişse yeni ödeme alınmaz, müşteri siparişine gider ("ödemeniz zaten alınmış"). Ödenmemişse eski sipariş kapatılır (stok serbest), yenisi açılır. |
| Kapatılmış denemenin sonradan ödenmesi / aynı siparişe ikinci ödeme | Ödeme otomatik iade edilir (`auto_refunded`); iade reddedilirse `refund_failed` olarak işaretlenir. Müşteriye ve yöneticiye e-posta, siparişte "Fazla ödeme" olayı ve denetim kaydı. Siparişin tutarları değişmez. |
| Dönüş gelmedi, sipariş "ödeme bekleniyor" | Sipariş sayfası 5 sn'de bir durumu sorar (sunucu en fazla 10 sn'de bir iyzico'ya gider); zamanlanmış görev 2 dakikada bir açık denemeleri kontrol eder. Form ömrü 30 dk; 35 dk sonunda ödenmemiş sipariş kapanır, stok serbest kalır. |
| Kötüye kullanım | Bir IP veya e-posta için en fazla 3 ödenmemiş kart siparişi. |
| Ödeme sırasında sepete eklenen ürün | Ödeme onaylanınca yalnızca siparişteki satırlar sepetten silinir. |
| iyzico yanıtının doğruluğu | Tutar, sepet numarası, para birimi (TRY), belirteç ve fraud durumu kontrol edilir. Yanıt imzası (HMAC-SHA256) **İzle** modunda yalnızca kayda geçer ve yöneticiye bildirilir; **Zorunlu** modunda imzası tutmayan ödeme kabul edilmez (Ayarlar → Ödemeler). |
| Geri dönüş adresi | Canlıda `config('store.domain')` ile kurulur; istek başlığından türetilmez. |
| İade sırasında iyzico'ya ulaşılamaması | İade "sonucu belirsiz" kaydedilir, yeniden denenmez (çift iade önlenir). Yönetici iyzico panelinden bakıp siparişte **Yapıldı / Yapılmadı** işaretler. |
| Test (sandbox) modu | Panellerde kırmızı "Kartla ödeme TEST modunda" uyarısı; test ödemeleri "TEST ÖDEMESİ – para alınmadı" olarak gösterilir. |

## B2B

- Aynı siparişe yeni kart formu açılmadan önce açık deneme sorgulanır; ödenmişse "Bu siparişin ödemesi zaten alındı"
  denir, ödenmemişse eski deneme kapatılır.
- Kapatılmış denemenin geç ödenmesi veya ikinci ödeme otomatik iade edilir; toptancıya e-posta + bildirim, bayiye
  bildirim. Cariye tek tahsilat yazılır.
- Bayi sipariş ekranı açık ödemeyi canlı kontrol eder; zamanlanmış görev 2 dakikada bir çalışır.
- İptalde kart iadesi sırasında iyzico'ya ulaşılamazsa sipariş iptal edilmez; iyzico panelinden kontrol edilip iptal
  yeniden denenir.

## Canlıya alma sonrası

1. Küçük tutarlı gerçek bir ödeme ve iadesi.
2. Sandbox ödemesiyle imza kontrolünün denetim kaydında uyarı üretmediği doğrulanınca imza ayarı **Zorunlu** yapılır.

Testler: `tests/Feature/Store/StorePaymentSafetyTest.php`, `tests/Feature/Ordering/DealerPaymentSafetyTest.php`.
