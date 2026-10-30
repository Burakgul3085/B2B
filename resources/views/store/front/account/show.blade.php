@php
    use App\Domain\Store\Customers\PhoneNumber;
    use App\Domain\Support\Money;

    $card = 'rounded-3xl border border-line bg-white p-6 sm:p-7';
    $stats = [
        ['package', 'Siparişler', $stats['orders'], route('store.orders'), 'bg-puzzle-orange-soft text-puzzle-orange'],
        ['truck', 'Devam eden', $stats['open'], route('store.orders'), 'bg-puzzle-blue-soft text-puzzle-blue'],
        ['heart', 'Favoriler', $stats['favorites'], route('store.favorites'), 'bg-puzzle-green-soft text-puzzle-green'],
        ['map-pin', 'Adresler', $addresses->count(), '#adresler', 'bg-puzzle-yellow-soft text-puzzle-yellow'],
    ];
@endphp
<x-store.account-shell title="Hesabım" active="overview">
    {{-- Karşılama --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-xs font-bold tracking-[0.18em] text-puzzle-orange uppercase">Hesabım</p>
            <h1 class="mt-1 font-display text-3xl font-extrabold tracking-tight sm:text-4xl">Merhaba, {{ $customer->first_name }}</h1>
            <p class="mt-1 text-muted">Siparişlerinizi, adreslerinizi ve hesap bilgilerinizi buradan yönetin.</p>
        </div>
        <a href="{{ route('store.products') }}" class="inline-flex h-11 items-center gap-2 rounded-xl bg-navy px-5 text-sm font-semibold text-white shadow-md shadow-navy/20 transition hover:bg-puzzle-orange">
            Alışverişe devam et <x-icon name="arrow-right" class="size-4" />
        </a>
    </div>

    {{-- Özet kutuları --}}
    <div class="mt-8 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        @foreach ($stats as [$icon, $label, $value, $url, $tone])
            <a href="{{ $url }}" class="group rounded-3xl border border-line bg-white p-5 transition hover:-translate-y-0.5 hover:border-transparent hover:shadow-xl hover:shadow-navy/10">
                <span class="flex size-11 items-center justify-center rounded-2xl {{ $tone }}"><x-icon :name="$icon" class="size-5" /></span>
                <p class="mt-4 font-display text-3xl font-extrabold tabular-nums">{{ $value }}</p>
                <p class="mt-0.5 flex items-center justify-between text-sm text-muted">{{ $label }} <x-icon name="arrow-right" class="size-4 opacity-0 transition group-hover:opacity-100" /></p>
            </a>
        @endforeach
    </div>

    @if ($awaitingReviews)
        <a href="{{ route('store.reviews') }}" class="mt-6 flex items-center gap-4 rounded-3xl border border-puzzle-yellow/40 bg-puzzle-yellow-soft p-5 transition hover:shadow-lg">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-white text-puzzle-yellow"><x-icon name="star" class="size-5" /></span>
            <span class="min-w-0 flex-1"><span class="block font-semibold text-ink">{{ $awaitingReviews }} ürün değerlendirmenizi bekliyor</span>
                <span class="block text-sm text-muted">Teslim aldığınız ürünlere puan verin, fotoğraflı yorum yazın.</span></span>
            <x-icon name="arrow-right" class="size-5 shrink-0 text-ink" />
        </a>
    @endif

    <div class="mt-6 space-y-6">
        {{-- Son siparişler --}}
        <section id="siparisler" class="scroll-mt-32 {{ $card }}">
            <div class="flex items-center justify-between gap-3">
                <h2 class="flex items-center gap-2.5 font-display text-xl font-extrabold"><x-icon name="package" class="size-5 text-puzzle-orange" /> Son siparişlerim</h2>
                @if ($orders->isNotEmpty())<a href="{{ route('store.orders') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-navy-ink hover:underline">Tümü <x-icon name="arrow-right" class="size-4" /></a>@endif
            </div>
            @if ($orders->isEmpty())
                <div class="mt-5 flex flex-col items-center rounded-2xl bg-surface px-6 py-10 text-center">
                    <span class="flex size-14 items-center justify-center rounded-2xl bg-white text-puzzle-orange shadow-sm"><x-icon name="cart" class="size-7" /></span>
                    <p class="mt-4 font-semibold">Henüz siparişiniz yok.</p>
                    <p class="mt-1 text-sm text-muted">İlk siparişinizi verdiğinizde burada takip edebilirsiniz.</p>
                    <a href="{{ route('store.products') }}" class="mt-5 inline-flex h-10 items-center rounded-xl bg-navy px-5 text-sm font-semibold text-white hover:bg-puzzle-orange">Ürünlere göz at</a>
                </div>
            @else
                <ul class="mt-5 divide-y divide-line overflow-hidden rounded-2xl border border-line">
                    @foreach ($orders as $order)
                        <li><a href="{{ route('store.orders.show', $order->order_no) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 transition hover:bg-surface">
                            <span class="flex items-center gap-3">
                                <span class="flex size-10 items-center justify-center rounded-xl bg-surface text-navy-ink"><x-icon name="file" class="size-5" /></span>
                                <span><span class="block font-semibold">{{ $order->order_no }}</span><span class="block text-xs text-muted">{{ $order->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</span></span>
                            </span>
                            <span class="flex items-center gap-4"><x-badge :tone="$order->statusTone()">{{ $order->statusLabel() }}</x-badge>
                                <span class="font-display font-extrabold tabular-nums">{{ Money::format($order->total) }}</span></span>
                        </a>
                        @if ($order->tracking_number && $order->tracking_url && $order->status === 'shipped')
                            <div class="flex items-center justify-between gap-3 bg-surface/60 px-5 py-2.5 text-sm">
                                <span class="text-muted">{{ $order->carrier }} · <span class="font-mono">{{ $order->tracking_number }}</span></span>
                                <a href="{{ $order->tracking_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 font-semibold text-navy-ink hover:underline"><x-icon name="truck" class="size-4" /> Kargom nerede</a>
                            </div>
                        @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Adresler --}}
        <section id="adresler" class="scroll-mt-32 {{ $card }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="flex items-center gap-2.5 font-display text-xl font-extrabold"><x-icon name="map-pin" class="size-5 text-puzzle-green" /> Adreslerim</h2>
                <a href="{{ route('store.addresses.create') }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-navy px-4 text-sm font-semibold text-white hover:bg-puzzle-orange"><x-icon name="plus" class="size-4" /> Yeni adres</a>
            </div>
            @if ($addresses->isEmpty())
                <p class="mt-5 rounded-2xl bg-surface px-6 py-10 text-center text-sm text-muted">Kayıtlı adresiniz yok. Adres eklerseniz siparişte tek tıkla seçebilirsiniz.</p>
            @else
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    @foreach ($addresses as $address)
                        <article @class(['relative flex flex-col rounded-2xl border p-5', 'border-navy/30 bg-navy-soft/40 ring-1 ring-navy/20' => $address->is_default, 'border-line' => ! $address->is_default])>
                            <div class="flex items-start justify-between gap-3">
                                <p class="flex items-center gap-2 font-semibold"><x-icon name="home" class="size-4 text-muted" /> {{ $address->title }}</p>
                                @if ($address->is_default)<span class="rounded-full bg-navy px-2.5 py-0.5 text-[11px] font-bold text-white">Varsayılan</span>@endif
                            </div>
                            <p class="mt-3 text-sm font-medium">{{ $address->first_name }} {{ $address->last_name }} · {{ PhoneNumber::format($address->phone) }}</p>
                            <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $address->oneLine() }}</p>
                            <div class="mt-auto flex flex-wrap gap-4 pt-4 text-sm">
                                <a href="{{ route('store.addresses.edit', $address->id) }}" class="font-semibold text-navy-ink hover:underline">Düzenle</a>
                                @unless ($address->is_default)
                                    <form method="POST" action="{{ route('store.addresses.default', $address->id) }}">@csrf @method('PUT')<button class="font-semibold text-navy-ink hover:underline">Varsayılan yap</button></form>
                                @endunless
                                <form method="POST" action="{{ route('store.addresses.destroy', $address->id) }}" data-confirm="Adres silinsin mi?">@csrf @method('DELETE')<button class="text-danger hover:underline">Sil</button></form>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Bilgiler --}}
        <section id="bilgiler" class="scroll-mt-32 {{ $card }}">
            <h2 class="flex items-center gap-2.5 font-display text-xl font-extrabold"><x-icon name="user" class="size-5 text-puzzle-blue" /> Bilgilerim</h2>
            <form method="POST" action="{{ route('store.account.profile') }}" class="mt-5 space-y-5">
                @csrf @method('PUT')
                <div class="grid gap-5 sm:grid-cols-3">
                    <x-field name="first_name" label="Ad" :value="$customer->first_name" required maxlength="60" />
                    <x-field name="last_name" label="Soyad" :value="$customer->last_name" required maxlength="60" />
                    <x-field name="phone" label="Cep telefonu" type="tel" :value="PhoneNumber::format($customer->phone)" placeholder="05xx xxx xx xx" />
                </div>
                <div class="rounded-2xl bg-surface p-4">
                    <x-toggle name="marketing" label="Kampanya ve indirimlerden e-posta ve SMS ile haberdar olmak istiyorum" :checked="$customer->marketing_consent_at !== null" />
                </div>
                <x-button>Kaydet</x-button>
            </form>
        </section>

        {{-- Güvenlik --}}
        <section id="guvenlik" class="scroll-mt-32 grid gap-6 lg:grid-cols-2">
            <form method="POST" action="{{ route('store.account.email') }}" class="space-y-5 {{ $card }}">
                @csrf @method('PUT')
                <h2 class="flex items-center gap-2.5 font-display text-xl font-extrabold"><x-icon name="mail" class="size-5 text-puzzle-orange" /> E-posta adresi</h2>
                <p class="-mt-2 text-sm text-muted">Şu an: <span class="font-semibold text-ink">{{ $customer->email }}</span></p>
                <x-field name="email" label="Yeni e-posta adresi" type="email" required autocomplete="off" />
                <x-field name="email_password" label="Parolanız" type="password" required autocomplete="current-password" hint="Güvenliğiniz için parolanızla onaylayın." />
                <x-button variant="secondary">E-postayı değiştir</x-button>
            </form>
            <form method="POST" action="{{ route('store.account.password') }}" class="space-y-5 {{ $card }}">
                @csrf @method('PUT')
                <h2 class="flex items-center gap-2.5 font-display text-xl font-extrabold"><x-icon name="lock" class="size-5 text-puzzle-green" /> Parola</h2>
                <x-field name="current_password" label="Mevcut parola" type="password" required autocomplete="current-password" />
                <x-field name="password" label="Yeni parola" type="password" required autocomplete="new-password" hint="En az 10 karakter; harf ve rakam içermeli." />
                <x-field name="password_confirmation" label="Yeni parola (tekrar)" type="password" required autocomplete="new-password" />
                <x-button variant="secondary">Parolayı değiştir</x-button>
            </form>
        </section>

        {{-- Hesabı kapat --}}
        <details id="hesabi-kapat" class="group scroll-mt-32 rounded-3xl border border-red-200 bg-white" @if ($errors->has('close_password')) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-6 py-4 font-semibold text-danger [&::-webkit-details-marker]:hidden">
                <span class="flex items-center gap-2.5"><x-icon name="alert" class="size-5" /> Hesabı kapat</span>
                <x-icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
            </summary>
            <div class="border-t border-red-100 px-6 pt-4 pb-6">
                <p class="text-sm text-muted">Ad, e-posta, telefon ve kayıtlı adresleriniz silinir; bu işlem geri alınamaz. Siparişleriniz ve faturalarınız vergi mevzuatı gereği saklanır
                    (<a href="{{ route('store.legal', 'kvkk') }}" class="text-navy-ink underline">KVKK Aydınlatma Metni</a>). Tamamlanmamış sipariş veya iade varken hesap kapatılamaz.</p>
                <form method="POST" action="{{ route('store.account.close') }}" class="mt-4 flex flex-wrap items-end gap-3" data-confirm="Hesabınız kalıcı olarak kapatılsın mı?">
                    @csrf @method('DELETE')
                    <x-field name="close_password" label="Parolanız" type="password" required autocomplete="current-password" class="min-w-64" />
                    <x-button variant="danger">Hesabımı kapat</x-button>
                </form>
            </div>
        </details>
    </div>
</x-store.account-shell>
