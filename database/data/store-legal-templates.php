<?php

/*
| Mağaza yasal metinlerinin İLK sürümleri (örnek şablon). Yayından önce hukukçu tarafından gözden geçirilmeli;
| mağaza yönetiminde "Yasal Metinler" ekranından düzenlenir (her kayıt yeni sürüm oluşturur).
| {ALAN} biçimindeki yer tutucular sipariş anında doldurulur; liste: App\Domain\Store\Orders\LegalDocuments::PLACEHOLDERS
*/

return [
    'on-bilgilendirme' => ['Ön Bilgilendirme Formu', <<<'MD'
## 1. Satıcı bilgileri

- **Unvan:** {SATICI_UNVAN}
- **Adres:** {SATICI_ADRES}
- **Telefon:** {SATICI_TELEFON}
- **E-posta:** {SATICI_EPOSTA}
- **Vergi dairesi / no:** {SATICI_VERGI}
- **MERSİS no:** {SATICI_MERSIS}

## 2. Alıcı bilgileri

- **Ad soyad:** {ALICI_AD}
- **Teslimat adresi:** {ALICI_ADRES}
- **Telefon:** {ALICI_TELEFON}
- **E-posta:** {ALICI_EPOSTA}

## 3. Sözleşme konusu ürünler

{URUNLER}

- **Teslimat ücreti:** {TESLIMAT_UCRETI}
- **Kapıda ödeme hizmet bedeli:** {KAPIDA_ODEME_BEDELI}
- **Toplam (KDV dahil):** {TOPLAM}
- **Ödeme yöntemi:** {ODEME_YONTEMI}
- **Teslimat yöntemi:** {TESLIMAT_YONTEMI}

## 4. Teslimat

Ürünler, sipariş onayından itibaren en geç 30 gün içinde alıcının bildirdiği teslimat adresine teslim edilir.

## 5. Cayma hakkı

Alıcı, ürünü teslim aldığı tarihten itibaren 14 gün içinde herhangi bir gerekçe göstermeksizin ve cezai şart ödemeksizin sözleşmeden cayma hakkına sahiptir. Cayma bildirimi satıcının yukarıdaki iletişim bilgilerine yazılı olarak veya e-postayla yapılabilir. Cayma hakkının kullanılmasında ürünün kullanılmamış ve yeniden satılabilir durumda olması gerekir. Satıcı, cayma bildiriminin ulaşmasından itibaren 14 gün içinde ürün bedelini alıcının ödeme yöntemine uygun şekilde iade eder.

Mesafeli Sözleşmeler Yönetmeliği'nin 15. maddesinde sayılan ürünlerde (ör. ambalajı açılmış hijyen ürünleri, alıcının isteğine göre kişiselleştirilmiş ürünler) cayma hakkı kullanılamaz.

## 6. Şikâyet ve itirazlar

Alıcı, şikâyet ve itirazlarını 6502 sayılı Tüketicinin Korunması Hakkında Kanun kapsamında belirlenen parasal sınırlar dahilinde tüketici hakem heyetlerine veya tüketici mahkemelerine iletebilir.

Tarih: {TARIH}
MD],

    'mesafeli-satis' => ['Mesafeli Satış Sözleşmesi', <<<'MD'
## Madde 1 — Taraflar

**Satıcı:** {SATICI_UNVAN}, {SATICI_ADRES}, Tel: {SATICI_TELEFON}, E-posta: {SATICI_EPOSTA}, Vergi: {SATICI_VERGI}, MERSİS: {SATICI_MERSIS}

**Alıcı:** {ALICI_AD}, {ALICI_ADRES}, Tel: {ALICI_TELEFON}, E-posta: {ALICI_EPOSTA}

## Madde 2 — Konu

Bu sözleşmenin konusu, alıcının satıcıya ait internet sitesi üzerinden elektronik ortamda siparişini verdiği aşağıda nitelikleri ve satış fiyatı belirtilen ürünlerin satışı ve teslimi ile ilgili olarak 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği hükümleri gereğince tarafların hak ve yükümlülüklerinin belirlenmesidir.

## Madde 3 — Ürünler ve bedel

{URUNLER}

- **Teslimat ücreti:** {TESLIMAT_UCRETI}
- **Kapıda ödeme hizmet bedeli:** {KAPIDA_ODEME_BEDELI}
- **Toplam (KDV dahil):** {TOPLAM}
- **Ödeme yöntemi:** {ODEME_YONTEMI}
- **Teslimat yöntemi:** {TESLIMAT_YONTEMI}

## Madde 4 — Genel hükümler

1. Alıcı, ürünlerin temel nitelikleri, satış fiyatı, ödeme şekli ve teslimata ilişkin ön bilgileri okuyup bilgi sahibi olduğunu ve elektronik ortamda gerekli onayı verdiğini kabul eder.
2. Ürünler, sipariş onayından itibaren yasal 30 günlük süreyi aşmamak kaydıyla alıcının belirttiği adrese teslim edilir.
3. Satıcı, sipariş konusu ürünün tedarikinin imkânsızlaştığı hâllerde bu durumu alıcıya bildirir ve ödenen bedeli 14 gün içinde iade eder.
4. Kartla ödemelerde ödeme bilgileri satıcı tarafından saklanmaz; ödeme, lisanslı ödeme kuruluşu aracılığıyla alınır.

## Madde 5 — Cayma hakkı

Alıcı, ürünün kendisine veya gösterdiği kişiye tesliminden itibaren 14 gün içinde cayma hakkını kullanabilir. Cayma hakkının kullanılması için bu süre içinde satıcıya yazılı bildirimde bulunulması ve ürünün kullanılmamış olması gerekir. Satıcı, cayma bildiriminin kendisine ulaşmasından itibaren 14 gün içinde ürün bedelini iade eder.

## Madde 6 — Yetkili mahkeme

Bu sözleşmeden doğan uyuşmazlıklarda Ticaret Bakanlığınca ilan edilen değere kadar tüketici hakem heyetleri, bu değerin üzerindeki uyuşmazlıklarda tüketici mahkemeleri yetkilidir.

## Madde 7 — Yürürlük

Alıcı, siparişi onaylamakla bu sözleşmenin tüm koşullarını kabul etmiş sayılır.

Tarih: {TARIH}
MD],
];
