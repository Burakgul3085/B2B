<x-layouts.auth :area="\App\Domain\Auth\Area::Dealer" title="Toptancı seçin">
    @if ($relationships->isEmpty())
        <x-empty-state icon="building" title="Aktif toptancı bağlantınız yok"
            text="Bağlı olduğunuz toptancı hesabınızı askıya almış olabilir. Lütfen toptancınızla iletişime geçin." class="py-4" />
    @else
        <p class="mb-5 text-sm text-muted">Birden fazla toptancıyla çalışıyorsunuz. Fiyat, sipariş ve cari bilgileri seçtiğiniz toptancıya göre gösterilir.</p>
        <div class="space-y-3">
            @foreach ($relationships as $relationship)
                <form method="POST" action="{{ route('dealer.select-business.store') }}">
                    @csrf
                    <input type="hidden" name="relationship_id" value="{{ $relationship->id }}">
                    <button class="flex w-full items-center gap-3 rounded-xl border border-line px-4 py-3 text-left hover:border-primary hover:bg-primary-soft/40">
                        <span class="flex size-10 items-center justify-center rounded-lg bg-primary text-sm font-semibold text-white">{{ mb_substr($relationship->business->display_name, 0, 1) }}</span>
                        <span class="flex-1">
                            <span class="block font-medium">{{ $relationship->business->display_name }}</span>
                            <span class="block text-xs text-muted">Bayi kodu: {{ $relationship->dealer_code }}</span>
                        </span>
                        <x-icon name="arrow-right" class="size-4 text-muted" />
                    </button>
                </form>
            @endforeach
        </div>
    @endif

    <x-slot:footer>
        <form method="POST" action="{{ route('dealer.logout') }}">@csrf<button class="hover:underline">Çıkış yap</button></form>
    </x-slot:footer>
</x-layouts.auth>
