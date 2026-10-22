@php $forOrder = in_array($document->key, \App\Models\Store\StoreLegalDocument::CHECKOUT_KEYS, true); @endphp
<x-layouts.store :title="$document->title" :noindex="$forOrder">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $document->title }}</h1>
        <p class="mt-2 text-sm text-muted">
            @if ($forOrder)
                Sürüm {{ $document->version }}. Parantez içindeki alanlar siparişinizi onayladığınızda sizin bilgilerinizle doldurulur ve onayladığınız metin siparişinizde saklanır.
            @else
                Son güncelleme: {{ $document->created_at->timezone('Europe/Istanbul')->format('d.m.Y') }}
            @endif
        </p>
        <div class="mt-6 space-y-4 text-[15px] leading-relaxed text-slate-700 [&_h2]:mt-8 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-ink [&_li]:ml-5 [&_ol]:list-decimal [&_strong]:text-ink [&_ul]:list-disc
            [&_table]:w-full [&_table]:text-sm [&_td]:border-b [&_td]:border-line [&_td]:py-2 [&_td]:pr-3 [&_th]:border-b [&_th]:border-line [&_th]:py-2 [&_th]:pr-3 [&_th]:text-left [&_code]:text-xs">{{ $html }}</div>
        @if ($document->key === 'gizlilik-cerez')
            <button type="button" data-cookie-settings class="mt-8 rounded-lg border border-line px-4 py-2 text-sm font-medium hover:bg-slate-50">Çerez tercihlerimi değiştir</button>
        @endif
    </div>
</x-layouts.store>
