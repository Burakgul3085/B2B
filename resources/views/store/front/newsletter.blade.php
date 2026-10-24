{{-- E-bülten onay ve abonelikten çıkma sayfası: $state confirmed | confirm-leave | unsubscribed | invalid --}}
@php
    $views = [
        'confirmed' => ['check-circle', 'text-puzzle-green bg-puzzle-green-soft', 'Aboneliğiniz onaylandı', 'Teşekkürler! Yeni ürünler, kampanyalar ve size özel fırsatlar artık e-postanıza gelecek.'],
        'confirm-leave' => ['mail', 'text-puzzle-blue bg-puzzle-blue-soft', 'E-bültenden ayrılmak istiyor musunuz?', 'Ayrıldığınızda size kampanya e-postası göndermeyiz. Sipariş e-postalarınız bundan etkilenmez.'],
        'unsubscribed' => ['check-circle', 'text-puzzle-orange bg-puzzle-orange-soft', 'E-bültenden ayrıldınız', 'Size artık kampanya e-postası göndermeyeceğiz. Fikrinizi değiştirirseniz sitemizin altındaki formdan yeniden abone olabilirsiniz.'],
        'invalid' => ['alert', 'text-danger bg-red-50', 'Bağlantı geçersiz', 'Bu bağlantı geçersiz ya da süresi dolmuş. Sitemizin altındaki formdan yeniden abone olabilirsiniz.'],
    ];
    [$icon, $tone, $title, $text] = $views[$state];
@endphp
<x-layouts.store :title="$title" :noindex="true">
    <div class="mx-auto max-w-xl px-4 py-16 text-center sm:py-24">
        <span class="mx-auto flex size-16 items-center justify-center rounded-2xl {{ $tone }}"><x-icon :name="$icon" class="size-8" /></span>
        <h1 class="mt-6 font-display text-3xl font-extrabold tracking-tight">{{ $title }}</h1>
        <p class="mt-3 text-muted">{{ $text }}</p>
        @if ($subscriber && $state !== 'invalid')<p class="mt-2 text-sm font-semibold">{{ $subscriber->email }}</p>@endif
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            @if ($state === 'confirm-leave')
                <form method="POST" action="{{ route('store.newsletter.unsubscribe.store', $subscriber->token) }}">
                    @csrf
                    <x-button variant="secondary">Evet, ayrıl</x-button>
                </form>
            @endif
            <x-button :href="route('store.home')">Alışverişe devam et</x-button>
        </div>
    </div>
</x-layouts.store>
