@php use App\Models\Store\StoreNewsletterSubscriber; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="E-bülten Aboneleri">
    <x-page-header title="E-bülten Aboneleri" subtitle="Alt bilgideki formdan (e-postayla onaylanan) ve üyelikte “kampanyalardan haberdar olmak istiyorum” diyen müşterilerden gelen aboneler. Onay metni, tarih ve IP her kayıtta saklanır.">
        <x-button :href="route('store_admin.campaigns.index')" icon="mail">Toplu e-posta</x-button>
    </x-page-header>

    @unless ($mailReady)
        <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Mağazanın e-posta hesabı tanımlı değil: alt bilgideki abonelik formu gizli; onay e-postaları ve toplu e-postalar gönderilemez. <a href="{{ route('store_admin.settings.edit') }}" class="font-semibold underline">Mağaza Ayarları → E-posta gönderimi</a> bölümünden hesabınızı girin.
        </div>
    @endunless

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-kpi label="Onaylı aboneler" :value="number_format($counts['active'] ?? 0, 0, ',', '.')" hint="Toplu e-posta bunlara gider" />
        <x-kpi label="Onay bekleyen" :value="number_format($counts['pending'] ?? 0, 0, ',', '.')" hint="E-postadaki bağlantıya tıklamadı" />
        <x-kpi label="Ayrılan" :value="number_format($counts['unsubscribed'] ?? 0, 0, ',', '.')" hint="Kendi isteğiyle çıktı" />
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-card :padding="false">
                <form method="GET" class="flex flex-wrap items-center gap-2 border-b border-line p-4">
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="E-posta ara" class="field-input max-w-xs py-1.5" aria-label="E-posta ara">
                    <select name="status" class="field-input w-auto py-1.5" aria-label="Durum" data-autosubmit>
                        <option value="">Tüm durumlar</option>
                        @foreach (StoreNewsletterSubscriber::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-button size="sm" variant="secondary">Ara</x-button>
                    <span class="ml-auto flex gap-2">
                        <x-button size="sm" variant="secondary" icon="download" :href="route('store_admin.newsletter.export', array_filter(['format' => 'list', 'status' => $filters['status'] ?? null]))">CSV indir</x-button>
                        <x-button size="sm" variant="secondary" icon="download" :href="route('store_admin.newsletter.export', ['format' => 'iys'])">İYS dosyası</x-button>
                    </span>
                </form>
                @if ($subscribers->isEmpty())
                    <x-empty-state icon="users" title="Abone yok" text="Alt bilgideki formdan abone olunca burada görünür." />
                @else
                    <div class="overflow-x-auto">
                        <table class="table-base">
                            <thead><tr><th>E-posta</th><th>Kaynak</th><th>Onay</th><th>Durum</th><th></th></tr></thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($subscribers as $s)
                                    <tr>
                                        <td class="font-medium">{{ $s->email }}</td>
                                        <td class="text-sm text-muted">{{ StoreNewsletterSubscriber::SOURCES[$s->source] ?? $s->source }}</td>
                                        <td class="text-sm text-muted">{{ $s->consented_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</td>
                                        <td><x-badge :tone="$s->statusTone()">{{ $s->statusLabel() }}</x-badge></td>
                                        <td class="text-right">
                                            <form method="POST" action="{{ route('store_admin.newsletter.destroy', $s) }}" data-confirm="{{ $s->email }} listeden kalıcı olarak silinsin mi?">
                                                @csrf @method('DELETE')
                                                <button class="text-xs text-danger hover:underline">Sil</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($subscribers->hasPages())<div class="border-t border-line p-4">{{ $subscribers->links() }}</div>@endif
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Alt bilgideki e-bülten alanı" description="Her sayfanın altındaki kayıt şeridi.">
                <form method="POST" action="{{ route('store_admin.newsletter.settings') }}" class="space-y-4">
                    @csrf @method('PUT')
                    <x-toggle name="newsletter_enabled" label="E-bülten alanını göster" :checked="$settings['newsletter_enabled'] === '1'" />
                    <x-field name="newsletter_title" label="Başlık" :value="$settings['newsletter_title']" required maxlength="80" />
                    <x-field name="newsletter_text" label="Açıklama" :value="$settings['newsletter_text']" textarea maxlength="200" />
                    <x-field name="newsletter_consent" label="Onay metni" :value="$settings['newsletter_consent']" textarea required maxlength="255"
                        hint="Ziyaretçinin işaretlediği kutunun yanındaki metin; her abonelikte bu metin saklanır." />
                    <x-button size="sm" class="w-full justify-center">Kaydet</x-button>
                </form>
            </x-card>
            <x-card title="İYS hakkında">
                <p class="text-sm leading-relaxed text-muted">Ticari e-posta gönderen işletmelerin İleti Yönetim Sistemi'ne (iys.org.tr) kayıtlı olması ve aldığı onayları oraya bildirmesi gerekir. “İYS dosyası” onaylı ve ayrılan aboneleri İYS toplu yükleme sütunlarıyla indirir.</p>
            </x-card>
        </div>
    </div>
</x-layouts.panel>
