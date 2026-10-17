{{-- Slayt formu (yeni veya düzenleme). $banner: ?HomeBanner, $groups, $targets --}}
@php
    use App\Domain\Storefront\BannerMedia;
    use App\Models\HomeBanner;

    $editing = $banner !== null;
    $state = [
        'preview' => $editing ? route('business.storefront.banners.media', $banner) : null,
        'isVideo' => $editing && $banner->isVideo(),
        'title' => old('title', $banner?->title ?? ''),
        'subtitle' => old('subtitle', $banner?->subtitle ?? ''),
        'button' => old('button_label', $banner?->button_label ?? ''),
        'position' => old('text_position', $banner?->text_position ?? 'left'),
        'linkType' => old('link_type', $banner?->link_type ?? 'none'),
        'fileName' => '',
    ];
    $id = $editing ? 'b'.$banner->id : 'new';
@endphp
<form method="POST" enctype="multipart/form-data" data-upload-progress class="space-y-5"
    action="{{ $editing ? route('business.storefront.banners.update', $banner) : route('business.storefront.banners.store') }}"
    x-data="{{ Js::from($state) }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    {{-- Canlı önizleme: bayinin göreceği oran (masaüstü 3:1), kırpma yok --}}
    <div>
        <div class="relative aspect-[3/1] overflow-hidden rounded-xl bg-slate-900 ring-1 ring-line">
            <template x-if="preview && !isVideo">
                <div class="absolute inset-0">
                    <img :src="preview" alt="" class="absolute inset-0 size-full scale-125 object-cover opacity-70 blur-xl">
                    <img :src="preview" alt="" class="relative size-full object-contain">
                </div>
            </template>
            <template x-if="preview && isVideo">
                <video :src="preview" muted autoplay loop playsinline class="absolute inset-0 size-full object-contain"></video>
            </template>
            <template x-if="!preview">
                <div class="absolute inset-0 flex flex-col items-center justify-center gap-1 text-slate-400">
                    <x-icon name="image" class="size-8" />
                    <span class="text-xs">Önizleme dosya seçince görünür</span>
                </div>
            </template>
            <div x-show="title || subtitle" class="absolute inset-0 flex px-5"
                :class="{ 'justify-start text-left bg-linear-to-r from-slate-950/80 via-slate-950/30 to-transparent': position === 'left', 'justify-center text-center bg-slate-950/45': position === 'center', 'justify-end text-right bg-linear-to-l from-slate-950/80 via-slate-950/30 to-transparent': position === 'right' }">
                <div class="flex max-w-[60%] flex-col justify-center gap-1 text-white" :class="{ 'items-start': position === 'left', 'items-center': position === 'center', 'items-end': position === 'right' }">
                    <p class="text-sm leading-tight font-bold sm:text-lg" x-text="title"></p>
                    <p class="hidden text-[11px] text-white/80 sm:block" x-text="subtitle"></p>
                    <span x-show="button && linkType !== 'none'" class="mt-1 rounded-full bg-white px-2.5 py-1 text-[10px] font-semibold text-ink" x-text="button + ' →'"></span>
                </div>
            </div>
        </div>
        <p class="mt-1.5 text-[11px] text-muted">Bayinin masaüstünde gördüğü oran. Görsel hiçbir zaman kırpılmaz; kenarlarda kalan alan görselin bulanık hâliyle dolar.</p>
    </div>

    {{-- Dosya --}}
    <div>
        <label for="media_{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink">Görsel veya video @unless ($editing)<span class="text-danger">*</span>@endunless</label>
        <label for="media_{{ $id }}" class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed border-line bg-slate-50 px-4 py-3 transition hover:border-primary/50 hover:bg-primary-soft/40">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white text-primary shadow-xs"><x-icon name="upload" class="size-5" /></span>
            <span class="min-w-0">
                <span class="block truncate text-sm font-medium text-ink" x-text="fileName || '{{ $editing ? 'Değiştirmek için yeni dosya seçin' : 'Dosya seçin' }}'"></span>
                <span class="block text-xs text-muted">Görsel: JPG, PNG, WEBP (en fazla 15 MB) · Video: MP4, WEBM (en fazla 50 MB)</span>
            </span>
        </label>
        <input id="media_{{ $id }}" type="file" name="media" class="sr-only" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"
            @change="const f = $event.target.files[0]; if (f) { fileName = f.name; isVideo = f.type.startsWith('video/'); preview = URL.createObjectURL(f); }">
        <p class="mt-1.5 text-xs text-muted">Önerilen: 1920×640 piksel (3:1). Videolar sessiz ve döngüde oynar; 10–20 saniyelik kısa videolar en iyi sonucu verir.</p>
    </div>

    {{-- Metin --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label for="title_{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink">Başlık <span class="font-normal text-muted">(isteğe bağlı)</span></label>
            <input id="title_{{ $id }}" name="title" maxlength="120" x-model="title" class="field-input" placeholder="Örn. Okul sezonu kampanyası başladı">
        </div>
        <div class="sm:col-span-2">
            <label for="subtitle_{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink">Alt başlık <span class="font-normal text-muted">(isteğe bağlı)</span></label>
            <input id="subtitle_{{ $id }}" name="subtitle" maxlength="240" x-model="subtitle" class="field-input" placeholder="Örn. Defterlerde %10 indirim ay sonuna kadar">
        </div>
        <div class="sm:col-span-2">
            <span class="mb-1.5 block text-sm font-medium text-ink">Metin konumu</span>
            <div class="inline-flex rounded-lg border border-line bg-white p-0.5" role="radiogroup">
                @foreach (HomeBanner::TEXT_POSITIONS as $value => $label)
                    <label class="cursor-pointer rounded-md px-3 py-1.5 text-sm transition" :class="position === '{{ $value }}' ? 'bg-primary text-white' : 'text-muted hover:text-ink'">
                        <input type="radio" name="text_position" value="{{ $value }}" x-model="position" class="sr-only"> {{ $label }}
                    </label>
                @endforeach
            </div>
            <p class="mt-1.5 text-xs text-muted">Görselinizde zaten yazı varsa başlık ve alt başlığı boş bırakın.</p>
        </div>
    </div>

    {{-- Bağlantı --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="link_type_{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink">Tıklanınca</label>
            <select id="link_type_{{ $id }}" name="link_type" x-model="linkType" class="field-input">
                @foreach (HomeBanner::LINK_TYPES as $value => $label)
                    <option value="{{ $value }}">{{ $value === 'none' ? 'Hiçbir yere gitmesin' : $label.' sayfası açılsın' }}</option>
                @endforeach
            </select>
        </div>
        @foreach (['category' => 'Kategori', 'brand' => 'Marka', 'product' => 'Ürün', 'showcase' => 'Vitrin'] as $type => $label)
            <div x-show="linkType === '{{ $type }}'" x-cloak>
                <label for="link_{{ $type }}_{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink">{{ $label }}</label>
                <select id="link_{{ $type }}_{{ $id }}" name="link_id" :disabled="linkType !== '{{ $type }}'" class="field-input">
                    <option value="">Seçin</option>
                    @foreach ($targets[$type] as $value => $name)
                        <option value="{{ $value }}" @selected((string) old('link_id', $banner?->link_type === $type ? $banner->link_id : '') === (string) $value)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
        <div x-show="linkType !== 'none'" x-cloak class="sm:col-span-2">
            <label for="button_{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink">Düğme yazısı <span class="font-normal text-muted">(isteğe bağlı)</span></label>
            <input id="button_{{ $id }}" name="button_label" maxlength="40" x-model="button" class="field-input" placeholder="Örn. Kampanyayı incele">
            <p class="mt-1.5 text-xs text-muted">Düğme yazısı yoksa slaytın tamamı tıklanabilir olur.</p>
        </div>
    </div>

    {{-- Ek görseller --}}
    <details class="group rounded-xl border border-line" @if ($editing && ($banner->mobile_media_path || $banner->poster_path)) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-medium text-ink">
            Mobil görsel ve video kapağı <span class="text-xs font-normal text-muted">isteğe bağlı</span>
        </summary>
        <div class="space-y-4 border-t border-line px-4 py-4">
            <div>
                <label for="mobile_{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink">Mobil görsel</label>
                <input id="mobile_{{ $id }}" type="file" name="mobile_media" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium">
                <p class="mt-1 text-xs text-muted">Telefonda bu görsel gösterilir. Önerilen: 1080×810 (4:3). Boş bırakılırsa masaüstü görseli kullanılır.</p>
                @if ($editing && $banner->mobile_media_path)
                    <label class="mt-2 flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="remove_mobile" value="1" class="size-3.5 rounded"> Mevcut mobil görseli kaldır</label>
                @endif
            </div>
            <div>
                <label for="poster_{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink">Video kapak görseli</label>
                <input id="poster_{{ $id }}" type="file" name="poster" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium">
                <p class="mt-1 text-xs text-muted">Video yüklenirken ve yavaş bağlantılarda gösterilir. Yalnızca video slaytlarında kullanılır.</p>
                @if ($editing && $banner->poster_path)
                    <label class="mt-2 flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="remove_poster" value="1" class="size-3.5 rounded"> Mevcut kapak görselini kaldır</label>
                @endif
            </div>
        </div>
    </details>

    {{-- Yayın --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="starts_on" :label="'Başlangıç'" type="date" :value="$banner?->starts_on?->toDateString()" hint="Boşsa hemen yayında." />
        <x-field name="ends_on" :label="'Bitiş'" type="date" :value="$banner?->ends_on?->toDateString()" hint="Boşsa süresiz." />
        <x-field name="dealer_group_id" label="Kimlere gösterilsin" :options="['' => 'Tüm bayiler'] + $groups->all()" :value="$banner?->dealer_group_id" class="sm:col-span-2" />
    </div>
    <x-toggle name="is_active" label="Yayında" :checked="$banner?->is_active ?? true" hint="Kapalı slaytlar bayilere gösterilmez." />

    {{-- Yükleme ilerlemesi ve hatalar --}}
    <div data-progress hidden class="rounded-xl bg-slate-50 p-3 ring-1 ring-line">
        <div class="h-2 overflow-hidden rounded-full bg-slate-200"><div data-progress-bar class="h-full w-0 rounded-full bg-primary transition-[width] duration-200"></div></div>
        <p data-progress-label class="mt-2 text-xs font-medium text-muted">Yükleniyor…</p>
    </div>
    <p data-upload-errors hidden role="alert" class="rounded-lg border border-red-200 bg-danger-soft px-3 py-2 text-sm text-red-900"></p>
    @if ($errors->any() && old('_banner') === $id)
        <p role="alert" class="rounded-lg border border-red-200 bg-danger-soft px-3 py-2 text-sm text-red-900">{{ $errors->first() }}</p>
    @endif
    <input type="hidden" name="_banner" value="{{ $id }}">

    <x-button class="w-full" :icon="$editing ? 'check' : 'plus'">{{ $editing ? 'Değişiklikleri kaydet' : 'Slaytı ekle' }}</x-button>
</form>
