{{-- Vitrin formu. $showcase: ?Showcase, $targets --}}
@php use App\Models\Showcase; @endphp
<form method="POST" action="{{ $showcase ? route('business.storefront.showcases.update', $showcase) : route('business.storefront.showcases.store') }}" class="space-y-4"
    x-data="{ type: '{{ old('type', $showcase?->type ?? 'manual') }}' }">
    @csrf
    @if ($showcase) @method('PUT') @endif
    <x-field name="name" label="Vitrin adı" required maxlength="60" :value="$showcase?->name" placeholder="Örn. Önerdiğimiz ürünler" hint="Ana sayfada sekme başlığı olarak görünür." />
    <div>
        <label class="mb-1.5 block text-sm font-medium text-ink">Ürünler nereden gelsin</label>
        <select name="type" x-model="type" class="field-input">
            @foreach (Showcase::TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
        </select>
        <p class="mt-1.5 text-xs text-muted" x-show="type === 'manual'">Ürünleri vitrini kaydettikten sonra tek tek seçersiniz.</p>
        <p class="mt-1.5 text-xs text-muted" x-show="type === 'best_sellers'" x-cloak>Son 90 günde en çok onaylanan ürünler; henüz satış yoksa en yeni ürünler.</p>
    </div>
    @foreach (['category' => 'Kategori', 'brand' => 'Marka'] as $type => $label)
        <div x-show="type === '{{ $type }}'" x-cloak>
            <label class="mb-1.5 block text-sm font-medium text-ink">{{ $label }}</label>
            <select name="source_id" :disabled="type !== '{{ $type }}'" class="field-input">
                <option value="">Seçin</option>
                @foreach ($targets[$type] as $value => $name)
                    <option value="{{ $value }}" @selected((string) old('source_id', $showcase?->type === $type ? $showcase->source_id : '') === (string) $value)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
    @endforeach
    <x-field name="product_limit" label="Gösterilecek ürün sayısı" :options="[4 => '4 ürün (1 satır)', 8 => '8 ürün (2 satır)', 12 => '12 ürün (3 satır)']" :value="$showcase?->product_limit ?? 8" />
    <x-toggle name="is_active" label="Yayında" :checked="$showcase?->is_active ?? true" />
    @error('showcase')<p class="text-sm text-danger">{{ $message }}</p>@enderror
    <x-button class="w-full" :icon="$showcase ? 'check' : 'plus'">{{ $showcase ? 'Değişiklikleri kaydet' : 'Vitrini oluştur' }}</x-button>
</form>
