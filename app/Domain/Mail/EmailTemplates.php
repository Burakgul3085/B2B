<?php

namespace App\Domain\Mail;

/**
 * Kurumsal e-posta şablonları. İşletme yalnızca konu ve giriş metnini değiştirebilir (düz metin);
 * görsel düzen (logo, renk, buton, alt bilgi) kodda sabittir. Yer tutucular güvenli biçimde doldurulur.
 */
final class EmailTemplates
{
    public const DEFAULTS = [
        'application_received' => [
            'label' => 'Yeni bayilik başvurusu (size)',
            'subject' => 'Yeni bayilik başvurusu: {bayi}',
            'intro' => '{bayi} bayilik başvurusu gönderdi. Başvuruyu panelinizden inceleyebilirsiniz.',
        ],
        'application_received_applicant' => [
            'label' => 'Başvuru alındı (başvuru sahibine)',
            'subject' => '{isletme} bayilik başvurunuz alındı',
            'intro' => 'Sayın {yetkili}, {bayi} adına yaptığınız bayilik başvurusu bize ulaştı. Başvurunuzu inceleyip en kısa sürede size dönüş yapacağız.',
        ],
        'application_approved' => [
            'label' => 'Başvuru onayı ve hesap bilgileri (bayiye)',
            'subject' => '{isletme} bayilik başvurunuz onaylandı',
            'intro' => 'Bayilik başvurunuz onaylandı ve bayi hesabınız açıldı. Giriş e-postanız: {eposta}. Şifreniz size ayrıca iletilecektir; giriş yaptıktan sonra Profil ve Güvenlik ekranından e-postanızı ve şifrenizi değiştirebilirsiniz.',
        ],
        'relationship_added' => [
            'label' => 'Mevcut bayi hesabına yeni toptancı (bayiye)',
            'subject' => '{isletme} ile çalışmaya başladınız',
            'intro' => 'Bayilik başvurunuz onaylandı. Mevcut hesabınızla giriş yaparak {isletme} kataloğuna ulaşabilirsiniz.',
        ],
        'dealer_activation' => [
            'label' => 'Bayi hesabı açıldı (bayiye)',
            'subject' => '{isletme} bayi hesabınız hazır',
            'intro' => '{isletme} bayi hesabınız açıldı. Giriş e-postanız: {eposta}. Şifreniz size ayrıca iletilecektir; giriş yaptıktan sonra Profil ve Güvenlik ekranından e-postanızı ve şifrenizi değiştirebilirsiniz.',
        ],
        'password_reset' => [
            'label' => 'Parola sıfırlama (hesap sahibine)',
            'subject' => '{isletme} hesabınız için parola sıfırlama',
            'intro' => 'Hesabınız için parola sıfırlama talebi aldık. Aşağıdaki bağlantıyla yeni parolanızı belirleyin; bağlantı 60 dakika geçerlidir. Bu talebi siz yapmadıysanız bu e-postayı dikkate almayın, parolanız değişmez.',
        ],
        'application_rejected' => [
            'label' => 'Başvuru reddi (bayiye)',
            'subject' => '{isletme} bayilik başvurunuz hakkında',
            'intro' => 'Bayilik başvurunuz değerlendirildi ve bu aşamada olumlu sonuçlanmadı. Açıklama: {neden}',
        ],
        'application_info_requested' => [
            'label' => 'Başvuru için ek bilgi talebi (bayiye)',
            'subject' => '{isletme} başvurunuz için ek bilgi',
            'intro' => 'Bayilik başvurunuzu değerlendirebilmemiz için ek bilgiye ihtiyacımız var: {neden}. Lütfen bu e-postayı yanıtlayın veya bizi arayın.',
        ],
        'order_submitted' => [
            'label' => 'Yeni sipariş (size)',
            'subject' => 'Yeni sipariş {siparis_no} — {bayi}',
            'intro' => '{bayi} {siparis_no} numaralı siparişi gönderdi. Tutar: {tutar}. Siparişi panelinizden onaylayabilirsiniz.',
        ],
        'order_status' => [
            'label' => 'Sipariş durumu (bayiye)',
            'subject' => 'Siparişiniz {siparis_no}: {durum}',
            'intro' => '{siparis_no} numaralı siparişinizin durumu "{durum}" olarak güncellendi. {neden}',
        ],
        'cancel_requested' => [
            'label' => 'İptal talebi (size)',
            'subject' => 'İptal talebi: {siparis_no} — {bayi}',
            'intro' => '{bayi}, {siparis_no} numaralı sipariş için iptal talep etti. Gerekçe: {neden}',
        ],
        'new_message' => [
            'label' => 'Yeni mesaj',
            'subject' => 'Yeni mesaj: {konu}',
            'intro' => '{gonderen} size yeni bir mesaj gönderdi: “{onizleme}”',
        ],
        'request_update' => [
            'label' => 'Ürün talebi güncellemesi (bayiye)',
            'subject' => 'Ürün talebiniz: {urun}',
            'intro' => '{urun} için talebinizin durumu "{durum}" oldu. {neden}',
        ],
        'stock_arrived' => [
            'label' => 'Talep edilen ürün stoğa girdi (bayiye)',
            'subject' => '{urun} stoğa girdi',
            'intro' => 'Talep ettiğiniz {urun} stoğa girdi. Sipariş vermek için kataloğu ziyaret edin. Talep stok ayırmaz; stok sipariş onayında kesinleşir.',
        ],
        'payment_reminder' => [
            'label' => 'Vade hatırlatması (bayiye)',
            'subject' => '{isletme} cari hesap vade hatırlatması',
            'intro' => 'Cari hesabınızda vadesi geçmiş {gecikmis} ve önümüzdeki günlerde vadesi gelecek {yaklasan} tutarında bakiye bulunuyor. Ekstrenizi panelden görüntüleyebilirsiniz.',
        ],
        'announcement' => [
            'label' => 'Duyuru (bayilere)',
            'subject' => '{baslik}',
            'intro' => '{metin}',
        ],
    ];

    /** Yer tutucuları doldurur. Değerler düz metindir; HTML kaçışı Blade şablonunda yapılır. */
    public static function fill(string $text, array $vars): string
    {
        // Yalnızca şablondaki yer tutucular değiştirilir; değerlerin içindeki süslü parantezlere dokunulmaz.
        return trim(preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => (string) ($vars[$m[1]] ?? ''), $text));
    }
}
