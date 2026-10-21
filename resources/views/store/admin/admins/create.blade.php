<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Yeni yönetici">
    <x-page-header title="Yeni yönetici" subtitle="Hesap hemen etkin olur. Giriş e-postasını ve şifreyi kişiye siz iletirsiniz; kendisi sonradan değiştirebilir.">
        <x-slot:breadcrumb><a href="{{ route('store_admin.admins.index') }}" class="hover:underline">Yöneticiler</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('store_admin.admins.store') }}" class="space-y-5">
            @csrf
            <x-field name="name" label="Ad soyad" required />
            <x-field name="email" label="Giriş e-postası" type="email" required autocomplete="off" />
            <x-field name="password" label="Şifre" type="password" required autocomplete="new-password" hint="En az 10 karakter; harf ve rakam içermeli." />
            <div class="flex gap-3">
                <x-button icon="plus">Yöneticiyi ekle</x-button>
                <x-button :href="route('store_admin.admins.index')" variant="ghost">Vazgeç</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.panel>
