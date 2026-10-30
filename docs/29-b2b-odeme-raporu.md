# B2B ödemeleri: kart, havale, açık hesap, teslimde ödeme

Bayi siparişleri artık ödemeli verilebilir. Ödeme sistemi toptancı tarafından açılana kadar her şey eskisi gibidir: sipariş ödemesiz verilir, tutar carisine borç yazılır.

## Akış

1. **Sepet:** Bayi ödeme yöntemini seçer. Kart ve havale (açıksa) tüm bayilere görünür. Açık hesap (cari) ve teslimde ödeme yalnızca bayi kartında izin verilen bayilere görünür.
2. **Onay:** Toptancı miktarları düşürebilir (kısmi onay). Onaylanan tutar yeniden hesaplanır, onay öncesi tutar `requested_total` olarak saklanır. Kart, havale ve teslimde ödemede sipariş "ödeme bekleniyor" olur.
3. **Bildirim:** Bayiye "onaylanan tutarı öde" e-postası (`order_payment_due`) ve uygulama içi bildirim gider. Miktar değiştiyse e-postada "DİKKAT" satırı ve her değişen kalem için "istendi → onaylandı" satırı bulunur. Bayinin sipariş ekranında değişiklik kutusu ve ödeme paneli görünür.
4. **Ödeme:**
   - Kart: iyzico Ödeme Formu. Sonuç iyzico'dan sorgulanır; durum, sepet kimliği ve tutar tutmazsa kabul edilmez. Aynı belirteç ikinci kez işlenmez.
   - Havale: bayi IBAN'ları ve açıklamaya yazacağı sipariş numarasını görür. Toptancı parayı görünce siparişte "Ödeme alındı" ile kaydeder.
   - Teslimde ödeme: toptancı teslimde nakit / POS ile alınan ödemeyi aynı yerden kaydeder.
   - Bayi onaylı ve ödenmemiş siparişin yöntemini değiştirebilir (izinli yöntemler arasında).
5. **Cari:** Her başarılı ödeme carisine tahsilat yazar (kart: "Kredi kartı (sanal POS)"). Onaydaki borçla birlikte bakiye sıfırlanır.
6. **Kural:** Kart / havale siparişi ödeme alınmadan "Hazırlanıyor" yapılamaz. Ödemesi beklenen sipariş tamamlanamaz.

## İptal

- Kartla ödenmiş sipariş iptal edilirse tutar iyzico'dan iade edilir, tahsilat ters kayıtla iptal edilir. İade alınamazsa sipariş iptal edilmez ve hata gösterilir.
- Havale / nakit / POS ile ödenmiş siparişte para bayinin carisinde alacak olarak kalır; iadeyi toptancı yapar (gerekirse tahsilatı Tahsilatlar ekranından iptal eder).

## Teslimat ücreti

Kargo yoktur; teslimatı toptancı yapar. Ödeme Ayarları'nda isteğe bağlı teslimat ücreti ve "bu tutar ve üstü ücretsiz" sınırı girilir (varsayılan 0). Ücret KDV dahil genel toplama eklenir, onayda onaylanan tutara göre yeniden hesaplanır ve cariye yazılır. `orders_totals_chk` kısıtı `grand_total = net_total + vat_total + delivery_fee` olarak güncellendi.

## Ekranlar

| Ekran | İçerik |
|---|---|
| Toptancı → Ayarlar → Ödeme Ayarları | Ödeme sistemi aç/kapa, kartla ödeme (iyzico ortamı, şifreli API anahtarları, taksitler), havale hesapları (IBAN'dan banka tanınır), teslimat ücreti. |
| Toptancı → Bayi → Koşullar | "Açık hesapla sipariş verebilir", "Teslimde ödeme seçebilir". |
| Toptancı → Siparişler | "Ödeme bekleyen" sekmesi ve ödeme rozeti. Sipariş detayında Ödeme kartı: yöntem, durum, ödeme kayıtları, "Ödeme alındı" formu. |
| Bayi → Sepet | Ödeme yöntemi seçimi, teslimat ücreti satırı. |
| Bayi → Sipariş | Ödeme paneli (Kartla öde, havale bilgileri, teslimde ödeme notu, yöntem değiştirme), değişiklik kutusu, ödendi / iade edildi bilgisi. |

## Teknik notlar

- Ortak kod `app/Domain/Support/Payments`: `IyzicoApi` (imza ve istekler), `Iban`, `BankAccounts`, `TurkishBanks`. Mağaza ve B2B kendi anahtar ve ayarlarıyla kullanır; mağaza ile B2B arasında kod paylaşımı yasağı korunur.
- B2B: `app/Domain/Payments` (`PaymentSettings`, `OrderPaymentService`), `order_payments` tablosu, `orders.payment_method / payment_status / paid_at / delivery_fee / requested_total`, `dealer_relationships.allow_open_account / allow_on_delivery`, `business_settings` ödeme alanları.
- iyzico geri dönüşü `POST /bayi/odeme/iyzico/donus`: CSRF ve **oturum açılmaz** (çerezsiz istekte yeni oturum çerezi bayinin açık oturumunu düşürürdü). İşletme bağlamı belirtecin kayıtlı ödemesinden kurulur, sonuç iyzico'dan sorgulanır; bayi `?odeme=sonuc` ile sipariş ekranına döner, mesaj siparişin gerçek ödeme durumuna göre gösterilir.
- `b2b:expire-pending-payments` (5 dakikada bir): 30 dakikayı geçen başlatılmış kart ödemelerini sorgulayıp sonuçlandırır; sipariş ödeme beklemeye devam eder.
- iyzico bildirim adresi (webhook) mağazanınkidir; B2B ödemeleri geri dönüş ve zamanlanmış görevle sonuçlanır.
- Testler: `tests/Feature/Ordering/DealerPaymentTest.php`.

## Canlıya alırken

1. `php artisan migrate`
2. Toptancı → Ödeme Ayarları: iyzico anahtarları (önce sandbox), taksitler, havale hesapları; sonra "Bayi siparişlerinde ödeme alınsın".
3. Açık hesap / teslimde ödeme verilecek bayileri bayi kartından işaretleyin.
4. iyzico başvurusu mağaza alan adı içindi; bayi panelinden de ödeme alınacağını iyzico'ya bildirin.
