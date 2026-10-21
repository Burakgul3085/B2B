@php use App\Domain\Store\Customers\PhoneNumber; @endphp
<x-layouts.store title="Hesabım" :noindex="true">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Merhaba, {{ $customer->first_name }}</h1>
                <p class="mt-1 text-sm text-muted">{{ $customer->email }}</p>
            </div>
            <form method="POST" action="{{ route('store.logout') }}">@csrf<x-button variant="secondary" size="sm" icon="logout">Çıkış yap</x-button></form>
        </div>

        <nav class="mt-6 flex gap-2 overflow-x-auto border-b border-line text-sm font-medium" aria-label="Hesap bölümleri">
            <a href="#siparisler" class="border-b-2 border-transparent px-3 py-2.5 whitespace-nowrap hover:border-primary hover:text-primary">Siparişlerim</a>
            <a href="#adresler" class="border-b-2 border-transparent px-3 py-2.5 whitespace-nowrap hover:border-primary hover:text-primary">Adreslerim</a>
            <a href="#bilgiler" class="border-b-2 border-transparent px-3 py-2.5 whitespace-nowrap hover:border-primary hover:text-primary">Bilgilerim</a>
            <a href="#guvenlik" class="border-b-2 border-transparent px-3 py-2.5 whitespace-nowrap hover:border-primary hover:text-primary">E-posta ve parola</a>
        </nav>

        <div class="mt-8 space-y-10">
            <section id="siparisler" class="scroll-mt-40">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold">Siparişlerim</h2>
                    @if ($orders->isNotEmpty())<a href="{{ route('store.orders') }}" class="text-sm font-medium text-primary hover:underline">Tümü</a>@endif
                </div>
                @if ($orders->isEmpty())
                    <div class="mt-4 rounded-2xl border border-dashed border-line px-6 py-10 text-center text-sm text-muted">Henüz siparişiniz yok.</div>
                @else
                    <ul class="mt-4 divide-y divide-line rounded-2xl border border-line">
                        @foreach ($orders as $order)
                            <li><a href="{{ route('store.orders.show', $order->order_no) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm hover:bg-slate-50">
                                <span><span class="font-semibold">{{ $order->order_no }}</span> <span class="text-muted">· {{ $order->created_at->timezone('Europe/Istanbul')->format('d.m.Y') }}</span></span>
                                <span class="flex items-center gap-3"><x-badge :tone="$order->statusTone()">{{ $order->statusLabel() }}</x-badge>
                                    <span class="font-semibold tabular-nums">{{ \App\Domain\Support\Money::format($order->total) }}</span></span>
                            </a></li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section id="adresler" class="scroll-mt-40">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold">Adreslerim</h2>
                    <x-button size="sm" icon="plus" :href="route('store.addresses.create')">Yeni adres</x-button>
                </div>
                @if ($addresses->isEmpty())
                    <p class="mt-4 rounded-2xl border border-dashed border-line px-6 py-10 text-center text-sm text-muted">Kayıtlı adresiniz yok.</p>
                @else
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        @foreach ($addresses as $address)
                            <article @class(['rounded-2xl border p-5', 'border-primary ring-1 ring-primary' => $address->is_default, 'border-line' => ! $address->is_default])>
                                <div class="flex items-start justify-between gap-3">
                                    <p class="font-semibold">{{ $address->title }}</p>
                                    @if ($address->is_default)<x-badge tone="primary">Varsayılan</x-badge>@endif
                                </div>
                                <p class="mt-2 text-sm">{{ $address->first_name }} {{ $address->last_name }} · {{ PhoneNumber::format($address->phone) }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $address->oneLine() }}</p>
                                <div class="mt-4 flex flex-wrap gap-4 text-sm">
                                    <a href="{{ route('store.addresses.edit', $address->id) }}" class="font-medium text-primary hover:underline">Düzenle</a>
                                    @unless ($address->is_default)
                                        <form method="POST" action="{{ route('store.addresses.default', $address->id) }}">@csrf @method('PUT')<button class="font-medium text-primary hover:underline">Varsayılan yap</button></form>
                                    @endunless
                                    <form method="POST" action="{{ route('store.addresses.destroy', $address->id) }}" data-confirm="Adres silinsin mi?">@csrf @method('DELETE')<button class="text-danger hover:underline">Sil</button></form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section id="bilgiler" class="scroll-mt-40">
                <h2 class="text-lg font-semibold">Bilgilerim</h2>
                <form method="POST" action="{{ route('store.account.profile') }}" class="mt-4 max-w-xl space-y-5 rounded-2xl border border-line p-6">
                    @csrf @method('PUT')
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field name="first_name" label="Ad" :value="$customer->first_name" required maxlength="60" />
                        <x-field name="last_name" label="Soyad" :value="$customer->last_name" required maxlength="60" />
                    </div>
                    <x-field name="phone" label="Cep telefonu" type="tel" :value="PhoneNumber::format($customer->phone)" placeholder="05xx xxx xx xx" />
                    <x-toggle name="marketing" label="Kampanya ve indirimlerden e-posta ve SMS ile haberdar olmak istiyorum" :checked="$customer->marketing_consent_at !== null" />
                    <x-button>Kaydet</x-button>
                </form>
            </section>

            <section id="guvenlik" class="scroll-mt-40 grid gap-6 lg:grid-cols-2">
                <form method="POST" action="{{ route('store.account.email') }}" class="space-y-5 rounded-2xl border border-line p-6">
                    @csrf @method('PUT')
                    <h2 class="text-lg font-semibold">E-posta adresi</h2>
                    <x-field name="email" label="Yeni e-posta adresi" type="email" required autocomplete="off" />
                    <x-field name="email_password" label="Parolanız" type="password" required autocomplete="current-password" hint="Güvenliğiniz için parolanızla onaylayın." />
                    <x-button variant="secondary">E-postayı değiştir</x-button>
                </form>
                <form method="POST" action="{{ route('store.account.password') }}" class="space-y-5 rounded-2xl border border-line p-6">
                    @csrf @method('PUT')
                    <h2 class="text-lg font-semibold">Parola</h2>
                    <x-field name="current_password" label="Mevcut parola" type="password" required autocomplete="current-password" />
                    <x-field name="password" label="Yeni parola" type="password" required autocomplete="new-password" hint="En az 10 karakter; harf ve rakam içermeli." />
                    <x-field name="password_confirmation" label="Yeni parola (tekrar)" type="password" required autocomplete="new-password" />
                    <x-button variant="secondary">Parolayı değiştir</x-button>
                </form>
            </section>
        </div>
    </div>
</x-layouts.store>
