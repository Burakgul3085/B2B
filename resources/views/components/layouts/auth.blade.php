@props(['area', 'title'])
@php
    /** @var \App\Domain\Auth\Area $area */
    $isPlatform = $area === \App\Domain\Auth\Area::Platform;
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · {{ $area->label() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body @class(['min-h-screen', 'bg-graphite' => $isPlatform, 'bg-surface' => ! $isPlatform])>
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-md">
            <div class="mb-6 text-center">
                <span @class([
                    'inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold tracking-wide uppercase',
                    'bg-white/10 text-white' => $isPlatform,
                    'bg-primary-soft text-primary' => ! $isPlatform,
                ])>
                    <x-icon :name="match ($area) { \App\Domain\Auth\Area::Platform => 'shield', \App\Domain\Auth\Area::Business => 'building', default => 'package' }" class="size-3.5" />
                    {{ $area->label() }}
                </span>
                <h1 @class(['mt-4 text-2xl font-semibold tracking-tight', 'text-white' => $isPlatform, 'text-ink' => ! $isPlatform])>{{ $title }}</h1>
            </div>

            <div class="rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-8">
                <x-flash :summary="false" />
                {{ $slot }}
            </div>

            @isset($footer)
                <div @class(['mt-6 text-center text-sm', 'text-slate-400' => $isPlatform, 'text-muted' => ! $isPlatform])>{{ $footer }}</div>
            @endisset
        </div>
    </main>
</body>
</html>
