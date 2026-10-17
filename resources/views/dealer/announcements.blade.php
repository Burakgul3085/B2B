<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Duyurular">
    <x-page-header title="Duyurular" :subtitle="$currentBusiness->display_name.' tarafından yayımlanan duyurular ve ekleri'" />

    @if ($announcements->isEmpty())
        <x-card><x-empty-state icon="inbox" title="Henüz duyuru yok" text="Toptancınız duyuru yayımladığında burada görünür." /></x-card>
    @else
        <div class="space-y-4">
            @foreach ($announcements as $a)
                <article class="rounded-2xl border border-line bg-white p-5 shadow-xs">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-base font-semibold text-ink">{{ $a->title }}</h2>
                        <time class="text-xs text-muted" datetime="{{ $a->published_at->toIso8601String() }}">{{ $a->published_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</time>
                    </div>
                    <p class="mt-2 text-sm whitespace-pre-line text-slate-700">{{ $a->body }}</p>
                    @include('partials.announcement-attachments', ['announcement' => $a, 'route' => 'dealer.announcements.attachment'])
                </article>
            @endforeach
        </div>
        @if ($announcements->hasPages())<div class="mt-6">{{ $announcements->links() }}</div>@endif
    @endif
</x-layouts.panel>
