{{-- Kategori ağacının bir dalı (kendini alt kategoriler için yeniden çağırır): $nodes (Category::tree(), "depth" hazır) --}}
@foreach ($nodes as $node)
    @include('business.catalog._category-row', ['category' => $node, 'level' => $node->depth])
    @if ($node->children->isNotEmpty())
        @include('business.catalog._category-branch', ['nodes' => $node->children])
    @endif
@endforeach
