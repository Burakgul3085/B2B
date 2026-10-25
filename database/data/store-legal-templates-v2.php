<?php

/*
| Mağaza yasal metinlerinin ikinci (ayrıntılı) sürümleri: 6502 sayılı Tüketicinin Korunması Hakkında Kanun, Mesafeli
| Sözleşmeler Yönetmeliği, 6698 sayılı KVKK ve Kurul'un Çerez Uygulamaları Hakkında Rehberi esas alınmıştır.
| Mevzuat değiştikçe yönetimde Ayarlar → Yasal Metinler ekranından güncellenir (her kayıt yeni sürüm oluşturur).
| Yer tutucular: App\Domain\Store\Orders\LegalDocuments::PLACEHOLDERS
*/

return [
    'on-bilgilendirme' => ['Ön Bilgilendirme Formu', <<<'MD'
İşbu Ön Bilgilendirme Formu, 6502 sayılı Tüketicinin Korunması Hakkında Kanun ("Kanun") ve Mesafeli Sözleşmeler Yönetmeliği ("Yönetmelik") uyarınca, mesafeli satış sözleşmesi kurulmadan önce ALICI'nın bilgilendirilmesi amacıyla düzenlenmiştir. ALICI, siparişini onaylamadan önce bu formu okuyarak aşağıdaki hususlarda bilgi sahibi olduğunu kabul eder.

## 1. Satıcı bilgileri

- **Unvan:** {SATICI_UNVAN}
- **Adres:** {SATICI_ADRES}
- **Telefon:** {SATICI_TELEFON}
- **E-posta:** {SATICI_EPOSTA}
- **Vergi dairesi / no:** {SATICI_VERGI}
- **MERSİS no:** {SATICI_MERSIS}
- **İnternet sitesi:** {SATICI_WEB}

## 2. Alıcı bilgileri

- **Ad soyad / unvan:** {ALICI_AD}
- **Teslimat adresi:** {ALICI_ADRES}
- **Telefon:** {ALICI_TELEFON}
- **E-posta:** {ALICI_EPOSTA}

## 3. Sözleşme konusu mal ve hizmetler

Malların temel nitelikleri (tür, marka, model, renk, adet ve benzeri özellikler) ürün sayfalarında yer aldığı şekildedir. Sipariş edilen mallar ve satış bedelleri aşağıdadır:

{URUNLER}

**Ödeme özeti**

- **Teslimat ücreti:** {TESLIMAT_UCRETI}
- **Kapıda ödeme hizmet bedeli:** {KAPIDA_ODEME_BEDELI}
- **Toplam bedel (vergiler dahil):** {TOPLAM}
- **Ödeme yöntemi:** {ODEME_YONTEMI}
- **Teslimat yöntemi:** {TESLIMAT_YONTEMI}

Sitede ilan edilen fiyatlar Katma Değer Vergisi dahil Türk lirası fiyatlarıdır. Kampanya ve indirimler, sepette ve bu formda gösterilen tutara yansıtılmıştır. Yukarıda belirtilenler dışında ALICI'dan ek bir ücret talep edilmez.

## 4. Ödeme

1. Kartla ödemeler, lisanslı ödeme kuruluşu iyzico'nun güvenli ödeme altyapısı ve 3D Secure doğrulamasıyla alınır. Kart bilgileri SATICI tarafından görülmez ve saklanmaz. Taksitli ödemelerde taksit sayısı ve varsa vade farkı, ödeme ekranında ALICI'ya ayrıca gösterilir.
2. Havale / EFT ile ödemelerde sipariş, bedelin SATICI hesabına ödeme sayfasında bildirilen süre içinde ulaşmasıyla kesinleşir. Süresinde ödenmeyen sipariş kendiliğinden iptal edilir.
3. Kapıda ödeme yalnızca SATICI'nın kendi teslimat hizmeti verdiği bölgelerde ve ödeme sayfasında belirtilen koşullarla sunulur.
4. Siparişin onaylanması, ALICI için ödeme yükümlülüğü doğurur.

## 5. Teslimat

1. Mallar, siparişin SATICI'ya ulaştığı tarihten itibaren yasal **30 günlük** süreyi aşmamak kaydıyla, ALICI'nın bildirdiği teslimat adresine veya gösterdiği kişiye teslim edilir. Tahmini kargoya veriliş ve teslim süreleri ürün ve ödeme sayfalarında ayrıca belirtilir.
2. Mal, ALICI'ya veya gösterdiği kişiye teslim edilinceye kadar oluşabilecek kayıp ve hasarlardan SATICI sorumludur.
3. Sipariş konusu malın tedarikinin imkânsızlaşması hâlinde SATICI, bu durumu öğrendiği tarihten itibaren **3 gün** içinde ALICI'ya yazılı olarak veya kalıcı veri saklayıcısı ile bildirir ve tahsil ettiği tüm ödemeleri bildirim tarihinden itibaren en geç **14 gün** içinde iade eder.

## 6. Cayma hakkı

1. ALICI, malın kendisine veya gösterdiği kişiye teslim edildiği tarihten itibaren **{IADE_SURESI} gün** içinde hiçbir hukuki ve cezai sorumluluk üstlenmeksizin ve hiçbir gerekçe göstermeksizin sözleşmeden cayma hakkına sahiptir. ALICI, malın teslimine kadar geçen sürede de cayma hakkını kullanabilir. Tek siparişle ayrı ayrı teslim edilen mallarda süre, son malın teslim edildiği tarihten itibaren başlar.
2. Cayma hakkı; sitedeki **Hesabım → Siparişlerim → İade talebi** adımıyla, misafir siparişlerinde sipariş e-postasındaki bağlantıyla veya SATICI'nın {SATICI_EPOSTA} adresine ya da {SATICI_ADRES} adresine yöneltilecek açık bir yazılı bildirimle kullanılır. Bildirimin süresi içinde yöneltilmesi yeterlidir.
3. ALICI, cayma bildirimini yönelttiği tarihten itibaren **10 gün** içinde malı SATICI'ya geri gönderir. SATICI'nın iade talebinin onayıyla bildirdiği kargo firması ve gönderim bilgisiyle yapılan iadelerde gönderim masrafı SATICI'ya aittir. İade adresi: {IADE_ADRESI}
4. SATICI, cayma bildiriminin kendisine ulaştığı tarihten itibaren **14 gün** içinde, teslimat masrafları dahil tahsil ettiği tüm bedelleri, ALICI'nın satın alırken kullandığı ödeme aracına uygun şekilde ve ALICI'ya herhangi bir masraf veya yükümlülük getirmeden tek seferde iade eder.
5. Cayma hakkı süresi içinde malın, işleyişine, teknik özelliklerine ve kullanım talimatlarına uygun şekilde kullanılması sebebiyle meydana gelen değişiklik ve bozulmalardan ALICI sorumlu değildir. Bunun dışındaki kullanım sonucu oluşan değer kaybından ALICI sorumludur.

## 7. Cayma hakkının kullanılamayacağı mallar

Yönetmelik'in 15. maddesi uyarınca, özellikle aşağıdaki mallarda cayma hakkı kullanılamaz:

- ALICI'nın istekleri veya kişisel ihtiyaçları doğrultusunda hazırlanan, kişiye özel üretilen veya baskı yapılan mallar (ör. isim baskılı ürünler, kişiye özel kaşe ve damgalar);
- Tesliminden sonra ambalaj, bant, mühür, paket gibi koruyucu unsurları açılmış olup iadesi sağlık ve hijyen açısından uygun olmayan mallar;
- Tesliminden sonra koruyucu unsurları (ambalaj, bant, mühür, jelatin) açılmış olan, maddi ortamda sunulan kitaplar, dijital içerikler ve bilgisayar sarf malzemeleri (ör. toner, kartuş, şerit);
- Abonelik sözleşmesi kapsamında sağlananlar dışında gazete, dergi gibi süreli yayınlar;
- Çabuk bozulabilen veya son kullanma tarihi geçebilecek mallar ile tesliminden sonra başka ürünlerle karışan ve doğası gereği ayrıştırılması mümkün olmayan mallar;
- Yönetmelik'te sayılan diğer mal ve hizmetler.

## 8. Ayıplı mal

Teslim edilen malın ayıplı olması hâlinde ALICI, Kanun'un 11. maddesi uyarınca; sözleşmeden dönme, satış bedelinden ayıp oranında indirim isteme, ücretsiz onarım isteme veya imkân varsa malın ayıpsız bir misli ile değiştirilmesini isteme seçimlik haklarından birini kullanabilir. Malın tesliminden itibaren altı ay içinde ortaya çıkan ayıpların teslim tarihinde var olduğu kabul edilir. Ayıplı maldan doğan sorumluluk, ayıp daha sonra ortaya çıksa bile malın tesliminden itibaren iki yıllık zamanaşımı süresine tabidir.

## 9. Şikâyet ve itirazlar

ALICI, talep ve şikâyetlerini {SATICI_TELEFON} numaralı telefondan veya {SATICI_EPOSTA} adresinden SATICI'ya iletebilir. Uyuşmazlık hâlinde ALICI; Ticaret Bakanlığınca her yıl ilan edilen parasal sınırlar dahilinde yerleşim yerindeki veya işlemin yapıldığı yerdeki **tüketici hakem heyetine**, bu sınırların üzerindeki uyuşmazlıklarda ise dava şartı olan arabuluculuk sürecinin ardından **tüketici mahkemesine** başvurabilir. Başvurular e-Devlet üzerinden Tüketici Bilgi Sistemi (TÜBİS) aracılığıyla da yapılabilir.

## 10. Diğer hususlar

1. ALICI'nın kişisel verileri, sitede yayımlanan **KVKK Aydınlatma Metni** kapsamında işlenir.
2. ALICI'ya, açık onayı bulunmadıkça ticari elektronik ileti gönderilmez. Sipariş, teslimat ve iadeye ilişkin bilgilendirme mesajları ticari elektronik ileti niteliğinde değildir.
3. İşbu form ve Mesafeli Satış Sözleşmesi, sipariş tamamlandığında ALICI'nın e-posta adresine gönderilir ve SATICI tarafından mevzuatta öngörülen süre boyunca saklanır.

Tarih: {TARIH}
MD],

    'mesafeli-satis' => ['Mesafeli Satış Sözleşmesi', <<<'MD'
## Madde 1 — Taraflar

**1.1. SATICI**

- **Unvan:** {SATICI_UNVAN}
- **Adres:** {SATICI_ADRES}
- **Telefon:** {SATICI_TELEFON}
- **E-posta:** {SATICI_EPOSTA}
- **Vergi dairesi / no:** {SATICI_VERGI}
- **MERSİS no:** {SATICI_MERSIS}
- **İnternet sitesi:** {SATICI_WEB}

**1.2. ALICI**

- **Ad soyad / unvan:** {ALICI_AD}
- **Teslimat adresi:** {ALICI_ADRES}
- **Telefon:** {ALICI_TELEFON}
- **E-posta:** {ALICI_EPOSTA}

## Madde 2 — Tanımlar

İşbu sözleşmede geçen;

- **Kanun:** 6502 sayılı Tüketicinin Korunması Hakkında Kanun'u,
- **Yönetmelik:** Mesafeli Sözleşmeler Yönetmeliği'ni,
- **Site:** SATICI'ya ait {SATICI_WEB} alan adlı internet sitesini,
- **Mal:** Sözleşmeye konu, Site üzerinden satışa sunulan taşınır eşyayı,
- **Kalıcı veri saklayıcısı:** ALICI'nın gönderdiği veya kendisine gönderilen bilgiyi, bu bilginin amacına uygun olarak makul bir süre incelemesine elverecek şekilde kaydedilmesini ve değiştirilmeden kopyalanmasını sağlayan e-posta, SMS ve benzeri her türlü araç veya ortamı

ifade eder.

## Madde 3 — Konu

İşbu sözleşmenin konusu, ALICI'nın Site üzerinden elektronik ortamda siparişini verdiği, aşağıda nitelikleri ve satış bedeli belirtilen Mal'ın satışı ve teslimi ile ilgili olarak Kanun ve Yönetmelik hükümleri uyarınca tarafların hak ve yükümlülüklerinin belirlenmesidir.

## Madde 4 — Sözleşme konusu mal, bedel ve ödeme

{URUNLER}

**Ödeme özeti**

- **Teslimat ücreti:** {TESLIMAT_UCRETI}
- **Kapıda ödeme hizmet bedeli:** {KAPIDA_ODEME_BEDELI}
- **Toplam bedel (vergiler dahil):** {TOPLAM}
- **Ödeme yöntemi:** {ODEME_YONTEMI}
- **Teslimat yöntemi:** {TESLIMAT_YONTEMI}

4.1. Mal'ın temel nitelikleri Site'deki ürün sayfasında yer aldığı şekildedir. Satış bedeli, KDV dahil Türk lirası olarak belirtilmiştir.

4.2. Kartla ödemeler lisanslı ödeme kuruluşu iyzico aracılığıyla, 3D Secure doğrulamasıyla alınır; kart bilgileri SATICI tarafından saklanmaz. Havale / EFT ile ödemelerde sipariş, bedelin ödeme sayfasında bildirilen süre içinde SATICI hesabına ulaşmasıyla kesinleşir; süresinde ödenmeyen sipariş iptal edilir.

4.3. Kartla yapılan ödemelerde, ALICI'nın kart hamili olarak bankasına karşı yükümlülükleri ve bankanın uyguladığı faiz, taksit ve vade koşulları ALICI ile bankası arasındaki sözleşmeye tabidir.

## Madde 5 — Genel hükümler

5.1. ALICI, Mal'ın temel nitelikleri, satış bedeli, ödeme şekli, teslimat ve cayma hakkına ilişkin **Ön Bilgilendirme Formu**'nu okuyup bilgi sahibi olduğunu ve elektronik ortamda gerekli teyidi verdiğini kabul ve beyan eder.

5.2. Sözleşme, ALICI'nın siparişi onaylamasıyla kurulur. SATICI, siparişin alındığını ALICI'ya kalıcı veri saklayıcısı ile teyit eder; sözleşme ve ön bilgilendirme metinleri sipariş teyidiyle birlikte ALICI'nın e-posta adresine gönderilir.

5.3. SATICI, Mal'ı sağlam, eksiksiz, siparişte belirtilen niteliklere uygun ve varsa garanti belgesi ve kullanım kılavuzuyla birlikte teslim etmekle yükümlüdür.

5.4. SATICI, sözleşmeden doğan ifa yükümlülüğünün süresi dolmadan ALICI'yı bilgilendirmek ve açıkça onayını almak suretiyle eşit kalite ve fiyatta farklı bir mal tedarik edebilir. ALICI'nın onay vermemesi hâlinde sipariş iptal edilir ve ödenen bedel 14 gün içinde iade edilir.

5.5. Sipariş konusu Mal'ın tedarikinin imkânsızlaşması hâlinde SATICI, bu durumu öğrendiği tarihten itibaren 3 gün içinde ALICI'ya bildirir ve tahsil ettiği tüm ödemeleri bildirim tarihinden itibaren en geç 14 gün içinde iade eder.

5.6. ALICI, sipariş sırasında verdiği bilgilerin doğru ve güncel olduğunu; yanlış veya eksik bilgiden doğan teslimat gecikmelerinden SATICI'nın sorumlu tutulamayacağını kabul eder.

## Madde 6 — Teslimat

6.1. Mal, siparişin SATICI'ya ulaştığı tarihten itibaren yasal 30 günlük süreyi aşmamak kaydıyla ALICI'nın bildirdiği adrese veya gösterdiği kişiye teslim edilir.

6.2. Mal'ın ALICI'ya veya gösterdiği kişiye tesliminden önce oluşabilecek kayıp ve hasarlardan SATICI sorumludur. ALICI, teslim sırasında paketin dışında hasar gördüğü durumda paketi teslim almamalı veya taşıyıcı görevlisine hasar tespit tutanağı düzenletmelidir.

6.3. Teslim adresinde ALICI'ya veya gösterdiği kişiye ulaşılamaması hâlinde Mal, taşıyıcının uygulamasına göre bekletilir; ALICI bu süre içinde Mal'ı teslim almakla yükümlüdür.

## Madde 7 — Cayma hakkı

7.1. ALICI, Mal'ın kendisine veya gösterdiği kişiye teslim edildiği tarihten itibaren **{IADE_SURESI} gün** içinde hiçbir hukuki ve cezai sorumluluk üstlenmeksizin ve hiçbir gerekçe göstermeksizin sözleşmeden cayma hakkına sahiptir. ALICI, Mal'ın teslimine kadar geçen sürede de cayma hakkını kullanabilir.

7.2. Cayma hakkı; Site'deki iade talebi adımıyla veya {SATICI_EPOSTA} adresine ya da {SATICI_ADRES} adresine yöneltilecek açık bir yazılı bildirimle kullanılır. Bildirimin süresi içinde yöneltilmesi yeterlidir.

7.3. ALICI, cayma bildirimini yönelttiği tarihten itibaren 10 gün içinde Mal'ı SATICI'ya geri gönderir. SATICI'nın bildirdiği kargo firması ve gönderim bilgisiyle yapılan iadelerde gönderim masrafı SATICI'ya aittir. İade adresi: {IADE_ADRESI}

7.4. SATICI, cayma bildiriminin kendisine ulaştığı tarihten itibaren 14 gün içinde teslimat masrafları dahil tahsil ettiği tüm bedelleri, ALICI'nın satın alırken kullandığı ödeme aracına uygun şekilde ve ALICI'ya herhangi bir masraf veya yükümlülük getirmeden tek seferde iade eder. Havale / EFT ve kapıda ödemeyle yapılan alışverişlerde iade, ALICI'nın kendi adına kayıtlı banka hesabına yapılır.

7.5. Cayma hakkı süresi içinde Mal'ın işleyişine, teknik özelliklerine ve kullanım talimatlarına uygun şekilde kullanılması sebebiyle meydana gelen değişiklik ve bozulmalardan ALICI sorumlu değildir.

7.6. Kampanya kapsamında birlikte satın alınan mallardan bir kısmının iadesi hâlinde, iade edilmeyen mallar için kampanya koşulları sağlanmıyorsa, iade tutarı bu mallara kampanya uygulanmamış fiyatlar esas alınarak hesaplanır.

## Madde 8 — Cayma hakkının istisnaları

Yönetmelik'in 15. maddesi uyarınca; ALICI'nın istekleri veya kişisel ihtiyaçları doğrultusunda hazırlanan mallarda, tesliminden sonra koruyucu unsurları açılmış olup iadesi sağlık ve hijyen açısından uygun olmayan mallarda, tesliminden sonra koruyucu unsurları açılmış olan maddi ortamda sunulan kitap, dijital içerik ve bilgisayar sarf malzemelerinde, abonelik dışındaki gazete ve dergi gibi süreli yayınlarda, çabuk bozulabilen veya son kullanma tarihi geçebilecek mallarda ve Yönetmelik'te sayılan diğer hâllerde cayma hakkı kullanılamaz.

## Madde 9 — Ayıplı mal ve garanti

9.1. Teslim edilen Mal'ın ayıplı olması hâlinde ALICI, Kanun'un 11. maddesi uyarınca sözleşmeden dönme, satış bedelinden ayıp oranında indirim isteme, ücretsiz onarım isteme veya imkân varsa ayıpsız misli ile değiştirilmesini isteme seçimlik haklarından birini kullanabilir. SATICI, ALICI'nın tercih ettiği bu talebi yerine getirmekle yükümlüdür.

9.2. Mal'ın tesliminden itibaren altı ay içinde ortaya çıkan ayıpların teslim tarihinde var olduğu kabul edilir. Ayıplı maldan doğan sorumluluk, ayıp daha sonra ortaya çıksa bile teslimden itibaren iki yıllık zamanaşımına tabidir; ayıbın ağır kusur veya hile ile gizlenmesi hâlinde zamanaşımı uygulanmaz.

9.3. Garanti belgesi ile satılan mallarda ALICI, garanti kapsamındaki haklarını üretici veya ithalatçıya karşı da kullanabilir.

## Madde 10 — Kişisel veriler ve iletişim

10.1. ALICI'nın kişisel verileri, sözleşmenin kurulması ve ifası amacıyla 6698 sayılı Kişisel Verilerin Korunması Kanunu'na uygun olarak ve Site'de yayımlanan **KVKK Aydınlatma Metni** kapsamında işlenir.

10.2. ALICI'ya, açık onayı bulunmadıkça ticari elektronik ileti gönderilmez. Sipariş, ödeme, teslimat ve iadeye ilişkin bilgilendirme mesajları ticari elektronik ileti niteliğinde değildir.

## Madde 11 — Uyuşmazlıkların çözümü

İşbu sözleşmeden doğan uyuşmazlıklarda, Ticaret Bakanlığınca her yıl ilan edilen parasal sınırlar dahilinde ALICI'nın yerleşim yerindeki veya işlemin yapıldığı yerdeki **tüketici hakem heyetleri**, bu sınırların üzerindeki uyuşmazlıklarda dava şartı olan arabuluculuk sürecinin ardından **tüketici mahkemeleri** yetkilidir.

## Madde 12 — Yürürlük

12.1. ALICI, Site'de siparişi onaylamakla işbu sözleşmenin tüm koşullarını okuduğunu, anladığını ve kabul ettiğini beyan eder. Sözleşme, siparişin onaylandığı tarihte yürürlüğe girer.

12.2. SATICI, işbu sözleşmeyi ve siparişe ilişkin kayıtları mevzuatta öngörülen süre boyunca saklar; ALICI, sipariş e-postasındaki bağlantıdan veya Site'deki hesabından sözleşmeye her zaman erişebilir.

Tarih: {TARIH}
MD],

    'iptal-iade' => ['İptal ve İade Koşulları', <<<'MD'
Bu sayfa, {SATICI_UNVAN} ("Yapboz Kırtasiye") internet sitesi {SATICI_WEB} üzerinden yapılan alışverişlerde sipariş iptali, cayma hakkı ve iade süreçlerini açıklar. Aşağıdaki koşullar 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği'nden doğan haklarınızı sınırlamaz.

## 1. Sipariş iptali

- Siparişiniz kargoya veya teslimata çıkmadan önce **Hesabım → Siparişlerim** sayfasından ya da {SATICI_TELEFON} · {SATICI_EPOSTA} üzerinden bize ulaşarak iptal edebilirsiniz. İptal için herhangi bir ücret alınmaz.
- Kargoya verilmiş siparişlerde ürünü teslim aldıktan sonra cayma hakkınızı kullanabilir veya ürünü teslim almayarak kargo görevlisine iade edebilirsiniz.
- Stok veya tedarik sorunu nedeniyle siparişinizi karşılayamamamız hâlinde sizi en geç **3 gün** içinde bilgilendirir, ödediğiniz tutarın tamamını en geç **14 gün** içinde iade ederiz.

## 2. Cayma hakkı

Ürünün size veya gösterdiğiniz kişiye teslim edildiği tarihten itibaren **{IADE_SURESI} gün** içinde, hiçbir gerekçe göstermeden ve cezai şart ödemeden cayma hakkınızı kullanabilirsiniz. Ürünlerin ayrı ayrı teslim edildiği siparişlerde süre, son ürünün teslim tarihinden başlar. Bildiriminizi süresi içinde yapmanız yeterlidir.

## 3. İade talebi nasıl oluşturulur?

1. **Üye iseniz:** Hesabım → Siparişlerim sayfasından siparişinizi açın ve **İade talebi oluştur** düğmesini kullanın.
2. **Misafir alışverişlerde:** Sipariş onay e-postanızdaki sipariş bağlantısını açarak aynı adımı izleyin.
3. Dilerseniz sipariş numaranızı ve iade etmek istediğiniz ürünleri belirterek {SATICI_EPOSTA} adresine yazılı bildirimde de bulunabilirsiniz.
4. Talebiniz onaylandığında size iade gönderim bilgisi iletilir. Ürünleri, bildirim tarihinden itibaren **10 gün** içinde, mümkünse orijinal kutusu ve faturası veya sipariş numarasıyla birlikte aşağıdaki adrese gönderin:

{IADE_ADRESI}

Tarafımızca bildirilen kargo firması ve gönderim bilgisiyle yapılan iadelerde **kargo ücreti tarafımıza aittir**.

## 4. Para iadesi

- Cayma bildiriminizin tarafımıza ulaştığı tarihten itibaren en geç **14 gün** içinde, teslimat ücreti dahil ödediğiniz tutar iade edilir.
- **Kartla ödemelerde** tutar ödemede kullandığınız karta iade edilir. Taksitli alışverişlerde iade, bankanızın uygulamasına göre taksitler hâlinde yansıtılabilir; tutarın hesabınıza yansıma süresi bankanıza bağlıdır.
- **Havale / EFT ve kapıda ödemede** tutar, iade talebinde bildirdiğiniz ve **sizin adınıza kayıtlı** banka hesabına (IBAN) gönderilir.
- Siparişin yalnızca bir kısmını iade ettiğinizde, iade edilen ürünlerin bedeli iade edilir; kampanya koşulları iade sonrası sağlanmıyorsa iade tutarı kampanyasız fiyatlar üzerinden hesaplanır.

## 5. Ürünün iade edilirken durumu

Cayma süresi içinde ürünü, mağazada inceler gibi kontrol edip deneyebilirsiniz; bu olağan kullanım nedeniyle oluşan değişikliklerden sorumlu tutulmazsınız. Ürünün olağan kullanımın ötesinde kullanılması, hasar görmesi veya eksik parçayla gönderilmesi hâlinde oluşan değer kaybı iade tutarından düşülebilir.

## 6. Cayma hakkının kullanılamayacağı ürünler

Mesafeli Sözleşmeler Yönetmeliği'nin 15. maddesi uyarınca aşağıdaki ürünlerde cayma hakkı kullanılamaz:

- Kişiye özel hazırlanan, isim baskılı veya isteğinize göre üretilen ürünler (ör. kişiye özel kaşe, damga, baskılı ürünler);
- Ambalajı, bandı, mührü veya jelatini açılmış olup iadesi sağlık ve hijyen açısından uygun olmayan ürünler;
- Koruyucu ambalajı veya jelatini açılmış **kitaplar**, dijital içerikler ve **bilgisayar sarf malzemeleri** (toner, kartuş, şerit vb.);
- Gazete, dergi gibi süreli yayınlar;
- Çabuk bozulabilen veya son kullanma tarihi geçebilecek ürünler.

## 7. Hasarlı, eksik veya ayıplı ürün

- Teslimat sırasında paketin hasarlı olduğunu fark ederseniz paketi teslim almamanızı ya da kargo görevlisine **hasar tespit tutanağı** düzenletmenizi rica ederiz.
- Ürün eksik, hatalı veya ayıplı çıkarsa bize fotoğrafla birlikte bildirin. 6502 sayılı Kanun'un 11. maddesi uyarınca sözleşmeden dönme, bedel indirimi, ücretsiz onarım veya ayıpsız misli ile değişim haklarınızdan dilediğinizi kullanabilirsiniz; bu durumda tüm gönderim masrafları tarafımıza aittir.
- Teslimden itibaren altı ay içinde ortaya çıkan ayıpların teslim anında var olduğu kabul edilir. Ayıplı ürün sorumluluğu teslimden itibaren iki yıldır.

## 8. İletişim ve başvuru

Her türlü iptal ve iade sorunuz için {SATICI_TELEFON} numaralı telefondan veya {SATICI_EPOSTA} adresinden bize ulaşabilirsiniz. Uyuşmazlık hâlinde, Ticaret Bakanlığınca ilan edilen parasal sınırlar dahilinde tüketici hakem heyetine veya tüketici mahkemesine başvurma hakkınız saklıdır.
MD],

    'kvkk' => ['KVKK Aydınlatma Metni', <<<'MD'
İşbu aydınlatma metni, 6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") ve Aydınlatma Yükümlülüğünün Yerine Getirilmesinde Uyulacak Usul ve Esaslar Hakkında Tebliğ uyarınca, {SATICI_WEB} internet sitesini ziyaret eden, üye olan, sipariş veren ve bizimle iletişime geçen kişileri, kişisel verilerinin işlenmesi hakkında bilgilendirmek amacıyla hazırlanmıştır.

## 1. Veri sorumlusu

- **Veri sorumlusu:** {SATICI_UNVAN} ("Şirket")
- **Adres:** {SATICI_ADRES}
- **Telefon:** {SATICI_TELEFON}
- **E-posta:** {SATICI_EPOSTA}
- **Vergi dairesi / no:** {SATICI_VERGI}

## 2. İşlenen kişisel veri kategorileri

| Kategori | Veriler |
|---|---|
| **Kimlik** | Ad, soyad; fatura için talep edilmesi hâlinde T.C. kimlik numarası |
| **İletişim** | Telefon numarası, e-posta adresi, teslimat ve fatura adresi |
| **Müşteri işlem** | Sipariş, sepet, ödeme durumu, iade ve talep kayıtları, iletişim formu mesajları, ürün yorumları |
| **Finans** | Kurumsal faturada vergi dairesi ve numarası; iade için bildirilen IBAN. Kart bilgileri Şirket tarafından görülmez ve saklanmaz. |
| **İşlem güvenliği** | IP adresi, tarayıcı ve cihaz bilgisi, oturum, giriş ve doğrulama kayıtları |
| **Pazarlama** | Ticari elektronik ileti tercihi ve onay kayıtları; izin verilmesi hâlinde site kullanım istatistikleri |

## 3. Kişisel verilerin toplanma yöntemi

Kişisel verileriniz; internet sitemizdeki üyelik, sipariş, iletişim, e-bülten ve iade formları, SMS ile doğrulama adımları, çerezler ve benzeri teknolojiler ile telefon ve e-posta yoluyla, tamamen veya kısmen otomatik yollarla elektronik ortamda toplanır.

## 4. İşleme amaçları ve hukuki sebepler

| Amaç | Hukuki sebep (KVKK m. 5) |
|---|---|
| Üyelik hesabının oluşturulması ve yönetimi | Sözleşmenin kurulması ve ifası (m. 5/2-c) |
| Siparişin alınması, ödemenin tahsili, teslimat, iade ve müşteri hizmetleri | Sözleşmenin kurulması ve ifası (m. 5/2-c) |
| Fatura düzenlenmesi, vergi ve ticaret mevzuatından doğan saklama yükümlülükleri, mesafeli sözleşme kayıtlarının saklanması | Kanunlarda açıkça öngörülmesi ve hukuki yükümlülüğün yerine getirilmesi (m. 5/2-a, ç) |
| Yetkili kurum ve kuruluşların bilgi taleplerinin karşılanması | Hukuki yükümlülüğün yerine getirilmesi (m. 5/2-ç) |
| Dolandırıcılığın önlenmesi, işlem ve bilgi güvenliğinin sağlanması | Meşru menfaat (m. 5/2-f) |
| Hukuki uyuşmazlıklarda hakların tesisi, kullanılması ve korunması | Bir hakkın tesisi, kullanılması veya korunması (m. 5/2-e) |
| Kampanya, indirim ve duyuruların ticari elektronik ileti olarak gönderilmesi | Açık rıza (m. 5/1) |
| Site kullanımının analitik çerezlerle ölçülmesi | Açık rıza (m. 5/1) |

## 5. Kişisel verilerin aktarılması

Kişisel verileriniz, yukarıdaki amaçlarla sınırlı ve ölçülü olarak aşağıdaki alıcı gruplarına aktarılabilir:

- **Ödeme kuruluşu:** Kartla ödemelerin alınması için iyzico Ödeme Hizmetleri A.Ş.;
- **Kargo ve lojistik firmaları:** Siparişin teslimi ve iadelerin alınması için;
- **Hizmet sağlayıcılar:** Sunucu ve barındırma, e-posta, SMS ve e-fatura / e-arşiv hizmeti aldığımız iş ortakları;
- **Yetkili kamu kurum ve kuruluşları ile adli makamlar:** Kanunen yetkili olmaları ve talep etmeleri hâlinde;
- **Hukuki danışmanlar ve denetçiler:** Hakların korunması ve yasal yükümlülükler kapsamında.

**Yurt dışına aktarım:** E-posta altyapısı ve (izin vermeniz hâlinde) Google Analytics gibi bazı hizmet sağlayıcılarımızın sunucuları yurt dışında bulunabilir. Bu aktarımlar KVKK'nın 9. maddesine uygun olarak, Kurul'ca ilan edilen standart sözleşmeler gibi uygun güvencelerle veya açık rızanız bulunması hâlinde gerçekleştirilir.

## 6. Saklama süreleri

| Kayıt | Süre |
|---|---|
| Sipariş, fatura ve muhasebe kayıtları | Vergi Usul Kanunu ve Türk Ticaret Kanunu uyarınca **10 yıl** |
| Mesafeli sözleşme ve ön bilgilendirme kayıtları | En az **3 yıl** |
| Ticari elektronik ileti onay kayıtları | Onayın geri alınmasından itibaren **3 yıl** |
| Üyelik bilgileri | Üyelik süresince; hesabın kapatılmasıyla anonim hâle getirilir |
| İşlem güvenliği kayıtları | İlgili mevzuatta öngörülen süre boyunca, en fazla **2 yıl** |

Süresi dolan veriler, Kişisel Verilerin Silinmesi, Yok Edilmesi veya Anonim Hale Getirilmesi Hakkında Yönetmelik uyarınca silinir, yok edilir veya anonim hâle getirilir.

## 7. Haklarınız (KVKK m. 11)

Veri sahibi olarak Şirket'e başvurarak;

- Kişisel verilerinizin işlenip işlenmediğini öğrenme ve işlenmişse buna ilişkin bilgi talep etme,
- İşlenme amacını ve bunların amacına uygun kullanılıp kullanılmadığını öğrenme,
- Yurt içinde veya yurt dışında aktarıldığı üçüncü kişileri bilme,
- Eksik veya yanlış işlenmişse düzeltilmesini, KVKK'nın 7. maddesindeki şartlar çerçevesinde silinmesini veya yok edilmesini ve bu işlemlerin aktarılan üçüncü kişilere bildirilmesini isteme,
- Münhasıran otomatik sistemlerle analiz edilmesi suretiyle aleyhinize bir sonucun ortaya çıkmasına itiraz etme,
- Kanuna aykırı işlenmesi sebebiyle zarara uğramanız hâlinde zararın giderilmesini talep etme

haklarına sahipsiniz.

## 8. Başvuru yöntemi

Haklarınıza ilişkin taleplerinizi, Veri Sorumlusuna Başvuru Usul ve Esasları Hakkında Tebliğ uyarınca kimliğinizi tespit edici bilgilerle birlikte;

- {SATICI_ADRES} adresine yazılı olarak (ıslak imzalı dilekçe ile),
- Sistemimizde kayıtlı e-posta adresinizden {SATICI_EPOSTA} adresine,
- Kayıtlı elektronik posta (KEP), güvenli elektronik imza veya mobil imza ile

iletebilirsiniz. Başvurunuz, niteliğine göre en geç **30 gün** içinde ücretsiz olarak sonuçlandırılır; işlemin ayrıca bir maliyet gerektirmesi hâlinde Kurul'ca belirlenen tarifedeki ücret alınabilir. Başvurunuzun reddedilmesi, verilen cevabı yetersiz bulmanız veya süresinde cevap verilmemesi hâlinde Kişisel Verileri Koruma Kurulu'na şikâyette bulunabilirsiniz.

Üye iseniz **Hesabım** sayfasından bilgilerinizi güncelleyebilir, ticari ileti tercihinizi değiştirebilir ve hesabınızı kapatabilirsiniz. Ticari elektronik ileti onayınızı, gönderilen her iletideki ret bağlantısıyla da dilediğiniz zaman geri alabilirsiniz.
MD],

    'gizlilik-cerez' => ['Gizlilik ve Çerez Politikası', <<<'MD'
{SATICI_UNVAN} olarak {SATICI_WEB} internet sitemizi ziyaret edenlerin gizliliğine önem veriyoruz. Bu politika, sitemizde kullanılan çerezleri, bunların hangi amaçla kullanıldığını ve tercihlerinizi nasıl yönetebileceğinizi, Kişisel Verileri Koruma Kurulu'nun Çerez Uygulamaları Hakkında Rehberi'ne uygun olarak açıklar.

## 1. Çerez nedir?

Çerezler, ziyaret ettiğiniz internet sitesinin tarayıcınıza veya cihazınıza kaydettiği küçük metin dosyalarıdır. Çerezler sayesinde site; oturumunuzu sürdürür, sepetinizi hatırlar, tercihlerinizi saklar ve (izin vermeniz hâlinde) nasıl kullanıldığını ölçebilir.

## 2. Çerez türleri ve hukuki sebep

- **Zorunlu çerezler:** Sitenin çalışması, güvenliğin sağlanması ve talep ettiğiniz hizmetin (sepet, giriş, ödeme) sunulması için gereklidir. Sözleşmenin kurulması ve ifası ile meşru menfaat hukuki sebeplerine dayanır; kapatılamaz.
- **İşlevsel çerezler:** Tercihlerinizi (ör. koyu / açık tema) hatırlar. Yalnızca izninizle kullanılır.
- **Analitik çerezler:** Sitenin nasıl kullanıldığını (ziyaret ve sayfa görüntüleme sayıları) ölçmemize yardımcı olur. Yalnızca açık rızanızla kullanılır.
- **Pazarlama çerezleri:** Şu anda kullanılmamaktadır; ileride kullanılması hâlinde yalnızca açık rızanızla etkinleşir.

## 3. Sitemizde kullanılan çerezler

| Çerez | Türü | Sağlayıcı | Amaç | Süre |
|---|---|---|---|---|
| `magaza` | Zorunlu | Sitemiz | Oturumun sürdürülmesi ve giriş | Oturum süresince (en fazla 2 saat) |
| `XSRF-TOKEN` | Zorunlu | Sitemiz | Formların sahte isteklere karşı korunması | Oturum süresince |
| `magaza_sepet` | Zorunlu | Sitemiz | Sepetinizin hatırlanması | 30 gün |
| `magaza_cerez` | Zorunlu | Sitemiz | Çerez tercihinizin saklanması | 1 yıl |
| `remember_customer_*` | Zorunlu | Sitemiz | Yalnızca "Beni hatırla" seçildiğinde oturumun açık tutulması | 5 yıla kadar |
| `magaza_onizleme` | Zorunlu | Sitemiz | Yalnızca mağaza yöneticisinin önizleme oturumu | 2 saat |
| `tema` | İşlevsel | Sitemiz | Koyu veya açık tema tercihinizin hatırlanması | 1 yıl |
| `_ga` | Analitik | Google | Ziyaretçilerin birbirinden ayırt edilmesi (Google Analytics) | 2 yıl |
| `_ga_*` | Analitik | Google | Ziyaret (oturum) bilgisinin saklanması (Google Analytics) | 2 yıl |

Siz **Çerez tercihleri** penceresinden izin vermedikçe analitik çerezler yazılmaz ve Google Analytics betiği yüklenmez; işlevsel çerezlere izin vermezseniz tema tercihiniz yalnızca tarayıcınız açıkken hatırlanır.

## 4. Üçüncü taraf içerikleri

**Google Haritalar:** İletişim sayfasındaki harita Google Haritalar'dan yüklenir. Bu sayfayı açtığınızda Google, kendi gizlilik politikası kapsamında tarayıcınıza çerez yazabilir ve IP adresiniz gibi bilgileri alabilir. Bu çerezler sitemiz tarafından yönetilmez; ayrıntılar için Google'ın gizlilik politikasına (policies.google.com) bakabilirsiniz.

**Ödeme sayfası:** Kartla ödemede kart bilgileriniz doğrudan lisanslı ödeme kuruluşu iyzico'nun güvenli ödeme sayfasında alınır; sitemizde saklanmaz. iyzico, ödeme güvenliği için kendi çerezlerini kullanabilir.

## 5. Tercihlerinizi yönetme

- Sayfanın altındaki **Çerez tercihleri** bağlantısıyla seçiminizi istediğiniz zaman değiştirebilir veya verdiğiniz izni geri alabilirsiniz.
- Tarayıcınızın ayarlarından çerezleri silebilir veya engelleyebilirsiniz. Zorunlu çerezleri engellemeniz hâlinde sepet, giriş ve ödeme gibi özellikler çalışmayabilir.
- Google Analytics'i tüm sitelerde devre dışı bırakmak için Google'ın tarayıcı eklentisini (tools.google.com/dlpage/gaoptout) kullanabilirsiniz.

## 6. Gizlilik ve güvenlik

- Sitemizle aranızdaki tüm iletişim SSL / TLS şifrelemesiyle korunur.
- Hesap şifreniz geri döndürülemez biçimde (özetlenerek) saklanır; çalışanlarımız dahil kimse tarafından görüntülenemez.
- Kişisel verileriniz satılmaz, kiralanmaz ve izniniz olmadan pazarlama amacıyla üçüncü kişilerle paylaşılmaz.
- Kişisel verilerinizin işlenmesine ilişkin tüm ayrıntılar ve haklarınız **KVKK Aydınlatma Metni**'nde yer alır.

## 7. Değişiklikler ve iletişim

Bu politika, kullandığımız çerezler veya mevzuat değiştiğinde güncellenir; güncel sürüm her zaman bu sayfada yayımlanır. Sorularınız için: {SATICI_UNVAN} · {SATICI_TELEFON} · {SATICI_EPOSTA}
MD],
];
