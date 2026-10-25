@php
    $storeName = config('store.name');
    $orgJson = ['@context' => 'https://schema.org', '@type' => 'Store', 'name' => $storeName, 'url' => route('store.home')];
    $siteJson = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $storeName, 'url' => route('store.home'),
        'potentialAction' => ['@type' => 'SearchAction', 'target' => route('store.search').'?q={search_term_string}', 'query-input' => 'required name=search_term_string']];
@endphp
{{-- Ana sayfa: bloklar Vitrin → Ana Sayfa'daki sırayla; içeriği olmayan blok kendini göstermez. --}}
<x-layouts.store :canonical="route('store.home')" :json-ld="[$orgJson, $siteJson]">
    @php
        // Son blok tam genişlik zeminli bir bantsa (Hakkımızda) alt bilgiye bitişik durur: alttaki boşluklar kaldırılır.
        $lastBlock = collect($blocks)->where('enabled', true)->last()['key'] ?? null;
        $flushBottom = $lastBlock === 'about' && $about;
    @endphp
    <div @class(['space-y-16 sm:space-y-20', 'pb-16' => ! $flushBottom, '-mb-16' => $flushBottom])>
        @foreach ($blocks as $block)
            @if ($block['enabled'])
                @include('store.front.home.'.$block['key'], ['block' => $block])
            @endif
        @endforeach
    </div>
</x-layouts.store>
