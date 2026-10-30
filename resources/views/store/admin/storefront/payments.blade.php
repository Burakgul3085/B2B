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
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field name="return_carrier" label="İade kargo firması" :value="$s['return_carrier']" maxlength="40" placeholder="ör. Aras Kargo" />
                        <x-field name="return_code" label="İade anlaşma kodu" :value="$s['return_code']" maxlength="40" autocomplete="off" />
                    </div>
                    <p class="text-xs text-muted">Sözleşmeye göre sizin bildirdiğiniz kargo firmasıyla yapılan iadelerde kargo ücreti size aittir. Kod girilirse müşteriye "bu firmanın şubesine kodla ücretsiz gönderin; başka firmayla gönderirseniz ücret size aittir" denir. Kod girilmezse müşteri gönderici ödemeli gönderir ve ödediği kargo ücreti iadesine eklenir.</p>
                </div>
            </x-card>

            <x-card title="Kartla ödeme (iyzico)" :description="$iyzicoReady ? 'Anahtarlar kayıtlı.' : 'iyzico üye işyeri hesabı açıldığında API anahtarlarını girin. Test için iyzico sandbox anahtarları kullanılabilir.'">
                <div class="space-y-5">
                    <x-toggle name="card_enabled" label="Kartla ödeme açık" :checked="$s['card_enabled'] === '1'" hint="Anahtarlar girilmeden müşteriye gösterilmez." />
                    <x-field name="iyzico_mode" label="Ortam" :options="['sandbox' => 'Test (sandbox)', 'live' => 'Canlı']" :value="$s['iyzico_mode']" class="max-w-48" />
                    @foreach (['iyzico_api_key' => 'API anahtarı', 'iyzico_secret_key' => 'Gizli anahtar'] as $key => $label)
                        <div class="flex items-end gap-3">
                            <x-field :name="$key" :label="$label" type="password" autocomplete="new-password" class="flex-1" :hint="$secrets[$key] ? 'Kayıtlı. Değiştirmek için yenisini girin; boş bırakırsanız korunur.' : 'Kayıtlı değil.'" />
                            @if ($secrets[$key])<button form="sil-{{ $key }}" class="mb-7 text-sm text-danger hover:underline">Sil</button>@endif
                        </div>
                    @endforeach
                    <x-field name="iyzico_signature" label="iyzico yanıt imzası" :options="['monitor' => 'İzle (tutmazsa uyar, ödemeyi kabul et)', 'enforce' => 'Zorunlu (tutmazsa ödemeyi kabul etme)']" :value="$s['iyzico_signature']"
                        hint="Ödeme sonucu zaten doğrudan iyzico'dan sorgulanır; imza ek bir kontroldür. Bir deneme ödemesinde imza uyarısı gelmediyse Zorunlu yapabilirsiniz." />
                    <div class="grid gap-5 sm:grid-cols-[1fr_auto]">
                        <x-field name="iyzico_badge_token" label="Korumalı Alışveriş token" :value="$s['iyzico_badge_token']" autocomplete="off" spellcheck="false" placeholder="2e548140-482d-…"
                            hint="iyzico panelinde Korumalı Alışveriş → Korumalı Alışveriş Script bölümündeki koddaki token: '…' değeri. Girilince vitrinin her sayfasında iyzico Korumalı Alışveriş rozeti görünür; boş bırakırsanız gösterilmez." />
                        <x-field name="iyzico_badge_position" label="Rozet konumu" :options="['bottomLeft' => 'Sol alt köşe', 'bottomRight' => 'Sağ alt köşe']" :value="$s['iyzico_badge_position']" />
                    </div>
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
                    @include('partials.bank-accounts-field', ['bankRows' => $bankRows, 'banks' => $banks, 'max' => \App\Domain\Store\Payments\BankTransfer::MAX_ACCOUNTS, 'hint' => "IBAN'ı yazın; banka ve logosu kendiliğinden tanınır. Müşteriye siparişte bu sırayla gösterilir."])
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
                        <x-field name="netgsm_password" label="Şifre" type="password" autocomplete="new-password" class="flex-1" :hint="$secrets['netgsm_password'] ? 'Kayıtlı. Boş bırakırsanız korunur.' : 'Kayıtlı değil.'" />
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
