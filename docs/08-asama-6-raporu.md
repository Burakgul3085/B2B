# Aşama 6 Raporu — Cari Hesap

> Tarih: 2026-10-19 · Durum: **Tamamlandı** · Testler: 148/148 geçti

## Yapılanlar

### Veritabanı
`collections` (manuel tahsilat), `order_returns`, `order_return_items`; `current_account_movements.collection_id` bileşik FK. Tahsilat tutarı ve iade miktarı için `CHECK (> 0)`.

### Cari servisi (`LedgerService`) — cariyi değiştiren tek yer
| Kural | Uygulama |
|---|---|
| C1 bakiye = hareket toplamı | Her hareket, hesap satırı kilitliyken aynı transaction'da bakiyeyi günceller; testlerde tüm veritabanı üzerinde doğrulanıyor |
| C2 hareket silinmez | Model güncelleme/silme engelli; ters kayıt `reverses_movement_id` UNIQUE |
| C3 sipariş borcu tek | `order:{id}:debit` UNIQUE (Aşama 5) |
| C4 tahsilat | Tahsilat kaydı + alacak hareketi + bakiye tek transaction; yöntem (nakit, havale/EFT, çek, senet, fiziki POS, diğer), tarih, isteğe bağlı ilgili sipariş (yalnızca aynı bayinin siparişi) |
| C5 tahsilat iptali | Kayıt silinmez; gerekçe + ters borç hareketi, **en fazla bir kez** |
| C6 açılış bakiyesi | İlişki başına bir kez; pozitif = bayi borçlu, negatif = bayi alacaklı |
| C7 manuel düzeltme | Borç veya alacak; açıklama zorunlu; denetim kaydına yazılır |
| C8 vade | Sipariş borcu: hareket tarihi + bayinin vade günü |
| C9 vadesi geçmiş | Hareketlerden **FIFO** ile hesaplanır, saklanmaz |

### Vade yaşlandırma ve ekstre (`AccountAnalysis`)
- Alacaklar en eski vadeli borçtan başlayarak düşülür. **Birbirini ters çeviren çiftler** (iptal edilen sipariş borcu ve ters kaydı, iptal edilen tahsilat ve ters kaydı) hesaba katılmaz — aksi halde iptal edilen bir siparişin ters kaydı yanlış borcu kapatırdı.
- Dört grup: vadesi geçmiş (gecikme günüyle), 7 gün içinde, vadesi gelmemiş, borcu aşan alacak.
- Ekstre: tarih aralığı, devreden bakiye, yürüyen bakiye, dönem borç/alacak toplamı, kapanış bakiyesi. Siparişe bağlı satırlardan siparişe geçilebilir.

### İade (tamamlanmış sipariş)
- Kalem bazında **satılan − daha önce iade edilen** miktar sınırı; birim küsurat kuralı.
- Tek transaction: iade kaydı + sevk deposuna `return_in` stok girişi + `return_credit` cari alacak (siparişteki birim fiyat, indirim ve KDV ile).
- Siparişin durumu değişmez; sipariş detayında iade geçmişi görünür. Henüz tamamlanmamış sipariş için iade değil iptal kullanılır.

### Ekranlar
- **Toptancı — Cari Hesaplar:** toplam alacak, vadesi geçmiş, bu ay tahsilat; borcu olanlar / vadesi geçenler / tümü; bayi başına bakiye, vadesi geçmiş ve 7 gün içinde gelecek tutar.
- **Toptancı — Cari detayı:** dört gösterge, ekstre, açık borç kalemleri (gecikme günüyle), tahsilat ekle, açılış bakiyesi (bir kez), düzeltme hareketi.
- **Toptancı — Tahsilatlar:** tarih, yöntem, bayi filtresi; dönem toplamı; gerekçeli iptal.
- **Toptancı — Sipariş detayı:** iade formu ve iade geçmişi.
- **Toptancı dashboard:** finans kartı (cari alacak, vadesi geçmiş, bu ay tahsilat).
- **Bayi — Cari Hesabım:** bakiye, vade durumu, ekstre, son tahsilatlar (yalnızca görüntüleme). Ana sayfadaki bakiye kartı ekstreye bağlandı.
- Ekstre ve ekranlarda "resmî muhasebe kaydı değildir" notu.

### Geliştirme verisi
Onur Kırtasiye'nin Akgün'deki carisi: onaylanmış sipariş 1.897,20 + 45 gün önceki açılış bakiyesi 1.250,00 − tahsilat 1.000,00 = **2.147,20 ₺** (açılış bakiyesinin kalan 250 ₺'si vadesi geçmiş olarak görünür).

## Testler (bu aşamada eklenen 10 test)

- Tahsilatın bakiyeyi düşürmesi, iptalin tam bir kez geri alması, ikinci iptalin reddi.
- Başka bayinin siparişine tahsilat bağlanamaması.
- Açılış bakiyesinin bir kez girilmesi ve işaret anlamı (borç/alacak).
- Düzeltmede açıklama zorunluluğu ve denetim kaydı.
- İadenin stok + cari alacak yazması, iade edilebilir miktar sınırı, kademeli iadeyle bakiyenin sıfırlanması, siparişin durumunun değişmemesi.
- Tamamlanmamış siparişte iade reddi.
- **FIFO yaşlandırma senaryosu:** gecikmiş 100, 3 gün sonra 50, uzak vadeli 80, iptal edilmiş 500'lük çift ve 120 tahsilat → vadesi geçmiş 0, yaklaşan 30, gelmemiş 80.
- Ekstrede devreden ve yürüyen bakiye.
- İşletmeler ve bayiler arası izolasyon (cari detay, tahsilat ekleme ve iptal 404; bayi yalnızca kendi ekstresini görür).
- Cari ekranlarının ve iade formunun açılması.

## Bilinen eksikler

- Ekstrenin **Excel/PDF dışa aktarımı** raporlama altyapısıyla Aşama 8'de.
- **Vade hatırlatma e-postası** Aşama 7'de.
- Cari listesi yaşlandırmayı tüm hareketleri belleğe alarak hesaplıyor; yüzlerce bayi için yeterli, binlerce bayi ve on binlerce hareket için Aşama 9'daki performans ölçümünde gözden geçirilecek.

## Aşama 7 gereksinimleri (İletişim)

- `conversations`, `messages`, `conversation_reads`, `notifications`, `email_settings`, `email_templates`, `email_logs`, `announcements`.
- Uygulama içi mesajlaşma: bire bir, siparişe ve ürün talebine bağlı konuşma, okundu bilgisi, ekler (mevcut `DocumentUploader`), arama.
- Bildirim merkezi (menüde sayaç).
- İşletme bazlı Gmail ayarı: yalnızca e-posta + 16 haneli uygulama şifresi + görünen ad; şifre `MAIL_SECRET_KEY` ile şifreli, geri gösterilmez; "Bağlantıyı test et".
- PHPMailer ile kuyruklu, commit sonrası gönderim; `email_logs`, yeniden deneme; e-posta hatası işlemi geri almaz.
- Kurumsal e-posta şablonları (logo, renk, sipariş/talep numarası, güvenli bağlantı); gönderim noktaları: başvuru bildirimi/sonucu, aktivasyon, sipariş bildirimi/onay/ret, yeni mesaj, talep yanıtı ve stoğa giriş, vade hatırlatması, duyurular.
