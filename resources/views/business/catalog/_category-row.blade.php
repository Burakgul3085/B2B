{{-- Kategori ağacında bir satır: $category, $level (ana kategori: 0) --}}
@php $count = $productCounts[$category->id] ?? 0; @endphp
<li @class(['flex flex-wrap items-center gap-3 py-3 pr-5', 'bg-slate-50/50' => $level > 0]) style="padding-left: {{ 1.25 + min($level, 8) * 1.5 }}rem">
    @if ($level)<span class="text-slate-300" aria-hidden="true">└</span>@endif
    <div class="min-w-0 flex-1">
        <a href="{{ route('business.categories.index', ['duzenle' => $category->id]) }}" @class(['hover:text-primary', 'font-medium' => $level === 0])>{{ $category->name }}</a>
        <span class="text-muted">· {{ $count }} ürün</span>
        @unless ($category->is_active)<x-badge class="ml-1">Pasif</x-badge>@endunless
    </div>
    @if ($level + 1 < \App\Models\Category::MAX_DEPTH)
        <a href="{{ route('business.categories.index', ['ust' => $category->id]) }}" class="text-xs font-medium text-primary hover:underline">+ Alt kategori</a>
    @endif
    <a href="{{ route('business.categories.index', ['duzenle' => $category->id]) }}" class="text-sm text-primary hover:underline">Düzenle</a>
</li>
