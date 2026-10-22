# Mağaza M9 — Yasal Uyum, Çerez Onayı, Bot Koruması, Yayın Hazırlığı

> Testler: 345/345 (eşzamanlılık 11/11). Önceki aşama: [21-magaza-m8-raporu.md](21-magaza-m8-raporu.md).
> Sunucu kurulumu, alan adı ve yayın bu aşamada **yapılmadı** (kullanıcı kararı); B2B Aşama 10 ile birlikte yapılacak.

## 1. Yasal metinler
- M6'da yalnızca Ön Bilgilendirme Formu ve Mesafeli Satış Sözleşmesi vardı (M6 raporundaki "beş metin" ifadesi hatalıydı). Eklenenler:
  **İptal ve İade Koşulları**, **KVKK Aydınlatma Metni**, **Gizlilik ve Çerez Politikası** (ilk sürümler, hukukçu kontrolü gerekir).
- Yeni yer tutucular: `{IADE_SURESI}` (Ödeme ve Teslimat → İade süresi), `{IADE_ADRESI}` (iade adresi, yoksa mağaza adresi).
- Ödemede yalnızca iki sözleşme onaylanır ve siparişe kopyalanır (`StoreLegalDocument::CHECKOUT_KEYS`).
- Alt bilgide **Yasal** bölümü: İptal ve İade, Mesafeli Satış, KVKK, Gizlilik ve Çerez, Çerez tercihleri. Üyelik formundaki aydınlatma metni bağlantısı KVKK sayfasına gider.
- Alt bilgide telif satırında ticari unvan gösterilir.

## 2. Çerez onayı
- İlk ziyarette alt köşede bant: **Kabul et** / **Yalnızca zorunlu** ve Çerez Politikası bağlantısı.
- Tercih `magaza_cerez` çerezinde 1 yıl saklanır (`essential` / `all`); şifrelenmez, tarayıcıda yazılır.
- Şu anda yalnızca zorunlu çerezler var. İleride analiz/reklam betiği eklenirse yalnızca `window.storeConsent() === 'all'` iken yüklenmelidir.
- "Çerez tercihleri" bağlantısı bandı yeniden açar.

## 3. Bot koruması
- `store.bot` ara katmanı + `<x-store.bot-trap />` bileşeni: görünmez tuzak alanı (`website`) ve formun gösterildiği anın şifreli zamanı (`_ft`).
- Form 2 saniyeden kısa sürede ya da 12 saatten eski gönderilirse, tuzak alanı doluysa reddedilir ("Form doğrulanamadı…").
- Uygulanan formlar: üyelik, giriş (yalnızca tuzak alanı; parola yöneticileri formu anında gönderebilir), parolamı unuttum, ödeme, iade talebi.
- "Parolamı unuttum" rotasına eksik olan hız sınırı eklendi (10 dakikada 5).
- Testlerde `STORE_BOT_PROTECTION=false`; korumanın kendi testi açarak çalışır.
- İsteğe bağlı ek: Cloudflare Turnstile (anahtar gerektirir) — yayın aşamasında değerlendirilecek.

## 4. KVKK: hesabı kapatma
- **Hesabım → Hesabı kapat** (parola onayı). Açık sipariş veya iade varken kapatılamaz.
- Ad, e-posta, telefon, adres defteri ve sepet silinir/anonimleştirilir; ticari ileti izni kaldırılır; oturum kapanır; aynı e-postayla yeniden üye olunabilir.
- Siparişler (kendi ad/adres kopyalarıyla) vergi mevzuatı gereği saklanır. İşlem geçmişine kişisel veri yazılmaz.

## 5. Yayın hazırlığı (Ayarlar → Yayın Hazırlığı)
iyzico üye işyeri başvurusunda incelenen ve yayından önce yapılacak maddeler, durumlarıyla (Tamam / Kontrol edin / Eksik / Yayın aşamasında) ve düzeltilecek ekran bağlantısıyla:
- Satıcı bilgileri, MERSİS, yasal metinlerin gözden geçirilmesi (yönetimden kaydedilmiş sürüm), Hakkımızda (`hakkimizda`) ve İletişim (`iletisim`) sayfaları, ödeme logo bandı, çerez/KVKK, ürünler ve görselleri.
- Kart ödemesi (sandbox/canlı), kapıda ödeme SMS'i, kargo takip bağlantıları, iade adresi.
- Yöneticilerde iki adımlı doğrulama, sistem e-postası, alan adı ve HTTPS, zamanlanmış görevler, yedekleme.

**Ödeme logo bandı:** Mağaza Ayarları'ndan yüklenir (iyzico üye işyeri panelinden indirilen "iyzico ile Öde" ve kart logoları görseli); alt bilgide gösterilir.

## 6. Testler
`StoreComplianceTest` (4 test): yasal sayfalar ve alt bilgi, çerez bandı, bot koruması, KVKK hesap kapatma, yayın hazırlığı ve logo bandı.
