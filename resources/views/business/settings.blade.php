<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="İşletme Ayarları">
    <x-page-header title="İşletme Ayarları" subtitle="Bayilerinizin gördüğü iletişim bilgileri ve sipariş davranışları." />

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <x-card title="Logo" description="Başvuru sayfasında, bayi panelinde ve e-postalarda görünür. JPG, PNG veya WEBP; en fazla 2 MB.">
            <div class="flex items-center gap-4">
                <div class="flex h-16 w-32 items-center justify-center rounded-lg border border-line bg-slate-50">
                    @if ($business->logo_path)
                        <img src="{{ route('public.logo', $business->slug) }}?v={{ $business->updated_at->timestamp }}" alt="Logo" class="max-h-14 max-w-28">
                    @else
                        <span class="text-xs text-muted">Logo yok</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('business.settings.logo') }}" enctype="multipart/form-data" class="flex flex-1 flex-wrap items-center gap-2">
                    @csrf
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" required class="min-w-0 flex-1 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium" aria-label="Logo dosyası">
                    <x-button variant="secondary" size="sm" icon="upload">Yükle</x-button>
                </form>
            </div>
            @error('logo')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
            @if ($business->logo_path)
                <form method="POST" action="{{ route('business.settings.logo') }}" class="mt-3">@csrf<input type="hidden" name="remove" value="1"><button class="text-sm text-danger hover:underline">Logoyu kaldır</button></form>
            @endif
        </x-card>

        <x-card title="Bayilik başvuru sayfanız" description="Bu bağlantıyı web sitenizde veya müşterilerinizle paylaşın.">
            <div class="flex gap-2" x-data="copyLink">
                <input readonly value="{{ route('public.application', $business->slug) }}" class="field-input font-mono text-xs" x-ref="url" aria-label="Başvuru adresi">
                <x-button type="button" variant="secondary" @click="copy()"><span x-text="copied ? 'Kopyalandı' : 'Kopyala'">Kopyala</span></x-button>
            </div>
            <a href="{{ route('public.application', $business->slug) }}" target="_blank" rel="noopener" class="mt-3 inline-block text-sm text-primary hover:underline">Sayfayı aç</a>
        </x-card>
    </div>

    <form method="POST" action="{{ route('business.settings.update') }}" class="space-y-6">
        @csrf @method('PUT')

        <x-card title="İletişim bilgileri" description="Ticari unvan ve vergi bilgileri platform yöneticisi tarafından güncellenir.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="display_name" label="Görünen ad" :value="$business->display_name" required />
                <x-field name="email" label="İletişim e-postası" type="email" :value="$business->email" />
                <x-field name="phone" label="Telefon" type="tel" :value="$business->phone" />
                <x-field name="accent_color" label="Vurgu rengi" type="color" :value="$business->accent_color ?? '#1e3a8a'" hint="Bayi panelinin üst çizgisinde ve e-posta başlığında kullanılır." class="[&_input]:h-10 [&_input]:w-24 [&_input]:p-1" />
                <x-field name="city" label="İl" :value="$business->city" />
                <x-field name="district" label="İlçe" :value="$business->district" />
                <x-field name="address" label="Adres" :value="$business->address" textarea class="sm:col-span-2" />
            </div>
        </x-card>

        <x-card title="Sipariş ve bayi paneli">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="stock_display" label="Bayiye stok gösterimi" :value="$settings->stock_display"
                    :options="['level' => 'Stokta / Sınırlı / Yok', 'exact' => 'Stok adedi']" />
                <x-field name="price_display" label="Bayiye fiyat gösterimi" :value="$settings->price_display"
                    :options="['excl_vat' => 'KDV hariç', 'incl_vat' => 'KDV dahil']" />
                <x-field name="ledger_post_on" label="Cari borç ne zaman oluşsun?" :value="$settings->ledger_post_on"
                    :options="['approval' => 'Sipariş onaylandığında', 'completion' => 'Sipariş tamamlandığında']"
                    hint="Her sipariş için cari borç yalnızca bir kez oluşur." />
                <x-field name="default_payment_term_days" label="Varsayılan vade (gün)" type="number" min="0" max="365" :value="$settings->default_payment_term_days" required />
            </div>
        </x-card>

        <x-card title="Yeni ürünler" description="Bayi panelindeki “Yeni Ürünler” sayfası ve ürünlerdeki “Yeni” rozeti.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="new_product_days" label="Yeni ürün süresi" :value="$settings->new_product_days ?? 30"
                    :options="collect(\App\Domain\Catalog\NewProducts::DAY_OPTIONS)->mapWithKeys(fn ($d) => [$d => 'Son '.$d.' günde eklenenler'])->all()"
                    hint="Bu süre içinde eklenen ürünler bayilere yeni olarak gösterilir." />
                <x-field name="new_products_since" label="Bu tarihten önce eklenenleri yeni sayma" type="date" :value="$settings->new_products_since?->toDateString()"
                    :max="now()->toDateString()" hint="İsteğe bağlı. İlk toplu ürün yüklemesinden sonraki günü seçin; o yüklemedeki ürünler yeni görünmez." />
            </div>
        </x-card>

        <div class="flex justify-end"><x-button>Ayarları kaydet</x-button></div>
    </form>
</x-layouts.panel>
