<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Fatura Ayarları">
    <x-page-header title="Fatura Ayarları" subtitle="Bayi faturaları (e-Arşiv / e-Fatura). Mağazanın fatura ayarlarından tamamen ayrıdır: aynı Uyumsoft hesabı kullanılsa bile bilgiler burada ayrıca girilir ve bayi faturaları ayrı seriden kesilir.">
        <x-button variant="secondary" :href="route('business.invoices.index')">Faturalar</x-button>
    </x-page-header>

    @error('invoice')<div role="alert" class="mb-4 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <form method="POST" action="{{ route('business.invoice-settings.update') }}" class="space-y-6" autocomplete="off">
        @csrf @method('PUT')
        <div class="grid gap-6 xl:grid-cols-2">
            <x-card title="Sağlayıcı" description="Deneme sürücüsü hiçbir yere göndermez; akışı denemek içindir. Gerçek fatura için Uyumsoft web servis kullanıcısını girin.">
                <div class="space-y-5">
                    <x-field name="driver" label="Sağlayıcı" :options="$drivers" :value="$s->driver" />
                    <x-field name="uyumsoft_mode" label="Uyumsoft ortamı" :options="['test' => 'Test', 'live' => 'Canlı']" :value="$s->uyumsoft_mode" class="max-w-48" />
                    <x-field name="uyumsoft_username" label="Web servis kullanıcı adı" :value="$s->uyumsoft_username" autocomplete="off" />
                    <x-field name="uyumsoft_password" label="Web servis şifresi" type="password" autocomplete="new-password"
                        :hint="$hasPassword ? 'Kayıtlı. Değiştirmek için yenisini girin; boş bırakırsanız korunur.' : 'Kayıtlı değil.'" />
                    <x-field name="sender_id" label="Gönderici VKN / TCKN" :value="$s->sender_id" inputmode="numeric" hint="Boşsa işletme ayarlarındaki vergi numarası kullanılır. Uyumsoft hesabınızdakiyle aynı olmalı." />
                </div>
            </x-card>

            <x-card title="Seri ve kesim" description="Mali müşavir kararı: bayi faturaları mağazadan ayrı seriden kesilir. Serileri önce Uyumsoft portalında tanımlayın.">
                <div class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field name="series_earsiv" label="e-Arşiv serisi" :value="$s->series_earsiv" maxlength="3" hint="3 karakter, ör. BYA" />
                        <x-field name="series_efatura" label="e-Fatura serisi" :value="$s->series_efatura" maxlength="3" hint="3 karakter, ör. BYF" />
                    </div>
                    <x-field name="delivery_vat" label="Teslimat ücreti KDV oranı" :options="['20' => '%20', '10' => '%10', '1' => '%1', '0' => '%0']" :value="\App\Domain\Invoicing\InvoiceMath::rate((string) $s->delivery_vat)" class="max-w-48" />
                    <x-toggle name="auto_issue" label="Faturayı otomatik kes" :checked="$s->auto_issue"
                        hint="Kart / havale siparişinde ödeme alınınca, sonra öde siparişinde mal yola çıkınca fatura bir dakika içinde kendiliğinden kesilir. Yalnızca açtığınız andan sonraki siparişlere uygulanır." />
                    @if ($s->auto_issue && $s->auto_since)<p class="text-xs text-muted">Otomatik kesim {{ $s->auto_since->timezone('Europe/Istanbul')->format('d.m.Y H:i') }} tarihinden beri açık.</p>@endif
                    <x-toggle name="notify_dealer" label="Fatura kesilince bayiye bildirim ve e-posta gönder" :checked="$s->notify_dealer"
                        hint="E-postaya fatura eklenmez; bayi panele girip indirir." />
                </div>
            </x-card>

            <x-card title="Faturadaki satıcı" description="İşletme Ayarları'ndaki unvan, adres ve vergi bilgilerinden gelir. Gerekirse yalnızca faturada görünecek adı ve vergi dairesini değiştirebilirsiniz.">
                <div class="space-y-5">
                    <x-field name="seller_name" label="Satıcı adı (boşsa işletme unvanı)" :value="$s->seller_name" />
                    <x-field name="tax_office" label="Vergi dairesi (boşsa işletme ayarlarındaki)" :value="$s->tax_office" />
                    <dl class="rounded-lg bg-slate-50 p-4 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-muted">Unvan</dt><dd class="text-right">{{ $seller['name'] ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">VKN / TCKN</dt><dd>{{ $seller['id'] ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Vergi dairesi</dt><dd>{{ $seller['tax_office'] ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Adres</dt><dd class="text-right">{{ $seller['address'] ?: '—' }} {{ $seller['district'] }} / {{ $seller['city'] }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Web adresi</dt><dd>{{ $seller['web'] }}</dd></div>
                    </dl>
                    @if ($sellerProblem)<p class="text-sm text-danger">{{ $sellerProblem }}</p>@endif
                </div>
            </x-card>
        </div>

        <div class="flex flex-wrap gap-3">
            <x-button>Kaydet</x-button>
            <x-button variant="secondary" form="baglanti-testi" icon="refresh">Bağlantıyı dene</x-button>
        </div>
    </form>
    <form id="baglanti-testi" method="POST" action="{{ route('business.invoice-settings.test') }}">@csrf</form>
</x-layouts.panel>
