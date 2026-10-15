# Aşama 4 Raporu — Bayilik ve Fiyatlandırma

> Tarih: 2026-10-17 · Durum: **Tamamlandı** · Testler: 113/113 geçti

## Yapılanlar

### Veritabanı
`dealer_applications`, `attachments`, `dealer_addresses`, `current_accounts`, `price_lists`, `price_list_items`, `dealer_product_prices`, `discount_rules`; `dealer_groups.price_list_id`. Tüm ilişkiler bileşik yabancı anahtarla aynı işletmeye bağlı. Fiyat ve indirim oranında `CHECK` kısıtları (negatif fiyat; %0 veya %100 ve üstü indirim veritabanına giremez).

### Herkese açık başvuru sayfası — `/basvuru/{işletme-kısa-adı}`
- İşletme logosu ve rengiyle; ürün, fiyat veya stok bilgisi **göstermez**.
- Alanlar: unvan, yetkili, telefon, e-posta, il/ilçe/adres, işletme türü, faaliyet alanı, vergi bilgileri (isteğe bağlı), açıklama, en fazla 3 belge (PDF/JPG/PNG, 5 MB).
- Aydınlatma metni onayı zorunlu; onay zamanı, metin sürümü ve IP kaydedilir. Metin şablondur — **işletme tarafından hukuki olarak gözden geçirilmelidir.**
- Kötüye kullanım önlemleri: IP başına saatte 5 gönderim, görünmez bot tuzağı alanı, aynı e-postayla açık başvuru varken yenisinin reddi, belgelerin içeriğe göre doğrulanması (PDF imzası, görsel başlığı).
- Askıdaki veya olmayan işletmenin sayfası 404.

### Başvuru yönetimi (toptancı)
- Liste: açık / onaylanan / reddedilen / iptal sekmeleri, arama, menüde açık başvuru sayısı rozeti, form linkini kopyalama.
- Durumlar: yeni → inceleniyor → ek bilgi bekleniyor → onaylandı / reddedildi / iptal. Tanımsız geçiş reddedilir; ret gerekçesi zorunlu.
- **Onay tek transaction'da:** bayi (yoksa) + giriş hesabı (parolasız, aktivasyon bekliyor) + toptancı–bayi ilişkisi + cari hesap (bakiye 0) + ilk sevk adresi + tek kullanımlık aktivasyon bağlantısı. Aynı başvuru iki kez onaylanamaz (satır kilidi + durum kontrolü).
- **K11:** E-posta platformda başka bir toptancının bayisine aitse yeni hesap açılmaz; mevcut hesaba bu toptancıyla çalışma yetkisi eklenir. Bayi giriş yaptığında toptancı seçim ekranında yeni toptancıyı görür.
- Lisans bayi sınırı uygulanıyor.
- Toptancı başvuru beklemeden **doğrudan bayi hesabı** da açabilir.

### Bayi yönetimi
- Liste (arama, grup, durum filtresi; hesap etkinleşti mi bilgisi).
- Bayi sayfası sekmeleri: **Genel** (bilgiler, bayi kodu, grup, vade, adres değiştirme izni, durum: aktif/askı/kapalı — gerekçe zorunlu), **Sevk adresleri**, **Özel fiyatlar** (tarih aralıklı), **Fiyat önizleme** (bu bayinin göreceği fiyatı, kaynağını ve uygulanan indirimi ürün ürün gösterir).
- Bekleyen hesaplar için yeni aktivasyon bağlantısı (eskiler geçersiz olur).
- Bayi grupları: gruba fiyat listesi atama; bayisi veya indirimi olan grup silinemez.

### Fiyatlandırma — `PriceResolver`
| Sıra | Kaynak | Not |
|---|---|---|
| 1 | Bayiye özel fiyat (tarih aralığında) | Nihai fiyat; indirim **uygulanmaz** |
| 2 | Bayi grubunun fiyat listesi (liste aktif ve tarih aralığında) | |
| 3 | Ürünün standart bayi fiyatı | |
| 4 | Ürünün liste fiyatı | |
| + | Tek indirim (2–4 için) | Önce ürün indirimi, yoksa alt kategori, yoksa üst kategori indirimi. Aynı seviyede bayiye özel > gruba > herkese; yine eşitse en son başlayan. İndirimler toplanmaz. |

- Tüm fiyatlar ürünün "KDV dahil" ayarına göre yorumlanıp **net (KDV hariç), 4 ondalık** hesaplanır. Yuvarlama her yerde tek fonksiyonla: **yarım yukarı**.
- `resolveMany()` katalog için toplu çalışır (ürün başına ayrı sorgu yok).
- Fiyat listesi ekranı: ürün ekleme/güncelleme/çıkarma, kategoriye göre **toplu doldurma** (standart bayi fiyatı ± %).
- Bayi tarafında henüz fiyat gösterilmiyor; katalog Aşama 5'te bu servisi kullanacak.

### Sevk adresleri
- **Değişiklik (doküman 02'ye göre):** adresler bayi şirketine değil **toptancı–bayi ilişkisine** bağlandı. Gerekçe: "adres değiştirebilir" izni toptancı bazında ve her toptancı yalnızca kendisiyle paylaşılan adresleri görmeli. Bayi iki toptancıyla çalışıyorsa adres listeleri ayrıdır.
- Bayi paneli: adres ekle/düzenle/kaldır, tek varsayılan adres. İzin kapalıysa bayi yalnızca görür ve varsayılanı seçer; ekleme/düzenleme 403.
- Toptancı da bayinin adreslerini yönetebilir.
- Adresler silinmez, arşivlenir (siparişler zaten kendi kopyasını taşıyacak).

### Diğer
- İşletme logosu yükleme (WEBP'ye yeniden kodlanır), ayarlarda başvuru sayfası bağlantısı.
- Bayi işletme profili (iletişim bilgileri düzenlenebilir; unvan ve vergi bilgileri yalnızca görüntülenir).
- Lisans **ürün sınırı** uygulanıyor (Aşama 3'ten ertelenmişti).
- Geliştirme verisi: Onur Kırtasiye için cari hesap ve adres, "A Grubu" + fiyat listesi, "Defter kampanyası" (%10 kategori indirimi), bekleyen örnek başvuru (Deniz Kitabevi).

## Testler (bu aşamada eklenen 32 test)

- **Fiyat önceliği (10):** bayi fiyatı → liste fiyatı geri düşüşü; grup listesinin yalnızca geçerlilik tarihinde uygulanması; bayiye özel fiyatın kazanıp indirim almaması; özel fiyatın tarih aralığı; ürün indiriminin kategori indirimini yenmesi ve toplanmaması; alt kategori > üst kategori, bayiye özel > herkese; başka bayi/grup, pasif ve süresi geçmiş kuralların yok sayılması; KDV dahil fiyatın nete çevrilmesi; indirim yuvarlaması (7,25 × %92,5 = 6,7063); toplu ve tekil hesabın aynı sonucu vermesi.
- **Başvuru ve onay (13):** form → başvuru + onay kaydı + belge; onay kutusu zorunlu; PDF kılığındaki PHP dosyası reddi; bot tuzağı; mükerrer açık başvuru; askıdaki işletmenin sayfası; onay → hesap/ilişki/cari/adres + **aktivasyonla gerçek giriş**; çift onay engeli; mevcut bayi hesabına ilişki ekleme (K11); ret gerekçesi ve reddedilenin onaylanamaması; lisans bayi sınırı; doğrudan bayi açma; başka işletmenin başvurusuna, belgesine ve onay ucuna 404; askıya alınan bayinin panelden düşmesi.
- **Adres ve fiyat erişimi (7):** tek varsayılan adres ve arşivleme; izin kapalıyken 403 ama varsayılan seçimi serbest; başka bayinin/toptancının adresine 404; adreslerin toptancı bazında ayrı olması; fiyat listesi, özel fiyat, indirim ve grup formlarının başka işletmenin kayıtlarını reddetmesi; indirim oranı sınırları; fiyat listesini toplu doldurma.
- **Sayfa render (1)** ve **lisans ürün sınırı (1)**.

**Test altyapısı düzeltmesi:** "oturumda hata yok" kontrolü 500 yanıtında da geçebiliyordu. Tüm form testleri artık ayrıca yönlendirme bekliyor. Bu sıkılaştırma, adres arşivlemede gerçek bir hatayı yakaladı (düzeltildi).

## Bilinen eksikler

- **E-posta bildirimleri yok** (başvuru sonucu, ek bilgi talebi, aktivasyon). Aktivasyon bağlantısı toptancıya bir kez gösteriliyor; bayiye elle iletilmeli. Toptancının Gmail ayarıyla gönderim Aşama 7'de.
- Ek bilgi istenen başvuru sahibinin yanıt verebileceği bir ekran yok (e-postayla yanıt bekleniyor). Gerekirse Aşama 7'de güvenli yanıt bağlantısı eklenebilir.
- Aydınlatma metni şablondur; hukuki gözden geçirme gerekir.
- Tarayıcıda görsel kontrol yapılmadı; sayfalar otomatik testle doğrulandı.

## Aşama 5 gereksinimleri (Sipariş ve ürün talepleri)

- Migration: `carts`, `cart_items`, `orders`, `order_items`, `order_status_history`, `product_requests`; cari hareketleri tablosu Aşama 6'da (onayda cari borç bu aşamada tablo hazır olunca bağlanacak — sıra kararı aşağıda).
- Bayi kataloğu (arama, kategori/marka filtresi, stok gösterimi ayara göre, `PriceResolver` ile fiyat), sepet (min. miktar, adım, birim kuralları), sipariş oluşturma (`submit_token`, adres kopyası, fiyat kopyası).
- Toptancı sipariş onayı: `StockService::applyMany` ile tek transaction, kısmi onay (K2), ret, iptal ve iptal talebi, durum geçmişi.
- Stokta olmayan ürün talebi ve "stok gelince haber ver".
- Eşzamanlı onay testi (gerçek süreçlerle) ve çift onay testi.
- **Sıra kararı:** Onayda cari borç yazımı Aşama 6'nın cari hareket tablosuna bağlı. Öneri: Aşama 5'te `current_account_movements` tablosunu ve yalnızca "sipariş borcu / iptal ters kaydı" yazımını birlikte ekleyip, tahsilat ve ekstre ekranlarını Aşama 6'da tamamlamak — böylece onay transaction'ı baştan eksiksiz kurulur.
