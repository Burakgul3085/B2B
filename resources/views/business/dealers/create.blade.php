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
        <x-card title="Yetkili ve giriş bilgileri" description="Bayi bu e-posta ve şifreyle hemen giriş yapar; giriş yaptıktan sonra e-postasını ve şifresini kendisi değiştirebilir. Bu e-posta başka bir toptancının bayisi olarak kayıtlıysa yeni hesap açılmaz, mevcut hesaba erişim eklenir (şifre değişmez).">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="contact_name" label="Ad soyad" required />
                <x-field name="phone" label="Telefon" type="tel" required />
                <x-field name="email" label="Giriş e-postası" type="email" required autocomplete="off" />
                <div x-data="{ show: false }">
                    <label for="password" class="mb-1.5 block text-sm font-medium">Şifre <span class="text-danger">*</span></label>
                    <div class="flex gap-2">
                        <input id="password" name="password" :type="show ? 'text' : 'password'" autocomplete="new-password" class="field-input" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                        <x-button type="button" variant="secondary" size="sm" @click="show = !show" x-text="show ? 'Gizle' : 'Göster'">Göster</x-button>
                    </div>
                    <p class="mt-1.5 text-xs text-muted">En az 10 karakter; harf ve rakam içermeli. Şifreyi bayiye siz iletirsiniz (e-postayla gönderilmez).</p>
                    @error('password')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
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
