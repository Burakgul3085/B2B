<?php

namespace App\Domain\Mail;

/**
 * Kurumsal e-posta şablonları. İşletme yalnızca konu ve giriş metnini değiştirebilir (düz metin);
 * görsel düzen (logo, renk, buton, bilgi notu, alt bilgi) kodda sabittir. Yer tutucular güvenli biçimde doldurulur.
 *
 * eyebrow: başlığın üstündeki kategori etiketi; note: metnin altındaki sabit bilgi kutusu.
 */
final class EmailTemplates
{
    public const DEFAULTS = [
        'application_received' => [
            'label' => 'Yeni bayilik başvurusu (size)',
            'eyebrow' => 'Bayilik başvurusu',
            'subject' => 'Yeni bayilik başvurusu: {bayi}',
            'intro' => "{bayi} bayiniz olmak için başvuru gönderdi.\n\nBaşvuru formundaki firma bilgilerini, yetkili kişiyi ve yüklenen belgeleri panelinizden inceleyebilirsiniz. Uygun bulursanız başvuruyu onaylayarak bayinin hesabını tek adımda açabilir, eksik bilgi varsa başvuru sahibinden ek bilgi isteyebilirsiniz.",
            'note' => 'Başvuruyu hızlı yanıtlamak, yeni bayinizle güçlü bir başlangıç yapmanızı sağlar.',
        ],
        'application_received_applicant' => [
            'label' => 'Başvuru alındı (başvuru sahibine)',
            'eyebrow' => 'Başvurunuz alındı',
            'subject' => '{isletme} bayilik başvurunuz alındı',
            'intro' => "Sayın {yetkili},\n\n{bayi} adına yaptığınız bayilik başvurusu tarafımıza ulaştı. İlginiz için teşekkür ederiz.\n\nBaşvurunuz ekibimiz tarafından incelenecek ve en kısa sürede sizinle iletişime geçilecektir. Değerlendirme sırasında ek bilgi veya belge gerekirse bu e-posta adresi üzerinden size ulaşacağız.",
            'note' => 'Bu e-postayı yanıtlayarak başvurunuzla ilgili sorularınızı bize iletebilirsiniz.',
        ],
        'application_approved' => [
            'label' => 'Başvuru onayı ve hesap bilgileri (bayiye)',
            'eyebrow' => 'Hoş geldiniz',
            'subject' => '{isletme} bayilik başvurunuz onaylandı',
            'intro' => "Bayilik başvurunuz onaylandı; {isletme} bayi ailesine hoş geldiniz!\n\nBayi hesabınız açıldı. Giriş e-postanız: {eposta}. Şifreniz size ayrıca iletilecektir.\n\nPanelimizden size özel fiyatlarla katalogu inceleyebilir, sipariş verebilir, siparişlerinizin durumunu adım adım takip edebilir ve cari hesap ekstrenizi görüntüleyebilirsiniz.",
            'note' => 'İlk girişinizden sonra Profil ve Güvenlik ekranından şifrenizi değiştirmenizi öneririz. Şifrenizi kimseyle paylaşmayın.',
        ],
        'relationship_added' => [
            'label' => 'Mevcut bayi hesabına yeni toptancı (bayiye)',
            'eyebrow' => 'Yeni toptancı',
            'subject' => '{isletme} ile çalışmaya başladınız',
            'intro' => "Bayilik başvurunuz onaylandı; artık {isletme} ile de çalışabilirsiniz.\n\nYeni bir hesap açmanıza gerek yok. Mevcut bayi hesabınızla giriş yaptığınızda toptancı seçim ekranında {isletme} de listelenecek; size özel fiyatlarla katalogu inceleyip hemen sipariş verebilirsiniz.",
            'note' => 'Birden fazla toptancıyla çalışırken siparişleriniz ve cari hesaplarınız her toptancı için ayrı tutulur.',
        ],
        'dealer_activation' => [
            'label' => 'Bayi hesabı açıldı (bayiye)',
            'eyebrow' => 'Hesabınız hazır',
            'subject' => '{isletme} bayi hesabınız hazır',
            'intro' => "{isletme} bayi hesabınız açıldı ve kullanıma hazır.\n\nGiriş e-postanız: {eposta}. Şifreniz size ayrıca iletilecektir.\n\nPanelimizde size özel fiyatlarla güncel katalog, sipariş takibi, cari hesap ekstresi, ürün talepleri ve mesajlaşma sizi bekliyor.",
            'note' => 'İlk girişinizden sonra Profil ve Güvenlik ekranından şifrenizi değiştirmenizi öneririz. Şifrenizi kimseyle paylaşmayın.',
        ],
        'password_reset' => [
            'label' => 'Parola sıfırlama (hesap sahibine)',
            'eyebrow' => 'Hesap güvenliği',
            'subject' => '{isletme} hesabınız için parola sıfırlama',
            'intro' => "Hesabınız için parola sıfırlama talebi aldık.\n\nAşağıdaki düğmeye tıklayarak yeni parolanızı belirleyebilirsiniz. Güvenliğiniz için bağlantı 60 dakika boyunca ve yalnızca bir kez kullanılabilir.",
            'note' => 'Bu talebi siz yapmadıysanız bu e-postayı dikkate almayın; parolanız değişmez. Çalışanlarımız sizden asla parolanızı istemez.',
        ],
        'login_code' => [
            'label' => 'Giriş doğrulama kodu (hesap sahibine)',
            'eyebrow' => 'Giriş doğrulaması',
            'subject' => '{isletme} giriş doğrulama kodunuz',
            'intro' => "Hesabınıza giriş yapılıyor. Girişi tamamlamak için aşağıdaki 4 haneli kodu giriş ekranına yazın.\n\nKod 10 dakika geçerlidir ve yalnızca bir kez kullanılabilir.",
            'note' => 'Bu girişi siz yapmadıysanız kodu kimseyle paylaşmayın ve şifrenizi hemen değiştirin. Çalışanlarımız sizden asla bu kodu istemez.',
        ],
        'application_rejected' => [
            'label' => 'Başvuru reddi (bayiye)',
            'eyebrow' => 'Başvuru sonucu',
            'subject' => '{isletme} bayilik başvurunuz hakkında',
            'intro' => "Bayilik başvurunuz için gösterdiğiniz ilgiye teşekkür ederiz.\n\nBaşvurunuz değerlendirildi; ancak bu aşamada olumlu sonuçlanmadı.\n\nAçıklama: {neden}",
            'note' => 'Koşullarınız değiştiğinde yeniden başvurabilirsiniz. Sorularınız için bu e-postayı yanıtlayabilirsiniz.',
        ],
        'application_info_requested' => [
            'label' => 'Başvuru için ek bilgi talebi (bayiye)',
            'eyebrow' => 'Ek bilgi gerekiyor',
            'subject' => '{isletme} başvurunuz için ek bilgi',
            'intro' => "Bayilik başvurunuzu değerlendirebilmemiz için bazı ek bilgilere ihtiyacımız var:\n\n{neden}\n\nBilgileri ve gerekiyorsa belgeleri aşağıdaki bağlantıdan gönderebilirsiniz. Bilgiler elimize ulaştığında değerlendirmeye kaldığımız yerden devam edeceğiz.",
            'note' => 'Bağlantı 14 gün geçerlidir. Başvurunuz, istenen bilgiler tamamlanana kadar beklemede tutulur.',
        ],
        'application_info_replied' => [
            'label' => 'Başvurudan ek bilgi geldi (size)',
            'eyebrow' => 'Ek bilgi geldi',
            'subject' => '{bayi} başvurusuna ek bilgi geldi',
            'intro' => "{bayi}, bayilik başvurusu için istediğiniz ek bilgileri gönderdi.\n\nAçıklamayı ve varsa yüklenen belgeleri başvuru ekranında inceleyebilirsiniz.",
            'note' => 'Başvuru yeniden "İncelemede" durumuna alındı.',
        ],
        'order_submitted' => [
            'label' => 'Yeni sipariş (size)',
            'eyebrow' => 'Yeni sipariş',
            'subject' => 'Yeni sipariş {siparis_no} — {bayi}',
            'intro' => "{bayi} yeni bir sipariş gönderdi.\n\nSipariş numarası {siparis_no}, genel toplam {tutar}. Sipariş kalemlerini panelinizden inceleyebilir; stok durumuna göre tamamen veya kısmen onaylayabilir, gerekirse gerekçesiyle reddedebilirsiniz.",
            'note' => 'Stok, sipariş onayında düşülür; onaylanana kadar sipariş stok ayırmaz.',
        ],
        'order_status' => [
            'label' => 'Sipariş durumu (bayiye)',
            'eyebrow' => 'Sipariş takibi',
            'subject' => 'Siparişiniz {siparis_no}: {durum}',
            'intro' => "{siparis_no} numaralı siparişinizin durumu güncellendi.\n\nYeni durum: {durum}\n{neden}",
            'note' => 'Siparişinizin tüm aşamalarını, kalemlerini ve teslim planını Siparişlerim ekranından takip edebilirsiniz.',
        ],
        'cancel_requested' => [
            'label' => 'İptal talebi (size)',
            'eyebrow' => 'İptal talebi',
            'subject' => 'İptal talebi: {siparis_no} — {bayi}',
            'intro' => "{bayi}, {siparis_no} numaralı sipariş için iptal talep etti.\n\nGerekçe: {neden}\n\nTalebi panelinizden kabul edebilir veya reddedebilirsiniz. Kabul ederseniz stok ve cari kayıtları otomatik olarak düzeltilir.",
            'note' => 'Talep yanıtlanana kadar sipariş mevcut aşamasında bekler.',
        ],
        'new_message' => [
            'label' => 'Yeni mesaj',
            'eyebrow' => 'Yeni mesaj',
            'subject' => 'Yeni mesaj: {konu}',
            'intro' => "{gonderen} size yeni bir mesaj gönderdi:\n\n“{onizleme}”\n\nMesajın tamamını görmek ve yanıtlamak için panele gidin.",
            'note' => 'Mesajlarınız ve ekleri panelde güvenle saklanır; konuşma geçmişine her zaman ulaşabilirsiniz.',
        ],
        'request_update' => [
            'label' => 'Ürün talebi güncellemesi (bayiye)',
            'eyebrow' => 'Ürün talebi',
            'subject' => 'Ürün talebiniz: {urun}',
            'intro' => "{urun} için oluşturduğunuz ürün talebinin durumu güncellendi.\n\nYeni durum: {durum}\n{neden}",
            'note' => 'Taleplerinizin tamamını Taleplerim ekranından takip edebilirsiniz.',
        ],
        'stock_arrived' => [
            'label' => 'Talep edilen ürün stoğa girdi (bayiye)',
            'eyebrow' => 'Stok bildirimi',
            'subject' => '{urun} stoğa girdi',
            'intro' => "Beklediğiniz haber: talep ettiğiniz {urun} stoğa girdi.\n\nÜrün sayfasına giderek size özel fiyatla hemen sipariş verebilirsiniz.",
            'note' => 'Talep stok ayırmaz; stok sipariş onayında kesinleşir. Yoğun talep gören ürünlerde erken sipariş vermenizi öneririz.',
        ],
        'payment_reminder' => [
            'label' => 'Vade hatırlatması (bayiye)',
            'eyebrow' => 'Cari hesap',
            'subject' => '{isletme} cari hesap vade hatırlatması',
            'intro' => "Cari hesabınızla ilgili hatırlatma yapmak isteriz.\n\nVadesi geçmiş bakiye: {gecikmis}\nÖnümüzdeki günlerde vadesi gelecek bakiye: {yaklasan}\n\nHesap hareketlerinizi ve ekstrenizi panelden ayrıntılı olarak görüntüleyebilirsiniz.",
            'note' => 'Ödemenizi yaptıysanız bu hatırlatmayı dikkate almayın; tahsilat kaydı işlendiğinde bakiyeniz güncellenir.',
        ],
        'announcement' => [
            'label' => 'Duyuru (bayilere)',
            'eyebrow' => 'Duyuru',
            'subject' => '{baslik}',
            'intro' => '{metin}',
            'note' => 'Duyuruya eklenen dosyalar varsa panelinizdeki Duyurular sayfasından indirebilirsiniz.',
        ],
    ];

    /** Yer tutucuları doldurur. Değerler düz metindir; HTML kaçışı Blade şablonunda yapılır. */
    public static function fill(string $text, array $vars): string
    {
        // Yalnızca şablondaki yer tutucular değiştirilir; değerlerin içindeki süslü parantezlere dokunulmaz.
        $filled = preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => (string) ($vars[$m[1]] ?? ''), $text);

        // Boş kalan yer tutucuların bıraktığı fazla boş satırlar toplanır.
        return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace("/[ \t]+\n/", "\n", $filled)));
    }

    /** Şablonun sabit görsel parçaları (etiket ve bilgi notu). */
    public static function meta(?string $key): array
    {
        $default = self::DEFAULTS[$key] ?? [];

        return ['eyebrow' => $default['eyebrow'] ?? null, 'note' => $default['note'] ?? null];
    }
}
