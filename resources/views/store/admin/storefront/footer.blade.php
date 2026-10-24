<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Üst ve Alt Bilgi">
    <x-page-header title="Üst ve Alt Bilgi" subtitle="Her sayfanın en üstündeki şerit ve en altındaki bölüm. İletişim bilgileri Mağaza Ayarları'ndan, çalışma saatleri İletişim sayfasından, kategoriler katalogdan gelir.">
        <x-button :href="route('store.home').'#alt-bilgi'" variant="secondary" icon="eye" target="_blank">Mağazada gör</x-button>
    </x-page-header>

    <form method="POST" action="{{ route('store_admin.footer.update') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-card title="Kayan şerit" description="Sayfanın en üstünde sağdan sola sürekli kayan renkli bant. Her madde: simge, metin ve isteğe bağlı vurgulu kutu (ör. “₺750 üzeri alışverişlerde” + “KARGO BEDAVA”). En fazla 12 madde. Form, ayarlarınızdan hazırlanmış önerilerle dolu gelir; düzenleyip kaydedin. Kuruluş yılı (ör. “1998’den beri” + “28 YIL GÜVENİ”) gibi size özel bilgileri boş satırlara ekleyebilirsiniz.">
                    <div class="mb-4 flex flex-wrap items-end gap-4">
                        <x-toggle name="marquee_enabled" label="Kayan şeridi göster" :checked="$footer['marquee_enabled']" />
                        <x-field name="marquee_color" label="Renk" :options="\App\Domain\Store\Content\StoreFooter::MARQUEE_COLORS" :value="$footer['marquee_color']" class="w-40" />
                        <x-field name="marquee_speed" label="Hız" :options="array_map(fn ($s) => $s[0], \App\Domain\Store\Content\StoreFooter::MARQUEE_SPEEDS)" :value="$footer['marquee_speed']" class="w-40" />
                    </div>
                    <div class="space-y-2">
                        @for ($i = 0; $i < \App\Domain\Store\Content\StoreFooter::MAX_MARQUEE; $i++)
                            @php $row = ($footer['marquee_items'] ?: \App\Domain\Store\Content\StoreFooter::marqueeSuggestions())[$i] ?? null; @endphp
                            <div class="grid gap-3 rounded-lg bg-surface p-3 sm:grid-cols-[10rem_1fr_12rem]">
                                <x-field :name="'marquee_items['.$i.'][icon]'" label="Simge" :options="$icons" :value="$row['icon'] ?? 'check-circle'" />
                                <x-field :name="'marquee_items['.$i.'][text]'" :label="($i + 1).'. metin'" :value="$row['text'] ?? null" maxlength="60" placeholder="ör. 1989'dan beri" />
                                <x-field :name="'marquee_items['.$i.'][badge]'" label="Vurgulu kutu" :value="$row['badge'] ?? null" maxlength="30" placeholder="ör. 37 yıl güveni" />
                            </div>
                        @endfor
                    </div>
                    <p class="mt-3 text-xs text-muted">Vurgulu kutu büyük harfle yazılır. Fare şeridin üzerine gelince kayma durur; hareket azaltmayı seçen ziyaretçilerde şerit sabit durur.</p>
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

                <x-card title="Bağlantı sütunları" description="Alt bilgideki sütunlar (en fazla 4) ve bağlantıları. Listeden bir sayfa seçin; isterseniz görünen adını değiştirin. Yayında olmayan sayfanın bağlantısı kendiliğinden gizlenir. “Özel adres” ile başka bir siteye de bağlantı verebilirsiniz.">
                    @php
                        $linkOptions = \App\Domain\Store\Content\FooterLinks::options();
                        $columns = old('columns') ? array_values(old('columns')) : $footer['columns'];
                    @endphp
                    <div class="grid gap-4 lg:grid-cols-2">
                        @for ($c = 0; $c < \App\Domain\Store\Content\StoreFooter::MAX_COLUMNS; $c++)
                            @php $column = $columns[$c] ?? ['title' => '', 'links' => []]; @endphp
                            <div class="rounded-xl bg-surface p-4">
                                <x-field :name="'columns['.$c.'][title]'" :label="($c + 1).'. sütun başlığı'" :value="$column['title']" maxlength="30" placeholder="ör. Kurumsal" />
                                <div class="mt-3 space-y-2" x-data="{ shown: {{ max(3, count($column['links']) + 1) }} }">
                                    @for ($l = 0; $l < \App\Domain\Store\Content\StoreFooter::MAX_LINKS; $l++)
                                        @php
                                            $row = $column['links'][$l] ?? ['key' => '', 'label' => null];
                                            $isUrl = \App\Domain\Store\Content\FooterLinks::isUrl((string) ($row['key'] ?? '')) || ($row['key'] ?? '') === 'url';
                                        @endphp
                                        <div class="grid gap-2 sm:grid-cols-2" x-show="shown > {{ $l }}" @if ($l >= max(3, count($column['links']) + 1)) x-cloak @endif x-data="{ custom: {{ $isUrl ? 'true' : 'false' }} }">
                                            <select name="columns[{{ $c }}][links][{{ $l }}][key]" class="field-input text-sm" aria-label="{{ $c + 1 }}. sütun, {{ $l + 1 }}. bağlantı" @change="custom = $event.target.value === 'url'">
                                                <option value="">— Bağlantı yok —</option>
                                                @foreach ($linkOptions as $group => $options)
                                                    <optgroup label="{{ $group }}">
                                                        @foreach ($options as $key => $label)
                                                            <option value="{{ $key }}" @selected(($row['key'] ?? '') === $key)>{{ $label }}</option>
                                                        @endforeach
                                                    </optgroup>
                                                @endforeach
                                                <option value="url" @selected($isUrl)>Özel adres…</option>
                                            </select>
                                            <input name="columns[{{ $c }}][links][{{ $l }}][label]" value="{{ $row['label'] ?? '' }}" maxlength="40" class="field-input text-sm" placeholder="Görünen ad (isteğe bağlı)" aria-label="Görünen ad">
                                            <input name="columns[{{ $c }}][links][{{ $l }}][url]" value="{{ $isUrl && ($row['key'] ?? '') !== 'url' ? $row['key'] : ($row['url'] ?? '') }}" maxlength="255" x-show="custom" @if (! $isUrl) x-cloak @endif
                                                class="field-input text-sm sm:col-span-2" placeholder="https://…" aria-label="Özel adres">
                                            @error('columns.'.$c.'.links.'.$l.'.url')<p class="text-xs text-danger sm:col-span-2">{{ $message }}</p>@enderror
                                            @error('columns.'.$c.'.links.'.$l.'.label')<p class="text-xs text-danger sm:col-span-2">{{ $message }}</p>@enderror
                                        </div>
                                    @endfor
                                    <button type="button" x-show="shown < {{ \App\Domain\Store\Content\StoreFooter::MAX_LINKS }}" @click="shown++" class="text-sm font-medium text-primary hover:underline">+ Bağlantı ekle</button>
                                </div>
                            </div>
                        @endfor
                    </div>
                    <div class="mt-4 max-w-sm">
                        <x-field name="store_title" label="Mağaza sütunu başlığı" :value="$footer['store_title']" maxlength="30" hint="Harita ve çalışma saatlerinin başlığı." />
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
                        <x-field name="bottom_note" label="En alttaki not" :value="$footer['bottom_note']" maxlength="120" placeholder="İsteğe bağlı, ör. EST. 1998 · Eskişehir" />
                        <x-field name="manager_name" label="Sayfa yöneticisi" :value="$footer['manager_name']" maxlength="60" hint="En altta “Sayfa yöneticisi” olarak yazılır. Boşsa gösterilmez." />
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
