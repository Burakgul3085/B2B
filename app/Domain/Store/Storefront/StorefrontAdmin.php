<?php

namespace App\Domain\Store\Storefront;

use App\Domain\Audit\AuditLogger;
use App\Domain\Files\ImageUploader;
use App\Domain\Store\Catalog\Slugs;
use App\Models\Store\StoreBanner;
use App\Models\Store\StorePage;
use App\Models\Store\StoreSetting;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Vitrin yönetimi: banner'lar, sabit sayfalar ve mağaza ayarları. Her değişiklik işlem geçmişine yazılır. */
class StorefrontAdmin
{
    public const BANNER_MAX_BYTES = 8 * 1024 * 1024;

    public function __construct(private AuditLogger $audit, private ImageUploader $images, private ImageVariants $variants) {}

    /* ---------------- Banner'lar ---------------- */

    /** @throws ValidationException|DomainException */
    public function createBanner(array $input, UploadedFile $image, ?UploadedFile $mobile): StoreBanner
    {
        $data = $this->validateBanner($input);
        $banner = new StoreBanner($data + ['sort' => (int) StoreBanner::max('sort') + 1]);
        $banner->image_path = $this->images->store($image, 'store/banners', 2560, self::BANNER_MAX_BYTES, 88);
        $banner->mobile_image_path = $mobile ? $this->images->store($mobile, 'store/banners', 1200, self::BANNER_MAX_BYTES, 88) : null;
        $banner->save();
        $this->audit->log('store.banner_created', $banner, [], ['title' => $banner->title]);

        return $banner;
    }

    /** @throws ValidationException|DomainException */
    public function updateBanner(StoreBanner $banner, array $input, ?UploadedFile $image, ?UploadedFile $mobile, bool $removeMobile): void
    {
        $data = $this->validateBanner($input);
        $old = $banner->only(array_keys($data));
        $banner->fill($data);

        $replaced = [];
        if ($image) {
            $replaced[] = $banner->image_path;
            $banner->image_path = $this->images->store($image, 'store/banners', 2560, self::BANNER_MAX_BYTES, 88);
        }
        if ($mobile || $removeMobile) {
            $replaced[] = $banner->mobile_image_path;
            $banner->mobile_image_path = $mobile ? $this->images->store($mobile, 'store/banners', 1200, self::BANNER_MAX_BYTES, 88) : null;
        }
        $banner->save();
        $this->forgetFiles($replaced);
        $this->audit->log('store.banner_updated', $banner, $old, $banner->only(array_keys($data)));
    }

    public function deleteBanner(StoreBanner $banner): void
    {
        $banner->delete();
        $this->forgetFiles([$banner->image_path, $banner->mobile_image_path]);
        $this->audit->log('store.banner_deleted', $banner, ['title' => $banner->title]);
    }

    public function moveBanner(StoreBanner $banner, string $direction): void
    {
        $all = StoreBanner::orderBy('sort')->orderBy('id')->get()->values();
        $index = $all->search(fn ($b) => $b->id === $banner->id);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if ($index === false || ! isset($all[$swap])) {
            return;
        }
        $ordered = $all->all();
        [$ordered[$index], $ordered[$swap]] = [$ordered[$swap], $ordered[$index]];
        DB::transaction(fn () => collect($ordered)->each(fn ($b, $i) => $b->forceFill(['sort' => $i + 1])->save()));
    }

    /* ---------------- Sayfalar ---------------- */

    /** @throws ValidationException */
    public function savePage(?StorePage $page, array $input): StorePage
    {
        $input['slug'] = Slugs::normalize($input['slug'] ?? null);
        $data = Validator::make($input, [
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('store_pages', 'slug')->ignore($page?->id)],
            'body' => ['nullable', 'string', 'max:100000'],
            'seo_title' => ['nullable', 'string', 'max:160'],
            'seo_description' => ['nullable', 'string', 'max:320'],
        ], [], ['title' => 'başlık', 'slug' => 'adres (kısa ad)', 'body' => 'içerik', 'seo_title' => 'SEO başlığı', 'seo_description' => 'SEO açıklaması'])->validate();

        $data['title'] = trim($data['title']);
        $data['slug'] ??= $page?->slug ?? Slugs::unique(StorePage::class, $data['title']);
        $data['is_active'] = filter_var($input['is_active'] ?? true, FILTER_VALIDATE_BOOL);
        $data['show_in_footer'] = filter_var($input['show_in_footer'] ?? true, FILTER_VALIDATE_BOOL);

        if ($page) {
            $old = $page->only(['title', 'slug', 'is_active', 'show_in_footer']);
            $page->update($data);
            $this->audit->log('store.page_updated', $page, $old, $page->only(['title', 'slug', 'is_active', 'show_in_footer']));

            return $page;
        }

        $page = StorePage::create($data + ['sort' => (int) StorePage::max('sort') + 1]);
        $this->audit->log('store.page_created', $page, [], ['title' => $page->title, 'slug' => $page->slug]);

        return $page;
    }

    public function deletePage(StorePage $page): void
    {
        $page->delete();
        $this->audit->log('store.page_deleted', $page, ['title' => $page->title, 'slug' => $page->slug]);
    }

    /* ---------------- Ayarlar ---------------- */

    /** @throws ValidationException */
    public function saveSettings(array $input): void
    {
        $data = Validator::make($input, [
            'announcement' => ['nullable', 'string', 'max:200'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'footer_about' => ['nullable', 'string', 'max:600'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +()-]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'instagram' => ['nullable', 'url:https', 'max:255'],
            'facebook' => ['nullable', 'url:https', 'max:255'],
            'company_title' => ['nullable', 'string', 'max:200'],
            'company_tax_office' => ['nullable', 'string', 'max:80'],
            'company_tax_number' => ['nullable', 'regex:/^\d{10,11}$/'],
            'company_mersis' => ['nullable', 'regex:/^\d{16}$/'],
            'notify_emails' => ['nullable', 'string', 'max:500'],
        ], ['company_tax_number.regex' => 'Vergi numarası 10, T.C. kimlik numarası 11 haneli olmalıdır.', 'company_mersis.regex' => 'MERSİS numarası 16 haneli olmalıdır.'], [
            'company_title' => 'ticari unvan', 'company_tax_office' => 'vergi dairesi',
            'announcement' => 'duyuru', 'meta_title' => 'SEO başlığı', 'meta_description' => 'SEO açıklaması', 'footer_about' => 'kısa tanıtım',
            'phone' => 'telefon', 'whatsapp' => 'WhatsApp numarası', 'email' => 'e-posta', 'address' => 'adres',
            'notify_emails' => 'bildirim adresleri',
        ])->validate();

        // Bildirim adresleri: virgülle ayrılmış e-postalar (en fazla 10).
        $emails = array_values(array_unique(array_filter(array_map(fn ($e) => strtolower(trim($e)), preg_split('/[,;\s]+/', (string) ($data['notify_emails'] ?? ''))))));
        if (count($emails) > 10 || array_filter($emails, fn ($e) => ! filter_var($e, FILTER_VALIDATE_EMAIL))) {
            throw ValidationException::withMessages(['notify_emails' => 'Geçerli e-posta adreslerini virgülle ayırarak yazın (en fazla 10).']);
        }
        $data['notify_emails'] = $emails ? implode(', ', $emails) : null;
        foreach (['notify_orders', 'notify_returns', 'notify_low_stock'] as $flag) {
            $data[$flag] = filter_var($input[$flag] ?? false, FILTER_VALIDATE_BOOL) ? '1' : '0';
        }

        $old = array_intersect_key(StoreSetting::values(), $data);
        StoreSetting::put(array_map(fn ($v) => $v === null ? null : trim($v), $data));
        $this->audit->log('store.settings_updated', null, array_diff_assoc(array_map('strval', $old), array_map('strval', $data)), array_diff_assoc(array_map('strval', $data), array_map('strval', $old)));
    }

    /**
     * Alt bilgideki ödeme logo bandı (iyzico üye işyeri panelinden indirilen "iyzico ile Öde" ve kart logoları).
     * null: kaldırır.
     *
     * @throws DomainException
     */
    public function setPaymentLogos(?UploadedFile $image): void
    {
        $old = StoreSetting::get('payment_logos_path');
        $path = $image ? $this->images->store($image, 'store/site', 1600, 2 * 1024 * 1024, 90) : null;
        StoreSetting::put(['payment_logos_path' => $path]);
        $this->forgetFiles([$old]);
        $this->audit->log($image ? 'store.payment_logos_updated' : 'store.payment_logos_removed');
    }

    /** Mağazayı ziyaretçilere açar veya kapatır. */
    public function setOpen(bool $open): void
    {
        if (StoreSetting::isOpen() === $open) {
            return;
        }
        StoreSetting::put(['is_open' => $open ? '1' : '0']);
        $this->audit->log($open ? 'store.opened' : 'store.closed');
    }

    /** @throws ValidationException */
    private function validateBanner(array $input): array
    {
        $data = Validator::make($input, [
            'title' => ['nullable', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:240'],
            'button_text' => ['nullable', 'string', 'max:40'],
            'link_url' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ], [], ['title' => 'başlık', 'subtitle' => 'alt başlık', 'button_text' => 'düğme yazısı', 'link_url' => 'bağlantı', 'starts_at' => 'başlangıç', 'ends_at' => 'bitiş'])->validate();

        // Bağlantı: mağaza içi yol (/kategori/...) veya https adresi. javascript: gibi şemalar kabul edilmez.
        $link = trim((string) ($data['link_url'] ?? ''));
        if ($link !== '' && ! preg_match('#^/(?!/)[^\s]*$#', $link) && ! preg_match('#^https://[^\s]+$#i', $link)) {
            throw ValidationException::withMessages(['link_url' => 'Bağlantı "/" ile başlayan bir mağaza adresi (ör. /kategori/kalemler) veya https:// ile başlayan bir adres olmalıdır.']);
        }

        return [
            'title' => $data['title'] ?? null,
            'subtitle' => $data['subtitle'] ?? null,
            'button_text' => $data['button_text'] ?? null,
            'link_url' => $link ?: null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => filter_var($input['is_active'] ?? true, FILTER_VALIDATE_BOOL),
        ];
    }

    /** @param  list<?string>  $paths */
    private function forgetFiles(array $paths): void
    {
        foreach (array_filter($paths) as $path) {
            $this->images->delete($path);
            $this->variants->forget($path);
        }
    }
}
