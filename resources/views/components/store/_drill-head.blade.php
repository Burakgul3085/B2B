{{-- Mobil kategori menüsünde alt ekran başlığı: geri, kategori adı, tümünü gör. --}}
<div class="sticky top-0 z-10 border-b border-line bg-white px-2 pt-3 pb-3">
    <button type="button" @click="back" class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-sm font-semibold text-muted active:bg-surface">
        <x-icon name="chevron-down" class="size-4 rotate-90" /> {{ $back }}
    </button>
    <div class="mt-1 flex items-center justify-between gap-3 px-2">
        <p class="min-w-0 truncate font-display text-lg font-extrabold">{{ $title }}</p>
        <a href="{{ $url }}" class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold text-white {{ $tone['solid'] }}">Tümünü gör</a>
    </div>
</div>
