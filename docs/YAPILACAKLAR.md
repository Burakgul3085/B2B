# Yapılmayanlar ve Bekleyenler

> Son güncelleme: 21.10.2026 (B2B eksikleri sonrası). Bir madde tamamlandıkça buradan silinir veya "Tamamlandı" notu düşülür.

## 1. Mağaza: kalan aşamalar

### M7'den kalanlar
M7 tamamlandı (kargo firmaları ve takip bağlantısı, iade talepleri, iyzico ve elle para iadesi, kartlı siparişin iadeli iptali, elle fatura). Kalanlar:
- [ ] **Uyumsoft e-Arşiv sürücüsü.** Uyumsoft API kullanıcısı ve test ortamı gelince yazılacak; şimdilik fatura numarası ve PDF elle ekleniyor.
- [ ] **Otomatik iade faturası.** Elle iade faturası eklenebiliyor; e-Arşiv bağlanınca otomatik oluşturulacak, belge türü mali müşavirle netleştirilecek.
- [ ] **Kargo firması API'si:** Gönderi oluşturma, barkod, durum sorgulama. Anlaşma yapılan firmaya göre; şimdilik firma seçilip takip numarası elle giriliyor.

### M9'dan kalanlar (yayın aşaması)
M9'un kod tarafı tamamlandı (yasal metinler, çerez bandı, bot koruması, KVKK hesap kapatma, yayın hazırlığı ekranı). Yayınla birlikte yapılacaklar:
- [ ] Sunucu, HTTPS, yedekleme ve geri yükleme provası (B2B Aşama 10 ile birlikte).
- [ ] Alan adının bağlanması (`yapbozkirtasiye.com` ve alt alan adları), ardından mağazanın açılması.
- [ ] İşlem e-postası servisi seçimi (sipariş e-postaları için).
- [ ] İsteğe bağlı: Cloudflare Turnstile ile ek bot koruması (anahtar ve CSP ayarı gerekir).
- [ ] Yönetimde **Ayarlar → Yayın Hazırlığı** ekranındaki eksik maddelerin tamamlanması (Hakkımızda ve İletişim sayfaları, ödeme logo bandı, yasal metinlerin gözden geçirilip kaydedilmesi vb.).

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
- **iyzico yalnızca sahte yanıtlarla denendi.** Ödeme ve para iadesi testleri iyzico yanıtlarını taklit ediyor; gerçek sandbox anahtarlarıyla uçtan uca deneme yapılmadı. Özellikle kalem bazında iade (`/payment/refund`) sandbox'ta mutlaka denenmeli.
- **Kargo firmalarının takip bağlantıları boş.** `Ayarlar → Kargo Firmaları` ekranından her firmanın gönderi sorgulama adresi `{takip}` ile girilmeli.
- **Mağaza ziyaretçilere kapalı.** "Yakında" sayfası gösteriliyor; görmek için önizleme bağlantısı gerekir.
- **Geliştirme veritabanında mağaza verisi yok.** Mağaza yöneticisi, ürün ve stok bulunmuyor. Süper admin `Platform → Mağaza Yöneticileri` ekranından açılır, ürünler mağaza panelinden eklenir.
- **İki adımlı doğrulama zorunlu.** Süper admin ilk girişte iki adımlı doğrulamayı kurmak zorundadır.

## 4. B2B: bekleyenler

### Aşama 10 — Sunucu ve yayın (kullanıcı isteğiyle beklemede)
- [ ] HTTPS ve sunucuda geri yükleme provası. (Yedekleme kodu hazır: Platform → Yedekler, her gece 03:30; yerelde geri yükleme doğrulandı.)
- [ ] Yedeklerin sunucu dışına (uzak depolama) kopyalanması.
- [ ] Gerçek Gmail hesabı ve uygulama şifresiyle e-posta gönderiminin uçtan uca denenmesi.
- [ ] Performansın sunucuda yeniden ölçülmesi (geliştirme bilgisayarında ölçüldü).
- [ ] Sunucuda PHP yükleme sınırlarının 64M yapılması (50 MB banner videoları için).
- [ ] Zamanlanmış görevlerin (`schedule:run`) ve kuyruk işçisinin sunucuda sürekli çalışması.
- [ ] Kullanım ve kurulum dokümanları.

### Eksik ekranlar ve özellikler
B2B eksikleri tamamlandı (docs/24-b2b-eksikler-raporu.md): destek erişimi, hata kayıtları, yedekler, başvuruya web üzerinden ek bilgi yanıtı, kayıtlı rapor filtreleri, koyu tema (yönetim panelleri).
- [ ] İsteğe bağlı: mağaza vitrini (müşteri tarafı) için koyu tema.

### Teknik borç
- [ ] **CSP'de `'unsafe-eval'` (bilinçli olarak bırakıldı).** Alpine'in CSP sürümü denendi; birden fazla deyim, ok fonksiyonu, `?.`, `??`, genel nesneler (JSON, Math…) gibi yapıları desteklemediği için 45 şablondaki 300'ü aşkın ifadenin JS bileşenlerine taşınması ve tüm arayüzün yeniden sınanması gerekir. Satır içi script yok ve Blade çıktıyı kaçırdığı için risk düşük; ayrı bir çalışma olarak planlanmalı.
- Cari yaşlandırma (tek SQL sorgusu) ve demo videoları (Python ile üretiliyor) notları eskimişti; kapatıldı.
