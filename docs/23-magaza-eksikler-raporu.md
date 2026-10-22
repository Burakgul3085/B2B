# Mağaza — Küçük Eksiklerin Tamamlanması

> Testler: 351/351, eşzamanlılık 12/12. Önceki aşama: [22-magaza-m9-raporu.md](22-magaza-m9-raporu.md). M10 (canlı iyzico / Netgsm) yapılmadı.

## 1. Müşteri adına iade talebi
- Sipariş ekranında **Diğer işlemler → Müşteri adına iade talebi aç** (telefon/e-posta ile gelen istekler).
- Kurallar müşteri talebiyle aynı (adet sınırı, kapıda ödemede IBAN); teslim edilmiş olmalı. **İade süresi sınırı yönetici için uygulanmaz** (süre dışı iyi niyet iadeleri).
- Talebi açan "store_admin" olarak kaydedilir; müşteriye e-posta gider, yöneticilere yeni talep bildirimi gitmez.

## 2. Ürün iadesi olmadan para iadesi
- **Diğer işlemler → Para iadesi (ürün iadesi olmadan)**: eksik gönderim, hasarlı ürün için indirim, gönül alma. Neden zorunlu ve müşteriye e-postada gösterilir.
- Kartla ödemede iyzico'ya (tutar işlemlere dağıtılır) veya "iyzico panelinden yaptım (elle)"; kapıda ödemede her zaman elle (havale).
- Stok değişmez. İade edilebilecek kalan tutar aşılamaz.

## 3. İade / iptal faturası (elle)
- Satış faturası olan ve para iadesi yapılmış / iptal edilmiş / teslim alınmamış siparişte **Fatura → İade faturası ekle**: numara (satış faturalarıyla da çakışamaz), tarih, isteğe bağlı tutar ve ilgili iade, PDF (gerçek PDF denetimi).
- Bir siparişte birden fazla olabilir; müşteri sipariş sayfasından indirir. Başka siparişin belgesine erişilemez.
- e-Arşiv (Uyumsoft) bağlanınca otomatik oluşturulacak; hangi belgenin düzenleneceği mali müşavirle netleşecek.

## 4. Kategoriye / ürüne özel kupon
- İndirim formunda **Kapsam**: kategoriler (alt kategorileriyle) ve SKU listesi (seçeneğin SKU'su girilse de ürünün tüm seçenekleri girer). Bulunamayan SKU hata verir.
- Kapsam varsa en düşük tutar, yüzde ve paylaştırma yalnızca kapsamdaki satırlar üzerinden hesaplanır; kapsam dışı satırlara indirim düşmez. Sepette uygun ürün yoksa "uygun ürün yok" denir.

## 5. Toplu kupon kodları
- Kupon ekranında **Toplu kodlar**: adet (en fazla 1.000; kupon başına 10.000) ve önek → `ÖNEK-XXXXXXXX` (karışabilecek 0/O, 1/I yok). Her kod **bir kez** kullanılır; iptal edilen siparişin kodu yeniden kullanılabilir.
- **Yalnızca toplu kodlarla kullanılsın** seçeneği ana kodu kapatır.
- Kodlar CSV olarak indirilir (kullanıldı bilgisiyle, Excel uyumlu).

## 6. Kupon kullanım sınırı — eşzamanlılık
- Yeni paralel test (`StoreCouponConcurrencyTest`): iki süreç tek kullanımlık kuponla aynı anda sipariş verir.
- Test **gerçek bir hata buldu**: sipariş satırı indirime yabancı anahtarla bağlı olduğundan, önce siparişi ekleyip sonra indirim satırını kilitlemek iki eşzamanlı siparişte deadlock'a (500 hatası) yol açıyordu. Kilit artık sipariş eklenmeden önce alınıyor; biri kullanır, diğerine "kullanım sınırı doldu" denir ve siparişi/stok düşüşü oluşmaz.
- Eşzamanlılık paketindeki diğer testler tüm tabloları boşalttığı için bu test il/ilçe verisini ve yasal metinleri gerekirse yeniden yükler.
