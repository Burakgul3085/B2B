<?php

namespace App\Domain\Store\Storefront;

use App\Domain\Store\Content\SitePages;
use App\Domain\Store\Mail\StoreMailer;
use App\Domain\Store\Orders\LegalDocuments;
use App\Domain\Store\Payments\IyzicoClient;
use App\Domain\Store\Sms\SmsGateway;
use App\Domain\Support\OpsStatus;
use App\Models\Store\StoreCarrier;
use App\Models\Store\StoreLegalDocument;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreSetting;

/**
 * Yayın hazırlığı ve iyzico üye işyeri başvurusunda incelenen maddeler (docs/22-magaza-m9-raporu.md §5).
 * Her madde: durum (ok / warn / todo / later), açıklama ve düzeltileceği ekran. "later": sunucu/yayın aşamasında yapılır.
 */
class LaunchChecklist
{
    public function __construct(private IyzicoClient $iyzico, private SmsGateway $sms, private StoreMailer $mailer, private OpsStatus $ops) {}

    /** @return list<array{group: string, items: list<array{label: string, status: string, hint: string, route: ?string}>}> */
    public function groups(): array
    {
        $s = StoreSetting::values();
        $missingSeller = array_keys(array_filter([
            'ticari unvan' => blank($s['company_title']), 'vergi dairesi' => blank($s['company_tax_office']), 'vergi numarası' => blank($s['company_tax_number']),
            'adres' => blank($s['address']), 'telefon' => blank($s['phone']), 'e-posta' => blank($s['email']),
        ]));
        $unreviewed = collect(StoreLegalDocument::KEYS)->reject(fn ($title, $key) => LegalDocuments::isReviewed($key));
        $livePages = array_column(SitePages::published(), 'key');
        $missingPages = array_values(array_map(fn ($p) => $p['title'], array_diff_key(SitePages::PAGES, array_flip($livePages))));
        $published = StoreProduct::where('is_published', true)->count();
        $withoutImage = StoreProduct::where('is_published', true)->whereDoesntHave('images')->count();
        $cod = $s['cod_enabled'] === '1';
        $local = str_ends_with((string) config('store.domain'), '.localhost');

        return [
            ['group' => 'iyzico başvurusu', 'items' => [
                $this->item('Satıcı bilgileri', $missingSeller ? 'todo' : 'ok',
                    $missingSeller ? 'Eksik: '.implode(', ', $missingSeller).'.' : 'Unvan, vergi bilgileri ve iletişim bilgileri alt bilgide ve sözleşmelerde görünüyor.', 'store_admin.settings.edit'),
                $this->item('MERSİS / Esnaf sicil numarası', blank($s['company_mersis']) && blank($s['company_esnaf_no']) ? 'warn' : 'ok',
                    'Şirketlerde MERSİS, şahıs işletmelerinde Esnaf ve Sanatkârlar sicil numarası sözleşmelerde ve iletişim sayfasında gösterilir.', 'store_admin.settings.edit'),
                $this->item('Yasal metinler gözden geçirildi', $unreviewed->isEmpty() ? 'ok' : 'todo',
                    $unreviewed->isEmpty() ? 'Tüm metinler gözden geçirildi (yönetimde düzenlendi veya onaylandı).' : 'Hukukçu kontrolünden sonra düzenleyip kaydedin ya da "Gözden geçirildi" olarak işaretleyin: '.$unreviewed->implode(', ').'.', 'store_admin.legal.index'),
                $this->item('Hakkımızda ve İletişim sayfaları', $missingPages ? 'todo' : 'ok',
                    $missingPages ? 'Yayında olmayan sayfa: '.implode(', ', $missingPages).'. Vitrin menüsünden düzenleyip "Yayında" işaretleyin.' : 'Sayfalar yayında.',
                    in_array('Hakkımızda', $missingPages, true) ? 'store_admin.site-pages.about' : 'store_admin.site-pages.contact'),
                $this->item('Ödeme logo bandı', 'ok', blank($s['payment_logos_path'])
                    ? "Alt bilgide iyzico'nun resmî logo bandı (iyzico ile Öde, Mastercard, Visa, American Express, Troy) gösteriliyor."
                    : 'Alt bilgide mağaza ayarlarından yüklenen logo bandı gösteriliyor.', 'store_admin.settings.edit'),
                $this->item('Çerez ve KVKK bilgilendirmesi', 'ok', 'Çerez bandı, KVKK Aydınlatma Metni ve Gizlilik ve Çerez Politikası alt bilgide; üyelikte aydınlatma onayı alınıyor.', null),
                $this->item('Ürünler', $published === 0 ? 'todo' : ($withoutImage ? 'warn' : 'ok'),
                    $published === 0 ? 'Yayında ürün yok.' : "{$published} ürün yayında".($withoutImage ? ", {$withoutImage} ürünün görseli yok." : '.'), 'store_admin.products.index'),
            ]],
            ['group' => 'Ödeme ve teslimat', 'items' => [
                $this->item('Kartla ödeme (iyzico)', ! $this->iyzico->isConfigured() ? 'todo' : ($s['iyzico_mode'] === 'live' ? 'ok' : 'warn'),
                    ! $this->iyzico->isConfigured() ? 'API anahtarları girilmedi.' : ($s['iyzico_mode'] === 'live' ? 'Canlı anahtarlar kayıtlı.' : 'Deneme (sandbox) modunda; başvuru onayından sonra canlı anahtarlar girilmeli.'), 'store_admin.payments.edit'),
                $this->item('Kapıda ödeme SMS doğrulaması', ! $cod ? 'warn' : ($this->sms->isLive() ? 'ok' : 'todo'),
                    ! $cod ? 'Kapıda ödeme kapalı.' : ($this->sms->isLive() ? 'Netgsm bağlı.' : 'Netgsm bilgileri girilmedi; canlıda kapıda ödeme açılamaz.'), 'store_admin.payments.edit'),
                $this->item('Kargo takip bağlantıları', StoreCarrier::where('is_active', true)->get()->contains(fn ($c) => $c->trackingTemplate() !== null) ? 'ok' : 'warn',
                    'En az bir kargo firmasının takip bağlantısı olmalı (bilinen firmalarda kendiliğinden gelir); müşteri kargosunu sipariş sayfasından izler.', 'store_admin.carriers.index'),
                $this->item('Kargo firması ücretleri', StoreCarrier::where('is_active', true)->whereNotNull('fee')->exists() ? 'ok' : 'warn',
                    'Anlaştığınız firmaların ücretini girin; müşteri ödeme sayfasında firmayı fiyatıyla seçer. Girilmezse yedek kargo ücreti uygulanır.', 'store_admin.carriers.index'),
                $this->item('e-Fatura / e-Arşiv (Uyumsoft)', $s['invoice_driver'] === 'uyumsoft' ? ($s['uyumsoft_mode'] === 'live' ? 'ok' : 'warn') : 'todo',
                    match (true) {
                        $s['invoice_driver'] !== 'uyumsoft' => 'Deneme sürücüsü açık; Faturalar ekranında kesilen belgeler geçerli fatura değildir. Uyumsoft bilgilerini Fatura Ayarları\'na girin.',
                        $s['uyumsoft_mode'] !== 'live' => 'Uyumsoft test ortamında; denemeler bitince ortamı "Canlı" yapın.',
                        default => 'Uyumsoft canlı ortamda. Faturalar otomatik kesilmez; Faturalar ekranından gönderilir.',
                    }, 'store_admin.invoices.index'),
                $this->item('İade adresi', blank($s['return_address']) && blank($s['address']) ? 'todo' : 'ok', 'Onaylanan iade e-postasında müşteriye gösterilir.', 'store_admin.payments.edit'),
            ]],
            ['group' => 'Güvenlik ve işletim', 'items' => [
                $this->item('Yönetici girişinde e-posta kodu', ! config('b2b.login_code') ? 'warn' : ($this->mailer->isConfigured() ? 'ok' : 'todo'),
                    ! config('b2b.login_code') ? 'Sunucuda kapatılmış (LOGIN_EMAIL_CODE=false); girişte yalnızca şifre isteniyor.'
                        : ($this->mailer->isConfigured() ? 'Her girişte yöneticinin e-postasına 4 haneli kod gidiyor.' : 'E-posta hesabı tanımlı değil; kod gönderilemediği için yöneticiler giriş yapamaz.'), 'store_admin.settings.edit'),
                $this->item('Sistem e-postası', $this->mailer->isConfigured() ? 'ok' : 'later', 'Sipariş, bildirim ve e-bülten e-postaları için Mağaza Ayarları → E-posta gönderimi bölümüne SMTP hesabı girin.', null),
                $this->item('Alan adı ve HTTPS', $local ? 'later' : 'ok', $local ? 'Alan adı ve alt alan adları sunucu kurulumunda bağlanır.' : 'Mağaza '.config('store.domain').', yönetim '.config('store.admin_domain').' adresinde yayında.', null),
                $this->operations(),
                $this->backups(),
            ]],
        ];
    }

    /** Zamanlayıcı (schedule:run, her dakika) ve kuyruk işçisi (queue:work) gerçekten çalışıyor mu: OpsStatus izlerine bakılır. */
    private function operations(): array
    {
        $scheduler = $this->ops->schedulerSeenAt();
        $queue = $this->ops->queueSeenAt();
        $schedulerOk = $scheduler?->gt(now()->subMinutes(5));
        $queueOk = $queue?->gt(now()->subMinutes(15));
        $at = fn ($t) => $t ? $t->timezone('Europe/Istanbul')->format('d.m.Y H:i') : 'hiç';

        $hint = match (true) {
            $schedulerOk && $queueOk => 'Çalışıyor: zamanlayıcı son '.$at($scheduler).', kuyruk işçisi son '.$at($queue).'. Süresi dolan ödemeler, havale iptalleri, stok bildirimleri ve e-postalar işleniyor.',
            ! $schedulerOk => 'Zamanlayıcı çalışmıyor (son iz: '.$at($scheduler).'). Sunucuda her dakika "php artisan schedule:run" çalışmalı (cron).',
            default => 'Kuyruk işçisi çalışmıyor (son iz: '.$at($queue).'). Sunucuda "php artisan queue:work" sürekli çalışmalı (supervisor); e-postalar bekliyor olabilir.',
        };

        return $this->item('Zamanlanmış görevler ve kuyruk', $schedulerOk && $queueOk ? 'ok' : 'todo', $hint, null);
    }

    /** Gece 03:30 yedeği (veritabanı ve yüklenen dosyalar; Platform → Yedekler): son yedeğin tarihine bakılır. */
    private function backups(): array
    {
        $last = $this->ops->lastBackupAt();
        if (! $last) {
            return $this->item('Yedekleme', 'todo', 'Henüz yedek yok. Yedek her gece 03:30\'da alınır; platform yöneticisi Platform → Yedekler\'den hemen de alabilir.', null);
        }
        $fresh = $last->gt(now()->subHours(36));

        return $this->item('Yedekleme', $fresh ? 'ok' : 'warn', 'Son yedek: '.$last->timezone('Europe/Istanbul')->format('d.m.Y H:i').'. '.($fresh
            ? 'Veritabanı ve yüklenen dosyalar her gece 03:30\'da yedekleniyor; yedekler Platform → Yedekler\'den indirilebilir.'
            : 'Son yedek 36 saatten eski; gece yedeği çalışmıyor olabilir.'), null);
    }

    private function item(string $label, string $status, string $hint, ?string $route): array
    {
        return compact('label', 'status', 'hint', 'route');
    }
}
