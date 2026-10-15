@props(['business', 'title'])
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ $business->display_name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface">
    <header class="border-b border-line bg-white" @if ($business->accent_color) style="border-top: 4px solid {{ $business->accent_color }}" @endif>
        <div class="mx-auto flex h-16 max-w-3xl items-center gap-3 px-4">
            @if ($business->logo_path)
                <img src="{{ route('public.logo', $business->slug) }}" alt="{{ $business->display_name }}" class="h-9 w-auto">
            @else
                <span class="flex size-9 items-center justify-center rounded-lg bg-primary font-semibold text-white">{{ mb_substr($business->display_name, 0, 1) }}</span>
            @endif
            <span class="font-semibold text-ink">{{ $business->display_name }}</span>
            <a href="{{ route('dealer.login') }}" class="ml-auto text-sm font-medium text-primary hover:underline">Bayi girişi</a>
        </div>
    </header>
    <main class="mx-auto max-w-3xl px-4 py-10">{{ $slot }}</main>
    <footer class="pb-10 text-center text-xs text-muted">
        {{ $business->legal_name }}@if ($business->phone) · {{ $business->phone }}@endif @if ($business->email) · {{ $business->email }}@endif
    </footer>
</body>
</html>
