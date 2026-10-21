<x-layouts.store :title="$document->title" :noindex="true">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $document->title }}</h1>
        <p class="mt-2 text-sm text-muted">Sürüm {{ $document->version }}. Parantez içindeki alanlar siparişinizi onayladığınızda sizin bilgilerinizle doldurulur ve onayladığınız metin siparişinizde saklanır.</p>
        <div class="mt-6 space-y-4 text-[15px] leading-relaxed text-slate-700 [&_h2]:mt-8 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-ink [&_li]:ml-5 [&_ol]:list-decimal [&_strong]:text-ink [&_ul]:list-disc">{{ $html }}</div>
    </div>
</x-layouts.store>
