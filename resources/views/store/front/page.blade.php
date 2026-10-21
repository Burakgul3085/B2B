<x-layouts.store :title="$page->seo_title ?: $page->title" :description="$page->seo_description ?: $page->body" :canonical="route('store.page', $page->slug)">
    <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6">
        @include('store.front._breadcrumbs', ['items' => [[$page->title, null]]])
        <h1 class="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">{{ $page->title }}</h1>
        {{-- Markdown'dan üretilen güvenli HTML (ham HTML atılır) --}}
        <div class="mt-6 space-y-4 text-[15px] leading-relaxed text-slate-700 [&_a]:font-medium [&_a]:text-primary [&_a]:underline [&_h2]:mt-8 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-ink [&_h3]:mt-6 [&_h3]:font-semibold [&_h3]:text-ink [&_li]:ml-5 [&_ol]:list-decimal [&_strong]:text-ink [&_ul]:list-disc">
            {{ $page->html() }}
        </div>
    </div>
</x-layouts.store>
