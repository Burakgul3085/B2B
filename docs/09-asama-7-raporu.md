# Aşama 7 Raporu — İletişim

> Durum: **Tamamlandı** · Testler: 166/166 geçti

## Yapılanlar

### Veritabanı
`conversations`, `messages` (FULLTEXT), `conversation_reads`, `app_notifications`, `email_settings`, `email_templates`, `email_logs`, `announcements`.

### Mesajlaşma
- Konuşma her zaman bir toptancı ile bir bayi (ilişki) arasında; katılımcılar sabit, ayrı bayi çalışanı rolü yok.
- Bire bir konuşma, **siparişe bağlı** ve **ürün talebine bağlı** konuşma (aynı kayıt için tek konuşma; ikinci mesaj aynı konuşmaya eklenir). Sipariş sayfalarında "Mesaj" düğmesi.
- Okundu bilgisi ("Okundu"), okunmamış filtresi ve menüde okunmamış sayısı, konu ve mesaj içinde arama.
- Ekler: en fazla 3 dosya (PDF/JPG/PNG, 5 MB), içerik doğrulamalı, yalnızca konuşmanın tarafları indirebilir.
- Mesajlar düz metindir ve değiştirilemez. Platform yöneticisi modülü kapatırsa ekranlar 403 döner, menüden kalkar.

### Bildirim merkezi
- İki panelin üst barında **bildirim zili** (okunmamış sayısı, son 5 bildirim), tüm bildirimler sayfası, tümünü okundu işaretleme.
- Bayi bildirimleri toptancı ilişkisi bazındadır; iki toptancıyla çalışan bayi yalnızca seçili toptancınınkileri görür.
- Bildirim açılırken yalnızca uygulama içi adreslere yönlendirilir (dış adres → ana sayfa).

### E-posta (PHPMailer + Gmail)
- Ayar ekranında yalnızca: **gönderici e-posta**, **16 harfli Google uygulama şifresi**, isteğe bağlı **görünen ad**. Sunucu, port ve şifreleme sistemde sabit: `smtp.gmail.com:587`, STARTTLS, kimlik doğrulama açık.
- Uygulama şifresi `MAIL_SECRET_KEY` ile **AES-256-GCM** şifreli saklanır; ekranda, logda, denetim kaydında ve hata mesajlarında yer almaz. Kayıtlıyken "Yeni uygulama şifresi gir" alanı görünür; boş bırakılırsa mevcut şifre korunur.
- **Bağlantıyı test et:** Gmail'e bağlanıp kimlik doğrular, başarılıysa kullanıcının kendi adresine test e-postası gönderir. Hata anlaşılır Türkçe mesaja çevrilir (yanlış şifre, bağlantı, gönderen adres…); sunucunun ham yanıtı saklanmaz. 10 dakikada en fazla 5 deneme.
- Gönderimler kuyrukta ve işlem **commit edildikten sonra** yapılır; e-posta hatası siparişi, onayı veya başvuruyu geri almaz (testli). Kuyruk senkron çalışsa bile hata isteği bozmaz.
- Her gönderim `email_logs`'ta: kuyrukta / gönderildi / gönderilemedi / atlandı. Geçici hatada 3 deneme (1, 5, 30 dk); kimlik doğrulama hatasında tekrar denenmez ve ayar "hatalı" işaretlenir. Başarısız olanlar ekrandan tekrar gönderilir.
- E-posta ayarı yoksa veya modül kapalıysa gönderim "atlandı" olarak kaydedilir; **platform e-postası toptancı adına kullanılmaz** (K9).
- **Aktivasyon bağlantısı:** e-posta gönderilebiliyorsa bayiye e-postayla gider ve ekranda gösterilmez; ayar yoksa bir kez ekranda gösterilir.

### Kurumsal şablonlar
Logo, işletme rengi, başlık, açıklayıcı metin, özet tablo (sipariş no, tutar, teslim tarihi…), güvenli bağlantı düğmesi, işletme iletişim bilgileri ve alt bilgi; mobil uyumlu tablo düzeni. İşletme her şablonun **konu ve metnini** değiştirebilir (düz metin, `{bayi}`, `{siparis_no}` gibi yer tutucular); değerler HTML olarak yorumlanmaz.

Gönderim noktaları: yeni başvuru (toptancıya), başvuru onayı/aktivasyon, mevcut hesaba yeni toptancı, başvuru reddi, ek bilgi talebi, yeni sipariş (toptancıya), sipariş durumu (bayiye: inceleme, onay/kısmi onay, ret, hazırlanıyor, tamamlandı, iptal, iptal talebi sonucu), iptal talebi (toptancıya), yeni mesaj (iki yön), talep yanıtı, talep edilen ürünün stoğa girmesi, vade hatırlatması, duyuru.

### Duyurular ve vade hatırlatması
- Duyuru: tüm aktif bayilere veya bir bayi grubuna; uygulama içi bildirim + isteğe bağlı e-posta; bayi ana sayfasında son 30 günün duyuruları.
- `ledger:send-reminders`: her pazartesi 09:00 (İstanbul), yalnızca hatırlatmayı açmış ve e-posta ayarı doğrulanmış işletmelerde, vadesi geçmiş veya 7 gün içinde vadesi gelecek borcu olan aktif bayilere.

### Platform
"E-posta Altyapısı" sayfası: işletme bazında ayar durumu ve son 7 günün gönderildi / başarısız / atlandı sayıları; sistem e-postasının yapılandırılıp yapılandırılmadığı. İçerik, alıcı ve şifre gösterilmez.

### Yerel geliştirme
`baslat.ps1` artık e-posta kuyruğu çalıştırıcısını da (küçültülmüş ayrı pencerede) başlatıyor.

## Testler (bu aşamada eklenen 18 test)

- **E-posta (9):** şifrenin yalnızca şifreli saklanması, ekranda/denetim kaydında görünmemesi ve boş bırakılınca korunması; bağlantı testi başarı/başarısızlık (anlaşılır mesaj, ayarın "hatalı" olması), sunucu/port alanlarının formda olmaması; sipariş e-postasının **toptancının hesabından** commit sonrası gitmesi; **e-posta hatasının siparişi geri almaması** ve tekrar gönderim; ayar yokken atlanma ve aktivasyon bağlantısının gösterilmesi; ayar varken bağlantının e-postayla gitmesi ve gösterilmemesi; modül kapalıyken atlanma; şablon özelleştirme, **HTML enjeksiyonunun kaçırılması** ve bilinmeyen yer tutucuların temizlenmesi; vade hatırlatma komutunun yalnızca borçlu bayiye gitmesi.
- **Mesajlaşma ve bildirim (9):** iki yönlü yazışma, okunmamış filtresi, okundu bilgisi, bildirimler; başka bayinin ve başka toptancının konuşmasına 404 ve başka işletmenin bayisine mesaj başlatılamaması; iki toptancılı bayide konuşmaların ayrı olması; siparişe bağlı konuşmanın yeniden kullanılması ve ekin yalnızca taraflarca indirilmesi; sipariş reddinde bayiye bildirim, açınca okundu ve siparişe yönlenme; başkasının bildirimine 404 ve dış adrese yönlendirilmeme; duyurunun yalnızca hedef gruba görünmesi; modül kapatılınca 403; ekranların açılması.

Gerçek Gmail'e bağlanılmadı: testlerde sahte taşıyıcı kullanıldı. Canlı Gmail hesabıyla bağlantı testi 10. aşamada (sunucuda) yapılacak.

## Bilinen eksikler
- Gerçek bir Google hesabı ve uygulama şifresiyle uçtan uca deneme yapılmadı (sunucu aşamasında).
- Ek bilgi istenen başvuru sahibi için web üzerinden yanıt formu yok; e-postayı yanıtlayarak iletişim kuruyor.

## Aşama 8 gereksinimleri (Dashboard ve raporlama)
- Chart.js ile gerçek veriden grafikler: dönemsel satış (çizgi/alan), duruma göre siparişler (halka), kategori/bayi bazında (yatay çubuk), dönem karşılaştırması; tarih/kategori/bayi filtreleri KPI ve grafiklere uygulanır.
- Rapor altyapısı: rapor tanımı (filtreler, sütunlar, toplamlar) → aynı sorgu ekran ve dışa aktarımda.
- Raporlar: ürün, stok, sipariş, cari (ekstre dahil), bayi raporları; sıralama, sayfalama, sütun seçimi, alt toplamlar.
- Excel (OpenSpout, akış) ve PDF (Dompdf, satır sınırı) dışa aktarma; kurumsal başlık, logo, rapor tarihi, filtre özeti; CSV/Excel formül enjeksiyonu önlemi; büyük raporlar için kuyruk.
- Bayi raporları (kendi siparişleri, ekstre).
