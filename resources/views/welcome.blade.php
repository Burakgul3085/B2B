<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} · Toptancı ve Bayi Yönetimi</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-surface">
    <main class="mx-auto flex min-h-screen max-w-3xl flex-col justify-center px-4 py-16">
        <p class="text-sm font-semibold tracking-wide text-primary uppercase">Kurumsal B2B</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-ink sm:text-4xl">Toptancı ve bayi sipariş yönetimi</h1>
        <p class="mt-3 max-w-xl text-muted">Ürün, stok, sipariş, cari hesap ve iletişim süreçleri tek panelde. Ürün ve fiyat bilgilerine yalnızca yetkili hesaplar erişebilir.</p>

        <div class="mt-10 grid gap-4 sm:grid-cols-2">
            <a href="{{ route('business.login') }}" class="group rounded-xl border border-line bg-white p-6 shadow-xs transition hover:border-primary">
                <x-icon name="building" class="size-6 text-primary" />
                <h2 class="mt-4 font-semibold text-ink">Toptancı girişi</h2>
                <p class="mt-1 text-sm text-muted">Ürün, stok, bayi ve sipariş yönetimi.</p>
                <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-primary">Giriş yap <x-icon name="arrow-right" class="size-4" /></span>
            </a>
            <a href="{{ route('dealer.login') }}" class="group rounded-xl border border-line bg-white p-6 shadow-xs transition hover:border-primary">
                <x-icon name="package" class="size-6 text-primary" />
                <h2 class="mt-4 font-semibold text-ink">Bayi girişi</h2>
                <p class="mt-1 text-sm text-muted">Katalog, sipariş, cari hesap ve mesajlar.</p>
                <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-primary">Giriş yap <x-icon name="arrow-right" class="size-4" /></span>
            </a>
        </div>
    </main>
</body>
</html>
