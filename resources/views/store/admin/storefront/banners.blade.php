<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Banner'lar">
    <x-page-header title="Banner'lar" subtitle="Mağaza ana sayfasının en üstünde, ekranın iki yanına kadar uzanan kayan görseller ve altındaki kampanya kutuları." />

    {{-- Ölçü rehberi: görseller vitrinde sabit oranlı çerçevelere tam oturur; oranı farklı görsel kabul edilmez. --}}
    @php [$hw, $hh] = \App\Models\Store\StoreBanner::SIZES['hero']; [$mw, $mh] = \App\Models\Store\StoreBanner::SIZES['mobile']; [$sw, $sh] = \App\Models\Store\StoreBanner::SIZES['side']; @endphp
    <div class="mb-6 rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sm text-sky-950">
        <p class="font-semibold">Görsel ölçüleri — bu ölçülerde hazırlanan görsel vitrinde kırpılmadan, boşluksuz ve tam oturur</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div class="flex items-center gap-3">
                <span class="flex h-8 w-24 shrink-0 items-center justify-center rounded bg-sky-200 text-[10px] font-bold text-sky-900">3 : 1</span>
                <span><b>Büyük alan:</b> {{ $hw }}×{{ $hh }} px<br><span class="text-sky-800">Masaüstünde tam genişlik</span></span>
            </div>
            <div class="flex items-center gap-3">
                <span class="flex h-14 w-11 shrink-0 items-center justify-center rounded bg-sky-200 text-[10px] font-bold text-sky-900">4 : 5</span>
                <span><b>Mobil görsel:</b> {{ $mw }}×{{ $mh }} px<br><span class="text-sky-800">İsteğe bağlı, telefonlar için</span></span>
            </div>
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-[60px] shrink-0 items-center justify-center rounded bg-sky-200 text-[10px] font-bold text-sky-900">3 : 2</span>
                <span><b>Kampanya kutusu:</b> {{ $sw }}×{{ $sh }} px<br><span class="text-sky-800">Büyük alanın altında</span></span>
            </div>
        </div>
        <ul class="mt-4 list-disc space-y-1 pl-5 text-sky-900">
            <li>Aynı oranda daha büyük görsel de olur (ör. 2880×960). Oranı farklı görsel yüklenmez; sistem nedenini yazar.</li>
            <li>Yazı ve logoları kenarlardan biraz içeride tutun. Başlık girerseniz görselin sol tarafında koyu bir geçişin üzerinde gösterilir.</li>
            <li>Telefonlarda dikey mobil görsel, yalnızca tüm büyük alan banner'larında mobil görsel varsa kullanılır; yoksa masaüstü görseli telefonda da bütün hâliyle gösterilir.</li>
            <li>JPG, PNG veya WEBP; en fazla 8 MB.</li>
        </ul>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            @forelse ($banners as $banner)
                <x-card :padding="false" x-data="{ edit: {{ $errors->any() && old('banner_id') == $banner->id ? 'true' : 'false' }} }">
                    <div class="flex flex-wrap items-center gap-4 p-4">
                        <img src="{{ route('store_admin.banners.image', $banner) }}?v={{ $banner->updated_at?->timestamp }}" alt="" class="h-20 w-48 rounded-lg border border-line bg-surface object-contain">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ $banner->title ?: 'Başlıksız banner' }}</p>
                            <p class="truncate text-xs text-muted">{{ $banner->link_url ?: 'Bağlantı yok' }}</p>
                            <p class="mt-1 flex flex-wrap gap-1.5">
                                @if ($banner->is_active)<x-badge tone="success">Yayında</x-badge>@else<x-badge>Pasif</x-badge>@endif
                                @if ($banner->starts_at || $banner->ends_at)<x-badge tone="info">{{ $banner->starts_at?->format('d.m.Y') ?? '…' }} – {{ $banner->ends_at?->format('d.m.Y') ?? '…' }}</x-badge>@endif
                                @if ($banner->mobile_image_path)<x-badge>Mobil görsel var</x-badge>@endif
                            </p>
                        </div>
                        <div class="flex items-center gap-1">
                            @foreach (['up' => '↑', 'down' => '↓'] as $direction => $arrow)
                                <form method="POST" action="{{ route('store_admin.banners.move', $banner) }}">@csrf @method('PUT')<input type="hidden" name="direction" value="{{ $direction }}">
                                    <button class="rounded px-2 py-1 text-muted hover:bg-slate-100 disabled:opacity-30" @disabled(($direction === 'up' && $loop->parent->first) || ($direction === 'down' && $loop->parent->last)) aria-label="Sırayı değiştir">{{ $arrow }}</button></form>
                            @endforeach
                            <button type="button" @click="edit = ! edit" class="ml-2 text-sm font-medium text-primary hover:underline" x-text="edit ? 'Kapat' : 'Düzenle'">Düzenle</button>
                        </div>
                    </div>
                    <div x-show="edit" x-cloak class="border-t border-line bg-slate-50/60 p-4">
                        <form method="POST" action="{{ route('store_admin.banners.update', $banner) }}" enctype="multipart/form-data" class="space-y-4">
                            @csrf @method('PUT')
                            <input type="hidden" name="banner_id" value="{{ $banner->id }}">
                            @include('store.admin.storefront._banner-fields', ['banner' => $banner])
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex flex-wrap gap-5">
                                    <x-toggle name="is_active" label="Yayında" :checked="$banner->is_active" />
                                    @if ($banner->mobile_image_path)<x-toggle name="remove_mobile" label="Mobil görseli kaldır" />@endif
                                </div>
                                <x-button size="sm">Kaydet</x-button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('store_admin.banners.destroy', $banner) }}" class="mt-3 text-right" data-confirm="Banner silinsin mi?">@csrf @method('DELETE')<button class="text-sm text-danger hover:underline">Banner'ı sil</button></form>
                    </div>
                </x-card>
            @empty
                <x-card><x-empty-state icon="image" title="Henüz banner yok" text="Sağdaki formdan ilk banner'ı ekleyin; ana sayfanın en üstünde görünür." /></x-card>
            @endforelse
        </div>

        <x-card title="Yeni banner">
            <form method="POST" action="{{ route('store_admin.banners.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @include('store.admin.storefront._banner-fields', ['banner' => null])
                <x-button icon="plus">Ekle</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.panel>
