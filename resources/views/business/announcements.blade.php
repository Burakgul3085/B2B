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
                            @include('partials.announcement-attachments', ['announcement' => $a, 'route' => 'business.announcements.attachment'])
                        </li>
                    @endforeach
                </ul>
                @if ($announcements->hasPages())<div class="border-t border-line p-4">{{ $announcements->links() }}</div>@endif
            @endif
        </x-card>
        <x-card title="Yeni duyuru">
            <form method="POST" action="{{ route('business.announcements.store') }}" enctype="multipart/form-data" class="space-y-4" data-confirm="Duyuru yayımlansın mı?"
                x-data="fileList({{ \App\Http\Controllers\Business\AnnouncementController::MAX_TOTAL_BYTES }})">
                @csrf
                <x-field name="title" label="Başlık" required maxlength="160" />
                <x-field name="body" label="Metin" textarea required />
                <x-field name="dealer_group_id" label="Kimlere" :options="['' => 'Tüm aktif bayiler'] + $groups->all()" />
                <div>
                    <label for="announcement_files" class="mb-1.5 block text-sm font-medium text-ink">Ekler <span class="font-normal text-muted">(isteğe bağlı)</span></label>
                    <label for="announcement_files" class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-line bg-slate-50 px-4 py-5 text-center transition hover:border-primary/50 hover:bg-primary-soft/40">
                        <x-icon name="upload" class="size-5 text-primary" />
                        <span class="text-sm font-medium text-ink">Dosya seçin</span>
                        <span class="text-xs text-muted">PDF, JPG, PNG, Word, Excel · en fazla {{ \App\Http\Controllers\Business\AnnouncementController::MAX_FILES }} dosya, her biri {{ \App\Http\Controllers\Business\AnnouncementController::MAX_BYTES / 1048576 }} MB, toplam {{ \App\Http\Controllers\Business\AnnouncementController::MAX_TOTAL_BYTES / 1048576 }} MB</span>
                    </label>
                    <input id="announcement_files" type="file" name="files[]" multiple class="sr-only"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,application/pdf,image/jpeg,image/png"
                        @change="pick($event)">
                    <ul x-show="names.length" x-cloak class="mt-2 space-y-1 text-xs text-ink">
                        <template x-for="n in names" :key="n"><li class="flex items-center gap-1.5 truncate"><x-icon name="file" class="size-3.5 shrink-0 text-muted" /><span x-text="n" class="truncate"></span></li></template>
                    </ul>
                    <p x-show="tooBig" x-cloak class="mt-1.5 text-sm text-danger">Seçilen dosyaların toplamı {{ \App\Http\Controllers\Business\AnnouncementController::MAX_TOTAL_BYTES / 1048576 }} MB'ı aşıyor; daha az veya daha küçük dosya seçin.</p>
                    @error('files')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                    @error('files.*')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
                <x-toggle name="send_email" label="E-posta olarak da gönder" hint="E-posta ayarınız yapılmışsa." />
                <x-button x-bind:disabled="tooBig">Yayımla</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.panel>
