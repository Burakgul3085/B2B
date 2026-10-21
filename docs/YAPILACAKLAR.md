# Yapılmayanlar ve Bekleyenler

> Son güncelleme: 21.10.2026 (Mağaza M6 sonrası). Bir madde tamamlandıkça buradan silinir veya "Tamamlandı" notu düşülür.

## 1. Mağaza: kalan aşamalar

### M7 — Teslimat, iade ve fatura
- [ ] Kargo firması altyapısı. Firmaya bağlı olmayan bir sürücü katmanı kurulacak, önce elle çalışacak. Şimdilik firma adı ve takip numarası elle giriliyor.
- [ ] Müşteri iade talebi: talep, onay, ürünün geri alınması ve stoğa dönmesi.
- [ ] iyzico üzerinden para iadesi (tam ve kısmi).
- [ ] **Kartla ödenmiş siparişin iptali.** Şu an engelli; para iadesi gelince açılacak.
- [ ] Elle fatura: fatura numarası ve PDF'in siparişe eklenmesi.
- [ ] Uyumsoft e-Arşiv sürücüsü. Test kullanıcısı gelince yazılacak.

### M8 — Kampanya, kupon, rapor
- [ ] Kampanya ve kupon. Sepette kupon alanı yok; altyapı da henüz kurulmadı.
- [ ] Mağaza raporları: satış, ürün, ödeme türü, kapıda ödeme tahsilatları.
- [ ] Yönetici bildirimleri: yeni siparişte e-posta, kritik stok uyarısı.

### M9 — Yasal uyum ve yayın
- [ ] Çerez onay bandı ve KVKK sayfalarının vitrine bağlanması.
- [ ] Bot koruması: üyelik, giriş ve ödeme formları.
- [ ] iyzico başvuru kontrol listesi (sitede bulunması gereken sayfa ve bilgiler).
- [ ] Sunucu, HTTPS, yedekleme ve geri yükleme provası. Bu, B2B'nin Aşama 10'uyla birlikte yapılacak.
- [ ] Alan adının bağlanması (`yapbozkirtasiye.com` ve alt alan adları), ardından mağazanın açılması.
- [ ] İşlem e-postası servisi seçimi (sipariş e-postaları için).

### M10 — Yayın sonrası
- [ ] iyzico canlı anahtarlarının girilmesi ve gerçek kartla deneme.
- [ ] Netgsm hesabının bağlanması ve gerçek SMS ile kapıda ödeme denemesi.
- [ ] Anlaşma yapılırsa kargo firmasının sürücüsü.

## 2. Dışarıdan beklenenler

| Konu | Kimden | Ne için |
|---|---|---|
| `yapbozkirtasiye.com` alan adı | Müşteri | Yayın (M9) |
| iyzico sandbox anahtarları | Müşteri (iyzico sandbox hesabı) | Kart ödemesini gerçek iyzico deneme ortamında uçtan uca denemek |
| iyzico üye işyeri başvurusu ve canlı anahtarlar | Müşteri | Canlı kart ödemesi (yayından sonra) |
| Netgsm kullanıcı kodu, şifre, onaylı mesaj başlığı | Müşteri | Gerçek SMS; canlıda kapıda ödemenin açılması |
| Uyumsoft e-Arşiv API kullanıcısı ve test ortamı | Müşteri / mali müşavir | Otomatik fatura (M7) |
| Satıcı bilgileri (unvan, vergi dairesi, vergi no, MERSİS) | Müşteri | Yasal metinler ve faturalar |
| Yasal metinlerin hukuki kontrolü | Müşteri / hukukçu | Yayından önce. Şu anki metinler başlangıç şablonudur. |
| Kargo firması anlaşması | Müşteri | İsteğe bağlı |
| Sunucu erişimi (Hostinger KVM 2, SSH) | Müşteri | Kurulum ve yayın |

## 3. Mağaza: şu anki durumla ilgili notlar

- **Ödeme yöntemleri kapalı.** Kapıda ödeme ve kartla ödeme varsayılan olarak kapalıdır; `Mağaza yönetimi → Ayarlar → Ödeme ve Teslimat` ekranından açılır.
- **SMS gönderilmiyor.** SMS sürücüsü `log` modunda; kodlar `storage/logs/laravel.log` dosyasına yazılır. Canlı ortamda gerçek SMS ayarlanmadan kapıda ödeme açılamaz.
- **iyzico yalnızca sahte yanıtlarla denendi.** Testler iyzico yanıtlarını taklit ediyor; gerçek sandbox anahtarlarıyla uçtan uca deneme yapılmadı.
- **Mağaza ziyaretçilere kapalı.** "Yakında" sayfası gösteriliyor; görmek için önizleme bağlantısı gerekir.
- **Geliştirme veritabanında mağaza verisi yok.** Mağaza yöneticisi, ürün ve stok bulunmuyor. Süper admin `Platform → Mağaza Yöneticileri` ekranından açılır, ürünler mağaza panelinden eklenir.
- **İki adımlı doğrulama zorunlu.** Süper admin ilk girişte iki adımlı doğrulamayı kurmak zorundadır.

## 4. B2B: bekleyenler

### Aşama 10 — Sunucu ve yayın (kullanıcı isteğiyle beklemede)
- [ ] HTTPS, yedekleme, geri yükleme provası.
- [ ] Gerçek Gmail hesabı ve uygulama şifresiyle e-posta gönderiminin uçtan uca denenmesi.
- [ ] Performansın sunucuda yeniden ölçülmesi (geliştirme bilgisayarında ölçüldü).
- [ ] Sunucuda PHP yükleme sınırlarının 64M yapılması (50 MB banner videoları için).
- [ ] Zamanlanmış görevlerin (`schedule:run`) ve kuyruk işçisinin sunucuda sürekli çalışması.
- [ ] Kullanım ve kurulum dokümanları.

### Eksik ekranlar ve özellikler
- [ ] Destek erişimi (support access) ekranı. Tablosu hazır, arayüzü yok.
- [ ] Platform yönetiminde hata kayıtları ve yedekler ekranları.
- [ ] Bayi başvurusunda ek bilgi istenen kişinin web üzerinden yanıt verebileceği form. Şimdilik e-postayla yanıt veriliyor.
- [ ] Kayıtlı rapor filtreleri ("favori rapor").
- [ ] Koyu tema (uygulama yalnızca açık tema).

### Teknik borç
- [ ] CSP'de `'unsafe-eval'` duruyor. Kaldırmak için Alpine'in CSP sürümüne geçmek ve bütün `x-data` ifadelerini bileşenlere taşımak gerekir. Satır içi script olmadığı için risk düşük.
- [ ] Cari yaşlandırma hesabı tüm hareketleri belleğe alıyor. Yüzlerce bayi için yeterli; binlerce bayide yeniden ele alınmalı.
- [ ] Bu bilgisayarda ffmpeg yok; demo banner'ları yalnızca görsel.
