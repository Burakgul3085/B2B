@php $money = fn ($v) => $v === null ? '' : number_format((float) $v, 2, ',', ''); @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Ödeme Ayarları">
    <x-page-header title="Ödeme Ayarları" subtitle="Bayileriniz siparişlerini onayınızdan sonra onaylanan tutar üzerinden öder. Ödeme bayinin carisine otomatik tahsilat olarak işlenir. Tutarlar KDV dahildir." />

    <form method="POST" action="{{ route('business.payments.update') }}" class="space-y-6">
        @csrf @method('PUT')
        <x-card title="Ödeme sistemi" description="Kapalıyken siparişler eskisi gibi ödemesiz verilir ve tutar bayinin carisine borç yazılır. Açıkken bayi sepette ödeme yöntemini seçer.">
            <div class="space-y-3 text-sm">
                <x-toggle name="payments_enabled" label="Bayi siparişlerinde ödeme alınsın" :checked="$s->payments_enabled" />
                <ul class="list-inside list-disc space-y-1 text-muted">
                    <li><b class="text-ink">Kart</b> ve <b class="text-ink">havale / EFT</b>: tüm bayiler; aşağıda açtığınız yöntemler görünür.</li>
                    <li><b class="text-ink">Açık hesap (cari)</b> ve <b class="text-ink">teslimde ödeme</b>: yalnızca bayi kartında izin verdiğiniz bayiler (Bayiler → bayi → Koşullar).</li>
                    <li>Bayi siparişi verir, siz onaylarsınız; bayiye "onaylanan tutarı öde" e-postası gider. Miktar değiştirdiyseniz e-postada ve sipariş ekranında ayrıca belirtilir.</li>
                    <li>Kart ve havale siparişleri ödeme alınmadan hazırlanamaz; ödemesi alınmamış sipariş tamamlanamaz.</li>
                </ul>
            </div>
        </x-card>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-card title="Kartla ödeme (iyzico)" :description="$hasKeys['iyzico_api_key'] && $hasKeys['iyzico_secret_key'] ? 'Anahtarlar kayıtlı.' : 'iyzico üye işyeri API anahtarlarını girin (mağazadakiyle aynı olabilir). Test için sandbox anahtarları kullanılabilir.'">
                <div class="space-y-5">
                    <x-toggle name="card_enabled" label="Kartla ödeme açık" :checked="$s->card_enabled" hint="Bayi kart bilgisini iyzico'nun güvenli sayfasına girer; kart bilgisi sistemimize gelmez." />
                    @error('card_enabled')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                    <x-field name="iyzico_mode" label="Ortam" :options="['sandbox' => 'Test (sandbox)', 'live' => 'Canlı']" :value="$s->iyzico_mode" class="max-w-48" />
                    @foreach (['iyzico_api_key' => 'API anahtarı', 'iyzico_secret_key' => 'Gizli anahtar'] as $key => $label)
                        <x-field :name="$key" :label="$label" type="password" autocomplete="new-password" :hint="$hasKeys[$key] ? 'Kayıtlı. Değiştirmek için yenisini girin; boş bırakırsanız korunur.' : 'Kayıtlı değil.'" />
                    @endforeach
                    @if ($hasKeys['iyzico_api_key'] || $hasKeys['iyzico_secret_key'])
                        <button form="anahtarlari-sil" class="text-sm text-danger hover:underline">Kayıtlı anahtarları sil</button>
                    @endif
                    <fieldset>
                        <legend class="text-sm font-medium">Taksit seçenekleri</legend>
                        <div class="mt-2 flex flex-wrap gap-4">
                            @foreach (\App\Domain\Payments\PaymentSettings::INSTALLMENTS as $n)
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="installments[]" value="{{ $n }}" @checked(in_array($n, $installments, true)) @disabled($n === 1) class="size-4 rounded text-primary"> {{ $n === 1 ? 'Tek çekim' : $n.' taksit' }}</label>
                            @endforeach
                            <input type="hidden" name="installments[]" value="1">
                        </div>
                        <p class="mt-1 text-xs text-muted">Taksit farkını iyzico'daki ayarlarınıza göre bayi öder.</p>
                    </fieldset>
                </div>
            </x-card>

            <x-card title="Havale / EFT" description="Bayi, onaylanan siparişinde hesap bilgilerini görür ve sipariş numarasını açıklamaya yazarak öder. Para hesabınıza geçince siparişte “Ödeme alındı” ile kaydedersiniz.">
                <div class="space-y-5">
                    <x-toggle name="transfer_enabled" label="Havale / EFT ile ödeme açık" :checked="$s->transfer_enabled" />
                    @include('partials.bank-accounts-field', ['bankRows' => $bankRows, 'banks' => $banks, 'max' => \App\Domain\Payments\PaymentSettings::MAX_ACCOUNTS, 'hint' => "IBAN'ı yazın; banka ve logosu kendiliğinden tanınır. Bayiye bu sırayla gösterilir."])
                </div>
            </x-card>

            <x-card title="Teslimat ücreti" description="Siparişleri Eskişehir'de siz teslim edersiniz; kargo yoktur. İsterseniz teslimat ücreti alabilirsiniz. 0 bırakırsanız ücret alınmaz.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="delivery_fee" label="Teslimat ücreti (₺)" :value="$money($s->delivery_fee)" inputmode="decimal" />
                    <x-field name="delivery_free_over" label="Bu tutar ve üstü ücretsiz (₺)" :value="$money($s->delivery_free_over)" inputmode="decimal" hint="Boşsa her siparişte ücret alınır." />
                </div>
                <p class="mt-3 text-xs text-muted">Ücret sipariş toplamına eklenir, onayda onaylanan tutara göre yeniden hesaplanır ve carisine yazılır.</p>
            </x-card>
        </div>

        <x-button>Kaydet</x-button>
    </form>
    <form id="anahtarlari-sil" method="POST" action="{{ route('business.payments.keys.destroy') }}" data-confirm="Kayıtlı iyzico anahtarları silinsin mi? Kartla ödeme kapanır.">@csrf @method('DELETE')</form>
</x-layouts.panel>
