@php use App\Models\Store\StoreContactMessage; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Mesajlar">
    <x-page-header title="Mesajlar" subtitle="İletişim sayfasındaki formdan gelen mesajlar. Yanıtı e-posta veya telefonla verip mesajı “yanıtlandı” olarak işaretleyin." />

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach (['' => 'Tümü'] + StoreContactMessage::STATUSES as $key => $label)
            @php
                $count = $key === '' ? null : ($counts[$key] ?? 0);
                $active = ($status ?? '') === $key;
            @endphp
            <a href="{{ route('store_admin.messages.index', array_filter(['status' => $key])) }}" @class([
                'rounded-full px-3.5 py-1.5 font-medium ring-1',
                'bg-primary text-white ring-primary' => $active,
                'bg-white text-ink ring-line hover:ring-primary' => ! $active,
            ])>{{ $label }}@if ($count) <span class="ml-1 tabular-nums opacity-80">{{ $count }}</span>@endif</a>
        @endforeach
    </div>

    <x-card :padding="false">
        @if ($messages->isEmpty())
            <x-empty-state icon="message-circle" title="Mesaj yok" text="İletişim sayfasındaki formdan gelen mesajlar burada görünür." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Gönderen</th><th>Konu</th><th>Mesaj</th><th>Durum</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($messages as $message)
                            <tr>
                                <td>
                                    <a href="{{ route('store_admin.messages.show', $message) }}" @class(['hover:text-primary', 'font-semibold text-ink' => $message->status === 'new'])>{{ $message->name }}</a>
                                    <p class="text-xs text-muted">{{ $message->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p>
                                </td>
                                <td class="text-sm">{{ StoreContactMessage::SUBJECTS[$message->subject] ?? $message->subject }}</td>
                                <td class="max-w-md text-sm text-muted"><span class="line-clamp-2">{{ $message->message }}</span></td>
                                <td><x-badge :tone="$message->statusTone()">{{ $message->statusLabel() }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($messages->hasPages())<div class="border-t border-line p-4">{{ $messages->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
