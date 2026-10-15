@php
    $ctxLabel = match ($context['context'] ?? null) {
        'order' => 'Sipariş '.\App\Models\Order::whereKey($context['id'] ?? 0)->value('order_no'),
        'product_request' => 'Ürün talebi #'.($context['id'] ?? ''),
        default => null,
    };
@endphp
<x-layouts.panel :area="$area" title="Yeni mesaj">
    <x-page-header title="Yeni mesaj" :subtitle="$ctxLabel ? $ctxLabel.' hakkında' : null">
        <x-slot:breadcrumb><a href="{{ route($prefix.'.messages.index') }}" class="hover:underline">Mesajlar</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route($prefix.'.messages.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf
            @if ($ctxLabel)
                <input type="hidden" name="context" value="{{ $context['context'] }}">
                <input type="hidden" name="context_id" value="{{ $context['id'] }}">
            @endif
            @if ($prefix === 'business')
                <x-field name="dealer_relationship_id" label="Bayi" :options="['' => 'Seçin'] + $dealers->all()" :value="$context['dealer'] ?? null" required />
            @else
                <p class="text-sm text-muted">Alıcı: <b class="text-ink">{{ $currentBusiness->display_name }}</b></p>
            @endif
            <x-field name="subject" label="Konu" :value="$ctxLabel" required maxlength="160" />
            @include('messages._compose')
            <x-button>Gönder</x-button>
        </form>
    </x-card>
</x-layouts.panel>
