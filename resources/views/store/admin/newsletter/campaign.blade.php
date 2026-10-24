@php
    $title = $campaign->exists ? $campaign->subject : 'Yeni e-posta';
    $editable = ! $campaign->exists || $campaign->isDraft();
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" :title="$title">
    <x-page-header :title="$title">
        <x-slot:breadcrumb><a href="{{ route('store_admin.campaigns.index') }}" class="hover:underline">Toplu E-posta</a></x-slot:breadcrumb>
        @if ($campaign->exists)<x-button :href="route('store_admin.campaigns.preview', $campaign)" variant="secondary" icon="eye" target="_blank">Önizle</x-button>@endif
    </x-page-header>

    @error('campaign')<div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-danger">{{ $message }}</div>@enderror
    @unless ($mailReady)
        <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            E-posta hesabı tanımlı değil; gönderim yapılamaz. <a href="{{ route('store_admin.settings.edit') }}" class="font-semibold underline">Mağaza Ayarları → E-posta gönderimi</a>
        </div>
    @endunless

    @if ($campaign->exists && ! $campaign->isDraft())
        {{-- Gönderim durumu: sayfa açıkken birkaç saniyede bir yenilenir --}}
        <x-card class="mb-6">
            <div x-data="campaignProgress(@alpine(route('store_admin.campaigns.progress', $campaign)), @alpine(['status' => $campaign->status, 'label' => $campaign->statusLabel(), 'sent' => $campaign->sent_count, 'failed' => $campaign->failed_count, 'total' => $campaign->recipients_count, 'percent' => $campaign->progress()]))">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="font-semibold">Gönderim: <span x-text="state.label">{{ $campaign->statusLabel() }}</span></p>
                    <p class="text-sm text-muted tabular-nums"><span x-text="state.sent">{{ $campaign->sent_count }}</span> / <span x-text="state.total">{{ $campaign->recipients_count }}</span> gönderildi
                        · <span x-text="state.failed">{{ $campaign->failed_count }}</span> hata</p>
                </div>
                <div class="mt-3 h-3 overflow-hidden rounded-full bg-surface"><div class="h-full rounded-full bg-primary transition-all duration-700" :style="barStyle" style="width: {{ $campaign->progress() }}%"></div></div>
                @if ($campaign->status === 'sending')
                    <form method="POST" action="{{ route('store_admin.campaigns.cancel', $campaign) }}" class="mt-4" data-confirm="Gönderim durdurulsun mu? Kalan abonelere gönderilmez.">
                        @csrf <x-button size="sm" variant="secondary">Gönderimi durdur</x-button>
                    </form>
                @endif
                <p class="mt-3 text-xs text-muted">E-postalar dakikada yaklaşık {{ \App\Domain\Store\Newsletter\Campaigns::BATCH_SIZE }} adet gönderilir (e-posta sağlayıcınızın sınırlarına takılmamak için). Bu sayfayı kapatabilirsiniz; gönderim arka planda sürer.</p>
            </div>
        </x-card>
    @endif

    <form method="POST" action="{{ $campaign->exists ? route('store_admin.campaigns.update', $campaign) : route('store_admin.campaigns.store') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-3">
        @csrf @if ($campaign->exists) @method('PUT') @endif
        <fieldset class="space-y-6 xl:col-span-2" @disabled(! $editable)>
            <x-card>
                <div class="space-y-5">
                    <x-field name="subject" label="Konu" :value="$campaign->subject" required maxlength="150" placeholder="ör. Okula dönüş fırsatları başladı!" />
                    <x-field name="preheader" label="Ön izleme metni" :value="$campaign->preheader" maxlength="150" hint="E-posta kutusunda konunun yanında görünen kısa metin (isteğe bağlı)." />
                    <div x-data="markdownToolbar()">
                        <p class="mb-1.5 text-sm font-medium">Metin <span class="text-danger">*</span></p>
                        <div class="mb-2 flex flex-wrap gap-1.5 text-sm">
                            <button type="button" class="rounded-md border border-line px-2.5 py-1 font-bold hover:bg-slate-50" @click="bold">K</button>
                            <button type="button" class="rounded-md border border-line px-2.5 py-1 hover:bg-slate-50" @click="heading">Ara başlık</button>
                            <button type="button" class="rounded-md border border-line px-2.5 py-1 hover:bg-slate-50" @click="bullet">• Madde</button>
                            <button type="button" class="rounded-md border border-line px-2.5 py-1 hover:bg-slate-50" @click="link">Bağlantı</button>
                        </div>
                        <textarea name="body" rows="14" x-ref="text" class="field-input text-sm leading-relaxed" maxlength="30000" required>{{ old('body', $campaign->body) }}</textarea>
                        @error('body')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                    </div>
                </div>
            </x-card>
            <x-card title="Düğme" description="Metnin altında turuncu bir düğme (isteğe bağlı).">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="button_label" label="Düğme yazısı" :value="$campaign->button_label" maxlength="40" placeholder="ör. Fırsatları gör" />
                    <x-field name="button_url" label="Düğme adresi" :value="$campaign->button_url" maxlength="255" placeholder="https://…" />
                </div>
            </x-card>
        </fieldset>

        <div class="space-y-6">
            <x-card title="Görsel">
                @if ($campaign->image_path)
                    <img src="{{ route('store_admin.campaigns.image', $campaign) }}" alt="" class="mb-2 w-full rounded-lg">
                    @if ($editable)<x-toggle name="remove_image" label="Görseli kaldır" class="mb-2" />@endif
                @endif
                @if ($editable)
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="field-input">
                    @error('image')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@else<p class="mt-1.5 text-xs text-muted">Konunun üstünde, tam genişlikte. Yatay görsel (ör. 1200×600), en fazla 6 MB.</p>@enderror
                @endif
            </x-card>
            @if ($editable)
                <x-card>
                    <x-button class="w-full justify-center">Taslağı kaydet</x-button>
                </x-card>
            @endif
        </div>
    </form>

    @if ($campaign->exists && $campaign->isDraft())
        <x-card title="Gönderim" class="mt-6">
            <div class="flex flex-wrap items-center gap-3">
                <form method="POST" action="{{ route('store_admin.campaigns.test', $campaign) }}">@csrf<x-button variant="secondary" icon="mail">Kendime deneme gönder</x-button></form>
                <form method="POST" action="{{ route('store_admin.campaigns.send', $campaign) }}" data-confirm="E-posta {{ $activeCount }} onaylı aboneye gönderilsin mi? Gönderim başladıktan sonra e-posta düzenlenemez.">
                    @csrf<x-button icon="arrow-right" :disabled="! $activeCount">{{ $activeCount }} aboneye gönder</x-button>
                </form>
                <form method="POST" action="{{ route('store_admin.campaigns.destroy', $campaign) }}" data-confirm="Taslak silinsin mi?" class="ml-auto">
                    @csrf @method('DELETE')<button class="text-sm text-danger hover:underline">Taslağı sil</button>
                </form>
            </div>
            <p class="mt-3 text-xs text-muted">Gönderilmeden önce kaydedilmemiş değişiklikleri “Taslağı kaydet” ile kaydedin. Her e-postanın altında kişiye özel “Abonelikten çık” bağlantısı bulunur.</p>
        </x-card>
    @endif
</x-layouts.panel>
