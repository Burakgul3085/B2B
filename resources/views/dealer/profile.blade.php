<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="İşletme Profili">
    <x-page-header title="İşletme Profili" subtitle="İşletmenizin iletişim bilgileri." />

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="Kayıt bilgileri" description="Unvan ve vergi bilgilerinin değişmesi için toptancınızla iletişime geçin.">
            <dl class="space-y-3 text-sm">
                <div><dt class="text-muted">İşletme unvanı</dt><dd class="font-medium">{{ $dealer->legal_name }}</dd></div>
                <div><dt class="text-muted">Vergi dairesi / no</dt><dd class="font-medium">{{ trim(($dealer->tax_office ?? '').' '.($dealer->tax_number ?? '')) ?: '—' }}</dd></div>
                <div><dt class="text-muted">Bayi kodunuz</dt><dd class="font-medium">{{ $currentRelationship->dealer_code }}</dd></div>
                <div><dt class="text-muted">Vade</dt><dd class="font-medium">{{ $currentRelationship->payment_term_days }} gün</dd></div>
            </dl>
        </x-card>
        <x-card title="İletişim bilgileri">
            <form method="POST" action="{{ route('dealer.profile.update') }}" class="space-y-4">
                @csrf @method('PUT')
                <x-field name="phone" label="Telefon" type="tel" :value="$dealer->phone" required />
                <div class="grid grid-cols-2 gap-3">
                    <x-field name="city" label="İl" :value="$dealer->city" required />
                    <x-field name="district" label="İlçe" :value="$dealer->district" required />
                </div>
                <x-field name="address" label="İşletme adresi" :value="$dealer->address" textarea required hint="Sevk adresleriniz ayrıca yönetilir." />
                <x-button>Kaydet</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.panel>
