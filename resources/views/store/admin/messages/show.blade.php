@php
    use App\Domain\Store\Customers\PhoneNumber;
    use App\Models\Store\StoreContactMessage;

    $subject = StoreContactMessage::SUBJECTS[$message->subject] ?? $message->subject;
    $whatsapp = $message->phone ? '90'.ltrim($message->phone, '0') : null;
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" :title="'Mesaj · '.$message->name">
    <x-page-header :title="$message->name" :subtitle="$subject.' · '.$message->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i')">
        <x-slot:breadcrumb><a href="{{ route('store_admin.messages.index') }}" class="hover:underline">Mesajlar</a></x-slot:breadcrumb>
        <x-badge :tone="$message->statusTone()" class="px-3! py-1! text-sm!">{{ $message->statusLabel() }}</x-badge>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card title="Mesaj" class="xl:col-span-2">
            <p class="whitespace-pre-line text-sm leading-relaxed">{{ $message->message }}</p>
        </x-card>

        <div class="space-y-6">
            <x-card title="Yanıtla">
                <div class="space-y-3 text-sm">
                    @if ($message->email)
                        <a href="mailto:{{ $message->email }}?subject={{ rawurlencode('Re: '.$subject) }}" class="flex items-center gap-2 font-medium text-primary hover:underline"><x-icon name="mail" class="size-4" /> {{ $message->email }}</a>
                    @endif
                    @if ($message->phone)
                        <a href="tel:{{ $message->phone }}" class="flex items-center gap-2 font-medium text-primary hover:underline"><x-icon name="phone" class="size-4" /> {{ PhoneNumber::format($message->phone) }}</a>
                        <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="flex items-center gap-2 font-medium text-primary hover:underline"><x-icon name="message-circle" class="size-4" /> WhatsApp'tan yaz</a>
                    @endif
                    @if ($message->status !== 'replied')
                        <form method="POST" action="{{ route('store_admin.messages.replied', $message) }}" class="border-t border-line pt-3">@csrf<x-button icon="check" class="w-full justify-center">Yanıtlandı olarak işaretle</x-button></form>
                    @else
                        <p class="border-t border-line pt-3 text-muted">Yanıtlandı: {{ $message->replied_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p>
                    @endif
                </div>
            </x-card>
            <x-card>
                <form method="POST" action="{{ route('store_admin.messages.destroy', $message) }}" data-confirm="Mesaj kalıcı olarak silinsin mi?">
                    @csrf @method('DELETE')
                    <x-button variant="danger" icon="trash" class="w-full justify-center">Mesajı sil</x-button>
                </form>
                <p class="mt-2 text-xs text-muted">Kişi verilerinin silinmesini isterse (KVKK) kullanın.</p>
            </x-card>
        </div>
    </div>
</x-layouts.panel>
