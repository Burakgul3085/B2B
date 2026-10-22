@php
    use App\Domain\Store\Customers\PhoneNumber;
    use App\Domain\Support\Money;

    $default = $addresses->firstWhere('is_default', true) ?? $addresses->first();
    $alpine = [
        'addressMode' => old('address_id') || ($addresses->isNotEmpty() && ! old('address')) ? 'saved' : 'new',
        'addressId' => (int) old('address_id', $default?->id),
        'saved' => $addresses->map(fn ($a) => ['id' => $a->id, 'province' => $a->province_id, 'district' => $a->district_id])->values(),
        'province' => (int) old('address.province_id') ?: '',
        'district' => (int) old('address.district_id') ?: '',
        'districts' => [],
        'delivery' => old('delivery_method', 'cargo'),
        'payment' => old('payment_method', $rules['cardAvailable'] ? 'card' : 'cod'),
        'billingType' => old('billing_type', 'individual'),
        'billingSame' => old('billing_same', '1') === '1',
    ] + $rules;
@endphp
<x-layouts.store title="Ödeme" :noindex="true">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Siparişi tamamla</h1>

        <form method="POST" action="{{ route('store.checkout.store') }}" class="mt-6 grid gap-8 lg:grid-cols-3"
            x-data="checkout(@js($alpine))">
            @csrf
            <x-store.bot-trap />
            <div class="space-y-6 lg:col-span-2">
                @if ($errors->has('coupon') || $couponError)
                    <p role="alert" class="rounded-xl border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $errors->first('coupon') ?: $couponError }}
                        <a href="{{ route('store.cart') }}" class="font-medium underline">Sepete dön</a></p>
                @elseif ($errors->any() && ! $errors->has('cart'))
                    <p class="rounded-xl border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">Lütfen işaretli alanları düzeltin.</p>
                @endif

                {{-- 1. İletişim --}}
                <section class="rounded-2xl border border-line p-5 sm:p-6">
                    <h2 class="font-semibold">1. İletişim</h2>
                    @if ($customer)
                        <p class="mt-3 text-sm">{{ $customer->fullName() }} · {{ $customer->email }}</p>
                    @else
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <x-field name="email" label="E-posta" type="email" required autocomplete="email" hint="Sipariş bilgileri bu adrese gönderilir." />
                        </div>
                        <p class="mt-3 text-sm text-muted">Üye misiniz? <a href="{{ route('store.login') }}" class="font-medium text-primary hover:underline">Giriş yapın</a> — siparişlerinizi hesabınızdan takip edebilirsiniz.</p>
                    @endif
                </section>

                {{-- 2. Teslimat adresi --}}
                <section class="rounded-2xl border border-line p-5 sm:p-6">
                    <h2 class="font-semibold">2. Teslimat adresi</h2>
                    @if ($addresses->isNotEmpty())
                        <div class="mt-4 grid gap-3 sm:grid-cols-2" x-show="addressMode === 'saved'">
                            @foreach ($addresses as $address)
                                <label class="flex cursor-pointer gap-3 rounded-xl border p-4 text-sm" :class="addressMode === 'saved' && addressId === {{ $address->id }} ? 'border-primary ring-1 ring-primary' : 'border-line'">
                                    <input type="radio" name="address_id" value="{{ $address->id }}" x-model.number="addressId" :disabled="addressMode !== 'saved'" class="mt-0.5 size-4 text-primary">
                                    <span>
                                        <span class="font-semibold">{{ $address->title }}</span>
                                        <span class="mt-1 block">{{ $address->first_name }} {{ $address->last_name }} · {{ PhoneNumber::format($address->phone) }}</span>
                                        <span class="mt-0.5 block text-muted">{{ $address->oneLine() }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('address_id')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                        <button type="button" class="mt-3 text-sm font-medium text-primary hover:underline" @click="addressMode = addressMode === 'saved' ? 'new' : 'saved'"
                            x-text="addressMode === 'saved' ? '+ Yeni adres gir' : '← Kayıtlı adreslerim'"></button>
                    @endif

                    <div class="mt-4 space-y-4" x-show="addressMode === 'new'" @if ($addresses->isNotEmpty()) x-cloak @endif>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field name="address[first_name]" label="Ad" :value="$customer?->first_name" required maxlength="60" ::disabled="addressMode !== 'new'" />
                            <x-field name="address[last_name]" label="Soyad" :value="$customer?->last_name" required maxlength="60" ::disabled="addressMode !== 'new'" />
                        </div>
                        <x-field name="address[phone]" label="Cep telefonu" type="tel" :value="PhoneNumber::format($customer?->phone)" required placeholder="05xx xxx xx xx" ::disabled="addressMode !== 'new'"
                            hint="Teslimatta ulaşabilmemiz için; kapıda ödemede doğrulama kodu bu numaraya gönderilir." />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="c_province" class="mb-1.5 block text-sm font-medium">İl<span class="text-danger"> *</span></label>
                                <select id="c_province" name="address[province_id]" x-model.number="province" @change="loadDistricts(false)" :disabled="addressMode !== 'new'" class="field-input">
                                    <option value="">Seçin</option>
                                    @foreach ($provinces as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                </select>
                                @error('address.province_id')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="c_district" class="mb-1.5 block text-sm font-medium">İlçe<span class="text-danger"> *</span></label>
                                <select id="c_district" name="address[district_id]" x-model.number="district" :disabled="addressMode !== 'new' || ! province" class="field-input">
                                    <option value="" x-text="province ? 'Seçin' : 'Önce il seçin'">Önce il seçin</option>
                                    <template x-for="d in districts" :key="d.id"><option :value="d.id" x-text="d.name" :selected="d.id == district"></option></template>
                                </select>
                                @error('address.district_id')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <x-field name="address[neighborhood]" label="Mahalle" maxlength="100" ::disabled="addressMode !== 'new'" />
                        <x-field name="address[address_line]" label="Açık adres" textarea required maxlength="300" ::disabled="addressMode !== 'new'" hint="Cadde/sokak, bina no, daire no." />
                        @if ($customer)
                            <div class="flex flex-wrap items-end gap-4">
                                <x-toggle name="save_address" label="Bu adresi adreslerime kaydet" :checked="true" />
                                <x-field name="address[title]" label="Adres başlığı" placeholder="Ev, İş…" maxlength="40" class="w-48" ::disabled="addressMode !== 'new'" />
                            </div>
                        @endif
                    </div>
                </section>

                {{-- 3. Teslimat yöntemi --}}
                <section class="rounded-2xl border border-line p-5 sm:p-6">
                    <h2 class="font-semibold">3. Teslimat</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="flex cursor-pointer gap-3 rounded-xl border p-4 text-sm" :class="delivery === 'cargo' ? 'border-primary ring-1 ring-primary' : 'border-line'">
                            <input type="radio" name="delivery_method" value="cargo" x-model="delivery" class="mt-0.5 size-4 text-primary">
                            <span><span class="font-semibold">Kargo</span><span class="block text-muted" x-text="money(cargoFee) === money(0) ? 'Ücretsiz' : money(cargoFee)"></span>
                                <template x-if="freeThreshold && Number(cargoFee) > 0"><span class="block text-xs text-muted" x-text="money(freeThreshold) + ' ve üzeri ücretsiz'"></span></template></span>
                        </label>
                        <label class="flex gap-3 rounded-xl border p-4 text-sm" :class="! isLocal ? 'cursor-not-allowed opacity-50 border-line' : (delivery === 'local' ? 'cursor-pointer border-primary ring-1 ring-primary' : 'cursor-pointer border-line')">
                            <input type="radio" name="delivery_method" value="local" x-model="delivery" :disabled="! isLocal" class="mt-0.5 size-4 text-primary">
                            <span><span class="font-semibold">Mağaza teslimatı</span>
                                <span class="block text-muted" x-text="isLocal ? (Number(localFee) > 0 ? money(localFee) : 'Ücretsiz') : 'Yalnızca Eskişehir içi'"></span>
                                <span class="block text-xs text-muted">Kendi aracımızla, kapıda ödeme seçeneğiyle</span></span>
                        </label>
                    </div>
                    @error('delivery_method')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                </section>

                {{-- 4. Ödeme --}}
                <section class="rounded-2xl border border-line p-5 sm:p-6">
                    <h2 class="font-semibold">4. Ödeme</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="flex gap-3 rounded-xl border p-4 text-sm" :class="! cardAvailable ? 'cursor-not-allowed opacity-50 border-line' : (payment === 'card' ? 'cursor-pointer border-primary ring-1 ring-primary' : 'cursor-pointer border-line')">
                            <input type="radio" name="payment_method" value="card" x-model="payment" :disabled="! cardAvailable" class="mt-0.5 size-4 text-primary">
                            <span><span class="font-semibold">Kredi / banka kartı</span>
                                <span class="block text-muted" x-text="cardAvailable ? 'iyzico güvenli ödeme sayfasında; taksit seçenekleriyle' : 'Şu anda kullanılamıyor'"></span></span>
                        </label>
                        <label class="flex gap-3 rounded-xl border p-4 text-sm" :class="! codAllowed ? 'cursor-not-allowed opacity-50 border-line' : (payment === 'cod' ? 'cursor-pointer border-primary ring-1 ring-primary' : 'cursor-pointer border-line')">
                            <input type="radio" name="payment_method" value="cod" x-model="payment" :disabled="! codAllowed" class="mt-0.5 size-4 text-primary">
                            <span><span class="font-semibold">Kapıda ödeme</span>
                                <span class="block text-muted" x-text="codReason"></span></span>
                        </label>
                    </div>
                    @error('payment_method')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror

                    <div class="mt-6 border-t border-line pt-5">
                        <p class="text-sm font-semibold">Fatura bilgisi</p>
                        <div class="mt-3 flex gap-6 text-sm">
                            <label class="flex items-center gap-2"><input type="radio" name="billing_type" value="individual" x-model="billingType" class="size-4 text-primary"> Bireysel</label>
                            <label class="flex items-center gap-2"><input type="radio" name="billing_type" value="corporate" x-model="billingType" class="size-4 text-primary"> Kurumsal</label>
                        </div>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2" x-show="billingType === 'individual'">
                            <x-field name="billing_tckn" label="T.C. kimlik no (isteğe bağlı)" inputmode="numeric" maxlength="11" hint="Fatura için; boş bırakılabilir." />
                        </div>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2" x-show="billingType === 'corporate'" x-cloak>
                            <x-field name="billing_company" label="Firma unvanı" maxlength="160" class="sm:col-span-2" />
                            <x-field name="billing_tax_office" label="Vergi dairesi" maxlength="80" />
                            <x-field name="billing_tax_number" label="Vergi numarası" inputmode="numeric" maxlength="10" />
                        </div>
                        <label class="mt-4 flex items-center gap-2 text-sm">
                            <input type="hidden" name="billing_same" value="0">
                            <input type="checkbox" name="billing_same" value="1" x-model="billingSame" class="size-4 rounded text-primary"> Fatura adresi teslimat adresiyle aynı
                        </label>
                        <div x-show="! billingSame" x-cloak class="mt-3"><x-field name="billing_address" label="Fatura adresi" textarea maxlength="500" /></div>
                    </div>
                </section>

                <section class="rounded-2xl border border-line p-5 sm:p-6">
                    <x-field name="note" label="Sipariş notu (isteğe bağlı)" textarea maxlength="500" />
                </section>
            </div>

            {{-- Özet --}}
            <aside class="h-fit space-y-4 rounded-2xl border border-line p-6 lg:sticky lg:top-40">
                <h2 class="text-lg font-semibold">Sipariş özeti</h2>
                <ul class="max-h-64 space-y-3 overflow-y-auto text-sm">
                    @foreach ($summary->orderable() as $line)
                        <li class="flex justify-between gap-3">
                            <span class="min-w-0"><span class="line-clamp-2">{{ $line->product->name }}</span>
                                <span class="text-xs text-muted">@if ($line->variant->option_value){{ $line->variant->option_value }} · @endif{{ $line->quantity() }} adet</span></span>
                            <span class="shrink-0 tabular-nums">{{ Money::format($line->total()) }}</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="space-y-2 border-t border-line pt-4 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Ürünler</dt><dd class="tabular-nums">{{ Money::format($summary->subtotal()) }}</dd></div>
                    @if ($discount && bccomp($discount->amount, '0', 2) > 0)
                        <div class="flex justify-between text-green-800"><dt>{{ $discount->label() }}</dt><dd class="tabular-nums">−{{ Money::format($discount->amount) }}</dd></div>
                    @endif
                    @if ($discount?->freeShipping)
                        <p class="text-xs text-green-800">{{ $discount->label() }}: teslimat ücretsiz.</p>
                    @endif
                    <div class="flex justify-between"><dt class="text-muted">Teslimat</dt><dd class="tabular-nums" x-text="Number(shippingFee) > 0 ? money(shippingFee) : 'Ücretsiz'"></dd></div>
                    <div class="flex justify-between" x-show="payment === 'cod' && Number(codFee) > 0" x-cloak><dt class="text-muted">Kapıda ödeme bedeli</dt><dd class="tabular-nums" x-text="money(codFee)"></dd></div>
                </dl>
                <div class="flex justify-between border-t border-line pt-4 text-base font-bold"><span>Toplam</span><span class="tabular-nums" x-text="money(total)"></span></div>
                <p class="text-xs text-muted">Fiyatlara KDV dahildir.</p>

                <label class="flex items-start gap-3 rounded-xl bg-surface p-3 text-xs leading-relaxed">
                    <input type="checkbox" name="consent" value="1" @checked(old('consent')) required class="mt-0.5 size-4 shrink-0 rounded text-primary">
                    <span><a href="{{ route('store.legal', 'on-bilgilendirme') }}" target="_blank" rel="noopener" class="font-medium text-primary underline">Ön Bilgilendirme Formu</a>'nu ve
                        <a href="{{ route('store.legal', 'mesafeli-satis') }}" target="_blank" rel="noopener" class="font-medium text-primary underline">Mesafeli Satış Sözleşmesi</a>'ni okudum, onaylıyorum.</span>
                </label>
                @error('consent')<p class="text-sm text-danger">{{ $message }}</p>@enderror

                <button class="w-full rounded-xl bg-primary px-6 py-3 font-semibold text-white transition hover:bg-primary-hover disabled:opacity-50" :disabled="! payment">
                    <span x-text="payment === 'cod' ? 'Siparişi onayla' : 'Ödemeye geç'">Ödemeye geç</span>
                </button>
                <p class="text-center text-xs text-muted" x-text="payment === 'cod' ? 'Telefonunuza gelen kodla siparişi doğrulayacaksınız.' : 'Kart bilgileriniz iyzico güvenli ödeme sayfasında alınır; mağazamızda saklanmaz.'"></p>
            </aside>
        </form>
    </div>

</x-layouts.store>
