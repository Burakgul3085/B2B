<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Mağaza yayına çıkana kadar arama motorlarına kapalı (M9'da açılır). --}}
    <meta name="robots" content="noindex, nofollow">
    <title>{{ config('store.name') }} · Çok yakında</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface">
    <main class="mx-auto flex min-h-screen max-w-2xl flex-col items-center justify-center px-6 py-16 text-center">
        <x-puzzle-logo class="size-28" />
        <h1 class="mt-8 text-3xl font-bold tracking-tight text-ink sm:text-4xl">{{ config('store.name') }}</h1>
        <p class="mt-3 inline-flex items-center gap-2 rounded-full bg-violet-50 px-3 py-1 text-sm font-semibold text-violet-800 ring-1 ring-violet-200">Online mağazamız çok yakında açılıyor</p>
        <p class="mt-6 max-w-md text-base leading-relaxed text-muted">Kırtasiye, okul ve ofis ürünlerini yakında buradan sipariş verebileceksiniz.</p>
    </main>
</body>
</html>
