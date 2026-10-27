@php $s = $settings; $money = fn ($v) => $v === null ? '' : number_format((float) $v, 2, ',', ''); @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Ödeme ve Teslimat">
    <x-page-header title="Ödeme ve Teslimat" subtitle="Teslimat ücretleri, Eskişehir mağaza teslimatı, kapıda ödeme kuralları, kartla ödeme (iyzico) ve SMS (Netgsm). Tutarlar KDV dahildir." />

    <form method="POST" action="{{ route('store_admin.payments.update') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="grid gap-6 xl:grid-cols-2">
            <x-card title="Kargo" description="Tüm Türkiye'ye kargo ile teslimat. Firma ücretleri Ayarlar → Kargo Firmaları ekranından girilir; müşteri firmayı fiyatıyla seçer.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="shipping_cargo_fee" label="Yedek kargo ücreti (₺)" :value="$money($s['shipping_cargo_fee'])" inputmode="decimal" hint="Yalnızca hiçbir kargo firmasına ücret girilmemişse uygulanır." />
                    <x-field name="shipping_free_threshold" label="Ücretsiz kargo sınırı (₺)" :value="$money($s['shipping_free_threshold'])" inputmode="decimal" hint="Bu tutar ve üzeri siparişlerde kargo ücretsiz; müşteri firma seçmez, gönderirken siz seçersiniz. Boşsa uygulanmaz." />
                    <x-field name="cargo_cutoff" type="time" label="Aynı gün kargo saati" :value="$s['cargo_cutoff']" hint="Hafta içi bu saatten önce verilen siparişler aynı gün kargoya verilir; ürün sayfasında yazılır. Boşsa gösterilmez." />
                </div>
            </x-card>

            <x-card title="Mağaza teslimatı (Eskişehir)" description="Kendi aracınızla teslimat. Kapıda ödeme yalnızca bu teslimatla kullanılabilir.">
                <div class="space-y-5">
                    <x-toggle name="local_delivery_enabled" label="Mağaza teslimatı açık" :checked="$s['local_delivery_enabled'] === '1'" />
                    <x-field name="local_delivery_fee" label="Teslimat ücreti (₺)" :value="$money($s['local_delivery_fee'])" inputmode="decimal" class="max-w-48" />
                    <fieldset>
                        <legend class="text-sm font-medium">Teslimat yapılan ilçeler</legend>
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ($districts as $id => $name)
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="local_districts[]" value="{{ $id }}" @checked(in_array($id, $selectedDistricts, true)) class="size-4 rounded text-primary"> {{ $name }}</label>
                            @endforeach
                        </div>
                        @error('local_districts')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                    </fieldset>
                </div>
            </x-card>

            <x-card title="Kapıda ödeme" description="Yalnızca Eskişehir'deki mağaza teslimatında. Müşteri telefonuna gelen SMS koduyla siparişi doğrular.">
                <div class="space-y-5">
                    <x-toggle name="cod_enabled" label="Kapıda ödeme açık" :checked="$s['cod_enabled'] === '1'" :hint="$smsLive ? null : 'SMS şu anda geliştirme modunda (günlüğe yazılıyor). Canlıda Netgsm bilgileri girilmeden açılamaz.'" />
                    @error('cod_enabled')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-field name="cod_min" label="En düşük tutar (₺)" :value="$money($s['cod_min'])" inputmode="decimal" />
                        <x-field name="cod_max" label="En yüksek tutar (₺)" :value="$money($s['cod_max'])" inputmode="decimal" />
                        <x-field name="cod_fee" label="Hizmet bedeli (₺)" :value="$money($s['cod_fee'])" inputmode="decimal" />
                    </div>
                    <x-field name="cod_max_open" label="Aynı telefonda en fazla açık sipariş" type="number" min="1" max="10" :value="$s['cod_max_open']" class="max-w-48" />
                    <x-toggle name="cod_first_order_approval" label="İlk siparişte telefonla teyit (sipariş 'Onay bekliyor' düşer)" :checked="$s['cod_first_order_approval'] === '1'" />
                </div>
            </x-card>

            <x-card title="İade (cayma hakkı)" description="Müşteri teslimden sonra bu süre içinde sipariş sayfasından iade talebi açabilir. Yasal en kısa süre 14 gündür.">
                <div class="space-y-5">
                    <x-field name="return_days" label="İade süresi (gün)" type="number" min="14" max="90" :value="$s['return_days']" required class="max-w-48" />
                    <x-field name="return_address" label="İade gönderim adresi" textarea maxlength="300" :value="$s['return_address']" hint="Onaylanan iade e-postasında müşteriye gösterilir. Boşsa Mağaza Ayarları'ndaki adres kullanılır." />
                </div>
            </x-card>

            <x-card title="Kartla ödeme (iyzico)" :description="$iyzicoReady ? 'Anahtarlar kayıtlı.' : 'iyzico üye işyeri hesabı açıldığında API anahtarlarını girin. Test için iyzico sandbox anahtarları kullanılabilir.'">
                <div class="space-y-5">
                    <x-toggle name="card_enabled" label="Kartla ödeme açık" :checked="$s['card_enabled'] === '1'" hint="Anahtarlar girilmeden müşteriye gösterilmez." />
                    <x-field name="iyzico_mode" label="Ortam" :options="['sandbox' => 'Test (sandbox)', 'live' => 'Canlı']" :value="$s['iyzico_mode']" class="max-w-48" />
                    @foreach (['iyzico_api_key' => 'API anahtarı', 'iyzico_secret_key' => 'Gizli anahtar'] as $key => $label)
                        <div class="flex items-end gap-3">
                            <x-field :name="$key" :label="$label" type="password" autocomplete="off" class="flex-1" :hint="$secrets[$key] ? 'Kayıtlı. Değiştirmek için yenisini girin; boş bırakırsanız korunur.' : 'Kayıtlı değil.'" />
                            @if ($secrets[$key])<button form="sil-{{ $key }}" class="mb-7 text-sm text-danger hover:underline">Sil</button>@endif
                        </div>
                    @endforeach
                    <div class="rounded-lg bg-surface p-3 text-sm">
                        <p class="font-medium">Bildirim adresi (webhook)</p>
                        <p class="mt-1 text-muted">iyzico üye işyeri panelinde <span class="font-medium">Ayarlar → Bildirim Ayarları</span> bölümüne girin. Müşteri ödeme sonrası sayfayı kapatsa da sipariş hemen sonuçlanır.</p>
                        <p class="mt-2 break-all font-mono text-xs">{{ route('store.payment.webhook') }}</p>
                    </div>
                </div>
            </x-card>

            <x-card title="Havale / EFT" description="İsteğe bağlı. Müşteri siparişten sonra hesap bilgilerini görür ve sipariş numarasını açıklamaya yazarak ödeme yapar. Ödeme hesaba geçince siparişte “Havale alındı” ile onaylarsınız.">
                <div class="space-y-5">
                    <x-toggle name="transfer_enabled" label="Havale / EFT ile ödeme açık" :checked="$s['transfer_enabled'] === '1'" />
                    @php
                        $rowErrors = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'transfer_accounts.'))
                            ->mapWithKeys(fn ($m, $k) => [substr($k, strlen('transfer_accounts.')) => $m[0]])->all();
                        $oldRows = array_values(array_map(fn ($r) => ['bank' => (string) ($r['bank'] ?? ''), 'holder' => (string) ($r['holder'] ?? ''), 'iban' => (string) ($r['iban'] ?? '')], array_filter((array) old('transfer_accounts', []), 'is_array')));
                    @endphp
                    <fieldset x-data="bankAccounts(@alpine(old('transfer_accounts') !== null ? $oldRows : $bankRows), @alpine((object) $banks), @alpine((object) $rowErrors), @alpine(\App\Domain\Store\Payments\BankTransfer::MAX_ACCOUNTS))">
                        <legend class="text-sm font-medium">Banka hesapları</legend>
                        <p class="mt-1 text-xs text-muted">IBAN'ı yazın; banka ve logosu kendiliğinden tanınır. Müşteriye siparişte bu sırayla gösterilir.</p>
                        @error('transfer_accounts')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror

                        <div class="mt-3 space-y-3">
                            <template x-for="(row, i) in rows" :key="i">
                                <div class="rounded-xl border border-line p-4">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-11 w-32 shrink-0 items-center justify-center rounded-lg bg-[#fff] px-2 ring-1 ring-line">
                                            <img x-show="known(row)" :src="bankLogo(row)" :alt="bankName(row)" class="max-h-7 max-w-full object-contain">
                                            <span x-show="!known(row)" class="flex size-8 items-center justify-center rounded-full bg-surface text-xs font-bold text-muted" x-text="initials(row)"></span>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold" x-text="title(row)"></p>
                                            <p class="text-xs text-muted" x-text="status(row)"></p>
                                        </div>
                                        <button type="button" class="rounded-lg p-2 text-muted hover:bg-danger-soft hover:text-danger" @click="remove(i)" aria-label="Hesabı kaldır" title="Hesabı kaldır"><x-icon name="trash" class="size-4" /></button>
                                    </div>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        <label class="block">
                                            <span class="mb-1 block text-xs font-medium text-muted">IBAN</span>
                                            <input type="text" class="field-input font-mono tracking-wide" :name="'transfer_accounts[' + i + '][iban]'" x-model="row.iban" @blur="tidy(row)"
                                                maxlength="40" inputmode="text" autocomplete="off" spellcheck="false" placeholder="TR00 0000 0000 0000 0000 0000 00">
                                            <span class="mt-1 block text-xs text-danger" x-show="error(i, 'iban')" x-text="error(i, 'iban')"></span>
                                        </label>
                                        <label class="block">
                                            <span class="mb-1 block text-xs font-medium text-muted">Hesap sahibi</span>
                                            <input type="text" class="field-input" :name="'transfer_accounts[' + i + '][holder]'" x-model="row.holder" maxlength="120" placeholder="Ad soyad veya şirket unvanı">
                                            <span class="mt-1 block text-xs text-danger" x-show="error(i, 'holder')" x-text="error(i, 'holder')"></span>
                                        </label>
                                        <label class="block sm:col-span-2" x-show="unknown(row)">
                                            <span class="mb-1 block text-xs font-medium text-muted">Banka adı</span>
                                            <input type="text" class="field-input" :name="'transfer_accounts[' + i + '][bank]'" x-model="row.bank" maxlength="60" placeholder="Bankanın adı">
                                            <span class="mt-1 block text-xs text-danger" x-show="error(i, 'bank')" x-text="error(i, 'bank')"></span>
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <button type="button" class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline disabled:cursor-not-allowed disabled:opacity-50" @click="add()" :disabled="!canAdd()">
                            <x-icon name="plus" class="size-4" /> Hesap ekle
                        </button>
                        <span class="ml-2 text-xs text-muted">(en fazla {{ \App\Domain\Store\Payments\BankTransfer::MAX_ACCOUNTS }})</span>
                    </fieldset>
                    <x-field name="transfer_days" label="Ödeme süresi (gün)" type="number" min="1" max="7" :value="$s['transfer_days']" required class="max-w-48"
                        hint="Bu sürede ödenmeyen sipariş kendiliğinden iptal edilir ve ürünler stoğa döner." />
                </div>
            </x-card>

            <x-card title="SMS (Netgsm)" :description="$smsLive ? 'Netgsm bilgileri kayıtlı; SMS gerçekten gönderilir.' : 'Netgsm hesabı açıldığında bilgileri girip sürücüyü Netgsm yapın.'">
                <div class="space-y-5">
                    <x-field name="sms_driver" label="Gönderim" :options="['log' => 'Geliştirme (SMS gönderilmez, günlüğe yazılır)', 'netgsm' => 'Netgsm']" :value="$s['sms_driver']" />
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field name="netgsm_usercode" label="Kullanıcı kodu" :value="$s['netgsm_usercode']" autocomplete="off" />
                        <x-field name="netgsm_header" label="Mesaj başlığı" :value="$s['netgsm_header']" maxlength="20" hint="Netgsm'de onaylı başlık (ör. YAPBOZ)." />
                    </div>
                    <div class="flex items-end gap-3">
                        <x-field name="netgsm_password" label="Şifre" type="password" autocomplete="off" class="flex-1" :hint="$secrets['netgsm_password'] ? 'Kayıtlı. Boş bırakırsanız korunur.' : 'Kayıtlı değil.'" />
                        @if ($secrets['netgsm_password'])<button form="sil-netgsm_password" class="mb-7 text-sm text-danger hover:underline">Sil</button>@endif
                    </div>
                </div>
            </x-card>
        </div>
        <x-button>Kaydet</x-button>
    </form>
    @foreach ($secrets as $key => $exists)
        @if ($exists)<form id="sil-{{ $key }}" method="POST" action="{{ route('store_admin.payments.secret.destroy', $key) }}" data-confirm="Kayıtlı anahtar silinsin mi?">@csrf @method('DELETE')</form>@endif
    @endforeach

    <div class="mt-8 grid gap-6 xl:grid-cols-2">
        <x-card title="Kapıda ödeme engel listesi" description="Teslim almayan müşterilerin telefonları ve teslimat adresleri. Bu numaralarla veya bu adreslere yalnızca kartla ödeme yapılabilir." :padding="false">
            @if ($blocklist->isEmpty())
                <p class="p-5 text-sm text-muted">Liste boş.</p>
            @else
                <ul class="divide-y divide-line text-sm">
                    @foreach ($blocklist as $block)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <span><span class="font-medium">{{ \App\Domain\Store\Customers\PhoneNumber::format($block->phone) }}</span><span class="block text-xs text-muted">{{ $block->reason }}</span>@if ($block->address_label)<span class="block text-xs text-muted">Adres: {{ $block->address_label }}</span>@endif</span>
                            <form method="POST" action="{{ route('store_admin.cod-blocks.destroy', $block) }}" data-confirm="Engel kaldırılsın mı?">@csrf @method('DELETE')<button class="text-sm text-primary hover:underline">Kaldır</button></form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
        <x-card title="Son SMS'ler" :padding="false">
            @if ($smsLog->isEmpty())
                <p class="p-5 text-sm text-muted">Henüz SMS yok.</p>
            @else
                <ul class="divide-y divide-line text-sm">
                    @foreach ($smsLog as $sms)
                        <li class="px-5 py-3"><span class="font-medium">{{ \App\Domain\Store\Customers\PhoneNumber::format($sms->phone) }}</span>
                            <x-badge :tone="$sms->status === 'sent' ? 'success' : 'danger'" class="ml-2">{{ $sms->driver }} · {{ $sms->status }}</x-badge>
                            <span class="block text-xs text-muted">{{ $sms->body }}@if ($sms->error) — {{ $sms->error }}@endif</span></li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-layouts.panel>
