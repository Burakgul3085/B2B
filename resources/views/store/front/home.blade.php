@php
    $storeName = config('store.name');
    $orgJson = ['@context' => 'https://schema.org', '@type' => 'Store', 'name' => $storeName, 'url' => route('store.home')];
    $siteJson = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $storeName, 'url' => route('store.home'),
        'potentialAction' => ['@type' => 'SearchAction', 'target' => route('store.search').'?q={search_term_string}', 'query-input' => 'required name=search_term_string']];
@endphp
{{-- Ana sayfa: bloklar Vitrin → Ana Sayfa'daki sırayla; içeriği olmayan blok kendini göstermez. --}}
<x-layouts.store :canonical="route('store.home')" :json-ld="[$orgJson, $siteJson]">
    <div class="space-y-16 pb-16 sm:space-y-20">
        @foreach ($blocks as $block)
            @if ($block['enabled'])
                @include('store.front.home.'.$block['key'], ['block' => $block])
            @endif
        @endforeach
    </div>
</x-layouts.store>
