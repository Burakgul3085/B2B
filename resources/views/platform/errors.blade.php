<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" title="Hata Kayıtları">
    <x-page-header title="Hata Kayıtları" subtitle="Uygulamada oluşan beklenmeyen hatalar. Aynı hata tek kayıtta toplanır; kişisel veri ve sorgu değerleri yazılmaz.">
        <form method="POST" action="{{ route('platform.errors.purge') }}" data-confirm="Son 30 günde tekrar etmemiş çözülmüş kayıtlar silinsin mi?">
            @csrf @method('DELETE')
            <x-button variant="secondary" icon="trash">Eski çözülmüşleri temizle</x-button>
        </form>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
        @foreach (['acik' => 'Açık', 'cozuldu' => 'Çözüldü'] as $key => $label)
            <a href="{{ route('platform.errors', ['durum' => $key]) }}" @class([
                'rounded-full px-3.5 py-1.5 font-medium ring-1',
                'bg-primary text-white ring-primary' => $filters['durum'] === $key,
                'bg-white text-ink ring-line hover:ring-primary' => $filters['durum'] !== $key,
            ])>{{ $label }} <span class="ml-1 tabular-nums opacity-80">{{ $counts[$key] }}</span></a>
        @endforeach
        <span class="ml-auto text-muted">Son 24 saatte görülen: <span class="font-medium text-ink">{{ $last24 }}</span></span>
    </div>

    <x-card :padding="false">
        <form method="GET" class="flex gap-3 border-b border-line p-4">
            <input type="hidden" name="durum" value="{{ $filters['durum'] }}">
            <x-field name="q" label="Ara" :value="$filters['q'] ?? ''" placeholder="Referans kodu (H12), mesaj, sınıf veya dosya" class="flex-1" />
            <x-button variant="secondary" class="self-end" aria-label="Ara"><x-icon name="search" class="size-4" /></x-button>
        </form>
        @if ($errors_list->isEmpty())
            <x-empty-state icon="check-circle" :title="$filters['durum'] === 'acik' ? 'Açık hata yok' : 'Çözülmüş kayıt yok'" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($errors_list as $event)
                    <li class="flex flex-wrap items-start gap-4 px-5 py-4">
                        <div class="min-w-0 flex-1">
                            <p class="font-mono text-xs text-muted"><span class="font-semibold text-ink">{{ \App\Domain\Platform\ErrorRecorder::reference($event->id) }}</span> · {{ $event->exception_class }}</p>
                            <p class="mt-1 break-words text-sm font-medium text-ink">{{ $event->message }}</p>
                            <p class="mt-1 font-mono text-xs text-muted">{{ $event->location }}@if ($event->url) · {{ $event->url }}@endif</p>
                        </div>
                        <div class="text-right text-xs text-muted">
                            <p><span class="text-base font-semibold text-ink tabular-nums">{{ number_format($event->occurrences, 0, ',', '.') }}</span> kez</p>
                            <p>Son: {{ $event->last_seen_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p>
                            <p>İlk: {{ $event->first_seen_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p>
                            @if ($event->resolved_at)
                                <p class="text-green-700">Çözüldü: {{ $event->resolved_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p>
                            @else
                                <form method="POST" action="{{ route('platform.errors.resolve', $event) }}" class="mt-2">@csrf<x-button size="sm" variant="secondary">Çözüldü</x-button></form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($errors_list->hasPages())<div class="border-t border-line p-4">{{ $errors_list->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
