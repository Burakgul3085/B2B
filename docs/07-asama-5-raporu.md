# Aşama 5 Raporu — Sipariş ve Ürün Talepleri

> Tarih: 2026-10-18 · Durum: **Tamamlandı** · Testler: 138/138 geçti (4'ü gerçek çok süreçli eşzamanlılık testi)

## Yapılanlar

### Veritabanı
`carts`, `cart_items`, `orders`, `order_items`, `order_status_history`, `product_requests`, `current_account_movements`, `business_counters`.
- Sipariş tutar tutarlılığı veritabanında: `CHECK (net = ara toplam − indirim, genel toplam = net + KDV)`.
- Kalem: `CHECK (0 ≤ onaylanan ≤ istenen)`.
- Cari hareket: borç ve alacaktan **yalnızca biri** dolu olabilir; `source_key` ve `reverses_movement_id` UNIQUE.
- Sipariş numarası işletme ve yıl bazında kesintisiz: `SIP-2026-000001` (satır kilitli sayaç).

### Bayi tarafı
- **Katalog:** arama (ad, kod, barkod; üst barda da), kategori (alt kategorileri kapsar), marka, "sadece stoktakiler", sıralama. Fiyat bayinin kendi fiyatıdır (`PriceResolver`), indirim varsa üstü çizili taban fiyat ve oran gösterilir. KDV dahil/hariç gösterimi işletme ayarına göre. Stok bilgisi ayara göre adet veya "Stokta / Sınırlı / Yok" (K4).
- **Ürün detayı:** görseller, birim ve sipariş kuralları, varyant seçenekleri, **"bu üründen geçmiş alımlarınız"**.
- Yalnızca aktif, bayiye görünür, arşivlenmemiş ürünler; gizli ürünün detay sayfası ve görseli 404. Alış fiyatı hiçbir bayi yanıtında yer almaz (testle doğrulanıyor).
- **Sepet:** minimum miktar, artış adımı ve birim küsurat kuralı; fiyat ve stok her açılışta güncel hesaplanır; sorunlu satır kırmızı işaretlenir ve sipariş gönderimi engellenir. Sepet bayi-toptancı ilişkisi bazında: iki toptancıyla çalışan bayinin sepetleri ayrıdır. **Sepet stok ayırmaz.**
- **Sipariş gönderimi:** kayıtlı adreslerden seçim, not, tek kullanımlık gönderim anahtarı (çift tıklama ve ağ tekrarı ikinci sipariş üretmez — testle). Adres, ürün adı/kodu, birim, KDV oranı, fiyat ve indirim siparişe **kopyalanır**.
- **Siparişlerim:** durum filtresi, detay, durum geçmişi; onay öncesi doğrudan iptal, onay sonrası iptal talebi.
- **Taleplerim:** stokta olmayan ürün için talep (miktar, not, "stoğa girince haber ver"); aynı ürün için açık talep varsa yenisi açılmaz, güncellenir.
- **Ana sayfa:** devam eden sipariş, bu ay onaylanan tutar, cari bakiye, açık talep; son siparişler; en çok sipariş verilen ürünler; "talep ettiğiniz ürünler stoğa girdi" bildirimi.

### Toptancı tarafı
- **Siparişler:** karar bekleyen / onaylandı / hazırlanıyor / tamamlandı / reddedildi / iptal sekmeleri, bayi ve tarih filtresi, menüde bekleyen sipariş rozeti.
- **Onay ekranı:** kalem kalem istenen miktar, **seçilen depodaki güncel stok** (depo değiştirildiğinde anında güncellenir, yetmeyen kırmızı), onaylanacak miktar (düşürülürse kısmi onay — K2), teslim alacak kişi, telefon, planlanan teslim tarihi, teslimat notu.
- Ret (gerekçe zorunlu, bayiye gösterilir), "hazırlanıyor", "tamamlandı", iptal (ters kayıtlarla), iptal talebini kabul/ret.
- **Ürün talepleri:** açık talepler, aynı ürünü kaç bayinin istediği ve toplam miktar, durum + yanıt.
- **Dashboard:** karar bekleyen sipariş (bugün gelen), bu ay onaylanan satış, stokta olmayan ürün, açık talep; son siparişler; özet (bayi, başvuru, ürün, stok, stok değeri); kritik stok.

### Kritik işlem: sipariş onayı (tek transaction)
1. Sipariş satırı kilitlenir; durum "iletildi/inceleniyor" değilse veya stok daha önce düşülmüşse durur.
2. Bayi ilişkisi aktif değilse onay verilmez.
3. Kalemler ürün ID sırasıyla kilitlenir; **biri yetersizse hiçbir şey yazılmaz** ve ekranda hangi üründe ne kadar stok olduğu gösterilir.
4. Stok hareketleri (`order:{id}:sale:{kalem}`), tutarların onaylanan miktar ve **siparişteki** fiyatla yeniden hesabı, durum, cari borç (`order:{id}:debit`, vade = bugün + bayinin vade günü) ve durum geçmişi aynı transaction'da.

İptalde ters stok (`sale_reversal`) ve ters cari kayıt (`reversal`, orijinal harekete bağlı) **en fazla bir kez** yazılır. Tamamlanmış sipariş iptal edilemez.

### Cari (Aşama 6'nın temeli)
`LedgerService`: sipariş borcu ve ters kaydı; bakiye aynı transaction'da kilitli satırda güncellenir. Cari borcun oluşma anı işletme ayarı: **onayda** (varsayılan) veya **tamamlandığında** (K5) — ikisi de testli. Tahsilat, ekstre, açılış bakiyesi ve vade raporu Aşama 6'da.

### Ürün talebi ve stok girişi
Varsayılan sevk deposuna stok girdiğinde (giriş, iade, transfer, sayım, iptal dönüşü) "gelince haber ver" seçili açık talepler **commit sonrasında** otomatik "Stok güncellendi" olur. Talep stoğu değiştirmez, ürünü ayırmaz ve siparişe dönüşmez — üçü de testli. E-posta bildirimi Aşama 7'de.

## Testler (bu aşamada eklenen 25 test)

- **Sipariş yaşam döngüsü (13):** gönderimde anlık görüntüler ve stoğa dokunulmaması; aynı anahtarla tek sipariş; yetersiz stok ve askıdaki bayi engeli; tam onayda stok düşümü + tek cari borç + ikinci onayın etkisizliği; **kısmi onayda tutarın siparişteki eski fiyatla yeniden hesabı (fiyat sonradan değişse de)**; onay anında stok yetmezse hiçbir değişiklik olmaması; reddin stok/cariyi etkilememesi; iptalde ters kayıtların tam bir kez yazılması; bayi iptal talebi → ret (önceki duruma dönüş) → kabul (ters kayıt); cari borcun tamamlanmada yazılması seçeneği; tamamlanmış siparişin iptal edilememesi; adres değişikliğinin geçmiş siparişi etkilememesi; sipariş numarasının işletme bazında sıralı olması. Her uygun testte **cari bakiye = hareket toplamı** tüm veritabanı üzerinde doğrulanıyor.
- **HTTP ve izolasyon (10):** katalogda yalnızca satıştaki ürünler, bayi fiyatı, **alış fiyatının hiç görünmemesi**; gizli ürün detayı/görseli 404; sepet kuralları; bayinin web üzerinden sipariş verip toptancının onaylaması (uçtan uca); başka bayinin ve başka toptancının siparişine 404; başka bayinin sepet satırına 404; iki toptancılı bayide sepetlerin ayrı olması; talep akışı + stok girişinde otomatik işaretleme + talebin siparişe dönüşmemesi; toptancının talebe yanıtı; talep modülü kapatılınca 403 ve menüden kalkması.
- **Eşzamanlılık (2, gerçek ayrı PHP süreçleri):** stok 10 iken iki farklı siparişin (7+7) paralel onayı → biri onaylanır, diğeri "yetersiz stok"; kalan stok 3, tek satış hareketi, tek cari borç. Aynı siparişin paralel iki onayı → tek onay, tek stok düşümü, tek borç.

## Bu aşamada alınan kararlar

| Konu | Karar |
|---|---|
| Durum geçişi | "Onaylandı" → doğrudan "Tamamlandı" da serbest ("Hazırlanıyor" isteğe bağlı ara adım). |
| Askıdaki bayinin bekleyen siparişi | Onaylanamaz; toptancı reddeder veya önce bayiyi aktif yapar. |
| Onaylanan miktar | 0 ile istenen arasında; en az bir kalem sıfırdan büyük olmalı (hepsi sıfırsa ret kullanılır). |
| Bayi ekranında birim fiyat | İndirimli birim fiyat 4 ondalık gösterilir; satır tutarı ise kurala göre satırda yuvarlanır (§4.2). |
| Talep durum değişikliği | Stok girişinde otomatik "Stok güncellendi"; diğer durumlar toptancı tarafından. |
| İade (tamamlanmış sipariş) | Stok ve cari alacak birlikte yazıldığı için **Aşama 6'ya** (cari) alındı. |

## Bilinen eksikler

- **E-posta ve uygulama içi bildirimler** (yeni sipariş, onay/ret, talep yanıtı, stoğa giriş) Aşama 7'de.
- Tamamlanmış sipariş için **iade kaydı** Aşama 6'da.
- Tarayıcıda görsel kontrol yapılmadı; ekranlar otomatik testlerle doğrulandı. Örnek veride Onur Kırtasiye'nin onaylanmış bir siparişi (SIP-2026-000001, 1.897,20 ₺ — grup fiyat listesi ve kategori indirimi birlikte, elle hesapla doğrulandı) ve onay bekleyen bir siparişi var.

## Aşama 6 gereksinimleri (Cari hesap)

- `collections` tablosu; manuel tahsilat (yöntem, tarih, tutar, ilgili sipariş) ve tahsilat iptali (ters kayıt).
- Açılış bakiyesi (ilişki başına bir kez), yetkili manuel borç/alacak düzeltmesi (açıklama zorunlu).
- Tamamlanmış sipariş için iade: `return_in` stok + `return_credit` cari, kalem bazında satılan − iade edilen sınırı.
- Cari hesap listesi (bakiye, vadesi geçmiş), cari ekstre (tarih aralığı, devreden bakiye), FIFO eşleştirmeyle vade yaşlandırma (C9).
- Bayi tarafında Cari Hesap sayfası (ekstre, tahsilat geçmişi, vadesi yaklaşan).
- Testler: bakiye = hareket toplamı değişmezi, ters kaydın tekliği, eşzamanlı tahsilat, başka bayinin carisine erişim, FIFO yaşlandırma senaryoları.
