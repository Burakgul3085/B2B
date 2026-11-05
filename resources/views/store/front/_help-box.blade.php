{{--
    "Aradığınızı bulamadınız mı?" kutusu (Mağaza yönetimi → Ödeme Kodları'ndan düzenlenir).
    $full: sonuçsuz aramada büyük gösterim; aksi hâlde ürün listelerinin altında küçük kutu.
    İçerik: başlık + mesaj, mağaza iletişim bilgileri ve ödeme kodu alanı (kod girilince tutar sunucuda kodun kaydından gelir).
--}}
@php
    use App\Domain\Store\PaymentCodes\PaymentCodeSettings;
    use App\Domain\Store\Storefront\ProductPage;
    use App\Domain\Store\Storefront\StorePhones;
    use App\Models\Store\StoreSetting;

    $full ??= false;
    $box = app(PaymentCodeSettings::class)->all();
    $contact = StoreSetting::values();
    $phones = StorePhones::list($contact);
    $whatsapp = ProductPage::whatsapp($contact['whatsapp'] ?? null);
    $email = trim((string) ($contact['email'] ?? ''));
@endphp
@if ($box['box_enabled'])
    <section @class(['help-box group relative isolate overflow-clip rounded-3xl p-[2px] shadow-sm', 'mt-10' => ! $full, 'mt-5' => $full]) aria-labelledby="yardim-kutusu">
        <div @class(['relative overflow-clip rounded-[calc(1.5rem-2px)] bg-white', 'px-5 py-5 sm:px-7' => ! $full, 'px-6 py-10 sm:px-10' => $full])>
            {{-- Arka plan süsü: yavaşça süzülen büyük yapboz ve parıltı --}}
            <x-puzzle-logo :animated="false" mono class="puzzle-float pointer-events-none absolute -right-10 -bottom-14 -z-10 size-48 text-puzzle-blue/[0.06]" />
            <span class="help-box-shine pointer-events-none absolute inset-y-0 -left-1/3 -z-10 w-1/3" aria-hidden="true"></span>

            <div @class(['flex flex-col gap-5', 'lg:flex-row lg:items-center lg:gap-8' => ! $full, 'items-center text-center' => $full])>
                <div @class(['flex gap-4', 'items-center' => ! $full, 'flex-col items-center' => $full])>
                    <span class="relative flex shrink-0 items-center justify-center">
                        <span class="help-box-ping absolute inset-0 rounded-2xl bg-puzzle-orange/25" aria-hidden="true"></span>
                        <span @class(['relative flex items-center justify-center rounded-2xl bg-puzzle-orange-soft ring-1 ring-puzzle-orange/20', 'size-14' => ! $full, 'size-20' => $full])>
                            <x-puzzle-logo class="{{ $full ? 'size-14' : 'size-9' }}" />
                        </span>
                    </span>
                    <div class="min-w-0">
                        <p class="text-[11px] font-extrabold tracking-[0.18em] text-puzzle-orange uppercase">Fiziki mağazamızda olabilir</p>
                        <h2 id="yardim-kutusu" @class(['font-display font-extrabold tracking-tight text-ink', 'text-lg' => ! $full, 'mt-1 text-2xl sm:text-3xl' => $full])>{{ $box['box_title'] }}</h2>
                        <p @class(['mt-1 text-sm text-muted', 'max-w-xl' => ! $full, 'mx-auto max-w-2xl sm:text-base' => $full])>{{ $box['box_text'] }}</p>
                    </div>
                </div>

                <div @class(['flex flex-col gap-3', 'lg:ml-auto lg:w-[26rem] lg:shrink-0' => ! $full, 'w-full max-w-xl' => $full])>
                    @if ($phones || $whatsapp || $email)
                        <div @class(['flex flex-wrap gap-2', 'justify-center' => $full])>
                            @foreach ($phones as $phone)
                                <a href="{{ $phone['tel'] }}" class="inline-flex items-center gap-1.5 rounded-xl bg-surface px-3 py-2 text-xs font-semibold text-ink ring-1 ring-line transition hover:-translate-y-0.5 hover:ring-puzzle-blue"><x-icon name="phone" class="size-3.5 text-puzzle-blue" /> {{ $phone['number'] }}</a>
                            @endforeach
                            @if ($whatsapp)
                                <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-xl bg-puzzle-green-soft px-3 py-2 text-xs font-semibold text-puzzle-green ring-1 ring-puzzle-green/20 transition hover:-translate-y-0.5 hover:ring-puzzle-green"><x-icon name="message-circle" class="size-3.5" /> WhatsApp</a>
                            @endif
                            @if ($email !== '')
                                <a href="mailto:{{ $email }}" class="inline-flex items-center gap-1.5 rounded-xl bg-surface px-3 py-2 text-xs font-semibold text-ink ring-1 ring-line transition hover:-translate-y-0.5 hover:ring-puzzle-blue"><x-icon name="mail" class="size-3.5 text-puzzle-blue" /> E-posta</a>
                            @endif
                            <a href="{{ route('store.contact') }}" class="inline-flex items-center gap-1 px-1 py-2 text-xs font-semibold text-puzzle-blue hover:underline">İletişim <x-icon name="arrow-right" class="size-3.5" /></a>
                        </div>
                    @endif

                    @if ($box['enabled'])
                        <form method="POST" action="{{ route('store.payment-code.add') }}" class="relative">
                            @csrf
                            <x-store.bot-trap />
                            <label for="odeme-kodu-{{ $full ? 'b' : 'k' }}" class="mb-1 block text-xs font-semibold text-ink">Ödeme kodunuz mu var?</label>
                            <div class="flex gap-2">
                                <span class="relative flex-1">
                                    <x-icon name="key" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" />
                                    <input id="odeme-kodu-{{ $full ? 'b' : 'k' }}" name="code" value="{{ old('code') }}" maxlength="20" autocomplete="off" autocapitalize="characters" spellcheck="false"
                                        placeholder="ABCD-EFGH" class="w-full rounded-xl border border-line bg-white py-2.5 pr-3 pl-9 font-mono text-sm tracking-widest text-ink uppercase placeholder:tracking-widest placeholder:text-slate-300 focus:border-puzzle-orange focus:ring-2 focus:ring-puzzle-orange/20 focus:outline-none"
                                        aria-describedby="odeme-kodu-ipucu-{{ $full ? 'b' : 'k' }}">
                                </span>
                                <button class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-navy px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-2 active:scale-95">Sepete ekle</button>
                            </div>
                            <p id="odeme-kodu-ipucu-{{ $full ? 'b' : 'k' }}" class="mt-1 text-[11px] text-muted">{{ $box['code_hint'] }}</p>
                            @error('payment_code')<p role="alert" class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>@enderror
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endif
