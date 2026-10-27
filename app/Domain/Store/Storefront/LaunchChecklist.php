<?php

namespace App\Domain\Store\Storefront;

use App\Domain\Store\Content\SitePages;
use App\Domain\Store\Mail\StoreMailer;
use App\Domain\Store\Payments\IyzicoClient;
use App\Domain\Store\Sms\SmsGateway;
use App\Models\Store\StoreAdmin;
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
    public function __construct(private IyzicoClient $iyzico, private SmsGateway $sms, private StoreMailer $mailer) {}

    /** @return list<array{group: string, items: list<array{label: string, status: string, hint: string, route: ?string}>}> */
    public function groups(): array
    {
        $s = StoreSetting::values();
        $missingSeller = array_keys(array_filter([
            'ticari unvan' => blank($s['company_title']), 'vergi dairesi' => blank($s['company_tax_office']), 'vergi numarası' => blank($s['company_tax_number']),
            'adres' => blank($s['address']), 'telefon' => blank($s['phone']), 'e-posta' => blank($s['email']),
        ]));
        $unreviewed = collect(StoreLegalDocument::KEYS)->filter(fn ($title, $key) => ! StoreLegalDocument::where('key', $key)->where('created_by_type', 'store_admin')->exists());
        $livePages = array_column(SitePages::published(), 'key');
        $missingPages = array_values(array_map(fn ($p) => $p['title'], array_diff_key(SitePages::PAGES, array_flip($livePages))));
        $published = StoreProduct::where('is_published', true)->count();
        $withoutImage = StoreProduct::where('is_published', true)->whereDoesntHave('images')->count();
        $without2fa = StoreAdmin::where('is_active', true)->get()->reject(fn (StoreAdmin $a) => $a->hasTwoFactorEnabled())->count();
        $cod = $s['cod_enabled'] === '1';

        return [
            ['group' => 'iyzico başvurusu', 'items' => [
                $this->item('Satıcı bilgileri', $missingSeller ? 'todo' : 'ok',
                    $missingSeller ? 'Eksik: '.implode(', ', $missingSeller).'.' : 'Unvan, vergi bilgileri ve iletişim bilgileri alt bilgide ve sözleşmelerde görünüyor.', 'store_admin.settings.edit'),
                $this->item('MERSİS numarası', blank($s['company_mersis']) ? 'warn' : 'ok', 'Şirketler için sözleşmelerde gösterilmesi beklenir; şahıs işletmesiyse boş kalabilir.', 'store_admin.settings.edit'),
                $this->item('Yasal metinler gözden geçirildi', $unreviewed->isEmpty() ? 'ok' : 'todo',
                    $unreviewed->isEmpty() ? 'Tüm metinlerin mağaza yönetiminden kaydedilmiş bir sürümü var.' : 'Hukukçu kontrolünden sonra kaydedin: '.$unreviewed->implode(', ').'.', 'store_admin.legal.index'),
                $this->item('Hakkımızda ve İletişim sayfaları', $missingPages ? 'todo' : 'ok',
                    $missingPages ? 'Yayında olmayan sayfa: '.implode(', ', $missingPages).'. Vitrin menüsünden düzenleyip "Yayında" işaretleyin.' : 'Sayfalar yayında.',
                    in_array('Hakkımızda', $missingPages, true) ? 'store_admin.site-pages.about' : 'store_admin.site-pages.contact'),
                $this->item('Ödeme logo bandı', blank($s['payment_logos_path']) ? 'todo' : 'ok', 'iyzico "iyzico ile Öde" ve kart logolarının alt bilgide bulunmasını ister.', 'store_admin.settings.edit'),
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
                $this->item('İade adresi', blank($s['return_address']) && blank($s['address']) ? 'todo' : 'ok', 'Onaylanan iade e-postasında müşteriye gösterilir.', 'store_admin.payments.edit'),
            ]],
            ['group' => 'Güvenlik ve işletim', 'items' => [
                $this->item('Yöneticilerde iki adımlı doğrulama', $without2fa ? 'warn' : 'ok', $without2fa ? "{$without2fa} etkin yönetici iki adımlı doğrulamayı açmadı." : 'Tüm etkin yöneticilerde açık.', null),
                $this->item('Sistem e-postası', $this->mailer->isConfigured() ? 'ok' : 'later', 'Sipariş, bildirim ve e-bülten e-postaları için Mağaza Ayarları → E-posta gönderimi bölümüne SMTP hesabı girin.', null),
                $this->item('Alan adı ve HTTPS', str_ends_with((string) config('store.domain'), '.localhost') ? 'later' : 'ok', 'yapbozkirtasiye.com ve alt alan adları sunucu kurulumunda bağlanacak.', null),
                $this->item('Zamanlanmış görevler ve kuyruk', 'later', 'Süresi dolan kart ödemelerinin kapanması ve e-postalar için sunucuda schedule:run ve queue:work sürekli çalışmalı.', null),
                $this->item('Yedekleme', 'later', 'Veritabanı ve yüklenen dosyaların günlük yedeği ve geri yükleme provası sunucu aşamasında.', null),
            ]],
        ];
    }

    private function item(string $label, string $status, string $hint, ?string $route): array
    {
        return compact('label', 'status', 'hint', 'route');
    }
}
