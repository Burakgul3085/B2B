@props(['title', 'number' => null, 'business', 'dealer', 'meta' => [], 'address' => []])
{{--
    B2B yazdırma belgesi (sipariş, cari ekstre). Ekranda görünmez; yazdırırken sayfanın ekran içeriği print:hidden ile
    gizlenir ve yalnızca bu belge A4'e basılır. Üstte toptancı kimliği ve belge başlığı, altında bayi ve belge bilgileri,
    ardından $slot (tablo). meta: [etiket => değer], address: teslimat adresi satırları (isteğe bağlı).
--}}
@php
    $lines = fn ($m) => array_filter([
        $m->address,
        trim(collect([$m->district, $m->city])->filter()->implode(' / ')),
        collect([$m->phone, $m->email])->filter()->implode(' · '),
        $m->tax_number ? trim(($m->tax_office ? $m->tax_office.' V.D. · ' : '').'VKN/TCKN '.$m->tax_number) : null,
    ]);
@endphp
<div {{ $attributes->merge(['class' => 'print-document hidden text-[9.5pt] leading-snug text-slate-900 print:block']) }}>
    <header class="flex items-start justify-between gap-6 border-b-2 border-slate-900 pb-3">
        <div class="flex min-w-0 items-start gap-3">
            <x-puzzle-logo :animated="false" class="size-12 shrink-0" />
            <div class="min-w-0">
                <p class="text-[13pt] font-bold">{{ $business->display_name }}</p>
                @if ($business->legal_name && $business->legal_name !== $business->display_name)<p class="font-medium">{{ $business->legal_name }}</p>@endif
                @foreach ($lines($business) as $line)<p class="text-[8.5pt] text-slate-600">{{ $line }}</p>@endforeach
            </div>
        </div>
        <div class="shrink-0 text-right">
            <p class="text-[15pt] font-bold tracking-wide uppercase">{{ $title }}</p>
            @if ($number)<p class="text-[11pt] font-semibold tabular-nums">{{ $number }}</p>@endif
            <p class="mt-1 text-[8pt] text-slate-600">Yazdırma: {{ now('Europe/Istanbul')->format('d.m.Y H:i') }}</p>
        </div>
    </header>

    <div class="mt-4 grid grid-cols-2 gap-6">
        <section class="space-y-3">
            <div>
                <p class="text-[7.5pt] font-semibold tracking-wider text-slate-500 uppercase">Bayi</p>
                <p class="font-semibold">{{ $dealer->legal_name }}</p>
                @foreach ($lines($dealer) as $line)<p class="text-[8.5pt] text-slate-600">{{ $line }}</p>@endforeach
            </div>
            @if ($address)
                <div>
                    <p class="text-[7.5pt] font-semibold tracking-wider text-slate-500 uppercase">Teslimat adresi</p>
                    @foreach ($address as $line)<p @class(['text-[8.5pt]', 'font-medium' => $loop->first, 'text-slate-600' => ! $loop->first])>{{ $line }}</p>@endforeach
                </div>
            @endif
        </section>
        <dl class="self-start rounded border border-slate-300 text-[8.5pt]">
            @foreach (array_filter($meta, fn ($v) => $v !== null && $v !== '') as $label => $value)
                <div @class(['flex justify-between gap-4 px-3 py-1.5', 'border-t border-slate-200' => ! $loop->first])>
                    <dt class="text-slate-600">{{ $label }}</dt>
                    <dd class="text-right font-semibold tabular-nums">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    <div class="mt-5 overflow-hidden rounded border border-slate-300">{{ $slot }}</div>

    @isset($notes)<div class="mt-4 space-y-1 text-[8.5pt]">{{ $notes }}</div>@endisset

    <footer class="mt-6 border-t border-slate-300 pt-2 text-[7.5pt] text-slate-500">
        Bu belge {{ $business->display_name }} B2B sipariş sisteminden oluşturulmuştur; mali belge (fatura) yerine geçmez.
    </footer>
</div>
