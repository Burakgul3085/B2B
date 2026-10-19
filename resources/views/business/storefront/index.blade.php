@php
    use App\Models\HomeBanner;
    use App\Models\Showcase;

    $status = function (HomeBanner $b): array {
        $today = now()->toDateString();

        return match (true) {
            ! $b->is_active => ['Kapalı', 'neutral'],
            $b->starts_on && $b->starts_on->toDateString() > $today => ['Planlandı · '.$b->starts_on->format('d.m.Y'), 'info'],
            $b->ends_on && $b->ends_on->toDateString() < $today => ['Süresi doldu', 'warning'],
            default => ['Yayında', 'success'],
        };
    };
    $linkLabel = function (HomeBanner $b) use ($targets): string {
        return match ($b->link_type) {
            'none' => 'Bağlantı yok',
            'catalog' => 'Tüm ürünler',
            default => HomeBanner::LINK_TYPES[$b->link_type].': '.($targets[$b->link_type][$b->link_id] ?? 'bulunamadı'),
        };
    };
    $sourceName = fn (Showcase $s) => match ($s->type) {
        'category' => $targets['category'][$s->source_id] ?? '—',
        'brand' => $targets['brand'][$s->source_id] ?? '—',
        default => null,
    };
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Vitrin">
    <x-page-header title="Vitrin" subtitle="Bayilerinizin giriş yaptığında gördüğü ana sayfa: tanıtım slaytları ve ürün vitrinleri." />

    <div x-data="{ tab: '{{ $tab }}' }">
        <div class="mb-6 flex gap-1 border-b border-line" role="tablist">
            @foreach (['banners' => ['Slaytlar', $banners->count(), 'image'], 'showcases' => ['Ürün vitrinleri', $showcases->count(), 'layers']] as $key => [$label, $count, $icon])
                <button type="button" role="tab" @click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'"
                    class="-mb-px inline-flex items-center gap-2 border-b-2 px-4 pb-3 text-sm font-semibold transition"
                    :class="tab === '{{ $key }}' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'">
                    <x-icon :name="$icon" class="size-4" /> {{ $label }}
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-muted">{{ $count }}</span>
                </button>
            @endforeach
        </div>

        {{-- ================= Slaytlar ================= --}}
        <div x-show="tab === 'banners'" @if ($tab !== 'banners') x-cloak @endif class="grid gap-6 xl:grid-cols-5">
            <div class="space-y-4 xl:col-span-3">
                <div class="flex items-start gap-3 rounded-xl border border-blue-200 bg-info-soft px-4 py-3 text-sm text-sky-900">
                    <x-icon name="alert" class="mt-0.5 size-4 shrink-0" />
                    <p>Slaytlar bayi ana sayfasında sırasıyla <strong>5 saniyede bir</strong> değişir; video slaytlar video bitene kadar gösterilir. Yüklediğiniz görseller ve videolar <strong>kırpılmaz</strong>. En iyi görünüm için <strong>ana slaytta 1920×600</strong>, <strong>ara bantta 1920×320</strong> piksel kullanın.</p>
                </div>

                @forelse ($banners as $banner)
                    @php [$label, $tone] = $status($banner); @endphp
                    <article class="overflow-hidden rounded-2xl border border-line bg-white shadow-xs" x-data="{ edit: {{ old('_banner') === 'b'.$banner->id ? 'true' : 'false' }} }">
                        <div class="flex flex-col gap-4 p-4 sm:flex-row">
                            <div @class(['relative w-full shrink-0 overflow-hidden rounded-xl sm:w-64', 'aspect-[16/5]' => $banner->placement === 'hero', 'aspect-[6/1]' => $banner->placement !== 'hero'])>
                                <x-banner-media :banner="$banner" route="business.storefront.banners.media" :index="$loop->index" loop />
                                @if ($banner->title)
                                    <div class="absolute inset-0 flex items-center bg-linear-to-r from-slate-950/70 to-transparent px-3">
                                        <p class="line-clamp-2 max-w-[70%] text-xs font-bold text-white">{{ $banner->title }}</p>
                                    </div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-semibold text-muted tabular-nums">#{{ $loop->iteration }}</span>
                                    <x-badge :tone="$tone">{{ $label }}</x-badge>
                                    <x-badge :tone="$banner->placement === 'hero' ? 'primary' : 'info'">{{ HomeBanner::PLACEMENTS[$banner->placement]['label'] }}</x-badge>
                                    <x-badge tone="neutral">{{ $banner->isVideo() ? 'Video' : 'Görsel' }}</x-badge>
                                </div>
                                <p class="mt-1.5 truncate font-semibold text-ink">{{ $banner->title ?: 'Başlıksız slayt' }}</p>
                                <dl class="mt-1 space-y-0.5 text-xs text-muted">
                                    <div>{{ $linkLabel($banner) }}</div>
                                    <div>{{ $banner->group?->name ? 'Yalnızca '.$banner->group->name : 'Tüm bayiler' }}@if ($banner->ends_on) · {{ $banner->ends_on->format('d.m.Y') }} tarihine kadar @endif</div>
                                </dl>
                            </div>
                            <div class="flex shrink-0 items-start gap-1 sm:flex-col sm:items-end">
                                <div class="flex gap-1">
                                    @foreach (['up' => ['Yukarı taşı', 'rotate-180', $loop->first], 'down' => ['Aşağı taşı', '', $loop->last]] as $dir => [$title, $rot, $disabled])
                                        <form method="POST" action="{{ route('business.storefront.banners.move', $banner) }}">
                                            @csrf <input type="hidden" name="direction" value="{{ $dir }}">
                                            <button class="flex size-8 items-center justify-center rounded-lg border border-line text-muted hover:bg-slate-50 hover:text-ink disabled:opacity-40" title="{{ $title }}" aria-label="{{ $title }}" @disabled($disabled)>
                                                <x-icon name="chevron-down" class="size-4 {{ $rot }}" />
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                                <div class="flex gap-1">
                                    <x-button type="button" variant="secondary" size="sm" icon="pencil" @click="edit = !edit">Düzenle</x-button>
                                    <form method="POST" action="{{ route('business.storefront.banners.destroy', $banner) }}" data-confirm="Bu slayt silinsin mi? Görsel/video da silinir.">
                                        @csrf @method('DELETE')
                                        <x-button variant="ghost" size="sm" icon="trash" class="text-danger" aria-label="Sil" />
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div x-show="edit" x-cloak class="border-t border-line bg-slate-50/60 p-5">
                            @include('business.storefront._banner-form', ['banner' => $banner])
                        </div>
                    </article>
                @empty
                    <x-card><x-empty-state icon="image" title="Henüz slayt yok" text="İlk slaytınızı ekleyin; bayileriniz giriş yaptığında ana sayfanın en üstünde görür." /></x-card>
                @endforelse
            </div>

            <div class="xl:col-span-2">
                <x-card title="Yeni slayt" class="xl:sticky xl:top-6">
                    @include('business.storefront._banner-form', ['banner' => null])
                </x-card>
            </div>
        </div>

        {{-- ================= Ürün vitrinleri ================= --}}
        <div x-show="tab === 'showcases'" @if ($tab !== 'showcases') x-cloak @endif class="grid gap-6 xl:grid-cols-5">
            <div class="space-y-4 xl:col-span-3">
                <div class="flex items-start gap-3 rounded-xl border border-blue-200 bg-info-soft px-4 py-3 text-sm text-sky-900">
                    <x-icon name="alert" class="mt-0.5 size-4 shrink-0" />
                    <p>Her vitrin ana sayfada bir sekme olur. Stokta olmayan ürünler vitrinin sonuna alınır; yalnızca bayilerin sipariş verebildiği ürünler gösterilir. Ürünleri ürün sayfasındaki "Vitrinler" kutusundan da ekleyebilirsiniz.</p>
                </div>

                @forelse ($showcases as $showcase)
                    <article class="rounded-2xl border border-line bg-white shadow-xs" x-data="{ edit: false }">
                        <div class="flex flex-wrap items-start justify-between gap-3 p-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-semibold text-muted tabular-nums">#{{ $loop->iteration }}</span>
                                    <p class="font-semibold text-ink">{{ $showcase->name }}</p>
                                    <x-badge :tone="$showcase->is_active ? 'success' : 'neutral'">{{ $showcase->is_active ? 'Yayında' : 'Kapalı' }}</x-badge>
                                </div>
                                <p class="mt-1 text-xs text-muted">{{ Showcase::TYPES[$showcase->type] }}@if ($sourceName($showcase)): {{ $sourceName($showcase) }}@endif · en fazla {{ $showcase->product_limit }} ürün</p>
                            </div>
                            <div class="flex items-center gap-1">
                                @foreach (['up' => ['Yukarı taşı', 'rotate-180', $loop->first], 'down' => ['Aşağı taşı', '', $loop->last]] as $dir => [$title, $rot, $disabled])
                                    <form method="POST" action="{{ route('business.storefront.showcases.move', $showcase) }}">
                                        @csrf <input type="hidden" name="direction" value="{{ $dir }}">
                                        <button class="flex size-8 items-center justify-center rounded-lg border border-line text-muted hover:bg-slate-50 hover:text-ink disabled:opacity-40" title="{{ $title }}" aria-label="{{ $title }}" @disabled($disabled)>
                                            <x-icon name="chevron-down" class="size-4 {{ $rot }}" />
                                        </button>
                                    </form>
                                @endforeach
                                <x-button type="button" variant="secondary" size="sm" icon="pencil" @click="edit = !edit">Düzenle</x-button>
                                <form method="POST" action="{{ route('business.storefront.showcases.destroy', $showcase) }}" data-confirm="Bu vitrin silinsin mi? Ürünler silinmez, yalnızca vitrinden çıkar.">
                                    @csrf @method('DELETE')
                                    <x-button variant="ghost" size="sm" icon="trash" class="text-danger" aria-label="Sil" />
                                </form>
                            </div>
                        </div>

                        <div x-show="edit" x-cloak class="border-t border-line bg-slate-50/60 p-5">
                            @include('business.storefront._showcase-form', ['showcase' => $showcase])
                        </div>

                        @if ($showcase->type === 'manual')
                            <div class="border-t border-line p-4">
                                @if ($showcase->items->isEmpty())
                                    <p class="text-sm text-muted">Henüz ürün eklenmedi.</p>
                                @else
                                    <ul class="flex flex-wrap gap-2">
                                        @foreach ($showcase->items as $item)
                                            <li class="inline-flex items-center gap-1.5 rounded-full border border-line bg-slate-50 py-1 pr-1 pl-3 text-sm">
                                                <span class="max-w-56 truncate">{{ $item->product?->name }}</span>
                                                <form method="POST" action="{{ route('business.storefront.showcases.products.destroy', [$showcase, $item->product_id]) }}">
                                                    @csrf @method('DELETE')
                                                    <button class="flex size-6 items-center justify-center rounded-full text-muted hover:bg-white hover:text-danger" aria-label="{{ $item->product?->name }} ürününü çıkar"><x-icon name="x" class="size-3.5" /></button>
                                                </form>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                <form method="POST" action="{{ route('business.storefront.showcases.products.store', $showcase) }}" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end">
                                    @csrf
                                    <x-product-picker label="Ürün ekle" :required="false" class="flex-1" />
                                    <x-button icon="plus">Ekle</x-button>
                                </form>
                            </div>
                        @endif
                    </article>
                @empty
                    <x-card><x-empty-state icon="layers" title="Henüz vitrin yok" text="Örn. &quot;Önerdiğimiz ürünler&quot;, &quot;Yeni gelenler&quot; veya bir kategoriyi vitrin yapın." /></x-card>
                @endforelse
            </div>

            <div class="xl:col-span-2">
                <x-card title="Yeni vitrin" class="xl:sticky xl:top-6">
                    @include('business.storefront._showcase-form', ['showcase' => null])
                </x-card>
            </div>
        </div>
    </div>
</x-layouts.panel>
