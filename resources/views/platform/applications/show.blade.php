@php use App\Models\DealerApplication; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" :title="$a->legal_name">
    <x-page-header :title="$a->legal_name" :subtitle="$a->business->display_name.' toptancısına başvuru · '.$a->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i')">
        <x-slot:breadcrumb><a href="{{ route('platform.applications.index') }}" class="hover:underline">Bayilik Başvuruları</a></x-slot:breadcrumb>
        <x-badge :tone="['new' => 'warning', 'in_review' => 'primary', 'waiting_info' => 'warning', 'approved' => 'success'][$a->status] ?? 'neutral'">{{ DealerApplication::STATUSES[$a->status] }}</x-badge>
    </x-page-header>

    @error('approve')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Başvuru bilgileri">
                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    @foreach ([
                        'Yetkili' => $a->contact_name, 'E-posta' => $a->email, 'Telefon' => $a->phone,
                        'İşletme türü' => DealerApplication::BUSINESS_TYPES[$a->business_type] ?? null,
                        'Faaliyet alanı' => $a->activity_area,
                        'Vergi dairesi / no' => trim(($a->tax_office ?? '').' '.($a->tax_number ?? '')),
                        'Adres' => $a->address.', '.$a->district.' / '.$a->city,
                    ] as $label => $value)
                        <div @class(['sm:col-span-2' => $label === 'Adres'])><dt class="text-muted">{{ $label }}</dt><dd class="mt-0.5 font-medium">{{ $value ?: '—' }}</dd></div>
                    @endforeach
                </dl>
                @if ($a->note)
                    <div class="mt-5 border-t border-line pt-4"><p class="text-sm text-muted">Açıklama</p><p class="mt-1 whitespace-pre-line text-sm">{{ $a->note }}</p></div>
                @endif
            </x-card>

            @if ($a->attachments->isNotEmpty())
                <x-card title="Belgeler" :padding="false">
                    <ul class="divide-y divide-line text-sm">
                        @foreach ($a->attachments as $file)
                            <li class="flex items-center justify-between px-5 py-3">
                                <span class="flex items-center gap-2"><x-icon name="file" class="size-4 text-muted" />{{ $file->original_name }}</span>
                                <a href="{{ route('platform.applications.attachment', [$a->id, $file->id]) }}" class="font-medium text-primary hover:underline">İndir</a>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if ($a->info_request_note || $a->decision_note)
                <x-card title="Değerlendirme notları">
                    @if ($a->info_request_note)<p class="text-sm"><b>İstenen ek bilgi:</b> {{ $a->info_request_note }}</p>@endif
                    @if ($a->decision_note)<p class="mt-2 text-sm"><b>Karar notu:</b> {{ $a->decision_note }}</p>@endif
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            @if ($a->isOpen())
                <x-card title="Onayla ve bayi hesabı aç" description="Bayi hesabı, cari hesap ve ilk sevk adresi {{ $a->business->display_name }} adına oluşturulur. Bayi kodu ve grubu toptancı sonradan düzenleyebilir.">
                    <form method="POST" action="{{ route('platform.applications.approve', $a->id) }}" class="space-y-4" data-confirm="Başvuru onaylansın ve bayi hesabı açılsın mı?">
                        @csrf
                        @include('partials.approve-login', ['application' => $a])
                        <x-field name="payment_term_days" label="Vade (gün)" type="number" min="0" max="365" :value="$defaultTerm" required />
                        <x-button class="w-full" icon="check">Onayla</x-button>
                    </form>
                </x-card>

                <x-card title="Reddet">
                    <form method="POST" action="{{ route('platform.applications.reject', $a->id) }}" class="space-y-3" data-confirm="Başvuru reddedilsin mi?">
                        @csrf
                        <x-field name="reason" label="Gerekçe" textarea required hint="Başvuru sahibine e-postayla iletilir." />
                        <x-button variant="danger" size="sm">Reddet</x-button>
                    </form>
                </x-card>
            @else
                <x-card title="Sonuç">
                    <p class="text-sm">Bu başvuru <b>{{ mb_strtolower(DealerApplication::STATUSES[$a->status]) }}</b>.</p>
                    @if ($a->reviewed_at)<p class="mt-1 text-xs text-muted">{{ $a->reviewed_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p>@endif
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.panel>
