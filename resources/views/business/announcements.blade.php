<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Duyurular">
    <x-page-header title="Duyurular" subtitle="Bayilerinize duyuru yayımlayın; ana sayfalarında ve bildirimlerinde görünür." />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Yayımlanan duyurular" class="lg:col-span-2" :padding="false">
            @if ($announcements->isEmpty())
                <x-empty-state icon="inbox" title="Henüz duyuru yok" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($announcements as $a)
                        <li class="px-5 py-4">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p class="font-medium">{{ $a->title }}</p>
                                <span class="text-xs text-muted">{{ $a->published_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') }} · {{ $a->group?->name ?? 'Tüm bayiler' }}@if ($a->send_email) · e-posta @endif</span>
                            </div>
                            <p class="mt-1 text-sm whitespace-pre-line text-slate-700">{{ $a->body }}</p>
                        </li>
                    @endforeach
                </ul>
                @if ($announcements->hasPages())<div class="border-t border-line p-4">{{ $announcements->links() }}</div>@endif
            @endif
        </x-card>
        <x-card title="Yeni duyuru">
            <form method="POST" action="{{ route('business.announcements.store') }}" class="space-y-4" onsubmit="return confirm('Duyuru yayımlansın mı?')">
                @csrf
                <x-field name="title" label="Başlık" required maxlength="160" />
                <x-field name="body" label="Metin" textarea required />
                <x-field name="dealer_group_id" label="Kimlere" :options="['' => 'Tüm aktif bayiler'] + $groups->all()" />
                <x-toggle name="send_email" label="E-posta olarak da gönder" hint="E-posta ayarınız yapılmışsa." />
                <x-button>Yayımla</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.panel>
