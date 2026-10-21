# Mağaza M5 — Müşteri Hesabı, Adres Defteri ve Sepet

> Testler: 306/306. Mimari: [13-magaza-mimarisi.md](13-magaza-mimarisi.md) §7–8.

## 1. İl ve ilçe listesi

- `tr_provinces` (kimlik = plaka kodu; Eskişehir = 26) ve `tr_districts`: **81 il, 973 ilçe**.
- Kaynak: `database/data/turkiye-il-ilce.json`. Vikipedi'deki "Türkiye'nin ilçeleri" tablosundan alındı ve Türkçe alfabeyle sıralandı.
- Veri migration ile yüklenir; her ortamda (testler dahil) hazırdır.
- Eskişehir'in 14 ilçesi kapıda ödeme bölge kontrolünde (M6) kullanılacak.
- `/ilceler/{il}`: adres formundaki ilçe listesi (JSON, 1 gün önbellek).

## 2. Müşteri hesabı (guard `customer`)

- `store_customers`: ad, soyad, e-posta (benzersiz), cep telefonu, parola, KVKK onay zamanı, ticari ileti izni zamanı, etkinlik, son giriş.
- `Area::Customer`: mağaza alan adında oturum açar; çerezi `magaza`.
  - Müşteri oturumu mağaza yönetimini ve B2B'yi açmaz.
  - Bayi hesabı mağazaya giremez (ayrı tablolar).
- **Üyelik:**
  - Hesap hemen etkindir (B2B'deki gibi doğrulama bağlantısı yok).
  - KVKK aydınlatma metninin okunduğu onaylanır (zorunlu); ticari ileti izni isteğe bağlıdır. İkisinin zamanı da saklanır.
  - Telefon `05xxxxxxxxx` biçimine çevrilir (`+90…`, boşluklu yazımlar kabul edilir).
  - Saatte 10 üyelik isteği sınırı vardır.
- **Giriş:**
  - E-posta + IP ve IP başına deneme sınırı (B2B ile aynı ayarlar).
  - Hesap varlığı sızdırılmaz.
  - "Beni hatırla" vardır.
  - Girişte ve üyelikte misafir sepeti hesaba katılır.
- **Parolamı unuttum:** Sıfırlama bağlantısı tek kullanımlıktır, süreli ve özetlenmiş (hash) olarak saklanır. Yanıt, kayıtlı e-posta olsun olmasın aynıdır. E-posta sistem posta hesabından gider.
- **Hesabım:**
  - Siparişlerim (M6'da dolacak), adreslerim.
  - Bilgilerim: ad, soyad, telefon, ticari ileti izni.
  - E-posta değişikliği parolayla onaylanır.
  - Parola değişikliği diğer cihazlardaki oturumları kapatır.

## 3. Adres defteri

- `store_customer_addresses`: başlık, ad, soyad, telefon, il, ilçe, mahalle, açık adres, posta kodu, varsayılan.
- İlçenin seçilen ile ait olduğu sunucuda denetlenir.
- İlk adres varsayılan olur; varsayılan silinirse kalan ilk adres varsayılan olur.
- Müşteri başına en fazla 20 adres.
- Başkasının adresi görülemez, değiştirilemez, silinemez (404).

## 4. Sepet

- `store_carts` / `store_cart_items`:
  - Misafir sepeti tarayıcıdaki şifreli çerezde (`magaza_sepet`, 30 gün) tutulan belirteçle eşleşir.
  - Üye sepeti hesaba bağlıdır.
  - **Sepette fiyat saklanmaz.**
- `CartService`:
  - Ekleme ve adet değişikliği **satılabilir stokla** sınırlıdır: stoktan, ödeme aşamasındaki ayırmalar düşülür. Mesaj kalan adedi söyler ("en fazla 1 adet daha…").
  - Yayında olmayan ürün veya satışta olmayan seçenek eklenemez.
  - Ekleme başarısızsa yeni misafir sepeti oluşmaz.
  - Başka sepetin satırına dokunulamaz (404).
- **Özet (`CartSummary`):** Fiyat ve KDV her gösterimde güncel değerlerle hesaplanır.
  - Satır KDV'si = toplam × oran / (100 + oran), yarım yukarı yuvarlanır.
  - KDV oranlarına göre döküm gösterilir.
  - Stoğu düşen ya da satıştan kalkan satır işaretlenir ve toplama girmez.
- **Girişte birleştirme:** Misafir sepetindeki adetler hesap sepetine eklenir, satılabilir stokla sınırlanır; misafir sepeti silinir.
- **Ekranlar:**
  - Başlıkta hesap ve sepet simgeleri (sepet adedi rozetle).
  - Ürün sayfasında seçenek ve adetle "Sepete ekle"; tükenen seçenekte düğme pasif.
  - Kartlarda tek seçenekli ürün için hızlı ekleme; seçenekli ürün ürün sayfasına yönlendirir.
  - Sepet sayfası: adet seçimi, kaldırma, özet.
  - "Siparişi tamamla" M6'ya kadar pasif.
- Liste sayfasında filtre formu yalnızca kenar çubuğunu sarar (sıralama `form` özniteliğiyle bağlıdır); böylece kartlardaki sepet formları iç içe olmaz.

## 5. Düzeltilen hata (M1'den)

`auth_tokens.guard` sütunu yalnızca `platform`, `business` ve `dealer` değerlerini kabul ediyordu. Bu yüzden **mağaza yöneticisinin "Parolamı unuttum" isteği bağlantı üretemiyordu**. Sütuna `store_admin` ve `customer` eklendi. M1 testi yalnızca kayıtlı olmayan bir e-postayı denediği için hatayı yakalamamıştı; artık gerçek hesapla da denetleniyor.

## 6. Testler (bu aşamada 12 yeni test)

| Dosya | Kapsam |
|---|---|
| `Store/StoreCustomerTest` (6) | İl/ilçe verisi ve uç noktası; üyelik (KVKK, telefon, e-posta tekrarı); giriş/çıkış, pasif hesap, alan ayrımı; parola sıfırlama (tek kullanımlık); profil, e-posta ve parola; adres defteri (il-ilçe uyumu, varsayılan, başkasının adresi 404) |
| `Store/StoreCartTest` (6) | Misafir ekleme ve stok sınırı (ayırmalar dahil); satılamaz ürünler; güncel fiyat, KDV dökümü, stok sorunlu satır; adet ve çıkarma (yalnızca kendi sepeti); girişte birleştirme; sayfalar ve formlar |
| `Store/StoreAdminAuthTest` (+1 doğrulama) | Mağaza yöneticisi için sıfırlama bağlantısı üretimi |
| `Security/InputFuzzTest` | Mağaza giriş ve parola formları da bozuk girdi taramasına girdi (mağaza açık) |

## 7. M6 gereksinimleri (Sipariş ve ödeme)

- Ödeme sayfası: adres (kayıtlı adres veya misafir için form), fatura bilgisi (bireysel/kurumsal), teslimat yöntemi (Eskişehir'de "kendi teslimatım", diğer illerde kargo), ödeme yöntemi.
- Ön Bilgilendirme Formu ve Mesafeli Satış Sözleşmesi onayı (sürüm ve onay kaydı).
- Sipariş oluşurken stok ayırma (15 dk) → iyzico Ödeme Formu (sandbox) → sonucun sunucudan doğrulanması → satışa çevirme; başarısız/terk edilen ödemede ayırmanın bırakılması.
- Kapıda ödeme (Eskişehir, izinli ilçeler, tutar sınırı, açık sipariş sınırı, engel listesi) + Netgsm/log SMS doğrulama.
- Sipariş e-postaları; "Siparişlerim" ve yönetim panelinde siparişler.
- Satışı olan ürünün silinmesinin engellenmesi (stok hareketi kuralıyla zaten sağlanıyor).
