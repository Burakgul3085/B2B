# Proje Dokümanları

| Doküman | İçerik |
|---|---|
| [01-analiz-ve-mimari.md](01-analiz-ve-mimari.md) | Ortam incelemesi, referans analizi, teknoloji kararı, üç giriş alanı, tenant izolasyonu, güvenlik, tasarım sistemi, navigasyon, açık kararlar (K1–K14), aşama planı |
| [02-veritabani-er.md](02-veritabani-er.md) | ER diyagramları, tablolar, kısıtlar, indeksler |
| [03-is-kurallari.md](03-is-kurallari.md) | Başvuru, stok, sipariş durum makinesi, onay/iptal transaction'ları, fiyat önceliği, KDV/yuvarlama, cari, e-posta, eşzamanlılık korumaları |
| [04-asama-2-raporu.md](04-asama-2-raporu.md) | Aşama 2: temel altyapı raporu |
| [05-asama-3-raporu.md](05-asama-3-raporu.md) | Aşama 3: ürün ve stok raporu |
| [06-asama-4-raporu.md](06-asama-4-raporu.md) | Aşama 4: bayilik ve fiyatlandırma raporu |
| [07-asama-5-raporu.md](07-asama-5-raporu.md) | Aşama 5: sipariş ve ürün talepleri raporu |
| [08-asama-6-raporu.md](08-asama-6-raporu.md) | Aşama 6: cari hesap raporu |
| [09-asama-7-raporu.md](09-asama-7-raporu.md) | Aşama 7: iletişim raporu |
| [10-asama-8-raporu.md](10-asama-8-raporu.md) | Aşama 8: dashboard ve raporlama raporu |
| [11-asama-9-raporu.md](11-asama-9-raporu.md) | Aşama 9: güvenlik, performans ve negatif testler raporu |
| [12-revizyonlar.md](12-revizyonlar.md) | Sunum öncesi revizyonlar: raporlar, hesap ve e-posta kurgusu, bayilik başvurusu |
| [13-magaza-mimarisi.md](13-magaza-mimarisi.md) | Mağaza (B2C) mimarisi ve tek toptancı revizyonu: kararlar, adresler, yetkiler, veri modeli, ödeme/kapıda ödeme/SMS/fatura/kargo, aşama planı (M0–M10) |
| [14-magaza-m1-raporu.md](14-magaza-m1-raporu.md) | Mağaza M1: tek toptancı kilidi, alan adına göre ayrım, mağaza yöneticileri (süper admin), 2FA, işlem geçmişi, modül sınırı testi |
| [15-magaza-m2-raporu.md](15-magaza-m2-raporu.md) | Mağaza M2: katalog — kategori ağacı, marka/logo, ürün ve seçenekler, KDV dahil fiyat, görseller, SEO, Excel ile aktarma |
| [16-magaza-m3-raporu.md](16-magaza-m3-raporu.md) | Mağaza M3: stok — yalnızca eklenen hareketler, giriş/sayım/düzeltme, ters kayıt, kritik stok, ödeme sırasında ayırma, eşzamanlılık testleri |
| [17-magaza-m4-raporu.md](17-magaza-m4-raporu.md) | Mağaza M4: vitrin — yayın kapısı ve önizleme, ana sayfa, kategori/marka/arama listeleri, ürün sayfası, görsel boyutları, SEO (JSON-LD, sitemap, robots), banner/sayfa/ayar yönetimi |
| [18-magaza-m5-raporu.md](18-magaza-m5-raporu.md) | Mağaza M5: il/ilçe verisi, müşteri hesabı (üyelik, giriş, parola sıfırlama, hesabım), adres defteri, sepet (stok sınırı, güncel fiyat/KDV, girişte birleştirme) |
| [19-magaza-m6-raporu.md](19-magaza-m6-raporu.md) | Mağaza M6: ödeme sayfası, siparişler, iyzico ödeme formu, Eskişehir içi kapıda ödeme (SMS doğrulama, engel listesi), Netgsm altyapısı, yasal metinler ve onaylar, yönetimde sipariş işlemleri |
| [20-magaza-m7-raporu.md](20-magaza-m7-raporu.md) | Mağaza M7: kargo firmaları ve takip bağlantısı, iade talepleri (cayma hakkı), iyzico ve elle para iadesi, kartlı siparişin iadeli iptali, elle fatura; istek sınırı (throttle) anahtar hatası düzeltmesi |
| [21-magaza-m8-raporu.md](21-magaza-m8-raporu.md) | Mağaza M8: kampanya ve kupon, raporlar ve Excel aktarma, yönetici bildirimleri, gösterge paneli |
| [22-magaza-m9-raporu.md](22-magaza-m9-raporu.md) | Mağaza M9: İptal-İade / KVKK / Gizlilik-Çerez metinleri, çerez onay bandı, form bot koruması, KVKK hesap kapatma, yayın hazırlığı ve iyzico kontrol listesi, ödeme logo bandı |
| [23-magaza-eksikler-raporu.md](23-magaza-eksikler-raporu.md) | Mağaza küçük eksikleri: müşteri adına iade, iadesiz para iadesi, iade faturası, kategori/ürün kuponu, toplu tek kullanımlık kodlar, kupon eşzamanlılık testi (deadlock düzeltmesi) |
| [24-b2b-eksikler-raporu.md](24-b2b-eksikler-raporu.md) | B2B eksikleri: hata kayıtları, yedekler (gece yedeği, geri yükleme doğrulandı), destek erişimi, başvuruya web üzerinden ek bilgi, kayıtlı rapor filtreleri, koyu tema; teknik borç kararları (CSP) |
| [25-yayin-oncesi-eksikler-raporu.md](25-yayin-oncesi-eksikler-raporu.md) | Yayın öncesi eksikler: Türkçe hata sayfaları ve referans kodu, eksiksiz Türkçe doğrulama mesajları, sözleşmelerin sipariş e-postasıyla gönderimi, iyzico bildirim adresi (webhook), adrese göre kapıda ödeme engeli, .env.example ve günlük dosyaları, mağaza performans ölçümü |
| [26-havale-ve-kilavuzlar-raporu.md](26-havale-ve-kilavuzlar-raporu.md) | Havale / EFT ile ödeme (hesaplar, süre, onay, süre dolunca iptal, iade kuralı); desiye göre kargo ücreti olmayacağı ve Turnstile'ın yapılmayacağı kararları |
| [27-hakkimizda-iletisim-raporu.md](27-hakkimizda-iletisim-raporu.md) | Mağaza: serbest sayfalar yerine sabit Hakkımızda ve İletişim sayfaları (yönetimde düzenlenir), Google Haritalar, çalışma saatleri, iletişim formu ve Mesajlar kutusu |
| [28-fatura-uyumsoft-raporu.md](28-fatura-uyumsoft-raporu.md) | Mağaza: Faturalar ekranı (otomatik fatura yok; taslak düzenlenip elle gönderilir), e-Fatura / e-Arşiv ayrımı, iade faturası, deneme sürücüsü ve Uyumsoft altyapısı |
| [kilavuz/](kilavuz/) | Kullanım kılavuzları: [toptancı](kilavuz/toptanci.md), [bayi](kilavuz/bayi.md), [mağaza yönetimi](kilavuz/magaza-yonetimi.md), [platform](kilavuz/platform.md); [sunucu kurulumu](kilavuz/kurulum.md) |
| [YAPILACAKLAR.md](YAPILACAKLAR.md) | Yapılmayanlar ve bekleyenler: kalan mağaza aşamaları (M7–M10), dışarıdan beklenen hesap ve bilgiler, B2B Aşama 10 ve eksik ekranlar |
| [GELISTIRME.md](GELISTIRME.md) | Yerel kurulum ve çalıştırma |
