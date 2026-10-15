<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Ana Sayfa">
    <x-page-header title="Hoş geldiniz" :subtitle="$currentBusiness->display_name.' bayi paneli'" />

    <x-card>
        <x-empty-state icon="package" title="Katalog yakında burada"
            text="Ürün kataloğu, siparişleriniz ve cari hesabınız {{ $currentBusiness->display_name }} tarafından kullanıma açıldığında bu sayfada görünecek." />
    </x-card>

    @if ($currentBusiness->phone || $currentBusiness->email)
        <x-card title="Toptancı iletişim bilgileri" class="mt-6">
            <dl class="grid gap-4 text-sm sm:grid-cols-3">
                @if ($currentBusiness->phone)<div><dt class="text-muted">Telefon</dt><dd class="mt-0.5 font-medium">{{ $currentBusiness->phone }}</dd></div>@endif
                @if ($currentBusiness->email)<div><dt class="text-muted">E-posta</dt><dd class="mt-0.5 font-medium">{{ $currentBusiness->email }}</dd></div>@endif
                @if ($currentBusiness->address)<div><dt class="text-muted">Adres</dt><dd class="mt-0.5 font-medium">{{ $currentBusiness->address }}</dd></div>@endif
            </dl>
        </x-card>
    @endif
</x-layouts.panel>
