@php use App\Domain\Inventory\Quantity; use App\Domain\Support\Money; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" :title="$product->name">
    <x-page-header :title="$product->name" :subtitle="$product->sku.($product->barcode ? ' · '.$product->barcode : '')">
        <x-slot:breadcrumb><a href="{{ route('business.products.index') }}" class="hover:underline">Ürünler</a></x-slot:breadcrumb>
        @if ($product->isArchived())
            <x-badge>Arşivde</x-badge>
            <form method="POST" action="{{ route('business.products.restore', $product) }}">@csrf<x-button variant="secondary">Arşivden çıkar</x-button></form>
        @else
            @if ($product->is_active)<x-badge tone="success">Aktif</x-badge>@else<x-badge tone="warning">Pasif</x-badge>@endif
            <x-button variant="secondary" icon="layers" :href="route('business.stock.create', ['product' => $product->id])">Stok hareketi</x-button>
            <x-button icon="pencil" :href="route('business.products.edit', $product)">Düzenle</x-button>
        @endif
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Ürün bilgileri">
                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-3">
                    @foreach ([
                        'Kategori' => $product->category?->fullName(),
                        'Marka' => $product->brand?->name,
                        'Model' => $product->model,
                        'Birim' => $product->unit->name,
                        'Koli / paket içi' => Quantity::format($product->pack_quantity),
                        'KDV' => $product->taxRate->name.($product->price_includes_tax ? ' (dahil)' : ' (hariç)'),
                        'Liste fiyatı' => Money::formatUnit($product->list_price),
                        'Bayi fiyatı' => $product->dealer_price !== null ? Money::formatUnit($product->dealer_price) : 'Liste fiyatı',
                        'Alış fiyatı' => Money::formatUnit($product->purchase_price),
                        'Minimum sipariş' => Quantity::format($product->min_order_qty).' '.$product->unit->name,
                        'Sipariş adımı' => Quantity::format($product->order_step),
                        'Kritik stok' => Quantity::format($product->critical_stock_level),
                    ] as $label => $value)
                        <div><dt class="text-muted">{{ $label }}</dt><dd class="mt-0.5 font-medium">{{ $value ?: '—' }}</dd></div>
                    @endforeach
                </dl>
                @if ($product->description)<p class="mt-5 border-t border-line pt-4 text-sm whitespace-pre-line text-slate-700">{{ $product->description }}</p>@endif
                @unless ($product->visible_to_dealers)<p class="mt-4 text-sm text-warning">Bu ürün bayi kataloğunda gösterilmiyor.</p>@endunless
            </x-card>

            <x-card title="Görseller" description="JPG, PNG veya WEBP; en fazla 5 MB. Görseller güvenlik için yeniden işlenir.">
                @if ($product->images->isEmpty())
                    <p class="text-sm text-muted">Henüz görsel yok.</p>
                @else
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ($product->images as $image)
                            <div class="group relative overflow-hidden rounded-lg border border-line">
                                <img src="{{ route('business.images.show', $image) }}" alt="{{ $product->name }}" class="aspect-square w-full object-cover" loading="lazy">
                                @if ($image->is_primary)<span class="absolute top-2 left-2 rounded bg-primary px-1.5 py-0.5 text-[11px] font-medium text-white">Ana görsel</span>@endif
                                <div class="flex justify-between border-t border-line bg-white px-2 py-1.5 text-xs">
                                    @unless ($image->is_primary)
                                        <form method="POST" action="{{ route('business.products.images.primary', [$product, $image]) }}">@csrf @method('PUT')<button class="text-primary hover:underline">Ana yap</button></form>
                                    @else<span></span>@endunless
                                    <form method="POST" action="{{ route('business.products.images.destroy', [$product, $image]) }}" data-confirm="Görsel silinsin mi?">@csrf @method('DELETE')<button class="text-danger hover:underline">Sil</button></form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                @unless ($product->isArchived())
                    <form method="POST" action="{{ route('business.products.images.store', $product) }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-3">
                        @csrf
                        <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required class="text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium">
                        <x-button variant="secondary" size="sm" icon="upload">Yükle</x-button>
                    </form>
                    @error('images')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                    @error('images.*')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                @endunless
            </x-card>

            <x-card title="Son stok hareketleri" :padding="false">
                <x-slot:actions><x-button variant="ghost" size="sm" :href="route('business.stock-movements.index', ['product' => $product->id])">Tümü</x-button></x-slot:actions>
                @include('business.inventory._movement-table', ['movements' => $movements, 'showProduct' => false])
            </x-card>

            <x-card title="Ürün geçmişi" description="Fiyat ve bilgi değişiklikleri." :padding="false">
                @if ($history->isEmpty())
                    <p class="p-5 text-sm text-muted">Kayıt yok.</p>
                @else
                    <ul class="divide-y divide-line text-sm">
                        @foreach ($history as $log)
                            <li class="px-5 py-3">
                                <div class="flex justify-between gap-3">
                                    <span class="font-medium">{{ ['product.created' => 'Oluşturuldu', 'product.updated' => 'Güncellendi', 'product.archived' => 'Arşivlendi', 'product.restored' => 'Arşivden çıkarıldı', 'product.duplicated' => 'Kopyalanarak oluşturuldu'][$log->action] ?? $log->action }}</span>
                                    <span class="text-muted tabular-nums">{{ $log->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</span>
                                </div>
                                @if ($log->action === 'product.updated' && $log->new_values)
                                    <ul class="mt-1 text-xs text-muted">
                                        @foreach ($log->new_values as $field => $new)
                                            <li>{{ __("fields.$field") === "fields.$field" ? $field : __("fields.$field") }}: {{ is_bool($log->old_values[$field] ?? null) ? (($log->old_values[$field] ?? false) ? 'Evet' : 'Hayır') : ($log->old_values[$field] ?? '—') }} → {{ is_bool($new) ? ($new ? 'Evet' : 'Hayır') : ($new ?? '—') }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Depo bazında stok" :padding="false">
                <ul class="divide-y divide-line text-sm">
                    @foreach ($stocks as $row)
                        <li class="flex items-center justify-between px-5 py-3">
                            <span>{{ $row['warehouse']->name }}@if ($row['warehouse']->id === $currentBusiness->settings->default_warehouse_id) <x-badge tone="primary" class="ml-1">Sevk</x-badge>@endif</span>
                            <span class="font-medium tabular-nums">{{ Quantity::format($row['quantity']) }} {{ $product->unit->name }}</span>
                        </li>
                    @endforeach
                </ul>
                @unless ($product->isArchived())
                    <div class="flex gap-2 border-t border-line p-4">
                        <x-button variant="secondary" size="sm" :href="route('business.stock.create', ['product' => $product->id])">Giriş / çıkış</x-button>
                        @if ($stocks->count() > 1)<x-button variant="secondary" size="sm" icon="arrows" :href="route('business.stock.transfer', ['product' => $product->id])">Transfer</x-button>@endif
                    </div>
                @endunless
            </x-card>

            @if ($product->variantGroup)
                <x-card title="Varyantlar" :description="$product->variantGroup->name">
                    <p class="text-sm"><b>Bu ürün:</b> {{ $product->variantLabel() ?? '—' }}</p>
                    @foreach ($siblings as $sibling)
                        <a href="{{ route('business.products.show', $sibling) }}" class="mt-2 block text-sm text-primary hover:underline">{{ $sibling->variantLabel() ?? $sibling->name }} <span class="text-muted">· {{ $sibling->sku }}</span></a>
                    @endforeach
                </x-card>
            @endif

            @unless ($product->isArchived())
                <x-card title="Diğer işlemler">
                    <form method="POST" action="{{ route('business.products.duplicate', $product) }}" class="space-y-3">
                        @csrf
                        <x-field name="sku" label="Kopya için yeni ürün kodu" :value="app(\App\Domain\Catalog\ProductService::class)->suggestSku($product->sku)" required />
                        <x-button variant="secondary" size="sm" icon="copy">Ürünü kopyala</x-button>
                    </form>
                    <form method="POST" action="{{ route('business.products.archive', $product) }}" class="mt-5 border-t border-line pt-4"
                        data-confirm="Ürün arşivlensin mi? Bayiler artık göremez; geçmiş sipariş ve stok kayıtları korunur.">
                        @csrf
                        <x-button variant="ghost" size="sm" icon="archive" class="text-danger">Arşivle</x-button>
                    </form>
                </x-card>
            @endunless
        </div>
    </div>
</x-layouts.panel>
