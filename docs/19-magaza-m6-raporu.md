# Mağaza M6 — Ödeme, Siparişler, iyzico ve Eskişehir İçi Kapıda Ödeme

> Testler: 322/322 (eşzamanlılık 11/11). Mimari: [13-magaza-mimarisi.md](13-magaza-mimarisi.md) §8–10.

## 1. Ödeme sayfası (`/odeme`)

- Sırasıyla dört adım: iletişim, teslimat adresi, teslimat yöntemi, ödeme yöntemi. Fatura bilgisi bireysel (T.C. kimlik no isteğe bağlı) veya kurumsal (unvan, vergi dairesi, vergi no). Fatura adresi ayrı girilebilir.
- Üye müşteri kayıtlı adresini seçebilir.
- Sağdaki özet tarayıcıda yalnızca **önizleme** olarak hesaplanır. Sipariş tutarı, kargo, kapıda ödeme ücreti ve KDV **sunucuda yeniden hesaplanır**; formdan tutar alınmaz.
- Sepette stoğu düşen ya da satıştan kalkan satır varsa ödeme açılmaz; müşteri sepete yönlendirilir.
- Hatalar tek seferde birlikte gösterilir (alanlar, yasal onay, not).
- **Yasal onay:** Ön Bilgilendirme Formu ve Mesafeli Satış Sözleşmesi okunup onaylanmadan sipariş verilemez. Onay anında metinler sipariş bilgileriyle doldurulur ve sürüm numarasıyla birlikte `store_order_consents` tablosuna kaydedilir (IP ve tarayıcı bilgisiyle).

## 2. Teslimat

| Yöntem | Nerede | Ücret |
|---|---|---|
| Kargo | Tüm Türkiye | `Ödeme ve Teslimat` ayarındaki kargo ücreti; belirlenen tutarın üzerinde ücretsiz |
| Mağaza teslimatı (kendi aracımızla) | Yalnızca Eskişehir; ayarda seçilen ilçeler (boşsa tümü) | Ayardaki sabit ücret (0 olabilir) |

- Kargo firması entegrasyonu M7'dedir; şimdilik yönetici gönderide firma adını ve takip numarasını elle girer.

## 3. Ödeme yöntemleri

### Kartla ödeme (iyzico Ödeme Formu)

- Sipariş "ödeme bekliyor" durumunda açılır; ürünler **20 dakika** ayrılır (stoktan düşülmez).
- Müşteri iyzico'nun ödeme sayfasına yönlenir; kart bilgisi bizim sunucumuzdan geçmez. 3D Secure ve taksit iyzico tarafındadır.
- Dönüşte (`/odeme/iyzico/donus`) sonuç **iyzico'dan sorgulanır**; tarayıcıdan gelen sonuca güvenilmez. Denetlenenler:
  - işlem durumu ve ödeme durumu,
  - sepet kimliği (sipariş),
  - ödenen tutar = sipariş tutarı,
  - dolandırıcılık kontrolü sonucu.
- Başarılı ödemede ayırma satışa çevrilir, stok düşer, sepet temizlenir. Ödeme kaydı tekrar işlenmez (aynı dönüş iki kez gelirse ikinci etkisizdir).
- Ödeme alındığı hâlde stok yetmiyorsa (ayırma süresi dolmuş ve başkası almışsa) sipariş yine ödenmiş sayılır. Yöneticiye "ödeme alındı, stok yetersiz" uyarısı düşer.
- Başarısız ödemede ayırma kalkar; sipariş "ödeme başarısız" olur.
- `store:expire-pending-payments` komutu beş dakikada bir çalışır. Süresi dolan ödemeleri iyzico'dan son kez sorgular, ödenmemişse siparişi kapatıp ayırmayı bırakır.
- API anahtarları **şifreli** saklanır; ekranda ve işlem geçmişinde görünmez. Boş bırakılan anahtar alanı mevcut değeri korur.
- Kart ödemesi, ayarda açık ve iki anahtar da girilmişse görünür.

### Kapıda ödeme (yalnızca Eskişehir, kendi aracımızla)

- Koşullar (hepsi `Ödeme ve Teslimat` ekranından değiştirilebilir):
  - mağaza teslimatı seçilmiş ve ilçe teslimat bölgesinde,
  - tutar alt ve üst sınır arasında (varsayılan üst sınır 3.000 ₺),
  - telefon engel listesinde değil,
  - aynı telefonda teslim edilmemiş kapıda ödemeli sipariş sayısı sınırın altında (varsayılan 1).
- İsteğe bağlı kapıda ödeme ücreti eklenebilir.
- **SMS doğrulaması:** Sipariş vermeden önce telefona 6 haneli kod gider.
  - Kod 3 dakika geçerlidir, en fazla 5 deneme hakkı vardır.
  - Yeniden gönderim için 60 saniye beklenir.
  - Telefon başına günde 5, IP başına saatte 10 kod gönderilebilir.
  - Kod veritabanında özetlenmiş (HMAC) tutulur.
- Kod beklenirken sepet, fiyat veya kapıda ödeme koşulları değiştiyse sipariş oluşmaz; müşteri sepete döner.
- Sipariş oluşunca stok **hemen düşer**.
- **İlk sipariş teyidi** (ayardan kapatılabilir): Teslim edilmiş kapıda ödemeli siparişi olmayan numaranın siparişi "onay bekliyor" durumunda açılır. Yönetici müşteriyi arayıp teyit eder.
- Teslimatta tahsilat türü (nakit / kart) seçilmeden "teslim edildi" işaretlenemez.
- **Teslim alınmadı:** Ürünler stoğa döner ve telefon engel listesine girer. Engel yönetimden kaldırılabilir; kaldırma işlem geçmişine yazılır.

## 4. SMS altyapısı (Netgsm)

- `SmsGateway` iki sürücülüdür:
  - `log`: SMS gönderilmez, kod uygulama günlüğüne yazılır (geliştirme). Doğrulama ekranında bu durum belirtilir.
  - `netgsm`: Netgsm HTTP API'si; kullanıcı kodu, şifre (şifreli saklanır) ve onaylı başlık gerekir.
- Gönderilen her mesaj `store_sms_messages` tablosuna sağlayıcı yanıtıyla kaydedilir. Kodun kendisi kaydedilmez.
- **Canlı ortamda** gerçek SMS sürücüsü açık değilse kapıda ödeme etkinleştirilemez.

## 5. Siparişler

- Numara `YB100001` biçimindedir. Müşteriye özel takip bağlantısı (`/siparis/{no}/{belirteç}`) ile üye olmayan müşteri de siparişini görür. Üye müşteri `Hesabım → Siparişlerim`'den takip eder.
- Sipariş satırları ürün adı, SKU, birim fiyat ve KDV oranı ile **anlık görüntü** olarak saklanır; sonradan ürün değişse de sipariş değişmez.
- Durumlar: ödeme bekliyor → (ödeme başarısız) / onay bekliyor → hazırlanıyor → yolda → teslim edildi; ayrıca teslim alınmadı ve iptal.
- Her durum değişikliği `store_order_events` tablosuna kimin yaptığıyla yazılır ve sipariş sayfasında zaman çizelgesi olarak görünür.
- İşlemler tek tek kilitlenerek yapılır; aynı işlem iki kez uygulanamaz.
- **İptal:** Yola çıkmadan önce, gerekçe zorunlu; ürünler stoğa döner. Kartla ödenmiş sipariş, iyzico iadesi M7'de geleceği için şimdilik iptal edilemez.
- **E-postalar:** Sipariş alındı, yola çıktı ve iptal edildi bildirimleri sistem posta hesabından gider.

## 6. Mağaza yönetimi

- **Satış → Siparişler:** Arama (numara, ad, e-posta, telefon), durum, ödeme türü ve tarih filtreleri. Menüde yeni sipariş sayısı rozetle gösterilir. Ayrıntı ekranında durum işlemleri, ürünler, tutarlar, adresler, fatura bilgisi, onaylanan yasal metinler ve zaman çizelgesi bulunur.
- **Ayarlar → Ödeme ve Teslimat:** Kargo ücreti ve ücretsiz kargo sınırı, mağaza teslimatı ve ilçeleri, kapıda ödeme koşulları, iyzico (deneme / canlı) anahtarları, SMS sürücüsü ve Netgsm bilgileri. Şifreli anahtarlar ayrıca silinebilir.
- **Ayarlar → Yasal Metinler:** Ön Bilgilendirme Formu, Mesafeli Satış Sözleşmesi, İptal ve İade Koşulları, KVKK Aydınlatma Metni, Gizlilik ve Çerez Politikası.
  - Şablonlar `{ALICI_AD}`, `{SATICI_UNVAN}`, `{URUNLER}` gibi alanlarla doldurulur.
  - Her kayıt **yeni sürüm** olarak yayımlanır; eski sürümler ve onlara verilmiş onaylar korunur.
  - Hazır şablonlar başlangıç içindir; yayına çıkmadan önce hukuki olarak gözden geçirilmelidir.
- **Mağaza Ayarları → Satıcı bilgileri:** Unvan, vergi dairesi, vergi no ve MERSİS no. Yasal metinlerde ve sipariş belgelerinde kullanılır.
- İşlem geçmişine sipariş, yasal metin ve ödeme ayarı kayıtları eklendi. Gizli değerler kayıtlara yazılmaz; yalnızca hangi ayarın değiştiği yazılır.

## 7. Güvenlik notları

- iyzico dönüş adresi başka siteden POST aldığı için CSRF denetimi dışındadır. Bu adres hiçbir değeri formdan kabul etmez; yalnızca belirteçle iyzico'ya sorgu yapar.
- Takip bağlantısındaki belirteç sabit süreli karşılaştırmayla denetlenir; yanlış belirteç 404 döner.
- Müşteri başka müşterinin siparişini göremez; mağaza yönetimi sayfaları müşteri ve B2B oturumlarına kapalıdır.

## 8. Yayına almadan önce yapılacaklar

1. **iyzico:** Başvuru onaylanınca canlı API anahtarları `Ödeme ve Teslimat` ekranına girilir ve mod "canlı" yapılır. Deneme için iyzico sandbox anahtarları kullanılabilir.
2. **Netgsm:** Hesap açılınca kullanıcı kodu, şifre ve başlık girilir; SMS sürücüsü `netgsm` yapılır. Canlıda kapıda ödeme ancak bundan sonra açılabilir.
3. Satıcı bilgileri doldurulur, yasal metinler gözden geçirilip yayımlanır.
4. Zamanlanmış görevler (`schedule:run`) sunucuda çalışmalıdır; süresi dolan kart ödemeleri bununla kapanır.

## 9. Sonraki aşama (M7)

Kargo firması entegrasyonu (gönderi oluşturma, takip), iade talepleri ve iyzico üzerinden para iadesi (kartla ödenmiş siparişlerin iptali dahil), e-Arşiv faturası (Uyumsoft).
