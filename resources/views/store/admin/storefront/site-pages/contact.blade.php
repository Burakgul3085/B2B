@php
    use App\Domain\Store\Content\SitePages;

    $c = $page->content;
    $info = [
        'Telefon' => $settings['phone'], 'İkinci telefon' => $settings['phone2'], 'WhatsApp' => $settings['whatsapp'], 'E-posta' => $settings['email'], 'Adres' => $settings['address'],
        'Instagram' => $settings['instagram'], 'Facebook' => $settings['facebook'], 'Ticari unvan' => $settings['company_title'],
    ];
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="İletişim">
    <x-page-header title="İletişim" subtitle="Mağazanın İletişim sayfası: iletişim bilgileri, çalışma saatleri, harita ve iletişim formu.">
        <x-badge :tone="$page->is_published ? 'success' : 'warning'" class="px-3! py-1! text-sm!">{{ $page->is_published ? 'Yayında' : 'Yayında değil' }}</x-badge>
        @if ($page->exists)
            <x-button :href="route('store_admin.site-pages.preview', 'contact')" variant="secondary" icon="eye" target="_blank">Önizle</x-button>
        @endif
    </x-page-header>

    <form method="POST" action="{{ route('store_admin.site-pages.contact.update') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-card title="Giriş metni">
                    <x-field name="intro" label="Sayfanın başındaki kısa metin" :value="$c['intro']" textarea maxlength="500" />
                </x-card>

                <x-card title="Çalışma saatleri" description="Sayfada saatler ve o anki duruma göre “Şu an açık / kapalı” rozeti gösterilir (İstanbul saati).">
                    <div class="divide-y divide-line">
                        @foreach ($days as $day => $label)
                            @php $row = $c['hours'][$day]; @endphp
                            <div class="flex flex-wrap items-center gap-3 py-2.5">
                                <span class="w-24 text-sm font-medium">{{ $label }}</span>
                                <input type="time" name="hours[{{ $day }}][open]" value="{{ old("hours.{$day}.open", $row['open']) }}" class="field-input w-32" aria-label="{{ $label }} açılış">
                                <span class="text-muted">–</span>
                                <input type="time" name="hours[{{ $day }}][close]" value="{{ old("hours.{$day}.close", $row['close']) }}" class="field-input w-32" aria-label="{{ $label }} kapanış">
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="hidden" name="hours[{{ $day }}][closed]" value="0">
                                    <input type="checkbox" name="hours[{{ $day }}][closed]" value="1" class="size-4 rounded text-primary" @checked(old("hours.{$day}.closed", $row['closed']))> Kapalı
                                </label>
                                @error('hours.'.$day)<p class="w-full text-sm text-danger">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                    <x-field name="hours_note" label="Saatlerle ilgili not" :value="$c['hours_note']" maxlength="200" class="mt-4" placeholder="ör. Resmi tatillerde kapalıyız." />
                </x-card>

                <x-card title="Harita" description="Google Haritalar'da dükkanı açın → Paylaş → Bağlantıyı kopyala, buraya yapıştırın. Konum bulunamazsa Paylaş → Harita yerleştir → HTML'yi kopyala kodunu yapıştırın.">
                    <x-field name="map_input" label="Google Haritalar bağlantısı veya yerleştirme kodu" :value="$c['map_input']" textarea maxlength="3000" placeholder="https://maps.app.goo.gl/…" />
                    @if ($c['map_embed'])
                        <iframe src="{{ $c['map_embed'] }}" class="mt-4 h-72 w-full rounded-lg border border-line" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Harita önizlemesi"></iframe>
                        @if (SitePages::directionsUrl($c))
                            <a href="{{ SitePages::directionsUrl($c) }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"><x-icon name="navigation" class="size-4" /> Yol tarifini dene</a>
                        @endif
                    @else
                        <p class="mt-3 text-sm text-muted">Henüz harita yok. Harita olmadan da sayfada adres ve "Yol tarifi al" (adrese göre) gösterilir.</p>
                    @endif
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Yayın">
                    <div class="space-y-4">
                        <x-toggle name="is_published" label="Yayında" :checked="$page->is_published" hint="Açıkken sayfa mağazada ve alt bilgide görünür." />
                        <x-toggle name="form_enabled" label="İletişim formu" :checked="$c['form_enabled']" hint="Mesajlar Satış → Mesajlar'a düşer." />
                        <x-button class="w-full justify-center">Kaydet</x-button>
                    </div>
                </x-card>

                <x-card title="İletişim bilgileri" description="Sayfada bu bilgiler gösterilir. Değiştirmek için Mağaza Ayarları'nı kullanın.">
                    <dl class="space-y-2 text-sm">
                        @foreach ($info as $label => $value)
                            <div class="flex justify-between gap-3"><dt class="text-muted">{{ $label }}</dt><dd class="text-right {{ $value ? '' : 'text-amber-700' }}">{{ $value ?: 'girilmedi' }}</dd></div>
                        @endforeach
                    </dl>
                    <a href="{{ route('store_admin.settings.edit') }}" class="mt-4 inline-block text-sm font-medium text-primary hover:underline">Mağaza Ayarları'na git →</a>
                </x-card>

                <x-card title="Arama motorları">
                    <div class="space-y-4">
                        <x-field name="seo_title" label="SEO başlığı" :value="$page->seo_title" maxlength="160" />
                        <x-field name="seo_description" label="SEO açıklaması" :value="$page->seo_description" textarea maxlength="320" />
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.panel>
