<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" title="Yeni işletme">
    <x-page-header title="Yeni işletme" subtitle="İşletme, yetkili hesabı ve ilk lisans dönemi birlikte oluşturulur.">
        <x-slot:breadcrumb><a href="{{ route('platform.businesses.index') }}" class="hover:underline">İşletmeler</a></x-slot:breadcrumb>
    </x-page-header>

    <form method="POST" action="{{ route('platform.businesses.store') }}" class="space-y-6">
        @csrf
        <x-card title="İşletme bilgileri">
            @include('platform.businesses._fields')
            <x-field name="slug" label="Kısa ad (adres)" required class="mt-5 sm:w-1/2"
                hint="Bayilik başvuru adresinde kullanılır: /basvuru/kisa-ad. Küçük harf, rakam ve tire." />
        </x-card>

        <x-card title="İşletme yetkilisi ve giriş bilgileri" description="Yetkili bu e-posta ve şifreyle toptancı paneline hemen giriş yapar; sonra kendi hesabından değiştirebilir. Şifre e-postayla gönderilmez.">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="owner_name" label="Ad soyad" required />
                <x-field name="owner_email" label="Giriş e-postası" type="email" required autocomplete="off" />
                <x-field name="owner_password" label="Şifre" type="password" required autocomplete="new-password" hint="En az 10 karakter; harf ve rakam." />
            </div>
        </x-card>

        <x-card title="Lisans">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="plan" label="Plan" value="Standart" required />
                <x-field name="starts_on" label="Başlangıç" type="date" :value="now('Europe/Istanbul')->toDateString()" required />
                <x-field name="ends_on" label="Bitiş" type="date" hint="Boş bırakılırsa süresiz." />
            </div>
        </x-card>

        <div class="flex justify-end gap-2">
            <x-button variant="secondary" :href="route('platform.businesses.index')">Vazgeç</x-button>
            <x-button>İşletmeyi oluştur</x-button>
        </div>
    </form>
</x-layouts.panel>
