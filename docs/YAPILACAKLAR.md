# Yapılmayanlar ve Bekleyenler

> Son güncelleme: 23.10.2026 (havale / EFT ve kullanım kılavuzları sonrası). Bir madde tamamlandıkça buradan silinir veya "Tamamlandı" notu düşülür.

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
- İsteğe bağlı, **şimdilik yapılmayacak** (karar 2026-10-22): Cloudflare Turnstile ile ek bot koruması. Mevcut bot koruması (gizli alan + süre denetimi + istek sınırı) çalışıyor.
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

- **Ödeme yöntemleri kapalı.** Kapıda ödeme, kartla ödeme ve havale / EFT varsayılan olarak kapalıdır; `Mağaza yönetimi → Ayarlar → Ödeme ve Teslimat` ekranından açılır.
- **SMS gönderilmiyor.** SMS sürücüsü `log` modunda; kodlar `storage/logs/laravel.log` dosyasına yazılır. Canlı ortamda gerçek SMS ayarlanmadan kapıda ödeme açılamaz.
- **iyzico yalnızca sahte yanıtlarla denendi.** Ödeme ve para iadesi testleri iyzico yanıtlarını taklit ediyor; gerçek sandbox anahtarlarıyla uçtan uca deneme yapılmadı. Özellikle kalem bazında iade (`/payment/refund`) sandbox'ta mutlaka denenmeli.
- **Kargo ücreti sabit.** Desiye göre ücret olmayacak (karar 22.10.2026): sabit kargo ücreti ve ücretsiz kargo sınırı, kargo firmasıyla yapılacak anlaşmaya göre Ödeme ve Teslimat ekranından girilir.
- **Kargo firmalarının takip bağlantıları boş.** `Ayarlar → Kargo Firmaları` ekranından her firmanın gönderi sorgulama adresi `{takip}` ile girilmeli.
- **Mağaza ziyaretçilere kapalı.** "Yakında" sayfası gösteriliyor; görmek için önizleme bağlantısı gerekir.
- **Geliştirme veritabanında mağaza verisi yok.** Mağaza yöneticisi, ürün ve stok bulunmuyor. Süper admin `Platform → Mağaza Yöneticileri` ekranından açılır, ürünler mağaza panelinden eklenir.
- **İki adımlı doğrulama zorunlu.** Süper admin ilk girişte iki adımlı doğrulamayı kurmak zorundadır.

## 4. B2B: bekleyenler

### Aşama 10 — Sunucu ve yayın
Sunucu 23.10.2026'da kuruldu (docs/kilavuz/kurulum.md §0): HTTPS, güvenlik duvarı, zamanlanmış görevler, kuyruk işçisi, gece yedeği (sunucuda denendi), `deploy/yayinla.sh` ile yayınlama. Mağaza kapalı ("Çok yakında").
- [x] HTTPS (Let's Encrypt, dört adres).
- [ ] Sunucuda geri yükleme provası.
- [ ] Yedeklerin sunucu dışına (uzak depolama) kopyalanması.
- [ ] Gerçek Gmail hesabı ve uygulama şifresiyle e-posta gönderiminin uçtan uca denenmesi.
- [ ] Performansın sunucuda yeniden ölçülmesi (geliştirme bilgisayarında ölçüldü).
- [x] Sunucuda PHP yükleme sınırlarının 64M yapılması (50 MB banner videoları için).
- [x] Zamanlanmış görevlerin (`schedule:run`, cron) ve kuyruk işçisinin (supervisor) sunucuda sürekli çalışması.
- [ ] Platform yöneticisinin oluşturulması (`platform:create-admin`), ardından toptancı hesabı ve mağaza süper admini.
- [ ] Sistem e-postası (`SYSTEM_MAIL_*`): mağaza e-postaları ve platform parola sıfırlaması için.
- [ ] SSH'de şifreyle girişin kapatılması (yalnızca anahtar), isteğe bağlı.
- [x] Kullanım ve kurulum dokümanları: [kilavuz/](kilavuz/) (toptancı, bayi, mağaza yönetimi, platform, sunucu kurulumu).

### Eksik ekranlar ve özellikler
B2B eksikleri tamamlandı (docs/24-b2b-eksikler-raporu.md): destek erişimi, hata kayıtları, yedekler, başvuruya web üzerinden ek bilgi yanıtı, kayıtlı rapor filtreleri, koyu tema (yönetim panelleri).
- [x] Mağaza vitrini (müşteri tarafı) için koyu tema: üst bilgide ay/güneş düğmesi (açık ↔ koyu); Gizlilik ve Çerez Politikası'na `tema` tercih çerezi eklendi.
- Vitrin yenilemesi (logodaki dört yapboz rengi + lacivert; sahte aciliyet/puan yok, yalnızca gerçek veri):
  - [x] Aşama 1: kimlik, üst şerit, geniş menü, canlı arama, mini sepet, mobil alt menü.
  - [x] Aşama 2: yönetimden düzenlenen ana sayfa blokları, seçkiler, yan banner'lar, ana sayfa kuponları.
  - [x] Aşama 3: ürün kartı (ikinci görsel, indirim/yeni/son N ürün rozetleri), kategori başlığı ve renkli alt kategoriler, "İndirimler" filtresi ve menü bağlantısı, ürün sayfasında teslimat özeti, aynı gün kargo saati (Ödeme ve Teslimat → Kargo), WhatsApp ile sor, paylaş, açıklama/teslimat sekmeleri.
  - [x] Aşama 4: favoriler (üyeler), ürün yorumları (yalnızca ürünü teslim alan üyeler, Katalog → Yorumlar'da onay ve mağaza yanıtı; puan yalnızca onaylı yorumlardan, JSON-LD dahil), stoğa gelince haber ver (`store:stock-alerts`, 10 dakikada bir; e-posta gönderimden sonra silinir, en geç 180 gün), son bakılanlar (oturumda; ürün sayfası ve ana sayfa bloğu). Hesap kapatılınca favori, yorum ve bildirim kayıtları silinir.
  - [ ] KVKK Aydınlatma Metni'ne "stok bildirimi için bırakılan e-posta" ve "ürün yorumları (ad + soyadının baş harfi)" maddelerinin eklenmesi (Ayarlar → Yasal Metinler; mağaza sahibi).
  - [x] Aşama 5: blog (Vitrin → Blog): kategoriler, Markdown yazı, kapak, yazıda geçen ürünler (SKU), ileri tarihli yayın, SEO alanları; /blog, kategori sayfaları, BlogPosting JSON-LD, site haritası, ana sayfa "Blogdan" bloğu; menü ve alt bilgide bağlantı yalnızca yayında yazı varsa.
- Alt bilgi ve e-bülten (2026-10-24):
  - [x] Mağaza e-posta hesabı (SMTP) Mağaza Ayarları'ndan; tüm mağaza e-postaları (sipariş, bildirim, stok, e-bülten) bununla gider, boşsa sistem hesabı.
  - [x] Sosyal medya: Instagram, Facebook, X, YouTube, TikTok, LinkedIn, Pinterest; doldurulanların simgesi alt bilgide.
  - [x] Alt bilgi sütunları yönetimden (Kurumsal / Üyelik / Yardım varsayılan; en fazla 4 sütun, özel adres de eklenebilir).
  - [x] Yardım sayfaları (Vitrin → Yardım Sayfaları): Yardım Merkezi, SSS, Sipariş ve Teslimat, Ödeme Seçenekleri, Kargo Takip, Yasal Haklar.
  - [x] E-bülten: iki adımlı onay, onay metni/tarih/IP kaydı, tek tıkla ayrılma, üyelik izniyle eşitleme, toplu e-posta (parti parti, ilerleme), CSV ve İYS dışa aktarma.
  - [ ] Mağaza e-posta hesabının girilmesi (Mağaza Ayarları → E-posta gönderimi). Girilene kadar alt bilgideki abonelik formu gizlidir.
  - [ ] İYS (iys.org.tr) kaydı ve onayların yüklenmesi; KVKK Aydınlatma Metni'ne e-bülten maddesi (mağaza sahibi).

### Teknik borç
- [x] **CSP'den `'unsafe-eval'` kaldırıldı.** Alpine'in CSP sürümüne (`@alpinejs/csp`) geçildi; mantık içeren ifadeler `resources/js/components.js` bileşenlerine taşındı, sunucu verisi `@alpine()` ile basılıyor. Şablonlar `npm run denetim:alpine` ile CSP ayrıştırıcısına karşı denetleniyor.
- Cari yaşlandırma (tek SQL sorgusu) ve demo videoları (Python ile üretiliyor) notları eskimişti; kapatıldı.
