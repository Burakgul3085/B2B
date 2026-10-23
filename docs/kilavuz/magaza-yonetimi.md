# Mağaza yönetimi kullanım kılavuzu

Mağaza yönetimi, perakende sitenin (Yapboz Kırtasiye mağazası) yönetildiği paneldir. Kendi alt alan adında açılır (ör. `https://yonetim.<alan adı>`). Mağazanın ürünleri, stoğu, müşterileri ve siparişleri B2B'den tamamen ayrıdır: toptancı panelindeki ürün veya stok burada görünmez.

## 1. Yöneticiler ve giriş

- İlk **süper admin** hesabını platform yöneticisi açar (Platform → Mağaza Yöneticileri).
- Süper admin ilk girişte **iki adımlı doğrulamayı** (Google Authenticator vb.) kurmak zorundadır.
- Süper admin **Yönetim → Yöneticiler**'den başka yöneticiler açar. Yönetici sayısı sınırsızdır.
- Yöneticiler birbirini veya süper admini silemez; yöneticiyi yalnızca süper admin kaldırır.
- **Yönetim → İşlem Geçmişi:** Kimin ne zaman ne yaptığı (sipariş işlemleri, ayar değişiklikleri, stok düzeltmeleri…).
- **Profil ve Güvenlik:** Şifre ve iki adımlı doğrulama.

## 2. Yayına hazırlık

Mağaza varsayılan olarak **kapalıdır**; ziyaretçiler "Çok yakında" sayfasını görür. Önerilen sıra:

1. **Ayarlar → Mağaza Ayarları:**
   - Satıcı bilgileri: unvan, vergi dairesi ve numarası, MERSİS, adres. Bu bilgiler yasal metinlere ve faturalara yansır.
   - İletişim bilgileri, duyuru şeridi, ana sayfa SEO metni.
   - Yönetici bildirimleri: yeni sipariş, iade, düşük stok e-postaları ve bunların gideceği adresler.
   - Ödeme logo bandı.
2. **Ayarlar → Yasal Metinler:** Ön Bilgilendirme Formu, Mesafeli Satış Sözleşmesi, İptal-İade, KVKK, Gizlilik ve Çerez Politikası. Metinler başlangıç şablonudur, **hukukçuya kontrol ettirin**. Her kayıt yeni bir sürüm oluşturur; müşterinin hangi sürümü onayladığı siparişte saklanır.
3. **Ayarlar → Ödeme ve Teslimat** (bkz. §4) ve **Ayarlar → Kargo Firmaları:** Takip bağlantısı şablonu, ör. `https://.../sorgula?no={takip}`.
4. **Vitrin → Banner'lar**, **Vitrin → Hakkımızda** ve **Vitrin → İletişim** (bkz. §9).
5. Katalog ve stok (§3).
6. **Ayarlar → Yayın Hazırlığı:** Eksik maddeleri tek ekranda gösterir. Hepsi tamamsa **Mağaza Ayarları → Yayın durumu → Mağazayı aç**.

Mağaza kapalıyken **Mağaza Ayarları → Önizleme** bağlantısıyla siteyi müşteri gibi gezebilirsiniz.

## 3. Katalog ve stok

- **Katalog → Kategoriler:** Ağaç yapısında kategoriler. ↑ / ↓ düğmeleriyle sıralanır.
- **Katalog → Markalar:** Logolu markalar.
- **Katalog → Ürünler:**
  - Ürün grubunun açıklaması, SEO alanları ve görselleri tanımlanır.
  - Grubun altındaki **seçenekler** (renk, boy…) satılan birimlerdir. Her seçeneğin kodu (SKU), barkodu, **KDV dahil** fiyatı, üstü çizili fiyatı ve KDV oranı vardır.
  - Toplu ekleme ve güncelleme için **Excel ile içe aktarma** kullanılabilir. Önce şablonu indirin.
- **Stok → Stok Durumu:** Satılabilir miktar, stoktan ödeme aşamasında ayrılan miktar düşülerek gösterilir. Buradan giriş, sayım ve düzeltme yapılır. Kritik seviyenin altındaki ürünler işaretlidir.
- **Stok → Stok Hareketleri:** Bütün hareketler. Hiçbir hareket silinmez; yanlış giriş ters kayıtla geri alınır.

## 4. Ödeme ve teslimat ayarları

**Ayarlar → Ödeme ve Teslimat** (tutarlar KDV dahil):

| Bölüm | Ayar |
|---|---|
| Kargo | Sabit kargo ücreti ve **ücretsiz kargo sınırı**. Sınırın üzerindeki siparişlerde kargo ücretsizdir. **Aynı gün kargo saati** doluysa ürün sayfasında "Hafta içi saat … öncesi verilen siparişler aynı gün kargoya verilir" yazar; boşsa bu söz verilmez. |
| Mağaza teslimatı (Eskişehir) | Kendi aracınızla teslimat: ücret ve teslimat yapılan ilçeler. |
| Kapıda ödeme | Yalnızca Eskişehir mağaza teslimatında. En düşük/en yüksek tutar, hizmet bedeli, aynı telefonda en fazla açık sipariş, ilk siparişte telefonla teyit. Müşteri siparişi SMS koduyla doğrular. |
| Kartla ödeme (iyzico) | API anahtarları, test/canlı ortam. Bildirim adresini (webhook) iyzico panelinde Ayarlar → Bildirim Ayarları'na girin. |
| Havale / EFT | İsteğe bağlı. Banka hesapları (her satırda `Banka \| Hesap sahibi \| IBAN`) ve ödeme süresi (1–7 gün). |
| SMS (Netgsm) | Kullanıcı kodu, şifre, onaylı başlık. Canlıda Netgsm bilgileri girilmeden kapıda ödeme açılamaz. |
| İade | İade süresi (en az 14 gün) ve iade gönderim adresi. |

Aynı sayfanın altında **kapıda ödeme engel listesi** ve son SMS'ler görünür. Engel kaldırmak mümkündür.

## 5. Siparişler

**Satış → Siparişler:** "Yeni (işlem bekleyen)" sekmesi sizden işlem bekleyen siparişleri gösterir. Menüdeki rakam bekleyen sipariş sayısıdır.

| Ödeme | Akış |
|---|---|
| Kart | Ödeme iyzico'da alınır → **Ödendi** → Hazırlanıyor → Kargoya verildi / Yola çıktı → Teslim edildi. Ödeme 30 dakika içinde tamamlanmazsa sipariş kendiliğinden kapanır, stok serbest kalır. |
| Kapıda ödeme | İlk siparişte **Onay bekliyor**: müşteriyi arayıp "Teyit edildi, onayla". Sonra Hazırlanıyor → Yola çıktı → **Teslim edildi** (tahsilat türü: nakit veya kapıda kart). |
| Havale / EFT | **Havale bekleniyor**: müşteri sipariş numarasını açıklamaya yazarak ödeme yapar. Para hesaba geçince **"Havale alındı"** deyin; sipariş hazırlanmaya geçer. Süresinde ödenmeyen sipariş kendiliğinden iptal edilir, ürünler stoğa döner. |

- **Kargo:** Kargo firması ve takip numarası girilir; müşteriye takip bağlantılı e-posta gider.
- **Teslim alınmadı** (kapıda ödeme): Ürünler stoğa döner. Müşterinin telefonu ve teslimat adresi kapıda ödeme engel listesine girer; bu kişi bundan sonra yalnızca kartla ödeyebilir.
- **İptal:** Yola çıkmadan yapılabilir.
  - Kartla ödenmiş siparişin tutarı iptalle birlikte iyzico üzerinden karta iade edilir.
  - Havalesi alınmış siparişte önce tutarı müşterinin hesabına gönderip **Para iadesi** ile elle kaydedin, sonra iptal edin.
- **Fatura:** Şimdilik e-Arşiv faturası Uyumsoft'ta kesilir. Fatura numarası ve PDF siparişe eklenir; müşteri sipariş sayfasından indirir. İade faturası da eklenebilir.
- **Para iadesi (ürün iadesi olmadan):** Eksik gönderim, hasar indirimi vb. Kartta iyzico'dan karta; kapıda ödeme ve havalede müşterinin hesabına havale edip elle kaydedilir.

## 6. İadeler (cayma hakkı)

Müşteri teslimden sonra iade süresi içinde sipariş sayfasından iade talebi açar. Telefonla gelen istekler için siparişte "Müşteri adına iade talebi aç" kullanılabilir.

**Satış → İadeler:** Talep alındı → Onaylandı, ürün bekleniyor → Ürün teslim alındı (ürünler stoğa döner) → **Para iadesi yapıldı**. Talebi gerekçeyle reddedebilirsiniz.

İade tutarı kartla ödemede karta gider. Kapıda ödeme ve havalede müşterinin bildirdiği IBAN'a havale edilip elle kaydedilir.

## 7. Kampanya, kupon ve raporlar

- **Satış → Kampanya ve Kuponlar:**
  - Türler: yüzde indirim, tutar indirimi, ücretsiz teslimat.
  - **Kupon:** Müşteri kodu sepette girer. Toplu tek kullanımlık kodlar üretilip CSV olarak indirilebilir.
  - **Kampanya:** Koşulları tutan sepete kendiliğinden uygulanır.
  - Koşullar: tarih aralığı, en düşük sepet tutarı, en yüksek indirim, toplam ve kişi başı kullanım sınırı, yalnızca ilk sipariş, belirli kategoriler veya ürün kodları.
  - Bir siparişte tek indirim geçerlidir.
- **Satış → Raporlar:** Tarih aralığına göre ciro, indirim, iade, günlük satış, ödeme ve teslimat yöntemine göre dağılım. Siparişler Excel olarak alınabilir.
- **Genel Bakış:** Bugünün ve ayın özeti; onay bekleyen, havale bekleyen, hazırlanacak ve yoldaki siparişler; iade talepleri.

### Yorumlar (Katalog → Yorumlar)

- Yalnızca ürünü **teslim almış üyeler** yorum yazabilir; her ürüne bir yorum. Yorum siz **Yayımla** diyene kadar mağazada görünmez.
- İsterseniz **Mağaza yanıtı** yazın; yorumun altında görünür. Uygunsuz yorumu **Yayımlama** ile gizleyin veya silin.
- Ürün kartlarındaki ve ürün sayfasındaki yıldızlar yalnızca yayındaki yorumlardan hesaplanır.

### Stoğa gelince haber ver

Tükenen ürünün sayfasında ziyaretçi e-posta bırakabilir. Ürünü stoğa girdiğinizde (stok girişi veya sayım) en geç 10 dakika içinde kişiye bir kez e-posta gider ve adres silinir. Sistem e-postası ayarlı olmalıdır.

### Blog (Vitrin → Blog)

- Sağdaki kutudan **kategorileri** yazın (ör. Okul, Ofis, Sanat). Adını silip kaydettiğiniz kategori kaldırılır; yazıları kategorisiz kalır.
- **Yeni yazı:** başlık, kısa özet, yazı metni (ara başlık için `## `, madde için `- `), kapak fotoğrafı ve isterseniz yazının altında gösterilecek ürünlerin stok kodları.
- **Yayında** açıkken yazı kaydettiğiniz an yayımlanır; **Yayın tarihi**ne ileri bir tarih yazarsanız o an yayına girer (listede "Zamanlandı").
- Yayında yazı olduğunda üst menüde ve alt bilgide "Blog" bağlantısı, ana sayfada "Blogdan" bölümü kendiliğinden görünür.

## 8. Müşteri hesapları ve KVKK

Müşteri üye olarak veya misafir olarak sipariş verebilir. Üye, hesabından siparişlerini, adreslerini ve iadelerini yönetir. Hesabını kapatabilir; bu durumda kişisel bilgileri anonimleştirilir, yasal saklama gereği sipariş kayıtları korunur. Çerez tercihleri sitedeki çerez bandından yönetilir.

## 9. Hakkımızda, İletişim ve Mesajlar

Serbest sayfa oluşturma yoktur. Her sabit sayfanın kendi düzenleme ekranı vardır; yeni bir sayfa gerekirse yazılım tarafında eklenir. Sayfalar örnek metinle ve **yayında kapalı** başlar; **Önizle** ile yayına almadan görülebilir.

- **Vitrin → Hakkımızda:**
    - başlık, slogan, kapak fotoğrafı
    - hikâye metni (kalın, ara başlık, madde, bağlantı düğmeleri)
    - en fazla 4 rakam (ör. 1998 · Kuruluş)
    - en fazla 4 "Neden biz" kartı (simge, başlık, kısa metin)
    - en fazla 6 fotoğraflık galeri
    - sayfa sonu "Ürünlere göz atın" düğmesi
    - arama motoru başlığı ve açıklaması

    Boş bırakılan bölüm sayfada görünmez.
- **Vitrin → İletişim:** Telefon, WhatsApp, e-posta, adres, sosyal medya ve satıcı bilgileri **Mağaza Ayarları**'ndan gelir; burada tekrar girilmez. Bu ekranda şunlar ayarlanır:
    - giriş metni
    - günlük çalışma saatleri (sayfada "Şu an açık / kapalı" rozeti)
    - harita
    - iletişim formunun açık olup olmadığı
- **Harita:** Google Haritalar'da dükkânı açın, **Paylaş → Bağlantıyı kopyala** deyip bağlantıyı yapıştırın. Sistem konumu bulur, haritayı ve "Yol tarifi al" düğmesini oluşturur. Konum bulunamazsa **Paylaş → Harita yerleştir → HTML'yi kopyala** kodunu yapıştırın. Harita, Gizlilik ve Çerez Politikası'nda belirtildiği gibi Google'dan yüklenir.
- **Satış → Mesajlar:** İletişim formundan gelen mesajlar burada toplanır; menüdeki rakam yeni mesaj sayısıdır.
    - Mesaj açılınca "Okundu" olur.
    - Yanıtı e-posta, telefon veya WhatsApp ile verdikten sonra **Yanıtlandı olarak işaretle** deyin.
    - Kişi verilerinin silinmesini isterse mesajı silebilirsiniz.
    - Yeni mesaj bildirimi, Mağaza Ayarları → Yönetici bildirimleri bölümünden açılıp kapatılır; bildirimin gitmesi için sistem e-postası tanımlı olmalıdır.

## 10. Alt bilgi

**Vitrin → Alt Bilgi** her sayfanın altındaki bölümü düzenler:

- güven şeridi (en fazla 4 madde: simge, başlık, alt yazı)
- logonun altındaki yazı ve tanıtım metni
- sütun başlıkları
- kaç ana kategori listeleneceği
- "Haritada gör" kartı, çalışma saatleri, "Bayi girişi" bağlantısı ve sağ alttaki WhatsApp düğmesinin gösterilip gösterilmeyeceği
- en alttaki not

Telefon, e-posta, adres ve sosyal medya Mağaza Ayarları'ndan, çalışma saatleri İletişim sayfasından, sayfa bağlantıları yayındaki sayfalardan gelir. Ödeme logo bandı Mağaza Ayarları'nda yüklenir.
