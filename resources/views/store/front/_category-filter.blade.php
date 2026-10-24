{{-- Filtre kutusundaki kategori ağacı (en fazla 3 seviye): $nodes (product_count yüklü), $selected, $depth. Ürünü olmayanlar gizlenir. --}}
@foreach ($nodes as $node)
    @continue($node->product_count === 0 && ! in_array($node->id, $selected, true))
    <label @class(['flex cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-slate-50', 'ml-5' => $depth === 1, 'ml-10' => $depth >= 2])>
        <input type="checkbox" name="kategori[]" value="{{ $node->id }}" @checked(in_array($node->id, $selected, true)) data-autosubmit class="size-4 rounded border-line text-primary">
        <span @class(['flex-1 text-sm', 'font-medium text-ink' => $depth === 0, 'text-ink/80' => $depth > 0])>{{ $node->name }}</span>
        <span @class(['text-[11px] font-semibold text-muted tabular-nums', 'rounded-md bg-slate-100 px-1.5 py-0.5' => $depth === 0])>{{ number_format($node->product_count, 0, ',', '.') }}</span>
    </label>
    @if ($node->children->isNotEmpty())
        @include('store.front._category-filter', ['nodes' => $node->children, 'selected' => $selected, 'depth' => $depth + 1])
    @endif
@endforeach
