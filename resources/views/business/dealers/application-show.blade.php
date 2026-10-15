@php use App\Models\DealerApplication; $a = $application; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" :title="$a->legal_name">
    <x-page-header :title="$a->legal_name" :subtitle="'Başvuru tarihi: '.$a->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i')">
        <x-slot:breadcrumb><a href="{{ route('business.applications.index') }}" class="hover:underline">Başvurular</a></x-slot:breadcrumb>
        <x-badge :tone="$a->statusTone()">{{ DealerApplication::STATUSES[$a->status] }}</x-badge>
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
                    <div class="mt-5 border-t border-line pt-4"><p class="text-sm text-muted">Açıklama</p><p class="mt-1 text-sm whitespace-pre-line">{{ $a->note }}</p></div>
                @endif
                <p class="mt-5 border-t border-line pt-4 text-xs text-muted">Aydınlatma metni onayı: {{ $a->consent_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }} (sürüm {{ $a->consent_text_version }}) · IP {{ $a->ip }}</p>
            </x-card>

            @if ($a->attachments->isNotEmpty())
                <x-card title="Belgeler" :padding="false">
                    <ul class="divide-y divide-line text-sm">
                        @foreach ($a->attachments as $file)
                            <li class="flex items-center justify-between px-5 py-3">
                                <span class="flex items-center gap-2"><x-icon name="file" class="size-4 text-muted" />{{ $file->original_name }} <span class="text-muted">· {{ number_format($file->size / 1024, 0, ',', '.') }} KB</span></span>
                                <a href="{{ route('business.applications.attachment', [$a, $file]) }}" class="font-medium text-primary hover:underline">İndir</a>
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
            @if ($a->status === 'approved' && $a->relationship)
                <x-card title="Onaylandı">
                    <p class="text-sm text-muted">Bayi hesabı açıldı.</p>
                    <x-button class="mt-3" variant="secondary" :href="route('business.dealers.show', $a->relationship)">Bayiyi görüntüle</x-button>
                </x-card>
            @elseif ($a->isOpen())
                <x-card title="Onayla ve hesap aç" description="Bayi hesabı, cari hesap ve ilk sevk adresi oluşturulur.">
                    <form method="POST" action="{{ route('business.applications.approve', $a) }}" class="space-y-4"
                        onsubmit="return confirm('Başvuru onaylansın ve bayi hesabı açılsın mı?')">
                        @csrf
                        <x-field name="dealer_group_id" label="Bayi grubu" :options="['' => 'Grupsuz'] + $groups->all()" />
                        <x-field name="payment_term_days" label="Vade (gün)" type="number" min="0" max="365" :value="$defaultTerm" required />
                        <x-toggle name="can_edit_addresses" label="Bayi sevk adreslerini değiştirebilsin" :checked="true" />
                        <x-button class="w-full" icon="check">Onayla</x-button>
                    </form>
                </x-card>

                <x-card title="Diğer işlemler">
                    @if ($a->status === 'new')
                        <form method="POST" action="{{ route('business.applications.review', $a) }}">@csrf<x-button variant="secondary" size="sm" class="w-full">İncelemeye al</x-button></form>
                    @endif
                    <details class="mt-4">
                        <summary class="cursor-pointer text-sm font-medium text-primary">Ek bilgi iste</summary>
                        <form method="POST" action="{{ route('business.applications.request-info', $a) }}" class="mt-3 space-y-3">
                            @csrf
                            <x-field name="note" label="İstenen bilgi" textarea required />
                            <x-button variant="secondary" size="sm">Kaydet</x-button>
                        </form>
                    </details>
                    <details class="mt-4">
                        <summary class="cursor-pointer text-sm font-medium text-danger">Reddet</summary>
                        <form method="POST" action="{{ route('business.applications.reject', $a) }}" class="mt-3 space-y-3" onsubmit="return confirm('Başvuru reddedilsin mi?')">
                            @csrf
                            <x-field name="reason" label="Ret gerekçesi" textarea required />
                            <x-button variant="danger" size="sm">Reddet</x-button>
                        </form>
                    </details>
                    <form method="POST" action="{{ route('business.applications.cancel', $a) }}" class="mt-4 border-t border-line pt-4" onsubmit="return confirm('Başvuru iptal edilsin mi?')">
                        @csrf<button class="text-sm text-muted hover:underline">Başvuruyu iptal et</button>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.panel>
