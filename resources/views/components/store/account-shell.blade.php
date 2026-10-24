{{--
    Üye hesabı sayfalarının ortak çerçevesi: solda profil kartı ve hesap menüsü, sağda içerik ($slot).
    $active: overview | orders | favorites | addresses | profile | security
--}}
@props(['title', 'active' => 'overview'])
@php
    $customer = auth('customer')->user();
    $initials = mb_strtoupper(mb_substr($customer->first_name, 0, 1).mb_substr((string) $customer->last_name, 0, 1));
    $menu = [
        ['overview', route('store.account'), 'home', 'Genel bakış'],
        ['orders', route('store.orders'), 'package', 'Siparişlerim'],
        ['favorites', route('store.favorites'), 'heart', 'Favorilerim'],
        ['addresses', route('store.account').'#adresler', 'map-pin', 'Adreslerim'],
        ['profile', route('store.account').'#bilgiler', 'user', 'Bilgilerim'],
        ['security', route('store.account').'#guvenlik', 'lock', 'E-posta ve parola'],
    ];
@endphp
<x-layouts.store :title="$title" :noindex="true">
    <div class="bg-gradient-to-b from-surface to-transparent">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[17rem_minmax(0,1fr)] lg:px-8 lg:py-12">
            <aside class="min-w-0 space-y-4 lg:sticky lg:top-28 lg:self-start">
                {{-- Profil kartı --}}
                <div class="relative overflow-hidden rounded-3xl bg-[#0b1631] p-6 text-white shadow-xl shadow-navy/15">
                    <div class="pointer-events-none absolute -top-6 -right-6 grid rotate-12 grid-cols-2 gap-1.5 opacity-80" aria-hidden="true">
                        <span class="size-8 rounded-lg bg-puzzle-orange"></span><span class="size-8 rounded-lg bg-puzzle-green"></span>
                        <span class="size-8 rounded-lg bg-puzzle-blue"></span><span class="size-8 rounded-lg bg-puzzle-yellow"></span>
                    </div>
                    <span class="relative flex size-14 items-center justify-center rounded-2xl bg-white/10 font-display text-xl font-extrabold ring-1 ring-white/20">{{ $initials }}</span>
                    <p class="relative mt-4 font-display text-lg leading-tight font-extrabold">{{ $customer->fullName() }}</p>
                    <p class="relative mt-0.5 truncate text-sm text-white/60">{{ $customer->email }}</p>
                    <p class="relative mt-3 inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-semibold text-white/80">
                        <x-icon name="star" class="size-3.5 text-puzzle-yellow" /> {{ $customer->created_at->timezone('Europe/Istanbul')->translatedFormat('F Y') }} tarihinden beri üye
                    </p>
                </div>

                {{-- Menü --}}
                <nav class="rounded-3xl border border-line bg-white p-2" aria-label="Hesap bölümleri">
                    <ul class="flex gap-1 overflow-x-auto lg:flex-col lg:overflow-visible">
                        @foreach ($menu as [$key, $url, $icon, $label])
                            <li class="shrink-0">
                                <a href="{{ $url }}" @class(['flex items-center gap-3 rounded-2xl px-3.5 py-2.5 text-sm font-semibold whitespace-nowrap transition',
                                    'bg-navy text-white shadow-md shadow-navy/20' => $active === $key, 'text-ink/75 hover:bg-surface hover:text-ink' => $active !== $key])
                                    @if ($active === $key) aria-current="page" @endif>
                                    <x-icon :name="$icon" class="size-[18px]" /> {{ $label }}
                                </a>
                            </li>
                        @endforeach
                        <li class="shrink-0 lg:mt-1 lg:border-t lg:border-line lg:pt-1">
                            <form method="POST" action="{{ route('store.logout') }}">
                                @csrf
                                <button class="flex w-full items-center gap-3 rounded-2xl px-3.5 py-2.5 text-sm font-semibold whitespace-nowrap text-danger transition hover:bg-red-50">
                                    <x-icon name="logout" class="size-[18px]" /> Çıkış yap
                                </button>
                            </form>
                        </li>
                    </ul>
                </nav>
            </aside>

            <div class="min-w-0">{{ $slot }}</div>
        </div>
    </div>
</x-layouts.store>
