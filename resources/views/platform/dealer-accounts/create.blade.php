<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" title="Yeni bayi">
    <x-page-header title="Yeni bayi" subtitle="Seçilen toptancının bayisi olarak hesap açılır; bayi belirlediğiniz e-posta ve şifreyle hemen giriş yapar.">
        <x-slot:breadcrumb><a href="{{ route('platform.dealer-accounts.index') }}" class="hover:underline">Bayi Hesapları</a></x-slot:breadcrumb>
    </x-page-header>

    <form method="POST" action="{{ route('platform.dealer-accounts.store') }}" class="max-w-4xl space-y-6">
        @csrf
        <x-card title="Toptancı">
            <x-field name="business_id" label="Hangi toptancının bayisi olacak?" :options="$businesses->count() === 1 ? $businesses->all() : ['' => 'Seçin'] + $businesses->all()" required class="sm:w-1/2"
                hint="Bayi kodu, vade ve cari hesap bu toptancının varsayılanlarıyla oluşturulur; toptancı sonradan değiştirebilir." />
        </x-card>

        <x-card title="İşletme bilgileri">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="legal_name" label="İşletme unvanı" required class="sm:col-span-2" />
                <x-field name="tax_office" label="Vergi dairesi" />
                <x-field name="tax_number" label="Vergi / TC kimlik no" inputmode="numeric" maxlength="11" />
                <x-field name="city" label="İl" required />
                <x-field name="district" label="İlçe" required />
                <x-field name="address" label="Açık adres" textarea required class="sm:col-span-2" hint="İlk sevk adresi olarak da kaydedilir." />
            </div>
        </x-card>

        <x-card title="Yetkili ve giriş bilgileri" description="Bu e-posta başka bir toptancının bayisi olarak kayıtlıysa yeni hesap açılmaz, mevcut hesaba erişim eklenir (şifre değişmez).">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="contact_name" label="Ad soyad" required />
                <x-field name="phone" label="Telefon" type="tel" required />
                <x-field name="email" label="Giriş e-postası" type="email" required autocomplete="off" />
                <x-field name="password" label="Şifre" type="password" autocomplete="new-password" hint="Yeni hesap için zorunlu; en az 10 karakter, harf ve rakam. Şifre e-postayla gönderilmez." />
            </div>
        </x-card>

        <div class="flex justify-end gap-3">
            <x-button variant="secondary" :href="route('platform.dealer-accounts.index')">Vazgeç</x-button>
            <x-button icon="check">Bayiyi aç</x-button>
        </div>
    </form>
</x-layouts.panel>
