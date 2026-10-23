# Toptancı paneli kullanım kılavuzu

Toptancı paneli Yapboz Kırtasiye'nin bayilerle çalıştığı yerdir: ürünler, fiyatlar, stok, bayiler, siparişler ve cari hesaplar burada yönetilir. Adres: `https://<alan adı>/toptanci`. İşletmede tek kullanıcı vardır.

> Mağaza (perakende site) bu panelden yönetilmez; onun kendi yönetim paneli vardır ([magaza-yonetimi.md](magaza-yonetimi.md)). İki taraf ürün, stok veya müşteri paylaşmaz.

## 1. İlk kurulum (bir kez)

Önerilen sıra:

1. **Ayarlar → E-posta Ayarları:** İşletmenin Gmail adresini ve 16 haneli **uygulama şifresini** girin, "Bağlantıyı test et" ile deneyin. Bütün e-postalar (sipariş, başvuru, parola sıfırlama) bu hesaptan gider; yeni sipariş ve başvuru bildirimleri de bu adrese düşer.
2. **Ayarlar → İşletme Ayarları:** Unvan, iletişim, logo; cari borcun ne zaman yazılacağı (sipariş onayında veya tamamlanınca), bayiye stoğun nasıl gösterileceği.
3. **Ayarlar → KDV ve Birimler:** Kullandığınız KDV oranları ve satış birimleri (adet, paket, koli…).
4. **Katalog → Kategori ve Markalar**, ardından **Katalog → Ürünler** (tek tek veya Excel ile içe aktarma).
5. **Stok → Depolar ve Sayım:** Depoyu tanımlayın, açılış stoklarını girin.
6. **Katalog → Fiyatlandırma:** Bayi grupları, fiyat listeleri ve indirimler (gerekirse).
7. **Bayiler:** Bayileri açın veya gelen başvuruları onaylayın.
8. **Ayarlar → Profil ve Güvenlik:** Kendi şifrenizi değiştirin.

## 2. Ürünler ve katalog

- Ürün: ad, kod (SKU), barkod, kategori, marka, birim, KDV oranı, liste fiyatı ve bayi fiyatı, en az sipariş miktarı ve sipariş adımı (ör. 12'nin katları), kritik stok seviyesi, görseller.
- Arşivlenen ürün bayilere görünmez. Geçmiş siparişler etkilenmez, çünkü sipariş kalemi ürünün o anki adını, fiyatını ve KDV'sini kendi üzerinde saklar.
- **Katalog → Vitrin:** Bayi ana sayfasındaki slaytlar (görsel veya en fazla 50 MB video) ve ürün vitrinleri.

## 3. Fiyatlandırma

Bayiye gösterilen fiyat şu sırayla belirlenir; ilk bulunan kullanılır:

1. Bayiye özel ürün fiyatı. Bu nihai fiyattır, üzerine indirim uygulanmaz.
2. Bayinin grubuna atanmış fiyat listesindeki fiyat.
3. Ürünün bayi fiyatı.
4. Ürünün liste fiyatı.

2–4'ten gelen fiyata **tek bir indirim** uygulanabilir. Önce ürün bazlı indirim aranır, sonra kategori bazlı. Her birinde sıra şöyledir: bayiye özel, sonra bayi grubu, sonra tüm bayiler. Aynı seviyede birden fazla kural geçerliyse en son başlayan uygulanır. Kural eklerken çakışma varsa uyarı görürsünüz.

Fiyat değişikliği yalnızca yeni siparişleri etkiler.

## 4. Bayiler

- **Bayiler → Bayiler → Yeni bayi:** Giriş e-postası, ad soyad ve şifreyi siz belirlersiniz; hesap hemen etkin olur. Bayiye giriş e-postasını bildiren bir e-posta gider, şifre e-postayla gönderilmez. Şifreyi bayiye kendiniz iletin.
- Bayi detayında:
  - bayi kodu, grup, vade günü, adres düzenleme izni
  - sevk adresleri, cari hesap, siparişler
  - "yeni şifre belirle"
  - ilişkiyi pasifleştirme. Pasif bayi giriş yapabilir ama sipariş veremez.
- **Bayiler → Başvurular:** Bayi giriş sayfasındaki "Bayimiz olmak ister misiniz?" formundan gelen başvurular. Başvuruyu inceleyebilir, ek bilgi isteyebilir, reddedebilir (gerekçe zorunlu) veya onaylayabilirsiniz.
  - **Ek bilgi isteme:** Başvurana e-postayla bir bağlantı gider; başvuran web üzerinden yanıt verir.
  - **Onay:** Giriş e-postası ve şifreyi siz belirlersiniz. Bayi hesabı, cari hesap ve ilk sevk adresi hemen oluşur.
- **Bayiler → Bayi Grupları:** Fiyat listesi ve indirimleri grup halinde uygulamak için.

## 5. Siparişler

Bayi siparişi **Satış → Siparişler**'e "Toptancıya iletildi" durumunda düşer. Ana e-posta adresinize de bildirim gelir.

| Adım | Ne olur |
|---|---|
| İncelemeye al | İsteğe bağlı ara adım. |
| **Siparişi onayla** | Stok o anda düşer, cari borç yazılır (ayar "onayda" ise). Stok yetmezse kalem kalem "istenen / mevcut" gösterilir; miktarı düşürüp **kısmi** onaylayabilir veya reddedebilirsiniz. |
| Reddet | Gerekçe zorunlu. Stok ve cari etkilenmez. |
| Hazırlanıyor → Tamamlandı | İç operasyon durumları. Hazırlık adımı atlanabilir. |
| İptal et | Onaylanmış siparişte stok ve cari ters kayıtla geri alınır. |
| İptali kabul et | Bayinin onaylanmış sipariş için gönderdiği iptal talebi. |
| İadeyi kaydet | Tamamlanmış siparişten kalem ve miktar seçilir. Stok geri girer, bayinin carisine alacak yazılır. |

Sepette bekleyen veya yeni oluşturulmuş sipariş stok ayırmaz. Stok yalnızca onayda kesinleşir. B2B'de kargo takibi yoktur.

## 6. Stok

- **Stok Durumu:** Depo bazında miktarlar ve kritik stok uyarıları.
- **Stok Hareketleri:** Her giriş, çıkış, satış, iade ve sayım farkı burada görünür. Hareketler silinmez; hatalı kayıt ters kayıtla düzeltilir.
- **Depolar ve Sayım:** Depo tanımlama, depolar arası transfer, sayım. Sayım uygulandığı anda sistem farkı yeniden hesaplar; sayım sırasında satış olsa da doğru fark yazılır.

## 7. Cari hesap ve tahsilat

- **Finans → Cari Hesaplar:** Bayilerin bakiyesi, vadesi geçmiş tutarı, ekstresi. Bakiye her zaman hareketlerden hesaplanır.
- **Finans → Tahsilatlar:** Bayiden aldığınız ödemeyi elle girin: tarih, tutar, yöntem, isteğe bağlı sipariş, açıklama. Sistem ödeme almaz, bankayı kontrol etmez. Hatalı tahsilat silinmez; gerekçeyle iptal edilir ve ters kayıt oluşur.
- Açılış bakiyesi ve elle düzeltme: açıklama zorunludur, işlem geçmişine yazılır.
- Cari modül resmî muhasebe değildir.

## 8. İletişim ve raporlar

- **Duyurular:** Bayi ana sayfasında görünen duyurular.
- **Mesajlar:** Modül açıksa bayilerle sipariş veya genel konu bazında yazışma.
- **Ürün Talepleri:** Modül açıksa bayilerin stokta olmayan ürün talepleri. Talep stok ayırmaz. Ürüne stok girince "haber ver" diyen bayiye bildirim gider.
- **Raporlar:** Modül açıksa siparişler, kalemler, en çok satanlar, bayi hacmi, cari bakiyeler, tahsilatlar, stok ve kritik stok raporları. Excel ve PDF olarak alınabilir. Sık kullandığınız filtreleri kaydedebilirsiniz.

## 9. Güvenlik ve destek

- **Ayarlar → Destek Erişimi:** Platform yöneticisinin panelinize geçici olarak bakabilmesi için süreli izin verirsiniz. İzin süresi dolunca veya geri alınca erişim kapanır, kullanılan her erişim kayda geçer.
- Görünüm: sağ üstteki menüden açık, koyu veya sistem teması.
- Parolanızı unutursanız giriş sayfasındaki "Parolamı unuttum" bağlantısını kullanın.
