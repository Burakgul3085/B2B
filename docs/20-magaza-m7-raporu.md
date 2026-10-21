# Mağaza M7 — Kargo, İade, Para İadesi ve Fatura

> Testler: 332/332 (eşzamanlılık 11/11). Önceki aşama: [19-magaza-m6-raporu.md](19-magaza-m6-raporu.md).

## 1. Kargo firmaları

- **Ayarlar → Kargo Firmaları:** Firma adı, takip bağlantısı şablonu, sıra, etkin/pasif.
  - Başlangıçta Yurtiçi, Aras, MNG, PTT ve Sürat Kargo eklidir; takip bağlantıları boştur.
  - Takip bağlantısı `https://` ile başlar, takip numarasının geleceği yere `{takip}` yazılır. Örnek: `https://firma.com/sorgu?no={takip}`.
- **Kargoya verme:** Yönetici siparişte firmayı listeden seçer ve takip numarasını girer. Pasif firma seçilemez.
- Firma adı ve oluşan takip bağlantısı siparişe kopyalanır. Firma sonradan değişse ya da silinse de eski sipariş etkilenmez.
- Müşteri sipariş sayfasında ve "Siparişiniz yolda" e-postasında **Kargoyu takip et** bağlantısını görür.
- Firma API'leriyle otomatik gönderi oluşturma (barkod, durum sorgulama) bir anlaşma yapıldığında eklenecek.

## 2. İade talepleri (cayma hakkı)

- **Müşteri:** Teslim edilen siparişin sayfasında, teslimden sonraki iade süresi içinde **İade talebi oluştur** formu çıkar. Üye müşteri hesabından, misafir e-postadaki sipariş bağlantısından ulaşır.
  - Ürün ve adet seçilir, iade nedeni seçilir, açıklama isteğe bağlıdır.
  - **Kapıda ödemede** para havaleyle döneceği için IBAN ve hesap sahibi zorunludur. IBAN'ın TR biçimi ve kontrol hanesi denetlenir.
- **Kurallar:**
  - İade süresi `Ödeme ve Teslimat → İade` ayarındadır. Varsayılan ve en kısa süre 14 gündür, en fazla 90 gün yapılabilir.
  - Bir siparişte aynı anda tek açık talep olabilir. Bir ürün için en fazla, daha önce reddedilmemiş taleplerde istenmemiş adet kadar iade istenebilir.
  - Başka müşterinin siparişine ya da yanlış bağlantıyla talep açılamaz (404).
- **Yönetim → Satış → İadeler:** Durum filtreleri ve arama vardır; yeni talepler menüde rozetle sayılır. Bir talep şu adımlardan geçer:
  1. **Onayla:** İsteğe bağlı not eklenebilir. Müşteriye iade adresi e-postayla gider (ayardaki iade adresi, yoksa mağaza adresi).
  2. **Reddet:** Gerekçe zorunludur ve müşteriye bildirilir. Reddedilen adetler için yeniden talep açılabilir.
  3. **Ürünler teslim alındı:** Her ürün için satılabilir durumdaki adet girilir. Bu adetler **stoğa geri eklenir**; hasarlı adetler eklenmez.
  4. **Para iadesi:** Ancak ürün teslim alındıktan sonra yapılabilir (aşağıda).
- Her adım sipariş geçmişine ve işlem geçmişine yazılır; müşteriye e-posta gider.

## 3. Para iadesi

- **Tutar sunucuda hesaplanır:**
  - İade edilen ürünlerin sipariş satırındaki payı.
  - Siparişteki tüm ürünler iade edilmişse, buna teslimat ücreti ve kapıda ödeme bedeli de eklenir.
  - Yönetici daha düşük bir tutar girebilir; hesaplanandan fazlasını giremez.
- **Kartla ödeme (iyzico):**
  - iyzico'da sepetteki her kalem ayrı bir işlemdir. Ödeme sonucunda bu işlem kimlikleri artık ödeme kaydına yazılıyor.
  - İade tutarı önce iade edilen ürünlerin işlemlerine, tam iadede teslimat işlemine dağıtılır.
  - Her işlemden ne kadar iade edildiği tutulur; bir işlemden ödenenden fazlası iade edilemez.
  - iyzico'da bir kısım başarısız olursa başarılı kısım kaydedilir ve hata yöneticiye gösterilir. Yeniden denemede yalnızca kalan tutar gönderilir.
- **Kapıda ödeme:**
  - Yönetici, müşterinin bildirdiği IBAN'a havale/EFT yapar, sonra iadeyi açıklamayla kaydeder (ör. "21.10.2026 EFT, dekont 555").
- **Çift ödemeye karşı:** Aynı siparişe ya da aynı iade talebine aynı anda iki para iadesi yapılamaz (kilit). Tamamlanmış iade tekrar ödenemez.
- Siparişte "İade edilen" tutarı ve ödeme durumu ("kısmen iade edildi" / "iade edildi") gösterilir.

## 4. Kartla ödenmiş siparişin iptali

- M6'da engelliydi; artık **İptal et ve ödemeyi iade et** ile yapılabiliyor (ödendi veya hazırlanıyor durumunda).
- Önce ödemenin tamamı iyzico üzerinden karta iade edilir. **İade başarısız olursa sipariş iptal edilmez**; tekrar denenebilir.
- İade başarılı olunca sipariş iptal edilir ve ürünler stoğa döner. Müşteriye giden iptal e-postasında paranın karta iade edildiği yazar.
- Stok yalnızca bu sipariş için gerçekten düşülmüşse geri eklenir. "Ödendi ama stok yetersizdi" durumunda olmayan stok eklenmez.

## 5. Fatura (elle)

- Siparişin **Fatura** kartında fatura numarası, tarihi ve PDF'i eklenir.
  - Numara yalnızca harf, rakam, `-` ve `/` içerebilir ve benzersizdir.
  - Dosya en fazla 5 MB olabilir ve içeriği gerçekten PDF olmalıdır (`%PDF-` ile başlar).
- Müşteriye "Faturanız hazır" e-postası gider; sipariş sayfasında **Faturayı indir** bağlantısı çıkar.
- PDF'ler web'den doğrudan erişilemeyen klasörde (`storage/app/private/store/invoices/`) durur. Yalnızca yönetici, siparişin sahibi müşteri veya sipariş bağlantısını bilen kişi indirebilir.
- Fatura değiştirilebilir: PDF boş bırakılırsa eskisi korunur, yenisi yüklenirse eskisi silinir. Değişiklik işlem geçmişine yazılır.
- **e-Arşiv (Uyumsoft):** Aynı fatura kaydına otomatik bağlanacak. Uyumsoft API kullanıcısı ve test ortamı bilgileri gelince yazılacak.

## 6. İstek sınırlarındaki hata düzeltildi (B2B dahil)

- `throttle:x,y` önek verilmeden kullanıldığında aynı kullanıcı ya da IP için **bütün rotalar tek sayacı paylaşıyormuş**. Örnekler:
  - Mağazada aramalar ve sepete eklemeler ödeme sayfasının 10 dakikalık sınırını doldurabiliyordu.
  - B2B'de aynı IP'den 5 giriş denemesi bayi başvuru formunu bir saatliğine kilitleyebiliyordu.
- Mağaza ve B2B'deki bütün sınırlı rotalara kendi önekleri (rota adı) verildi.
- `ThrottleKeysTest`, ileride öneksiz sınır eklenirse testi düşürür.

## 7. Testler

- `StoreFulfillmentTest` (9 test):
  - kargo firması doğrulaması ve takip bağlantısı;
  - kartlı siparişin iptali ve kalem bazında iade;
  - kısmi başarısız iade ve yeniden deneme;
  - satış hareketi olmayan siparişin iptali;
  - kartla kısmi ve tam iade (kargo ücreti dahil);
  - ret ve iade süresi;
  - kapıda ödemede IBAN ve elle iade;
  - fatura yükleme ve indirme;
  - başka müşterinin faturasına ve iadesine erişilememesi.
- `ThrottleKeysTest` (1 test).

## 8. Sonraki aşama (M8)

Kampanya ve kupon, mağaza raporları (satış, ürün, ödeme türü, iade), yönetici bildirimleri (yeni sipariş, yeni iade talebi, kritik stok).
