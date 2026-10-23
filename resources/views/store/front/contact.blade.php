@php
    $c = $content;
    $s = $settings;
    $whatsapp = $s['whatsapp'] ? preg_replace('/\D/', '', $s['whatsapp']) : null;
    if ($whatsapp && str_starts_with($whatsapp, '0')) {
        $whatsapp = '9'.$whatsapp;
    }
    $cards = array_filter([
        $s['phone'] ? ['icon' => 'phone', 'label' => 'Telefon', 'value' => $s['phone'], 'url' => 'tel:'.preg_replace('/[^\d+]/', '', $s['phone'])] : null,
        $whatsapp ? ['icon' => 'message-circle', 'label' => 'WhatsApp', 'value' => 'Mesaj gönderin', 'url' => 'https://wa.me/'.$whatsapp] : null,
        $s['email'] ? ['icon' => 'mail', 'label' => 'E-posta', 'value' => $s['email'], 'url' => 'mailto:'.$s['email']] : null,
        $s['instagram'] ? ['icon' => 'instagram', 'label' => 'Instagram', 'value' => 'Bizi takip edin', 'url' => $s['instagram']] : null,
        $s['facebook'] ? ['icon' => 'facebook', 'label' => 'Facebook', 'value' => 'Sayfamız', 'url' => $s['facebook']] : null,
    ]);
@endphp
<x-layouts.store :title="$page->seo_title ?: 'İletişim'" :description="$page->seo_description ?: $c['intro']" :canonical="route('store.contact')"
    :noindex="$adminPreview" :jsonLd="$jsonLd">
    @if ($adminPreview)
        <div class="bg-amber-400 px-4 py-2 text-center text-sm font-medium text-slate-900">Önizleme: kaydedilmiş içerik{{ $page->is_published ? '' : ' — sayfa henüz yayında değil' }}.</div>
    @endif

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        @include('store.front._breadcrumbs', ['items' => [['İletişim', null]]])
        <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">İletişim</h1>
        @if ($c['intro'])<p class="mt-3 max-w-2xl text-muted">{{ $c['intro'] }}</p>@endif

        @if ($cards)
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cards as $card)
                    <a href="{{ $card['url'] }}" @if (str_starts_with($card['url'], 'http')) target="_blank" rel="noopener" @endif
                        class="flex items-center gap-4 rounded-2xl border border-line p-5 transition hover:border-primary">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon :name="$card['icon']" class="size-5" /></span>
                        <span class="min-w-0"><span class="block text-sm text-muted">{{ $card['label'] }}</span><span class="block truncate font-semibold">{{ $card['value'] }}</span></span>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="mt-10 grid gap-8 lg:grid-cols-5">
            <div class="space-y-6 lg:col-span-2">
                @if ($s['address'])
                    <section class="rounded-2xl border border-line p-6">
                        <h2 class="flex items-center gap-2 font-semibold"><x-icon name="map-pin" class="size-5 text-primary" /> Adres</h2>
                        <p class="mt-3 text-sm leading-relaxed">{{ $s['address'] }}</p>
                        @if ($directions)
                            <a href="{{ $directions }}" target="_blank" rel="noopener" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover"><x-icon name="navigation" class="size-4" /> Yol tarifi al</a>
                        @endif
                    </section>
                @endif

                <section class="rounded-2xl border border-line p-6">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="flex items-center gap-2 font-semibold"><x-icon name="clock" class="size-5 text-primary" /> Çalışma saatleri</h2>
                        @if ($openNow !== null)
                            <x-badge :tone="$openNow ? 'success' : 'danger'">{{ $openNow ? 'Şu an açık' : 'Şu an kapalı' }}</x-badge>
                        @endif
                    </div>
                    <dl class="mt-4 space-y-2 text-sm">
                        @foreach ($hours as $row)
                            <div class="flex justify-between gap-3"><dt>{{ $row['days'] }}</dt><dd @class(['tabular-nums', 'text-muted' => $row['closed']])>{{ $row['hours'] }}</dd></div>
                        @endforeach
                    </dl>
                    @if ($c['hours_note'])<p class="mt-3 text-xs text-muted">{{ $c['hours_note'] }}</p>@endif
                </section>

                @if ($s['company_title'])
                    <section class="rounded-2xl bg-surface p-6 text-sm">
                        <h2 class="font-semibold">Satıcı bilgileri</h2>
                        <dl class="mt-3 space-y-1.5 text-slate-700">
                            <div><dt class="inline text-muted">Unvan:</dt> <dd class="inline">{{ $s['company_title'] }}</dd></div>
                            @if ($s['company_tax_office'] || $s['company_tax_number'])<div><dt class="inline text-muted">Vergi dairesi / no:</dt> <dd class="inline">{{ $s['company_tax_office'] }} / {{ $s['company_tax_number'] }}</dd></div>@endif
                            @if ($s['company_mersis'])<div><dt class="inline text-muted">MERSİS:</dt> <dd class="inline">{{ $s['company_mersis'] }}</dd></div>@endif
                        </dl>
                    </section>
                @endif
            </div>

            <div class="space-y-8 lg:col-span-3">
                @if ($c['map_embed'])
                    <iframe src="{{ $c['map_embed'] }}" class="h-80 w-full rounded-2xl border border-line sm:h-96" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="{{ config('store.name') }} konumu"></iframe>
                @endif

                @if ($c['form_enabled'])
                    <section id="form" class="scroll-mt-32 rounded-2xl border border-line p-6">
                        <h2 class="text-lg font-semibold">Bize yazın</h2>
                        @if (session('contact_sent'))
                            <p role="status" class="mt-4 rounded-xl border border-green-200 bg-success-soft px-4 py-3 text-sm text-green-900">Mesajınız bize ulaştı, teşekkür ederiz. En kısa sürede size dönüş yapacağız.</p>
                        @else
                            <form method="POST" action="{{ $adminPreview ? '#' : route('store.contact.send') }}" class="mt-4 space-y-4">
                                @csrf
                                <x-store.bot-trap />
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-field name="name" label="Ad soyad" required maxlength="80" autocomplete="name" class="sm:col-span-2" />
                                    <x-field name="email" label="E-posta" type="email" maxlength="255" autocomplete="email" />
                                    <x-field name="phone" label="Telefon" type="tel" maxlength="30" autocomplete="tel" />
                                </div>
                                <p class="-mt-2 text-xs text-muted">Size dönebilmemiz için e-posta veya telefonunuzdan en az birini yazın.</p>
                                <x-field name="subject" label="Konu" :options="['' => 'Seçin'] + $subjects" required />
                                <x-field name="message" label="Mesajınız" textarea rows="5" required minlength="10" maxlength="2000" />
                                <p class="text-xs text-muted">Gönderdiğiniz bilgiler yalnızca talebinizi yanıtlamak için kullanılır. Ayrıntılar: <a href="{{ route('store.legal', 'kvkk') }}" class="text-primary underline">KVKK Aydınlatma Metni</a>.</p>
                                <button class="rounded-xl bg-primary px-6 py-3 font-semibold text-white transition hover:bg-primary-hover" @disabled($adminPreview)>Gönder</button>
                            </form>
                        @endif
                    </section>
                @endif
            </div>
        </div>
    </div>
</x-layouts.store>
