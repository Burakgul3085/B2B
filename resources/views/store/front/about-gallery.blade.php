{{-- Hakkımızda galerisinin tamamı: taş duvar (masonry) dizilim, her fotoğraf kendi oranında, kırpılmadan. --}}
@php
    $name = config('store.name');
    $count = count($photos);
    $cols = match (true) { $count === 1 => 'mx-auto max-w-2xl columns-1', $count === 2 => 'columns-2', $count === 3 => 'columns-2 md:columns-3', default => 'columns-2 md:columns-3 lg:columns-4' };
@endphp
<x-layouts.store :title="$content['gallery_title'].' · Hakkımızda'" :description="$name.' mağazasından fotoğraflar.'" :canonical="route('store.about.gallery')"
    :ogImage="$photos[0]['thumb'] ?? null">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8" x-data="photoGallery(@alpine($photos))" @keydown.window.escape="close()" @keydown.window.arrow-right="next()" @keydown.window.arrow-left="prev()">
        @include('store.front._breadcrumbs', ['items' => [['Hakkımızda', route('store.about')], ['Galeri', null]]])

        <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="flex items-center gap-2 text-xs font-bold tracking-[0.2em] text-puzzle-orange uppercase">
                    <span class="inline-flex gap-0.5" aria-hidden="true"><span class="size-2 rounded-[3px] bg-puzzle-orange"></span><span class="size-2 rounded-[3px] bg-puzzle-green"></span><span class="size-2 rounded-[3px] bg-puzzle-blue"></span><span class="size-2 rounded-[3px] bg-puzzle-yellow"></span></span>
                    Galeri
                </p>
                <h1 class="mt-2 font-display text-3xl font-extrabold tracking-tight sm:text-5xl">{{ $content['gallery_title'] }}</h1>
                <p class="mt-2 text-muted">{{ $count }} fotoğraf · büyütmek için fotoğrafa dokunun</p>
            </div>
            <a href="{{ route('store.about') }}" class="inline-flex h-11 items-center gap-2 rounded-xl border border-line bg-white px-5 text-sm font-semibold transition hover:border-navy hover:bg-navy hover:text-white">
                <x-icon name="arrow-right" class="size-4 rotate-180" /> Hakkımızda
            </a>
        </div>

        <div class="mt-10 gap-3 md:gap-4 {{ $cols }}">
            @foreach ($photos as $i => $photo)
                <button type="button" @click="open({{ $i }})" class="group relative mb-3 block w-full break-inside-avoid overflow-hidden rounded-2xl focus:ring-4 focus:ring-amber-400/50 focus:outline-none md:mb-4"
                    aria-label="Fotoğrafı büyüt{{ $photo['caption'] ? ': '.$photo['caption'] : '' }}">
                    <x-store.fit-image :src="$photo['thumb']" :alt="$photo['caption'] ?? $name" :ratio="$photo['ratio']" natural :loading="$i < 8 ? 'eager' : 'lazy'" />
                    <span class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 transition group-hover:opacity-100"></span>
                    @if ($photo['caption'])<span class="absolute inset-x-0 bottom-0 p-4 text-left text-sm font-semibold text-white opacity-0 transition group-hover:opacity-100">{{ $photo['caption'] }}</span>@endif
                </button>
            @endforeach
        </div>

        @include('store.front._photo-lightbox')
    </div>
</x-layouts.store>
