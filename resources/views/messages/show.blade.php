<x-layouts.panel :area="$area" :title="$conversation->subject">
    <x-page-header :title="$conversation->subject" :subtitle="$counterpart.($conversation->context_type ? ' · '.$conversation->contextLabel() : '')">
        <x-slot:breadcrumb><a href="{{ route($prefix.'.messages.index') }}" class="hover:underline">Mesajlar</a></x-slot:breadcrumb>
        @if ($conversation->context_type === 'order')
            <x-button variant="secondary" size="sm" :href="route($prefix.'.orders.show', $conversation->context_id)">Siparişe git</x-button>
        @endif
    </x-page-header>

    <div class="mx-auto max-w-3xl space-y-4">
        @foreach ($conversation->messages as $m)
            @php $mine = $m->sender_side === $prefix; @endphp
            <div @class(['flex', 'justify-end' => $mine])>
                <div @class(['max-w-[85%] rounded-2xl px-4 py-3 text-sm shadow-xs', 'bg-primary text-white' => $mine, 'border border-line bg-white' => ! $mine])>
                    <p class="whitespace-pre-line break-words">{{ $m->body }}</p>
                    @foreach ($m->attachments as $file)
                        <a href="{{ route($prefix.'.messages.attachment', [$conversation, $file]) }}" @class(['mt-2 flex items-center gap-1.5 text-xs underline', 'text-white/90' => $mine, 'text-primary' => ! $mine])>
                            <x-icon name="file" class="size-3.5" />{{ $file->original_name }}
                        </a>
                    @endforeach
                    <p @class(['mt-1.5 text-[11px]', 'text-white/70' => $mine, 'text-muted' => ! $mine])>
                        {{ $mine ? 'Siz' : $counterpart }} · {{ $m->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                        @if ($mine && $m->id <= $counterpartRead) · Okundu @endif
                    </p>
                </div>
            </div>
        @endforeach

        <x-card>
            <form method="POST" action="{{ route($prefix.'.messages.reply', $conversation) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @include('messages._compose')
                <div class="flex justify-end"><x-button>Yanıtla</x-button></div>
            </form>
        </x-card>
    </div>
</x-layouts.panel>
