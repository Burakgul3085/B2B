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
            <form method="POST" action="{{ route('store_admin.messages.destroy-many') }}" x-data="pickList" data-confirm="Seçilen mesajlar yanıtlarıyla birlikte kalıcı olarak silinecek. Devam edilsin mi?">
                @csrf
                @method('DELETE')
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr>
                        <th class="w-10"><input type="checkbox" :checked="allPicked" @change="toggleAll" class="size-4 rounded border-line text-primary" aria-label="Sayfadaki tüm mesajları seç"></th>
                        <th>Gönderen</th><th>Konu</th><th>Mesaj</th><th>Durum</th><th class="text-right"><span class="sr-only">İşlem</span></th>
                    </tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($messages as $message)
                            <tr>
                                <td><input type="checkbox" name="messages[]" value="{{ $message->id }}" x-model="picked" data-pick class="size-4 rounded border-line text-primary" aria-label="{{ $message->name }} mesajını seç"></td>
                                <td>
                                    <a href="{{ route('store_admin.messages.show', $message) }}" @class(['hover:text-primary', 'font-semibold text-ink' => $message->status === 'new'])>{{ $message->name }}</a>
                                    <p class="text-xs text-muted">{{ $message->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p>
                                </td>
                                <td class="text-sm">{{ StoreContactMessage::SUBJECTS[$message->subject] ?? $message->subject }}</td>
                                <td class="max-w-md text-sm text-muted"><span class="line-clamp-2">{{ $message->message }}</span></td>
                                <td><x-badge :tone="$message->statusTone()">{{ $message->statusLabel() }}</x-badge></td>
                                <td class="text-right"><button type="submit" form="mesaj-sil-{{ $message->id }}" class="text-sm text-danger hover:underline">Sil</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center gap-3 border-t border-line p-4">
                <x-button variant="danger" icon="trash" x-bind:disabled="none">Seçilenleri sil</x-button>
                <span class="text-sm text-muted" x-show="! none"><span x-text="count"></span> mesaj seçildi</span>
            </div>
            </form>
            @foreach ($messages as $message)
                <form id="mesaj-sil-{{ $message->id }}" method="POST" action="{{ route('store_admin.messages.destroy', $message) }}" data-confirm="{{ $message->name }} adlı kişinin mesajı yanıtlarıyla birlikte kalıcı olarak silinecek. Devam edilsin mi?" class="hidden">@csrf @method('DELETE')</form>
            @endforeach
            @if ($messages->hasPages())<div class="border-t border-line p-4">{{ $messages->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
