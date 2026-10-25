{{-- Fotoğraf büyük görünümü; photoGallery bileşeninin içinde kullanılır (Hakkımızda bandı ve galeri sayfası). --}}
<div x-show="index !== null" x-cloak x-transition.opacity class="lightbox fixed inset-0 z-[70] flex items-center justify-center bg-[#0b1631]/95 p-4" @click.self="close()" role="dialog" aria-modal="true" aria-label="Fotoğraf">
    <button type="button" @click="close()" class="absolute top-4 right-4 flex size-11 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="Kapat"><x-icon name="x" class="size-6" /></button>
    <button type="button" @click="prev()" x-show="photos.length > 1" class="absolute left-3 flex size-12 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 sm:left-6" aria-label="Önceki"><x-icon name="arrow-right" class="size-6 rotate-180" /></button>
    <figure class="max-h-full max-w-5xl">
        <img :src="current.src" :alt="current.caption || ''" class="max-h-[80vh] w-auto rounded-2xl object-contain shadow-2xl">
        <figcaption class="mt-4 flex items-center justify-between gap-4 text-sm text-white/80">
            <span x-text="current.caption || ''"></span>
            <span class="tabular-nums text-white/50" x-text="counter"></span>
        </figcaption>
    </figure>
    <button type="button" @click="next()" x-show="photos.length > 1" class="absolute right-3 flex size-12 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 sm:right-6" aria-label="Sonraki"><x-icon name="arrow-right" class="size-6" /></button>
</div>
