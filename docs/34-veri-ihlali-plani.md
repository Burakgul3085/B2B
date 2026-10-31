# Veri ihlali müdahale planı (KVKK m.12/5)

Kişisel veriler (müşteri, bayi, yönetici; ad, telefon, adres, e-posta, sipariş, IBAN) yetkisiz kişilerin eline geçtiğinde
veya geçmiş olabileceğinden şüphelenildiğinde izlenecek yol. Kurul'a bildirim süresi **ihlalin öğrenilmesinden itibaren
72 saattir** (KVK Kurulu kararı 2019/10). Gecikirse gerekçesiyle birlikte yine bildirilir.

## Sorumlular

| Rol | Kim | Görev |
|---|---|---|
| Veri sorumlusu | Ebru Birbilen (Yapboz Kırtasiye) | Kararı verir, Kurul'a ve kişilere bildirimi yapar/onaylar |
| Teknik sorumlu | Siteyi yöneten geliştirici | Sızıntıyı durdurur, kapsamı ve etkilenen kişileri çıkarır |
| Destek (gerektiğinde) | Mali müşavir, avukat; Hostinger, iyzico, Uyumsoft | Hukuki görüş; sağlayıcı tarafındaki kayıtlar |

İletişim: KVKK Aydınlatma Metni'ndeki adres ve e-posta (Yönetim → Yasal Metinler).

## Saat saat

**0. saat: fark etme.** Kim fark ederse (müşteri şikâyeti, garip yönetici işlemi, sağlayıcı uyarısı, sunucu alarmı)
hemen veri sorumlusuna ve teknik sorumluya haber verir. Fark etme anı **tarih ve saatiyle** yazılır; 72 saat buradan sayılır.

**İlk 4 saat: durdur.**
- Şüpheli yönetici hesabını pasifleştirin, tüm yönetici parolalarını değiştirin, 2FA'yı kontrol edin.
- Gerekirse mağazayı kapatın (Ayarlar → Mağaza kapalı).
- Sızan veya sızmış olabilecek anahtarları yenileyin: iyzico API/gizli anahtar (iyzico paneli → Yenile), Uyumsoft web servis
  şifresi (Kurumsal Portal → Kullanıcı Tanımları), e-posta/SMS sağlayıcı şifreleri, sunucu SSH anahtarı, `APP_KEY` (dikkat:
  şifreli ayarlar yeniden girilmelidir).
- Kanıtları silmeyin: sunucuyu yeniden kurmadan önce kayıtların kopyasını alın.

**4–24. saat: kapsamı çıkar.**
- Mağaza işlem geçmişi (Yönetim → İşlem geçmişi) ve B2B denetim kayıtları (`audit_logs` tablosu): kim, ne zaman, neyi gördü/dışa aktardı.
- Sunucu kayıtları: nginx erişim kayıtları (`/var/log/nginx/`), uygulama kayıtları (`storage/logs/`), SSH girişleri (`/var/log/auth.log`).
- Hangi veri türleri, kaç kişi, hangi tarih aralığı etkilendi? Kart bilgisi bizde tutulmaz (iyzico'dadır); bunu bildirimde belirtin.
- Gerekiyorsa ihlalden önceki son temiz yedekten (Platform → Yedekler) geri dönüş planlanır.

**24–72. saat: bildir.**
- **Kurul'a:** kvkk.gov.tr → "Veri İhlali Bildirimi" formu (çevrim içi). Bilinmeyen bilgiler sonradan tamamlanabilir; süreyi kaçırmayın.
- **Etkilenen kişilere:** "makul en kısa sürede", tercihen e-postayla; ne olduğu, hangi verilerin etkilendiği, ne yapmaları
  gerektiği (ör. parolasını değiştirmesi, şüpheli aramalara dikkat), iletişim adresi. Toplu bildirim için Toplu E-posta değil,
  hesaplara tek tek giden e-posta kullanılır (pazarlama izni aranmaz; bu bir yasal bildirimdir).

**Sonra: kapat ve kaydet.**
- Açığın kökünü düzeltin; aynı açık başka yerde var mı kontrol edin.
- Bu belgenin sonuna bir **olay kaydı** ekleyin: tarih, fark etme yolu, kapsam, alınan önlemler, bildirim tarihleri.
  Kurul, ihlal olmasa bile bu kayıtların tutulmasını bekler.

## Önleyici olarak zaten olanlar

- Yönetici işlemleri ve platform değişiklikleri denetim kaydına yazılır; kayıt silme de kayda geçer.
- Parolalar hash'li, ödeme/fatura anahtarları şifreli saklanır; `.env` ve `.git` web'den erişilemez.
- Kart verisi sistemde hiç tutulmaz (iyzico ödeme formu).
- Her gece 03:30'da veritabanı ve yüklenen dosyaların yedeği alınır (Platform → Yedekler).

## Olay kaydı

| Tarih | Olay | Etkilenen | Önlem | Kurul bildirimi | Kişilere bildirim |
|---|---|---|---|---|---|
| – | – | – | – | – | – |
