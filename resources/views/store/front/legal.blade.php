{{-- Yasal metin: lacivert başlık, solda tüm yasal metinler ve içindekiler, sağda belge. Metin Ayarlar → Yasal Metinler'den. --}}
@php
    use App\Domain\Store\Content\HelpPages;
    use App\Domain\Store\Orders\LegalDocuments;
    use App\Models\Store\StoreLegalDocument;

    $forOrder = in_array($document->key, StoreLegalDocument::CHECKOUT_KEYS, true);
    [$body, $toc] = LegalDocuments::withAnchors($html);
    $icons = ['on-bilgilendirme' => 'file', 'mesafeli-satis' => 'file', 'iptal-iade' => 'refresh', 'kvkk' => 'shield', 'gizlilik-cerez' => 'lock'];
    $updated = $document->created_at->timezone('Europe/Istanbul')->translatedFormat('j F Y');
    $hubLive = HelpPages::isPublished('legal');
@endphp
<x-layouts.store :title="$document->title" :noindex="$forOrder">
    <section class="relative overflow-hidden bg-[#0b1631] text-white print:bg-white print:text-ink">
        <div class="pointer-events-none absolute -right-10 -bottom-14 hidden rotate-12 grid-cols-2 gap-3 opacity-90 md:grid print:hidden" aria-hidden="true">
            <span class="size-20 rounded-3xl bg-puzzle-orange"></span><span class="size-20 rounded-3xl bg-puzzle-green"></span>
            <span class="size-20 rounded-3xl bg-puzzle-blue"></span><span class="size-20 rounded-3xl bg-puzzle-yellow"></span>
        </div>
        <div class="relative mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
            <nav class="text-sm text-white/60 print:hidden" aria-label="Konum">
                <a href="{{ route('store.home') }}" class="hover:text-white">Ana sayfa</a> <span class="mx-1">›</span>
                @if ($hubLive)<a href="{{ route('store.help.legal') }}" class="hover:text-white">Yasal Haklar ve Sözleşmeler</a> <span class="mx-1">›</span>@endif
                {{ $document->title }}
            </nav>
            <div class="mt-6 flex items-start gap-4">
                <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-amber-400 ring-1 ring-white/15 print:hidden"><x-icon :name="$icons[$document->key] ?? 'file'" class="size-7" /></span>
                <div>
                    <h1 class="font-display text-3xl leading-tight font-extrabold tracking-tight sm:text-4xl">{{ $document->title }}</h1>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold">
                        <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/15">Sürüm {{ $document->version }}</span>
                        <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/15">Son güncelleme: {{ $updated }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[17rem_minmax(0,1fr)] lg:px-8">
        <aside class="order-last min-w-0 space-y-5 lg:sticky lg:top-28 lg:order-first lg:self-start print:hidden">
            @if (count($toc) > 1)
                <nav class="hidden rounded-3xl border border-line bg-white p-5 lg:block" aria-label="İçindekiler">
                    <p class="text-xs font-bold tracking-[0.18em] text-muted uppercase">İçindekiler</p>
                    <ol class="mt-3 space-y-1.5 text-sm">
                        @foreach ($toc as $item)
                            <li><a href="#{{ $item['id'] }}" class="flex gap-2 rounded-lg px-2 py-1.5 text-ink/75 transition hover:bg-surface hover:text-ink"><span class="w-5 shrink-0 text-xs font-bold text-muted tabular-nums">{{ $loop->iteration }}</span><span>{{ $item['title'] }}</span></a></li>
                        @endforeach
                    </ol>
                </nav>
            @endif
            <nav class="rounded-3xl border border-line bg-white p-2" aria-label="Yasal metinler">
                <p class="px-3 pt-3 pb-1 text-xs font-bold tracking-[0.18em] text-muted uppercase">Yasal metinler</p>
                @foreach (StoreLegalDocument::KEYS as $key => $title)
                    <a href="{{ route('store.legal', $key) }}" @class(['flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold transition',
                        'bg-navy text-white' => $key === $document->key, 'text-ink/75 hover:bg-surface hover:text-ink' => $key !== $document->key])
                        @if ($key === $document->key) aria-current="page" @endif>
                        <x-icon :name="$icons[$key] ?? 'file'" class="size-4 shrink-0" /> {{ $title }}
                    </a>
                @endforeach
            </nav>
            <div class="rounded-3xl bg-surface p-5 text-sm">
                <p class="font-display font-extrabold">Sorunuz mu var?</p>
                <p class="mt-1 text-muted">Metinlerle ilgili sorularınız için bize ulaşın.</p>
                <a href="{{ route('store.contact') }}" class="mt-3 inline-flex items-center gap-1.5 font-semibold text-navy-ink hover:underline">İletişim <x-icon name="arrow-right" class="size-4" /></a>
            </div>
        </aside>

        <article class="min-w-0 rounded-3xl border border-line bg-white p-6 sm:p-10 print:border-0 print:p-0">
            @if ($forOrder)
                <div class="mb-8 flex items-start gap-3 rounded-2xl bg-puzzle-blue-soft/60 px-5 py-4 text-sm text-slate-700 print:hidden">
                    <x-icon name="alert" class="mt-0.5 size-5 shrink-0 text-puzzle-blue" />
                    <p>Parantez içindeki alanlar siparişinizi onayladığınızda sizin bilgilerinizle doldurulur ve onayladığınız metin siparişinizde saklanır; ayrıca sipariş e-postanızla gönderilir.</p>
                </div>
            @endif
            <div class="space-y-4 text-[15.5px] leading-[1.8] text-slate-700
                [&_h2]:mt-10 [&_h2]:scroll-mt-32 [&_h2]:border-b [&_h2]:border-line [&_h2]:pb-3 [&_h2]:font-display [&_h2]:text-xl [&_h2]:font-extrabold [&_h2]:text-ink [&_h2:first-child]:mt-0
                [&_h3]:mt-6 [&_h3]:font-bold [&_h3]:text-ink [&_li]:ml-5 [&_li]:pl-1 [&_ol]:list-decimal [&_ol]:space-y-1.5 [&_ul]:list-disc [&_ul]:space-y-1.5 [&_li::marker]:text-puzzle-orange
                [&_strong]:text-ink [&_a]:font-semibold [&_a]:text-navy-ink [&_a]:underline
                [&_table]:w-full [&_table]:overflow-hidden [&_table]:rounded-xl [&_table]:text-sm [&_thead]:bg-surface [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:font-semibold [&_th]:text-ink
                [&_td]:border-t [&_td]:border-line [&_td]:px-3 [&_td]:py-2.5 [&_code]:rounded [&_code]:bg-surface [&_code]:px-1.5 [&_code]:text-xs">{{ $body }}</div>

            <div class="mt-10 flex flex-wrap items-center gap-3 border-t border-line pt-6 print:hidden">
                <button type="button" x-data="printPage" @click="print()" class="inline-flex h-11 items-center gap-2 rounded-xl border border-line px-4 text-sm font-semibold transition hover:bg-surface"><x-icon name="file" class="size-4" /> Yazdır / PDF olarak kaydet</button>
                @if ($document->key === 'gizlilik-cerez')
                    <button type="button" data-cookie-settings class="inline-flex h-11 items-center gap-2 rounded-xl bg-navy px-4 text-sm font-semibold text-white transition hover:bg-puzzle-orange"><x-icon name="settings" class="size-4" /> Çerez tercihlerimi değiştir</button>
                @endif
            </div>
        </article>
    </div>
</x-layouts.store>
