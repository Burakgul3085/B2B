# Aşama 8 Raporu — Dashboard ve Raporlama

> Durum: **Tamamlandı** · Testler: 174/174 geçti

## Yapılanlar

### Dashboard grafikleri (toptancı)
- Ana sayfada **Satış analizi** bölümü. Filtre çubuğunda dönem (bu ay, son 30 gün, son 90 gün, son 12 ay, özel aralık), bayi ve kategori seçilir. Filtreler göstergelere ve grafiklere birlikte uygulanır.
- Göstergeler: dönem satışı, onaylanan sipariş sayısı, ortalama sipariş tutarı ve dönem tahsilatı. Satış ve sipariş sayısının altında **önceki eşit uzunluktaki döneme göre değişim** yazar.
- Grafikler:
  - **Satış eğrisi:** bu dönem çizgisi ile önceki dönem gri kesikli çizgisi. 92 günü aşan dönemlerde değerler aylık, diğerlerinde günlük toplanır.
  - **Sipariş durumları:** dört gruplu halka grafiği (karar bekleyen, onaylandı/hazırlanıyor, tamamlandı, reddedildi/iptal).
  - **Kategoriye göre satış:** ana kategori bazında net kalem tutarı, ilk 8 kategori.
  - **En çok satış yapılan bayiler:** ilk 8 bayi.
- Her grafik kartında **Tablo** düğmesi aynı veriyi tablo olarak gösterir. Bu, erişilebilirlik ve düşük kontrastlı renkler için gereklidir.
- Tanım: **satış**, onaylanmış ve iptal edilmemiş siparişlerin KDV dahil genel toplamıdır ve onay tarihine göre sayılır. Karar bekleyen, reddedilen ve iptal edilen siparişler satışa girmez.
- Kategori filtresi seçildiğinde sipariş tutarları, o kategoriden kalem içeren siparişlerin tamamını kapsar. Kategori dağılımı ise yalnızca o kategorinin kalemlerini sayar. Bu fark ekranda not olarak yazar.

### Dashboard grafiği (bayi)
- Son 12 ayda onaylanmış siparişlerin aylık tutarı çubuk grafikte gösterilir; tablo görünümü de vardır. Bayi yalnızca kendi ilişkisinin verisini görür.

### Grafik tasarımı
- Chart.js yalnızca grafik bulunan sayfalarda ayrı bir parça olarak yüklenir.
- Kategorik renk paleti sabit sırayla kullanılır: `#2a78d6`, `#eb6834`, `#1baf7a`, `#eda100`. Palet renk körlüğü ve ayırt edilebilirlik denetiminden geçti.
- Önceki dönem gri gösterilir. Tek eksen kullanılır; ikinci bir y ekseni yoktur.
- Çizgi grafikte imleç tüm serileri ipucunda gösterir; çubuklarda tek değer gösterilir. Tutarlar ₺ biçimindedir.

### Raporlama merkezi
- Menüde **Raporlar** yer alır. Platform yöneticisi `reports` modülünü kapatırsa menüden kalkar ve sayfalar 403 döner.
- Altyapı: her rapor bir sınıftır (filtreler, sütunlar, toplamlar, sorgu). Ekran, Excel ve PDF **aynı hazırlık ve sorguyu** kullanır; bu yüzden toplamlar her çıktıda aynıdır.
- Ekran özellikleri:
  - Temel ve gelişmiş filtreler.
  - Sütun seçimi.
  - Başlıktan sıralama.
  - Sayfalama (50 satır).
  - Alt toplam satırı.
  - Sipariş raporlarında ortalama tutar.
- Filtre, sütun ve sıralama değerleri doğrulanır. Başka işletmenin bayisi veya kategorisi seçilemez.

| Alan | Raporlar |
|---|---|
| Ürün | Ürün ve fiyat listesi, Bayiye özel fiyat listesi (öncelik kurallarıyla hesaplanmış nihai fiyat) |
| Stok | Kritik stok ve stokta olmayanlar, Depo bazında stok, Stok hareketleri |
| Sipariş | Siparişler, Sipariş kalemleri, En çok sipariş verilen ürünler, Bayi bazında sipariş hacmi |
| Bayi | Bayilik başvuruları |
| Cari | Cari bakiye ve vadesi geçmiş alacaklar (FIFO yaşlandırma), Cari hareket dökümü, Tahsilatlar |
| Bayi paneli | Siparişlerim, Ürün bazında alımlarım, Cari hesap ekstrem |

### Excel ve PDF
- **Excel** OpenSpout ile **akış olarak** yazılır. Satırlar 1000'lik parçalarla okunur, bu yüzden büyük raporlar belleği doldurmaz.
  - Dosyanın başında işletme unvanı, rapor adı, rapor tarihi ve filtre özeti yer alır; ardından kalın başlık satırı gelir.
  - Tutar ve miktarlar sayı hücresi olarak yazılır.
  - Son satır kalın **TOPLAM** satırıdır.
- **Formül enjeksiyonu önlemi:** `= + - @` veya sekme ile başlayan metinlerin başına `'` eklenir. Excel'de hiçbir metin hücresi formül olarak çalışmaz.
- **PDF** Dompdf ile üretilir: işletme logosu, DejaVu Sans yazı tipi (Türkçe karakterler için), 6'dan fazla sütunda yatay sayfa. Dış kaynak yüklenmez.
  - PDF en fazla **2.000 satır** içerebilir. Sınır aşılırsa kullanıcıya filtreyi daraltması veya Excel kullanması söylenir.
- Her dışa aktarım denetim kaydına yazılır (`report.exported`: rapor, biçim, filtreler). Dışa aktarma kullanıcı başına dakikada 20 istekle sınırlıdır.

### Karar: kuyrukta rapor üretimi
Planda büyük raporların kuyrukta üretilmesi vardı. Excel zaten akışla ve parça parça üretildiği için bellek sorunu yoktur. PDF ise satır sınırıyla korunur. Bu yüzden kuyruk ve "hazır olunca indir" akışı eklenmedi; ihtiyaç doğarsa aynı dışa aktarıcı bir kuyruk işinden çağrılabilir.

## Testler (bu aşamada eklenen 8 test)
- **Ekranlar:** iki alandaki bütün rapor sayfaları açılıyor; toptancı raporu bayi alanında 404 veriyor.
- **Toplamlar:** ekran toplamı, Excel TOPLAM satırı ve rapor çalıştırıcısının toplamı birebir aynı; dışa aktarım denetim kaydına yazılıyor.
- **Formül enjeksiyonu:** `=HYPERLINK(...)` ve `+SKU` gibi değerler Excel'de formül değil, metin olarak yazılıyor.
- **PDF:** PDF üretiliyor; satır sınırı aşılınca hata mesajıyla geri dönülüyor; bilinmeyen biçim 404 veriyor.
- **İzolasyon:** toptancı yalnızca kendi verisini görüyor ve başka işletmenin bayisini filtre olarak seçemiyor. Bayi, aynı toptancının başka bayisinin siparişlerini ne ekranda ne Excel'de görüyor.
- **Alış fiyatı:** bayi raporlarında alış fiyatı sütunu yok ve istekle de eklenemiyor.
- **Modül kapatma:** raporlar 403 veriyor ve menü bağlantısı kalkıyor.
- **Grafik verisi:** yalnızca onaylanmış satışlar sayılıyor; önceki dönem, günlük kovalar, durum grupları, bayi sıralaması, 12 aylık toplam, bayi filtresi ve bayinin 12 aylık grafiği doğru; geçersiz dönem reddediliyor.

## Bilinen eksikler
- Dashboard için koyu tema yoktur; uygulama genelinde yalnızca açık tema kullanılır.
- Rapor kayıtlı filtre ("favori rapor") özelliği yok.

## Aşama 9 gereksinimleri (Güvenlik, performans, negatif testler)
- Yetki ve izolasyon için negatif test taraması: her alan ve rota için yetkisiz erişim, IDOR, başka işletmenin kaydı.
- Girdi doğrulama, dosya yükleme, hız sınırları, oturum ve CSRF davranışları, güvenlik başlıkları.
- Büyük veriyle performans: indeks kontrolü, N+1 taraması, rapor ve dashboard sorgu süreleri.
- Eşzamanlılık senaryolarının genişletilmesi.
