<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" title="İşletmeyi düzenle">
    <x-page-header :title="$business->display_name" subtitle="İşletme bilgilerini düzenle">
        <x-slot:breadcrumb><a href="{{ route('platform.businesses.show', $business) }}" class="hover:underline">İşletme</a></x-slot:breadcrumb>
    </x-page-header>

    <form method="POST" action="{{ route('platform.businesses.update', $business) }}" class="space-y-6">
        @csrf @method('PUT')
        <x-card title="İşletme bilgileri">@include('platform.businesses._fields')</x-card>
        <div class="flex justify-end gap-2">
            <x-button variant="secondary" :href="route('platform.businesses.show', $business)">Vazgeç</x-button>
            <x-button>Kaydet</x-button>
        </div>
    </form>
</x-layouts.panel>
