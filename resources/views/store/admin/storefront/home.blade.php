<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Ana Sayfa">
    <x-page-header title="Ana Sayfa" subtitle="Ana sayfadaki bölümlerin sırası, görünürlüğü ve başlıkları. Bölümlerin içeriği canlı veriden gelir; içeriği olmayan bölüm görünmez.">
        <x-button :href="route('store.home')" variant="secondary" icon="eye" target="_blank">Mağazada gör</x-button>
    </x-page-header>

    <form method="POST" action="{{ route('store_admin.home-layout.update') }}" class="space-y-4">
        @csrf @method('PUT')
        <x-card :padding="false">
            <ul class="divide-y divide-line">
                @foreach ($blocks as $i => $block)
                    @php $key = $block['key']; $hasTitle = \App\Domain\Store\Storefront\HomeLayout::BLOCKS[$key]['title'] !== null; @endphp
                    <li class="grid gap-4 p-5 md:grid-cols-12 md:items-start">
                        <div class="flex items-center gap-3 md:col-span-4">
                            <input type="number" name="blocks[{{ $key }}][sort]" value="{{ $i + 1 }}" min="0" max="99" class="field-input w-16 text-center" aria-label="{{ $block['label'] }} sırası">
                            <div>
                                <p class="font-semibold">{{ $block['label'] }}</p>
                                <x-toggle :name="'blocks['.$key.'][enabled]'" label="Gösterilsin" :checked="$block['enabled']" class="mt-1" />
                            </div>
                        </div>
                        @if ($hasTitle)
                            <x-field :name="'blocks['.$key.'][title]'" label="Başlık" :value="$block['title']" maxlength="80" class="md:col-span-4" />
                            <x-field :name="'blocks['.$key.'][subtitle]'" label="Alt başlık" :value="$block['subtitle']" maxlength="160" class="md:col-span-4" />
                        @else
                            <p class="text-sm text-muted md:col-span-8">
                                @switch($key)
                                    @case('hero') İçerik: <a href="{{ route('store_admin.banners.index') }}" class="text-primary hover:underline">Banner'lar</a> (büyük alan ve yan küçük alan). @break
                                    @case('perks') İçerik: <a href="{{ route('store_admin.footer.edit') }}" class="text-primary hover:underline">Üst ve Alt Bilgi → Güven şeridi</a>. @break
                                    @case('about') İçerik: <a href="{{ route('store_admin.site-pages.about') }}" class="text-primary hover:underline">Hakkımızda</a> sayfası (yayındaysa). @break
                                @endswitch
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-card>
        <div class="flex items-center gap-3">
            <x-button>Kaydet</x-button>
            <p class="text-sm text-muted">Sıra numarası küçük olan önce gelir.</p>
        </div>
    </form>
</x-layouts.panel>
