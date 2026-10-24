# Platform yöneticisi kılavuzu

Platform paneli, sistemin teknik yönetimi içindir. Adres: `https://<alan adı>/platform`. Sistem tek toptancı (Yapboz Kırtasiye) içindir; ikinci bir işletme açılamaz.

## Hesap

- Platform yöneticisi panelden değil, sunucuda komutla oluşturulur:

  ```bash
  php artisan platform:create-admin
  ```

- İlk girişte **iki adımlı doğrulama** kurulur.
- İsteğe bağlı olarak panele yalnızca belirli IP'lerden girilebilir. Bunun için `.env` dosyasında `PLATFORM_ALLOWED_IPS` ayarlanır.

### Doğrulama uygulamasını kaybettiyseniz

Kurtarma kodu yoktur. İki adımlı doğrulama sunucudan (SSH) sıfırlanır, sonraki girişte yeni karekod okutulur:

```bash
cd /var/www/yapboz/current && sudo -u www-data php artisan platform:reset-two-factor <e-posta>
```

Mağaza yöneticisi (süper admin dahil) için komutun sonuna `--store-admin` eklenir; onay sorusunu atlamak için `--force`. Sıfırlama işlem kaydına yazılır.

## Menüler

| Menü | Ne için |
|---|---|
| Genel Durum | Sistemin özeti. |
| İşletmeler | Toptancı hesabı: lisans ve modüller (mesajlar, raporlar, ürün talepleri), ana Gmail ayarı, toptancı kullanıcısının bilgileri ve şifresi. |
| Bayilik Başvuruları | Gelen başvurular. Toptancı adına onaylanabilir. |
| Bayi Hesapları | Bütün bayi hesapları. Ad, e-posta ve şifre gerekçeyle değiştirilir; hesap kapatılıp açılabilir. |
| Mağaza Yöneticileri | Mağaza yönetimi için ilk **süper admin** hesabı. Diğer yöneticileri süper admin açar. |
| E-posta Altyapısı | E-posta gönderim durumu ve hatalar. |
| Denetim Kayıtları | Kim, ne zaman, neyi değiştirdi. |
| Hata Kayıtları | Uygulama hataları; aynı hata tek kayıtta sayılır. Müşteri hata sayfasındaki **referans kodunu** (ör. `H12`) iletirse arama kutusuna yazın. |
| Yedekler | Veritabanı ve yüklenen dosyaların yedeği. Her gece 03:30'da otomatik alınır; elle de alınıp indirilebilir. |

## Destek erişimi

Toptancının paneline bakmanız gerekirse toptancı **Ayarlar → Destek Erişimi**'nden size süreli izin verir. İzin süresince toptancı panelini görüntüleyebilirsiniz. Her erişim kayda geçer.

## Sistem ayarları

Ayrı bir "Sistem Ayarları" ekranı yoktur. Sistem düzeyindeki ayarlar sunucudaki `.env` dosyasındadır: alan adları, sistem e-postası, yedek sayısı, günlük dosyaları. Ayrıntılar için [kurulum.md](kurulum.md) dosyasına bakın.
