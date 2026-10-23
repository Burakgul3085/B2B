<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Üst ve Alt Bilgi">
    <x-page-header title="Üst ve Alt Bilgi" subtitle="Her sayfanın en üstündeki şerit ve en altındaki bölüm. İletişim bilgileri Mağaza Ayarları'ndan, çalışma saatleri İletişim sayfasından, kategoriler katalogdan gelir.">
        <x-button :href="route('store.home').'#alt-bilgi'" variant="secondary" icon="eye" target="_blank">Mağazada gör</x-button>
    </x-page-header>

    <form method="POST" action="{{ route('store_admin.footer.update') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-card title="Üst şerit" description="Sayfanın en üstündeki lacivert şerit (masaüstü). Boş bırakırsanız maddeler ayarlardan üretilir: ücretsiz kargo sınırı, Eskişehir teslimatı, güvenli alışveriş. Sağda telefon ve WhatsApp görünür.">
                    <x-toggle name="topbar_enabled" label="Üst şeridi göster" :checked="$footer['topbar_enabled']" class="mb-4" />
                    <div class="grid gap-3 sm:grid-cols-3">
                        @for ($i = 0; $i < 3; $i++)
                            <x-field :name="'topbar_items['.$i.']'" :label="($i + 1).'. madde'" :value="$footer['topbar_items'][$i] ?? null" maxlength="50"
                                :placeholder="\App\Domain\Store\Content\StoreFooter::topbar(['topbar_items' => []])[$i][1] ?? ''" />
                        @endfor
                    </div>
                    <p class="mt-3 text-xs text-muted">Kampanya duyurusu (ör. “Okula dönüş fırsatları başladı!”) Mağaza Ayarları → Duyuru şeridi'nden yazılır ve şeridin ortasında görünür.</p>
                </x-card>

                <x-card title="Güven şeridi" description="Alt bilginin en üstündeki kısa maddeler (en fazla 4). Boş bırakılan madde görünmez.">
                    <x-toggle name="strip_enabled" label="Güven şeridini göster" :checked="$footer['strip_enabled']" class="mb-4" />
                    <div class="space-y-3">
                        @for ($i = 0; $i < \App\Domain\Store\Content\StoreFooter::MAX_STRIP; $i++)
                            <div class="grid gap-3 rounded-lg bg-surface p-4 sm:grid-cols-4">
                                <x-field :name="'strip['.$i.'][icon]'" label="Simge" :options="$icons" :value="$footer['strip'][$i]['icon'] ?? 'check-circle'" />
                                <x-field :name="'strip['.$i.'][title]'" label="Başlık" :value="$footer['strip'][$i]['title'] ?? null" maxlength="40" />
                                <x-field :name="'strip['.$i.'][text]'" label="Alt yazı" :value="$footer['strip'][$i]['text'] ?? null" maxlength="60" class="sm:col-span-2" />
                                @error('strip.'.$i)<p class="text-sm text-danger sm:col-span-4">{{ $message }}</p>@enderror
                            </div>
                        @endfor
                    </div>
                </x-card>

                <x-card title="Marka ve tanıtım">
                    <div class="space-y-5">
                        <x-field name="tagline" label="Logonun altındaki yazı" :value="$footer['tagline']" maxlength="40" hint="Büyük harfle, aralıklı yazılır (ör. KIRTASİYE & OFİS)." />
                        <x-field name="footer_about" label="Tanıtım metni" :value="$settings['footer_about']" textarea maxlength="600" hint="Logonun altında 1–2 cümle (ör. kuruluş yılı ve şehir)." />
                    </div>
                </x-card>

                <x-card title="Sütunlar">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field name="shop_title" label="Alışveriş sütunu başlığı" :value="$footer['shop_title']" required maxlength="30" />
                        <x-field name="category_count" label="Gösterilecek kategori sayısı" type="number" min="0" max="10" :value="$footer['category_count']" required
                            hint="Ana kategoriler, katalogdaki sırasıyla." />
                        <x-field name="info_title" label="Bilgi sütunu başlığı" :value="$footer['info_title']" required maxlength="30" hint="Yayındaki sayfalar ve yasal metinler." />
                        <x-field name="store_title" label="Mağaza sütunu başlığı" :value="$footer['store_title']" required maxlength="30" hint="Harita kartı ve çalışma saatleri." />
                    </div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Gösterilecekler">
                    <div class="space-y-4">
                        <x-toggle name="show_map" label="“Haritada gör” kartı" :checked="$footer['show_map']" hint="Adres girilmişse; yol tarifini açar." />
                        <x-toggle name="show_hours" label="Çalışma saatleri" :checked="$footer['show_hours']" hint="İletişim sayfasındaki saatler." />
                        <x-toggle name="show_b2b" label="“Bayi girişi” bağlantısı" :checked="$footer['show_b2b']" />
                        <x-toggle name="whatsapp_button" label="Sağ altta WhatsApp düğmesi" :checked="$footer['whatsapp_button']" hint="Mağaza Ayarları'nda WhatsApp numarası varsa." />
                        <x-field name="bottom_note" label="En alttaki not" :value="$footer['bottom_note']" maxlength="120" placeholder="ör. Fiyatlara KDV dahildir." />
                        <x-button class="w-full justify-center">Kaydet</x-button>
                    </div>
                </x-card>
                <x-card title="Başka yerden gelenler">
                    <ul class="space-y-2 text-sm text-muted">
                        <li>Telefon, e-posta, adres, sosyal medya: <a href="{{ route('store_admin.settings.edit') }}" class="text-primary hover:underline">Mağaza Ayarları</a></li>
                        <li>Ödeme logo bandı: <a href="{{ route('store_admin.settings.edit') }}" class="text-primary hover:underline">Mağaza Ayarları</a></li>
                        <li>Çalışma saatleri: <a href="{{ route('store_admin.site-pages.contact') }}" class="text-primary hover:underline">Vitrin → İletişim</a></li>
                        <li>Sayfa bağlantıları: yayındaki Hakkımızda ve İletişim</li>
                    </ul>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.panel>
