{{-- Kampanya kapağı: yüklenen fotoğraf, yoksa yapboz renklerinde hazır kapak. $promotion (cover yüklü), $width --}}
@if ($promotion->cover)
    <img src="{{ route('dealer.promotions.image', ['promotionImage' => $promotion->cover, 'w' => $width ?? 640]) }}" alt="{{ $promotion->name }}" loading="lazy"
        class="size-full object-cover transition duration-500 group-hover:scale-105">
    <span class="absolute bottom-3 left-3 rounded-xl bg-red-600 px-3 py-1.5 text-lg font-extrabold text-white shadow-lg">{{ $promotion->badge() }}</span>
@else
    <div class="relative flex size-full items-center justify-center overflow-hidden bg-gradient-to-br from-amber-300 via-orange-400 to-rose-500">
        <span class="absolute -top-8 -left-8 size-32 rounded-3xl bg-white/15 rotate-12"></span>
        <span class="absolute -right-6 -bottom-10 size-36 rounded-full bg-sky-400/30"></span>
        <span class="absolute top-4 right-16 size-10 rounded-lg bg-emerald-300/40 -rotate-12"></span>
        <div class="relative text-center text-white drop-shadow">
            <p class="text-4xl font-extrabold tracking-tight sm:text-5xl">{{ $promotion->badge() }}</p>
            <p class="mt-1 text-sm font-bold tracking-[0.2em] uppercase">indirim</p>
        </div>
    </div>
@endif
