<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Yeni bayi">
    <x-page-header title="Bayi hesabı aç" subtitle="Başvuru beklemeden bayi hesabı açın. Bayi parolasını kendisi belirler.">
        <x-slot:breadcrumb><a href="{{ route('business.dealers.index') }}" class="hover:underline">Bayiler</a></x-slot:breadcrumb>
    </x-page-header>

    <form method="POST" action="{{ route('business.dealers.store') }}" class="space-y-6">
        @csrf
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
        <x-card title="Yetkili" description="Giriş e-postası. Bu adres platformda başka bir toptancının bayisi olarak kayıtlıysa yeni hesap açılmaz, mevcut hesaba erişim eklenir.">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="contact_name" label="Ad soyad" required />
                <x-field name="email" label="E-posta" type="email" required />
                <x-field name="phone" label="Telefon" type="tel" required />
            </div>
        </x-card>
        <x-card title="Ticari koşullar">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="dealer_code" label="Bayi kodu" hint="Boş bırakılırsa otomatik verilir." />
                <x-field name="dealer_group_id" label="Bayi grubu" :options="['' => 'Grupsuz'] + $groups->all()" />
                <x-field name="payment_term_days" label="Vade (gün)" type="number" min="0" max="365" :value="$defaultTerm" required />
                <x-toggle name="can_edit_addresses" label="Bayi sevk adreslerini değiştirebilsin" :checked="true" class="sm:col-span-3" />
            </div>
        </x-card>
        <div class="flex justify-end gap-2">
            <x-button variant="secondary" :href="route('business.dealers.index')">Vazgeç</x-button>
            <x-button>Bayi hesabını aç</x-button>
        </div>
    </form>
</x-layouts.panel>
