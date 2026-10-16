# Aşama 9 Raporu — Güvenlik, Performans, Negatif Testler

> Durum: **Tamamlandı** · Testler: 197/197 geçti (+ ayrı performans ölçümü geçti)

## Güvenlik

### Otomatik rota taraması (`tests/Feature/Security/RouteAccessSweepTest.php`)
Testler rota listesini uygulamadan okur, bu yüzden ileride eklenen rotalar da taramaya kendiliğinden girer. Taramada, rotalarda geçen her model türünden kayıt kurulur (`tests/Concerns/BuildsWorld.php`).
- **İşletmeler arası:** Toptancı, başka işletmenin kaydıyla bütün kimlikli rotaları (GET/POST/PUT/DELETE) dener. Hepsi **404** döner ve diğer işletmenin bütün tablolarındaki veri **bayt bayt aynı** kalır. Kontrol olarak aynı sayfalar kendi kayıtlarıyla 200 döner.
- **İç içe kayıtlar:** Bir üst kayda başka bir üst kaydın alt kaydı verildiğinde (ör. A bayisinin adresi B bayisinin adresiyle) aynı işletme içinde de 404 döner.
- **Bayiler arası:** Bayi, aynı toptancının başka bayisinin siparişine, adresine, sepet kalemine, konuşmasına, ekine ve bildirimine erişemez. Başka toptancının ürününe ve görseline de erişemez.
- **Oturum:** ~190 korumalı rotanın her biri oturumsuz istekte ve başka alanın oturumuyla gelen istekte kendi giriş sayfasına yönlenir.

**Bulunan ve düzeltilen:** Stok sayımında başka sayıma ait satır silinmek istendiğinde 404 yerine 409 dönüyordu (veri değişmiyordu); artık 404.

### Bozuk girdi taraması (`InputFuzzTest.php`)
Uygulama kodunda doğrulanan bütün alan adları kaynak koddan çıkarılır. Her yazma rotasına beş tür bozuk değerle gönderilir: iç içe dizi, çok uzun metin, aşırı/negatif sayı, boş değer ve kontrol karakteri/SQL parçası. Bu, toptancı, bayi ve platform alanlarında, herkese açık formlarda ve giriş formlarında, ayrıca liste sayfalarının sorgu dizgelerinde yapılır. Beklenen: **hiçbir istek 500 vermez.**

**Bulunan ve düzeltilen 13 nokta.** Dizi gönderildiğinde (`code[]=x`) "Array to string conversion" veya TypeError ile 500 dönüyordu:
- Birim ve depo kodu, e-posta ayarı, ürün ekleme/düzenleme, sepet miktarı, bayi/fiyat/stok aramaları, platform işletme kısa adı → `Request::scalar()` ile güvenli okuma.
- Parola değiştirme (iki alan) → `bail` + `string`.
- Tarih aralığı filtreleri (siparişler, tahsilatlar, stok hareketleri, cari ekstre, bayi ekstresi, fiyat listesi, indirim, lisans) → Laravel'in `after_or_equal:alan` kuralı karşı alan dizi olunca çöküyordu; yerine `App\Rules\NotBefore` kuralı geldi.

### Sertleştirme (`SecurityHardeningTest.php`)
- **Content-Security-Policy** eklendi. Yalnızca kendi kaynaklarımıza izin verir; `frame-ancestors 'none'`, `object-src 'none'`, `form-action 'self'`. Alpine için `'unsafe-eval'` gerekiyor; satır içi `<script>` yok. Grafikler ve Alpine politikayla tarayıcıda denendi. `npm run dev` çalışırken politika uygulanmaz.
- Diğer başlıklar: X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy; HTTPS'te HSTS.
- Oturum çerezi üretimde varsayılan olarak `secure`.
- Testle doğrulananlar:
  - **Kaba kuvvet kilidi:** 5 hatalı denemeden sonra doğru parola da reddedilir. Kilit e-postaya özeldir.
  - **Oturum:** girişte oturum kimliği yenilenir; çıkışta oturum geçersiz olur.
  - **Yönlendirme:** girişten sonra başka alana veya dış adrese yönlendirilmez.
  - **Parola sıfırlama:** hesabın varlığını açığa vurmaz.
  - **Toplu atama:** formdan gönderilen `business_id`, `dealer_relationship_id` ve `id` yok sayılır.
  - **XSS:** ürün adı ve açıklamasındaki `<script>`, iki alanda da kaçırılır.
  - **Tarih kuralı:** bitiş tarihi başlangıçtan önce olamaz kuralı yeni kuralla da çalışır.

## Performans

### Ölçüm ortamı
`php artisan test --group=performance` komutu `VolumeSeeder` ile tutarlı büyük veri üretir (~65 sn). Üretilen veri:
- 300 bayi, 20.000 ürün, 90 kategori.
- **50.000 sipariş, 250.240 kalem** (18 aya yayılmış).
- 5.394 tahsilat; stok ve cari hareketleri.

Cari bakiyeler hareket toplamına, depo stokları son hareket bakiyesine eşittir. Ardından 37 sayfa ve dosya ölçülür.

Sınırlar:
- Sayfalar: 1,5 sn ve 40 sorgu.
- Rapor ekranları ile dashboard'un ilk açılışı (1 yıllık veriyi toplar): 3 sn.
- Excel dışa aktarma: 20 sn. Satırları 1000'lik parçalarla okuduğu için sorgu sınırı uygulanmaz.

### Bulunan darboğazlar ve sonuç

| Sayfa | Önce | Sonra | Neden / çözüm |
|---|---:|---:|---|
| Rapor: ürün ve fiyat listesi | **97,9 sn** | 0,30 sn | Stok alt sorgusu `business_id` içermediği için indeks kullanılamıyor, her ürün için tüm stok tablosu taranıyordu |
| Rapor: bayi bazında hacim | **18,9 sn** | 0,28 sn | Aynı sorun, sipariş alt sorgularında |
| Cari hesaplar | 4,1 sn | 0,54 sn | Vade yaşlandırması bütün cari hareketleri belleğe yüklüyordu. Artık tek SQL sorgusunda yalnızca açık borçları getiriyor (aşağıda) |
| Rapor: cari bakiye / vadesi geçmiş | 4,7 sn | 0,65 sn | Aynı |
| Dashboard (bu ay) | 4,7 sn | 0,87 sn | Aynı + onay tarihi indeksi |
| Dashboard (12 ay) | 5,3 sn | 1,9 sn ilk / 0,87 sn sonra | Önbellek (aşağıda) |
| Dashboard (12 ay, kategori) | 5,6 sn | 2,3 sn ilk / 0,84 sn sonra | Önbellek |
| Rapor: sipariş kalemleri / en çok satanlar (1 yıl) | 2,2 sn | 1,9–2,1 sn | 1 yıllık ~170.000 kalemi toplar; rapor sınırı içinde |
| Excel: 1 yıllık siparişler (~33.000 satır) | 6,3 sn | 6,0 sn | Akışla yazılır, bellek sabit |

Diğer bütün sayfalar 100 ms'nin altında. Liste ekranları, sipariş detayı, katalog ve bayi ekranları 15–90 ms sürüyor.

**Vade yaşlandırması:** FIFO'da tahsilatlar en eski vadeden kapatıldığı için, açık kalan borçlar vadesi en yeni olan ve ters çevrilmemiş borçlardır; toplamları da bakiyeye eşittir. Toplu yaşlandırma artık bunu SQL pencere fonksiyonuyla hesaplıyor. Eski hesapla birebir aynı sonucu verdiği, rastgele 12 defterle (açılış, borç/alacak düzeltmesi, iptal edilen tahsilatlar, aynı vadeli borçlar, alacak bakiyesi) test edildi (`AccountAgingEquivalenceTest`).

**Dashboard önbelleği:** Sipariş ölçüleri önbelleğe alınır. Anahtar, işletme + filtreler + **son sipariş değişikliği**dir. Bu yüzden herhangi bir sipariş oluşunca veya durumu değişince sonuç hemen yenilenir; eski veri gösterilmez. Son 5 saniyede değişiklik varsa önbellek kullanılmaz. Tahsilat göstergesi her zaman canlıdır. Bu davranış testlidir.

**Yeni indeksler:** `orders(business_id, approved_at)` ve `orders(business_id, updated_at)`.

**N+1:** Geliştirme ve testte tembel yükleme hata fırlatır (`Model::shouldBeStrict`). Bu yüzden testlerin açtığı her sayfa N+1 içermediği için geçiyor. Ölçümde en çok sorgu çalıştıran sayfa 38 sorgu.

## Eşzamanlılık (`tests/Concurrency/WorkflowConcurrencyTest.php`)
Gerçek ayrı PHP süreçleri aynı milisaniyede başlatılır. Hangi işlem önce kazanırsa kazansın stok, cari ve kayıt sayıları tutarlı kalmalıdır. Senaryolar:
- **Onay ile toptancı iptali yarışı:** iptal kazanırsa stok ve bakiye ilk haline döner; onay kazanırsa iptal ters kayıtla yapılır.
- **Onay ile bayi iptali yarışı:** iptal kazanırsa onay reddedilir; onay kazanırsa bayi iptali talebe dönüşür.
- **Aynı belirteçle 3 eşzamanlı sipariş gönderimi:** tek sipariş, tek kalem.
- **Aynı tahsilatın iki kez eşzamanlı iptali:** tek ters kayıt, doğru bakiye.
- **Açılış bakiyesinin eşzamanlı iki kez girilmesi:** yalnızca biri kabul edilir.
- **İade edilebilir miktarı aşan iki eşzamanlı iade:** biri reddedilir; stok ve cari tek iade kadar değişir.

Önceki aşamalardaki stok ve sipariş onayı yarış testleriyle birlikte toplam 9 eşzamanlılık testi var.

## Testler (bu aşamada eklenen 23 test, toplam 197)
- Rota taraması (4), bozuk girdi taraması (4), sertleştirme (8).
- Yaşlandırma eşdeğerliği (1), dashboard önbelleği (1).
- Eşzamanlı iş akışları (5).
- Ayrıca performans ölçümü (1, `--group=performance`, normal koşuya dahil değil).

## Bilinen eksikler / notlar
- `'unsafe-eval'` CSP'de duruyor (Alpine'in standart sürümü gerektiriyor). Kaldırmak için Alpine'in CSP sürümüne geçilmesi ve bütün `x-data` ifadelerinin bileşenlere taşınması gerekir. Satır içi script olmadığı için risk düşük.
- Dashboard'un önbellekli halinde kalan ~0,85 sn, sayfanın filtrelerden bağımsız operasyon bölümünden (vade yaşlandırması, stok değeri, kritik stok) geliyor.
- Performans ölçümü geliştirme bilgisayarında (Windows, taşınabilir MySQL) yapıldı. Sunucudaki değerler 10. aşamada tekrar ölçülecek.

## Aşama 10 gereksinimleri (Sunucu ve teslim)
- Sunucu kurulumu: HTTPS, PHP-FPM/Nginx, MySQL, kuyruk çalıştırıcısı (supervisor), zamanlanmış görevler (cron).
- Ortam: `APP_ENV=production`, `APP_DEBUG=false`, güvenli oturum çerezi, `MAIL_SECRET_KEY` ve `APP_KEY` yedeği.
- Yedekleme: günlük veritabanı ve yüklenen dosya yedeği, geri yükleme denemesi.
- Gerçek Gmail hesabıyla e-posta bağlantı testi.
- Performans ölçümünün sunucuda tekrarı; kullanıcı ve kurulum dokümanları.
