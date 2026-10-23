{{--
    Mağaza alt bilgisi. Ayarlar: Vitrin → Alt Bilgi (App\Domain\Store\Content\StoreFooter); iletişim Mağaza Ayarları'ndan,
    kategoriler katalogdan, sayfalar SitePages'ten, saatler İletişim sayfasından gelir. Renkler sabittir (koyu zemin her temada aynı).
--}}
@props(['storeName', 'settings', 'menu', 'pages', 'footer', 'b2bLogin'])
@php
    $digits = $settings['whatsapp'] ? preg_replace('/\D/', '', $settings['whatsapp']) : null;
    $whatsapp = match (true) {
        ! $digits => null,
        str_starts_with($digits, '90') => $digits,
        str_starts_with($digits, '0') => '9'.$digits,
        default => '90'.$digits,
    };
    $socials = array_filter([
        $settings['instagram'] ? ['instagram', $settings['instagram'], 'Instagram'] : null,
        $whatsapp ? ['message-circle', 'https://wa.me/'.$whatsapp, 'WhatsApp'] : null,
        $settings['facebook'] ? ['facebook', $settings['facebook'], 'Facebook'] : null,
    ]);
    $strip = $footer['strip_enabled'] ? $footer['strip'] : [];
    $stripCols = [1 => 'sm:grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-3', 4 => 'sm:grid-cols-2 lg:grid-cols-4'];
    $legal = ['mesafeli-satis', 'iptal-iade', 'kvkk', 'gizlilik-cerez'];
    $place = $settings['address'] ? trim(\Illuminate\Support\Str::afterLast($settings['address'], ',')) : null;
    $link = 'text-white/70 transition hover:text-white';
    $heading = 'text-xs font-bold tracking-[0.2em] text-amber-400 uppercase';
@endphp
<footer id="alt-bilgi" class="mt-16 bg-[#0b1631] text-white print:hidden">
    @if ($strip)
        <div class="border-b border-white/10 bg-[#0e1c3d]">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 py-7 sm:px-6 lg:px-8 {{ $stripCols[count($strip)] }}">
                @foreach ($strip as $item)
                    <div class="flex items-center gap-4">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white/5 text-amber-400 ring-1 ring-white/10"><x-icon :name="$item['icon']" class="size-5" /></span>
                        <span><span class="block font-semibold">{{ $item['title'] }}</span>@if ($item['text'])<span class="block text-sm text-white/60">{{ $item['text'] }}</span>@endif</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mx-auto grid max-w-7xl gap-12 px-4 py-14 sm:grid-cols-2 sm:px-6 lg:grid-cols-12 lg:px-8">
        {{-- Marka ve iletişim --}}
        <div class="lg:col-span-4">
            <a href="{{ route('store.home') }}" class="inline-flex items-center gap-3">
                <x-puzzle-logo :animated="false" class="size-12" />
                <span>
                    <span class="block text-2xl font-extrabold tracking-tight">{{ $storeName }}</span>
                    @if ($footer['tagline'])<span class="block text-[11px] font-semibold tracking-[0.3em] text-amber-400 uppercase">{{ $footer['tagline'] }}</span>@endif
                </span>
            </a>
            @if ($settings['footer_about'])<p class="mt-5 max-w-sm leading-relaxed text-white/70">{{ $settings['footer_about'] }}</p>@endif
            <ul class="mt-6 space-y-3 text-sm">
                @if ($settings['phone'])
                    <li class="flex gap-3"><x-icon name="phone" class="mt-0.5 size-4 shrink-0 text-amber-400" /><a href="tel:{{ preg_replace('/[^\d+]/', '', $settings['phone']) }}" class="{{ $link }}">{{ $settings['phone'] }}</a></li>
                @endif
                @if ($settings['email'])
                    <li class="flex gap-3"><x-icon name="mail" class="mt-0.5 size-4 shrink-0 text-amber-400" /><a href="mailto:{{ $settings['email'] }}" class="break-all {{ $link }}">{{ $settings['email'] }}</a></li>
                @endif
                @if ($settings['address'])
                    <li class="flex gap-3"><x-icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-amber-400" /><span class="leading-relaxed text-white/70">{{ $settings['address'] }}</span></li>
                @endif
            </ul>
            @if ($socials)
                <div class="mt-6 flex gap-2.5">
                    @foreach ($socials as [$icon, $url, $label])
                        <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $label }}"
                            class="flex size-10 items-center justify-center rounded-xl text-white/80 ring-1 ring-white/15 transition hover:bg-white/10 hover:text-white"><x-icon :name="$icon" class="size-4.5" /></a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Alışveriş --}}
        <div class="lg:col-span-2">
            <p class="{{ $heading }}">{{ $footer['shop_title'] }}</p>
            <ul class="mt-5 space-y-3 text-[15px]">
                <li><a href="{{ route('store.products') }}" class="{{ $link }}">Tüm ürünler</a></li>
                @foreach ($menu->take($footer['category_count']) as $root)
                    <li><a href="{{ route('store.category', $root->slug) }}" class="{{ $link }}">{{ $root->name }}</a></li>
                @endforeach
                <li><a href="{{ route('store.brands') }}" class="{{ $link }}">Markalar</a></li>
            </ul>
        </div>

        {{-- Bilgi --}}
        <div class="lg:col-span-3">
            <p class="{{ $heading }}">{{ $footer['info_title'] }}</p>
            <ul class="mt-5 space-y-3 text-[15px]">
                @foreach ($pages as $page)
                    <li><a href="{{ $page['url'] }}" class="{{ $link }}">{{ $page['title'] }}</a></li>
                @endforeach
                @foreach ($legal as $key)
                    <li><a href="{{ route('store.legal', $key) }}" class="{{ $link }}">{{ \App\Models\Store\StoreLegalDocument::KEYS[$key] }}</a></li>
                @endforeach
                <li><button type="button" data-cookie-settings class="{{ $link }}">Çerez tercihleri</button></li>
                @if ($footer['show_b2b'])<li><a href="{{ $b2bLogin }}" class="{{ $link }}">Bayi girişi</a></li>@endif
            </ul>
        </div>

        {{-- Mağazamız --}}
        @if ($footer['directions'] || $footer['hours'])
            <div class="space-y-4 sm:col-span-2 lg:col-span-3">
                <p class="{{ $heading }}">{{ $footer['store_title'] }}</p>
                @if ($footer['directions'])
                    <a href="{{ $footer['directions'] }}" target="_blank" rel="noopener"
                        class="group relative block overflow-hidden rounded-2xl bg-gradient-to-br from-white/10 to-white/[0.02] p-5 ring-1 ring-white/10 transition hover:ring-amber-400/60">
                        <span class="flex flex-col items-center gap-2 py-3 text-center">
                            <span class="flex size-12 items-center justify-center rounded-full bg-amber-400/15 text-amber-400 ring-1 ring-amber-400/40"><x-icon name="map-pin" class="size-5" /></span>
                            <span class="text-sm text-white/70 group-hover:text-white">Haritada gör · Yol tarifi al</span>
                        </span>
                        <span class="mt-2 flex items-center justify-between text-sm font-semibold">
                            <span class="truncate">{{ $place }}</span><x-icon name="arrow-right" class="size-4 -rotate-45 text-amber-400" />
                        </span>
                    </a>
                @endif
                @if ($footer['hours'])
                    <div class="rounded-2xl bg-white/5 p-5 ring-1 ring-white/10">
                        <p class="flex items-center gap-2 text-sm font-bold tracking-wide uppercase"><x-icon name="clock" class="size-4 text-amber-400" /> Çalışma saatleri</p>
                        <dl class="mt-4 space-y-2.5 text-sm">
                            @foreach ($footer['hours'] as $row)
                                <div class="flex justify-between gap-3">
                                    <dt class="text-white/70">{{ $row['days'] }}</dt>
                                    <dd @class(['font-semibold tabular-nums', 'text-rose-300' => $row['closed']])>{{ $row['hours'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        @if ($footer['contact_url'])<a href="{{ $footer['contact_url'] }}" class="mt-4 inline-block text-sm font-medium text-amber-400 hover:underline">İletişim sayfası →</a>@endif
                    </div>
                @endif
            </div>
        @endif
    </div>

    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-col items-center gap-4 px-4 py-6 text-sm sm:px-6 lg:flex-row lg:justify-between lg:px-8">
            <p class="text-center text-white/60 lg:text-left">© {{ now()->year }} <span class="font-semibold text-white">{{ $settings['company_title'] ?: $storeName }}</span> — Tüm hakları saklıdır.
                @if ($footer['bottom_note'])<span class="block text-white/50 sm:inline sm:pl-2">{{ $footer['bottom_note'] }}</span>@endif</p>
            <div class="flex flex-wrap items-center justify-center gap-4">
                @if ($settings['payment_logos_path'])
                    <span class="rounded-lg bg-[#fff] px-2.5 py-1.5"><img src="{{ route('store.media.payment-logos') }}" alt="iyzico ile öde, kabul edilen kartlar" class="h-6 w-auto" loading="lazy"></span>
                @endif
            </div>
        </div>
    </div>
</footer>

@if ($whatsapp && $footer['whatsapp_button'])
    <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" aria-label="WhatsApp ile yazın"
        class="fixed right-5 bottom-5 z-40 flex size-14 items-center justify-center rounded-full bg-[#25d366] text-white shadow-lg shadow-black/20 transition hover:scale-105 print:hidden">
        <x-icon name="message-circle" class="size-7" />
    </a>
@endif
