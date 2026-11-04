{{-- Yeni ürün formunda görseller: seçilenler önizlenir, ilk görsel ana görseldir. Düzenlemede görseller ürün sayfasındadır. --}}
@php $max = \App\Domain\Store\Catalog\ProductService::MAX_IMAGES; @endphp
<x-card title="Görseller" description="İlk görsel ana görseldir. En fazla {{ $max }} görsel; JPG, PNG veya WEBP, her biri en fazla 5 MB. Beyaz kenarlar otomatik kırpılır.">
    <div x-data="imagePicker({{ $max }}, {{ 5 * 1024 * 1024 }})" class="space-y-4">
        <input type="file" name="images[]" x-ref="input" @change="pick()" multiple accept="image/jpeg,image/png,image/webp" aria-label="Ürün görselleri"
            class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200">
        <p x-show="error" x-text="error" class="text-sm text-danger" role="alert"></p>
        @foreach (['images', 'images.*'] as $key)
            @error($key)<p class="text-sm text-danger">{{ $message }}</p>@enderror
        @endforeach
        @if ($errors->any())
            <p class="text-xs text-muted">Formda hata olduğu için seçtiğiniz görseller tarayıcı tarafından temizlendi; lütfen yeniden seçin.</p>
        @endif
        <ul x-show="previews.length" class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
            <template x-for="(p, i) in previews" :key="p.key">
                <li class="relative rounded-xl border border-line bg-white p-1.5">
                    <img :src="p.url" :alt="p.name" class="aspect-square w-full rounded-lg object-contain">
                    <span x-show="p.primary" class="absolute top-2 left-2 rounded-md bg-primary px-1.5 py-0.5 text-[10px] font-bold text-white">Ana</span>
                    <div class="mt-1.5 flex justify-between gap-1 text-xs">
                        <button type="button" x-show="!p.primary" @click="makePrimary(i)" class="text-primary hover:underline">Ana yap</button>
                        <button type="button" @click="remove(i)" class="ml-auto text-danger hover:underline">Kaldır</button>
                    </div>
                </li>
            </template>
        </ul>
    </div>
</x-card>
