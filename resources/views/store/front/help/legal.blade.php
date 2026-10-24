{{-- Yasal Haklar ve Sözleşmeler: tüm yasal metinler --}}
<x-store.help-layout :key="$key" :c="$c" icon="shield">
    <ul class="grid gap-3 sm:grid-cols-2">
        @foreach ($documents as $doc => $title)
            <li>
                <a href="{{ route('store.legal', $doc) }}" class="group flex items-center justify-between gap-4 rounded-2xl border border-line bg-white px-5 py-4 font-semibold transition hover:border-navy-ink/30 hover:shadow-lg hover:shadow-navy/5">
                    <span class="flex items-center gap-3"><span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-surface text-navy-ink"><x-icon name="file" class="size-5" /></span>{{ $title }}</span>
                    <x-icon name="arrow-right" class="size-4 text-muted transition group-hover:translate-x-1" />
                </a>
            </li>
        @endforeach
    </ul>
</x-store.help-layout>
