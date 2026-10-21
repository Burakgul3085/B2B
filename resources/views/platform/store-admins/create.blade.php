<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" title="Yeni mağaza yöneticisi">
    <x-page-header title="Yeni mağaza yöneticisi" subtitle="Hesap hemen etkin olur. Giriş adresini, e-postayı ve şifreyi kişiye siz iletirsiniz.">
        <x-slot:breadcrumb><a href="{{ route('platform.store-admins.index') }}" class="hover:underline">Mağaza Yöneticileri</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('platform.store-admins.store') }}" class="space-y-5">
            @csrf
            <x-field name="name" label="Ad soyad" required />
            <x-field name="email" label="Giriş e-postası" type="email" required autocomplete="off" />
            <x-field name="password" label="Şifre" type="password" required autocomplete="new-password" hint="En az 10 karakter; harf ve rakam içermeli." />
            <label class="flex items-start gap-3 rounded-lg border border-line p-4">
                <input type="hidden" name="is_super" value="0">
                <input type="checkbox" name="is_super" value="1" class="mt-0.5 size-4 rounded border-line text-primary" @checked(old('is_super', true))>
                <span class="text-sm">
                    <span class="font-medium text-ink">Süper admin</span>
                    <span class="mt-0.5 block text-muted">Diğer yöneticileri açar ve yönetir. İki adımlı doğrulama zorunludur. İşaretlenmezse normal yönetici olur.</span>
                </span>
            </label>
            <div class="flex gap-3">
                <x-button icon="plus">Hesabı aç</x-button>
                <x-button :href="route('platform.store-admins.index')" variant="ghost">Vazgeç</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.panel>
