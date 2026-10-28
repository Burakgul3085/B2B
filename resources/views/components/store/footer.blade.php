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
    // Mağaza Ayarları → Sosyal medya: yalnızca doldurulan hesaplar
    $socials = array_map(fn ($s) => [$s['key'], $s['url'], $s['label']], \App\Domain\Store\Content\SocialLinks::filled());
    if ($whatsapp) {
        $socials[] = ['message-circle', 'https://wa.me/'.$whatsapp, 'WhatsApp'];
    }
    $strip = $footer['strip_enabled'] ? $footer['strip'] : [];
    $stripCols = [1 => 'sm:grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-3', 4 => 'sm:grid-cols-2 lg:grid-cols-4'];
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
        <div class="lg:col-span-3">
            <a href="{{ route('store.home') }}" class="inline-flex items-center gap-3">
                <x-puzzle-logo :animated="false" class="size-12" />
                <x-store.wordmark :name="$storeName" :tagline="$footer['tagline']" nameClass="text-2xl font-extrabold tracking-tight" taglineClass="text-amber-400" :taglineSize="11" :nameSize="24" />
            </a>
            @if ($settings['footer_about'])<p class="mt-5 max-w-sm leading-relaxed text-white/70">{{ $settings['footer_about'] }}</p>@endif
            <ul class="mt-6 space-y-3 text-sm">
                @php $phones = \App\Domain\Store\Storefront\StorePhones::list($settings); @endphp
                @foreach ($phones as $phone)
                    <li class="flex gap-3"><x-icon name="phone" class="mt-0.5 size-4 shrink-0 text-amber-400" /><span>@if (count($phones) > 1)<span class="mr-1.5 text-white/50">{{ $phone['label'] }}:</span>@endif<a href="{{ $phone['tel'] }}" class="{{ $link }}">{{ $phone['number'] }}</a></span></li>
                @endforeach
                @if ($settings['email'])
                    <li class="flex gap-3"><x-icon name="mail" class="mt-0.5 size-4 shrink-0 text-amber-400" /><a href="mailto:{{ $settings['email'] }}" class="break-all {{ $link }}">{{ $settings['email'] }}</a></li>
                @endif
                @if ($settings['address'])
                    <li class="flex gap-3"><x-icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-amber-400" /><span class="leading-relaxed text-white/70">{{ $settings['address'] }}</span></li>
                @endif
            </ul>
            @if ($socials)
                <div class="mt-6 flex flex-wrap gap-2.5">
                    @foreach ($socials as [$icon, $url, $label])
                        <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $label }}"
                            class="flex size-10 items-center justify-center rounded-xl text-white/80 ring-1 ring-white/15 transition hover:bg-white/10 hover:text-white"><x-icon :name="$icon" class="size-4.5" /></a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Bağlantı sütunları (Vitrin → Üst ve Alt Bilgi → Sütunlar) --}}
        @php
            $columns = collect($footer['columns'])->map(fn ($c) => ['title' => $c['title'], 'links' => \App\Domain\Store\Content\FooterLinks::render(
                $footer['show_b2b'] ? $c['links'] : array_values(array_filter($c['links'], fn ($l) => ($l['key'] ?? '') !== 'b2b')), $b2bLogin)])
                ->filter(fn ($c) => $c['links'])->values();
            $colGrid = [1 => 'sm:grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-3', 4 => 'sm:grid-cols-2 xl:grid-cols-4'][max(1, $columns->count())];
        @endphp
        @php $newsletter = app(\App\Domain\Store\Newsletter\Subscriptions::class)->formAvailable(); @endphp
        @if ($columns->isNotEmpty() || $newsletter)
          <div class="sm:col-span-2 lg:col-span-6">
            <div class="grid gap-10 {{ $colGrid }}">
                @foreach ($columns as $column)
                    <div>
                        <p class="{{ $heading }}">{{ $column['title'] }}</p>
                        <ul class="mt-5 space-y-3 text-[15px]">
                            @foreach ($column['links'] as $item)
                                <li>
                                    @if ($item['cookies'])
                                        <button type="button" data-cookie-settings class="{{ $link }}">{{ $item['label'] }}</button>
                                    @else
                                        <a href="{{ $item['url'] }}" class="{{ $link }}" @if ($item['external']) target="_blank" rel="noopener" @endif>{{ $item['label'] }}</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
            {{-- E-bülten: sütunların altında, ortada --}}
            @if ($newsletter)
                @include('components.store._newsletter', ['title' => $settings['newsletter_title'], 'text' => $settings['newsletter_text'], 'consent' => $settings['newsletter_consent']])
            @endif
          </div>
        @endif

        {{-- Mağazamız --}}
        @if ($footer['directions'] || $footer['hours'])
            <div class="space-y-4 sm:col-span-2 lg:col-span-3">
                <p class="{{ $heading }}">{{ $footer['store_title'] }}</p>
                @if ($footer['map_embed'])
                    {{-- Gömülü Google haritası: herkese açık --}}
                    <div class="overflow-hidden rounded-2xl ring-1 ring-white/10">
                        <iframe src="{{ $footer['map_embed'] }}" class="block h-44 w-full border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                            title="{{ $storeName }} konumu"></iframe>
                        <a href="{{ $footer['directions'] }}" target="_blank" rel="noopener"
                            class="group flex items-center justify-between gap-3 bg-white/5 px-4 py-3 text-sm font-semibold transition hover:bg-white/10">
                            <span class="flex min-w-0 items-center gap-2"><x-icon name="map-pin" class="size-4 shrink-0 text-amber-400" /><span class="truncate">{{ $place ?: 'Yol tarifi al' }}</span></span>
                            <span class="flex shrink-0 items-center gap-1 text-amber-400">Yol tarifi <x-icon name="arrow-right" class="size-4 -rotate-45 transition group-hover:translate-x-0.5" /></span>
                        </a>
                    </div>
                @elseif ($footer['directions'])
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
                                <div class="flex items-baseline justify-between gap-3">
                                    <dt class="text-white/70">{{ $row['days'] }}</dt>
                                    <dd @class(['shrink-0 font-semibold whitespace-nowrap tabular-nums', 'text-rose-300' => $row['closed']])>{{ $row['hours'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        @if ($footer['contact_url'])<a href="{{ $footer['contact_url'] }}" class="mt-4 inline-block text-sm font-medium text-amber-400 hover:underline">İletişim sayfası →</a>@endif
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Ödeme logoları: iyzico'nun resmî beyaz bandı (koyu zemin); yönetimden yüklenen bant varsa onun yerine geçer --}}
    <div class="border-t border-white/10 bg-[#0e1c3d]">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <x-store.payment-marks dark :src="$settings['payment_logos_path'] ? route('store.media.payment-logos') : null"
                :transfer="$settings['transfer_enabled'] === '1'" :cod="$settings['cod_enabled'] === '1'" />
            <p class="mt-4 text-center text-xs text-white/60">Kart bilgileriniz iyzico güvencesiyle işlenir; mağazamızda saklanmaz. Uygun kartlarla taksitli ödeme yapabilirsiniz.</p>
        </div>
    </div>

    {{-- Satıcı künyesi ve sözleşmeler: alt bilgi sütunları yönetimde değiştirilse de her sayfada görünür (iyzico üye işyeri koşulu). --}}
    @php
        $seller = array_filter([
            $settings['company_title'] ?: $storeName,
            ($settings['company_tax_office'] || $settings['company_tax_number']) ? 'Vergi dairesi / no: '.trim($settings['company_tax_office'].' / '.$settings['company_tax_number'], ' /') : null,
            $settings['company_mersis'] ? 'MERSİS: '.$settings['company_mersis'] : ($settings['company_esnaf_no'] ? 'Esnaf sicil no: '.$settings['company_esnaf_no'] : null),
            $settings['address'],
        ]);
    @endphp
    <div class="border-t border-white/10 bg-[#0e1c3d]">
        <div class="mx-auto max-w-7xl px-4 py-4 text-center text-xs text-white/55 sm:px-6 lg:px-8">
            <nav aria-label="Sözleşmeler" class="flex flex-wrap justify-center gap-x-4 gap-y-1.5">
                @foreach (\App\Models\Store\StoreLegalDocument::KEYS as $key => $title)
                    <a href="{{ route('store.legal', $key) }}" class="text-white/75 transition hover:text-white hover:underline">{{ $title }}</a>
                @endforeach
            </nav>
            <p class="mt-2 leading-relaxed">{{ implode(' · ', $seller) }}</p>
        </div>
    </div>

    {{-- Telif ve künye --}}
    <div class="border-t border-white/10 bg-black/20">
        <div class="mx-auto flex max-w-7xl flex-col items-center gap-3 px-4 py-5 text-[13px] sm:px-6 lg:flex-row lg:justify-between lg:px-8">
            <p class="text-center text-white/60 lg:text-left">© {{ now()->year }} <span class="font-semibold text-white">{{ $settings['company_title'] ?: $storeName }}</span> — Tüm hakları saklıdır.
                @if ($footer['bottom_note'])<span class="block text-white/50 sm:inline sm:pl-2">{{ $footer['bottom_note'] }}</span>@endif</p>
            @php $dev = config('store.developer'); @endphp
            <div class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-white/55">
                @if ($footer['manager_name'])
                    <span>Sayfa yöneticisi: <span class="font-semibold text-white/85">{{ $footer['manager_name'] }}</span></span>
                    <span class="hidden h-4 w-px bg-white/15 sm:block" aria-hidden="true"></span>
                @endif
                <span class="flex items-center gap-2.5">
                    <span>Yazılım geliştirme: <span class="font-semibold text-white/85">{{ $dev['name'] }}</span></span>
                    <a href="mailto:{{ $dev['email'] }}" class="flex size-7 items-center justify-center rounded-lg bg-white/5 ring-1 ring-white/10 transition hover:bg-white/10 hover:text-white" aria-label="{{ $dev['name'] }} e-posta: {{ $dev['email'] }}" title="{{ $dev['email'] }}"><x-icon name="mail" class="size-3.5" /></a>
                    <a href="{{ $dev['linkedin'] }}" target="_blank" rel="noopener" class="flex size-7 items-center justify-center rounded-lg bg-white/5 ring-1 ring-white/10 transition hover:bg-[#0a66c2] hover:text-white" aria-label="{{ $dev['name'] }} LinkedIn" title="LinkedIn"><x-icon name="linkedin" class="size-3.5" /></a>
                </span>
            </div>
        </div>
    </div>
</footer>

@if ($whatsapp && $footer['whatsapp_button'])
    <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" aria-label="WhatsApp ile yazın"
        class="fixed right-4 bottom-20 z-40 flex size-14 lg:right-5 lg:bottom-5 items-center justify-center rounded-full bg-[#25d366] text-white shadow-lg shadow-black/20 transition hover:scale-105 print:hidden">
        <x-icon name="message-circle" class="size-7" />
    </a>
@endif
