{{-- Filtredeki kategori ağacı (her seviye; kendini alt kategoriler için yeniden çağırır): $nodes (product_count hazır), $depth, $selectedCategories. Ürünü olmayanlar gizlenir. --}}
@foreach ($nodes as $node)
    @continue($node->product_count === 0 && ! in_array($node->id, $selectedCategories, true))
    <label class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-slate-50" @if ($depth) style="margin-left: {{ min($depth, 6) * 1.25 }}rem" @endif>
        <input type="checkbox" name="category[]" value="{{ $node->id }}" @checked(in_array($node->id, $selectedCategories, true)) data-autosubmit class="size-4 rounded border-slate-300 text-primary">
        <span @class(['flex-1 text-sm', 'font-medium text-ink' => $depth === 0, 'text-ink/80' => $depth > 0])>{{ $node->name }}</span>
        <span @class(['text-[11px] text-muted tabular-nums', 'rounded-md bg-slate-100 px-1.5 py-0.5 font-semibold' => $depth === 0])>{{ number_format($node->product_count, 0, ',', '.') }}</span>
    </label>
    @if ($node->children->isNotEmpty())
        @include('dealer.catalog._category-filter', ['nodes' => $node->children, 'depth' => $depth + 1])
    @endif
@endforeach
