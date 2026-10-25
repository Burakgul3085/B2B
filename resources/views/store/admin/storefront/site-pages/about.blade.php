@php
    use App\Domain\Store\Content\SitePages;

    $c = $page->content;
    $image = fn (string $path) => route('store_admin.site-pages.image', basename($path));
    $on = fn (string $section) => SitePages::sectionOn($c, $section);
    $toggle = fn (string $section) => ['name' => 'sections['.$section.']', 'label' => 'Bu bölüm sayfada görünsün', 'checked' => $on($section)];
    $fileInput = 'field-input file:mr-3 file:rounded-md file:border-0 file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium';
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Hakkımızda">
    <x-page-header title="Hakkımızda" subtitle="Sayfanın her bölümü buradan düzenlenir. Boş bırakılan ya da kapatılan bölüm sayfada görünmez; rakam, yıl gibi bilgileri gerçek değerlerle doldurun.">
        <x-badge :tone="$page->is_published ? 'success' : 'warning'" class="px-3! py-1! text-sm!">{{ $page->is_published ? 'Yayında' : 'Yayında değil' }}</x-badge>
        @if ($page->exists)
            <x-button :href="route('store_admin.site-pages.preview', 'about')" variant="secondary" icon="eye" target="_blank">Önizle</x-button>
        @endif
    </x-page-header>

    <form method="POST" action="{{ route('store_admin.site-pages.about.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf @method('PUT')
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                {{-- 1. Kapak --}}
                <x-card title="1 · Kapak" description="Sayfanın en üstü: büyük başlık, slogan ve geniş bir mağaza fotoğrafı. Fotoğraf yoksa yapboz desenli lacivert zemin kullanılır.">
                    <div class="space-y-5">
                        <x-field name="headline" label="Başlık" :value="$c['headline']" required maxlength="120" placeholder="ör. 1998'den beri Eskişehir'in kırtasiyesi" />
                        <x-field name="tagline" label="Slogan" :value="$c['tagline']" maxlength="240" textarea hint="Başlığın altındaki 1–2 cümle." />
                        <div>
                            <p class="mb-1.5 text-sm font-medium text-ink">Kapak fotoğrafı</p>
                            @if ($c['cover'])
                                <img src="{{ $image($c['cover']) }}" alt="" class="mb-3 aspect-[3/1] w-full rounded-lg bg-surface object-contain">
                                <x-toggle name="remove_cover" label="Kapak fotoğrafını kaldır" class="mb-3" />
                            @endif
                            <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="{{ $fileInput }}">
                            @error('cover')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@else<p class="mt-1.5 text-xs text-muted">Geniş, yatay fotoğraf (ör. mağazanın içi veya vitrini). JPG, PNG veya WebP, en fazla 8 MB.</p>@enderror
                        </div>
                    </div>
                </x-card>

                {{-- 2. Rakamlar --}}
                <x-card title="2 · Rakamlarla biz" description="Kapağın altındaki beyaz şerit; en fazla {{ SitePages::MAX_STATS }}. Sayılar sayfa açılınca sayarak gelir (ör. 5.000+, %99, 1998).">
                    <x-toggle :name="$toggle('stats')['name']" :label="$toggle('stats')['label']" :checked="$toggle('stats')['checked']" class="mb-4" />
                    <div class="space-y-3">
                        @for ($i = 0; $i < SitePages::MAX_STATS; $i++)
                            <div class="grid gap-3 rounded-lg bg-surface p-3 sm:grid-cols-[9rem_8rem_1fr]">
                                <x-field :name="'stats['.$i.'][icon]'" label="Simge" :options="$icons" :value="$c['stats'][$i]['icon'] ?? 'star'" />
                                <x-field :name="'stats['.$i.'][value]'" label="Değer" :value="$c['stats'][$i]['value'] ?? null" maxlength="20" placeholder="ör. 5.000+" />
                                <x-field :name="'stats['.$i.'][label]'" label="Açıklama" :value="$c['stats'][$i]['label'] ?? null" maxlength="60" placeholder="ör. Ürün çeşidi" />
                                @error('stats.'.$i)<p class="text-sm text-danger sm:col-span-3">{{ $message }}</p>@enderror
                            </div>
                        @endfor
                    </div>
                </x-card>

                {{-- 3. Hikâye --}}
                <x-card title="3 · Hikâyemiz" description="Solda metin, sağda iki fotoğraf ve isteğe bağlı rozet (ör. “28” + “yıldır hizmetinizde”).">
                    <x-toggle :name="$toggle('story')['name']" :label="$toggle('story')['label']" :checked="$toggle('story')['checked']" class="mb-4" />
                    <div class="space-y-5">
                        <x-field name="story_title" label="Bölüm başlığı" :value="$c['story_title']" maxlength="80" />
                        <div x-data="markdownToolbar()">
                            <p class="mb-1.5 text-sm font-medium">Metin</p>
                            <div class="mb-2 flex flex-wrap gap-1.5 text-sm">
                                <button type="button" class="rounded-md border border-line px-2.5 py-1 font-bold hover:bg-slate-50" @click="bold">K</button>
                                <button type="button" class="rounded-md border border-line px-2.5 py-1 hover:bg-slate-50" @click="heading">Ara başlık</button>
                                <button type="button" class="rounded-md border border-line px-2.5 py-1 hover:bg-slate-50" @click="bullet">• Madde</button>
                                <button type="button" class="rounded-md border border-line px-2.5 py-1 hover:bg-slate-50" @click="link">Bağlantı</button>
                            </div>
                            <textarea name="story" rows="10" x-ref="text" class="field-input text-sm leading-relaxed" maxlength="20000">{{ old('story', $c['story']) }}</textarea>
                            @error('story')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@else<p class="mt-1.5 text-xs text-muted">Paragraflar arasında bir boş satır bırakın.</p>@enderror
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach ([0 => 'Büyük fotoğraf', 1 => 'Küçük fotoğraf (önde)'] as $slot => $label)
                                <div class="rounded-lg bg-surface p-3">
                                    <p class="mb-1.5 text-sm font-medium">{{ $label }}</p>
                                    @if ($c['story_images'][$slot] ?? null)
                                        <img src="{{ $image($c['story_images'][$slot]) }}" alt="" class="mb-2 aspect-[4/3] w-full rounded-lg bg-surface object-contain">
                                        <x-toggle :name="'remove_story_image['.$slot.']'" label="Kaldır" class="mb-2" />
                                    @endif
                                    <input type="file" name="story_images[{{ $slot }}]" accept="image/jpeg,image/png,image/webp" class="{{ $fileInput }}">
                                </div>
                            @endforeach
                        </div>
                        <div class="grid gap-4 sm:grid-cols-[8rem_1fr]">
                            <x-field name="badge_value" label="Rozet değeri" :value="$c['badge_value']" maxlength="12" placeholder="ör. 28" />
                            <x-field name="badge_label" label="Rozet yazısı" :value="$c['badge_label']" maxlength="40" placeholder="ör. yıldır hizmetinizde" />
                        </div>
                    </div>
                </x-card>

                {{-- 4. Misyon, vizyon, değerler --}}
                <x-card title="4 · Misyon, vizyon, değerler" description="Yan yana kartlar. Metni boş bırakılan kart görünmez.">
                    <x-toggle :name="$toggle('pillars')['name']" :label="$toggle('pillars')['label']" :checked="$toggle('pillars')['checked']" class="mb-4" />
                    <div class="space-y-4">
                        @foreach (SitePages::PILLARS as $key => [$default])
                            <div class="grid gap-3 rounded-lg bg-surface p-4">
                                <x-field :name="'pillars['.$key.'][title]'" label="Başlık" :value="$c['pillars'][$key]['title'] ?? $default" maxlength="60" />
                                <x-field :name="'pillars['.$key.'][text]'" label="Metin" textarea :value="$c['pillars'][$key]['text'] ?? null" maxlength="600" />
                            </div>
                        @endforeach
                    </div>
                </x-card>

                {{-- 5. Neden biz --}}
                <x-card title="5 · Neden biz" description="En fazla {{ SitePages::MAX_FEATURES }} kart; logonun renklerinde sırayla renklenir.">
                    <x-toggle :name="$toggle('features')['name']" :label="$toggle('features')['label']" :checked="$toggle('features')['checked']" class="mb-4" />
                    <x-field name="features_title" label="Bölüm başlığı" :value="$c['features_title']" maxlength="80" :placeholder="'Neden '.config('store.name').'?'" class="mb-4" />
                    <div class="space-y-3">
                        @for ($i = 0; $i < SitePages::MAX_FEATURES; $i++)
                            <div class="grid gap-3 rounded-lg bg-surface p-3 sm:grid-cols-[9rem_1fr]">
                                <x-field :name="'features['.$i.'][icon]'" label="Simge" :options="$icons" :value="$c['features'][$i]['icon'] ?? 'check-circle'" />
                                <x-field :name="'features['.$i.'][title]'" label="Başlık" :value="$c['features'][$i]['title'] ?? null" maxlength="60" />
                                <x-field :name="'features['.$i.'][text]'" label="Kısa metin" :value="$c['features'][$i]['text'] ?? null" maxlength="200" class="sm:col-span-2" />
                                @error('features.'.$i)<p class="text-sm text-danger sm:col-span-2">{{ $message }}</p>@enderror
                            </div>
                        @endfor
                    </div>
                </x-card>

                {{-- 6. Yolculuğumuz --}}
                <x-card title="6 · Yolculuğumuz" description="Yıllara göre kilometre taşları, en fazla {{ SitePages::MAX_TIMELINE }}. Yazdığınız sırayla gösterilir.">
                    <x-toggle :name="$toggle('timeline')['name']" :label="$toggle('timeline')['label']" :checked="$toggle('timeline')['checked']" class="mb-4" />
                    <x-field name="timeline_title" label="Bölüm başlığı" :value="$c['timeline_title']" maxlength="80" class="mb-4" />
                    <div class="space-y-3" x-data="{ shown: {{ max(3, count($c['timeline']) + 1) }} }">
                        @for ($i = 0; $i < SitePages::MAX_TIMELINE; $i++)
                            <div class="grid gap-3 rounded-lg bg-surface p-3 sm:grid-cols-[7rem_1fr]" x-show="shown > {{ $i }}" @if ($i >= max(3, count($c['timeline']) + 1)) x-cloak @endif>
                                <x-field :name="'timeline['.$i.'][year]'" label="Yıl" :value="$c['timeline'][$i]['year'] ?? null" maxlength="12" placeholder="ör. 1998" />
                                <x-field :name="'timeline['.$i.'][title]'" label="Başlık" :value="$c['timeline'][$i]['title'] ?? null" maxlength="80" placeholder="ör. İlk dükkânımızı açtık" />
                                <x-field :name="'timeline['.$i.'][text]'" label="Kısa açıklama" :value="$c['timeline'][$i]['text'] ?? null" maxlength="300" class="sm:col-span-2" />
                                @error('timeline.'.$i)<p class="text-sm text-danger sm:col-span-2">{{ $message }}</p>@enderror
                            </div>
                        @endfor
                        <button type="button" x-show="shown < {{ SitePages::MAX_TIMELINE }}" @click="shown++" class="text-sm font-medium text-primary hover:underline">+ Adım ekle</button>
                    </div>
                </x-card>

                {{-- 7. Galeri --}}
                <x-card title="7 · Fotoğraf galerisi" description="En fazla {{ SitePages::MAX_GALLERY }} fotoğraf. Sıra numarası küçük olan önce gelir; açıklama fotoğrafın üzerinde ve büyük görünümde yazar.">
                    <x-toggle :name="$toggle('gallery')['name']" :label="$toggle('gallery')['label']" :checked="$toggle('gallery')['checked']" class="mb-4" />
                    <x-field name="gallery_title" label="Bölüm başlığı" :value="$c['gallery_title']" maxlength="80" class="mb-4" />
                    @if ($c['gallery'])
                        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($c['gallery'] as $n => $path)
                                @php $file = basename($path); @endphp
                                <div class="rounded-lg bg-surface p-2 text-sm">
                                    <img src="{{ $image($path) }}" alt="" class="aspect-[4/3] w-full rounded-md bg-surface object-contain">
                                    <input name="gallery_captions[{{ $file }}]" value="{{ $c['gallery_captions'][$file] ?? '' }}" maxlength="120" placeholder="Açıklama (isteğe bağlı)" class="field-input mt-2 py-1.5 text-xs" aria-label="Fotoğraf açıklaması">
                                    <div class="mt-2 flex items-center justify-between gap-2">
                                        <label class="flex items-center gap-1.5 text-xs text-muted">Sıra <input name="gallery_order[{{ $file }}]" value="{{ $n + 1 }}" inputmode="numeric" class="field-input w-14 px-2 py-1 text-xs" aria-label="Sıra"></label>
                                        <label class="flex items-center gap-1.5 text-xs text-danger"><input type="checkbox" name="remove_gallery[]" value="{{ $file }}" class="size-4 rounded text-danger"> Kaldır</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <input type="file" name="gallery[]" multiple accept="image/jpeg,image/png,image/webp" class="{{ $fileInput }}">
                    @error('gallery')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                    @error('gallery.*')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@else<p class="mt-1.5 text-xs text-muted">Birden çok fotoğraf seçebilirsiniz; yeni fotoğraflar sona eklenir.</p>@enderror
                </x-card>

                {{-- 8. Ekibimiz --}}
                <x-card title="8 · Ekibimiz" description="İsteğe bağlı, en fazla {{ SitePages::MAX_TEAM }} kişi. Adı silinen kişi (ve fotoğrafı) kaldırılır; fotoğrafı olmayan kişide baş harfi gösterilir.">
                    <x-toggle :name="$toggle('team')['name']" :label="$toggle('team')['label']" :checked="$toggle('team')['checked']" class="mb-4" />
                    <x-field name="team_title" label="Bölüm başlığı" :value="$c['team_title']" maxlength="80" class="mb-4" />
                    <div class="grid gap-3 sm:grid-cols-2" x-data="{ shown: {{ max(2, count($c['team']) + 1) }} }">
                        @for ($i = 0; $i < SitePages::MAX_TEAM; $i++)
                            @php $member = $c['team'][$i] ?? null; @endphp
                            <div class="space-y-3 rounded-lg bg-surface p-3" x-show="shown > {{ $i }}" @if ($i >= max(2, count($c['team']) + 1)) x-cloak @endif>
                                <div class="flex items-center gap-3">
                                    @if ($member['photo'] ?? null)
                                        <img src="{{ $image($member['photo']) }}" alt="" class="size-14 shrink-0 rounded-xl bg-surface object-contain">
                                    @else
                                        <span class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-white text-muted"><x-icon name="user" class="size-6" /></span>
                                    @endif
                                    <input type="file" name="team_photo[{{ $i }}]" accept="image/jpeg,image/png,image/webp" class="{{ $fileInput }} min-w-0 text-xs" aria-label="Fotoğraf">
                                </div>
                                @if ($member['photo'] ?? null)<x-toggle :name="'remove_team_photo['.$i.']'" label="Fotoğrafı kaldır" />@endif
                                <x-field :name="'team['.$i.'][name]'" label="Ad soyad" :value="$member['name'] ?? null" maxlength="60" />
                                <x-field :name="'team['.$i.'][role]'" label="Görev" :value="$member['role'] ?? null" maxlength="60" placeholder="ör. Mağaza sorumlusu" />
                            </div>
                        @endfor
                        <button type="button" x-show="shown < {{ SitePages::MAX_TEAM }}" @click="shown++" class="text-left text-sm font-medium text-primary hover:underline sm:col-span-2">+ Kişi ekle</button>
                    </div>
                </x-card>

                {{-- 9. Kapanış bandı --}}
                <x-card title="9 · Kapanış bandı" description="Sayfanın sonundaki lacivert bant.">
                    <x-toggle name="show_cta" label="Bu bölüm sayfada görünsün" :checked="$c['show_cta']" class="mb-4" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field name="cta_title" label="Başlık" :value="$c['cta_title']" maxlength="120" class="sm:col-span-2" />
                        <x-field name="cta_text" label="Kısa metin" :value="$c['cta_text']" maxlength="240" class="sm:col-span-2" />
                        <x-field name="cta_label" label="Düğme yazısı" :value="$c['cta_label']" maxlength="40" />
                        <x-field name="cta_url" label="Düğme adresi" :value="$c['cta_url']" maxlength="255" placeholder="Boşsa tüm ürünler; ör. /iletisim" />
                    </div>
                </x-card>
            </div>

            <div class="space-y-6 xl:sticky xl:top-6 xl:self-start">
                <x-card title="Yayın">
                    <div class="space-y-4">
                        <x-toggle name="is_published" label="Yayında" :checked="$page->is_published" hint="Açıkken sayfa mağazada ve alt bilgide görünür." />
                        <x-button class="w-full justify-center">Kaydet</x-button>
                        @if ($page->exists)<x-button :href="route('store_admin.site-pages.preview', 'about')" variant="secondary" icon="eye" target="_blank" class="w-full justify-center">Önizle</x-button>@endif
                        @unless ($page->exists)<p class="text-xs text-muted">Örnek metinle başlar; kaydedince önizleyebilirsiniz.</p>@endunless
                    </div>
                </x-card>
                <x-card title="Bölümler">
                    <ol class="space-y-1.5 text-sm">
                        <li class="flex justify-between"><span>1 · Kapak</span><x-badge tone="success">Açık</x-badge></li>
                        @foreach (SitePages::ABOUT_SECTIONS as $key => $label)
                            <li class="flex justify-between gap-2"><span>{{ $loop->iteration + 1 }} · {{ $label }}</span>@if ($on($key))<x-badge tone="success">Açık</x-badge>@else<x-badge>Kapalı</x-badge>@endif</li>
                        @endforeach
                        <li class="flex justify-between"><span>9 · Kapanış bandı</span>@if ($c['show_cta'])<x-badge tone="success">Açık</x-badge>@else<x-badge>Kapalı</x-badge>@endif</li>
                    </ol>
                </x-card>
                <x-card title="Arama motorları" description="Google sonuçlarında görünen başlık ve açıklama. Boşsa sayfa başlığı ve slogan kullanılır.">
                    <div class="space-y-4">
                        <x-field name="seo_title" label="SEO başlığı" :value="$page->seo_title" maxlength="160" />
                        <x-field name="seo_description" label="SEO açıklaması" :value="$page->seo_description" textarea maxlength="320" />
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.panel>
