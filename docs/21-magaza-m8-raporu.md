# Mağaza M8 — Kampanya ve Kupon, Raporlar, Yönetici Bildirimleri

> Testler: 341/341 (eşzamanlılık 11/11). Önceki aşama: [20-magaza-m7-raporu.md](20-magaza-m7-raporu.md).

## 1. Kampanya ve kupon (Satış → Kampanya ve Kuponlar)
- Tek tablo (`store_discounts`): kodu olan **kupon**, kodsuz olan **otomatik kampanya**.
- Türler: yüzde (isteğe bağlı üst sınır), tutar, ücretsiz teslimat.
- Koşullar: en düşük sepet tutarı, başlangıç/bitiş, toplam kullanım, kişi başı kullanım (aynı e-posta veya telefon), yalnızca ilk sipariş.
- Siparişte **tek indirim**: kupon girildiyse kupon, girilmediyse en avantajlı kampanya.
- İndirim ürün satırlarına orantılı paylaştırılır (her satırda en az 0,01 ₺ kalır). KDV, iyzico sepeti ve iade tutarı indirimli satırdan hesaplanır.
- Kişiye bağlı koşullar ödeme adımında denetlenir. Kullanım sınırı sipariş oluşurken kilitle yeniden denetlenir.
- İptal, ödeme alınamadı ve teslim alınmadı durumlarında kullanım geçersiz sayılır; hak geri döner.
- Kullanılmış indirimin kodu ve türü değiştirilemez, kendisi silinemez (pasife alınır).
- Ücretsiz kargo sınırı indirimli tutara göre uygulanır.

## 2. Raporlar (Satış → Raporlar)
- Tarih aralığı (hazır seçenekler: bu ay, son 7/30 gün, geçen ay; en fazla 1 yıl).
- Net satış, sipariş sayısı, ortalama sepet, indirim, iptal, dönemdeki para iadeleri; günlük satış grafiği; ödeme/teslimat dağılımı; en çok satanlar; kampanya kullanımı; iade nedenleri.
- Siparişleri Excel'e aktarma (formül enjeksiyonuna karşı korumalı).

## 3. Yönetici bildirimleri (Mağaza Ayarları → Yönetici bildirimleri)
- Yeni sipariş, yeni iade talebi, kritik stok (satışla kritik seviyeye inildiğinde bir kez).
- Alıcılar: girilen adresler (en fazla 10); boşsa etkin tüm mağaza yöneticileri.
- Gösterge paneline bugünkü/aylık satış özeti ve bekleyen işler eklendi.

## 4. Sonraki aşama (M9)
Çerez onayı, KVKK sayfaları, bot koruması, iyzico başvuru kontrol listesi, sunucu ve yayın.
