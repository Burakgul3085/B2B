@php $open = $settings['is_open'] === '1'; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Mağaza Ayarları">
    <x-page-header title="Mağaza Ayarları" subtitle="İletişim bilgileri, duyuru şeridi, ana sayfa SEO'su ve mağazanın yayın durumu." />

    <div class="grid gap-6 xl:grid-cols-3">
        <form method="POST" action="{{ route('store_admin.settings.update') }}" class="space-y-6 xl:col-span-2">
            @csrf @method('PUT')
            <x-card title="İletişim" description="Mağazanın alt bilgisinde ve iletişim alanlarında görünür.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="phone" label="Telefon" :value="$settings['phone']" maxlength="30" placeholder="0222 000 00 00" />
                    <x-field name="whatsapp" label="WhatsApp numarası" :value="$settings['whatsapp']" maxlength="30" placeholder="90 5xx xxx xx xx" />
                    <x-field name="email" label="E-posta" type="email" :value="$settings['email']" />
                    <x-field name="address" label="Adres" :value="$settings['address']" maxlength="500" />
                    <x-field name="instagram" label="Instagram adresi" :value="$settings['instagram']" placeholder="https://instagram.com/..." />
                    <x-field name="facebook" label="Facebook adresi" :value="$settings['facebook']" placeholder="https://facebook.com/..." />
                </div>
            </x-card>
            <x-card title="Satıcı bilgileri" description="Ön Bilgilendirme Formu, Mesafeli Satış Sözleşmesi ve iletişim sayfasında kullanılır; iyzico başvurusunda da istenir.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="company_title" label="Ticari unvan" :value="$settings['company_title']" maxlength="200" class="sm:col-span-2" placeholder="ör. Yapboz Kırtasiye Ltd. Şti." />
                    <x-field name="company_tax_office" label="Vergi dairesi" :value="$settings['company_tax_office']" maxlength="80" />
                    <x-field name="company_tax_number" label="Vergi numarası" :value="$settings['company_tax_number']" inputmode="numeric" maxlength="11" />
                    <x-field name="company_mersis" label="MERSİS numarası" :value="$settings['company_mersis']" inputmode="numeric" maxlength="16" />
                </div>
            </x-card>
            <x-card title="Görünüm ve arama motorları">
                <div class="space-y-5">
                    <x-field name="announcement" label="Duyuru şeridi" :value="$settings['announcement']" maxlength="200" hint="Tüm sayfaların en üstünde görünür (ör. kampanya duyurusu). Boşsa gösterilmez." />
                    <x-field name="footer_about" label="Kısa tanıtım" :value="$settings['footer_about']" textarea hint="Alt bilgide mağaza adının altında görünür." />
                    <x-field name="meta_title" label="Ana sayfa SEO başlığı" :value="$settings['meta_title']" maxlength="160" :hint="'Boşsa: '.config('store.name')" />
                    <x-field name="meta_description" label="Ana sayfa SEO açıklaması" :value="$settings['meta_description']" maxlength="320" hint="Google sonuçlarında görünen açıklama (150–160 karakter ideal)." />
                </div>
            </x-card>
            <x-button>Kaydet</x-button>
        </form>

        <div class="space-y-6">
            <x-card title="Yayın durumu">
                <p class="flex items-center gap-2 text-sm">
                    @if ($open)<x-badge tone="success">Mağaza açık</x-badge> Ziyaretçiler alışveriş sayfalarını görüyor.
                    @else<x-badge tone="warning">Mağaza kapalı</x-badge> Ziyaretçiler "yakında" sayfasını görüyor.@endif
                </p>
                <form method="POST" action="{{ route('store_admin.settings.open') }}" class="mt-4"
                    data-confirm="{{ $open ? 'Mağaza ziyaretçilere kapatılsın mı?' : 'Mağaza ziyaretçilere açılsın mı? Ürünler Google ve ziyaretçiler tarafından görülebilir olacak.' }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="open" value="{{ $open ? 0 : 1 }}">
                    <x-button :variant="$open ? 'secondary' : 'primary'" size="sm">{{ $open ? 'Mağazayı kapat' : 'Mağazayı aç' }}</x-button>
                </form>
            </x-card>
            <x-card title="Önizleme" description="Kapalı mağazayı ziyaretçi gibi gezin. Bağlantı 15 dakika geçerlidir; açtığınız tarayıcıda 2 saat önizleme yapabilirsiniz.">
                <x-button variant="secondary" icon="eye" :href="$previewUrl" target="_blank" rel="noopener">Mağazayı önizle</x-button>
            </x-card>
        </div>
    </div>
</x-layouts.panel>
