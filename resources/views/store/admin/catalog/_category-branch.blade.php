{{-- Kategori ağacının bir dalı (kendini alt kategoriler için yeniden çağırır): $nodes, $depth --}}
@foreach ($nodes as $node)
    @include('store.admin.catalog._category-row', ['node' => $node, 'depth' => $depth, 'first' => $loop->first, 'last' => $loop->last])
    @if ($node->children->isNotEmpty())
        @include('store.admin.catalog._category-branch', ['nodes' => $node->children, 'depth' => $depth + 1])
    @endif
@endforeach
