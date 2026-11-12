{{-- Kategori ağacında bir satır: $node, $depth, $first, $last, $ancestors (üst kategoriler kapalıysa satır gizlenir) --}}
@php $branch = $node->children->count(); @endphp
<li class="flex items-center gap-3 px-5 py-2.5" style="padding-left: {{ 1.25 + $depth * 1.5 }}rem" @if ($ancestors) x-show="visible(@alpine($ancestors))" x-cloak @endif>
    @if ($branch)
        <button type="button" class="-ml-1 flex size-6 shrink-0 items-center justify-center rounded text-muted hover:bg-slate-100 hover:text-ink" @click="toggle({{ $node->id }})"
            :aria-expanded="isOpen({{ $node->id }})" aria-label="{{ $node->name }}: alt kategorileri aç / kapat">
            <span class="text-xs transition-transform" :class="isOpen({{ $node->id }}) ? 'rotate-90' : ''" aria-hidden="true">▶</span>
        </button>
    @elseif ($depth)
        <span class="text-slate-300" aria-hidden="true">└</span>
    @else
        <span class="size-6 shrink-0" aria-hidden="true"></span>
    @endif
    <a href="{{ route('store_admin.categories.index', ['duzenle' => $node->id]) }}" @class(['min-w-0 flex-1 truncate hover:text-primary', 'font-medium text-ink' => $depth === 0, 'text-slate-700' => $depth > 0])>{{ $node->name }}</a>
    @unless ($node->is_active)<x-badge>Pasif</x-badge>@endunless
    @if ($branch)<span class="text-xs text-muted" x-show="! isOpen({{ $node->id }})">{{ $branch }} alt kategori</span>@endif
    <span class="text-xs text-muted tabular-nums">{{ $node->products_count }} ürün</span>
    <span class="flex items-center">
        @foreach (['up' => ['↑', 'Yukarı taşı', $first], 'down' => ['↓', 'Aşağı taşı', $last]] as $direction => [$arrow, $label, $disabled])
            <form method="POST" action="{{ route('store_admin.categories.move', $node) }}">
                @csrf @method('PUT')
                <input type="hidden" name="direction" value="{{ $direction }}">
                <button class="rounded px-1.5 py-0.5 text-muted hover:bg-slate-100 hover:text-ink disabled:opacity-30" aria-label="{{ $node->name }}: {{ $label }}" @disabled($disabled)>{{ $arrow }}</button>
            </form>
        @endforeach
    </span>
    @if ($depth + 1 < \App\Models\Store\StoreCategory::MAX_DEPTH)
        <a href="{{ route('store_admin.categories.index', ['ust' => $node->id]) }}" class="text-xs font-medium text-primary hover:underline">+ Alt</a>
    @endif
</li>
