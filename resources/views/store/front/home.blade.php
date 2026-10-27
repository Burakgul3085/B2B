@php
    $storeName = config('store.name');
    $orgJson = ['@context' => 'https://schema.org', '@type' => 'Store', 'name' => $storeName, 'url' => route('store.home')];
    $siteJson = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $storeName, 'url' => route('store.home'),
        'potentialAction' => ['@type' => 'SearchAction', 'target' => route('store.search').'?q={search_term_string}', 'query-input' => 'required name=search_term_string']];
@endphp
{{-- Ana sayfa: bloklar Vitrin → Ana Sayfa'daki sırayla; içeriği olmayan blok kendini göstermez. --}}
<x-layouts.store :canonical="route('store.home')" :json-ld="[$orgJson, $siteJson]">
    @php
        // Son blok tam genişlik zeminli bir bantsa (Hakkımızda, bayilik) alt bilgiye bitişik durur: alttaki boşluklar kaldırılır.
        // Bayilik bandı Hakkımızda'nın hemen ardındaysa ikisi tek zemin gibi birleşir (aradaki boşluk kaldırılır).
        $shown = collect($blocks)->where('enabled', true)->reject(fn ($b) => $b['key'] === 'about' && ! $about)->pluck('key')->values();
        $flushBottom = in_array($shown->last(), ['about', 'dealer'], true);
    @endphp
    <div @class(['space-y-16 sm:space-y-20', 'pb-16' => ! $flushBottom, '-mb-16' => $flushBottom])>
        @foreach ($blocks as $block)
            @if ($block['enabled'])
                @php $joined = $block['key'] === 'dealer' && $shown->get($shown->search('dealer') - 1) === 'about'; @endphp
                @include('store.front.home.'.$block['key'], ['block' => $block, 'joined' => $joined])
            @endif
        @endforeach
    </div>
</x-layouts.store>
