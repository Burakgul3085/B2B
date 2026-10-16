# Sunum Öncesi Revizyonlar

> Aşama 9'dan sonra, müşteri sunumu öncesinde istenen düzenlemeler. Testler: 210/210.

## 1. Rapor çıktıları (Excel ve PDF)
- **Excel**
  - Kurumsal antet: unvan, rapor adı, tarih, kayıt sayısı ve hazırlayan.
  - Başlık satırı işletme renginde.
  - Sütun genişlikleri içeriğe göre otomatik.
  - ₺, miktar, yüzde ve tarih alanları gerçek Excel biçimiyle yazılır.
  - Satırlar açık-koyu sırayla gösterilir; TOPLAM satırı vurgulu.
  - Otomatik filtre var.
  - Yazdırma A4'e sığar; başlık her sayfada tekrar eder, altta "Sayfa X / Y" yazar.
- **PDF**
  - Logo ya da monogramla antet; iletişim ve vergi bilgileri.
  - Bilgi şeridi: tarih, kayıt sayısı, hazırlayan, bayi, filtreler.
  - Toplam kartları.
  - İşletme renginde tablo başlığı; her sayfada tekrar eder.
  - Altta künye ve sayfa numarası.
- Kaydırma sırasında görülen çizim hatası nedeniyle Excel'de dondurulmuş bölme kullanılmaz.

## 2. E-posta kurgusu: tek ana Gmail
- Her işletmenin bir ana Gmail hesabı vardır: adres ve 16 haneli uygulama şifresi. Bu ayar iki yerden girilir:
  - Toptancı paneli → **E-posta Ayarları**.
  - **Platform** → işletme detayı → "Ana e-posta (Gmail)".
- **Gönderilen e-postalar:** Bütün e-postalar bu hesaptan gider. Bunlara parola sıfırlama e-postası da dahildir.
- **Toptancıya gelen bildirimler:** Yeni sipariş, başvuru, mesaj ve iptal talebi bildirimleri bu ana adrese düşer; toptancı kullanıcısının giriş e-postasına gitmez.
- **Sistem e-postası:** Ayrı bir sistem e-postası ayarı yoktur. Yalnızca platform yöneticisinin kendi parola sıfırlaması, sunucuda tanımlıysa sistem e-postasını kullanır.

## 3. Hesaplar: aktivasyon bağlantısı yok
- **Hesap açma:** Bayi, toptancı panelinden veya platformdan açılır; platformdan açılırken toptancı seçilir. Toptancı hesabı platformdan açılır. Her durumda açan kişi giriş e-postasını, ad soyadı ve şifreyi belirler.
- **İlk giriş:** Hesap hemen etkindir. Şifre e-postayla gönderilmez; bayiye giriş e-postasını bildiren bir e-posta gider.
- **Kendi bilgilerini değiştirme:** Herkes Profil ve Güvenlik'ten e-postasını (mevcut şifreyle onaylayarak, hemen) ve şifresini değiştirir. İlk girişte zorunlu değildir.
- **Toptancı, bayisi için:**
  - Yeni şifre belirleyebilir.
  - Bayi henüz hiç giriş yapmadıysa ve başka toptancıyla çalışmıyorsa hatalı yazılmış e-postayı düzeltebilir.
- **Platform:** Her hesabın adını, e-postasını ve şifresini gerekçeyle değiştirir. Bayi hesabını kapatıp açabilir.
- **Parolamı unuttum:** Sıfırlama bağlantısı kişinin kendi e-postasına, ilgili toptancının Gmail'inden gider.
- **Eski hesaplar:** Eski akıştan kalan "şifre belirlenmemiş" hesaplar, toptancı veya platform şifre belirleyince etkinleşir.

## 4. Bayilik başvurusu
- Bayi giriş sayfasında **"Bayimiz olmak ister misiniz?"** kutusu ve başvuru formu bağlantısı var.
  - Tek toptancı varsa bağlantı doğrudan onun formuna gider.
  - Birden çok toptancı varsa önce toptancı seçilir.
- Form gönderilince:
  - Toptancının ana adresine "Yeni bayilik başvurusu" e-postası gider ve panelde bildirim oluşur.
  - Başvuru sahibine "Başvurunuz alındı, en kısa sürede dönüş yapacağız" e-postası gider.
  - Başvuru platformdaki **Bayilik Başvuruları** ekranına düşer (menüde açık başvuru sayısı görünür).
- **Onay:**
  - Toptancı veya platform başvuruyu onaylarken giriş e-postasını (başvurudaki adres, gerekirse düzeltilir) ve şifreyi belirler.
  - Bayi hesabı, cari hesap ve ilk sevk adresi hemen oluşur.
  - Bu e-postayla kayıtlı bir bayi hesabı varsa yeni hesap açılmaz; mevcut hesaba erişim eklenir ve şifresi değişmez.

## 5. Düzeltilen hata: onay pencereleri
Aşama 9'da eklenen içerik güvenlik politikası (CSP), satır içi `onsubmit="return confirm(…)"` işleyicilerini engelliyordu. Bu yüzden 24 yerde silme, iptal ve onay işlemleri soru sorulmadan yapılıyordu; iki filtre de otomatik gönderilmiyordu. Hepsi `data-confirm` ve `data-autosubmit` özniteliklerine çevrildi; `app.js` bunları merkezi olarak işliyor. Görünümlerde satır içi olay işleyicisi kalmadığını bir test denetliyor.
