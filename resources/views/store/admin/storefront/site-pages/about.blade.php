@php
    $c = $page->content;
    $image = fn (string $path) => route('store_admin.site-pages.image', basename($path));
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Hakkımızda">
    <x-page-header title="Hakkımızda" subtitle="Mağazanın Hakkımızda sayfası. Boş bırakılan bölümler sayfada görünmez.">
        <x-badge :tone="$page->is_published ? 'success' : 'warning'" class="px-3! py-1! text-sm!">{{ $page->is_published ? 'Yayında' : 'Yayında değil' }}</x-badge>
        @if ($page->exists)
            <x-button :href="route('store_admin.site-pages.preview', 'about')" variant="secondary" icon="eye" target="_blank">Önizle</x-button>
        @endif
    </x-page-header>

    <form method="POST" action="{{ route('store_admin.site-pages.about.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf @method('PUT')
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-card title="Üst bölüm">
                    <div class="space-y-5">
                        <x-field name="headline" label="Başlık" :value="$c['headline']" required maxlength="120" />
                        <x-field name="tagline" label="Slogan" :value="$c['tagline']" maxlength="240" hint="Başlığın altındaki kısa cümle." />
                        <div>
                            <p class="mb-1.5 text-sm font-medium text-ink">Kapak fotoğrafı</p>
                            @if ($c['cover'])
                                <img src="{{ $image($c['cover']) }}" alt="" class="mb-3 aspect-[3/1] w-full rounded-lg object-cover">
                                <x-toggle name="remove_cover" label="Kapak fotoğrafını kaldır" class="mb-3" />
                            @endif
                            <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="field-input">
                            @error('cover')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@else<p class="mt-1.5 text-xs text-muted">Geniş, yatay fotoğraf (ör. mağazanın içi). JPG, PNG veya WebP, en fazla 8 MB.</p>@enderror
                        </div>
                    </div>
                </x-card>

                <x-card title="Hikâyemiz" description="Sayfanın asıl metni. Paragraflar arasında bir boş satır bırakın.">
                    <div x-data="markdownToolbar()">
                        <div class="mb-2 flex flex-wrap gap-1.5 text-sm">
                            <button type="button" class="rounded-md border border-line px-2.5 py-1 font-bold hover:bg-slate-50" @click="bold">K</button>
                            <button type="button" class="rounded-md border border-line px-2.5 py-1 hover:bg-slate-50" @click="heading">Ara başlık</button>
                            <button type="button" class="rounded-md border border-line px-2.5 py-1 hover:bg-slate-50" @click="bullet">• Madde</button>
                            <button type="button" class="rounded-md border border-line px-2.5 py-1 hover:bg-slate-50" @click="link">Bağlantı</button>
                        </div>
                        <textarea name="story" rows="12" x-ref="text" class="field-input text-sm leading-relaxed" maxlength="20000">{{ old('story', $c['story']) }}</textarea>
                        @error('story')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                    </div>
                </x-card>

                <x-card title="Rakamlarla biz" description="İsteğe bağlı, en fazla 4. Ör. 2005 · Kuruluş, 5.000+ · Ürün çeşidi.">
                    <div class="space-y-3">
                        @for ($i = 0; $i < \App\Domain\Store\Content\SitePages::MAX_STATS; $i++)
                            <div class="grid gap-3 sm:grid-cols-3">
                                <x-field :name="'stats['.$i.'][value]'" label="Değer" :value="$c['stats'][$i]['value'] ?? null" maxlength="20" />
                                <x-field :name="'stats['.$i.'][label]'" label="Açıklama" :value="$c['stats'][$i]['label'] ?? null" maxlength="60" class="sm:col-span-2" />
                            </div>
                            @error('stats.'.$i)<p class="text-sm text-danger">{{ $message }}</p>@enderror
                        @endfor
                    </div>
                </x-card>

                <x-card title="Neden biz" description="İsteğe bağlı kartlar, en fazla 4. Boş bırakılan kart görünmez.">
                    <div class="space-y-5">
                        @for ($i = 0; $i < \App\Domain\Store\Content\SitePages::MAX_FEATURES; $i++)
                            <div class="grid gap-3 rounded-lg bg-surface p-4 sm:grid-cols-3">
                                <x-field :name="'features['.$i.'][icon]'" label="Simge" :options="$icons" :value="$c['features'][$i]['icon'] ?? 'check-circle'" />
                                <x-field :name="'features['.$i.'][title]'" label="Başlık" :value="$c['features'][$i]['title'] ?? null" maxlength="60" class="sm:col-span-2" />
                                <x-field :name="'features['.$i.'][text]'" label="Kısa metin" :value="$c['features'][$i]['text'] ?? null" maxlength="200" class="sm:col-span-3" />
                                @error('features.'.$i)<p class="text-sm text-danger sm:col-span-3">{{ $message }}</p>@enderror
                            </div>
                        @endfor
                    </div>
                </x-card>

                <x-card title="Fotoğraf galerisi" description="İsteğe bağlı, en fazla {{ \App\Domain\Store\Content\SitePages::MAX_GALLERY }} fotoğraf.">
                    @if ($c['gallery'])
                        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($c['gallery'] as $path)
                                <label class="block text-sm">
                                    <img src="{{ $image($path) }}" alt="" class="aspect-[4/3] w-full rounded-lg object-cover">
                                    <span class="mt-1.5 flex items-center gap-2"><input type="checkbox" name="remove_gallery[]" value="{{ basename($path) }}" class="size-4 rounded text-danger"> Kaldır</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <input type="file" name="gallery[]" multiple accept="image/jpeg,image/png,image/webp" class="field-input">
                    @error('gallery')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                    @error('gallery.*')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Yayın">
                    <div class="space-y-4">
                        <x-toggle name="is_published" label="Yayında" :checked="$page->is_published" hint="Açıkken sayfa mağazada ve alt bilgide görünür." />
                        <x-toggle name="show_cta" label="Sayfa sonunda “Ürünlere göz atın” düğmesi" :checked="$c['show_cta']" />
                        <x-button class="w-full justify-center">Kaydet</x-button>
                        @unless ($page->exists)<p class="text-xs text-muted">Örnek metinle başlar; kaydedince önizleyebilirsiniz.</p>@endunless
                    </div>
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
