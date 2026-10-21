{{--
    Varyant alanları. $prefix: alan adı öneki ("variant" → variant[sku]); $bag: hata çantası; $variant: değerler;
    $optionLabel: seçenek adı (null ise seçenek alanı gösterilmez); $useOld: eski girdiyle doldur.
--}}
@php
    $bagErrors = $errors->getBag($bag ?? 'default');
    $name = fn ($field) => $prefix ? "{$prefix}[{$field}]" : $field;
    $errKey = fn ($field) => $prefix && ($bag ?? 'default') === 'default' ? "{$prefix}.{$field}" : $field;
    $value = function ($field, $fallback) use ($prefix, $useOld) {
        if (! $useOld) {
            return $fallback;
        }

        return old($prefix ? "{$prefix}.{$field}" : $field, $fallback);
    };
    $vat = $variant?->vat_rate !== null ? (string) (int) $variant->vat_rate : '20';
    $money = fn ($v) => $v === null ? '' : number_format((float) $v, 2, ',', '');
    $inputs = [
        ['sku', 'Stok kodu (SKU)', $variant?->sku, true, 'text', 'Mağazada benzersiz olmalı.'],
        ['barcode', 'Barkod', $variant?->barcode, false, 'text', null],
        ['price', 'Fiyat (KDV dahil, ₺)', $money($variant?->price), true, 'text', null],
        ['compare_at_price', 'Üstü çizili fiyat (₺)', $money($variant?->compare_at_price), false, 'text', 'İndirim göstermek için; satış fiyatından yüksek olmalı.'],
        ['desi', 'Desi', $variant?->desi !== null ? $money($variant->desi) : '', false, 'text', 'Kargo ücreti için.'],
        ['weight_grams', 'Ağırlık (gram)', $variant?->weight_grams, false, 'number', null],
    ];
@endphp
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @if ($optionLabel)
        @php $id = 'v_'.($bag ?? $prefix).'_option_value'; @endphp
        <div>
            <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink">{{ $optionLabel }}<span class="text-danger" aria-hidden="true"> *</span></label>
            <input id="{{ $id }}" name="{{ $name('option_value') }}" value="{{ $value('option_value', $variant?->option_value) }}" maxlength="80" required class="field-input" placeholder="ör. Mavi">
            @if ($bagErrors->has($errKey('option_value')))<p class="mt-1.5 text-sm text-danger">{{ $bagErrors->first($errKey('option_value')) }}</p>@endif
        </div>
    @endif
    @foreach ($inputs as [$field, $label, $default, $required, $type, $hint])
        @php $id = 'v_'.($bag ?? $prefix).'_'.$field; @endphp
        <div>
            <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink">{{ $label }}@if ($required)<span class="text-danger" aria-hidden="true"> *</span>@endif</label>
            <input id="{{ $id }}" name="{{ $name($field) }}" type="{{ $type }}" value="{{ $value($field, $default) }}" @required($required)
                @if (in_array($field, ['price', 'compare_at_price', 'desi'])) inputmode="decimal" @endif
                @if ($type === 'number') min="0" @endif class="field-input">
            @if ($bagErrors->has($errKey($field)))
                <p class="mt-1.5 text-sm text-danger">{{ $bagErrors->first($errKey($field)) }}</p>
            @elseif ($hint)
                <p class="mt-1.5 text-xs text-muted">{{ $hint }}</p>
            @endif
        </div>
    @endforeach
    @php $id = 'v_'.($bag ?? $prefix).'_vat'; @endphp
    <div>
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink">KDV oranı<span class="text-danger" aria-hidden="true"> *</span></label>
        <select id="{{ $id }}" name="{{ $name('vat_rate') }}" class="field-input">
            @foreach (\App\Models\Store\StoreProductVariant::VAT_RATES as $rate)
                <option value="{{ $rate }}" @selected((string) $value('vat_rate', $vat) === $rate)>%{{ $rate }}</option>
            @endforeach
        </select>
        @if ($bagErrors->has($errKey('vat_rate')))<p class="mt-1.5 text-sm text-danger">{{ $bagErrors->first($errKey('vat_rate')) }}</p>@endif
    </div>
</div>
