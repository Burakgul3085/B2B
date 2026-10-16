<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bayilik başvurusu</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface">
    <header class="border-b border-line bg-white">
        <div class="mx-auto flex h-16 max-w-3xl items-center px-4">
            <span class="font-semibold text-ink">Bayilik başvurusu</span>
            <a href="{{ route('dealer.login') }}" class="ml-auto text-sm font-medium text-primary hover:underline">Bayi girişi</a>
        </div>
    </header>
    <main class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="text-xl font-semibold text-ink">Hangi toptancının bayisi olmak istiyorsunuz?</h1>
        <p class="mt-1 text-sm text-muted">Toptancıyı seçin ve başvuru formunu doldurun.</p>
        <ul class="mt-6 space-y-3">
            @forelse ($businesses as $business)
                <li>
                    <a href="{{ route('public.application', $business->slug) }}" class="flex items-center gap-3 rounded-xl border border-line bg-white p-4 shadow-xs hover:border-primary">
                        @if ($business->logo_path)
                            <img src="{{ route('public.logo', $business->slug) }}" alt="" class="h-10 w-auto">
                        @else
                            <span class="flex size-10 items-center justify-center rounded-lg bg-primary font-semibold text-white">{{ mb_substr($business->display_name, 0, 1) }}</span>
                        @endif
                        <span class="flex-1">
                            <span class="block font-medium text-ink">{{ $business->display_name }}</span>
                            <span class="block text-xs text-muted">{{ $business->legal_name }}@if ($business->city) · {{ $business->city }}@endif</span>
                        </span>
                        <span class="text-sm font-medium text-primary">Başvur →</span>
                    </a>
                </li>
            @empty
                <li class="rounded-xl border border-line bg-white p-6 text-center text-sm text-muted">Şu an başvuru kabul eden toptancı yok.</li>
            @endforelse
        </ul>
    </main>
</body>
</html>
