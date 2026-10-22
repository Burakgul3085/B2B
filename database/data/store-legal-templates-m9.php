<?php

/*
| M9 ile eklenen mağaza yasal metinlerinin İLK sürümleri (örnek şablon). Yayından önce hukukçu tarafından gözden
| geçirilmeli; mağaza yönetiminde "Yasal Metinler" ekranından düzenlenir (her kayıt yeni sürüm oluşturur).
| Yer tutucular: App\Domain\Store\Orders\LegalDocuments::PLACEHOLDERS
*/

return [
    'iptal-iade' => ['İptal ve İade Koşulları', <<<'MD'
## Cayma hakkı

Mesafeli Sözleşmeler Yönetmeliği uyarınca, ürünün size veya gösterdiğiniz kişiye tesliminden itibaren **{IADE_SURESI} gün** içinde hiçbir gerekçe göstermeden ve cezai şart ödemeden cayma hakkınızı kullanabilirsiniz.

## İade talebi nasıl açılır?

- Üye iseniz **Hesabım → Siparişlerim** sayfasından, misafir alışverişlerde sipariş e-postanızdaki bağlantıdan siparişinizi açın ve **İade talebi oluştur** düğmesini kullanın.
- Talebiniz onaylandığında ürünleri, faturası veya sipariş numarasıyla birlikte aşağıdaki adrese gönderebilirsiniz:

{IADE_ADRESI}

## Para iadesi

- Ürün elimize ulaşıp kontrol edildikten sonra en geç **14 gün** içinde iade yapılır.
- Kartla ödemelerde tutar ödemede kullandığınız karta iade edilir; hesabınıza yansıma süresi bankanıza bağlıdır.
- Kapıda ödemelerde tutar, iade talebinde bildirdiğiniz IBAN'a havale edilir.
- Siparişteki tüm ürünlerin iadesinde teslimat ücreti de iade edilir.

## Cayma hakkının kullanılamayacağı ürünler

Ambalajı açılmış ve iadesi sağlık ve hijyen açısından uygun olmayan ürünler, tüketicinin istekleri veya kişisel ihtiyaçları doğrultusunda hazırlanan ürünler ile Yönetmelik'in 15. maddesinde sayılan diğer ürünlerde cayma hakkı kullanılamaz.

## Sipariş iptali

Siparişiniz kargoya / teslimata çıkmadan önce iptal için bize ulaşabilirsiniz: {SATICI_TELEFON} · {SATICI_EPOSTA}

## Hasarlı veya ayıplı ürün

Teslimatta hasarlı paketi teslim almamanızı ve tutanak tutturmanızı öneririz. Ayıplı ürünlerde 6502 sayılı Kanun'daki seçimlik haklarınızı kullanabilirsiniz.
MD],

    'kvkk' => ['KVKK Aydınlatma Metni', <<<'MD'
## Veri sorumlusu

{SATICI_UNVAN} ("Şirket"), {SATICI_ADRES}. 6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") uyarınca veri sorumlusu sıfatıyla kişisel verilerinizi aşağıda açıklanan kapsamda işler.

## İşlenen kişisel veriler

- **Kimlik ve iletişim:** ad, soyad, telefon, e-posta, teslimat ve fatura adresi, (isteğe bağlı) T.C. kimlik numarası, kurumsal faturada vergi bilgileri.
- **Müşteri işlem:** sipariş, ödeme durumu, iade ve talepler. Kart bilgileriniz Şirket tarafından görülmez ve saklanmaz; ödeme kuruluşu (iyzico) tarafından işlenir.
- **İşlem güvenliği:** IP adresi, tarayıcı bilgisi, oturum ve doğrulama kayıtları.
- **Pazarlama:** yalnızca açık rıza vermeniz hâlinde ticari ileti tercihiniz.

## İşleme amaçları ve hukuki sebepler

- Siparişin alınması, ödemenin tahsili, teslimat, iade ve faturalandırma — sözleşmenin kurulması ve ifası (KVKK m. 5/2-c).
- Vergi ve ticaret mevzuatından doğan saklama yükümlülükleri — kanunlarda öngörülme ve hukuki yükümlülük (m. 5/2-a, ç).
- Dolandırıcılığın önlenmesi ve işlem güvenliği — meşru menfaat (m. 5/2-f).
- Kampanya ve duyuruların iletilmesi — açık rıza (m. 5/1).

## Aktarım

Kişisel verileriniz yalnızca yukarıdaki amaçlarla sınırlı olarak ödeme kuruluşuna (iyzico), kargo firmalarına, SMS ve e-posta hizmet sağlayıcılarına, e-Arşiv fatura entegratörüne ve yetkili kamu kurumlarına aktarılabilir.

## Saklama süresi

Sipariş ve fatura kayıtları mevzuatın öngördüğü süre (10 yıl) boyunca saklanır. Hesabınızı kapattığınızda hesap bilgileriniz anonim hâle getirilir; yasal saklama süresi bulunan sipariş kayıtları bu süre sonunda silinir.

## Haklarınız (KVKK m. 11)

Kişisel verilerinizin işlenip işlenmediğini öğrenme, bilgi talep etme, düzeltilmesini veya silinmesini isteme, aktarıldığı üçüncü kişileri bilme, itiraz etme ve zararın giderilmesini talep etme haklarına sahipsiniz. Başvurularınızı {SATICI_EPOSTA} adresine veya {SATICI_ADRES} adresine yazılı olarak iletebilirsiniz. Üye iseniz **Hesabım** sayfasından hesabınızı kendiniz kapatabilirsiniz.
MD],

    'gizlilik-cerez' => ['Gizlilik ve Çerez Politikası', <<<'MD'
## Çerez nedir?

Çerezler, ziyaret ettiğiniz sitenin tarayıcınıza kaydettiği küçük metin dosyalarıdır.

## Kullandığımız çerezler

| Çerez | Türü | Amaç | Süre |
|---|---|---|---|
| `magaza` | Zorunlu | Oturumun sürdürülmesi ve giriş | Oturum / 2 saat |
| `XSRF-TOKEN` | Zorunlu | Form güvenliği | Oturum |
| `magaza_sepet` | Zorunlu | Misafir sepetinin hatırlanması | 30 gün |
| `magaza_cerez` | Zorunlu | Çerez tercihinizin hatırlanması | 1 yıl |
| `tema` | Tercih | Seçtiğiniz görünümün (açık/koyu) hatırlanması; yalnızca tema seçtiğinizde yazılır | 1 yıl |
| `remember_*` | Zorunlu (isteğe bağlı giriş) | "Beni hatırla" seçildiğinde | 1 yıl |

Zorunlu çerezler sitenin çalışması için gereklidir ve kapatılamaz. Şu anda analiz veya reklam çerezi kullanılmamaktadır; ileride kullanılırsa yalnızca onay vermeniz hâlinde etkinleşir.

## Tercihlerinizi değiştirme

Sayfanın altındaki **Çerez tercihleri** bağlantısıyla seçiminizi istediğiniz zaman değiştirebilirsiniz. Tarayıcınızın ayarlarından da çerezleri silebilir veya engelleyebilirsiniz; bu durumda sepet ve giriş gibi özellikler çalışmayabilir.

## Gizlilik

Ödeme sırasında kart bilgileriniz doğrudan iyzico'nun güvenli ödeme sayfasında alınır; sitemizde saklanmaz. Kişisel verilerinizin işlenmesine ilişkin ayrıntılar **KVKK Aydınlatma Metni**'ndedir.

## İletişim

{SATICI_UNVAN} · {SATICI_TELEFON} · {SATICI_EPOSTA}
MD],
];
