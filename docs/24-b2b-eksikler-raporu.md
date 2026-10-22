# B2B Eksiklerinin Tamamlanması

> Testler: 357/357, eşzamanlılık 12/12. Sunucu / yayın (Aşama 10) ve mağaza M10 kapsam dışıdır.

## 1. Hata kayıtları (Platform → Hata Kayıtları)
- Raporlanan her hata (doğrulama, 404, yetki gibi beklenenler hariç) `error_events` tablosuna toplanır; aynı sınıf + dosya + satır tek kayıttır, tekrar ettikçe sayaç artar.
- Kişisel veri yazılmaz: veritabanı hatalarında SQL (değerleriyle) kesilir; adres yalnızca yol (sorgu dizesi yok); dosya yolu proje köküne göreli.
- Açık / Çözüldü filtresi, arama, "Çözüldü" işareti (hata tekrarlarsa yeniden açılır), 30 günden eski çözülmüşleri temizleme. Menüde açık hata sayısı rozeti.

## 2. Yedekler (Platform → Yedekler)
- Tek zip: `veritabani.sql.gz` (mysqldump; `--single-transaction`, parola komut satırına değil ortam değişkenine verilir) + `dosyalar/` (yüklenen tüm dosyalar; yedekler klasörü hariç).
- Her gece 03:30 (`backup:run`, zamanlanmış görev); son `BACKUP_KEEP` (varsayılan 14) yedek saklanır. "Şimdi yedek al" kuyrukta çalışır. İndirme ve silme denetim kaydına yazılır.
- Yerelde gerçek yedek alındı (102 tablo, 53 dosya) ve test veritabanına geri yüklenerek sipariş/bayi/ürün/cari hareket/yasal metin sayılarının birebir aynı olduğu doğrulandı.
- **Geri yükleme:** zip açılır, `veritabani.sql.gz` MySQL'e yüklenir (`gunzip -c veritabani.sql.gz | mysql veritabani`), `dosyalar/` içeriği `storage/app/private/` altına kopyalanır.
- Yerelde `.env`: `BACKUP_MYSQLDUMP="C:/tools/mysql84/bin/mysqldump.exe"`; sunucuda mysqldump PATH'te. Sunucu kaybına karşı yedeklerin düzenli olarak dışarı alınması gerekir (Aşama 10'da uzak depolama değerlendirilecek).

## 3. Destek erişimi
- **Toptancı → Ayarlar → Destek Erişimi:** belirli bir platform yöneticisine 1 saat / 4 saat / 24 saat / 3 gün salt okunur erişim (neden zorunlu); aynı yöneticiye aynı anda tek etkin erişim; istendiği an kaldırma. Yöneticiye e-posta gider.
- **Platform → İşletme → Destek görünümü:** son 50 sipariş, 100 bayi, 100 ürün (salt okunur). Erişim yoksa veya süresi dolduysa 403.
- Her görüntüleme (bölüm adıyla) işletmenin işlem geçmişine yazılır ve toptancı "Destek kayıtları"nda görür.

## 4. Bayilik başvurusuna web üzerinden ek bilgi yanıtı
- Toptancı ek bilgi istediğinde başvurana giden e-postada **"Bilgileri gönder"** bağlantısı olur (14 gün geçerli, tek kullanımlık; veritabanında yalnızca özeti tutulur).
- Başvuran giriş yapmadan açıklama ve en fazla 3 belge (PDF/JPG/PNG, 5 MB) gönderir; başvuru "İncelemede"ye döner, toptancıya bildirim + e-posta (`application_info_replied` şablonu) gider; yanıt ve belgeler başvuru ekranında görünür.
- Belirteç alanları formdan toplu atanamaz (yalnızca servis yazar).

## 5. Kayıtlı rapor filtreleri
- Rapor ekranında **"Bu filtreleri kaydet"** (ad): filtreler, sıralama ve sütunlar raporun kendi kurallarıyla doğrulanıp kaydedilir; sayfa numarası ve bilinmeyen alanlar atılır. Kullanıcı başına en fazla 30.
- Raporlar sayfasında "Kayıtlı raporlarım" ve rapor ekranında hızlı bağlantılar; yalnızca sahibi görür/siler (toptancı ve bayi ayrı; işletme kapsamı).

## 6. Koyu tema
- Yönetim panellerinde (platform, toptancı, bayi, mağaza yönetimi) kullanıcı menüsünde **Tema: Açık / Koyu / Sistem**. Tercih `tema` çerezinde 1 yıl; sayfa sunucudan doğru sınıfla gelir (yanıp sönme olmaz), "Sistem" tarayıcı ayarını izler.
- Tasarım token'ları koyu değerlerle değişir; şablonlarda doğrudan kullanılan sık renk sınıfları (beyaz kart, açık gri zeminler, durum renkleri, form alanları) ayrıca karşılanır. Grafikler açılıştaki temaya göre renklenir. Ürün görselleri beyaz zeminde kalır.
- Mağaza vitrini (müşteri tarafı) açık temada kalır.

## 7. Teknik borçlar
- **Cari yaşlandırma:** Aşama 6 notu eskimişti — liste, gösterge paneli, rapor ve hatırlatma zaten tek SQL pencere fonksiyonu (`agingMany`) kullanıyor; tek hesap ekranları yalnızca o hesabın hareketlerini okur. Kapatıldı.
- **ffmpeg:** Demo vitrin videoları Python ile üretiliyor (`demo:videolar`); ffmpeg gerekmiyor. Kapatıldı.
- **CSP `'unsafe-eval'` — kaldırıldı.** İlk denemede bırakılmıştı; ayrı bir çalışmada Alpine'in CSP sürümüne (`@alpinejs/csp` 3.17.4) geçildi ve politika `script-src 'self'` oldu. CSP ayrıştırıcısının desteklemedikleri (birden fazla deyim, ok fonksiyonu, `?.`, `??`, `typeof`, `new`, şablon dizgesi, x-data içinde metot, genel nesneler `JSON`/`Math`/`Number`…) şablonlardan çıkarıldı:
  - Mantık içeren davranışlar `resources/js/components.js` içinde `Alpine.data` bileşeni oldu (dosya listesi, bağlantı kopyalama, ürün seçici, marka arama, görsel yakınlaştırma, miktar adımlayıcı, adres formu, mağaza ürün varyantları, banner döngüsü, vitrin slayt formu); şablonda yalnızca `x-data="ad(...)"` ve metot çağrısı kalır.
  - Sunucu verisi `@alpine($deger)` yönergesiyle HTML varlıklarıyla kaçırılmış JSON nesne/dizi sabiti olarak basılır (`@js()` dizileri `JSON.parse(...)` ile sardığı için CSP sürümünde çalışmaz). Blade bileşen etiketlerinin (`<x-card …>`) özniteliklerinde yönerge derlenmez; orada x-data içteki HTML öğesine konur.
  - Denetim: `npm run denetim:alpine` (`scripts/alpine-csp-denetim.mjs`) bütün Blade şablonlarındaki Alpine ifadelerini paketin kendi ayrıştırıcısıyla ayrıştırır; genel nesne kullanımı, DOM öğesine atama, `@js`/`Js::from` ve bileşen etiketinde `@alpine` hata sayılır.
  - Platform, toptancı, bayi, mağaza yönetimi ve vitrinin bütün GET sayfaları tarayıcıda konsol hatası olmadan açıldı; ödeme, sepet, ürün seçici, bayi kataloğu, rapor filtreleri, tema seçici ve çerez bandı tıklanarak denendi.

## 8. Testler
- `B2bLeftoversTest` (6 test): hata kayıtları, yedekler (sahte mysqldump), destek erişimi, başvuru yanıtı, kayıtlı raporlar, koyu tema çerezi.
- Güvenlik taramaları (işletme izolasyonu, oturum, bozuk girdi) yeni rotaları da kapsıyor; test dünyasına destek erişimi ve kayıtlı rapor kayıtları eklendi. Bozuk girdi taraması "Şimdi yedek al"ı gerçek yedek üretmesin diye sahteler; tarama bellek sınırı 2 GB'ye çıkarıldı (yüzlerce istek aynı süreçte birikiyor).
