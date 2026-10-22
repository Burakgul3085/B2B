{{--
| Türkçe hata sayfaları için ortak düzen (Laravel'in errors::* görünümlerinin yerine geçer).
| Veritabanına ve oturuma dokunmaz: hata sayfasının kendisi hata vermemeli. Dönüş bağlantısı kullanıcının bulunduğu alana göredir.
--}}
@php
    $host = request()->getHost();
    $path = '/'.ltrim(request()->path(), '/');
    [$homeUrl, $homeLabel] = match (true) {
        $host === config('store.domain') => ['/', 'Mağazaya dön'],
        $host === config('store.admin_domain') => ['/', 'Yönetim paneline dön'],
        str_starts_with($path, '/platform') => ['/platform', 'Platform paneline dön'],
        str_starts_with($path, '/toptanci') => ['/toptanci', 'Panele dön'],
        default => ['/bayi', 'Ana sayfaya dön'],
    };
    $brand = $host === config('store.domain') || $host === config('store.admin_domain') ? config('store.name') : config('b2b.brand.name', config('app.name'));
    $dark = request()->cookie('tema') === 'dark';
@endphp
<!doctype html>
<html lang="tr" @class(['dark' => $dark])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ $brand }}</title>
    @if (is_file(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="flex min-h-screen items-center justify-center bg-surface p-6 text-ink">
    <main class="w-full max-w-md rounded-2xl border border-line bg-white p-8 text-center shadow-xs">
        <p class="text-sm font-semibold tracking-wide text-primary">@yield('code')</p>
        <h1 class="mt-2 text-xl font-bold tracking-tight">@yield('title')</h1>
        <p class="mt-3 text-sm leading-relaxed text-muted">@yield('message')</p>
        @hasSection('reference')
            <p class="mt-4 rounded-lg bg-surface px-3 py-2 text-sm">Referans kodu: <span class="font-mono font-semibold">@yield('reference')</span></p>
        @endif
        <div class="mt-6 flex flex-wrap justify-center gap-3">
            @yield('actions')
            <a href="{{ $homeUrl }}" class="inline-flex items-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover">{{ $homeLabel }}</a>
        </div>
        <p class="mt-6 text-xs text-muted">{{ $brand }}</p>
    </main>
</body>
</html>
