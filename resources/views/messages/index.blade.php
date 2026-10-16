<x-layouts.panel :area="$area" title="Mesajlar">
    <x-page-header title="Mesajlar" :subtitle="$prefix === 'business' ? 'Bayilerinizle yazışmalar.' : $currentBusiness->display_name.' ile yazışmalarınız.'">
        <x-button icon="plus" :href="route($prefix.'.messages.create')">Yeni mesaj</x-button>
    </x-page-header>

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-center gap-2 border-b border-line p-4">
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="field-input max-w-sm" placeholder="Konu veya mesaj içinde ara" aria-label="Ara">
            <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" name="unread" value="1" @checked($filters['unread'] ?? false) class="size-4 rounded" data-autosubmit> Okunmamış</label>
            <x-button variant="secondary" icon="search">Ara</x-button>
        </form>
        @if ($conversations->isEmpty())
            <x-empty-state icon="inbox" title="Mesaj yok" :text="$prefix === 'business' ? 'Bir bayiyle yazışma başlatın veya sipariş sayfasından mesaj gönderin.' : 'Toptancınıza soru sormak için yeni mesaj başlatın.'" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($conversations as $c)
                    @php $unread = $unreadIds->has($c->id); @endphp
                    <li>
                        <a href="{{ route($prefix.'.messages.show', $c) }}" @class(['flex gap-3 px-5 py-4 hover:bg-slate-50', 'bg-primary-soft/40' => $unread])>
                            <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-primary' => $unread, 'bg-transparent' => ! $unread])></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <p @class(['truncate text-sm', 'font-semibold' => $unread, 'font-medium' => ! $unread])>{{ $c->subject }}</p>
                                    <span class="text-xs text-muted tabular-nums">{{ $c->last_message_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</span>
                                </div>
                                <p class="text-xs text-muted">
                                    @if ($prefix === 'business'){{ $c->relationship->dealer->legal_name }} · @endif
                                    @if ($c->context_type)<span class="text-primary">{{ $c->contextLabel() }}</span> · @endif
                                    {{ \Illuminate\Support\Str::limit($c->latestMessage?->body, 90) }}
                                </p>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($conversations->hasPages())<div class="border-t border-line p-4">{{ $conversations->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
