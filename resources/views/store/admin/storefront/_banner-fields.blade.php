{{-- Banner alanları: $banner (yeni banner için null) --}}
<div class="grid gap-4 {{ $banner ? 'sm:grid-cols-2' : '' }}">
    <label class="block text-sm">
        <span class="mb-1.5 block font-medium">Görsel @unless ($banner)<span class="text-danger">*</span>@endunless</span>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" @required(! $banner)
            class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium">
        <span class="mt-1 block text-xs text-muted">Büyük alan 1920×520, kampanya kutusu 900×600 piksel.@if ($banner) Değiştirmek için seçin.@endif</span>
    </label>
    <label class="block text-sm">
        <span class="mb-1.5 block font-medium">Mobil görsel (isteğe bağlı)</span>
        <input type="file" name="mobile_image" accept="image/jpeg,image/png,image/webp"
            class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium">
        <span class="mt-1 block text-xs text-muted">Yalnızca büyük alan için, 1080×1350 piksel (dikey).</span>
    </label>
    <x-field name="title" label="Başlık" :value="$banner?->title" maxlength="120" />
    <x-field name="subtitle" label="Alt başlık" :value="$banner?->subtitle" maxlength="240" />
    <x-field name="link_url" label="Bağlantı" :value="$banner?->link_url" maxlength="500" placeholder="/kategori/kalemler" hint="Mağaza içi adres (/ ile başlar) veya https:// adresi." />
    <x-field name="placement" label="Yerleşim" :options="\App\Models\Store\StoreBanner::PLACEMENTS" :value="$banner?->placement ?? 'hero'"
        hint="Büyük alan: ana sayfanın en üstünde ekranın iki yanına uzanan kayan banner (1920×520, 2 saniyede bir geçer). Kampanya kutusu: büyük alanın altındaki kutulardan biri (900×600, en fazla 4)." />
    <x-field name="button_text" label="Düğme yazısı" :value="$banner?->button_text" maxlength="40" placeholder="Hemen incele" />
    <x-field name="starts_at" label="Başlangıç (isteğe bağlı)" type="datetime-local" :value="$banner?->starts_at?->timezone('Europe/Istanbul')->format('Y-m-d\TH:i')" />
    <x-field name="ends_at" label="Bitiş (isteğe bağlı)" type="datetime-local" :value="$banner?->ends_at?->timezone('Europe/Istanbul')->format('Y-m-d\TH:i')" />
</div>
@error('image')<p class="text-sm text-danger">{{ $message }}</p>@enderror
@error('mobile_image')<p class="text-sm text-danger">{{ $message }}</p>@enderror
