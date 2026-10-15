@php $prefix = $area->value; @endphp
<x-layouts.panel :area="$area" title="Bildirimler">
    <x-page-header title="Bildirimler">
        @if ($notifications->contains(fn ($n) => $n->read_at === null))
            <form method="POST" action="{{ route($prefix.'.notifications.read-all') }}">@csrf<x-button variant="secondary" size="sm">Tümünü okundu işaretle</x-button></form>
        @endif
    </x-page-header>

    <x-card :padding="false">
        @if ($notifications->isEmpty())
            <x-empty-state icon="inbox" title="Bildirim yok" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($notifications as $n)
                    <li>
                        <a href="{{ route($prefix.'.notifications.open', $n) }}" @class(['flex gap-3 px-5 py-3.5 hover:bg-slate-50', 'bg-primary-soft/40' => ! $n->read_at])>
                            <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-primary' => ! $n->read_at, 'bg-slate-200' => $n->read_at])></span>
                            <div class="min-w-0 flex-1">
                                <p @class(['text-sm', 'font-semibold' => ! $n->read_at])>{{ $n->title }}</p>
                                @if ($n->body)<p class="truncate text-xs text-muted">{{ $n->body }}</p>@endif
                            </div>
                            <span class="shrink-0 text-xs text-muted tabular-nums">{{ $n->created_at->timezone('Europe/Istanbul')->format('d.m H:i') }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($notifications->hasPages())<div class="border-t border-line p-4">{{ $notifications->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
