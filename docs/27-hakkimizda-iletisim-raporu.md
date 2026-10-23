# Hakkımızda ve İletişim sayfaları, iletişim formu

Mağaza yönetimindeki serbest "Sayfalar" ekranı (Yeni sayfa + Markdown) kaldırıldı. Yerine kodda tanımlı **sabit sayfalar** geldi. Her sayfanın kendi alanları ve düzenleme ekranı var. Yeni bir sayfa gerekirse kodla eklenir (`App\Domain\Store\Content\SitePages::PAGES`).

## Yönetim

| Ekran | İçerik |
|---|---|
| Vitrin → Hakkımızda | Başlık, slogan, kapak fotoğrafı, hikâye (Markdown + biçim düğmeleri), en fazla 4 rakam, en fazla 4 "Neden biz" kartı (simge listesinden), en fazla 6 fotoğraflık galeri, sayfa sonu düğmesi, SEO, Yayında |
| Vitrin → İletişim | Giriş metni, günlük çalışma saatleri ve not, Google Haritalar bağlantısı veya yerleştirme kodu (önizlemeli), iletişim formu açık/kapalı, SEO, Yayında. İletişim ve satıcı bilgileri Mağaza Ayarları'ndan okunur. |
| Satış → Mesajlar | Formdan gelen mesajlar. Menüde yeni mesaj sayısı görünür. Açılınca okundu sayılır; "yanıtlandı" olarak işaretlenir; silinebilir (KVKK). Silme ve yanıtlama işlem geçmişine yazılır. |

Sayfalar örnek metinle ve yayında kapalı başlar. **Önizle**, kaydedilmiş içeriği yönetim adresinde vitrin görünümüyle gösterir; sayfanın yayında olması gerekmez. Yayın Hazırlığı listesi iki sayfanın yayında olup olmadığına bakar.

## Vitrin

- Adresler `/hakkimizda` ve `/iletisim`. Eski `/sayfa/{kısa-ad}` adresleri kalktı.
- Alt bilgideki bağlantılar ve site haritası yalnızca yayındaki sayfaları gösterir. Yayında olmayan sayfa 404 verir.
- **İletişim sayfası:**
  - tıklanabilir kartlar: telefon, WhatsApp, e-posta, Instagram, Facebook
  - adres ve "Yol tarifi al"
  - çalışma saatleri (aynı saatli günler birleştirilir) ve İstanbul saatine göre "Şu an açık / kapalı" rozeti
  - harita
  - satıcı bilgileri (unvan, vergi, MERSİS)
  - form
  - Google için `Store` yapısal verisi (adres, telefon, saatler)
- **Form:** Ad, e-posta veya telefon (en az biri), konu, mesaj alanları var.
  - Bot koruması (`store.bot`) ve istek sınırı (10 dakikada 5) uygulanır.
  - KVKK bilgilendirme bağlantısı formun altında.
  - Yeni mesaj bildirimi, Mağaza Ayarları → Yönetici bildirimleri → "İletişim formu mesajı" ile açılıp kapatılır; e-posta, sistem e-postası tanımlıysa gider.

## Harita

`MapLink` şu girdilerden yerleştirilebilir harita adresi ve yol tarifi bağlantısı üretir:

- Google Haritalar paylaşım bağlantısı (`maps.app.goo.gl`): sunucu yönlendirmeleri izler.
- Adres çubuğundaki `google.com/maps/...` adresi.
- Paylaş → Harita yerleştir kodu.

Güvenlik:

- Yalnızca https ve Google alan adlarına gidilir; her yönlendirme adımı denetlenir. Google dışına giden yönlendirme reddedilir.
- Çerez onay sayfası (`consent.google.com`) atlanır.
- Sayfaya kullanıcının yazdığı HTML konmaz; harita adresini sistem kurar. Yerleştirme kodunda yalnızca `https://www.google.com/maps/embed?pb=…` biçimindeki adres kabul edilir.
- CSP'ye `frame-src https://www.google.com` eklendi.

Kullanıcının kararıyla harita herkese doğrudan gösterilir. Gizlilik ve Çerez Politikası'na Google Haritalar notu eklendi. Bunun için yasal metne yeni bir sürüm ekleyen bir migration yazıldı; metin yönetimde değiştirilmişse migration o metne dokunmaz.

## Veri

- `store_pages` yeniden oluşturuldu: `key` (about | contact), `is_published`, `content` (JSON), SEO alanları. Eski tablo boştu (yerel ve sunucu).
- Yeni tablo `store_contact_messages`.
- Görseller `storage/app/private/store/pages` altında. `/gorsel/sayfa/{dosya}` yalnızca yayındaki sayfaya ait dosyayı sunar.

## Testler

`tests/Feature/Store/StoreSitePagesTest.php` dosyasında 5 test var:

- Hakkımızda: alanlar, görseller, galeriden kaldırma, yayın, önizleme.
- İletişim: saatler, kısa bağlantıyla harita, açık/kapalı rozeti, yapısal veri, CSP.
- Harita: yerleştirme kodu ve reddedilen girdiler (Google dışına yönlendirme dahil).
- Form ve Mesajlar: gönderim, bildirim, okundu, yanıtlandı, silme, kapalı form.
- Yayın Hazırlığı listesi.

Mevcut vitrin testleri yeni adreslere göre güncellendi.

## Ek düzeltme

Kart açıklamalarında ve boş durum metinlerinde tırnaklar `&quot;` olarak görünüyordu (B2B ayarları, vitrin, yedekler, ödeme logo bandı, havale). Düz tırnakla değiştirildi.

## Alt bilgi (ek)

Mağaza alt bilgisi referans tasarıma göre yeniden yapıldı: koyu lacivert zemin ve logodaki turuncu-sarı vurgu. Bölümler:

- güven şeridi
- marka sütunu: logo, logo altı yazı, tanıtım, iletişim, sosyal medya
- Alışveriş sütunu: kategoriler
- Bilgi sütunu: sayfalar, yasal metinler, çerez tercihleri, bayi girişi
- Mağazamız sütunu: "Haritada gör" kartı ve çalışma saatleri
- telif satırı, tema seçici, ödeme logoları ve başa dön düğmesi
- sağ altta WhatsApp düğmesi

Ayarlar yönetimde **Vitrin → Alt Bilgi** ekranındadır (`App\Domain\Store\Content\StoreFooter`, `store_settings.footer`). Tanıtım metni Mağaza Ayarları'ndan bu ekrana taşındı. WhatsApp bağlantıları artık ülke kodunu ekliyor (0532… → 90532…).
