{{-- Bildirim zili: okunmamış sayısı ve son 5 bildirim. $side: business | dealer --}}
@props(['side', 'relationshipId' => null, 'dark' => false])
@php
    $base = \App\Models\AppNotification::query()->for($side, $relationshipId);
    $unread = (clone $base)->whereNull('read_at')->count();
    $latest = (clone $base)->latest('id')->limit(5)->get();
@endphp
<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button type="button" @click="open = !open" :aria-expanded="open" aria-label="Bildirimler ({{ $unread }} okunmamış)"
        @class(['relative rounded-lg p-2', 'hover:bg-white/10' => $dark, 'text-muted hover:bg-slate-100' => ! $dark])>
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
        @if ($unread)<span class="absolute -top-0.5 -right-0.5 rounded-full bg-danger px-1.5 text-[11px] font-bold text-white tabular-nums">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
    </button>
    <div x-cloak x-show="open" x-transition class="absolute right-0 z-40 mt-2 w-80 overflow-hidden rounded-xl border border-line bg-white text-sm text-ink shadow-lg">
        @forelse ($latest as $n)
            <a href="{{ route($side.'.notifications.open', $n) }}" @class(['block border-b border-line px-4 py-3 last:border-0 hover:bg-slate-50', 'bg-primary-soft/40' => ! $n->read_at])>
                <p @class(['truncate', 'font-semibold' => ! $n->read_at])>{{ $n->title }}</p>
                <p class="text-xs text-muted">{{ $n->created_at->timezone('Europe/Istanbul')->format('d.m H:i') }}@if ($n->body) · {{ \Illuminate\Support\Str::limit($n->body, 50) }}@endif</p>
            </a>
        @empty
            <p class="px-4 py-6 text-center text-muted">Bildirim yok</p>
        @endforelse
        <a href="{{ route($side.'.notifications.index') }}" class="block border-t border-line bg-slate-50 px-4 py-2.5 text-center font-medium text-primary hover:underline">Tüm bildirimler</a>
    </div>
</div>
