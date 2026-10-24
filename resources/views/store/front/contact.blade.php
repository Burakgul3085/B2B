@php
    use App\Domain\Store\Content\SocialLinks;
    use App\Domain\Store\Storefront\Palette;
    use App\Domain\Store\Storefront\ProductPage;

    $c = $content;
    $s = $settings;
    $whatsapp = ProductPage::whatsapp($s['whatsapp']);
    $phoneUrl = $s['phone'] ? 'tel:'.preg_replace('/[^\d+]/', '', $s['phone']) : null;
    $cards = array_values(array_filter([
        $s['phone'] ? ['icon' => 'phone', 'label' => 'Telefon', 'value' => $s['phone'], 'url' => $phoneUrl, 'note' => 'Hemen arayın'] : null,
        $whatsapp ? ['icon' => 'message-circle', 'label' => 'WhatsApp', 'value' => 'Mesaj gönderin', 'url' => 'https://wa.me/'.$whatsapp, 'note' => 'Hızlı yanıt'] : null,
        $s['email'] ? ['icon' => 'mail', 'label' => 'E-posta', 'value' => $s['email'], 'url' => 'mailto:'.$s['email'], 'note' => 'Bize yazın'] : null,
    ]));
    $socials = SocialLinks::filled();
    $card = 'rounded-3xl border border-line bg-white p-6 sm:p-7';
@endphp
<x-layouts.store :title="$page->seo_title ?: 'İletişim'" :description="$page->seo_description ?: $c['intro']" :canonical="route('store.contact')"
    :noindex="$adminPreview" :jsonLd="$jsonLd">
    @if ($adminPreview)
        <div class="bg-amber-400 px-4 py-2 text-center text-sm font-medium text-slate-900">Önizleme: kaydedilmiş içerik{{ $page->is_published ? '' : ' — sayfa henüz yayında değil' }}.</div>
    @endif

    {{-- Başlık alanı --}}
    <section class="relative overflow-hidden bg-[#0b1631] text-white">
        <div class="pointer-events-none absolute -right-10 -bottom-12 hidden rotate-12 grid-cols-2 gap-3 opacity-90 md:grid" aria-hidden="true">
            <span class="size-24 rounded-3xl bg-puzzle-orange"></span><span class="size-24 rounded-3xl bg-puzzle-green"></span>
            <span class="size-24 rounded-3xl bg-puzzle-blue"></span><span class="size-24 rounded-3xl bg-puzzle-yellow"></span>
        </div>
        <div class="relative mx-auto max-w-7xl px-4 pt-10 pb-24 sm:px-6 sm:pt-14 lg:px-8">
            <nav class="text-sm text-white/60" aria-label="Konum"><a href="{{ route('store.home') }}" class="hover:text-white">Ana sayfa</a> <span class="mx-1">›</span> İletişim</nav>
            <div class="mt-6 flex flex-wrap items-center gap-3">
                <h1 class="font-display text-4xl font-extrabold tracking-tight sm:text-5xl">İletişim</h1>
                @if ($openNow !== null)
                    <span @class(['inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-bold ring-1',
                        'bg-[#34d77b]/15 text-[#34d77b] ring-[#34d77b]/30' => $openNow, 'bg-rose-400/15 text-rose-300 ring-rose-300/30' => ! $openNow])>
                        <span @class(['size-2 rounded-full', 'bg-[#34d77b]' => $openNow, 'bg-rose-300' => ! $openNow])></span>{{ $openNow ? 'Şu an açık' : 'Şu an kapalı' }}
                    </span>
                @endif
            </div>
            @if ($c['intro'])<p class="mt-3 max-w-2xl text-white/70 sm:text-lg">{{ $c['intro'] }}</p>@endif
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 pb-6 sm:px-6 lg:px-8">
        {{-- Hızlı iletişim kartları (başlık alanının üzerine biner) --}}
        @if ($cards)
            <div @class(['relative -mt-14 grid gap-4', 'sm:grid-cols-2 lg:grid-cols-3' => count($cards) >= 3, 'sm:grid-cols-2' => count($cards) === 2])>
                @foreach ($cards as $i => $item)
                    @php $tone = Palette::classes(Palette::CYCLE[$i % 4]); @endphp
                    <a href="{{ $item['url'] }}" @if (str_starts_with($item['url'], 'http')) target="_blank" rel="noopener" @endif
                        class="group flex items-center gap-4 rounded-3xl border border-line bg-white p-5 shadow-xl shadow-navy/5 transition hover:-translate-y-0.5 hover:shadow-navy/10">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl {{ $tone['soft'] }} {{ $tone['text'] }}"><x-icon :name="$item['icon']" class="size-6" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-bold tracking-wider text-muted uppercase">{{ $item['label'] }}</span>
                            <span class="block truncate font-display text-base font-extrabold sm:text-[17px]" title="{{ $item['value'] }}">{{ $item['value'] }}</span>
                        </span>
                        <span class="hidden shrink-0 items-center gap-1 text-xs font-semibold text-muted transition group-hover:text-ink 2xl:flex">{{ $item['note'] }} <x-icon name="arrow-right" class="size-3.5 transition group-hover:translate-x-0.5" /></span>
                    </a>
                @endforeach
            </div>
        @endif

        <div @class(['grid gap-6 lg:grid-cols-12', 'mt-10' => $cards, 'mt-8' => ! $cards])>
            {{-- Form --}}
            @if ($c['form_enabled'])
                <section id="form" class="scroll-mt-32 lg:col-span-7 {{ $card }} sm:p-8">
                    <div class="flex items-start gap-4">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-navy text-white"><x-icon name="message-circle" class="size-6" /></span>
                        <div>
                            <h2 class="font-display text-2xl font-extrabold">Bize yazın</h2>
                            <p class="mt-1 text-sm text-muted">Sorunuzu iletin, en kısa sürede size dönüş yapalım.</p>
                        </div>
                    </div>
                    @if (session('contact_sent'))
                        <div role="status" class="mt-6 flex items-start gap-3 rounded-2xl bg-success-soft px-5 py-4 text-sm text-green-900">
                            <x-icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-success" />
                            <span>Mesajınız bize ulaştı, teşekkür ederiz. En kısa sürede size dönüş yapacağız.</span>
                        </div>
                    @else
                        <form method="POST" action="{{ $adminPreview ? '#' : route('store.contact.send') }}" class="auth-form mt-6 space-y-5">
                            @csrf
                            <x-store.bot-trap />
                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-field name="name" label="Ad soyad" required maxlength="80" autocomplete="name" />
                                <x-field name="subject" label="Konu" :options="['' => 'Seçin'] + $subjects" required />
                                <x-field name="email" label="E-posta" type="email" maxlength="255" autocomplete="email" />
                                <x-field name="phone" label="Telefon" type="tel" maxlength="30" autocomplete="tel" />
                            </div>
                            <p class="-mt-3 text-xs text-muted">Size dönebilmemiz için e-posta veya telefonunuzdan en az birini yazın.</p>
                            <x-field name="message" label="Mesajınız" textarea rows="6" required minlength="10" maxlength="2000" />
                            <div class="flex flex-wrap items-center justify-between gap-4">
                                <p class="max-w-sm text-xs text-muted">Gönderdiğiniz bilgiler yalnızca talebinizi yanıtlamak için kullanılır. Ayrıntılar: <a href="{{ route('store.legal', 'kvkk') }}" class="font-semibold text-navy-ink underline">KVKK Aydınlatma Metni</a>.</p>
                                <button class="inline-flex h-12 items-center gap-2 rounded-xl bg-navy px-7 font-semibold text-white shadow-lg shadow-navy/20 transition hover:bg-puzzle-orange disabled:opacity-60" @disabled($adminPreview)>
                                    Gönder <x-icon name="arrow-right" class="size-4" />
                                </button>
                            </div>
                        </form>
                    @endif
                </section>
            @endif

            {{-- Konum, saatler, satıcı bilgileri --}}
            <div @class(['space-y-6', 'lg:col-span-5' => $c['form_enabled'], 'lg:col-span-12 grid gap-6 lg:grid-cols-2 lg:space-y-0' => ! $c['form_enabled']])>
                @if ($c['map_embed'] || $s['address'])
                    <section class="overflow-hidden rounded-3xl border border-line bg-white">
                        @if ($c['map_embed'])
                            <iframe src="{{ $c['map_embed'] }}" class="block h-64 w-full border-0 sm:h-72" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="{{ config('store.name') }} konumu"></iframe>
                        @endif
                        <div class="p-6">
                            <h2 class="flex items-center gap-2.5 font-display text-lg font-extrabold"><x-icon name="map-pin" class="size-5 text-puzzle-green" /> Mağazamız</h2>
                            @if ($s['address'])<p class="mt-2 text-sm leading-relaxed text-slate-700">{{ $s['address'] }}</p>@endif
                            @if ($directions)
                                <a href="{{ $directions }}" target="_blank" rel="noopener" class="mt-4 inline-flex h-11 items-center gap-2 rounded-xl bg-navy px-5 text-sm font-semibold text-white transition hover:bg-puzzle-orange"><x-icon name="navigation" class="size-4" /> Yol tarifi al</a>
                            @endif
                        </div>
                    </section>
                @endif

                <section class="{{ $card }}">
                    <h2 class="flex items-center gap-2.5 font-display text-lg font-extrabold"><x-icon name="clock" class="size-5 text-puzzle-orange" /> Çalışma saatleri</h2>
                    <dl class="mt-4 divide-y divide-line text-sm">
                        @foreach ($hours as $row)
                            <div class="flex justify-between gap-3 py-2.5"><dt class="text-slate-700">{{ $row['days'] }}</dt><dd @class(['font-semibold whitespace-nowrap tabular-nums', 'text-danger' => $row['closed']])>{{ $row['hours'] }}</dd></div>
                        @endforeach
                    </dl>
                    @if ($c['hours_note'])<p class="mt-3 rounded-xl bg-surface px-3 py-2 text-xs text-muted">{{ $c['hours_note'] }}</p>@endif
                </section>

                @if ($socials)
                    <section class="{{ $card }}">
                        <h2 class="font-display text-lg font-extrabold">Bizi takip edin</h2>
                        <div class="mt-4 flex flex-wrap gap-2.5">
                            @foreach ($socials as $social)
                                <a href="{{ $social['url'] }}" target="_blank" rel="noopener" aria-label="{{ $social['label'] }}" title="{{ $social['label'] }}"
                                    class="flex size-11 items-center justify-center rounded-xl border border-line text-ink/70 transition hover:border-navy hover:bg-navy hover:text-white"><x-icon :name="$social['key']" class="size-5" /></a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($s['company_title'])
                    <section class="rounded-3xl bg-surface p-6 text-sm">
                        <h2 class="flex items-center gap-2 font-semibold"><x-icon name="building" class="size-4 text-muted" /> Satıcı bilgileri</h2>
                        <dl class="mt-3 space-y-1.5 text-slate-700">
                            <div><dt class="inline text-muted">Unvan:</dt> <dd class="inline">{{ $s['company_title'] }}</dd></div>
                            @if ($s['company_tax_office'] || $s['company_tax_number'])<div><dt class="inline text-muted">Vergi dairesi / no:</dt> <dd class="inline">{{ $s['company_tax_office'] }} / {{ $s['company_tax_number'] }}</dd></div>@endif
                            @if ($s['company_mersis'])<div><dt class="inline text-muted">MERSİS:</dt> <dd class="inline">{{ $s['company_mersis'] }}</dd></div>@endif
                        </dl>
                    </section>
                @endif
            </div>
        </div>
    </div>
</x-layouts.store>
