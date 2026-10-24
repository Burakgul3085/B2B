{{-- Sıkça Sorulan Sorular: gruplara ayrılmış, açılır-kapanır; FAQPage yapılandırılmış verisi --}}
@php
    use App\Domain\Store\Content\SitePages;
    use Illuminate\Support\Str;

    $groups = collect($c['items'])->groupBy('group');
    $faqJson = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($c['items'])->map(fn ($i) => [
        '@type' => 'Question', 'name' => $i['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags((string) SitePages::markdown($i['answer'])))],
    ])->all()];
@endphp
<x-store.help-layout :key="$key" :c="$c" icon="message-circle" :json-ld="$c['items'] ? [$faqJson] : []">
    @if ($groups->count() > 1)
        <nav class="mb-6 flex flex-wrap gap-2" aria-label="Soru grupları">
            @foreach ($groups->keys() as $group)
                <a href="#{{ Str::slug($group) }}" class="rounded-full bg-surface px-4 py-2 text-sm font-semibold text-ink/75 hover:text-ink">{{ $group }}</a>
            @endforeach
        </nav>
    @endif
    <div class="space-y-8">
        @forelse ($groups as $group => $items)
            <section id="{{ Str::slug($group) }}" class="scroll-mt-32">
                @if ($groups->count() > 1)<h2 class="mb-3 font-display text-xl font-extrabold">{{ $group }}</h2>@endif
                <div class="divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white">
                    @foreach ($items as $item)
                        <details class="group">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-semibold hover:bg-surface [&::-webkit-details-marker]:hidden">
                                {{ $item['question'] }}
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-surface text-navy-ink transition group-open:rotate-45"><x-icon name="plus" class="size-4" /></span>
                            </summary>
                            <div class="space-y-3 px-5 pb-5 text-[15px] leading-relaxed text-slate-700 [&_a]:font-semibold [&_a]:text-navy-ink [&_a]:underline [&_li]:ml-5 [&_strong]:text-ink [&_ul]:list-disc">
                                {{ SitePages::markdown($item['answer']) }}
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="rounded-2xl border border-dashed border-line px-6 py-10 text-center text-sm text-muted">Henüz soru eklenmedi.</p>
        @endforelse
    </div>
</x-store.help-layout>
