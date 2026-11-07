# B2B faturaları: toptancı → bayi (e-Arşiv / e-Fatura, Uyumsoft)

Bayi siparişlerinin satış ve iade faturaları artık toptancı panelinden kesilir. Mağaza faturalarından tamamen ayrıdır.
Ayrı tablolar (`invoice_settings`, `invoices`, `invoice_lines`), ayrı kod (`app/Domain/Invoicing`), ayrı ayarlar, ayrı Uyumsoft bilgileri ve ayrı seri kullanılır.
Mağazada yapılan hiçbir şey B2B faturalarını etkilemez, tersi de geçerlidir. `StoreBoundaryTest` iki tarafın birbirinin kodunu kullanmadığını denetler.

Uyumsoft portalında elle fatura kesmek de mümkün kalır. Elle kesilen fatura numarasıyla siparişe kaydedilir; böylece aynı siparişe ikinci fatura kesilmez.

## Ne zaman kesilir

| Ödeme yöntemi | Fatura anı | Neden |
|---|---|---|
| Kart (iyzico) | Ödeme alınınca | Ödeme anında satış kesinleşir |
| Havale / EFT | Toptancı "Ödeme alındı" ile kaydedince | Para hesaba geçince |
| Sonra öde, eski açık hesap / teslimde ödeme | Mal "Yola çıktı" olunca | VUK 231/5: fatura teslimden itibaren 7 gün içinde düzenlenir. Vade gününü beklemek bu süreyi aşar. |

Sipariş onaylanmış (ve iptal / ret edilmemiş) olmalıdır.

**Otomatik kesim:** Fatura Ayarları'nda açılırsa zamanlanmış komut (`b2b:invoices`, dakikada bir) faturayı kendiliğinden keser.
Yalnızca otomatik kesim açıldıktan **sonra** ödemesi alınan / yola çıkan siparişlere uygulanır (`auto_since`); geçmiş siparişler kendiliğinden faturalanmaz.
Sorun çıkan taslak "Sorunlu" sekmesinde kalır ve kendiliğinden yeniden denenmez.

Otomatik kesim kapalıysa sipariş "Fatura bekleyen"de durur. Toptancı taslak hazırlar, kontrol eder ve gönderir (tek tek veya toplu).

## Tutarlar

- Kalemler siparişin **hesaplanmış** tutarlarından birebir gelir: onaylanan miktar, KDV hariç birim fiyat, iskonto (fiyat listesi indirimi + kupon / kampanya payı), KDV.
- Teslimat ücreti ayrı "Teslimat hizmeti" kalemidir. KDV dahil ücret, Fatura Ayarları'ndaki orana (varsayılan %20) göre ayrılır; toplam değişmez.
- Kalemler ve tutarlar **değiştirilemez**. Taslakta yalnızca alıcı bilgisi, açıklama, fatura tarihi (en fazla 7 gün geri) ve bayi bildirimi düzeltilir.
  Böylece fatura, bayinin carisine yazılan ve ödediği tutarla her zaman aynıdır.
- Her kalem fatura kuralıyla yeniden hesaplanıp siparişle karşılaştırılır. Tutmazsa taslak hazırlanmaz.
- Gönderimden hemen önce şu üçü yeniden karşılaştırılır: kalemlerin toplamı, faturanın toplamı ve siparişin güncel genel toplamı. Sipariş sonradan değiştiyse fatura gönderilmez.
- B2B fiyatları KDV hariç tutulur (toptan ticaret alışkanlığı). Faturada birim fiyat KDV hariç, iskonto ayrı satırda görünür; UBL'de kalem iskontosu `AllowanceCharge` ile gider.

## Belge tipi ve seri

- Bayi Uyumsoft'ta e-Fatura mükellefiyse **e-Fatura** (TEMELFATURA, bayinin posta kutusu etiketine), değilse **e-Arşiv** kesilir.
- Tip: SATIS. %0 KDV'li kalem varsa ISTISNA (kod 335). İadede IADE; dayandığı satış faturasına `BillingReference` ile bağlanır (`DocumentTypeCode` IADE).
- **Seri:** Mali müşavir kararı gereği bayi faturaları mağazadan ayrı seriden kesilir. Fatura Ayarları'nda e-Arşiv ve e-Fatura serisi zorunludur (3 karakter, ör. BYA / BYF).
  Seri UBL `cbc:ID` alanına yazılır, Uyumsoft numarayı o seriden verir. Seri boşsa fatura gönderilmez.
- **e-Arşiv internet satışı:** B2B siparişi bayi panelinden verildiği için internet satışıdır. Ödeme şekli siparişin gerçek ödemesinden gelir:
  - kart → KREDIKARTI/BANKAKARTI (aracı iyzico)
  - havale → EFT/HAVALE
  - belli değilse (sonra öde, cariden mahsup) → KAPIDAODEME (GİB önerisi)

  Taşıyıcı toptancının kendisidir (malı kendisi teslim eder).

## İade ve iptal

- Her iade kaydına bir iade faturası kesilir. Satış faturası kesilmemiş siparişin iadesi listelenmez.
- Sipariş tamamen iade / iptal edildiyse ve satış faturası 7 gün içindeyse iade faturası **kesilmez**; satış faturası iptal edilir ("İade bekleyen" sekmesinde "Satış faturasını iptal et").
- Ödemesi alınıp yola çıkmadan iptal edilen siparişte iade kaydı yoktur. 7 gün geçtiyse satış faturasının tamamı için iade faturası kesilir.
- Tam iadede teslimat ücreti de iade edildiği için iade faturasına teslimat kalemi eklenir.
- e-Fatura mükellefi bayide iade faturasını **bayi keser**. Buradan kesilmez; gelen fatura "Elle kesilen faturayı ekle" ile kaydedilir.
- e-Arşiv iptali yalnızca buradan kesilmiş faturada ve fatura tarihinden itibaren 7 gün içinde yapılır. Dayanan iade faturası varsa satış faturası iptal edilemez.
  e-Fatura buradan iptal edilmez.

## Güvenlik ve çift fatura

- **Tek açık belge:** Bir siparişin aynı anda tek açık satış belgesi (taslak / gönderiliyor / belirsiz / kesildi) olabilir; iadede iade kaydı başına bir.
  Bu kural veritabanında tekil anahtarla da korunur (`invoices.open_key`). İptal edilen ve reddedilen belge yeni belgeye engel olmaz.
- **Atomik gönderim:** Yalnızca taslak durumundaki kayıt "gönderiliyor"a geçebilir (aynı anda iki istek gelirse biri reddedilir).
- **Sonuç belirsiz:** Bağlantı koparsa belge "sonuç belirsiz" kalır, yeniden gönderilemez. Zamanlanmış komut 2 dakika sonra Uyumsoft'tan ETTN ile sorgular:
  bulunursa kesildi olarak tamamlanır, bulunmazsa taslağa alınır. Toptancı da "Uyumsoft'tan sorgula" ile sorgulayabilir.
- **GİB denetimi:** Uyumsoft numarayı verdikten sonra GİB denetimi yapılır. Durum dakikada bir sorgulanır.
  - Reddedilirse belge "Reddedildi" olur ve sipariş yeniden faturalanabilir.
  - Portaldan iptal edildiyse belge "İptal" olur.
  - Onaylanınca bayiye bildirim gider.
- **Gizli bilgiler:** Uyumsoft şifresi şifreli saklanır. Ekranda, model çıktısında (`$hidden`) ve işlem geçmişinde görünmez; boş bırakılırsa korunur.
- **Yetki:** Faturalar işletme bağlamıyla sınırlıdır (`BelongsToBusiness`); başka işletmenin faturası / siparişi 404 döner.
  Bayi yalnızca kendi siparişlerinin **kesilmiş** faturalarını görür ve indirir. Taslak ve başka bayinin faturası 404 döner.
- **PDF:** Faturalar `businesses/{id}/invoices/{sipariş}/<rastgele>.pdf` yolunda, herkese açık olmayan diskte saklanır. Yalnızca oturum açmış sahibine `no-store` başlığıyla verilir.
  Elle eklenen PDF'in gerçekten PDF olduğu (`%PDF`) denetlenir; en fazla 5 MB.
- **Bayi e-postası:** "Faturanız hazır" e-postasına fatura eklenmez; bayi panele girip indirir.
  E-posta, faturadaki (düzenlenebilir) adrese değil bayinin kayıtlı giriş adresine gider.
- Her işlem işlem geçmişine yazılır (`invoice.*`).

## Ekranlar

| Ekran | İçerik |
|---|---|
| Toptancı → Satış → Faturalar | Sekmeler: Fatura bekleyen, İade bekleyen, Taslak, Sorunlu, Kesilen, İptal / reddedilen. Toplu taslak ve toplu gönderim. |
| Fatura sayfası | Kalemler (salt okunur), alıcı formu, önizleme PDF'i, gönder, sorgula / taslağa al, PDF indir / yeniden al, Uyumsoft durumu, iptal. |
| Sipariş sayfası → Fatura kartı | Siparişin faturaları, "Fatura taslağı hazırla", "Uyumsoft'ta elle kesilen faturayı ekle". |
| Ayarlar → Fatura Ayarları | Sağlayıcı (deneme / Uyumsoft), ortam, web servis kullanıcısı, gönderici VKN, seriler, teslimat KDV'si, otomatik kesim, bayi bildirimi, bağlantı testi. |
| Bayi → Faturalarım | Kesilen satış / iade faturaları ve PDF indirme; sipariş sayfasında da görünür. |

## Deneme sürücüsü

Varsayılan sağlayıcıdır. Hiçbir yere göndermez: "DNM" (e-Arşiv) / "DNF" (e-Fatura) numarası ve üzerinde "DENEME" yazan PDF üretir. Geçerli fatura değildir.
VKN'si 1111111111 olan bayi e-Fatura mükellefi sayılır (e-Fatura yolunu denemek için).

## Canlıya alma adımları

1. İşletme Ayarları'nda unvan, vergi dairesi, vergi numarası ve adresin tam olduğunu kontrol edin.
2. Uyumsoft portalında bayi faturaları için iki seri tanımlayın (e-Arşiv ve e-Fatura; mağazanınkinden farklı).
3. Fatura Ayarları'nda Uyumsoft'u seçin, önce **Test** ortamıyla kullanıcı adı / şifre girip "Bağlantıyı dene".
4. Test ortamında bir siparişle e-Arşiv faturası kesin. Numaranın seçtiğiniz seriyle başladığını Uyumsoft portalında kontrol edin.
5. Ortamı **Canlı** yapın. İsterseniz otomatik kesimi açın.

## Testler

`tests/Feature/Invoicing/B2bInvoiceTest.php` (21 test). Kapsadığı konular:
- tetikleyiciler (havale ödemesi, sonra öde sevki)
- tutarların sipariş / kısmi onay / iskonto ile birebir aynı olması, teslimat kalemi
- çift faturanın veritabanında da engellenmesi
- taslakta tutarın değiştirilememesi, gönderimde sipariş tutarının yeniden kontrolü
- Uyumsoft:
  - ayrı seri
  - internet satışı bilgisi (havale / kart)
  - GİB onayı → bildirim, red → yeniden faturalama
  - belirsiz sonuç → sorgu
  - e-Fatura mükellefi bayi
- iade:
  - kısmi iade
  - 7 gün içinde tam iade → iptal
  - 7 gün sonra tam iade → teslimat kalemiyle iade faturası
  - iade kaydı olmadan iptal edilen sipariş
- elle kesilen fatura
- otomatik kesimin yalnızca açıldıktan sonraki siparişlere uygulanması
- şifrenin şifreli ve gizli kalması
- işletme / bayi yalıtımı, sayfalar
